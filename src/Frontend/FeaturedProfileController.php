<?php
declare(strict_types=1);

namespace Matchmaker\Frontend;

use Matchmaker\Core\PMProSync;
use Matchmaker\Repository\MatchRepository;
use Matchmaker\Service\FileLoggerService;
use Matchmaker\Service\NotificationService;
use Matchmaker\Service\ProfileService;

/**
 * Controller: Featured Profile Management, Shortcodes & Application Workflow.
 *
 * Handles:
 * - Synchronization of the existing ACF CPT 'featured-profile' and field 'connected-member'.
 * - Elementor Loop Grid custom query filter (mm_featured_profiles).
 * - Public/anonymous visibility toggling in Member Portal.
 * - Application for match AJAX submission and admin alert workflow.
 * - Shortcodes: [featured_profile_details], [featured_profile_apply_button], [featured_profile_field].
 *
 * @package Matchmaker\Frontend
 */
final class FeaturedProfileController
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Elementor Loop Grid custom query filter
        add_action('elementor/query/mm_featured_profiles', [$this, 'filter_elementor_featured_profiles']);
        add_action('elementor/query/featured_profiles',    [$this, 'filter_elementor_featured_profiles']);

        // Public & Member Shortcodes
        add_shortcode('featured_profile_details', [$this, 'render_featured_profile_details']);
        add_shortcode('featured_profile_apply_button', [$this, 'render_featured_profile_apply_button']);
        add_shortcode('featured_profile_field', [$this, 'render_featured_profile_field']);

        // Member Portal AJAX: Toggle Featured Status & Visibility Mode
        add_action('wp_ajax_mm_toggle_featured_profile', [$this, 'handle_toggle_featured_profile']);

        // Public/Member AJAX: Apply for a Match with a Featured Member
        add_action('wp_ajax_mm_apply_featured_match', [$this, 'handle_apply_featured_match']);
        add_action('wp_ajax_nopriv_mm_apply_featured_match', [$this, 'handle_apply_featured_match_guest']);

        // Frontend Assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }

    /**
     * Enqueues frontend styles and JavaScript for the Featured Profiles and Apply flow.
     */
    public function enqueue_frontend_assets(): void
    {
        $min_ver    = defined('MM_VERSION') ? MM_VERSION : '2.10.0';
        $plugin_url = defined('MM_URL') ? MM_URL : (function_exists('plugin_dir_url') ? plugin_dir_url(dirname(__DIR__, 2) . '/matchmaker.php') : '');

        // Enqueue member portal styles for complete lightbox modal & component styling
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                'mm-member-portal-styles',
                $plugin_url . 'assets/css/member-portal.css',
                [],
                $min_ver
            );
        }

        // Localized config for apply buttons & portal toggles
        if (function_exists('wp_register_script')) {
            wp_register_script(
                'mm-featured-profiles',
                $plugin_url . 'assets/js/featured-profiles.js',
                ['jquery'],
                $min_ver,
                true
            );
        }


        $current_user_id = get_current_user_id();
        $has_base        = false;
        if ($current_user_id > 0) {
            $user_type = PMProSync::instance()->get_current_user_type($current_user_id);
            $has_base  = in_array($user_type, ['free', 'monthly', 'event'], true);
        }

        wp_localize_script('mm-featured-profiles', 'mmFeaturedConfig', [
            'ajax_url'     => admin_url('admin-ajax.php'),
            'apply_nonce'  => wp_create_nonce('mm_featured_apply_nonce'),
            'toggle_nonce' => wp_create_nonce('mm_featured_toggle_nonce'),
            'is_logged_in' => is_user_logged_in(),
            'has_base'     => $has_base,
            'login_url'    => wp_login_url(get_permalink(get_the_ID() ?: 0)),
            'checkout_url' => ProfileService::instance()->get_membership_checkout_url(),
            'i18n'         => [
                'submitting'      => __('Submitting application...', 'matchmaker'),
                'success'         => __('Application sent! The matchmaker team is reviewing your request.', 'matchmaker'),
                'error_generic'   => __('Unable to submit application. Please try again.', 'matchmaker'),
                'login_required'  => __('Please log in with your membership account to apply.', 'matchmaker'),
                'saving_settings' => __('Updating featured profile...', 'matchmaker'),
                'saved_success'   => __('Featured profile settings saved!', 'matchmaker'),
            ],
        ]);

        wp_enqueue_script('mm-featured-profiles');
    }

    /**
     * Resolves the connected WordPress User ID from a featured-profile post.
     *
     * @param int $post_id
     * @return int Connected User ID or 0 if not found.
     */
    public function get_connected_user_id(int $post_id): int
    {
        if ($post_id <= 0) {
            return 0;
        }

        // 1. Try ACF get_field
        if (function_exists('get_field')) {
            $val = get_field('connected-member', $post_id);
            if (!empty($val)) {
                if (is_array($val) && isset($val['ID'])) {
                    return (int) $val['ID'];
                }
                if (is_object($val) && isset($val->ID)) {
                    return (int) $val->ID;
                }
                if (is_numeric($val) && (int) $val > 0) {
                    return (int) $val;
                }
            }

            // Also check alternate underscore name
            $val_under = get_field('connected_member', $post_id);
            if (!empty($val_under)) {
                if (is_array($val_under) && isset($val_under['ID'])) {
                    return (int) $val_under['ID'];
                }
                if (is_object($val_under) && isset($val_under->ID)) {
                    return (int) $val_under->ID;
                }
                if (is_numeric($val_under) && (int) $val_under > 0) {
                    return (int) $val_under;
                }
            }
        }

        // 2. Direct post meta lookup fallback
        $meta_val = get_post_meta($post_id, 'connected-member', true);
        if (is_numeric($meta_val) && (int) $meta_val > 0) {
            return (int) $meta_val;
        }

        $meta_val_under = get_post_meta($post_id, 'connected_member', true);
        if (is_numeric($meta_val_under) && (int) $meta_val_under > 0) {
            return (int) $meta_val_under;
        }

        return 0;
    }

    /**
     * Finds an existing 'featured-profile' post ID for a user.
     *
     * @param int $user_id
     * @return int Post ID or 0 if none found.
     */
    public function get_user_featured_post_id(int $user_id): int
    {
        if ($user_id <= 0) {
            return 0;
        }

        $cached_id = (int) get_user_meta($user_id, '_mm_featured_post_id', true);
        if ($cached_id > 0) {
            return $cached_id;
        }

        $args = [
            'post_type'      => 'featured-profile',
            'post_status'    => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => 'connected-member',
                    'value'   => $user_id,
                    'compare' => '=',
                ],
                [
                    'key'     => 'connected_member',
                    'value'   => $user_id,
                    'compare' => '=',
                ],
            ],
        ];

        $q = new \WP_Query($args);
        if (!empty($q->posts) && is_array($q->posts)) {
            $first = $q->posts[0];
            $found_id = is_object($first) ? (int) ($first->ID ?? 0) : (int) $first;
            if ($found_id > 0) {
                update_user_meta($user_id, '_mm_featured_post_id', $found_id);
                return $found_id;
            }
        }

        return 0;
    }

    /**
     * Creates or updates a member's 'featured-profile' CPT post and synchronization metadata.
     *
     * @param int    $user_id
     * @param bool   $enabled
     * @param string $visibility_mode 'anonymous' or 'public'.
     * @return int|false Post ID on success, false on error.
     */
    public function sync_featured_post(int $user_id, bool $enabled, string $visibility_mode = 'anonymous', string $display_name = ''): int|false
    {
        if ($user_id <= 0) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $visibility_mode = in_array($visibility_mode, ['public', 'anonymous'], true) ? $visibility_mode : 'anonymous';
        $post_id         = $this->get_user_featured_post_id($user_id);
        $post_status     = $enabled ? 'publish' : 'draft';

        if ($display_name !== '') {
            update_user_meta($user_id, 'mm_featured_anonymous_name', $display_name);
        }

        // Title format
        if ($visibility_mode === 'public') {
            $post_title = $user->display_name . ' (' . __('Featured Profile', 'matchmaker') . ')';
        } else {
            $post_title = $this->get_anonymous_title($user_id, true);
        }

        if ($post_id > 0) {
            wp_update_post([
                'ID'          => $post_id,
                'post_title'  => $post_title,
                'post_status' => $post_status,
            ]);
        } else {
            $post_id = wp_insert_post([
                'post_title'  => $post_title,
                'post_type'   => 'featured-profile',
                'post_status' => $post_status,
            ]);
        }

        if (!$post_id || is_wp_error($post_id)) {
            return false;
        }

        $post_id = (int) $post_id;

        // Synchronize ACF & meta fields
        if (function_exists('update_field')) {
            update_field('connected-member', $user_id, $post_id);
            update_field('connected_member', $user_id, $post_id);
        }
        update_post_meta($post_id, 'connected-member', $user_id);
        update_post_meta($post_id, 'connected_member', $user_id);
        update_post_meta($post_id, '_mm_visibility_mode', $visibility_mode);
        if ($display_name !== '') {
            update_post_meta($post_id, '_mm_anonymous_name', $display_name);
        }

        // Store user state
        update_user_meta($user_id, 'mm_featured_enabled', $enabled ? 1 : 0);
        update_user_meta($user_id, 'mm_featured_visibility', $visibility_mode);
        update_user_meta($user_id, '_mm_featured_post_id', $post_id);


        if (class_exists(FileLoggerService::class)) {
            FileLoggerService::info(
                'Featured profile synced for user #' . $user_id . ': enabled=' . ($enabled ? '1' : '0') . ', visibility=' . $visibility_mode . ', post_id=' . $post_id . '.',
                ['user_id' => $user_id, 'enabled' => $enabled, 'visibility' => $visibility_mode, 'post_id' => $post_id],
                'profile'
            );
        }

        return $post_id;
    }

    /**
     * Invoked when a member's Featured Profile service expires or is cancelled.
     * Automatically unpublishes (drafts) their featured profile post.
     *
     * @param int $user_id
     * @return void
     */
    public function handle_service_revoked(int $user_id): void
    {
        if ($user_id <= 0) {
            return;
        }

        $enabled   = (int) get_user_meta($user_id, 'mm_featured_enabled', true);
        $cached_id = (int) get_user_meta($user_id, '_mm_featured_post_id', true);

        // Fast bail if the user never had a featured profile enabled and has no associated post ID
        if ($enabled === 0 && $cached_id === 0) {
            return;
        }

        $post_id = $this->get_user_featured_post_id($user_id);
        if ($post_id > 0) {
            wp_update_post([
                'ID'          => $post_id,
                'post_status' => 'draft',
            ]);
        }
        update_user_meta($user_id, 'mm_featured_enabled', 0);

        if (class_exists(FileLoggerService::class)) {
            FileLoggerService::info(
                'Featured profile revoked / set to draft for user #' . $user_id . ' (service no longer active).',
                ['user_id' => $user_id, 'post_id' => $post_id],
                'pmpro'
            );
        }
    }

    /**
     * Filter Elementor Loop Grid queries with Query ID 'mm_featured_profiles'.
     *
     * @param \WP_Query $query
     * @return void
     */
    public function filter_elementor_featured_profiles(\WP_Query $query): void
    {
        $query->set('post_type', 'featured-profile');
        $query->set('post_status', 'publish');

        // Only include posts where connected member has active pool profile
        // Elementor loop handles post querying cleanly
    }

    /**
     * Resolves display title for anonymous featured profile.
     * Uses member's custom alias if set; otherwise falls back to Candidate #{id}.
     *
     * @param int $user_id
     * @param bool $with_suffix
     * @return string
     */
    public function get_anonymous_title(int $user_id, bool $with_suffix = false): string
    {
        $custom_name = trim((string) get_user_meta($user_id, 'mm_featured_anonymous_name', true));
        if ($custom_name === '') {
            $post_id = $this->get_user_featured_post_id($user_id);
            if ($post_id > 0) {
                $custom_name = trim((string) get_post_meta($post_id, '_mm_anonymous_name', true));
            }
        }

        $title = ($custom_name !== '')
            ? $custom_name
            : sprintf(__('Candidate #%d', 'matchmaker'), $user_id);

        if ($with_suffix) {
            $title .= ' (' . __('Featured Profile', 'matchmaker') . ')';
        }

        return $title;
    }

    /**
     * Resolves the public or anonymous display name of a featured profile member.
     *
     * @param int $user_id
     * @return string
     */
    public function get_featured_profile_display_name(int $user_id): string
    {
        $user = get_userdata($user_id);
        if (!$user) {
            return 'Member #' . $user_id;
        }

        $visibility = (string) get_user_meta($user_id, 'mm_featured_visibility', true) ?: 'anonymous';
        if ($visibility === 'anonymous') {
            return $this->get_anonymous_title($user_id, false);
        }

        return $user->display_name;
    }

    /**
     * Parses width, height, dimensions, radius, and fit parameters from shortcode attributes.
     *
     * @param array<string, mixed> $atts
     * @param string               $default_width
     * @param string               $default_height
     * @param string               $default_radius
     * @return array{width: string, height: string, radius: string, fit: string, size_num: int}
     */
    public static function parse_image_dimensions(
        array $atts,
        string $default_width = '100%',
        string $default_height = 'auto',
        string $default_radius = '12px'
    ): array {
        $width     = '';
        $height    = '';
        $radius    = '';
        $fit       = 'cover';
        $size_num  = 0;

        // 1. Dimensions or size attribute e.g. "200x300", "150", "thumbnail"
        $dim_str = !empty($atts['dimensions']) ? trim((string) $atts['dimensions']) : (!empty($atts['size']) ? trim((string) $atts['size']) : '');
        if ($dim_str !== '') {
            if (preg_match('/^(\d+)\s*[xX*×]\s*(\d+)$/', $dim_str, $matches)) {
                $width    = $matches[1] . 'px';
                $height   = $matches[2] . 'px';
                $size_num = (int) $matches[1];
            } elseif (ctype_digit($dim_str)) {
                $width    = $dim_str . 'px';
                $height   = $dim_str . 'px';
                $size_num = (int) $dim_str;
            } elseif (in_array(strtolower($dim_str), ['thumb', 'thumbnail'], true)) {
                $width    = '150px';
                $height   = '150px';
                $size_num = 150;
            } elseif (strtolower($dim_str) === 'medium') {
                $width    = '300px';
                $height   = '300px';
                $size_num = 300;
            } elseif (strtolower($dim_str) === 'large') {
                $width    = '800px';
                $height   = '800px';
                $size_num = 800;
            } elseif (strtolower($dim_str) === 'full') {
                $width    = '100%';
                $height   = 'auto';
            }
        }

        // 2. Explicit width attribute overrides
        if (!empty($atts['width'])) {
            $w = trim((string) $atts['width']);
            $width = is_numeric($w) ? $w . 'px' : $w;
            if (is_numeric($w) && $size_num === 0) {
                $size_num = (int) $w;
            }
        }

        // 3. Explicit height attribute overrides
        if (!empty($atts['height'])) {
            $h = trim((string) $atts['height']);
            $height = is_numeric($h) ? $h . 'px' : $h;
        }

        // Fallbacks
        if ($width === '') {
            $width = $default_width;
        }
        if ($height === '') {
            $height = $default_height;
        }

        // 4. Border radius attribute e.g. "8px", "50%", "round", "circle"
        $rad_raw = !empty($atts['radius']) ? trim((string) $atts['radius']) : (!empty($atts['border_radius']) ? trim((string) $atts['border_radius']) : '');
        if ($rad_raw !== '') {
            if (in_array(strtolower($rad_raw), ['circle', 'round', 'full'], true)) {
                $radius = '50%';
            } elseif (is_numeric($rad_raw)) {
                $radius = $rad_raw . 'px';
            } else {
                $radius = $rad_raw;
            }
        } else {
            $radius = $default_radius;
        }

        // 5. Object fit attribute
        $fit_raw = !empty($atts['fit']) ? trim((string) $atts['fit']) : (!empty($atts['object_fit']) ? trim((string) $atts['object_fit']) : '');
        if ($fit_raw !== '' && in_array(strtolower($fit_raw), ['cover', 'contain', 'fill', 'none', 'scale-down'], true)) {
            $fit = strtolower($fit_raw);
        }

        return [
            'width'    => $width,
            'height'   => $height,
            'radius'   => $radius,
            'fit'      => $fit,
            'size_num' => $size_num,
        ];
    }

    /**
     * Helper to get anonymous avatar image or silhouette SVG markup.
     *
     * @param int    $size
     * @param string $width
     * @param string $height
     * @param string $radius
     * @param string $class
     * @param string $style
     * @return string
     */
    public function get_anonymous_avatar_html(
        int $size = 180,
        string $width = '',
        string $height = '',
        string $radius = '',
        string $class = '',
        string $style = ''
    ): string {
        $w_style = $width !== '' ? $width : ($size > 0 ? $size . 'px' : '100%');
        $h_style = $height !== '' ? $height : ($size > 0 ? $size . 'px' : 'auto');
        $r_style = $radius !== '' ? $radius : '14px';

        $custom_class = trim('mm-anonymous-avatar ' . $class);
        $custom_img   = trim((string) get_option('mm_featured_profile_default_image', ''));
        if ($custom_img !== '') {
            return '<div class="' . esc_attr($custom_class . ' mm-anonymous-custom-img-wrap') . '" style="width:' . esc_attr($w_style) . ';height:' . esc_attr($h_style) . ';border-radius:' . esc_attr($r_style) . ';overflow:hidden;margin:0 auto;box-shadow:0 2px 10px rgba(0,0,0,0.08);' . esc_attr($style) . '" title="' . esc_attr__('Anonymous Featured Profile', 'matchmaker') . '">
                <img src="' . esc_url($custom_img) . '" alt="' . esc_attr__('Anonymous Candidate', 'matchmaker') . '" class="mm-anonymous-avatar-img" style="width:100%;height:100%;object-fit:cover;display:block;border-radius:inherit;">
            </div>';
        }

        // SVG sizing
        $svg_size = $size > 0 ? (int) ($size * 0.5) : 48;
        if (preg_match('/^(\d+)/', $w_style, $wm) && preg_match('/^(\d+)/', $h_style, $hm)) {
            $min_dim = min((int) $wm[1], (int) $hm[1]);
            if ($min_dim > 0) {
                $svg_size = max(16, (int) ($min_dim * 0.45));
            }
        }

        return '<div class="' . esc_attr($custom_class) . '" style="width:' . esc_attr($w_style) . ';height:' . esc_attr($h_style) . ';border-radius:' . esc_attr($r_style) . ';background:#f1f5f9;display:flex;align-items:center;justify-content:center;color:#94a3b8;border:2px dashed #cbd5e1;margin:0 auto;' . esc_attr($style) . '" title="' . esc_attr__('Anonymous Featured Profile', 'matchmaker') . '">
            <svg width="' . $svg_size . '" height="' . $svg_size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
        </div>';
    }

    /**
     * Shortcode [featured_profile_details]
     *
     * Renders detailed profile card respecting anonymous vs public visibility mode.
     *
     * @param array<string, mixed>|string $atts
     * @return string HTML output
     */
    public function render_featured_profile_details(array|string $atts = []): string
    {
        $this->enqueue_frontend_assets();

        $raw_atts = is_array($atts) ? $atts : [];
        $atts = shortcode_atts([
            'post_id'      => 0,
            'photo_width'  => '',
            'photo_height' => '',
            'photo_size'   => '',
            'dimensions'   => '',
            'width'        => '',
            'height'       => '',
            'size'         => '',
        ], $raw_atts);

        $post_id = (int) $atts['post_id'];
        if ($post_id <= 0) {
            $post_id = get_the_ID() ?: 0;
        }

        if ($post_id <= 0) {
            return '';
        }

        $user_id = $this->get_connected_user_id($post_id);
        if ($user_id <= 0) {
            return '';
        }

        $repo       = MatchRepository::instance();
        $pool       = $repo->get_user_pool($user_id);
        $meta       = $repo->get_meta_block($user_id);
        $user       = get_userdata($user_id);
        $visibility = get_post_meta($post_id, '_mm_visibility_mode', true);
        if (empty($visibility)) {
            $visibility = (string) get_user_meta($user_id, 'mm_featured_visibility', true) ?: 'anonymous';
        }

        $is_anonymous = ($visibility === 'anonymous');
        $display_name = $is_anonymous
            ? $this->get_anonymous_title($user_id, false)
            : ($user ? $user->display_name : sprintf(__('Member #%d', 'matchmaker'), $user_id));

        // Candidate photos
        $photo1 = !empty($meta['user_photo1']) ? $meta['user_photo1'] : (!empty($pool['user_photo1']) ? $pool['user_photo1'] : (string) get_user_meta($user_id, 'user_photo1', true));
        $photo2 = !empty($meta['user_photo2']) ? $meta['user_photo2'] : (!empty($pool['user_photo2']) ? $pool['user_photo2'] : (string) get_user_meta($user_id, 'user_photo2', true));
        $photo3 = !empty($meta['user_photo3']) ? $meta['user_photo3'] : (!empty($pool['user_photo3']) ? $pool['user_photo3'] : (string) get_user_meta($user_id, 'user_photo3', true));
        $user_photos = array_values(array_filter([$photo1, $photo2, $photo3]));
        $user_photos_attr = !empty($user_photos) ? esc_attr(implode(',', $user_photos)) : '';

        // Candidate self-profile criteria (excluding partner preferences and private contact details)
        $age          = $repo->calc_age($pool['birth_date'] ?? '');
        $gender       = !empty($pool['gender']) ? ucfirst($pool['gender']) : '—';
        $height       = $repo->cm_to_feet((int) ($pool['height_cm'] ?? 0));
        $u_loc_parts  = array_filter([$pool['city'] ?? '', $pool['state'] ?? '', $pool['country'] ?? '']);
        $location     = !empty($u_loc_parts) ? implode(', ', $u_loc_parts) : ($pool['location'] ?? '—');
        $citizenship  = !empty($meta['user_citizenship']) ? $meta['user_citizenship'] : '—';
        $origin       = !empty($pool['origin']) ? $pool['origin'] : '—';
        $religion     = !empty($pool['religion']) ? $pool['religion'] : '—';
        $modesty      = !empty($pool['modesty']) ? $pool['modesty'] : '—';
        $rel_modesty  = ($religion !== '—' || $modesty !== '—') ? "{$religion} / {$modesty}" : '—';
        $languages    = !empty($pool['languages']) ? $pool['languages'] : '—';
        $career       = !empty($pool['job']) ? $pool['job'] : '—';
        $smoking      = !empty($pool['smoking']) ? $pool['smoking'] : '—';
        $drinking     = !empty($pool['drinking']) ? $pool['drinking'] : '—';
        $lifestyle    = ($smoking !== '—' || $drinking !== '—') ? "{$smoking} / {$drinking}" : '—';
        $marital      = !empty($meta['user_marital_status']) ? $meta['user_marital_status'] : '—';
        $children     = !empty($meta['user_children']) ? $meta['user_children'] : '—';
        $edu          = !empty($meta['user_education']) ? $meta['user_education'] : '—';
        $income       = !empty($meta['user_income']) ? $meta['user_income'] : '—';
        $about_me     = !empty($meta['user_about_me']) ? $meta['user_about_me'] : '';
        $is_parent    = !empty($pool['is_parent_applying']) || !empty($meta['is_parent_applying']);

        $card_dims = self::parse_image_dimensions([
            'width'      => $atts['photo_width'] ?: $atts['width'],
            'height'     => $atts['photo_height'] ?: $atts['height'],
            'dimensions' => $atts['photo_size'] ?: ($atts['dimensions'] ?: $atts['size']),
        ], '340px', '320px', '16px');

        ob_start();
        ?>
<div class="mm-featured-card mm-featured-details-view"
    style="background:#fff; border:1px solid #e2e8f0; border-radius:18px; padding:28px 24px; box-shadow:0 6px 20px rgba(0,0,0,0.05); margin:0 auto; font-family:inherit;">

    <!-- Photo / Gallery Section -->
    <div class="mm-featured-gallery-section" style="margin-bottom:20px; text-align:center;">
        <?php if ($is_anonymous || empty($user_photos)) : ?>
        <div class="mm-featured-photo-wrap" style="margin-bottom:14px;">
            <?php echo $this->get_anonymous_avatar_html(180, $card_dims['width'], $card_dims['height'], $card_dims['radius']); ?>
        </div>
        <?php else : ?>
        <!-- Primary Photo with Lightbox -->
        <div class="mm-featured-main-photo-wrap"
            style="position:relative; max-width:<?php echo esc_attr($card_dims['width']); ?>; margin:0 auto 12px; border-radius:<?php echo esc_attr($card_dims['radius']); ?>; overflow:hidden; box-shadow:0 4px 14px rgba(0,0,0,0.08); background:#f8fafc;">
            <img src="<?php echo esc_url($user_photos[0]); ?>" alt="<?php echo esc_attr($display_name); ?>"
                data-mm-lightbox="featured-gallery-<?php echo $post_id; ?>"
                data-mm-photos="<?php echo $user_photos_attr; ?>" class="mm-lightbox-trigger mm-featured-main-img"
                style="width:100%; height:<?php echo esc_attr($card_dims['height']); ?>; object-fit:cover; display:block; cursor:zoom-in; transition:transform 0.2s ease;"
                title="<?php esc_attr_e('Click to view full photo', 'matchmaker'); ?>"
                onclick="if(window.MM_openLightbox){window.MM_openLightbox(this);return false;}">
            <span
                style="position:absolute; bottom:12px; right:12px; background:rgba(15,23,42,0.75); color:#fff; font-size:12px; font-weight:600; padding:4px 10px; border-radius:14px; backdrop-filter:blur(4px); pointer-events:none; display:flex; align-items:center; gap:5px;">
                🔍 <?php echo count($user_photos); ?>
                <?php echo count($user_photos) === 1 ? esc_html__('Photo', 'matchmaker') : esc_html__('Photos', 'matchmaker'); ?>
            </span>
        </div>

        <!-- Additional Thumbnail Previews -->
        <?php if (count($user_photos) > 1) : ?>
        <div class="mm-featured-thumbnails-grid"
            style="display:flex; justify-content:center; gap:10px; flex-wrap:wrap; margin-bottom:14px;">
            <?php foreach ($user_photos as $idx => $pUrl) : ?>
            <img src="<?php echo esc_url($pUrl); ?>"
                alt="<?php echo esc_attr(sprintf(__('Photo %d', 'matchmaker'), $idx + 1)); ?>"
                data-mm-lightbox="featured-gallery-<?php echo $post_id; ?>"
                data-mm-photos="<?php echo $user_photos_attr; ?>" class="mm-lightbox-trigger"
                style="width:68px; height:68px; border-radius:10px; object-fit:cover; cursor:zoom-in; border:2px solid <?php echo $idx === 0 ? '#CC723F' : '#e2e8f0'; ?>; box-shadow:0 2px 6px rgba(0,0,0,0.06); transition:border-color 0.2s ease;"
                title="<?php esc_attr_e('Click to view in gallery', 'matchmaker'); ?>"
                onclick="if(window.MM_openLightbox){window.MM_openLightbox(this);return false;}">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Profile Title & Badges -->
    <div style="text-align:center; margin-bottom:18px;">
        <h2 style="margin:0 0 6px; font-size:24px; color:#1e293b; font-weight:700; line-height:1.3;">
            <?php echo esc_html($display_name); ?>
        </h2>

        <?php if ($is_parent) : ?>
        <div style="margin-bottom:8px;">
            <span class="mm-badge mm-badge-parent"
                style="display:inline-flex; align-items:center; gap:5px; background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; font-weight:600; font-size:12px; padding:4px 12px; border-radius:12px;">
                👨‍👩‍👧 <?php esc_html_e('Parent Applying on Behalf of Child', 'matchmaker'); ?>
            </span>
        </div>
        <?php endif; ?>

        <div style="display:flex; justify-content:center; gap:8px; flex-wrap:wrap; margin-top:8px;">
            <?php if ($age) : ?>
            <span
                style="background:#fff7ed; color:#c2410c; font-size:12px; font-weight:600; padding:4px 12px; border-radius:12px; border:1px solid #fed7aa;">
                <?php echo esc_html($age); ?> <?php esc_html_e('Years', 'matchmaker'); ?>
            </span>
            <?php endif; ?>
            <?php if ($gender && $gender !== '—') : ?>
            <span
                style="background:#f1f5f9; color:#334155; font-size:12px; font-weight:600; padding:4px 12px; border-radius:12px;">
                <?php echo esc_html($gender); ?>
            </span>
            <?php endif; ?>
            <?php if ($location && $location !== '—') : ?>
            <span
                style="background:#f1f5f9; color:#475569; font-size:12px; font-weight:600; padding:4px 12px; border-radius:12px;">
                📍 <?php echo esc_html($location); ?>
            </span>
            <?php endif; ?>
            <?php if ($origin && $origin !== '—') : ?>
            <span
                style="background:#f8fafc; color:#64748b; font-size:12px; font-weight:600; padding:4px 12px; border-radius:12px; border:1px solid #e2e8f0;">
                <?php echo esc_html($origin); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Candidate Self Profile Details (Excluding preferences & contact info) -->
    <div class="mm-featured-info-box"
        style="background:#f8fafc; border:1px solid #edf2f7; border-radius:14px; padding:18px 20px; margin-bottom:20px; text-align:left; font-size:13.5px; line-height:1.6; color:#334155;">
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;">
            <span style="color:#64748b; font-weight:500;"><?php esc_html_e('Age:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($age ? $age . ' yrs' : '—'); ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;">
            <span style="color:#64748b; font-weight:500;"><?php esc_html_e('Gender:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($gender); ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;">
            <span style="color:#64748b; font-weight:500;"><?php esc_html_e('Location:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($location); ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;">
            <span style="color:#64748b; font-weight:500;"><?php esc_html_e('Citizenship:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($citizenship); ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;">
            <span
                style="color:#64748b; font-weight:500;"><?php esc_html_e('Origin / Ethnicity:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($origin); ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;">
            <span
                style="color:#64748b; font-weight:500;"><?php esc_html_e('Religion / Modesty:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($rel_modesty); ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;">
            <span style="color:#64748b; font-weight:500;"><?php esc_html_e('Height:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($height ?: '—'); ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;">
            <span style="color:#64748b; font-weight:500;"><?php esc_html_e('Languages:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($languages); ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;">
            <span
                style="color:#64748b; font-weight:500;"><?php esc_html_e('Profession / Career:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($career); ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;">
            <span
                style="color:#64748b; font-weight:500;"><?php esc_html_e('Smoking / Drinking:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($lifestyle); ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;">
            <span style="color:#64748b; font-weight:500;"><?php esc_html_e('Marital Status:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($marital); ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;">
            <span style="color:#64748b; font-weight:500;"><?php esc_html_e('Children:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($children); ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #e2e8f0;">
            <span style="color:#64748b; font-weight:500;"><?php esc_html_e('Education Level:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($edu); ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; padding:6px 0;">
            <span
                style="color:#64748b; font-weight:500;"><?php esc_html_e('Yearly Income Range:', 'matchmaker'); ?></span>
            <strong><?php echo esc_html($income); ?></strong>
        </div>
    </div>

    <!-- About Myself narrative -->
    <?php if (!empty($about_me)) : ?>
    <div class="mm-featured-about-box"
        style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:16px 18px; margin-bottom:22px; text-align:left; font-size:13.5px; line-height:1.6; color:#475569;">
        <strong style="display:block; margin-bottom:6px; color:#1e293b; font-size:14px;">💬
            <?php esc_html_e('About Myself:', 'matchmaker'); ?></strong>
        <div style="font-style:italic;">
            "<?php echo esc_html($about_me); ?>"
        </div>
    </div>
    <?php endif; ?>

    <!-- Interactive Match Application CTA -->
    <div class="mm-featured-cta-wrap" style="text-align:center;">
        <?php echo $this->render_featured_profile_apply_button(['post_id' => $post_id]); ?>
    </div>
</div>
<?php
        return (string) ob_get_clean();
    }

    /**
     * Shortcode [featured_profile_field key="..."]
     *
     * Outputs an individual field for use in custom Elementor loop items.
     *
     * @param array<string, mixed>|string $atts
     * @return string
     */
    public function render_featured_profile_field(array|string $atts = []): string
    {
        $raw_atts = is_array($atts) ? $atts : [];
        $atts = shortcode_atts([
            'key'           => 'name',
            'post_id'       => 0,
            'user_id'       => 0,
            'width'         => '',
            'height'        => '',
            'size'          => '',
            'dimensions'    => '',
            'fit'           => 'cover',
            'object_fit'    => '',
            'radius'        => '',
            'border_radius' => '',
            'class'         => '',
            'style'         => '',
            'alt'           => '',
        ], $raw_atts);

        $post_id = (int) $atts['post_id'];
        if ($post_id <= 0) {
            $post_id = get_the_ID() ?: 0;
        }

        $user_id = (int) $atts['user_id'];
        if ($user_id <= 0 && $post_id > 0) {
            $user_id = $this->get_connected_user_id($post_id);
        }

        if ($user_id <= 0) {
            return '';
        }

        $repo       = MatchRepository::instance();
        $pool       = $repo->get_user_pool($user_id);
        $meta       = $repo->get_meta_block($user_id);
        $user       = get_userdata($user_id);
        $visibility = ($post_id > 0 ? get_post_meta($post_id, '_mm_visibility_mode', true) : '') ?: (get_user_meta($user_id, 'mm_featured_visibility', true) ?: 'anonymous');
        $is_anon    = ($visibility === 'anonymous');

        switch (strtolower(trim((string) $atts['key']))) {
            case 'name':
                return $is_anon
                    ? esc_html($this->get_anonymous_title($user_id, false))
                    : esc_html($user ? $user->display_name : sprintf(__('Member #%d', 'matchmaker'), $user_id));


            case 'photo':
            case 'photo_1':
            case 'photo_2':
            case 'photo_3':
                $dims = self::parse_image_dimensions($atts, '100%', 'auto', '12px');
                $custom_class = trim('mm-featured-loop-photo ' . preg_replace('/[^a-zA-Z0-9_\-\s]/', '', (string) $atts['class']));
                $extra_style  = trim((string) $atts['style']);
                if ($extra_style !== '' && !str_ends_with($extra_style, ';')) {
                    $extra_style .= ';';
                }

                if ($is_anon) {
                    $avatar_size = $dims['size_num'] > 0 ? $dims['size_num'] : 120;
                    return $this->get_anonymous_avatar_html(
                        $avatar_size,
                        $dims['width'],
                        $dims['height'],
                        $dims['radius'],
                        $custom_class,
                        $extra_style
                    );
                }

                $photos = $repo->get_user_photos($user_id);
                $key_str = strtolower(trim((string) $atts['key']));
                $photo_url = '';
                if ($key_str === 'photo_2') {
                    $photo_url = !empty($photos['user_photo2']) ? $photos['user_photo2'] : '';
                } elseif ($key_str === 'photo_3') {
                    $photo_url = !empty($photos['user_photo3']) ? $photos['user_photo3'] : '';
                } else {
                    $photo_url = !empty($photos['user_photo1']) ? $photos['user_photo1'] : '';
                }

                if (!$photo_url) {
                    $avatar_size = $dims['size_num'] > 0 ? $dims['size_num'] : 120;
                    return $this->get_anonymous_avatar_html(
                        $avatar_size,
                        $dims['width'],
                        $dims['height'],
                        $dims['radius'],
                        $custom_class,
                        $extra_style
                    );
                }

                $alt_text = !empty($atts['alt'])
                    ? sanitize_text_field((string) $atts['alt'])
                    : ($user ? $user->display_name : sprintf(__('Member #%d', 'matchmaker'), $user_id));

                $img_style = 'width:' . esc_attr($dims['width']) . ';'
                           . 'height:' . esc_attr($dims['height']) . ';'
                           . 'object-fit:' . esc_attr($dims['fit']) . ';'
                           . 'border-radius:' . esc_attr($dims['radius']) . ';'
                           . ($extra_style ? ' ' . esc_attr($extra_style) : '');

                return '<img src="' . esc_url($photo_url) . '" alt="' . esc_attr($alt_text) . '" class="' . esc_attr($custom_class) . '" style="' . $img_style . '">';

            case 'age':
                return esc_html($repo->calc_age($pool['birth_date'] ?? ''));

            case 'country':
                return esc_html($pool['country'] ?? '');

            case 'city':
                return esc_html($pool['city'] ?? '');

            case 'location':
                $city = $pool['city'] ?? '';
                $country = $pool['country'] ?? '';
                $loc = trim($city . ($city && $country ? ', ' : '') . $country);
                return esc_html($loc ?: ($pool['location'] ?? '—'));

            case 'gender':
                return !empty($pool['gender']) ? esc_html(ucfirst($pool['gender'])) : '—';

            case 'height':
                $h = $repo->cm_to_feet((int) ($pool['height_cm'] ?? 0));
                return esc_html($h ?: '—');

            case 'citizenship':
                return esc_html(!empty($meta['user_citizenship']) ? $meta['user_citizenship'] : '—');

            case 'origin':
            case 'ethnicity':
                return esc_html(!empty($pool['origin']) ? $pool['origin'] : '—');

            case 'languages':
                return esc_html(!empty($pool['languages']) ? $pool['languages'] : '—');

            case 'marital_status':
            case 'marital':
                return esc_html(!empty($meta['user_marital_status']) ? $meta['user_marital_status'] : '—');

            case 'children':
                return esc_html(!empty($meta['user_children']) ? $meta['user_children'] : '—');

            case 'income':
                return esc_html(!empty($meta['user_income']) ? $meta['user_income'] : '—');

            case 'job':
            case 'career':
            case 'profession':
                return esc_html($pool['job'] ?? '—');

            case 'education':
                return esc_html($meta['user_education'] ?? '—');

            case 'religion':
                return esc_html($pool['religion'] ?? '—');

            case 'modesty':
                return esc_html($pool['modesty'] ?? '—');

            case 'about_me':
                return esc_html($meta['user_about_me'] ?? '');

            default:
                return '';
        }
    }

    /**
     * Shortcode [featured_profile_apply_button]
     *
     * Renders interactive match application CTA with role/status gating.
     *
     * @param array<string, mixed>|string $atts
     * @return string HTML button
     */
    public function render_featured_profile_apply_button(array|string $atts = []): string
    {
        $atts = shortcode_atts([
            'post_id'        => 0,
            'target_user_id' => 0,
            'user_id'        => 0,
        ], is_array($atts) ? $atts : []);

        $post_id = (int) $atts['post_id'];
        if ($post_id <= 0) {
            $post_id = get_the_ID() ?: 0;
        }

        $target_user_id = (int) ($atts['target_user_id'] ?: $atts['user_id']);
        if ($target_user_id <= 0 && $post_id > 0) {
            $target_user_id = $this->get_connected_user_id($post_id);
        }

        if ($target_user_id <= 0 && $post_id <= 0) {
            return '';
        }

        $current_user_id = get_current_user_id();

        // 1. Guest / Not logged in -> Login CTA
        if ($current_user_id <= 0) {
            $login_url = wp_login_url(get_permalink($post_id));
            return '<a href="' . esc_url($login_url) . '" class="button mm-featured-apply-btn" style="background:#CC723F;color:#fff;border-radius:8px;padding:10px 20px;text-decoration:none;font-weight:600;display:inline-block;">'
                . esc_html__('Log in to Apply for Match', 'matchmaker') . '</a>';
        }

        // 2. Member viewing their own featured profile
        if ($current_user_id === $target_user_id) {
            return '<span class="mm-featured-badge" style="background:#f1f5f9;color:#64748b;font-weight:600;padding:8px 16px;border-radius:8px;display:inline-block;font-size:13px;">'
                . esc_html__('Your Featured Profile', 'matchmaker') . '</span>';
        }

        // 3. User logged in, but does not have a basic membership plan (Free, Monthly, Event)
        $user_type = (string) get_user_meta($current_user_id, 'user_type', true);
        if (empty($user_type)) {
            $user_type = PMProSync::instance()->get_current_user_type($current_user_id);
        }
        if (!in_array($user_type, ['free', 'monthly', 'event'], true)) {
            $checkout_url = ProfileService::instance()->get_membership_checkout_url();
            return '<a href="' . esc_url($checkout_url) . '" class="button mm-featured-apply-btn" style="background:#c2410c;color:#fff;border-radius:8px;padding:10px 20px;text-decoration:none;font-weight:600;display:inline-block;">'
                . esc_html__('Membership Required to Apply', 'matchmaker') . '</a>';
        }

        // 4. Check existing match record between applicant & featured member
        global $wpdb;
        $matches_table = $wpdb->prefix . 'matches';
        $u1 = min($current_user_id, $target_user_id);
        $u2 = max($current_user_id, $target_user_id);

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status, initiator_user_id FROM {$matches_table} WHERE user_one_id = %d AND user_two_id = %d LIMIT 1",
            $u1,
            $u2
        ), ARRAY_A);

        if (!empty($existing)) {
            $st = (string) ($existing['status'] ?? '');
            if ($st === 'pending_review') {
                return '<span class="mm-featured-badge mm-badge-pending" style="background:#fef3c7;color:#92400e;border:1px solid #f59e0b;font-weight:600;padding:8px 16px;border-radius:8px;display:inline-block;font-size:13px;">'
                    . '⏳ ' . esc_html__('Application Pending Review', 'matchmaker') . '</span>';
            }
            if ($st === 'approved') {
                $dash_url = ProfileService::instance()->get_dashboard_url();
                return '<a href="' . esc_url($dash_url) . '" class="button mm-featured-apply-btn" style="background:#0284c7;color:#fff;border-radius:8px;padding:10px 20px;text-decoration:none;font-weight:600;display:inline-block;">'
                    . '✨ ' . esc_html__('Match Approved! View on Dashboard', 'matchmaker') . '</a>';
            }
            if ($st === 'matched') {
                $dash_url = ProfileService::instance()->get_dashboard_url();
                return '<a href="' . esc_url($dash_url) . '" class="button mm-featured-apply-btn" style="background:#059669;color:#fff;border-radius:8px;padding:10px 20px;text-decoration:none;font-weight:600;display:inline-block;">'
                    . '🎉 ' . esc_html__('Already Matched! View Contacts', 'matchmaker') . '</a>';
            }
            if ($st === 'admin_rejected' || $st === 'rejected') {
                return '<span class="mm-featured-badge mm-badge-rejected" style="background:#fef2f2;color:#991b1b;border:1px solid #fecaca;font-weight:600;padding:8px 16px;border-radius:8px;display:inline-block;font-size:13px;">'
                    . '❌ ' . esc_html__('Application Rejected', 'matchmaker') . '</span>';
            }
            if ($st === 'expired') {
                return '<span class="mm-featured-badge mm-badge-expired" style="background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;font-weight:600;padding:8px 16px;border-radius:8px;display:inline-block;font-size:13px;">'
                    . '⏱️ ' . esc_html__('Application Expired', 'matchmaker') . '</span>';
            }
        }

        // 5. Eligible to apply
        return '<button type="button" class="button button-primary mm-featured-apply-btn mm-btn-apply-match" data-target-id="' . esc_attr((string) $target_user_id) . '" data-post-id="' . esc_attr((string) $post_id) . '" style="background:#CC723F;color:#fff;border:none;border-radius:8px;padding:10px 22px;font-weight:700;cursor:pointer;font-size:14px;box-shadow:0 2px 6px rgba(204,114,63,0.3);">'
            . '💌 ' . esc_html__('Apply for a Match', 'matchmaker') . '</button>';
    }

    /**
     * AJAX: Member Portal toggle for Featured Profile status and visibility mode.
     */
    public function handle_toggle_featured_profile(): void
    {
        check_ajax_referer('mm_featured_toggle_nonce', 'nonce');

        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            wp_send_json_error(['message' => __('You must be logged in to update your featured profile.', 'matchmaker')]);
        }

        // Verify that user holds the Featured Profile service
        if (!PMProSync::instance()->has_active_featured_profile_service($user_id)) {
            wp_send_json_error(['message' => __('You have not purchased the Featured Profile service.', 'matchmaker')]);
        }

        $enabled         = !empty($_POST['enabled']);
        $visibility_mode = sanitize_key($_POST['visibility_mode'] ?? 'anonymous');
        $display_name    = sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));

        if (isset($_POST['display_name'])) {
            update_user_meta($user_id, 'mm_featured_anonymous_name', $display_name);
        }

        $post_id = $this->sync_featured_post($user_id, $enabled, $visibility_mode, $display_name);
        if (!$post_id) {
            wp_send_json_error(['message' => __('Failed to update featured profile post.', 'matchmaker')]);
        }


        wp_send_json_success([
            'post_id'         => $post_id,
            'enabled'         => $enabled,
            'visibility_mode' => $visibility_mode,
            'message'         => $enabled
                ? __('Your Featured Profile is now live on the public page!', 'matchmaker')
                : __('Your Featured Profile has been hidden from the public page.', 'matchmaker'),
        ]);
    }

    /**
     * AJAX: Apply for a match with a featured member.
     */
    public function handle_apply_featured_match(): void
    {
        check_ajax_referer('mm_featured_apply_nonce', 'nonce');

        $applicant_id = get_current_user_id();
        if ($applicant_id <= 0) {
            wp_send_json_error(['message' => __('Please log in to apply for a match.', 'matchmaker')]);
        }

        $target_user_id = (int) ($_POST['target_user_id'] ?? 0);
        $post_id        = (int) ($_POST['post_id'] ?? 0);

        if ($target_user_id <= 0 && $post_id > 0) {
            $target_user_id = $this->get_connected_user_id($post_id);
        }

        if ($target_user_id <= 0 || $target_user_id === $applicant_id) {
            wp_send_json_error(['message' => __('Invalid featured profile candidate.', 'matchmaker')]);
        }

        // Verify applicant has a valid membership tier
        $user_type = (string) get_user_meta($applicant_id, 'user_type', true);
        if (empty($user_type)) {
            $user_type = PMProSync::instance()->get_current_user_type($applicant_id);
        }
        if (!in_array($user_type, ['free', 'monthly', 'event'], true)) {
            wp_send_json_error(['message' => __('A basic membership is required to apply for matches.', 'matchmaker')]);
        }

        $repo = MatchRepository::instance();

        // Check if existing match exists
        $u1 = min($applicant_id, $target_user_id);
        $u2 = max($applicant_id, $target_user_id);

        global $wpdb;
        $matches_table = $wpdb->prefix . 'matches';
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM {$matches_table} WHERE user_one_id = %d AND user_two_id = %d LIMIT 1",
            $u1,
            $u2
        ), ARRAY_A);

        if (!empty($existing)) {
            $ex_status = (string) ($existing['status'] ?? '');
            if ($ex_status === 'admin_rejected' || $ex_status === 'rejected') {
                wp_send_json_error(['message' => __('Your previous application for this featured candidate was rejected.', 'matchmaker')]);
            }
            wp_send_json_error(['message' => __('A match record already exists with this candidate.', 'matchmaker')]);
        }

        // Calculate flexible criteria score
        $p1 = $repo->get_user_pool($u1);
        $p2 = $repo->get_user_pool($u2);
        $score = ($p1 && $p2) ? \Matchmaker\Service\MatchService::instance()->compute_flexible_score($p1, $p2) : 0;

        // Insert match record with source 'featured' and status 'pending_review'
        $match_id = $repo->create_match(
            $applicant_id,
            $target_user_id,
            $applicant_id,
            'pending_review',
            'featured',
            $score
        );

        if (!$match_id) {
            wp_send_json_error(['message' => __('Failed to submit match application. Please try again.', 'matchmaker')]);
        }

        // Log lifecycle event
        $repo->log_event(
            'match_lifecycle',
            'featured_match_application',
            sprintf(__('Featured Match Application: User #%d applied for #%d', 'matchmaker'), $applicant_id, $target_user_id),
            sprintf(__('Member #%d submitted an application to match with Featured Member #%d (Match #%d).', 'matchmaker'), $applicant_id, $target_user_id, $match_id),
            ['match_id' => $match_id, 'applicant_id' => $applicant_id, 'target_id' => $target_user_id, 'score' => $score],
            $match_id,
            $applicant_id,
            null,
            'info'
        );

        if (class_exists(FileLoggerService::class)) {
            FileLoggerService::info(
                'Featured match application created: match #' . $match_id . ' (applicant #' . $applicant_id . ' vs target #' . $target_user_id . ').',
                ['match_id' => $match_id, 'applicant_id' => $applicant_id, 'target_id' => $target_user_id],
                'match_engine'
            );
        }

        // Dispatch admin alert email
        NotificationService::instance()->send_admin_featured_match_notification(
            $match_id,
            $applicant_id,
            $target_user_id,
            $score
        );

        wp_send_json_success([
            'match_id' => $match_id,
            'message'  => __('Your application has been received! Our matchmaking team will review and approve the introduction.', 'matchmaker'),
        ]);
    }

    /**
     * AJAX handler for non-logged-in visitors attempting to apply.
     */
    public function handle_apply_featured_match_guest(): void
    {
        wp_send_json_error([
            'requires_login' => true,
            'login_url'      => wp_login_url(wp_get_referer() ?: home_url()),
            'message'        => __('Please log in with your membership account to apply for a match.', 'matchmaker'),
        ]);
    }
}