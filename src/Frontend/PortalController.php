<?php
declare(strict_types=1);

namespace Matchmaker\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class PortalController
 *
 * Handles the unified member portal shortcode [matchmaker_member_portal],
 * tab navigation, interactive match flow rendering, and AJAX match actions.
 *
 * @package Matchmaker\Frontend
 * @since   2.0.0
 */
class PortalController
{
    private static ?self $instance = null;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_shortcode('matchmaker_member_portal', [$this, 'render_portal']);
        add_shortcode('az_profile',                [$this, 'render_portal']); // Backward-compatibility alias

        // AJAX handlers matching member-portal.js action names
        add_action('wp_ajax_mm_submit_match_response', [$this, 'handle_ajax_match_response']);
        add_action('wp_ajax_mm_reload_tab_content',    [$this, 'handle_ajax_reload_tab']);
        add_action('wp_ajax_mm_portal_action',         [$this, 'handle_ajax_portal_action']);

        // Conditional asset enqueue
        add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
    }

    /**
     * Enqueue assets for portal (member-portal.css and member-portal.js)
     *
     * @return void
     */
    public function maybe_enqueue_assets(): void
    {
        global $post;

        if (!is_a($post, 'WP_Post')) {
            return;
        }

        $has_shortcode = has_shortcode($post->post_content, 'matchmaker_member_portal') || has_shortcode($post->post_content, 'az_profile');

        if (!$has_shortcode) {
            return;
        }

        $plugin_url = defined('MM_URL') ? MM_URL : plugin_dir_url(dirname(__FILE__, 3));
        $version    = defined('MM_VERSION') ? MM_VERSION : '2.0.0';

        wp_enqueue_style(
            'mm-member-portal-styles',
            $plugin_url . 'assets/css/member-portal.css',
            [],
            $version
        );

        wp_enqueue_script(
            'mm-member-portal-script',
            $plugin_url . 'assets/js/member-portal.js',
            ['jquery', 'heartbeat'],
            $version,
            true
        );

        wp_localize_script('mm-member-portal-script', 'mmPortalData', [
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('mm_portal_nonce'),
            'dashboardUrl'  => home_url('/dashboard/'),
            'membershipUrl' => home_url('/membership-account/'),
        ]);
    }

    /**
     * Render the single unified member portal dashboard [matchmaker_member_portal].
     *
     * @param array|string $atts
     * @return string
     */
    public function render_portal(array|string $atts = []): string
    {
        if (!is_user_logged_in()) {
            return '<div class="mm-portal-wrap"><div class="az-card"><p>'
                . esc_html__('Please log in to view your matchmaking member portal.', 'matchmaker')
                . '</p></div></div>';
        }

        $user_id  = get_current_user_id();
        $user_obj = wp_get_current_user();

        $repo = \Matchmaker\Repository\MatchRepository::instance();
        
        $pool = $repo->get_user_pool($user_id);

        if (!$pool) {
            return '<div class="mm-portal-wrap"><div class="az-card"><p>'
                . esc_html__('Your matchmaking profile has not been set up yet. Please complete your registration questionnaire.', 'matchmaker')
                . '</p></div></div>';
        }

        // Determine user membership tier
        $user_type = \Matchmaker\Service\ProfileService::instance()->get_user_type($user_id);
        $is_premium = in_array($user_type, ['monthly', 'one_on_one'], true);

        // Fetch meta, stats, and approved match records
        $meta          = $repo->get_meta_block($user_id);
        $stats         = $repo->get_match_stats($user_id);
        $matches       = $repo->find_approved_matches_for_user($user_id);
        $unread_count  = \Matchmaker\Service\NotificationService::instance()->get_user_unread_count($user_id);
        $photos        = $repo->get_user_photos($user_id);
        $dashboard_url = \Matchmaker\Service\ProfileService::instance()->get_dashboard_url();

        $data = [
            'user_id'       => $user_id,
            'user'          => $user_obj,
            'user_type'     => $user_type,
            'is_premium'    => $is_premium,
            'pool'          => $pool,
            'matches'       => $matches,
            'stats'         => $stats,
            'unread_count'  => $unread_count,
            'photos'        => $photos,
            'meta'          => $meta,
            'dashboard_url' => $dashboard_url,
            'repo'          => $repo,
        ];

        ob_start();
        extract($data);
        include __DIR__ . '/../View/frontend/portal/portal.php';
        return (string) ob_get_clean();
    }

    /**
     * AJAX handler for submitting match response (Accept / Decline) sent by member-portal.js
     *
     * @return void
     */
    public function handle_ajax_match_response(): void
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mm_portal_nonce')) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh the page.', 'matchmaker')]);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            wp_send_json_error(['message' => __('You must be logged in.', 'matchmaker')]);
        }

        $match_id = isset($_POST['match_id']) ? (int) $_POST['match_id'] : 0;
        $action   = isset($_POST['response_action']) ? sanitize_key((string) $_POST['response_action']) : '';

        if ($match_id <= 0 || !in_array($action, ['accept', 'decline'], true)) {
            wp_send_json_error(['message' => __('Invalid action request.', 'matchmaker')]);
        }

        $result = \Matchmaker\Service\MatchService::instance()->handle_match_response($match_id, $user_id, $action);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for dynamic tab reload (Profile or Matches) sent by member-portal.js
     *
     * @return void
     */
    public function handle_ajax_reload_tab(): void
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash((string) $_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'mm_portal_nonce')) {
            wp_send_json_error(['message' => __('Security check failed.', 'matchmaker')]);
        }

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            wp_send_json_error(['message' => __('User not logged in.', 'matchmaker')]);
        }

        $tab = isset($_POST['tab']) ? sanitize_key((string) $_POST['tab']) : 'profile';
        $user_type = \Matchmaker\Service\ProfileService::instance()->get_user_type($user_id);

        $html = ($tab === 'matches')
            ? $this->render_matches_html($user_id, $user_type)
            : $this->render_profile_html($user_id);

        wp_send_json_success(['html' => $html, 'tab' => $tab]);
    }

    /**
     * Legacy / unified fallback portal action handler
     *
     * @return void
     */
    public function handle_ajax_portal_action(): void
    {
        $action = isset($_POST['portal_action']) ? sanitize_text_field(wp_unslash((string) $_POST['portal_action'])) : '';
        if ($action === 'accept_match' || $action === 'decline_match') {
            $_POST['response_action'] = ($action === 'accept_match') ? 'accept' : 'decline';
            $this->handle_ajax_match_response();
        } elseif ($action === 'get_matches_html' || $action === 'get_profile_html') {
            $_POST['tab'] = ($action === 'get_matches_html') ? 'matches' : 'profile';
            $this->handle_ajax_reload_tab();
        } else {
            wp_send_json_error(['message' => __('Invalid action.', 'matchmaker')]);
        }
    }
    
    /**
     * Render the matches tab HTML
     *
     * @param int    $user_id
     * @param string $user_type
     * @return string
     */
    private function render_matches_html(int $user_id, string $user_type): string
    {
        $repo = \Matchmaker\Repository\MatchRepository::instance();
        $matches = $repo->find_approved_matches_for_user($user_id);
        $is_premium = in_array($user_type, ['monthly', 'one_on_one'], true);
        
        ob_start();
        include __DIR__ . '/../View/frontend/portal/tab-matches.php';
        return (string) ob_get_clean();
    }
    
    /**
     * Render the profile tab HTML
     *
     * @param int $user_id
     * @return string
     */
    private function render_profile_html(int $user_id): string
    {
        $repo = \Matchmaker\Repository\MatchRepository::instance();
        $user = wp_get_current_user();
        $user_type = \Matchmaker\Service\ProfileService::instance()->get_user_type($user_id);
        $pool = $repo->get_user_pool($user_id);
        $meta = $repo->get_meta_block($user_id);
        $stats = $repo->get_match_stats($user_id);
        $photos = $repo->get_user_photos($user_id);
        $is_premium = in_array($user_type, ['monthly', 'one_on_one'], true);
        $dashboard_url = \Matchmaker\Service\ProfileService::instance()->get_dashboard_url();
        
        ob_start();
        include __DIR__ . '/../View/frontend/portal/tab-profile.php';
        return (string) ob_get_clean();
    }
}
