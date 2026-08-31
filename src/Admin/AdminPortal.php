<?php
declare(strict_types=1);

namespace Matchmaker\Admin;

use Matchmaker\Repository\MatchRepository;
use Matchmaker\Service\MatchService;
use Matchmaker\Service\ProfileService;
use Matchmaker\Core\PMProSync;
use Matchmaker\Core\MatchingEngine;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AdminPortal
 *
 * Controller for the Matchmaking admin menu and sub-pages in WordPress wp-admin.
 * Coordinates actions and delegates presentation to pure PHP templates under src/View/admin/.
 *
 * @package Matchmaker\Admin
 * @since   2.4.0
 */
class AdminPortal
{
    private static ?self $instance = null;

    /**
     * Singleton instance getter
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
        $this->boot();
    }

    /**
     * Hook registration
     *
     * @return void
     */
    private function boot(): void
    {
        add_action('admin_menu',            [$this, 'register_menu'], 30);
        add_action('admin_init',            [$this, 'handle_admin_actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /**
     * Admin Asset Enqueue
     *
     * @param string $hook Page hook suffix.
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void
    {
        if (!str_contains($hook, 'matchmaking-pool') && !str_contains($hook, 'matchmaking-matches') && !str_contains($hook, 'matchmaking-settings') && !str_contains($hook, 'matchmaking-logs')) {
            return;
        }

        $plugin_url = defined('MM_URL') ? MM_URL : plugin_dir_url(dirname(__FILE__, 3));
        $version    = defined('MM_VERSION') ? MM_VERSION : '2.4.0';

        wp_enqueue_style(
            'mm-admin-styles',
            $plugin_url . 'assets/css/admin-matchmaker.css',
            [],
            $version
        );

        wp_enqueue_script(
            'mm-admin-script',
            $plugin_url . 'assets/js/admin-matchmaker.js',
            ['jquery'],
            $version,
            true
        );
    }

    /**
     * Register Admin Menu & Submenus
     *
     * @return void
     */
    public function register_menu(): void
    {
        add_menu_page(
            __('Matchmaking', 'matchmaker'),
            __('Matchmaking', 'matchmaker'),
            'manage_options',
            'matchmaking-pool',
            [$this, 'render_admin_page'],
            'dashicons-heart',
            30
        );

        add_submenu_page(
            'matchmaking-pool',
            __('Pool Browser', 'matchmaker'),
            __('Pool Browser', 'matchmaker'),
            'manage_options',
            'matchmaking-pool',
            [$this, 'render_admin_page']
        );

        add_submenu_page(
            'matchmaking-pool',
            __('Matches Queue', 'matchmaker'),
            __('Matches Queue', 'matchmaker'),
            'manage_options',
            'matchmaking-matches',
            [$this, 'render_matches_page']
        );

        add_submenu_page(
            'matchmaking-pool',
            __('Settings', 'matchmaker'),
            __('Settings', 'matchmaker'),
            'manage_options',
            'matchmaking-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'matchmaking-pool',
            __('Match Logs & Diagnostics', 'matchmaker'),
            __('Match Logs', 'matchmaker'),
            'manage_options',
            'matchmaking-logs',
            [$this, 'render_logs_page']
        );
    }

    /**
     * Handle Admin Actions: Approve / Reject / Trigger / Manual Match / Save Settings / Reset Test Data
     *
     * @return void
     */
    public function handle_admin_actions(): void
    {
        $page = sanitize_text_field(wp_unslash($_GET['page'] ?? ''));
        if (!in_array($page, ['matchmaking-pool', 'matchmaking-matches', 'matchmaking-settings', 'matchmaking-logs'], true)) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $repo = MatchRepository::instance();

        // 1. Reset Test Data POST Action
        if (isset($_POST['mm_reset_test_data'])) {
            check_admin_referer('mm_reset_test_data_nonce');

            if (!$repo->is_test_mode()) {
                add_settings_error('mm_admin_notices', 'reset_forbidden', __('Test data reset is only available when the system is in Test Mode.', 'matchmaker'), 'error');
                return;
            }

            $stats = $repo->reset_test_matchmaking_data();
            $msg = sprintf(
                __('Test matchmaking data reset successfully! Preserved %d candidate profiles. Cleared match records, notifications, and logs.', 'matchmaker'),
                $stats['profiles_preserved']
            );
            add_settings_error('mm_admin_notices', 'test_data_reset_success', $msg, 'updated');
            return;
        }

        // 2. Save settings POST action
        if (isset($_POST['mm_save_settings'])) {
            check_admin_referer('mm_save_settings_nonce');

            // Environment Mode
            $mode = sanitize_key((string) ($_POST['mm_environment_mode'] ?? 'live'));
            if (in_array($mode, ['test', 'live'], true)) {
                update_option('mm_environment_mode', $mode);
            }

            // PMPro Tier Mappings
            if (isset($_POST['mm_pmpro_levels']) && is_array($_POST['mm_pmpro_levels'])) {
                $new_mapping = [];
                foreach ($_POST['mm_pmpro_levels'] as $lvl_id => $tier_slug) {
                    $lvl  = (int) $lvl_id;
                    $tier = sanitize_key((string) $tier_slug);
                    if ($lvl > 0 && in_array($tier, ['monthly', 'one_on_one', 'event', 'free'], true)) {
                        $new_mapping[$lvl] = $tier;
                    }
                }
                update_option('mm_pmpro_tier_mapping', $new_mapping);
            }

            // Quotas & Expiry Rules
            $max_matches = max(1, (int) ($_POST['mm_max_cycle_matches'] ?? 10));
            update_option('mm_max_cycle_matches', $max_matches);

            $expiry_days = max(1, (int) ($_POST['mm_match_expiry_days'] ?? 7));
            update_option('mm_match_expiry_days', $expiry_days);

            $recurrence = max(1, (int) ($_POST['mm_auto_match_recurrence_days'] ?? 7));
            update_option('mm_auto_match_recurrence_days', $recurrence);

            $max_candidates = max(1, (int) ($_POST['mm_max_candidates_per_run'] ?? 10));
            update_option('mm_max_candidates_per_run', $max_candidates);

            // Page Routing & Integration
            update_option('mm_page_dashboard_id', (int) ($_POST['mm_page_dashboard_id'] ?? 0));
            update_option('mm_page_questionnaire_id', (int) ($_POST['mm_page_questionnaire_id'] ?? 0));
            update_option('mm_page_account_id', (int) ($_POST['mm_page_account_id'] ?? 0));
            update_option('mm_page_checkout_id', (int) ($_POST['mm_page_checkout_id'] ?? 0));
            update_option('mm_page_events_id', (int) ($_POST['mm_page_events_id'] ?? 0));

            if (isset($_POST['mm_free_reg_form_id'])) {
                update_option('mm_free_reg_form_id', sanitize_text_field(wp_unslash($_POST['mm_free_reg_form_id'])));
            }

            // Email Templates
            if (isset($_POST['mm_email_approval_subject'])) {
                update_option('mm_email_approval_subject', sanitize_text_field(wp_unslash($_POST['mm_email_approval_subject'])));
            }

            if (isset($_POST['mm_email_approval_template'])) {
                update_option('mm_email_approval_template', wp_kses_post(wp_unslash($_POST['mm_email_approval_template'])));
            }

            add_settings_error('mm_admin_notices', 'settings_saved', __('Settings saved successfully.', 'matchmaker'), 'updated');
            return;
        }

        // 3. GET Query Actions (Approve / Reject / Trigger / Manual Match)
        $action   = sanitize_text_field(wp_unslash($_GET['mm_action'] ?? ''));
        $match_id = (int) ($_GET['match_id'] ?? 0);
        $user_id  = (int) ($_GET['user_id'] ?? 0);
        $nonce    = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));

        if (empty($action)) {
            return;
        }

        // --- APPROVE MATCH ---
        if ($action === 'approve' && $match_id > 0 && wp_verify_nonce($nonce, 'mm_approve_' . $match_id)) {
            $admin_id = get_current_user_id();
            $result   = MatchService::instance()->process_admin_approve($match_id, $admin_id);

            if ($result['success']) {
                add_settings_error('mm_admin_notices', 'approved', $result['message'], 'updated');
            } else {
                add_settings_error('mm_admin_notices', 'approve_failed', $result['message'], 'error');
            }
            return;
        }

        // --- REJECT MATCH ---
        if ($action === 'reject' && $match_id > 0 && wp_verify_nonce($nonce, 'mm_reject_' . $match_id)) {
            $success = MatchService::instance()->process_admin_reject($match_id);
            if ($success) {
                add_settings_error('mm_admin_notices', 'rejected', __('Match rejected.', 'matchmaker'), 'updated');
            } else {
                add_settings_error('mm_admin_notices', 'reject_failed', __('Failed to reject match.', 'matchmaker'), 'error');
            }
            return;
        }

        // --- CREATE MANUAL MATCH PAIR ---
        if ($action === 'create_manual_match' && wp_verify_nonce($nonce, 'mm_manual_match')) {
            $u1 = (int) ($_GET['u1'] ?? 0);
            $u2 = (int) ($_GET['u2'] ?? 0);

            if ($u1 > 0 && $u2 > 0 && $u1 !== $u2) {
                if ($repo->has_mutual_match_this_month($u1) || $repo->has_mutual_match_this_month($u2)) {
                    add_settings_error('mm_admin_notices', 'mutual_exists', __('Cannot create match: one or both users already have a mutually accepted match this month.', 'matchmaker'), 'error');
                    return;
                }

                $pool1 = $repo->get_user_pool($u1);
                $pool2 = $repo->get_user_pool($u2);
                $score = ($pool1 && $pool2)
                    ? MatchService::instance()->compute_flexible_score($pool1, $pool2)
                    : 0;

                $admin_id = get_current_user_id();
                $inserted = $repo->create_match($u1, $u2, $admin_id, 'pending_review', 'manual', $score);

                if ($inserted) {
                    $repo->log_event(
                        'match_lifecycle',
                        'manual_match_created',
                        sprintf(__('Manual Match Created: User #%d & Candidate #%d', 'matchmaker'), $u1, $u2),
                        sprintf(__('Admin #%d created manual match pair (Match ID #%d, Score: %d/6).', 'matchmaker'), $admin_id, $inserted, $score),
                        ['u1' => $u1, 'u2' => $u2, 'score' => $score, 'admin_id' => $admin_id],
                        $inserted,
                        $admin_id,
                        null,
                        'info'
                    );
                    add_settings_error('mm_admin_notices', 'manual_created', __('Manual match pair created and queued for review.', 'matchmaker'), 'updated');
                } else {
                    add_settings_error('mm_admin_notices', 'manual_exists', __('Match pair already exists in the database.', 'matchmaker'), 'error');
                }
            }
            return;
        }

        // --- TRIGGER AUTO-MATCH SCORING ---
        if (($action === 'run_matching' || $action === 'trigger_match') && $user_id > 0 && wp_verify_nonce($nonce, 'mm_run_matching_' . $user_id)) {
            if ($repo->has_mutual_match_this_month($user_id)) {
                add_settings_error('mm_admin_notices', 'mutual_exists', sprintf(__('User #%d already has a mutually accepted match this month. Matching skipped.', 'matchmaker'), $user_id), 'error');
                return;
            }

            mm_enqueue_user_matching_job($user_id, 'admin_manual_trigger');
            add_settings_error('mm_admin_notices', 'job_queued', sprintf(__('Matching engine job queued for User #%d.', 'matchmaker'), $user_id), 'updated');
            return;
        }
    }

    /**
     * Render main Candidate Pool page
     *
     * @return void
     */
    public function render_admin_page(): void
    {
        $view_user    = isset($_GET['view_user']) ? (int) $_GET['view_user'] : 0;
        $manual_match = isset($_GET['manual_match']) ? (int) $_GET['manual_match'] : 0;

        echo '<div class="wrap mm-admin-wrap">';
        settings_errors('mm_admin_notices');

        if ($view_user > 0) {
            $this->render_single_user_view($view_user);
        } elseif ($manual_match > 0) {
            $this->render_manual_match_view($manual_match);
        } else {
            $this->render_pool_list_view();
        }

        echo '</div>';
    }

    /**
     * Render Candidate Pool List Table
     *
     * @return void
     */
    private function render_pool_list_view(): void
    {
        $repo = MatchRepository::instance();

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $gender = isset($_GET['filter_gender']) ? sanitize_text_field(wp_unslash($_GET['filter_gender'])) : '';
        $tier   = isset($_GET['filter_tier']) ? sanitize_text_field(wp_unslash($_GET['filter_tier'])) : '';

        $filters = [
            'search'    => $search,
            'gender'    => $gender,
            'user_type' => $tier,
        ];

        $candidates = $repo->search_pool($filters);

        require dirname(__DIR__) . '/View/admin/pool/pool-list.php';
    }

    /**
     * Render Single Candidate Profile Details View
     *
     * @param int $user_id
     * @return void
     */
    private function render_single_user_view(int $user_id): void
    {
        $repo        = MatchRepository::instance();
        $pool        = $repo->get_user_pool($user_id);
        $user_obj    = get_userdata($user_id);
        $meta        = $repo->get_meta_block($user_id);
        $all_matches = $repo->find_all_matches_for_user($user_id);
        $matches     = array_values(array_filter($all_matches, static function ($m) {
            $st = (string) ($m['status'] ?? '');
            return !in_array($st, ['expired', 'rejected', 'admin_rejected'], true);
        }));

        if (!$pool || !$user_obj) {
            echo '<p>' . esc_html__('User profile not found in matchmaking pool.', 'matchmaker') . '</p>';
            return;
        }

        $age          = $repo->calc_age($pool['birth_date'] ?? '');
        $height       = $repo->cm_to_feet((int) ($pool['height_cm'] ?? 0));
        $quota_used   = (int) get_user_meta($user_id, 'cycle_matches_count', true);
        $has_mutual   = $repo->has_mutual_match_this_month($user_id);

        $back_url     = admin_url('admin.php?page=matchmaking-pool');
        $manual_url   = admin_url("admin.php?page=matchmaking-pool&manual_match={$user_id}");
        $trigger_url  = wp_nonce_url(
            admin_url("admin.php?page=matchmaking-pool&mm_action=run_matching&user_id={$user_id}&view_user={$user_id}"),
            'mm_run_matching_' . $user_id
        );

        require dirname(__DIR__) . '/View/admin/pool/user-single.php';
    }

    /**
     * Render manual matchmaking tool
     *
     * @param int $user_id
     * @return void
     */
    private function render_manual_match_view(int $user_id): void
    {
        $repo     = MatchRepository::instance();
        $pool     = $repo->get_user_pool($user_id);
        $user_obj = get_userdata($user_id);
        $meta     = $repo->get_meta_block($user_id);

        if (!$pool || !$user_obj) {
            echo '<p>' . esc_html__('User profile not found.', 'matchmaker') . '</p>';
            return;
        }

        $user_age   = $repo->calc_age($pool['birth_date'] ?? '');
        $quota_used = (int) get_user_meta($user_id, 'cycle_matches_count', true);

        // Smart default candidate gender (opposite of user gender if pref_gender not specified)
        $pref_g = trim((string)($pool['pref_gender'] ?? ''));
        if (empty($pref_g) || strtolower($pref_g) === 'any') {
            $user_g = strtolower(trim((string)($pool['gender'] ?? '')));
            $pref_g = ($user_g === 'male') ? 'female' : (($user_g === 'female') ? 'male' : 'any');
        }

        $f_gender   = sanitize_text_field(wp_unslash($_GET['f_gender']   ?? $pref_g));
        $f_age_min  = isset($_GET['f_age_min'])  ? (int) $_GET['f_age_min']  : (int) ($pool['preferred_age_min'] ?? 18);
        $f_age_max  = isset($_GET['f_age_max'])  ? (int) $_GET['f_age_max']  : (int) ($pool['preferred_age_max'] ?? 80);
        $f_location = sanitize_text_field(wp_unslash($_GET['f_location'] ?? $pool['pref_location'] ?? ''));
        $f_origin   = sanitize_text_field(wp_unslash($_GET['f_origin']   ?? $pool['pref_origin'] ?? ''));
        $f_religion = sanitize_text_field(wp_unslash($_GET['f_religion'] ?? $pool['pref_religion'] ?? ''));
        $f_modesty  = sanitize_text_field(wp_unslash($_GET['f_modesty']  ?? $pool['pref_modesty'] ?? ''));

        $filters = [
            'f_gender'   => $f_gender,
            'f_age_min'  => $f_age_min,
            'f_age_max'  => $f_age_max,
            'f_location' => $f_location,
            'f_origin'   => $f_origin,
            'f_religion' => $f_religion,
            'f_modesty'  => $f_modesty,
        ];

        $candidates = $repo->get_manual_match_candidates($user_id, $pool, $filters);
        $back_url   = admin_url('admin.php?page=matchmaking-pool&view_user=' . $user_id);
        $reset_url  = admin_url('admin.php?page=matchmaking-pool&manual_match=' . $user_id);

        require dirname(__DIR__) . '/View/admin/pool/manual-match.php';
    }

    /**
     * Render Global Matches Queue Page
     *
     * @return void
     */
    public function render_matches_page(): void
    {
        $view_match = isset($_GET['view_match']) ? (int) $_GET['view_match'] : 0;

        echo '<div class="wrap mm-admin-wrap">';
        settings_errors('mm_admin_notices');

        if ($view_match > 0) {
            $this->render_single_match_view($view_match);
        } else {
            $this->render_matches_list_view();
        }

        echo '</div>';
    }

    /**
     * Render Matches Queue List Table
     *
     * @return void
     */
    private function render_matches_list_view(): void
    {
        $repo = MatchRepository::instance();

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status = isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : '';
        $source = isset($_GET['filter_source']) ? sanitize_text_field(wp_unslash($_GET['filter_source'])) : '';

        $filters = [
            'search' => $search,
            'status' => $status,
            'source' => $source,
        ];

        $matches = $repo->search_matches($filters);

        require dirname(__DIR__) . '/View/admin/matches/matches-list.php';
    }

    /**
     * Render Side-by-Side Dual Profile Comparison View for a Single Match Record
     *
     * @param int $match_id
     * @return void
     */
    private function render_single_match_view(int $match_id): void
    {
        $repo  = MatchRepository::instance();
        $match = $repo->find_match_by_id($match_id);

        if (!$match) {
            echo '<p>' . esc_html__('Match record not found.', 'matchmaker') . '</p>';
            return;
        }

        $u1_id = (int) $match['user_one_id'];
        $u2_id = (int) $match['user_two_id'];

        $u1 = get_userdata($u1_id);
        $u2 = get_userdata($u2_id);
        $p1 = $repo->get_user_pool($u1_id);
        $p2 = $repo->get_user_pool($u2_id);
        $m1 = $repo->get_meta_block($u1_id);
        $m2 = $repo->get_meta_block($u2_id);

        $back_url    = admin_url('admin.php?page=matchmaking-matches');
        $approve_url = wp_nonce_url(admin_url('admin.php?page=matchmaking-matches&mm_action=approve&match_id=' . $match_id), 'mm_approve_' . $match_id);
        $reject_url  = wp_nonce_url(admin_url('admin.php?page=matchmaking-matches&mm_action=reject&match_id=' . $match_id), 'mm_reject_' . $match_id);
        $st          = (string) $match['status'];

        require dirname(__DIR__) . '/View/admin/matches/match-single.php';
    }

    /**
     * Render plugin settings page
     *
     * @return void
     */
    public function render_settings_page(): void
    {
        $repo        = MatchRepository::instance();
        $pmpro_sync  = PMProSync::instance();

        // 0. Environment Mode
        $environment_mode = $repo->get_environment_mode();
        $is_test_mode     = $repo->is_test_mode();

        // 1. PMPro Mapping
        $current_mapping = $pmpro_sync->get_tier_mapping();
        $pmpro_levels    = function_exists('pmpro_getAllLevels') ? pmpro_getAllLevels(true, true) : [];

        // 2. Quotas & Expiry
        $max_matches     = $repo->get_max_cycle_matches();
        $expiry_days     = $repo->get_match_expiry_days();
        $recurrence      = (int) get_option('mm_auto_match_recurrence_days', 7);
        $max_candidates  = (int) get_option('mm_max_candidates_per_run', 10);

        // 3. Page Routing
        $page_dashboard     = (int) get_option('mm_page_dashboard_id', 0);
        $page_questionnaire = (int) get_option('mm_page_questionnaire_id', 0);
        $page_account       = (int) get_option('mm_page_account_id', 0);
        $page_checkout      = (int) get_option('mm_page_checkout_id', 0);
        $page_events        = (int) get_option('mm_page_events_id', 0);
        $free_form_id       = (string) get_option('mm_free_reg_form_id', '2784843');

        // 4. Email Template
        $default_subject = __('Congratulations! You have a new approved match on Arab Zawaj', 'matchmaker');
        $default_template = "<p>Dear {user_name},</p>\n"
            . "<p>Great news! Our matchmakers have reviewed and approved a new profile match for you.</p>\n"
            . "<p><strong>Candidate Details:</strong><br>\n"
            . "Name: {candidate_name}<br>\n"
            . "Age: {candidate_age}<br>\n"
            . "Location: {candidate_location}</p>\n"
            . "<p>Please log in to your portal to review their full profile and respond:</p>\n"
            . "<p><a href=\"{dashboard_url}\" style=\"background:#CC723F;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;\">View Match Details &rarr;</a></p>\n"
            . "<p>Warm regards,<br>Arab Zawaj Matchmaking Team</p>";

        $subject  = (string) get_option('mm_email_approval_subject', $default_subject);
        $template = (string) get_option('mm_email_approval_template', $default_template);

        require dirname(__DIR__) . '/View/admin/settings/settings.php';
    }

    /**
     * Render Matchmaking Logs, Notifications & Debugger Page
     *
     * @return void
     */
    public function render_logs_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'matchmaker'));
        }

        global $wpdb;
        $repo          = MatchRepository::instance();
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';
        $matches_table = $wpdb->prefix . 'matches';

        $active_tab = sanitize_key($_GET['tab'] ?? 'match_logs');
        if (!in_array($active_tab, ['match_logs', 'notification_logs', 'debugger'], true)) {
            $active_tab = 'match_logs';
        }

        $tab_data = [];

        if ($active_tab === 'debugger') {
            // Live Matching Trigger for Audit
            $run_notice = '';
            if (isset($_POST['mm_run_live_user_id'])) {
                check_admin_referer('mm_run_live_matching_nonce');
                $target_uid = (int) $_POST['mm_run_live_user_id'];
                if ($target_uid > 0) {
                    $before_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$matches_table}");
                    MatchingEngine::instance()->run_matching_for_user($target_uid, 'admin_manual_trigger');
                    $after_count  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$matches_table}");
                    $new_inserted = $after_count - $before_count;
                    $run_notice   = sprintf(__('Executed matching for User #%d! New match pairs created in database: %d.', 'matchmaker'), $target_uid, $new_inserted);
                }
            }

            $selected_user_id = (int) ($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
            if ($selected_user_id <= 0) {
                $selected_user_id = (int) $wpdb->get_var("SELECT user_id FROM {$pool_table} WHERE (is_active = 1 OR is_active IS NULL) ORDER BY user_id ASC LIMIT 1");
            }

            $all_pool_users = $wpdb->get_results(
                "SELECT p.user_id, p.gender, p.user_type, u.display_name, u.user_email
                 FROM {$pool_table} p
                 LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
                 ORDER BY p.user_id ASC",
                ARRAY_A
            );

            $target_user    = $selected_user_id > 0 ? $repo->get_user_pool($selected_user_id) : null;
            $target_wp_user = $selected_user_id > 0 ? get_userdata($selected_user_id) : null;

            $tab_data = [
                'selected_user_id' => $selected_user_id,
                'all_pool_users'   => $all_pool_users,
                'target_user'      => $target_user,
                'target_wp_user'   => $target_wp_user,
                'run_notice'       => $run_notice,
            ];
        } else {
            // Pagination & Filtering for Match Logs or Notification Logs
            $search       = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
            $event_type   = isset($_GET['event_type']) ? sanitize_text_field(wp_unslash($_GET['event_type'])) : '';
            $current_page = max(1, (int) ($_GET['paged'] ?? 1));
            $per_page     = 25;
            $offset       = ($current_page - 1) * $per_page;

            $log_type_filter = ($active_tab === 'notification_logs') ? 'notification,email' : 'match_lifecycle,match_engine';

            $logs        = $repo->get_logs($log_type_filter, $per_page, $offset, $search, $event_type);
            $total_logs  = $repo->get_logs_count($log_type_filter, $search, $event_type);
            $total_pages = (int) ceil($total_logs / $per_page);

            $tab_data = [
                'logs'         => $logs,
                'total_logs'   => $total_logs,
                'current_page' => $current_page,
                'total_pages'  => $total_pages,
                'search'       => $search,
                'event_type'   => $event_type,
            ];
        }

        require dirname(__DIR__) . '/View/admin/logs/logs.php';
    }
}
