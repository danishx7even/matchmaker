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
        add_action('init',                  [self::class, 'register_role_and_caps']);
        add_action('admin_menu',            [$this, 'register_menu'], 30);
        add_action('admin_menu',            [$this, 'restrict_admin_menus_for_matchmaker_admin'], 9999);
        add_action('admin_init',            [$this, 'enforce_matchmaker_admin_screen_restrictions']);
        add_action('admin_init',            [$this, 'handle_admin_actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // AJAX handlers for Candidate Export & Bulk Email System
        add_action('wp_ajax_mm_export_pool_csv',          [$this, 'ajax_export_pool_csv']);
        add_action('wp_ajax_mm_search_members_for_email', [$this, 'ajax_search_members_for_email']);
        add_action('wp_ajax_mm_count_email_recipients',   [$this, 'ajax_count_email_recipients']);
        add_action('wp_ajax_mm_send_bulk_email',          [$this, 'ajax_send_bulk_email']);
        add_action('wp_ajax_mm_save_bulk_email_default',  [$this, 'ajax_save_bulk_email_default']);
        add_action('wp_ajax_mm_get_file_log',             [$this, 'ajax_get_file_log']);
        add_action('wp_ajax_mm_clear_file_log',           [$this, 'ajax_clear_file_log']);
        add_action('wp_ajax_mm_get_admin_notes',          [$this, 'ajax_get_admin_notes']);
        add_action('wp_ajax_mm_save_admin_notes',         [$this, 'ajax_save_admin_notes']);
        add_action('wp_ajax_mm_admin_search_candidates',       [$this, 'ajax_search_candidates']);
        add_action('wp_ajax_mm_admin_get_candidate_breakdown', [$this, 'ajax_get_candidate_breakdown']);
        add_action('wp_ajax_mm_admin_create_direct_match',     [$this, 'ajax_create_direct_match']);

        // Admin bar restrictions for restricted roles
        add_action('admin_bar_menu',                      [$this, 'restrict_admin_bar_for_restricted_roles'], 999);
    }

    /**
     * Register Matchmaker Admin & Events Organizer roles and custom capabilities.
     *
     * @return void
     */
    public static function register_role_and_caps(): void
    {
        // 1. Grant manage_matchmaker & manage_events_organizer to Full Administrator
        if (function_exists('get_role')) {
            $admin_role = get_role('administrator');
            if ($admin_role && method_exists($admin_role, 'add_cap')) {
                $admin_role->add_cap('manage_matchmaker', true);
                $admin_role->add_cap('manage_events_organizer', true);
            }
        }

        // 2. Register or update matchmaker_admin role
        if (function_exists('get_role') && function_exists('add_role')) {
            $mm_role = get_role('matchmaker_admin');
            if (!$mm_role) {
                add_role(
                    'matchmaker_admin',
                    __('Matchmaker Admin', 'matchmaker'),
                    [
                        'read'              => true,
                        'manage_matchmaker' => true,
                    ]
                );
            } else {
                if (method_exists($mm_role, 'add_cap')) {
                    $mm_role->add_cap('read', true);
                    $mm_role->add_cap('manage_matchmaker', true);
                }
            }

            // 3. Register or update events_organizer role
            $eo_role = get_role('events_organizer');
            $eo_caps = [
                'read'                           => true,
                'manage_events_organizer'        => true,
                'manage_matchmaker'              => true, // Allows access to matchmaking-event-clicks
                'edit_posts'                     => true,
                'edit_others_posts'              => true,
                'publish_posts'                  => true,
                'read_private_posts'             => true,
                'delete_posts'                   => true,
                'delete_others_posts'            => true,
                'delete_published_posts'         => true,
                'delete_private_posts'           => true,
                'edit_published_posts'           => true,
                'edit_private_posts'             => true,
                'upload_files'                   => true,
                'edit_events'                    => true,
                'edit_others_events'             => true,
                'publish_events'                 => true,
                'read_private_events'            => true,
                'delete_events'                  => true,
                'delete_others_events'           => true,
                'delete_published_events'        => true,
                'edit_published_events'          => true,
                'manage_events'                  => true,
                'assign_event_terms'             => true,
                'manage_event_terms'             => true,
                'edit_event_terms'               => true,
                'delete_event_terms'             => true,
                // Forminator capabilities
                'manage_forminator'              => true,
                'manage_forminator_modules'      => true,
                'manage_forminator_submissions'  => true,
                'manage_forminator_templates'    => true,
                'manage_forminator_addons'       => true,
                'manage_forminator_integrations' => true,
                'manage_forminator_reports'      => true,
                'manage_forminator_settings'     => true,
            ];

            if (!$eo_role) {
                add_role(
                    'events_organizer',
                    __('Events Organizer', 'matchmaker'),
                    $eo_caps
                );
            } else {
                if (method_exists($eo_role, 'add_cap')) {
                    foreach ($eo_caps as $cap => $grant) {
                        $eo_role->add_cap($cap, $grant);
                    }
                }
            }
        }
    }

    /**
     * Returns the role access matrix mapping role slugs to allowed menu/screen capabilities.
     *
     * @return array<string, array<string, int>>
     */
    public function get_role_access_matrix(): array
    {
        $saved = get_option('mm_role_access_matrix', null);
        if (is_array($saved) && !empty($saved)) {
            return $saved;
        }

        // Built-in sensible defaults
        return [
            'matchmaker_admin' => [
                'pool'         => 1,
                'matches'      => 1,
                'settings'     => 1,
                'logs'         => 1,
                'event_clicks' => 1,
                'events'       => 0,
                'forminator'   => 0,
                'posts'        => 0,
                'pages'        => 0,
                'users'        => 0,
                'tools'        => 0,
            ],
            'events_organizer' => [
                'pool'         => 0,
                'matches'      => 0,
                'settings'     => 0,
                'logs'         => 0,
                'event_clicks' => 1,
                'events'       => 1,
                'forminator'   => 1,
                'posts'        => 0,
                'pages'        => 0,
                'users'        => 0,
                'tools'        => 0,
            ],
        ];
    }

    /**
     * Checks if a specific feature or menu item is allowed for a user based on role matrix.
     *
     * @param string        $item Matrix item key ('pool', 'matches', 'settings', 'logs', 'event_clicks', 'events', 'forminator', 'posts', 'pages', 'users', 'tools').
     * @param \WP_User|null $user
     * @return bool
     */
    public function is_item_allowed_for_user(string $item, ?\WP_User $user = null): bool
    {
        if ($user === null) {
            $user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
        }

        if (!$user || !isset($user->ID) || $user->ID <= 0) {
            return false;
        }

        if (user_can($user, 'manage_options')) {
            return true;
        }

        $roles  = (array) ($user->roles ?? []);
        $matrix = $this->get_role_access_matrix();

        foreach ($roles as $role) {
            if (isset($matrix[$role]) && is_array($matrix[$role])) {
                if (!empty($matrix[$role][$item])) {
                    return true;
                }
            } else {
                // Fallbacks if role is not configured in matrix
                if ($role === 'matchmaker_admin' || user_can($user, 'manage_matchmaker')) {
                    if (in_array($item, ['pool', 'matches', 'settings', 'logs', 'event_clicks'], true)) {
                        return true;
                    }
                }
                if ($role === 'events_organizer' || user_can($user, 'manage_events_organizer')) {
                    if (in_array($item, ['events', 'forminator', 'event_clicks'], true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Remove non-authorized admin menus for restricted non-admin roles based on Role Access Matrix.
     *
     * @return void
     */
    public function restrict_admin_menus_for_matchmaker_admin(): void
    {
        if (current_user_can('manage_options')) {
            return;
        }

        $user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
        $roles = ($user && isset($user->roles)) ? (array) $user->roles : [];

        $matrix = $this->get_role_access_matrix();
        $is_managed_role = in_array('events_organizer', $roles, true)
            || in_array('matchmaker_admin', $roles, true)
            || current_user_can('manage_events_organizer')
            || current_user_can('manage_matchmaker');

        if (!$is_managed_role) {
            foreach ($roles as $r) {
                if (isset($matrix[$r])) {
                    $is_managed_role = true;
                    break;
                }
            }
        }

        if (!$is_managed_role) {
            return;
        }

        if (!function_exists('remove_menu_page')) {
            return;
        }

        $cpt_slug = (string) get_option('mm_events_cpt_slug', 'event');

        $can_pool         = $this->is_item_allowed_for_user('pool', $user);
        $can_matches      = $this->is_item_allowed_for_user('matches', $user);
        $can_settings     = $this->is_item_allowed_for_user('settings', $user);
        $can_logs         = $this->is_item_allowed_for_user('logs', $user);
        $can_event_clicks = $this->is_item_allowed_for_user('event_clicks', $user);
        $can_events       = $this->is_item_allowed_for_user('events', $user);
        $can_forminator   = $this->is_item_allowed_for_user('forminator', $user);
        $can_posts        = $this->is_item_allowed_for_user('posts', $user);
        $can_pages        = $this->is_item_allowed_for_user('pages', $user);
        $can_users        = $this->is_item_allowed_for_user('users', $user);
        $can_tools        = $this->is_item_allowed_for_user('tools', $user);

        // Submenus under Matchmaking
        if (!$can_pool && function_exists('remove_submenu_page')) {
            remove_submenu_page('matchmaking-pool', 'matchmaking-pool');
        }
        if (!$can_matches && function_exists('remove_submenu_page')) {
            remove_submenu_page('matchmaking-pool', 'matchmaking-matches');
        }
        if (!$can_settings && function_exists('remove_submenu_page')) {
            remove_submenu_page('matchmaking-pool', 'matchmaking-settings');
        }
        if (!$can_logs && function_exists('remove_submenu_page')) {
            remove_submenu_page('matchmaking-pool', 'matchmaking-logs');
        }
        if (!$can_event_clicks && function_exists('remove_submenu_page')) {
            remove_submenu_page('edit.php?post_type=' . $cpt_slug, 'matchmaking-event-clicks');
        }

        if (!$can_pool && !$can_matches && !$can_settings && !$can_logs) {
            remove_menu_page('matchmaking-pool');
        }

        // Add-on & Core Menus
        if (!$can_events) {
            remove_menu_page('edit.php?post_type=' . $cpt_slug);
        }
        if (!$can_forminator) {
            remove_menu_page('forminator');
        }
        if (!$can_posts) {
            remove_menu_page('edit.php');
        }
        if (!$can_pages) {
            remove_menu_page('edit.php?post_type=page');
        }
        if (!$can_users) {
            remove_menu_page('users.php');
        }
        if (!$can_tools) {
            remove_menu_page('tools.php');
        }

        // Common core and third-party disallowed menus
        $disallowed_menus = [
            'index.php',                  // Dashboard
            'upload.php',                  // Media
            'edit-comments.php',           // Comments
            'themes.php',                  // Appearance
            'plugins.php',                 // Plugins
            'options-general.php',         // Settings
            'pmpro-membershiplevels',      // PMPro
            'pmpro-memberslist',
            'pmpro-orders',
            'pmpro-reports',
            'pmpro-discountcodes',
            'pmpro-pagesettings',
            'pmpro-paymentsettings',
            'pmpro-emailsettings',
            'pmpro-advancedsettings',
            'pmpro-addons',
            'elementor',                   // Elementor Core & Pro
            'edit.php?post_type=elementor_library',
            'edit.php?post_type=e-landing-page',
            'edit.php?post_type=elementor_snippet',
            'edit.php?post_type=elementor_font',
            'edit.php?post_type=elementor_icons',
            'e-form-submissions',
            'elementor-tools',
            'elementor-system-info',
            'elementor-getting-started',
            'elementor-license',
            'elementor-apps',
            'elementor-home',
            'elementor-settings',
            'elementor-role-manager',
            'elementor-integrations',
            'elementor-custom-fonts',
            'elementor-custom-icons',
            'elementor-custom-code',
        ];

        foreach ($disallowed_menus as $menu_slug) {
            remove_menu_page($menu_slug);
        }

        // Dynamic $menu sweep: purge any remaining non-whitelisted top-level menu
        global $menu;
        if (is_array($menu)) {
            $allowed_slugs = ['profile.php'];
            if ($can_pool || $can_matches || $can_settings || $can_logs) {
                $allowed_slugs[] = 'matchmaking-pool';
            }
            if ($can_events) {
                $allowed_slugs[] = 'edit.php?post_type=' . $cpt_slug;
            }
            if ($can_forminator) {
                $allowed_slugs[] = 'forminator';
            }
            if ($can_posts) {
                $allowed_slugs[] = 'edit.php';
            }
            if ($can_pages) {
                $allowed_slugs[] = 'edit.php?post_type=page';
            }
            if ($can_users) {
                $allowed_slugs[] = 'users.php';
            }
            if ($can_tools) {
                $allowed_slugs[] = 'tools.php';
            }

            foreach ($menu as $idx => $item) {
                $slug = $item[2] ?? '';
                $is_separator = str_contains((string) ($item[4] ?? ''), 'wp-menu-separator');
                if ($is_separator) {
                    continue;
                }
                if (!in_array($slug, $allowed_slugs, true)) {
                    remove_menu_page($slug);
                    unset($menu[$idx]);
                }
            }
        }
    }

    /**
     * Remove non-authorized items from the WordPress Admin Bar for restricted roles.
     *
     * @param object|null $wp_admin_bar Admin bar instance.
     * @return void
     */
    public function restrict_admin_bar_for_restricted_roles($wp_admin_bar = null): void
    {
        if (current_user_can('manage_options')) {
            return;
        }

        $user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
        $roles = ($user && isset($user->roles)) ? (array) $user->roles : [];

        $matrix = $this->get_role_access_matrix();
        $is_managed_role = in_array('events_organizer', $roles, true)
            || in_array('matchmaker_admin', $roles, true)
            || current_user_can('manage_events_organizer')
            || current_user_can('manage_matchmaker');

        if (!$is_managed_role) {
            foreach ($roles as $r) {
                if (isset($matrix[$r])) {
                    $is_managed_role = true;
                    break;
                }
            }
        }

        if (!$is_managed_role) {
            return;
        }

        if (!is_object($wp_admin_bar) || !method_exists($wp_admin_bar, 'remove_node')) {
            return;
        }

        $disallowed_nodes = [
            'elementor_edit_page',
            'elementor_inspector',
            'elementor_app',
            'elementor_site_settings',
            'elementor-license-status',
            'comments',
            'updates',
            'new-content',
        ];

        foreach ($disallowed_nodes as $node_id) {
            $wp_admin_bar->remove_node($node_id);
        }
    }

    /**
     * Enforce strict page-level gating for restricted non-admin roles based on Role Access Matrix.
     * Redirects visits to wp-admin/ or index.php or other unauthorized backend screens.
     *
     * @return void
     */
    public function enforce_matchmaker_admin_screen_restrictions(): void
    {
        if (current_user_can('manage_options')) {
            return;
        }

        $user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
        $roles = ($user && isset($user->roles)) ? (array) $user->roles : [];

        $matrix = $this->get_role_access_matrix();
        $is_managed_role = in_array('events_organizer', $roles, true)
            || in_array('matchmaker_admin', $roles, true)
            || current_user_can('manage_events_organizer')
            || current_user_can('manage_matchmaker');

        if (!$is_managed_role) {
            foreach ($roles as $r) {
                if (isset($matrix[$r])) {
                    $is_managed_role = true;
                    break;
                }
            }
        }

        if (!$is_managed_role) {
            return;
        }

        // Allow AJAX, cron, and standard API calls
        if ((function_exists('wp_doing_ajax') && wp_doing_ajax()) || (defined('DOING_CRON') && DOING_CRON)) {
            return;
        }

        global $pagenow;

        // Allow user editing their own profile or submitting admin forms/actions/uploads
        if (in_array($pagenow, ['profile.php', 'admin-post.php', 'async-upload.php', 'media-upload.php'], true)) {
            return;
        }

        $cpt_slug  = (string) get_option('mm_events_cpt_slug', 'event');
        $page      = isset($_GET['page']) ? sanitize_text_field(wp_unslash((string) $_GET['page'])) : '';
        $post_type = isset($_GET['post_type']) ? sanitize_text_field(wp_unslash((string) $_GET['post_type'])) : (isset($_POST['post_type']) ? sanitize_text_field(wp_unslash((string) $_POST['post_type'])) : '');

        $is_allowed = false;

        // 1. Candidate Pool
        if ($page === 'matchmaking-pool' && $this->is_item_allowed_for_user('pool', $user)) {
            $is_allowed = true;
        }

        // 2. Matches Queue
        if ($page === 'matchmaking-matches' && $this->is_item_allowed_for_user('matches', $user)) {
            $is_allowed = true;
        }

        // 3. Settings
        if ($page === 'matchmaking-settings' && $this->is_item_allowed_for_user('settings', $user)) {
            $is_allowed = true;
        }

        // 4. Logs
        if ($page === 'matchmaking-logs' && $this->is_item_allowed_for_user('logs', $user)) {
            $is_allowed = true;
        }

        // 5. Event Clicks Analytics
        if ($page === 'matchmaking-event-clicks' && $this->is_item_allowed_for_user('event_clicks', $user)) {
            $is_allowed = true;
        }

        // 6. Events CPT
        if ($this->is_item_allowed_for_user('events', $user)) {
            if (in_array($pagenow, ['edit.php', 'post-new.php', 'edit-tags.php', 'term.php'], true) && $post_type === $cpt_slug) {
                $is_allowed = true;
            } elseif ($pagenow === 'post.php') {
                $post_id = isset($_GET['post']) ? (int) $_GET['post'] : (isset($_POST['post_ID']) ? (int) $_POST['post_ID'] : 0);
                if ($post_id > 0 && function_exists('get_post_type')) {
                    if (get_post_type($post_id) === $cpt_slug) {
                        $is_allowed = true;
                    }
                } elseif ($post_type === $cpt_slug) {
                    $is_allowed = true;
                }
            }
        }

        // 7. Forminator
        if ($this->is_item_allowed_for_user('forminator', $user)) {
            if ($page === 'forminator' || str_starts_with($page, 'forminator')) {
                $is_allowed = true;
            }
        }

        // 8. Posts
        if ($this->is_item_allowed_for_user('posts', $user)) {
            if (in_array($pagenow, ['edit.php', 'post-new.php', 'edit-tags.php', 'term.php', 'post.php'], true) && ($post_type === '' || $post_type === 'post')) {
                if ($pagenow === 'post.php') {
                    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : (isset($_POST['post_ID']) ? (int) $_POST['post_ID'] : 0);
                    if ($post_id === 0 || (function_exists('get_post_type') && get_post_type($post_id) === 'post')) {
                        $is_allowed = true;
                    }
                } else {
                    $is_allowed = true;
                }
            }
        }

        // 9. Pages
        if ($this->is_item_allowed_for_user('pages', $user)) {
            if (in_array($pagenow, ['edit.php', 'post-new.php', 'post.php'], true) && $post_type === 'page') {
                $is_allowed = true;
            }
        }

        // 10. Users
        if ($this->is_item_allowed_for_user('users', $user)) {
            if (in_array($pagenow, ['users.php', 'user-new.php', 'user-edit.php'], true)) {
                $is_allowed = true;
            }
        }

        // 11. Tools
        if ($this->is_item_allowed_for_user('tools', $user)) {
            if ($pagenow === 'tools.php') {
                $is_allowed = true;
            }
        }

        if (!$is_allowed) {
            if ($this->is_item_allowed_for_user('pool', $user)) {
                wp_safe_redirect(admin_url('admin.php?page=matchmaking-pool'));
            } elseif ($this->is_item_allowed_for_user('matches', $user)) {
                wp_safe_redirect(admin_url('admin.php?page=matchmaking-matches'));
            } elseif ($this->is_item_allowed_for_user('events', $user)) {
                wp_safe_redirect(admin_url('edit.php?post_type=' . $cpt_slug));
            } elseif ($this->is_item_allowed_for_user('forminator', $user)) {
                wp_safe_redirect(admin_url('admin.php?page=forminator'));
            } elseif ($this->is_item_allowed_for_user('posts', $user)) {
                wp_safe_redirect(admin_url('edit.php'));
            } elseif ($this->is_item_allowed_for_user('event_clicks', $user)) {
                wp_safe_redirect(admin_url('edit.php?post_type=' . $cpt_slug . '&page=matchmaking-event-clicks'));
            } else {
                wp_safe_redirect(admin_url('profile.php'));
            }
            exit;
        }
    }

    /**
     * Admin Asset Enqueue
     *
     * @param string $hook Page hook suffix.
     * @return void
     */
    public function enqueue_admin_assets(string $hook): void
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash((string) $_GET['page'])) : '';
        $is_matchmaker_page = str_starts_with($page, 'matchmaking')
            || str_contains($hook, 'matchmaking-pool')
            || str_contains($hook, 'matchmaking-matches')
            || str_contains($hook, 'matchmaking-settings')
            || str_contains($hook, 'matchmaking-logs')
            || str_contains($hook, 'matchmaking');

        if (!$is_matchmaker_page) {
            return;
        }

        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }

        $plugin_url = defined('MM_URL') ? MM_URL : plugin_dir_url(dirname(__FILE__, 3));
        $css_file   = dirname(dirname(__DIR__)) . '/assets/css/admin-matchmaker.css';
        $js_file    = dirname(dirname(__DIR__)) . '/assets/js/admin-matchmaker.js';
        $base_ver   = defined('MM_VERSION') ? MM_VERSION : '2.17.0';
        $css_ver    = $base_ver . '.' . (file_exists($css_file) ? (string) filemtime($css_file) : '0');
        $js_ver     = $base_ver . '.' . (file_exists($js_file) ? (string) filemtime($js_file) : '0');

        wp_enqueue_style(
            'mm-admin-styles',
            $plugin_url . 'assets/css/admin-matchmaker.css',
            [],
            $css_ver
        );

        wp_enqueue_script(
            'mm-admin-script',
            $plugin_url . 'assets/js/admin-matchmaker.js',
            ['jquery'],
            $js_ver,
            true
        );

        wp_localize_script(
            'mm-admin-script',
            'matchmakerAdmin',
            [
                'ajax_url'        => admin_url('admin-ajax.php'),
                'nonce'           => wp_create_nonce('mm_admin_nonce'),
                'file_logs_nonce' => wp_create_nonce('mm_file_logs_nonce'),
                'strings'  => [
                    'exporting'      => __('Generating CSV export...', 'matchmaker'),
                    'export_success' => __('CSV downloaded successfully.', 'matchmaker'),
                    'export_error'   => __('Failed to export CSV. Please try again.', 'matchmaker'),
                    'sending'        => __('Queueing campaign...', 'matchmaker'),
                    'saving'         => __('Saving default template...', 'matchmaker'),
                    'confirm_send'   => __('Are you sure you want to queue this bulk email campaign?', 'matchmaker'),
                    'no_recipients'  => __('No recipients matched the selected criteria.', 'matchmaker'),
                    'saving_notes'   => __('Saving notes...', 'matchmaker'),
                    'notes_saved'    => __('Notes saved successfully.', 'matchmaker'),
                    'notes_error'    => __('Failed to save notes. Please try again.', 'matchmaker'),
                    'loading_notes'  => __('Loading notes...', 'matchmaker'),
                ],
            ]
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
            'manage_matchmaker',
            'matchmaking-pool',
            [$this, 'render_admin_page'],
            'dashicons-heart',
            30
        );

        add_submenu_page(
            'matchmaking-pool',
            __('Pool Browser', 'matchmaker'),
            __('Pool Browser', 'matchmaker'),
            'manage_matchmaker',
            'matchmaking-pool',
            [$this, 'render_admin_page']
        );

        add_submenu_page(
            'matchmaking-pool',
            __('Matches Queue', 'matchmaker'),
            __('Matches Queue', 'matchmaker'),
            'manage_matchmaker',
            'matchmaking-matches',
            [$this, 'render_matches_page']
        );

        add_submenu_page(
            'matchmaking-pool',
            __('Settings', 'matchmaker'),
            __('Settings', 'matchmaker'),
            'manage_matchmaker',
            'matchmaking-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'matchmaking-pool',
            __('Match Logs & Diagnostics', 'matchmaker'),
            __('Match Logs', 'matchmaker'),
            'manage_matchmaker',
            'matchmaking-logs',
            [$this, 'render_logs_page']
        );

        if (current_user_can('manage_options')) {
            add_submenu_page(
                'matchmaking-pool',
                __('Scheduled Actions', 'matchmaker'),
                __('Scheduled Actions', 'matchmaker'),
                'manage_options',
                'tools.php?page=action-scheduler&s=matchmaker'
            );
        }

        $cpt_slug = (string) get_option('mm_events_cpt_slug', 'event');
        add_submenu_page(
            'edit.php?post_type=' . $cpt_slug,
            __('Event Click Analytics', 'matchmaker'),
            __('Join Click Analytics', 'matchmaker'),
            'manage_matchmaker',
            'matchmaking-event-clicks',
            [$this, 'render_event_clicks_page']
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
        if (!in_array($page, ['matchmaking-pool', 'matchmaking-matches', 'matchmaking-settings', 'matchmaking-logs', 'matchmaking-event-clicks'], true)) {
            return;
        }

        if (!current_user_can('manage_matchmaker')) {
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
            \Matchmaker\Service\FileLoggerService::info(
                'Admin #' . get_current_user_id() . ' reset test matchmaking data.',
                ['admin_id' => get_current_user_id(), 'profiles_preserved' => $stats['profiles_preserved'] ?? 0],
                'admin'
            );
            $msg = sprintf(
                __('Test matchmaking data reset successfully! Preserved %d candidate profiles. Cleared match records, notifications, and logs.', 'matchmaker'),
                $stats['profiles_preserved']
            );
            add_settings_error('mm_admin_notices', 'test_data_reset_success', $msg, 'updated');
            return;
        }

        // 1b. Recalculate Quotas POST action
        if (isset($_POST['mm_recalculate_quotas'])) {
            check_admin_referer('mm_recalculate_quotas_nonce');
            $stats = $repo->recalculate_all_user_quotas();
            \Matchmaker\Service\FileLoggerService::info(
                'Admin #' . get_current_user_id() . ' recalculated match quotas for ' . ($stats['updated_count'] ?? 0) . ' users.',
                ['admin_id' => get_current_user_id(), 'updated_count' => $stats['updated_count'] ?? 0],
                'admin'
            );
            $msg   = sprintf(
                __('Successfully recalculated and synchronized monthly match counters for %d monthly members.', 'matchmaker'),
                $stats['updated_count']
            );
            add_settings_error('mm_admin_notices', 'quotas_recalculated_success', $msg, 'updated');
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
                    if ($lvl > 0 && in_array($tier, ['monthly', 'event', 'free'], true)) {
                        $new_mapping[$lvl] = $tier;
                    }
                }
                update_option('mm_pmpro_tier_mapping', $new_mapping);
            }

            // PMPro Level Custom Tags
            if (isset($_POST['mm_pmpro_level_tags']) && is_array($_POST['mm_pmpro_level_tags'])) {
                $new_tags = [];
                foreach ($_POST['mm_pmpro_level_tags'] as $lvl_id => $tag_val) {
                    $lvl = (int) $lvl_id;
                    $tag = sanitize_text_field(wp_unslash((string) $tag_val));
                    if ($lvl > 0 && !empty($tag)) {
                        $new_tags[$lvl] = $tag;
                    }
                }
                update_option('mm_pmpro_level_tags', $new_tags);
            }

            // PMPro Services Group ID
            if (isset($_POST['mm_services_group_id'])) {
                update_option('mm_services_group_id', max(1, (int) $_POST['mm_services_group_id']));
            }

            // Featured Profile PMPro Service Level ID & Anonymous Customizations
            if (isset($_POST['mm_featured_profile_level_id'])) {
                update_option('mm_featured_profile_level_id', max(0, (int) $_POST['mm_featured_profile_level_id']));
            }
            if (isset($_POST['mm_featured_profile_anonymous_title'])) {
                $raw_title = sanitize_text_field(wp_unslash($_POST['mm_featured_profile_anonymous_title']));
                update_option('mm_featured_profile_anonymous_title', $raw_title !== '' ? $raw_title : 'Candidate #{id}');
            }
            if (isset($_POST['mm_featured_profile_default_image'])) {
                update_option('mm_featured_profile_default_image', esc_url_raw(trim(wp_unslash($_POST['mm_featured_profile_default_image']))));
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

            // Events Configuration
            if (isset($_POST['mm_events_cpt_slug'])) {
                update_option('mm_events_cpt_slug', sanitize_key((string) $_POST['mm_events_cpt_slug']));
            }
            if (isset($_POST['mm_events_template_id'])) {
                update_option('mm_events_template_id', max(0, (int) $_POST['mm_events_template_id']));
            }
            if (isset($_POST['mm_events_per_page'])) {
                update_option('mm_events_per_page', max(1, (int) $_POST['mm_events_per_page']));
            }

            // Email Templates (Match Approval)
            if (isset($_POST['mm_email_approval_subject'])) {
                update_option('mm_email_approval_subject', sanitize_text_field(wp_unslash($_POST['mm_email_approval_subject'])));
            }

            if (isset($_POST['mm_email_approval_template'])) {
                update_option('mm_email_approval_template', wp_kses_post(wp_unslash($_POST['mm_email_approval_template'])));
            }

            // Verification Email Configuration
            if (isset($_POST['mm_email_verify_from_email'])) {
                update_option('mm_email_verify_from_email', sanitize_email(wp_unslash($_POST['mm_email_verify_from_email'])));
            }
            if (isset($_POST['mm_email_verify_from_name'])) {
                update_option('mm_email_verify_from_name', sanitize_text_field(wp_unslash($_POST['mm_email_verify_from_name'])));
            }
            if (isset($_POST['mm_email_verify_subject'])) {
                update_option('mm_email_verify_subject', sanitize_text_field(wp_unslash($_POST['mm_email_verify_subject'])));
            }
            if (isset($_POST['mm_email_verify_template'])) {
                update_option('mm_email_verify_template', wp_kses_post(wp_unslash($_POST['mm_email_verify_template'])));
            }
            if (isset($_POST['mm_email_verify_update_subject'])) {
                update_option('mm_email_verify_update_subject', sanitize_text_field(wp_unslash($_POST['mm_email_verify_update_subject'])));
            }
            if (isset($_POST['mm_email_verify_update_template'])) {
                update_option('mm_email_verify_update_template', wp_kses_post(wp_unslash($_POST['mm_email_verify_update_template'])));
            }
            if (isset($_POST['mm_email_verify_expiry_hours'])) {
                update_option('mm_email_verify_expiry_hours', max(1, (int) $_POST['mm_email_verify_expiry_hours']));
            }
            if (isset($_POST['mm_email_verify_cooldown_seconds'])) {
                update_option('mm_email_verify_cooldown_seconds', max(5, (int) $_POST['mm_email_verify_cooldown_seconds']));
            }

            // Admin Service Purchase Email Configuration
            if (isset($_POST['mm_email_admin_service_recipient'])) {
                update_option('mm_email_admin_service_recipient', sanitize_email(wp_unslash($_POST['mm_email_admin_service_recipient'])));
            }
            if (isset($_POST['mm_email_admin_service_purchase_subject'])) {
                update_option('mm_email_admin_service_purchase_subject', sanitize_text_field(wp_unslash($_POST['mm_email_admin_service_purchase_subject'])));
            }
            if (isset($_POST['mm_email_admin_service_purchase_template'])) {
                update_option('mm_email_admin_service_purchase_template', wp_kses_post(wp_unslash($_POST['mm_email_admin_service_purchase_template'])));
            }

            // Admin Featured Match Application Email Configuration
            if (isset($_POST['mm_email_admin_featured_match_recipient'])) {
                update_option('mm_email_admin_featured_match_recipient', sanitize_text_field(wp_unslash($_POST['mm_email_admin_featured_match_recipient'])));
            }
            if (isset($_POST['mm_email_admin_featured_match_subject'])) {
                update_option('mm_email_admin_featured_match_subject', sanitize_text_field(wp_unslash($_POST['mm_email_admin_featured_match_subject'])));
            }
            if (isset($_POST['mm_email_admin_featured_match_template'])) {
                update_option('mm_email_admin_featured_match_template', wp_kses_post(wp_unslash($_POST['mm_email_admin_featured_match_template'])));
            }

            // Role Access Matrix Configuration
            if (isset($_POST['mm_role_access_submitted'])) {
                $raw_access  = isset($_POST['mm_role_access']) && is_array($_POST['mm_role_access']) ? $_POST['mm_role_access'] : [];
                $matrix      = [];
                $valid_items = [
                    'pool', 'matches', 'settings', 'logs', 'event_clicks',
                    'events', 'forminator',
                    'posts', 'pages', 'users', 'tools'
                ];
                $all_roles   = function_exists('get_editable_roles') ? get_editable_roles() : [];
                unset($all_roles['administrator']);

                if (empty($all_roles)) {
                    $all_roles = [
                        'matchmaker_admin' => ['name' => 'Matchmaker Admin'],
                        'events_organizer' => ['name' => 'Events Organizer'],
                        'editor'           => ['name' => 'Editor'],
                        'author'           => ['name' => 'Author'],
                    ];
                }

                foreach (array_keys($all_roles) as $role_slug) {
                    $matrix[$role_slug] = [];
                    foreach ($valid_items as $item) {
                        $matrix[$role_slug][$item] = !empty($raw_access[$role_slug][$item]) ? 1 : 0;
                    }
                }

                update_option('mm_role_access_matrix', $matrix);

                if (function_exists('get_role')) {
                    foreach ($matrix as $r_slug => $perms) {
                        $r_obj = get_role($r_slug);
                        if ($r_obj && method_exists($r_obj, 'add_cap')) {
                            $has_mm_perm = !empty($perms['pool']) || !empty($perms['matches']) || !empty($perms['settings']) || !empty($perms['logs']) || !empty($perms['event_clicks']);
                            if ($has_mm_perm) {
                                $r_obj->add_cap('read', true);
                                $r_obj->add_cap('manage_matchmaker', true);
                            }
                        }
                    }
                }
            }

            \Matchmaker\Service\FileLoggerService::info(
                'Admin #' . get_current_user_id() . ' updated plugin settings.',
                ['admin_id' => get_current_user_id()],
                'admin'
            );

            add_settings_error('mm_admin_notices', 'settings_saved', __('Settings saved successfully.', 'matchmaker'), 'updated');
            return;
        }

        // 3. GET Query Actions (Approve / Reject / Trigger / Manual Match / CSV Export)
        $action   = sanitize_text_field(wp_unslash($_GET['mm_action'] ?? $_GET['action'] ?? ''));
        $match_id = (int) ($_GET['match_id'] ?? 0);
        $user_id  = (int) ($_GET['user_id'] ?? $_GET['id'] ?? 0);
        $nonce    = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));

        if (empty($action)) {
            return;
        }

        // --- APPROVE MATCH ---
        if ($action === 'approve' && $match_id > 0 && wp_verify_nonce($nonce, 'mm_approve_' . $match_id)) {
            $admin_id = get_current_user_id();
            $result   = MatchService::instance()->process_admin_approve($match_id, $admin_id);

            if (!empty($result['success'])) {
                \Matchmaker\Service\FileLoggerService::info(
                    'Admin #' . $admin_id . ' approved match #' . $match_id . '.',
                    ['match_id' => $match_id, 'admin_id' => $admin_id, 'message' => $result['message'] ?? ''],
                    'admin'
                );
                add_settings_error('mm_admin_notices', 'approved', $result['message'] ?? sprintf(__('Match #%d approved successfully.', 'matchmaker'), $match_id), 'updated');
            } else {
                \Matchmaker\Service\FileLoggerService::warning(
                    'Admin #' . $admin_id . ' failed to approve match #' . $match_id . ': ' . ($result['message'] ?? 'unknown error') . '.',
                    ['match_id' => $match_id, 'admin_id' => $admin_id, 'message' => $result['message'] ?? ''],
                    'admin'
                );
                add_settings_error('mm_admin_notices', 'approve_failed', $result['message'] ?? __('Failed to approve match.', 'matchmaker'), 'error');
            }
            return;
        }

        // --- REJECT MATCH ---
        if ($action === 'reject' && $match_id > 0 && wp_verify_nonce($nonce, 'mm_reject_' . $match_id)) {
            $admin_id = get_current_user_id();
            $success  = MatchService::instance()->process_admin_reject($match_id);
            if ($success) {
                \Matchmaker\Service\FileLoggerService::info(
                    'Admin #' . $admin_id . ' rejected match #' . $match_id . '.',
                    ['match_id' => $match_id, 'admin_id' => $admin_id],
                    'admin'
                );
                add_settings_error('mm_admin_notices', 'rejected', __('Match rejected.', 'matchmaker'), 'updated');
            } else {
                \Matchmaker\Service\FileLoggerService::warning(
                    'Admin #' . $admin_id . ' failed to reject match #' . $match_id . '.',
                    ['match_id' => $match_id, 'admin_id' => $admin_id],
                    'admin'
                );
                add_settings_error('mm_admin_notices', 'reject_failed', __('Failed to reject match.', 'matchmaker'), 'error');
            }
            return;
        }

        // --- CANCEL APPROVED MATCH (REVERT TO PENDING) ---
        if (($action === 'cancel_approved' || $action === 'revert_approved') && $match_id > 0 && wp_verify_nonce($nonce, 'mm_cancel_approved_' . $match_id)) {
            $admin_id = get_current_user_id();
            $result   = MatchService::instance()->process_admin_cancel_approved($match_id, $admin_id);

            if (!empty($result['success'])) {
                \Matchmaker\Service\FileLoggerService::info(
                    'Admin #' . $admin_id . ' cancelled approved match #' . $match_id . ' (reverted to pending_review).',
                    ['match_id' => $match_id, 'admin_id' => $admin_id, 'message' => $result['message'] ?? ''],
                    'admin'
                );
                add_settings_error('mm_admin_notices', 'cancelled_approved', $result['message'] ?? sprintf(__('Match #%d approval cancelled and reverted to pending review.', 'matchmaker'), $match_id), 'updated');
            } else {
                \Matchmaker\Service\FileLoggerService::warning(
                    'Admin #' . $admin_id . ' failed to cancel approved match #' . $match_id . ': ' . ($result['message'] ?? 'unknown error') . '.',
                    ['match_id' => $match_id, 'admin_id' => $admin_id, 'message' => $result['message'] ?? ''],
                    'admin'
                );
                add_settings_error('mm_admin_notices', 'cancel_approved_failed', $result['message'] ?? __('Failed to cancel approved match.', 'matchmaker'), 'error');
            }
            return;
        }

        // --- RESET MATCH TO PENDING (FOR REJECTED / EXPIRED MATCHES) ---
        if ($action === 'reset_pending' && $match_id > 0 && wp_verify_nonce($nonce, 'mm_reset_pending_' . $match_id)) {
            $admin_id = get_current_user_id();
            $result   = MatchService::instance()->process_admin_reset_pending($match_id, $admin_id);

            if (!empty($result['success'])) {
                \Matchmaker\Service\FileLoggerService::info(
                    'Admin #' . $admin_id . ' reset match #' . $match_id . ' to pending review.',
                    ['match_id' => $match_id, 'admin_id' => $admin_id, 'message' => $result['message'] ?? ''],
                    'admin'
                );
                add_settings_error('mm_admin_notices', 'reset_pending_success', $result['message'] ?? sprintf(__('Match #%d reset to pending review.', 'matchmaker'), $match_id), 'updated');
            } else {
                \Matchmaker\Service\FileLoggerService::warning(
                    'Admin #' . $admin_id . ' failed to reset match #' . $match_id . ' to pending: ' . ($result['message'] ?? 'unknown error') . '.',
                    ['match_id' => $match_id, 'admin_id' => $admin_id, 'message' => $result['message'] ?? ''],
                    'admin'
                );
                add_settings_error('mm_admin_notices', 'reset_pending_failed', $result['message'] ?? __('Failed to reset match to pending review.', 'matchmaker'), 'error');
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
                    \Matchmaker\Service\FileLoggerService::info(
                        'Admin #' . $admin_id . ' created manual match #' . $inserted . ' (users #' . $u1 . ' & #' . $u2 . ', score ' . $score . '/6).',
                        ['admin_id' => $admin_id, 'match_id' => $inserted, 'u1' => $u1, 'u2' => $u2, 'score' => $score],
                        'admin'
                    );
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
                    \Matchmaker\Service\FileLoggerService::warning(
                        'Admin #' . $admin_id . ' manual match failed: pair already exists for #' . $u1 . ' & #' . $u2 . '.',
                        ['admin_id' => $admin_id, 'u1' => $u1, 'u2' => $u2],
                        'admin'
                    );
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

            \Matchmaker\Service\FileLoggerService::info(
                'Admin #' . get_current_user_id() . ' manually triggered matching job for user #' . $user_id . '.',
                ['admin_id' => get_current_user_id(), 'user_id' => $user_id],
                'admin'
            );
            mm_enqueue_user_matching_job($user_id, 'admin_manual_trigger');
            add_settings_error('mm_admin_notices', 'job_queued', sprintf(__('Matching engine job queued for User #%d.', 'matchmaker'), $user_id), 'updated');
            return;
        }

        // --- CREATE FEATURED PROFILE POST ---
        if ($action === 'create_featured_profile' && $user_id > 0 && wp_verify_nonce($nonce, 'mm_create_featured_' . $user_id)) {
            $feat_ctrl = class_exists(\Matchmaker\Frontend\FeaturedProfileController::class)
                ? \Matchmaker\Frontend\FeaturedProfileController::instance()
                : null;

            if ($feat_ctrl) {
                $existing_post_id = $feat_ctrl->get_user_featured_post_id($user_id);
                if ($existing_post_id > 0) {
                    if (!\Matchmaker\Core\PMProSync::instance()->has_active_featured_profile_service($user_id)) {
                        update_post_meta($existing_post_id, '_mm_created_by_admin', 1);
                        delete_user_meta($user_id, 'mm_has_featured_profile');
                        delete_user_meta($user_id, 'mm_featured_enabled');
                    }
                    wp_safe_redirect(admin_url('post.php?post=' . $existing_post_id . '&action=edit'));
                    if (!defined('MM_UNIT_TESTS')) {
                        exit;
                    }
                    return;
                }

                $new_post_id = $feat_ctrl->sync_featured_post($user_id, true, 'anonymous');
                if ($new_post_id > 0) {
                    update_post_meta($new_post_id, '_mm_created_by_admin', 1);
                    delete_user_meta($user_id, 'mm_has_featured_profile');
                    delete_user_meta($user_id, 'mm_featured_enabled');
                    wp_safe_redirect(admin_url('post.php?post=' . $new_post_id . '&action=edit'));
                    if (!defined('MM_UNIT_TESTS')) {
                        exit;
                    }
                    return;
                }
            }

            add_settings_error('mm_admin_notices', 'feature_error', __('Could not create featured profile post.', 'matchmaker'), 'error');
            return;
        }

        // --- REVOKE FEATURED PROFILE POST ---
        if ($action === 'revoke_featured_profile' && $user_id > 0 && wp_verify_nonce($nonce, 'mm_revoke_featured_' . $user_id)) {
            $feat_ctrl = class_exists(\Matchmaker\Frontend\FeaturedProfileController::class)
                ? \Matchmaker\Frontend\FeaturedProfileController::instance()
                : null;

            if ($feat_ctrl && $feat_ctrl->revoke_admin_featured_profile($user_id)) {
                wp_safe_redirect(admin_url('admin.php?page=matchmaking-pool&view_user=' . $user_id . '&mm_notice=featured_revoked'));
                if (!defined('MM_UNIT_TESTS')) {
                    exit;
                }
                return;
            }

            add_settings_error('mm_admin_notices', 'revoke_feature_error', __('Could not revoke featured profile. Only admin-created profiles can be revoked.', 'matchmaker'), 'error');
            return;
        }

        // --- EVENT CLICKS CSV EXPORT ---
        if ($page === 'matchmaking-event-clicks' && $action === 'export_event_clicks' && $nonce !== '') {
            if (wp_verify_nonce($nonce, 'mm_export_event_clicks')) {
                $event_id = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;
                if ($event_id > 0) {
                    $this->export_event_clicks_csv($event_id);
                    exit;
                }
            }
        }

        // --- DOWNLOAD FILE LOG ---
        if ($action === 'download_file_log' && $nonce !== '') {
            if (wp_verify_nonce($nonce, 'mm_download_file_log')) {
                $log_type = sanitize_key($_GET['log_type'] ?? 'info');
                $this->download_file_log($log_type);
                exit;
            }
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

        if (isset($_GET['mm_notice']) && $_GET['mm_notice'] === 'featured_revoked') {
            add_settings_error('mm_admin_notices', 'featured_revoked', __('Featured profile has been revoked and removed successfully.', 'matchmaker'), 'updated');
        }

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

        $search          = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $gender          = isset($_GET['filter_gender']) ? sanitize_text_field(wp_unslash($_GET['filter_gender'])) : '';
        $tier            = isset($_GET['filter_tier']) ? sanitize_text_field(wp_unslash($_GET['filter_tier'])) : '';
        $one_on_one      = isset($_GET['filter_one_on_one']) ? sanitize_text_field(wp_unslash($_GET['filter_one_on_one'])) : '';
        $parent_applying = isset($_GET['filter_parent_applying']) ? sanitize_text_field(wp_unslash($_GET['filter_parent_applying'])) : '';

        $filters = [
            'search'             => $search,
            'gender'             => $gender,
            'user_type'          => $tier,
            'has_one_on_one'     => $one_on_one,
            'is_parent_applying' => $parent_applying,
        ];

        $current_page     = max(1, (int) ($_GET['paged'] ?? 1));
        $per_page         = 20;
        $total_candidates = $repo->search_pool_count($filters);
        $total_pages      = (int) ceil($total_candidates / $per_page);
        $offset           = ($current_page - 1) * $per_page;

        $candidates       = $repo->search_pool($filters, $per_page, $offset);

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
        $matches     = $all_matches;

        if (!$pool || !$user_obj) {
            echo '<p>' . esc_html__('User profile not found in matchmaking pool.', 'matchmaker') . '</p>';
            return;
        }

        $current_tier = \Matchmaker\Core\PMProSync::instance()->get_current_user_type($user_id);
        if (($pool['user_type'] ?? '') !== $current_tier) {
            $pool['user_type'] = $current_tier;
            $repo->save_meta($user_id, 'user_type', $current_tier);
            $repo->update_pool_user_type($user_id, $current_tier);
        }

        $age          = $repo->calc_age($pool['birth_date'] ?? '');
        $height       = $repo->cm_to_feet((int) ($pool['height_cm'] ?? 0));
        $quota_used              = $repo->maybe_reset_monthly_quota($user_id);
        $has_mutual              = $repo->has_mutual_match_this_month($user_id);
        $subscription_start_date = $repo->get_subscription_start_date($user_id);
        $admin_notes             = $repo->get_admin_notes($user_id);

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
        $quota_used = $repo->maybe_reset_monthly_quota($user_id);

        // Smart default candidate gender (opposite of user gender if pref_gender not specified)
        $pref_g = trim((string)($pool['pref_gender'] ?? ''));
        if (empty($pref_g) || strtolower($pref_g) === 'any') {
            $user_g = strtolower(trim((string)($pool['gender'] ?? '')));
            $pref_g = ($user_g === 'male') ? 'female' : (($user_g === 'female') ? 'male' : 'any');
        }

        $parse_multi_param = static function ($raw, $default = ''): array {
            $val = $raw ?? $default;
            if (is_array($val)) {
                $items = array_map(static function ($v) {
                    return sanitize_text_field(wp_unslash((string) $v));
                }, $val);
            } else {
                $str = sanitize_text_field(wp_unslash((string) $val));
                $items = array_map('trim', explode(',', $str));
            }
            return array_values(array_unique(array_filter($items, static function ($item) {
                return $item !== '';
            })));
        };

        $f_gender          = sanitize_text_field(wp_unslash($_GET['f_gender']          ?? $pref_g));
        $f_age_min         = isset($_GET['f_age_min']) ? (int) $_GET['f_age_min']     : (int) ($pool['preferred_age_min'] ?? 18);
        $f_age_max         = isset($_GET['f_age_max']) ? (int) $_GET['f_age_max']     : (int) ($pool['preferred_age_max'] ?? 80);
        $f_country_arr     = $parse_multi_param($_GET['f_country']     ?? null, $pool['pref_country']     ?? '');
        $f_state_arr       = $parse_multi_param($_GET['f_state']       ?? null, $pool['pref_state']       ?? '');
        $f_city_arr        = $parse_multi_param($_GET['f_city']        ?? null, $pool['pref_city']        ?? '');
        $f_citizenship_arr = $parse_multi_param($_GET['f_citizenship'] ?? null, $meta['pref_citizenship'] ?? '');
        $f_location        = sanitize_text_field(wp_unslash($_GET['f_location']        ?? $pool['pref_location'] ?? ''));
        $f_origin_arr      = $parse_multi_param($_GET['f_origin']      ?? null, $pool['pref_origin']      ?? '');
        $f_religion_arr    = $parse_multi_param($_GET['f_religion']    ?? null, $pool['pref_religion']    ?? '');
        $f_modesty_arr     = $parse_multi_param($_GET['f_modesty']     ?? null, $pool['pref_modesty']     ?? '');

        $f_country     = implode(', ', $f_country_arr);
        $f_state       = implode(', ', $f_state_arr);
        $f_city        = implode(', ', $f_city_arr);
        $f_citizenship = implode(', ', $f_citizenship_arr);
        $f_origin      = implode(', ', $f_origin_arr);
        $f_religion    = implode(', ', $f_religion_arr);
        $f_modesty     = implode(', ', $f_modesty_arr);

        $filters = [
            'f_gender'      => $f_gender,
            'f_age_min'     => $f_age_min,
            'f_age_max'     => $f_age_max,
            'f_country'     => $f_country_arr,
            'f_state'       => $f_state_arr,
            'f_city'        => $f_city_arr,
            'f_citizenship' => $f_citizenship_arr,
            'f_location'    => $f_location,
            'f_origin'      => $f_origin_arr,
            'f_religion'    => $f_religion_arr,
            'f_modesty'     => $f_modesty_arr,
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

        $current_page  = max(1, (int) ($_GET['paged'] ?? 1));
        $per_page      = 20;
        $total_matches = $repo->search_matches_count($filters);
        $total_pages   = (int) ceil($total_matches / $per_page);
        $offset        = ($current_page - 1) * $per_page;

        $matches       = $repo->search_matches($filters, $per_page, $offset);

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
        $approve_url = wp_nonce_url(admin_url('admin.php?page=matchmaking-matches&view_match=' . $match_id . '&mm_action=approve&match_id=' . $match_id), 'mm_approve_' . $match_id);
        $reject_url  = wp_nonce_url(admin_url('admin.php?page=matchmaking-matches&view_match=' . $match_id . '&mm_action=reject&match_id=' . $match_id), 'mm_reject_' . $match_id);
        $cancel_url  = wp_nonce_url(admin_url('admin.php?page=matchmaking-matches&view_match=' . $match_id . '&mm_action=cancel_approved&match_id=' . $match_id), 'mm_cancel_approved_' . $match_id);
        $reset_url   = wp_nonce_url(admin_url('admin.php?page=matchmaking-matches&view_match=' . $match_id . '&mm_action=reset_pending&match_id=' . $match_id), 'mm_reset_pending_' . $match_id);
        $st          = (string) $match['status'];

        $breakdown   = ($p1 && $p2)
            ? MatchService::instance()->get_criteria_comparison_breakdown($p1, $p2)
            : ['criteria' => [], 'flexible_score' => (int) ($match['score'] ?? 0), 'matching_criteria_count' => 0, 'total_criteria_count' => 0];

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

        // 1. PMPro Mapping & Level Tags
        $current_mapping    = $pmpro_sync->get_tier_mapping();
        $pmpro_levels       = function_exists('pmpro_getAllLevels') ? pmpro_getAllLevels(true, true) : [];
        $pmpro_level_tags   = $pmpro_sync->get_level_tags();
        $services_group_id              = $pmpro_sync->get_services_group_id();
        $featured_profile_level_id      = $pmpro_sync->get_featured_profile_level_id();
        $featured_profile_default_image = (string) get_option('mm_featured_profile_default_image', '');


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

        // 4. Events Configuration
        $events_cpt_slug    = (string) get_option('mm_events_cpt_slug', 'event');
        $events_template_id = (int) get_option('mm_events_template_id', 395);
        $events_per_page    = (int) get_option('mm_events_per_page', 6);

        // 5. Email Template
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

        // 6. Verification Email Settings
        $verify_from_email       = (string) get_option('mm_email_verify_from_email', '');
        $verify_from_name        = (string) get_option('mm_email_verify_from_name', '');
        $verify_subject          = (string) get_option('mm_email_verify_subject', __('Your Arab Zawaj Verification Code: {code}', 'matchmaker'));
        $default_verify_template = "<p>Hello {user_name},</p>\n"
            . "<p>Thank you for joining {site_name}. To protect the integrity and security of our matrimony community, please enter the one-time verification code below to confirm your email address:</p>\n"
            . "<div style=\"font-size: 36px; font-weight: 800; letter-spacing: 10px; padding: 18px 24px; background: #FAF5F0; border: 2px dashed #CC723F; border-radius: 12px; display: inline-block; margin: 20px 0; color: #1D1E20; font-family: monospace;\">{code}</div>\n"
            . "<p>⏱ This code is valid for <strong>{expiry_hours} hours</strong>. If you did not create an account on {site_name}, you can safely disregard this email.</p>\n"
            . "<p>Warm regards,<br><strong>Arab Zawaj Matchmaking Team</strong></p>";
        $verify_template         = (string) get_option('mm_email_verify_template', $default_verify_template);

        // 6A. Email Update Verification Settings
        $verify_update_subject          = (string) get_option('mm_email_verify_update_subject', __('Verify Your New Arab Zawaj Email: {code}', 'matchmaker'));
        $default_verify_update_template = "<p>Hello {user_name},</p>\n"
            . "<p>You recently requested to update your account email address on {site_name} to <strong>{new_email}</strong>. Please enter the verification code below to confirm this change:</p>\n"
            . "<div style=\"font-size: 36px; font-weight: 800; letter-spacing: 10px; padding: 18px 24px; background: #FAF5F0; border: 2px dashed #CC723F; border-radius: 12px; display: inline-block; margin: 20px 0; color: #1D1E20; font-family: monospace;\">{code}</div>\n"
            . "<p>⏱ This code is valid for <strong>{expiry_hours} hours</strong>. If you did not request this email change, please log into your account to review your settings.</p>\n"
            . "<p>Warm regards,<br><strong>Arab Zawaj Matchmaking Team</strong></p>";
        $verify_update_template         = (string) get_option('mm_email_verify_update_template', $default_verify_update_template);

        $verify_expiry_hours     = (int) get_option('mm_email_verify_expiry_hours', 24);
        $verify_cooldown_seconds = (int) get_option('mm_email_verify_cooldown_seconds', 60);

        // 6B. Admin Service Purchase Email Settings
        $admin_service_recipient         = (string) get_option('mm_email_admin_service_recipient', get_option('admin_email'));
        $default_admin_service_subject   = __('[Arab Zawaj] New Service Purchased: {service_name} by {user_name}', 'matchmaker');
        $admin_service_subject           = (string) get_option('mm_email_admin_service_purchase_subject', $default_admin_service_subject);
        $default_admin_service_template  = "<h2>" . esc_html__('New Service Purchase Alert', 'matchmaker') . "</h2>\n"
            . "<p>A member has just purchased an additional service on {site_name}.</p>\n"
            . "<p><strong>Member Details:</strong><br>\n"
            . "Name: {user_name}<br>\n"
            . "Email: {user_email}<br>\n"
            . "User ID: #{user_id}</p>\n"
            . "<p><strong>Purchased Service:</strong><br>\n"
            . "Service: {service_name}<br>\n"
            . "Price: {service_price}<br>\n"
            . "Date: {purchase_date}</p>\n"
            . "<p><a href=\"{admin_profile_url}\" style=\"background:#CC723F;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;\">View Member in Candidate Pool &rarr;</a></p>";
        $admin_service_template          = (string) get_option('mm_email_admin_service_purchase_template', $default_admin_service_template);

        // 6C. Admin Featured Match Application Email Settings
        $admin_featured_recipient        = (string) get_option('mm_email_admin_featured_match_recipient', '');
        $default_admin_featured_subject  = __('[Matchmaker] New Featured Profile Match Application (Match #{match_id})', 'matchmaker');
        $admin_featured_subject          = (string) get_option('mm_email_admin_featured_match_subject', $default_admin_featured_subject);
        $default_admin_featured_template = "<h2>" . esc_html__('New Featured Profile Match Application', 'matchmaker') . "</h2>\n"
            . "<p>" . esc_html__('A member has submitted an application to match with a Featured Profile on {site_name}.', 'matchmaker') . "</p>\n"
            . "<p><strong>" . esc_html__('Application Details:', 'matchmaker') . "</strong><br>\n"
            . "<strong>" . esc_html__('Applicant:', 'matchmaker') . "</strong> {applicant_name} (#{applicant_id}) &lt;{applicant_email}&gt;<br>\n"
            . "<strong>" . esc_html__('Featured Candidate:', 'matchmaker') . "</strong> {candidate_name} (#{candidate_id})<br>\n"
            . "<strong>" . esc_html__('Match ID:', 'matchmaker') . "</strong> #{match_id}<br>\n"
            . "<strong>" . esc_html__('Compatibility Score:', 'matchmaker') . "</strong> {score}/6</p>\n"
            . "<p><a href=\"{admin_match_url}\" style=\"background:#CC723F;color:#fff;padding:10px 20px;text-decoration:none;border-radius:6px;display:inline-block;font-weight:700;\">" . esc_html__('Review Match in Admin Queue &rarr;', 'matchmaker') . "</a></p>";
        $admin_featured_template         = (string) get_option('mm_email_admin_featured_match_template', $default_admin_featured_template);

        require dirname(__DIR__) . '/View/admin/settings/settings.php';
    }

    /**
     * Render Matchmaking Logs, Notifications & Debugger Page
     *
     * @return void
     */
    public function render_logs_page(): void
    {
        if (!current_user_can('manage_matchmaker')) {
            wp_die(__('Unauthorized', 'matchmaker'));
        }

        global $wpdb;
        $repo          = MatchRepository::instance();
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';
        $matches_table = $wpdb->prefix . 'matches';

        $active_tab = sanitize_key($_GET['tab'] ?? 'match_logs');
        if (!in_array($active_tab, ['match_logs', 'notification_logs', 'file_logs', 'debugger'], true)) {
            $active_tab = 'match_logs';
        }

        $tab_data = [];

        if ($active_tab === 'file_logs') {
            $download_nonce  = wp_create_nonce('mm_download_file_log');
            $file_logs_nonce = wp_create_nonce('mm_file_logs_nonce');
            $info_log_stats  = [
                'path'    => \Matchmaker\Service\FileLoggerService::get_log_file_path('info'),
                'size'    => \Matchmaker\Service\FileLoggerService::get_log_file_size('info'),
                'lines'   => \Matchmaker\Service\FileLoggerService::get_log_line_count('info'),
                'mtime'   => \Matchmaker\Service\FileLoggerService::get_log_file_mtime('info'),
                'content' => \Matchmaker\Service\FileLoggerService::get_log_content('info', 300),
            ];
            $error_log_stats = [
                'path'    => \Matchmaker\Service\FileLoggerService::get_log_file_path('error'),
                'size'    => \Matchmaker\Service\FileLoggerService::get_log_file_size('error'),
                'lines'   => \Matchmaker\Service\FileLoggerService::get_log_line_count('error'),
                'mtime'   => \Matchmaker\Service\FileLoggerService::get_log_file_mtime('error'),
                'content' => \Matchmaker\Service\FileLoggerService::get_log_content('error', 300),
            ];
            $tab_data = [
                'download_nonce'  => $download_nonce,
                'file_logs_nonce' => $file_logs_nonce,
                'info_log_stats'  => $info_log_stats,
                'error_log_stats' => $error_log_stats,
            ];
        } elseif ($active_tab === 'debugger') {
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

    /**
     * AJAX: Export Candidate Pool to CSV.
     *
     * @return void
     */
    public function ajax_export_pool_csv(): void
    {
        if (!current_user_can('manage_matchmaker')) {
            wp_send_json_error(['message' => __('Unauthorized', 'matchmaker')], 403);
        }
        check_ajax_referer('mm_admin_nonce', 'nonce');

        $filters = [
            'search'             => sanitize_text_field(wp_unslash($_POST['s'] ?? $_GET['s'] ?? '')),
            'gender'             => sanitize_text_field(wp_unslash($_POST['filter_gender'] ?? $_GET['filter_gender'] ?? '')),
            'user_type'          => sanitize_text_field(wp_unslash($_POST['filter_tier'] ?? $_GET['filter_tier'] ?? '')),
            'has_one_on_one'     => isset($_POST['filter_one_on_one']) && $_POST['filter_one_on_one'] !== '' ? sanitize_text_field(wp_unslash($_POST['filter_one_on_one'])) : (isset($_GET['filter_one_on_one']) && $_GET['filter_one_on_one'] !== '' ? sanitize_text_field(wp_unslash($_GET['filter_one_on_one'])) : ''),
            'is_parent_applying' => isset($_POST['filter_parent_applying']) && $_POST['filter_parent_applying'] !== '' ? sanitize_text_field(wp_unslash($_POST['filter_parent_applying'])) : (isset($_GET['filter_parent_applying']) && $_GET['filter_parent_applying'] !== '' ? sanitize_text_field(wp_unslash($_GET['filter_parent_applying'])) : ''),
        ];

        $csv = \Matchmaker\Service\ExportService::instance()->generate_csv($filters);
        $filename = 'arabzawaj-candidates-export-' . gmdate('Y-m-d-His') . '.csv';

        if (isset($_GET['download']) && $_GET['download'] === '1') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            echo $csv;
            exit;
        }

        wp_send_json_success([
            'csv'      => $csv,
            'filename' => $filename,
        ]);
    }

    /**
     * AJAX: Search members for bulk email recipient tag autocomplete.
     *
     * @return void
     */
    public function ajax_search_members_for_email(): void
    {
        if (!current_user_can('manage_matchmaker')) {
            wp_send_json_error(['message' => __('Unauthorized', 'matchmaker')], 403);
        }
        check_ajax_referer('mm_admin_nonce', 'nonce');

        $term = sanitize_text_field(wp_unslash($_POST['term'] ?? ''));
        if (strlen($term) < 1) {
            wp_send_json_success(['results' => []]);
        }

        global $wpdb;
        $users_table = $wpdb->users;
        $like = '%' . $wpdb->esc_like($term) . '%';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, display_name, user_email, user_login 
                 FROM {$users_table} 
                 WHERE ID = %d 
                    OR display_name LIKE %s 
                    OR user_email LIKE %s 
                    OR user_login LIKE %s 
                 LIMIT 20",
                is_numeric($term) ? (int) $term : 0,
                $like,
                $like,
                $like
            )
        );

        $repo = MatchRepository::instance();
        $items = [];
        if (!empty($results)) {
            foreach ($results as $u) {
                $uid = (int) $u->ID;
                $user_type = ProfileService::instance()->get_user_type($uid);
                $tier_label = $repo->format_tier_label($user_type);
                $items[] = [
                    'id'        => $uid,
                    'name'      => $u->display_name ?: $u->user_login,
                    'email'     => $u->user_email,
                    'user_type' => $tier_label,
                ];
            }
        }

        wp_send_json_success(['results' => $items]);
    }

    /**
     * AJAX: Count recipients matching current bulk email criteria.
     *
     * @return void
     */
    public function ajax_count_email_recipients(): void
    {
        if (!current_user_can('manage_matchmaker')) {
            wp_send_json_error(['message' => __('Unauthorized', 'matchmaker')], 403);
        }
        check_ajax_referer('mm_admin_nonce', 'nonce');

        $bulk_service = \Matchmaker\Service\BulkEmailService::instance();
        $target_mode  = sanitize_key($_POST['target_mode'] ?? 'criteria');

        $raw_member_ids = $_POST['member_ids'] ?? [];
        if (is_string($raw_member_ids)) {
            $raw_member_ids = array_filter(array_map('intval', explode(',', $raw_member_ids)));
        }

        $args = [
            'mode'            => $target_mode,
            'tier'            => sanitize_key($_POST['tier'] ?? ''),
            'service'         => sanitize_text_field(wp_unslash($_POST['service'] ?? '')),
            'parent_applying' => isset($_POST['parent_applying']) ? sanitize_text_field(wp_unslash($_POST['parent_applying'])) : '',
            'member_ids'      => is_array($raw_member_ids) ? $raw_member_ids : [],
        ];

        $recipients = $bulk_service->resolve_recipients($args);
        wp_send_json_success(['count' => count($recipients)]);
    }

    /**
     * AJAX: Dispatch/Queue bulk email campaign.
     *
     * @return void
     */
    public function ajax_send_bulk_email(): void
    {
        if (!current_user_can('manage_matchmaker')) {
            wp_send_json_error(['message' => __('Unauthorized', 'matchmaker')], 403);
        }
        check_ajax_referer('mm_admin_nonce', 'nonce');

        $bulk_service = \Matchmaker\Service\BulkEmailService::instance();
        $target_mode  = sanitize_key($_POST['target_mode'] ?? 'criteria');
        $subject      = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
        $template     = wp_kses_post(wp_unslash($_POST['template'] ?? ''));

        if (empty(trim($subject))) {
            wp_send_json_error(['message' => __('Please enter an email subject.', 'matchmaker')]);
        }
        if (empty(trim($template))) {
            wp_send_json_error(['message' => __('Please enter email content.', 'matchmaker')]);
        }

        $raw_member_ids = $_POST['member_ids'] ?? [];
        if (is_string($raw_member_ids)) {
            $raw_member_ids = array_filter(array_map('intval', explode(',', $raw_member_ids)));
        }

        $args = [
            'mode'            => $target_mode,
            'tier'            => sanitize_key($_POST['tier'] ?? ''),
            'service'         => sanitize_text_field(wp_unslash($_POST['service'] ?? '')),
            'parent_applying' => isset($_POST['parent_applying']) ? sanitize_text_field(wp_unslash($_POST['parent_applying'])) : '',
            'member_ids'      => is_array($raw_member_ids) ? $raw_member_ids : [],
        ];

        $recipients = $bulk_service->resolve_recipients($args);
        if (empty($recipients)) {
            wp_send_json_error(['message' => __('No recipients matched the selected criteria.', 'matchmaker')]);
        }

        $result = $bulk_service->queue_bulk_email($recipients, $subject, $template);
        if (!empty($result['success'])) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error(['message' => $result['message'] ?? __('Failed to queue bulk emails.', 'matchmaker')]);
        }
    }

    /**
     * AJAX: Save custom template as persistent default.
     *
     * @return void
     */
    public function ajax_save_bulk_email_default(): void
    {
        if (!current_user_can('manage_matchmaker')) {
            wp_send_json_error(['message' => __('Unauthorized', 'matchmaker')], 403);
        }
        check_ajax_referer('mm_admin_nonce', 'nonce');

        $subject  = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
        $template = wp_kses_post(wp_unslash($_POST['template'] ?? ''));

        if (empty(trim($subject))) {
            wp_send_json_error(['message' => __('Subject line cannot be empty.', 'matchmaker')]);
        }
        if (empty(trim($template))) {
            wp_send_json_error(['message' => __('Template content cannot be empty.', 'matchmaker')]);
        }

        \Matchmaker\Service\BulkEmailService::instance()->save_default_template($subject, $template);
        wp_send_json_success(['message' => __('Default bulk email template saved successfully.', 'matchmaker')]);
    }

    /**
     * Render the Event Click Analytics admin page.
     * Displays all events overview or a single event user breakdown table.
     *
     * @return void
     */
    public function render_event_clicks_page(): void
    {
        if (!current_user_can('manage_matchmaker')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'matchmaker'));
        }

        $repo     = MatchRepository::instance();
        $event_id = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

        echo '<div class="wrap mm-admin-wrap">';
        settings_errors('mm_admin_notices');

        if ($event_id > 0) {
            $event   = get_post($event_id);
            $clicks  = $repo->get_event_click_details($event_id);
            $summary = [
                'total_clicks' => (int) array_sum(array_column($clicks, 'click_count')),
                'unique_users' => count($clicks),
            ];
            include __DIR__ . '/../View/admin/events/event-clicks-single.php';
        } else {
            $events_summary = $repo->get_events_click_summary();
            include __DIR__ . '/../View/admin/events/event-clicks.php';
        }

        echo '</div>';
    }

    /**
     * Export event click users to CSV.
     *
     * @param int $event_id Event post ID.
     * @return void
     */
    public function export_event_clicks_csv(int $event_id): void
    {
        if (!current_user_can('manage_matchmaker')) {
            wp_die(esc_html__('Unauthorized.', 'matchmaker'));
        }

        $repo   = MatchRepository::instance();
        $clicks = $repo->get_event_click_details($event_id);
        $event  = get_post($event_id);
        $title  = $event ? sanitize_title($event->post_title) : "event-{$event_id}";
        $filename = "event-{$event_id}-clicks-" . gmdate('Y-m-d') . '.csv';

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }

        // Add UTF-8 BOM for Excel compatibility
        fputs($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            __('User ID', 'matchmaker'),
            __('Full Name', 'matchmaker'),
            __('Username', 'matchmaker'),
            __('Email', 'matchmaker'),
            __('Phone', 'matchmaker'),
            __('Gender', 'matchmaker'),
            __('Location', 'matchmaker'),
            __('Membership Tier', 'matchmaker'),
            __('Total Clicks', 'matchmaker'),
            __('First Clicked At', 'matchmaker'),
            __('Last Clicked At', 'matchmaker'),
        ]);

        foreach ($clicks as $row) {
            $location = trim(($row['city'] ?? '') . ', ' . ($row['country'] ?? ''), ', ');
            fputcsv($out, [
                $row['user_id'] ?? '',
                $row['full_name'] ?? '',
                $row['user_login'] ?? '',
                $row['user_email'] ?? '',
                $row['phone'] ?? '',
                ucfirst((string) ($row['gender'] ?? '')),
                $location,
                ucfirst((string) ($row['user_type'] ?? '')),
                $row['click_count'] ?? 1,
                $row['first_clicked_at'] ?? '',
                $row['last_clicked_at'] ?? '',
            ]);
        }

        fclose($out);
        exit;
    }

    /**
     * Download the selected log file as an attachment.
     *
     * @param string $log_type 'info' | 'error'
     * @return void
     */
    public function download_file_log(string $log_type = 'info'): void
    {
        if (!current_user_can('manage_matchmaker')) {
            wp_die(__('Unauthorized.', 'matchmaker'), 403);
        }

        $log_type  = ($log_type === 'error' || $log_type === 'errors') ? 'error' : 'info';
        $file_path = \Matchmaker\Service\FileLoggerService::get_log_file_path($log_type);
        $filename  = $log_type === 'error' ? 'matchmaker-error.log' : 'matchmaker-info.log';

        if (!file_exists($file_path) || !is_readable($file_path)) {
            wp_die(__('Log file does not exist or is empty.', 'matchmaker'), 404);
        }

        if (function_exists('nocache_headers')) {
            nocache_headers();
        }

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . esc_attr($filename) . '"');
        header('Content-Length: ' . (int) filesize($file_path));

        readfile($file_path);
        exit;
    }

    /**
     * AJAX handler: Get contents and statistics of the specified log file.
     *
     * @return void
     */
    public function ajax_get_file_log(): void
    {
        check_ajax_referer('mm_file_logs_nonce', 'nonce');

        if (!current_user_can('manage_matchmaker')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'matchmaker')], 403);
        }

        $type      = sanitize_key($_POST['log_type'] ?? 'info');
        $type      = ($type === 'error' || $type === 'errors') ? 'error' : 'info';
        $max_lines = max(10, min(2000, (int) ($_POST['max_lines'] ?? 500)));

        $content = \Matchmaker\Service\FileLoggerService::get_log_content($type, $max_lines);
        $size    = \Matchmaker\Service\FileLoggerService::get_log_file_size($type);
        $lines   = \Matchmaker\Service\FileLoggerService::get_log_line_count($type);
        $mtime   = \Matchmaker\Service\FileLoggerService::get_log_file_mtime($type);
        $path    = \Matchmaker\Service\FileLoggerService::get_log_file_path($type);

        wp_send_json_success([
            'type'    => $type,
            'content' => $content,
            'size'    => $size,
            'lines'   => $lines,
            'mtime'   => $mtime,
            'path'    => $path,
        ]);
    }

    /**
     * AJAX handler: Clear the specified log file.
     *
     * @return void
     */
    public function ajax_clear_file_log(): void
    {
        check_ajax_referer('mm_file_logs_nonce', 'nonce');

        if (!current_user_can('manage_matchmaker')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'matchmaker')], 403);
        }

        $type = sanitize_key($_POST['log_type'] ?? 'info');
        $type = ($type === 'error' || $type === 'errors') ? 'error' : 'info';

        $cleared = \Matchmaker\Service\FileLoggerService::clear_log($type);

        if ($cleared) {
            \Matchmaker\Service\FileLoggerService::info("Log file {$type}.log was cleared by admin user #" . get_current_user_id() . ".", ['admin_id' => get_current_user_id()], 'admin');
            wp_send_json_success([
                'message' => sprintf(__('%s.log has been cleared successfully.', 'matchmaker'), $type),
                'type'    => $type,
                'size'    => '0 B',
                'lines'   => 0,
                'mtime'   => __('Just now', 'matchmaker'),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to clear log file.', 'matchmaker')]);
        }
    }

    /**
     * AJAX handler: Get private admin notes for a user.
     *
     * @return void
     */
    public function ajax_get_admin_notes(): void
    {
        check_ajax_referer('mm_admin_nonce', 'nonce');

        if (!current_user_can('manage_matchmaker')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'matchmaker')], 403);
        }

        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        if ($user_id <= 0) {
            wp_send_json_error(['message' => __('Invalid user ID.', 'matchmaker')]);
        }

        $notes = MatchRepository::instance()->get_admin_notes($user_id);
        $user  = get_userdata($user_id);
        $name  = $user ? $user->display_name : ('User #' . $user_id);

        wp_send_json_success([
            'user_id'   => $user_id,
            'user_name' => $name,
            'notes'     => $notes,
        ]);
    }

    /**
     * AJAX handler: Save private admin notes for a user.
     *
     * @return void
     */
    public function ajax_save_admin_notes(): void
    {
        check_ajax_referer('mm_admin_nonce', 'nonce');

        if (!current_user_can('manage_matchmaker')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'matchmaker')], 403);
        }

        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        if ($user_id <= 0) {
            wp_send_json_error(['message' => __('Invalid user ID.', 'matchmaker')]);
        }

        $notes = isset($_POST['notes']) ? (string) wp_unslash($_POST['notes']) : '';
        MatchRepository::instance()->save_admin_notes($user_id, $notes);

        if (class_exists(\Matchmaker\Service\FileLoggerService::class)) {
            \Matchmaker\Service\FileLoggerService::info(
                'Admin notes updated for user #' . $user_id . '.',
                ['user_id' => $user_id, 'admin_id' => get_current_user_id()],
                'admin'
            );
        }

        wp_send_json_success([
            'user_id' => $user_id,
            'message' => __('Admin notes saved successfully.', 'matchmaker'),
        ]);
    }

    /**
     * AJAX handler: Search candidate pool members by name, email, username, or ID.
     *
     * @return void
     */
    public function ajax_search_candidates(): void
    {
        check_ajax_referer('mm_admin_nonce', 'nonce');

        if (!current_user_can('manage_matchmaker')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'matchmaker')], 403);
        }

        $term = isset($_POST['query']) ? sanitize_text_field(wp_unslash((string) $_POST['query'])) : (isset($_POST['term']) ? sanitize_text_field(wp_unslash((string) $_POST['term'])) : '');
        $exclude_id = isset($_POST['target_user_id']) ? (int) $_POST['target_user_id'] : (isset($_POST['exclude_id']) ? (int) $_POST['exclude_id'] : 0);

        if (mb_strlen(trim($term)) < 1) {
            wp_send_json_success(['candidates' => []]);
        }

        global $wpdb;
        $repo = MatchRepository::instance();
        $pool_table  = $wpdb->prefix . 'matchmaking_pool';
        $users_table = $wpdb->users;
        $meta_table  = $wpdb->usermeta;

        $like_term = '%' . $wpdb->esc_like(trim($term)) . '%';
        $sql = "SELECT p.*, u.display_name, u.user_email, u.user_login 
                FROM {$pool_table} p 
                INNER JOIN {$users_table} u ON p.user_id = u.ID 
                WHERE (
                    p.user_id = %d 
                    OR u.display_name LIKE %s 
                    OR u.user_email LIKE %s 
                    OR u.user_login LIKE %s 
                    OR u.user_nicename LIKE %s
                    OR EXISTS (
                        SELECT 1 FROM {$meta_table} um 
                        WHERE um.user_id = p.user_id 
                        AND um.meta_key IN ('first_name', 'last_name', 'full_name', 'user_full_name', 'nickname') 
                        AND um.meta_value LIKE %s
                    )
                )";

        $params = [
            is_numeric($term) ? (int) $term : 0,
            $like_term,
            $like_term,
            $like_term,
            $like_term,
            $like_term,
        ];

        if ($exclude_id > 0) {
            $sql .= " AND p.user_id != %d";
            $params[] = $exclude_id;
        }

        $sql .= " ORDER BY u.display_name ASC LIMIT 20";

        $prepared = $wpdb->prepare($sql, $params);
        $rows     = $wpdb->get_results($prepared, ARRAY_A) ?: [];

        $results = [];
        foreach ($rows as $r) {
            $uid   = (int) $r['user_id'];
            $meta  = $repo->get_meta_block($uid);
            $photo = !empty($meta['user_photo1']) ? $meta['user_photo1'] : (!empty($r['user_photo1']) ? $r['user_photo1'] : '');
            $age   = $repo->calc_age((string) ($r['birth_date'] ?? ''));

            $u_obj = get_userdata($uid);
            $full_name = '';
            if ($u_obj) {
                $fname = get_user_meta($uid, 'first_name', true);
                $lname = get_user_meta($uid, 'last_name', true);
                $full_name = trim($fname . ' ' . $lname);
                if (empty($full_name)) {
                    $full_name = !empty($meta['full_name']) ? (string)$meta['full_name'] : $u_obj->display_name;
                }
            }
            if (empty($full_name)) {
                $full_name = $r['display_name'] ?: ('User #' . $uid);
            }

            $loc_parts = array_filter([$r['city'] ?? '', $r['state'] ?? '', $r['country'] ?? '']);
            $loc = !empty($loc_parts) ? implode(', ', $loc_parts) : ($r['location'] ?? '—');

            $results[] = [
                'id'           => $uid,
                'display_name' => $full_name,
                'user_email'   => $r['user_email'],
                'user_type'    => $r['user_type'] ?? 'free',
                'tier_label'   => $repo->format_tier_label($r['user_type'] ?? 'free'),
                'gender'       => ucfirst((string) ($r['gender'] ?? '')),
                'age'          => $age,
                'location'     => $loc,
                'photo'        => $photo,
            ];
        }

        wp_send_json_success(['candidates' => $results]);
    }


    /**
     * AJAX handler: Get comprehensive criteria comparison between target user and selected candidate.
     *
     * @return void
     */
    public function ajax_get_candidate_breakdown(): void
    {
        check_ajax_referer('mm_admin_nonce', 'nonce');

        if (!current_user_can('manage_matchmaker')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'matchmaker')], 403);
        }

        $target_id    = isset($_POST['target_user_id']) ? (int) $_POST['target_user_id'] : 0;
        $candidate_id = isset($_POST['candidate_id']) ? (int) $_POST['candidate_id'] : 0;

        if ($target_id <= 0 || $candidate_id <= 0 || $target_id === $candidate_id) {
            wp_send_json_error(['message' => __('Invalid user selection.', 'matchmaker')]);
        }

        $repo = MatchRepository::instance();
        $target_pool    = $repo->get_user_pool($target_id);
        $candidate_pool = $repo->get_user_pool($candidate_id);

        if (!$target_pool || !$candidate_pool) {
            wp_send_json_error(['message' => __('One or both candidate profiles could not be found in the matchmaking pool.', 'matchmaker')]);
        }

        $target_user    = get_userdata($target_id);
        $candidate_user = get_userdata($candidate_id);
        $target_meta    = $repo->get_meta_block($target_id);
        $candidate_meta = $repo->get_meta_block($candidate_id);

        $target_photos = array_values(array_filter([
            !empty($target_meta['user_photo1']) ? $target_meta['user_photo1'] : (!empty($target_pool['user_photo1']) ? $target_pool['user_photo1'] : ''),
            !empty($target_meta['user_photo2']) ? $target_meta['user_photo2'] : (!empty($target_pool['user_photo2']) ? $target_pool['user_photo2'] : ''),
            !empty($target_meta['user_photo3']) ? $target_meta['user_photo3'] : (!empty($target_pool['user_photo3']) ? $target_pool['user_photo3'] : ''),
        ]));
        $target_photo = $target_photos[0] ?? '';

        $candidate_photos = array_values(array_filter([
            !empty($candidate_meta['user_photo1']) ? $candidate_meta['user_photo1'] : (!empty($candidate_pool['user_photo1']) ? $candidate_pool['user_photo1'] : ''),
            !empty($candidate_meta['user_photo2']) ? $candidate_meta['user_photo2'] : (!empty($candidate_pool['user_photo2']) ? $candidate_pool['user_photo2'] : ''),
            !empty($candidate_meta['user_photo3']) ? $candidate_meta['user_photo3'] : (!empty($candidate_pool['user_photo3']) ? $candidate_pool['user_photo3'] : ''),
        ]));
        $candidate_photo = $candidate_photos[0] ?? '';

        // Check existing match
        global $wpdb;
        $matches_table = $wpdb->prefix . 'matches';
        $u1 = min($target_id, $candidate_id);
        $u2 = max($target_id, $candidate_id);
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$matches_table} WHERE user_one_id = %d AND user_two_id = %d ORDER BY id DESC LIMIT 1", $u1, $u2),
            ARRAY_A
        );

        $breakdown_data = MatchService::instance()->get_criteria_comparison_breakdown($target_pool, $candidate_pool);

        wp_send_json_success([
            'target' => [
                'id'           => $target_id,
                'name'         => $target_user ? $target_user->display_name : ('User #' . $target_id),
                'email'        => $target_user ? $target_user->user_email : '',
                'photo'        => $target_photo,
                'photos'       => $target_photos,
                'user_type'    => $target_pool['user_type'] ?? 'free',
                'tier_label'   => $repo->format_tier_label($target_pool['user_type'] ?? 'free'),
                'gender'       => ucfirst((string) ($target_pool['gender'] ?? '')),
                'age'          => $repo->calc_age((string) ($target_pool['birth_date'] ?? '')),
            ],
            'candidate' => [
                'id'           => $candidate_id,
                'name'         => $candidate_user ? $candidate_user->display_name : ('User #' . $candidate_id),
                'email'        => $candidate_user ? $candidate_user->user_email : '',
                'photo'        => $candidate_photo,
                'photos'       => $candidate_photos,
                'user_type'    => $candidate_pool['user_type'] ?? 'free',
                'tier_label'   => $repo->format_tier_label($candidate_pool['user_type'] ?? 'free'),
                'gender'       => ucfirst((string) ($candidate_pool['gender'] ?? '')),
                'age'          => $repo->calc_age((string) ($candidate_pool['birth_date'] ?? '')),
            ],
            'score'                   => $breakdown_data['flexible_score'],
            'max_score'               => $breakdown_data['max_flexible_score'],
            'total_criteria_count'    => $breakdown_data['total_criteria_count'],
            'matching_criteria_count' => $breakdown_data['matching_criteria_count'],
            'criteria'                => $breakdown_data['criteria'],
            'match_exists'            => !empty($existing),
            'existing_status'         => $existing ? ($existing['status'] ?? '') : '',
            'existing_match_id'       => $existing ? (int) $existing['id'] : 0,
        ]);
    }

    /**
     * AJAX handler: Create direct manual match between target user and selected candidate.
     *
     * @return void
     */
    public function ajax_create_direct_match(): void
    {
        check_ajax_referer('mm_admin_nonce', 'nonce');

        if (!current_user_can('manage_matchmaker')) {
            wp_send_json_error(['message' => __('Unauthorized permission.', 'matchmaker')], 403);
        }

        $target_id    = isset($_POST['target_user_id']) ? (int) $_POST['target_user_id'] : 0;
        $candidate_id = isset($_POST['candidate_id']) ? (int) $_POST['candidate_id'] : 0;

        if ($target_id <= 0 || $candidate_id <= 0 || $target_id === $candidate_id) {
            wp_send_json_error(['message' => __('Invalid user pair for match creation.', 'matchmaker')]);
        }

        $repo = MatchRepository::instance();
        $target_pool    = $repo->get_user_pool($target_id);
        $candidate_pool = $repo->get_user_pool($candidate_id);

        if (!$target_pool || !$candidate_pool) {
            wp_send_json_error(['message' => __('Both candidates must be present in the matchmaking pool.', 'matchmaker')]);
        }

        if ($repo->has_mutual_match_this_month($target_id) || $repo->has_mutual_match_this_month($candidate_id)) {
            wp_send_json_error(['message' => __('Cannot create match: one or both users already have a mutually accepted match this month.', 'matchmaker')]);
        }

        $score    = MatchService::instance()->compute_flexible_score($target_pool, $candidate_pool);
        $admin_id = get_current_user_id();
        $inserted = $repo->create_match($target_id, $candidate_id, $admin_id, 'pending_review', 'manual', $score);

        if ($inserted) {
            if (class_exists(\Matchmaker\Service\FileLoggerService::class)) {
                \Matchmaker\Service\FileLoggerService::info(
                    'Admin #' . $admin_id . ' created direct manual match #' . $inserted . ' for users #' . $target_id . ' & #' . $candidate_id . ' (score ' . $score . '/6).',
                    ['admin_id' => $admin_id, 'match_id' => $inserted, 'u1' => $target_id, 'u2' => $candidate_id, 'score' => $score],
                    'admin'
                );
            }

            $repo->log_event(
                'match_lifecycle',
                'manual_match_created',
                sprintf(__('Direct Match Created: User #%d & Candidate #%d', 'matchmaker'), $target_id, $candidate_id),
                sprintf(__('Admin #%d created direct match pair (Match ID #%d, Score: %d/6).', 'matchmaker'), $admin_id, $inserted, $score),
                ['u1' => $target_id, 'u2' => $candidate_id, 'score' => $score, 'admin_id' => $admin_id],
                $inserted,
                $admin_id,
                null,
                'info'
            );

            wp_send_json_success([
                'match_id' => $inserted,
                'score'    => $score,
                'message'  => sprintf(__('Direct manual match #%d created successfully and queued for review.', 'matchmaker'), $inserted),
                'queue_url'=> admin_url('admin.php?page=matchmaking-matches'),
            ]);
        } else {
            wp_send_json_error(['message' => __('A match record already exists for this pair in the database.', 'matchmaker')]);
        }
    }
}
