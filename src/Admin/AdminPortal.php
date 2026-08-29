<?php
declare(strict_types=1);

namespace Matchmaker\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class AdminPortal
 *
 * Registers the top-level Matchmaking admin menu and sub-pages in WordPress wp-admin.
 * Manages candidate pool browsing, single candidate profile view with manual matchmaker & auto-match actions,
 * side-by-side match comparison, global matches queue with filters, settings, and test seeder.
 *
 * @package Matchmaker\Admin
 * @since   2.0.0
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
        $version    = defined('MM_VERSION') ? MM_VERSION : '2.0.0';

        wp_enqueue_style(
            'mm-admin-styles',
            $plugin_url . 'assets/css/admin-matchmaker.css',
            [],
            $version
        );

        wp_enqueue_script(
            'mm-admin-script',
            $plugin_url . 'assets/js/admin-matchmaker.js',
            [],
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
            __('Match Logs & Debugger', 'matchmaker'),
            __('Match Logs', 'matchmaker'),
            'manage_options',
            'matchmaking-logs',
            [$this, 'render_logs_page']
        );
    }

    /**
     * Handle Admin Actions: Approve / Reject / Trigger / Manual Match / Save Settings
     *
     * @return void
     */
    public function handle_admin_actions(): void
    {
        $page = sanitize_text_field(wp_unslash($_GET['page'] ?? ''));
        if (!in_array($page, ['matchmaking-pool', 'matchmaking-matches', 'matchmaking-settings'], true)) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings POST action
        if (isset($_POST['mm_save_settings'])) {
            check_admin_referer('mm_save_settings_nonce');

            $recurrence = max(1, (int) ($_POST['mm_auto_match_recurrence_days'] ?? 7));
            update_option('mm_auto_match_recurrence_days', $recurrence);

            if (isset($_POST['mm_email_approval_subject'])) {
                update_option('mm_email_approval_subject', sanitize_text_field(wp_unslash($_POST['mm_email_approval_subject'])));
            }

            if (isset($_POST['mm_email_approval_template'])) {
                update_option('mm_email_approval_template', wp_kses_post(wp_unslash($_POST['mm_email_approval_template'])));
            }

            add_settings_error('mm_admin_notices', 'settings_saved', __('Settings saved successfully.', 'matchmaker'), 'updated');
            return;
        }

        $action   = sanitize_text_field(wp_unslash($_GET['mm_action'] ?? ''));
        $match_id = (int) ($_GET['match_id'] ?? 0);
        $user_id  = (int) ($_GET['user_id'] ?? 0);
        $nonce    = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));

        if (empty($action)) {
            return;
        }

        $repo = \Matchmaker\Repository\MatchRepository::instance();

        // --- APPROVE MATCH ---
        if ($action === 'approve' && $match_id > 0 && wp_verify_nonce($nonce, 'mm_approve_' . $match_id)) {
            $admin_id = get_current_user_id();
            $result   = \Matchmaker\Service\MatchService::instance()->process_admin_approve($match_id, $admin_id);

            if ($result['success']) {
                add_settings_error('mm_admin_notices', 'approved', $result['message'], 'updated');
            } else {
                add_settings_error('mm_admin_notices', 'approve_failed', $result['message'], 'error');
            }
            return;
        }

        // --- REJECT MATCH ---
        if ($action === 'reject' && $match_id > 0 && wp_verify_nonce($nonce, 'mm_reject_' . $match_id)) {
            $success = \Matchmaker\Service\MatchService::instance()->process_admin_reject($match_id);
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
                    ? \Matchmaker\Service\MatchService::instance()->compute_flexible_score($pool1, $pool2)
                    : 0;

                $admin_id = get_current_user_id();
                $inserted = $repo->create_match($u1, $u2, $admin_id, 'pending_review', 'manual', $score);

                if ($inserted) {
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
     * Render Candidate Pool List Table (Only View button in action column)
     *
     * @return void
     */
    private function render_pool_list_view(): void
    {
        $repo = \Matchmaker\Repository\MatchRepository::instance();

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $gender = isset($_GET['filter_gender']) ? sanitize_text_field(wp_unslash($_GET['filter_gender'])) : '';
        $tier   = isset($_GET['filter_tier']) ? sanitize_text_field(wp_unslash($_GET['filter_tier'])) : '';

        $filters = [
            'search'    => $search,
            'gender'    => $gender,
            'user_type' => $tier,
        ];

        $candidates = $repo->search_pool($filters);
        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e('Candidate Pool Browser', 'matchmaker'); ?></h1>
        <hr class="wp-header-end">

        <form method="get" class="mm-filter-bar">
            <input type="hidden" name="page" value="matchmaking-pool">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search name, email, or location...', 'matchmaker'); ?>" class="regular-text">

            <select name="filter_gender">
                <option value=""><?php esc_html_e('All Genders', 'matchmaker'); ?></option>
                <option value="male" <?php selected($gender, 'male'); ?>><?php esc_html_e('Male', 'matchmaker'); ?></option>
                <option value="female" <?php selected($gender, 'female'); ?>><?php esc_html_e('Female', 'matchmaker'); ?></option>
            </select>

            <select name="filter_tier">
                <option value=""><?php esc_html_e('All Tiers', 'matchmaker'); ?></option>
                <option value="monthly" <?php selected($tier, 'monthly'); ?>><?php esc_html_e('Monthly', 'matchmaker'); ?></option>
                <option value="one_on_one" <?php selected($tier, 'one_on_one'); ?>><?php esc_html_e('1-on-1 VIP', 'matchmaker'); ?></option>
                <option value="free" <?php selected($tier, 'free'); ?>><?php esc_html_e('Free', 'matchmaker'); ?></option>
                <option value="event" <?php selected($tier, 'event'); ?>><?php esc_html_e('Event', 'matchmaker'); ?></option>
            </select>

            <input type="submit" class="button" value="<?php esc_attr_e('Filter', 'matchmaker'); ?>">
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px;"><?php esc_html_e('Photo', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Name / Email', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Gender', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Age', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Location', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Tier', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Active Matches', 'matchmaker'); ?></th>
                    <th style="width:100px; text-align:center;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($candidates)) : ?>
                    <tr><td colspan="8"><?php esc_html_e('No candidates found in pool.', 'matchmaker'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($candidates as $c) :
                        $uid = (int) ($c['user_id'] ?? 0);
                        $user_obj = get_userdata($uid);
                        $photo = $repo->get_meta($uid, 'user_photo1');
                        $age = $repo->calc_age($c['birth_date'] ?? '');
                        $view_url = admin_url('admin.php?page=matchmaking-pool&view_user=' . $uid);
                        $approved_cnt = (int) ($c['approved_matches'] ?? 0);
                        $pending_cnt  = (int) ($c['pending_matches'] ?? 0);
                        $has_mutual   = $repo->has_mutual_match_this_month($uid);
                    ?>
                        <tr>
                            <td>
                                <?php if (!empty($photo)) : ?>
                                    <img src="<?php echo esc_url($photo); ?>" style="width:36px;height:36px;border-radius:4px;object-fit:cover;">
                                <?php else : ?>
                                    <div class="mm-avatar-thumb">
                                        <?php echo esc_html(strtoupper(substr($user_obj ? $user_obj->display_name : 'U', 0, 1))); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><a href="<?php echo esc_url($view_url); ?>"><?php echo esc_html($user_obj ? $user_obj->display_name : 'User #' . $uid); ?></a></strong><br>
                                <small style="color:#666;"><?php echo esc_html($user_obj ? $user_obj->user_email : ''); ?></small>
                            </td>
                            <td><?php echo esc_html(ucfirst($c['gender'] ?? '')); ?></td>
                            <td><?php echo esc_html($age); ?></td>
                            <td><?php echo esc_html($c['location'] ?? '—'); ?></td>
                            <td>
                                <span class="mm-badge mm-badge-<?php echo esc_attr($c['user_type'] ?? 'free'); ?>">
                                    <?php echo esc_html($repo->format_tier_label($c['user_type'] ?? 'free')); ?>
                                </span>
                            </td>
                            <td>
                                <span class="mm-count-approved"><?php echo $approved_cnt; ?> <?php esc_html_e('approved', 'matchmaker'); ?></span> / 
                                <span class="mm-count-pending"><?php echo $pending_cnt; ?> <?php esc_html_e('pending', 'matchmaker'); ?></span>
                                <?php if ($has_mutual) : ?>
                                    <br><span style="color:#2e7d32;font-size:11px;font-weight:bold;">★ <?php esc_html_e('Mutually Matched', 'matchmaker'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;">
                                <a href="<?php echo esc_url($view_url); ?>" class="button button-small button-primary">
                                    <?php esc_html_e('View', 'matchmaker'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render Single Candidate Profile Details View (with Auto-Match and Manual Match action buttons)
     *
     * @param int $user_id
     * @return void
     */
    private function render_single_user_view(int $user_id): void
    {
        $repo        = \Matchmaker\Repository\MatchRepository::instance();
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

        $photo1 = $meta['user_photo1'] ?? '';
        $photo2 = $meta['user_photo2'] ?? '';
        $photo3 = $meta['user_photo3'] ?? '';
        ?>

        <!-- Header Card -->
        <div class="mm-detail-header">
            <div>
                <a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Back to Candidate Pool', 'matchmaker'); ?></a>
                <h2 style="margin:8px 0 4px;">
                    <?php echo esc_html($user_obj->display_name); ?>
                    <span class="mm-badge mm-badge-<?php echo esc_attr($pool['user_type']); ?>">
                        <?php echo esc_html($repo->format_tier_label($pool['user_type'])); ?>
                    </span>
                </h2>
                <p class="description" style="margin:0;">
                    <strong><?php esc_html_e('Email:', 'matchmaker'); ?></strong> <?php echo esc_html($user_obj->user_email); ?> &nbsp;|&nbsp; 
                    <strong><?php esc_html_e('Phone:', 'matchmaker'); ?></strong> <?php echo esc_html($meta['phone_number'] ?: 'N/A'); ?> &nbsp;|&nbsp; 
                    <strong><?php esc_html_e('Monthly Quota Used:', 'matchmaker'); ?></strong> <?php echo (int) $quota_used; ?> / 10
                </p>
            </div>
            <div>
                <?php if (!$has_mutual) : ?>
                    <a href="<?php echo esc_url($manual_url); ?>" class="button button-secondary" style="margin-right:8px;">
                        + <?php esc_html_e('Manual Matchmaker', 'matchmaker'); ?>
                    </a>
                    <a href="<?php echo esc_url($trigger_url); ?>" class="button button-primary">
                        ⚡ <?php esc_html_e('Run Auto-Match Scoring', 'matchmaker'); ?>
                    </a>
                <?php else : ?>
                    <span style="color:#2e7d32; font-weight:bold; font-size:13px; background:#e8f5e9; padding:6px 12px; border-radius:4px;">
                        ★ <?php esc_html_e('Mutually Matched This Month', 'matchmaker'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($has_mutual) : ?>
            <div class="notice notice-info inline" style="margin-bottom:20px;">
                <p><strong>★ <?php esc_html_e('Notice:', 'matchmaker'); ?></strong> <?php esc_html_e('This candidate has a mutually accepted match for the current calendar month. Additional automated and manual matching runs are paused.', 'matchmaker'); ?></p>
            </div>
        <?php endif; ?>

        <!-- Two-Column Profile Cards -->
        <div class="mm-grid-two">
            <!-- Candidate Self Profile Card -->
            <div class="mm-card">
                <h3><?php esc_html_e('Candidate Self Profile', 'matchmaker'); ?></h3>
                
                <?php if (!empty($photo1) || !empty($photo2) || !empty($photo3)) : ?>
                    <div class="mm-photos-grid">
                        <?php if (!empty($photo1)) : ?><img src="<?php echo esc_url($photo1); ?>" alt="Photo 1"><?php endif; ?>
                        <?php if (!empty($photo2)) : ?><img src="<?php echo esc_url($photo2); ?>" alt="Photo 2"><?php endif; ?>
                        <?php if (!empty($photo3)) : ?><img src="<?php echo esc_url($photo3); ?>" alt="Photo 3"><?php endif; ?>
                    </div>
                    <hr style="margin:15px 0 10px; border:0; border-top:1px solid #eee;">
                <?php endif; ?>

                <table class="mm-kv-table">
                    <tr><th><?php esc_html_e('Age / Date of Birth', 'matchmaker'); ?></th><td><?php echo esc_html($age . ' yrs (' . ($pool['birth_date'] ?? 'N/A') . ')'); ?></td></tr>
                    <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($pool['gender'] ?? '')); ?></td></tr>
                    <tr><th><?php esc_html_e('Location', 'matchmaker'); ?></th><td><?php echo esc_html($pool['location'] ?? '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Citizenship', 'matchmaker'); ?></th><td><?php echo esc_html($meta['user_citizenship'] ?: '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Origin / Ethnicity', 'matchmaker'); ?></th><td><?php echo esc_html($pool['origin'] ?: '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Religion / Modesty', 'matchmaker'); ?></th><td><?php echo esc_html(($pool['religion'] ?? '—') . ' / ' . ($pool['modesty'] ?? '—')); ?></td></tr>
                    <tr><th><?php esc_html_e('Height', 'matchmaker'); ?></th><td><?php echo esc_html($height); ?></td></tr>
                    <tr><th><?php esc_html_e('Languages', 'matchmaker'); ?></th><td><?php echo esc_html($pool['languages'] ?: '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Job / Career', 'matchmaker'); ?></th><td><?php echo esc_html($pool['job'] ?: '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Smoking / Drinking', 'matchmaker'); ?></th><td><?php echo esc_html(($pool['smoking'] ?: '—') . ' / ' . ($pool['drinking'] ?: '—')); ?></td></tr>
                    <tr><th><?php esc_html_e('Marital Status', 'matchmaker'); ?></th><td><?php echo esc_html($meta['user_marital_status'] ?: '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Children', 'matchmaker'); ?></th><td><?php echo esc_html($meta['user_children'] ?: '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Education Level', 'matchmaker'); ?></th><td><?php echo esc_html($meta['user_education'] ?: '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Income Range', 'matchmaker'); ?></th><td><?php echo esc_html($meta['user_income'] ?: '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Social Links', 'matchmaker'); ?></th><td><?php echo esc_html($meta['user_social_links'] ?: '—'); ?></td></tr>
                </table>
            </div>

            <!-- Candidate Partner Preferences Card -->
            <div class="mm-card">
                <h3><?php esc_html_e('Candidate Partner Preferences', 'matchmaker'); ?></h3>
                <table class="mm-kv-table">
                    <tr><th><?php esc_html_e('Preferred Gender', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($pool['pref_gender'] ?? 'Any')); ?></td></tr>
                    <tr><th><?php esc_html_e('Preferred Age Range', 'matchmaker'); ?></th><td><?php echo esc_html(($pool['preferred_age_min'] ?? 18) . ' – ' . ($pool['preferred_age_max'] ?? 80) . ' yrs'); ?></td></tr>
                    <tr><th><?php esc_html_e('Preferred Location', 'matchmaker'); ?></th><td><?php echo esc_html($pool['pref_location'] ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Preferred Citizenship', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_citizenship'] ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Preferred Origin', 'matchmaker'); ?></th><td><?php echo esc_html($pool['pref_origin'] ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Preferred Religion', 'matchmaker'); ?></th><td><?php echo esc_html($pool['pref_religion'] ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Preferred Modesty', 'matchmaker'); ?></th><td><?php echo esc_html($pool['pref_modesty'] ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Preferred Height Range', 'matchmaker'); ?></th><td><?php echo esc_html(($pool['preferred_height_min'] ? $pool['preferred_height_min'] . 'cm' : 'Min') . ' – ' . ($pool['preferred_height_max'] ? $pool['preferred_height_max'] . 'cm' : 'Max')); ?></td></tr>
                    <tr><th><?php esc_html_e('Preferred Smoking / Drinking', 'matchmaker'); ?></th><td><?php echo esc_html(($pool['pref_smoking'] ?: 'Any') . ' / ' . ($pool['pref_drinking'] ?: 'Any')); ?></td></tr>
                    <tr><th><?php esc_html_e('Preferred Marital Status', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_marital_status'] ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Preferred Children', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_children'] ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Preferred Education', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_education'] ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Preferred Income Range', 'matchmaker'); ?></th><td><?php echo esc_html($meta['pref_income'] ?: 'Any'); ?></td></tr>
                </table>

                <?php if (!empty($meta['pref_additional_info'])) : ?>
                    <hr style="margin:15px 0 10px; border:0; border-top:1px solid #eee;">
                    <strong><?php esc_html_e('About Ideal Partner:', 'matchmaker'); ?></strong>
                    <p style="margin:6px 0 0; color:#444; font-style:italic; font-size:13px; line-height:1.4;">
                        "<?php echo esc_html($meta['pref_additional_info']); ?>"
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Match History & Approval Queue -->
        <div class="mm-card" style="margin-top:20px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                <h3 style="margin:0; border:0; padding:0;"><?php esc_html_e('Match History & Approval Queue', 'matchmaker'); ?></h3>
                <span class="mm-badge mm-badge-free" style="font-size:12px; font-weight:600; padding:4px 10px; border-radius:12px;">
                    <?php echo count($matches); ?> <?php esc_html_e('Total Matches Recorded', 'matchmaker'); ?>
                </span>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:65px;"><?php esc_html_e('ID', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Candidate Profile', 'matchmaker'); ?></th>
                        <th style="width:120px; text-align:center;"><?php esc_html_e('Score', 'matchmaker'); ?></th>
                        <th style="width:120px;"><?php esc_html_e('Status', 'matchmaker'); ?></th>
                        <th style="width:100px;"><?php esc_html_e('Source', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Member Responses', 'matchmaker'); ?></th>
                        <th style="width:110px;"><?php esc_html_e('Created Date', 'matchmaker'); ?></th>
                        <th style="width:180px; text-align:center;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($matches)) : ?>
                        <tr><td colspan="8"><?php esc_html_e('No match history recorded for this candidate.', 'matchmaker'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($matches as $m) :
                            $mid       = (int) $m['id'];
                            $is_u1     = ((int) $m['user_one_id'] === $user_id);
                            $cand_id   = $is_u1 ? (int) $m['user_two_id'] : (int) $m['user_one_id'];
                            $cand_user = get_userdata($cand_id);
                            $cand_pool = $repo->get_user_pool($cand_id);
                            $cand_photo= $repo->get_meta($cand_id, 'user_photo1');
                            $cand_type = $cand_pool['user_type'] ?? 'free';

                            $is_free_or_event = in_array($pool['user_type'], ['free', 'event'], true) || in_array($cand_type, ['free', 'event'], true);

                            $view_match_url = admin_url('admin.php?page=matchmaking-matches&view_match=' . $mid);
                            $approve_url    = wp_nonce_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $user_id . '&mm_action=approve&match_id=' . $mid), 'mm_approve_' . $mid);
                            $reject_url     = wp_nonce_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $user_id . '&mm_action=reject&match_id=' . $mid), 'mm_reject_' . $mid);
                            $st             = (string) $m['status'];

                            $u1_resp = $m['user_one_response'] ?? 'pending';
                            $u2_resp = $m['user_two_response'] ?? 'pending';
                        ?>
                            <tr>
                                <td><strong>#<?php echo $mid; ?></strong></td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <?php if (!empty($cand_photo)) : ?>
                                            <img src="<?php echo esc_url($cand_photo); ?>" style="width:34px;height:34px;border-radius:50%;object-fit:cover;">
                                        <?php else : ?>
                                            <div class="mm-avatar-thumb" style="width:34px;height:34px;border-radius:50%;">
                                                <?php echo esc_html(strtoupper(substr($cand_user ? $cand_user->display_name : 'U', 0, 1))); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $cand_id)); ?>"><?php echo esc_html($cand_user ? $cand_user->display_name : 'User #' . $cand_id); ?></a></strong>
                                            <span class="mm-badge mm-badge-<?php echo esc_attr($cand_type); ?>" style="margin-left:4px;">
                                                <?php echo esc_html($repo->format_tier_label($cand_type)); ?>
                                            </span>
                                            <br>
                                            <small style="color:#666;"><?php echo esc_html($cand_user ? $cand_user->user_email : ''); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <span style="display:inline-block; font-weight:700; color:#0284c7; background:#e0f2fe; padding:2px 8px; border-radius:12px; font-size:12px;">
                                        <?php echo (int) ($m['score'] ?? 0); ?> / 6
                                    </span>
                                </td>
                                <td>
                                    <span class="mm-status mm-status-<?php echo esc_attr($st); ?>">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $st))); ?>
                                    </span>
                                </td>
                                <td><small><?php echo esc_html(ucfirst($m['match_source'] ?? 'auto')); ?></small></td>
                                <td>
                                    <small style="line-height:1.4; display:block;">
                                        <strong>Self:</strong> 
                                        <?php if (($is_u1 ? $u1_resp : $u2_resp) === 'accepted') : ?>
                                            <span style="color:#16a34a; font-weight:600;">✓ Accepted</span>
                                        <?php elseif (($is_u1 ? $u1_resp : $u2_resp) === 'rejected') : ?>
                                            <span style="color:#dc2626; font-weight:600;">✕ Declined</span>
                                        <?php else : ?>
                                            <span style="color:#d97706;">⏳ Pending</span>
                                        <?php endif; ?>
                                        <br>
                                        <strong>Candidate:</strong> 
                                        <?php if (($is_u1 ? $u2_resp : $u1_resp) === 'accepted') : ?>
                                            <span style="color:#16a34a; font-weight:600;">✓ Accepted</span>
                                        <?php elseif (($is_u1 ? $u2_resp : $u1_resp) === 'rejected') : ?>
                                            <span style="color:#dc2626; font-weight:600;">✕ Declined</span>
                                        <?php else : ?>
                                            <span style="color:#d97706;">⏳ Pending</span>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td><small style="color:#555;"><?php echo esc_html(substr($m['created_at'] ?? '', 0, 10)); ?></small></td>
                                <td style="text-align:center;">
                                    <?php if ($st === 'pending_review') : ?>
                                        <?php if ($is_free_or_event) : ?>
                                            <span style="display:block; margin-bottom:4px; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:600; background:#fef3c7; color:#92400e; border:1px solid #f59e0b;" title="<?php esc_attr_e('Matching approvals are disabled for Free or Event users.', 'matchmaker'); ?>">
                                                ⚠️ Free/Event Tier
                                            </span>
                                            <a href="<?php echo esc_url($reject_url); ?>" class="button button-small mm-reject-link"><?php esc_html_e('Reject', 'matchmaker'); ?></a>
                                        <?php else : ?>
                                            <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary button-small"><?php esc_html_e('Approve', 'matchmaker'); ?></a>
                                            <a href="<?php echo esc_url($reject_url); ?>" class="button button-small mm-reject-link"><?php esc_html_e('Reject', 'matchmaker'); ?></a>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url($view_match_url); ?>" class="button button-small"><?php esc_html_e('View Comparison', 'matchmaker'); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render manual matchmaking tool
     *
     * @param int $user_id
     * @return void
     */
    private function render_manual_match_view(int $user_id): void
    {
        $repo = \Matchmaker\Repository\MatchRepository::instance();
        $pool = $repo->get_user_pool($user_id);
        $user_obj = get_userdata($user_id);
        $meta = $repo->get_meta_block($user_id);

        if (!$pool || !$user_obj) {
            echo '<p>' . esc_html__('User profile not found.', 'matchmaker') . '</p>';
            return;
        }

        $user_age   = $repo->calc_age($pool['birth_date'] ?? '');
        $quota_used = (int) get_user_meta($user_id, 'cycle_matches_count', true);

        // Pre-populate filter values from GET params if submitted, else default to target user's saved preferences
        $f_gender   = sanitize_text_field(wp_unslash($_GET['f_gender']   ?? $pool['pref_gender'] ?? ''));
        $f_age_min  = isset($_GET['f_age_min'])  ? (int) $_GET['f_age_min']  : (int) ($pool['preferred_age_min'] ?? 18);
        $f_age_max  = isset($_GET['f_age_max'])  ? (int) $_GET['f_age_max']  : (int) ($pool['preferred_age_max'] ?? 80);
        $f_location = sanitize_text_field(wp_unslash($_GET['f_location'] ?? $pool['pref_location'] ?? ''));
        $f_origin   = sanitize_text_field(wp_unslash($_GET['f_origin']   ?? $pool['pref_origin'] ?? ''));
        $f_religion = sanitize_text_field(wp_unslash($_GET['f_religion'] ?? $pool['pref_religion'] ?? ''));
        $f_modesty  = sanitize_text_field(wp_unslash($_GET['f_modesty']  ?? $pool['pref_modesty'] ?? ''));
        $f_marital  = sanitize_text_field(wp_unslash($_GET['f_marital']  ?? $meta['pref_marital_status'] ?? ''));
        $f_education= sanitize_text_field(wp_unslash($_GET['f_education']?? $meta['pref_education'] ?? ''));

        $filters = [
            'f_gender'         => $f_gender,
            'f_age_min'        => $f_age_min,
            'f_age_max'        => $f_age_max,
            'f_location'       => $f_location,
            'f_origin'         => $f_origin,
            'f_religion'       => $f_religion,
            'f_modesty'        => $f_modesty,
            'f_marital_status' => $f_marital,
            'f_education'      => $f_education,
        ];

        $candidates = $repo->get_manual_match_candidates($user_id, $pool, $filters);
        $back_url   = admin_url('admin.php?page=matchmaking-pool&view_user=' . $user_id);
        $reset_url  = admin_url('admin.php?page=matchmaking-pool&manual_match=' . $user_id);
        ?>
        <p><a href="<?php echo esc_url($back_url); ?>">&larr; <?php echo esc_html(sprintf(__('Back to %s Profile', 'matchmaker'), $user_obj->display_name)); ?></a></p>

        <!-- Header -->
        <div class="mm-detail-header">
            <div>
                <h2>
                    <?php esc_html_e('Manual Matchmaker for:', 'matchmaker'); ?> <?php echo esc_html($user_obj->display_name); ?>
                    <span class="mm-badge mm-badge-<?php echo esc_attr($pool['user_type']); ?>">
                        <?php echo esc_html($repo->format_tier_label($pool['user_type'])); ?>
                    </span>
                </h2>
                <p class="description">
                    <strong><?php esc_html_e('Gender:', 'matchmaker'); ?></strong> <?php echo esc_html(ucfirst($pool['gender'])); ?> &nbsp;|&nbsp; 
                    <strong><?php esc_html_e('Age:', 'matchmaker'); ?></strong> <?php echo esc_html($user_age . ' yrs'); ?> &nbsp;|&nbsp; 
                    <strong><?php esc_html_e('Location:', 'matchmaker'); ?></strong> <?php echo esc_html($pool['location']); ?> &nbsp;|&nbsp; 
                    <strong><?php esc_html_e('Quota Used:', 'matchmaker'); ?></strong> <?php echo $quota_used; ?> / 10
                </p>
            </div>
        </div>

        <!-- Advanced Filter Form Card -->
        <div class="mm-card" style="margin-bottom:24px;">
            <h3><?php esc_html_e('Advanced Candidate Match Filters', 'matchmaker'); ?></h3>
            <form method="get">
                <input type="hidden" name="page" value="matchmaking-pool">
                <input type="hidden" name="manual_match" value="<?php echo $user_id; ?>">

                <div style="display:flex; flex-wrap:wrap; gap:16px; margin-bottom:16px;">
                    <div style="flex:1 1 200px;">
                        <label><strong><?php esc_html_e('Candidate Gender', 'matchmaker'); ?></strong></label><br>
                        <select name="f_gender" style="width:100%;">
                            <option value="female" <?php selected(strtolower($f_gender), 'female'); ?>><?php esc_html_e('Female', 'matchmaker'); ?></option>
                            <option value="male"   <?php selected(strtolower($f_gender), 'male');   ?>><?php esc_html_e('Male', 'matchmaker'); ?></option>
                        </select>
                    </div>

                    <div style="flex:1 1 200px;">
                        <label><strong><?php esc_html_e('Age Range (Min – Max)', 'matchmaker'); ?></strong></label><br>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <input type="number" name="f_age_min" value="<?php echo esc_attr((string)$f_age_min); ?>" style="width:75px;" min="18" max="100">
                            <span>–</span>
                            <input type="number" name="f_age_max" value="<?php echo esc_attr((string)$f_age_max); ?>" style="width:75px;" min="18" max="100">
                        </div>
                    </div>

                    <div style="flex:1 1 200px;">
                        <label><strong><?php esc_html_e('Candidate Location', 'matchmaker'); ?></strong></label><br>
                        <input type="text" name="f_location" value="<?php echo esc_attr($f_location); ?>" placeholder="e.g. Riyadh or Any" style="width:100%;">
                    </div>

                    <div style="flex:1 1 200px;">
                        <label><strong><?php esc_html_e('Origin / Ethnicity', 'matchmaker'); ?></strong></label><br>
                        <input type="text" name="f_origin" value="<?php echo esc_attr($f_origin); ?>" placeholder="e.g. Arab or Any" style="width:100%;">
                    </div>

                    <div style="flex:1 1 200px;">
                        <label><strong><?php esc_html_e('Religion', 'matchmaker'); ?></strong></label><br>
                        <input type="text" name="f_religion" value="<?php echo esc_attr($f_religion); ?>" placeholder="e.g. Muslim or Any" style="width:100%;">
                    </div>

                    <div style="flex:1 1 200px;">
                        <label><strong><?php esc_html_e('Modesty Level', 'matchmaker'); ?></strong></label><br>
                        <input type="text" name="f_modesty" value="<?php echo esc_attr($f_modesty); ?>" placeholder="e.g. Hijab, Niqab or Any" style="width:100%;">
                    </div>
                </div>

                <div>
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Apply Advanced Filters', 'matchmaker'); ?>">
                    <a href="<?php echo esc_url($reset_url); ?>" class="button button-secondary" style="margin-left:8px;"><?php esc_html_e('Reset Filters', 'matchmaker'); ?></a>
                </div>
            </form>
        </div>

        <!-- Candidate Results Table -->
        <div class="mm-card">
            <h3><?php echo esc_html(sprintf(__('Filtered Candidate Results (%d found)', 'matchmaker'), count($candidates))); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:50px;"><?php esc_html_e('Photo', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Candidate Name / Email', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Gender / Age', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Location / Origin', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Religion / Modesty', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Compatibility Score', 'matchmaker'); ?></th>
                        <th style="width:160px; text-align:center;"><?php esc_html_e('Action', 'matchmaker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($candidates)) : ?>
                        <tr><td colspan="7"><?php esc_html_e('No compatible candidates found matching current filter criteria.', 'matchmaker'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($candidates as $cand) :
                            $cid   = (int) $cand['user_id'];
                            $cuser = get_userdata($cid);
                            $photo = $repo->get_meta($cid, 'user_photo1');
                            $cage  = $repo->calc_age($cand['birth_date'] ?? '');
                            $score = \Matchmaker\Service\MatchService::instance()->compute_flexible_score($pool, $cand);
                            $create_url = wp_nonce_url(
                                admin_url('admin.php?page=matchmaking-pool&manual_match=' . $user_id . '&mm_action=create_manual_match&u1=' . $user_id . '&u2=' . $cid),
                                'mm_manual_match'
                            );
                        ?>
                            <tr>
                                <td>
                                    <?php if (!empty($photo)) : ?>
                                        <img src="<?php echo esc_url($photo); ?>" style="width:36px;height:36px;border-radius:4px;object-fit:cover;">
                                    <?php else : ?>
                                        <div class="mm-avatar-thumb">
                                            <?php echo esc_html(strtoupper(substr($cuser ? $cuser->display_name : 'U', 0, 1))); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $cid)); ?>"><?php echo esc_html($cuser ? $cuser->display_name : 'User #' . $cid); ?></a></strong><br>
                                    <small style="color:#666;"><?php echo esc_html($cuser ? $cuser->user_email : ''); ?></small>
                                </td>
                                <td><?php echo esc_html(ucfirst($cand['gender'] ?? '')) . ' (' . esc_html($cage) . ' yrs)'; ?></td>
                                <td><?php echo esc_html(($cand['location'] ?? '—') . ' / ' . ($cand['origin'] ?? '—')); ?></td>
                                <td><?php echo esc_html(($cand['religion'] ?? '—') . ' / ' . ($cand['modesty'] ?? '—')); ?></td>
                                <td><strong><?php echo (int) $score; ?></strong> / 6</td>
                                <td style="text-align:center;">
                                    <a href="<?php echo esc_url($create_url); ?>" class="button button-primary button-small">
                                        + <?php esc_html_e('Create Match Pair', 'matchmaker'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render Global Matches Queue Page (Shows ALL matches with search and status filters)
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
     * Render Matches Queue List Table with Search, Status, and Source Filters
     *
     * @return void
     */
    private function render_matches_list_view(): void
    {
        $repo = \Matchmaker\Repository\MatchRepository::instance();

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status = isset($_GET['filter_status']) ? sanitize_text_field(wp_unslash($_GET['filter_status'])) : '';
        $source = isset($_GET['filter_source']) ? sanitize_text_field(wp_unslash($_GET['filter_source'])) : '';

        $filters = [
            'search' => $search,
            'status' => $status,
            'source' => $source,
        ];

        $matches = $repo->search_matches($filters);
        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e('All Matches Queue', 'matchmaker'); ?></h1>
        <hr class="wp-header-end">

        <form method="get" class="mm-filter-bar">
            <input type="hidden" name="page" value="matchmaking-matches">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search member name or email...', 'matchmaker'); ?>" class="regular-text">

            <select name="filter_status">
                <option value=""><?php esc_html_e('All Match Statuses', 'matchmaker'); ?></option>
                <option value="pending_review" <?php selected($status, 'pending_review'); ?>><?php esc_html_e('Pending Review', 'matchmaker'); ?></option>
                <option value="approved" <?php selected($status, 'approved'); ?>><?php esc_html_e('Approved', 'matchmaker'); ?></option>
                <option value="matched" <?php selected($status, 'matched'); ?>><?php esc_html_e('Mutual Match', 'matchmaker'); ?></option>
                <option value="expired" <?php selected($status, 'expired'); ?>><?php esc_html_e('Expired', 'matchmaker'); ?></option>
                <option value="rejected" <?php selected($status, 'rejected'); ?>><?php esc_html_e('Member Rejected', 'matchmaker'); ?></option>
                <option value="admin_rejected" <?php selected($status, 'admin_rejected'); ?>><?php esc_html_e('Admin Rejected', 'matchmaker'); ?></option>
            </select>

            <select name="filter_source">
                <option value=""><?php esc_html_e('All Sources', 'matchmaker'); ?></option>
                <option value="auto" <?php selected($source, 'auto'); ?>><?php esc_html_e('Auto Engine', 'matchmaker'); ?></option>
                <option value="manual" <?php selected($source, 'manual'); ?>><?php esc_html_e('Manual Matchmaker', 'matchmaker'); ?></option>
            </select>

            <input type="submit" class="button" value="<?php esc_attr_e('Filter Matches', 'matchmaker'); ?>">
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:65px;"><?php esc_html_e('Match ID', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('User 1 (Initiator)', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('User 2 (Candidate)', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Score', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Status', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Source', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Responses', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Created At', 'matchmaker'); ?></th>
                    <th style="width:160px; text-align:center;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($matches)) : ?>
                    <tr><td colspan="9"><?php esc_html_e('No matches found matching filter criteria.', 'matchmaker'); ?></td></tr>
                <?php else : ?>
                    <?php foreach ($matches as $m) :
                        $mid = (int) ($m['id'] ?? 0);
                        $u1  = get_userdata((int) ($m['user_one_id'] ?? 0));
                        $u2  = get_userdata((int) ($m['user_two_id'] ?? 0));
                        $st  = (string) ($m['status'] ?? 'pending_review');

                        $view_url    = admin_url('admin.php?page=matchmaking-matches&view_match=' . $mid);
                        $approve_url = wp_nonce_url(admin_url('admin.php?page=matchmaking-matches&mm_action=approve&match_id=' . $mid), 'mm_approve_' . $mid);
                        $reject_url  = wp_nonce_url(admin_url('admin.php?page=matchmaking-matches&mm_action=reject&match_id=' . $mid), 'mm_reject_' . $mid);
                    ?>
                        <tr>
                            <td>#<?php echo $mid; ?></td>
                            <td>
                                <strong><a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . ($m['user_one_id'] ?? 0))); ?>"><?php echo esc_html($u1 ? $u1->display_name : 'User #' . ($m['user_one_id'] ?? 0)); ?></a></strong><br>
                                <small style="color:#666;"><?php echo esc_html($u1 ? $u1->user_email : ''); ?></small>
                            </td>
                            <td>
                                <strong><a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . ($m['user_two_id'] ?? 0))); ?>"><?php echo esc_html($u2 ? $u2->display_name : 'User #' . ($m['user_two_id'] ?? 0)); ?></a></strong><br>
                                <small style="color:#666;"><?php echo esc_html($u2 ? $u2->user_email : ''); ?></small>
                            </td>
                            <td><strong><?php echo (int) ($m['score'] ?? 0); ?></strong> / 6</td>
                            <td><span class="mm-status mm-status-<?php echo esc_attr($st); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $st))); ?></span></td>
                            <td><?php echo esc_html(ucfirst($m['match_source'] ?? 'auto')); ?></td>
                            <td><small>U1: <?php echo esc_html($m['user_one_response'] ?? 'pending'); ?> | U2: <?php echo esc_html($m['user_two_response'] ?? 'pending'); ?></small></td>
                            <td><small><?php echo esc_html($m['created_at'] ?? '—'); ?></small></td>
                            <td style="text-align:center;">
                                <?php if ($st === 'pending_review') :
                                    $p1 = $repo->get_user_pool((int)($m['user_one_id'] ?? 0));
                                    $p2 = $repo->get_user_pool((int)($m['user_two_id'] ?? 0));
                                    $t1 = $p1['user_type'] ?? 'free';
                                    $t2 = $p2['user_type'] ?? 'free';
                                    $is_foe = in_array($t1, ['free', 'event'], true) || in_array($t2, ['free', 'event'], true);
                                ?>
                                    <?php if ($is_foe) : ?>
                                        <span style="display:block; margin-bottom:4px; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:600; background:#fef3c7; color:#92400e; border:1px solid #f59e0b;">
                                            ⚠️ Free/Event
                                        </span>
                                        <a href="<?php echo esc_url($reject_url); ?>" class="button button-small mm-reject-link"><?php esc_html_e('Reject', 'matchmaker'); ?></a>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary button-small"><?php esc_html_e('Approve', 'matchmaker'); ?></a>
                                        <a href="<?php echo esc_url($reject_url); ?>" class="button button-small mm-reject-link"><?php esc_html_e('Reject', 'matchmaker'); ?></a>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <a href="<?php echo esc_url($view_url); ?>" class="button button-small"><?php esc_html_e('View', 'matchmaker'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render Side-by-Side Dual Profile Comparison View for a Single Match Record
     *
     * @param int $match_id
     * @return void
     */
    private function render_single_match_view(int $match_id): void
    {
        $repo  = \Matchmaker\Repository\MatchRepository::instance();
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
        ?>
        <p><a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Back to Matches Queue', 'matchmaker'); ?></a></p>
        
        <div class="mm-detail-header">
            <div>
                <h2><?php sprintf(esc_html__('Match Pair #%d Side-by-Side Review', 'matchmaker'), $match_id); ?></h2>
                <p class="description">
                    <strong><?php esc_html_e('Compatibility Score:', 'matchmaker'); ?></strong> <?php echo (int) $match['score']; ?> / 6 &nbsp;|&nbsp; 
                    <strong><?php esc_html_e('Match Source:', 'matchmaker'); ?></strong> <?php echo esc_html(ucfirst($match['match_source'])); ?> &nbsp;|&nbsp; 
                    <strong><?php esc_html_e('Status:', 'matchmaker'); ?></strong> <span class="mm-status mm-status-<?php echo esc_attr($st); ?>"><?php echo esc_html(ucfirst(str_replace('_', ' ', $st))); ?></span>
                </p>
            </div>
            <div>
                <?php if ($st === 'pending_review') :
                    $t1 = $p1['user_type'] ?? 'free';
                    $t2 = $p2['user_type'] ?? 'free';
                    $is_foe = in_array($t1, ['free', 'event'], true) || in_array($t2, ['free', 'event'], true);
                ?>
                    <?php if ($is_foe) : ?>
                        <span style="display:inline-block; margin-right:8px; padding:6px 12px; border-radius:4px; font-size:12px; font-weight:600; background:#fef3c7; color:#92400e; border:1px solid #f59e0b;">
                            ⚠️ Free/Event Tier (Upgrade Required)
                        </span>
                        <a href="<?php echo esc_url($reject_url); ?>" class="button button-secondary button-hero mm-reject-link"><?php esc_html_e('Reject Match', 'matchmaker'); ?></a>
                    <?php else : ?>
                        <a href="<?php echo esc_url($approve_url); ?>" class="button button-primary button-hero" style="margin-right:8px;"><?php esc_html_e('Approve Match', 'matchmaker'); ?></a>
                        <a href="<?php echo esc_url($reject_url); ?>" class="button button-secondary button-hero mm-reject-link"><?php esc_html_e('Reject Match', 'matchmaker'); ?></a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="mm-grid-two">
            <!-- User 1 Card -->
            <div class="mm-card">
                <h3><?php echo esc_html($u1 ? $u1->display_name : 'User #' . $u1_id); ?> (User 1)</h3>
                <?php if (!empty($m1['user_photo1'])) : ?>
                    <div style="margin-bottom:15px;"><img src="<?php echo esc_url($m1['user_photo1']); ?>" style="width:120px;height:140px;object-fit:cover;border-radius:6px;"></div>
                <?php endif; ?>
                <table class="mm-kv-table">
                    <tr><th><?php esc_html_e('Email', 'matchmaker'); ?></th><td><?php echo esc_html($u1 ? $u1->user_email : '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Age', 'matchmaker'); ?></th><td><?php echo esc_html($repo->calc_age($p1['birth_date'] ?? '')); ?></td></tr>
                    <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($p1['gender'] ?? '')); ?></td></tr>
                    <tr><th><?php esc_html_e('Location', 'matchmaker'); ?></th><td><?php echo esc_html($p1['location'] ?? '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Origin', 'matchmaker'); ?></th><td><?php echo esc_html($p1['origin'] ?? '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Religion', 'matchmaker'); ?></th><td><?php echo esc_html($p1['religion'] ?? '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Modesty', 'matchmaker'); ?></th><td><?php echo esc_html($p1['modesty'] ?? '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Response', 'matchmaker'); ?></th><td><strong><?php echo esc_html(ucfirst($match['user_one_response'])); ?></strong></td></tr>
                </table>
            </div>

            <!-- User 2 Card -->
            <div class="mm-card">
                <h3><?php echo esc_html($u2 ? $u2->display_name : 'User #' . $u2_id); ?> (User 2)</h3>
                <?php if (!empty($m2['user_photo1'])) : ?>
                    <div style="margin-bottom:15px;"><img src="<?php echo esc_url($m2['user_photo1']); ?>" style="width:120px;height:140px;object-fit:cover;border-radius:6px;"></div>
                <?php endif; ?>
                <table class="mm-kv-table">
                    <tr><th><?php esc_html_e('Email', 'matchmaker'); ?></th><td><?php echo esc_html($u2 ? $u2->user_email : '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Age', 'matchmaker'); ?></th><td><?php echo esc_html($repo->calc_age($p2['birth_date'] ?? '')); ?></td></tr>
                    <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($p2['gender'] ?? '')); ?></td></tr>
                    <tr><th><?php esc_html_e('Location', 'matchmaker'); ?></th><td><?php echo esc_html($p2['location'] ?? '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Origin', 'matchmaker'); ?></th><td><?php echo esc_html($p2['origin'] ?? '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Religion', 'matchmaker'); ?></th><td><?php echo esc_html($p2['religion'] ?? '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Modesty', 'matchmaker'); ?></th><td><?php echo esc_html($p2['modesty'] ?? '—'); ?></td></tr>
                    <tr><th><?php esc_html_e('Response', 'matchmaker'); ?></th><td><strong><?php echo esc_html(ucfirst($match['user_two_response'])); ?></strong></td></tr>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Render plugin settings page with rich text email template editor & shortcodes reference
     *
     * @return void
     */
    public function render_settings_page(): void
    {
        $recurrence = (int) get_option('mm_auto_match_recurrence_days', 7);

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

        echo '<div class="wrap mm-admin-wrap">';
        echo '<h1>' . esc_html__('Matchmaker Settings & Documentation', 'matchmaker') . '</h1>';
        settings_errors('mm_admin_notices');

        ?>
        <form method="post" action="" style="max-width:900px; margin-top:20px;">
            <?php wp_nonce_field('mm_save_settings_nonce'); ?>
            
            <h2><?php esc_html_e('Matching Engine Options', 'matchmaker'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="mm_auto_match_recurrence_days"><?php esc_html_e('Auto-Match Recurrence (Days)', 'matchmaker'); ?></label></th>
                    <td>
                        <input type="number" min="1" max="30" name="mm_auto_match_recurrence_days" id="mm_auto_match_recurrence_days" value="<?php echo esc_attr((string)$recurrence); ?>" class="small-text">
                        <p class="description"><?php esc_html_e('Number of days between automatic matching runs for idle monthly users.', 'matchmaker'); ?></p>
                    </td>
                </tr>
            </table>

            <hr style="margin:30px 0;">

            <h2><?php esc_html_e('Approval Email Template', 'matchmaker'); ?></h2>
            <p class="description"><?php esc_html_e('Customize the email notification sent to both matched members when an admin approves a match.', 'matchmaker'); ?></p>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="mm_email_approval_subject"><?php esc_html_e('Email Subject', 'matchmaker'); ?></label></th>
                    <td>
                        <input type="text" name="mm_email_approval_subject" id="mm_email_approval_subject" value="<?php echo esc_attr($subject); ?>" class="large-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mm_email_approval_template"><?php esc_html_e('Email Body Template', 'matchmaker'); ?></label></th>
                    <td>
                        <?php
                        wp_editor($template, 'mm_email_approval_template', [
                            'textarea_name' => 'mm_email_approval_template',
                            'textarea_rows' => 12,
                            'media_buttons' => true,
                            'teeny'         => false,
                        ]);
                        ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Available Placeholders', 'matchmaker'); ?></th>
                    <td>
                        <table class="widefat compact striped" style="max-width:550px;">
                            <thead>
                                <tr><th><?php esc_html_e('Variable Tag', 'matchmaker'); ?></th><th><?php esc_html_e('Description', 'matchmaker'); ?></th></tr>
                            </thead>
                            <tbody>
                                <tr><td><code>{user_name}</code></td><td><?php esc_html_e('Recipient member display name', 'matchmaker'); ?></td></tr>
                                <tr><td><code>{candidate_name}</code></td><td><?php esc_html_e('Matched candidate display name', 'matchmaker'); ?></td></tr>
                                <tr><td><code>{candidate_age}</code></td><td><?php esc_html_e('Matched candidate age in years', 'matchmaker'); ?></td></tr>
                                <tr><td><code>{candidate_location}</code></td><td><?php esc_html_e('Matched candidate location', 'matchmaker'); ?></td></tr>
                                <tr><td><code>{dashboard_url}</code></td><td><?php esc_html_e('Direct member portal dashboard URL (/dashboard/)', 'matchmaker'); ?></td></tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="mm_save_settings" class="button button-primary" value="<?php esc_attr_e('Save Settings', 'matchmaker'); ?>">
            </p>
        </form>

        <hr style="margin:30px 0;">

        <h2><?php esc_html_e('Available Shortcodes Reference', 'matchmaker'); ?></h2>
        <table class="wp-list-table widefat fixed striped" style="max-width:800px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Shortcode', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Description', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Target Page / Location', 'matchmaker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><code>[matchmaker_member_portal]</code></td>
                    <td><?php esc_html_e('Primary 2-tab member portal dashboard (Profile & Matches)', 'matchmaker'); ?></td>
                    <td><code>/dashboard/</code></td>
                </tr>
                <tr>
                    <td><code>[az_profile]</code></td>
                    <td><?php esc_html_e('Backward-compatibility alias for member portal dashboard', 'matchmaker'); ?></td>
                    <td><code>/dashboard/</code></td>
                </tr>
                <tr>
                    <td><code>[matchmaking_form]</code></td>
                    <td><?php esc_html_e('Full 37-field matchmaking questionnaire wizard', 'matchmaker'); ?></td>
                    <td><code>/personal-matchmaking-questionnaire/</code></td>
                </tr>
                <tr>
                    <td><code>[matchmaking_field field="..."]</code></td>
                    <td><?php esc_html_e('Renders a single standalone matchmaking field input', 'matchmaker'); ?></td>
                    <td>Any page/elementor block</td>
                </tr>
                <tr>
                    <td><code>[logout_url]</code></td>
                    <td><?php esc_html_e('Outputs formatted logout URL with optional redirect attribute', 'matchmaker'); ?></td>
                    <td>Any menu or template</td>
                </tr>
            </tbody>
        </table>
        <?php
        echo '</div>';
    }

    /**
     * Render Matchmaking Logs & Debugger Page
     *
     * @return void
     */
    public function render_logs_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'matchmaker'));
        }

        global $wpdb;
        $repo = \Matchmaker\Repository\MatchRepository::instance();
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';
        $matches_table = $wpdb->prefix . 'matches';

        // Check if user requested running matching live for a specific user
        $run_notice = '';
        if (isset($_POST['mm_run_live_user_id'])) {
            check_admin_referer('mm_run_live_matching_nonce');
            $target_uid = (int) $_POST['mm_run_live_user_id'];
            if ($target_uid > 0) {
                $before_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$matches_table}");
                \Matchmaker\Core\MatchingEngine::instance()->run_matching_for_user($target_uid, 'admin_manual_trigger');
                $after_count  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$matches_table}");
                $new_inserted = $after_count - $before_count;
                $run_notice   = sprintf(__('Executed matching for User #%d! New match pairs created in database: %d.', 'matchmaker'), $target_uid, $new_inserted);
            }
        }

        // Get selected user ID (default to first active user in pool if not specified)
        $selected_user_id = (int) ($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
        if ($selected_user_id <= 0) {
            $selected_user_id = (int) $wpdb->get_var("SELECT user_id FROM {$pool_table} WHERE (is_active = 1 OR is_active IS NULL) ORDER BY user_id ASC LIMIT 1");
        }

        // Get list of all pool users for selector
        $all_pool_users = $wpdb->get_results(
            "SELECT p.user_id, p.gender, p.user_type, u.display_name, u.user_email
             FROM {$pool_table} p
             LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
             ORDER BY p.user_id ASC",
            ARRAY_A
        );

        // Load selected target user's pool data
        $target_user = $selected_user_id > 0 ? $repo->get_user_pool($selected_user_id) : null;
        $target_wp_user = $selected_user_id > 0 ? get_userdata($selected_user_id) : null;

        ?>
        <div class="wrap mm-admin-wrap">
            <h1><?php esc_html_e('🔍 Matchmaker Flow & Candidate Rejection Log', 'matchmaker'); ?></h1>
            <p class="description">
                <?php esc_html_e('Inspect full profile database records, evaluate bi-directional candidate hard gates side-by-side, view compatibility scores, and see the EXACT reason why matches passed or failed.', 'matchmaker'); ?>
            </p>

            <?php if (!empty($run_notice)) : ?>
                <div class="notice notice-success is-dismissible" style="margin-top:15px;"><p><strong><?php echo esc_html($run_notice); ?></strong></p></div>
            <?php endif; ?>

            <!-- Target User Selector & Live Execution Bar -->
            <div class="mm-card" style="margin: 20px 0; background: #f8fafc; border-left: 4px solid #0284c7;">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display:flex; flex-wrap:wrap; gap:15px; align-items:center;">
                    <input type="hidden" name="page" value="matchmaking-logs">
                    <label for="user_id"><strong><?php esc_html_e('Select Profile to Audit:', 'matchmaker'); ?></strong></label>
                    <select name="user_id" id="user_id" style="min-width:300px;">
                        <?php foreach ($all_pool_users as $pu) : ?>
                            <option value="<?php echo (int) $pu['user_id']; ?>" <?php selected($selected_user_id, (int) $pu['user_id']); ?>>
                                User #<?php echo (int) $pu['user_id']; ?>: <?php echo esc_html($pu['display_name'] ?: 'No Name'); ?> (<?php echo esc_html(ucfirst($pu['gender'])); ?>, <?php echo esc_html(ucfirst($pu['user_type'])); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Inspect & Debug Flow', 'matchmaker'); ?>">
                </form>

                <?php if ($target_user) : ?>
                    <form method="post" action="" style="margin-top:12px; display:inline-block;">
                        <?php wp_nonce_field('mm_run_live_matching_nonce'); ?>
                        <input type="hidden" name="mm_run_live_user_id" value="<?php echo (int) $selected_user_id; ?>">
                        <input type="submit" class="button button-secondary" style="color:#0284c7; border-color:#0284c7;" value="<?php echo esc_attr(sprintf(__('⚡ Run Live Matching Job For User #%d', 'matchmaker'), $selected_user_id)); ?>">
                    </form>
                <?php endif; ?>
            </div>

            <?php if (!$target_user) : ?>
                <div class="notice notice-warning"><p><?php esc_html_e('No candidate profile found in wp_matchmaking_pool.', 'matchmaker'); ?></p></div>
            <?php else :
                $target_age = $repo->calc_age($target_user['birth_date'] ?? '');
                $target_last_run = get_user_meta($selected_user_id, 'mm_last_match_run', true);
            ?>

                <!-- Section 1: DB Saved Profile Audit Card -->
                <div class="mm-card" style="margin-bottom:25px;">
                    <h3><?php echo esc_html(sprintf(__('📁 Section 1: Saved DB Record for User #%d (%s)', 'matchmaker'), $selected_user_id, $target_wp_user ? $target_wp_user->display_name : '')); ?></h3>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; font-size:13px;">
                        <div>
                            <table class="mm-kv-table">
                                <tr><th><?php esc_html_e('User ID / Email:', 'matchmaker'); ?></th><td>#<?php echo (int) $selected_user_id; ?> — <?php echo esc_html($target_wp_user ? $target_wp_user->user_email : ''); ?></td></tr>
                                <tr><th><?php esc_html_e('Membership Tier:', 'matchmaker'); ?></th><td><span class="mm-badge mm-badge-<?php echo esc_attr($target_user['user_type']); ?>"><?php echo esc_html(strtoupper($target_user['user_type'])); ?></span></td></tr>
                                <tr><th><?php esc_html_e('Active Status:', 'matchmaker'); ?></th><td><?php echo ((int)($target_user['is_active'] ?? 1) === 1) ? '<span style="color:#16a34a; font-weight:bold;">Active (1)</span>' : '<span style="color:#dc2626; font-weight:bold;">Inactive (0)</span>'; ?></td></tr>
                                <tr><th><?php esc_html_e('Gender & Preferred:', 'matchmaker'); ?></th><td><strong><?php echo esc_html(ucfirst($target_user['gender'] ?? '—')); ?></strong> seeking <strong><?php echo esc_html(ucfirst($target_user['pref_gender'] ?? 'Any')); ?></strong></td></tr>
                                <tr><th><?php esc_html_e('Age & Preferred Range:', 'matchmaker'); ?></th><td>Age <strong><?php echo esc_html($target_age); ?></strong> (Birth: <?php echo esc_html($target_user['birth_date'] ?? '—'); ?>) | Prefers Age <strong><?php echo (int)($target_user['preferred_age_min'] ?? 18); ?> - <?php echo (int)($target_user['preferred_age_max'] ?? 99); ?></strong></td></tr>
                                <tr><th><?php esc_html_e('Location & Preferred:', 'matchmaker'); ?></th><td>Loc: <strong><?php echo esc_html($target_user['location'] ?? '—'); ?></strong> | Pref: <strong><?php echo esc_html($target_user['pref_location'] ?? 'Any'); ?></strong></td></tr>
                            </table>
                        </div>
                        <div>
                            <table class="mm-kv-table">
                                <tr><th><?php esc_html_e('Religion & Preferred:', 'matchmaker'); ?></th><td>Rel: <strong><?php echo esc_html($target_user['religion'] ?? '—'); ?></strong> | Pref: <strong><?php echo esc_html($target_user['pref_religion'] ?? 'Any'); ?></strong></td></tr>
                                <tr><th><?php esc_html_e('Modesty & Preferred:', 'matchmaker'); ?></th><td>Mod: <strong><?php echo esc_html($target_user['modesty'] ?? '—'); ?></strong> | Pref: <strong><?php echo esc_html($target_user['pref_modesty'] ?? 'Any'); ?></strong></td></tr>
                                <tr><th><?php esc_html_e('Origin & Preferred:', 'matchmaker'); ?></th><td>Origin: <strong><?php echo esc_html($target_user['origin'] ?? '—'); ?></strong> | Pref: <strong><?php echo esc_html($target_user['pref_origin'] ?? 'Any'); ?></strong></td></tr>
                                <tr><th><?php esc_html_e('Languages:', 'matchmaker'); ?></th><td><strong><?php echo esc_html($target_user['languages'] ?? '—'); ?></strong></td></tr>
                                <tr><th><?php esc_html_e('Height & Range:', 'matchmaker'); ?></th><td>Height: <strong><?php echo (int)($target_user['height_cm'] ?? 0); ?> cm</strong> | Pref: <strong><?php echo (int)($target_user['preferred_height_min'] ?? 0); ?> - <?php echo (int)($target_user['preferred_height_max'] ?? 0); ?> cm</strong></td></tr>
                                <tr><th><?php esc_html_e('Last Match Run:', 'matchmaker'); ?></th><td><code><?php echo esc_html($target_last_run ?: 'Never'); ?></code></td></tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Candidate Evaluation & Flow Log -->
                <div class="mm-card">
                    <h3><?php esc_html_e('⚙️ Section 2: Step-by-Step Candidate Gate Evaluation & Rejection Reasons', 'matchmaker'); ?></h3>
                    <p class="description"><?php echo esc_html(sprintf(__('Evaluating User #%d against every candidate in wp_matchmaking_pool:', 'matchmaker'), $selected_user_id)); ?></p>

                    <?php
                    // Retrieve ALL other candidates in the pool
                    $candidates = $wpdb->get_results(
                        $wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id != %d ORDER BY user_id ASC", $selected_user_id),
                        ARRAY_A
                    );

                    if (empty($candidates)) : ?>
                        <p><?php esc_html_e('No other candidate profiles exist in the pool to check.', 'matchmaker'); ?></p>
                    <?php else :
                        $target_gender   = strtolower(trim((string)($target_user['gender'] ?? '')));
                        $target_pref_g   = strtolower(trim((string)($target_user['pref_gender'] ?? '')));
                        $target_loc      = trim((string)($target_user['location'] ?? ''));
                        $target_pref_loc = trim((string)($target_user['pref_location'] ?? ''));
                        $target_rel      = trim((string)($target_user['religion'] ?? ''));
                        $target_pref_rel = trim((string)($target_user['pref_religion'] ?? ''));
                        $target_mod      = trim((string)($target_user['modesty'] ?? ''));
                        $target_pref_mod = trim((string)($target_user['pref_modesty'] ?? ''));
                        $target_age_min  = (int)($target_user['preferred_age_min'] ?? 18);
                        $target_age_max  = (int)($target_user['preferred_age_max'] ?? 99);

                        $split = static fn(?string $v) => empty($v) ? [] : array_filter(array_map('trim', explode(',', $v)));
                        $in_list_ci = static fn(?string $n, ?string $h) => !empty($n) && !empty($h) && in_array(strtolower(trim($n)), array_map('strtolower', $split($h)), true);
                        $like_match = static fn(?string $v, ?string $list) => empty($v) || empty($list) || strtolower($list) === 'any' || str_contains(strtolower($list), strtolower($v));

                        foreach ($candidates as $cand) :
                            $cid   = (int) $cand['user_id'];
                            $cuser = get_userdata($cid);
                            $cage  = $repo->calc_age($cand['birth_date'] ?? '');
                            $c_gender = strtolower(trim((string)($cand['gender'] ?? '')));
                            $c_pref_g = strtolower(trim((string)($cand['pref_gender'] ?? '')));

                            $rejection_reasons = [];

                            // Gate 1: Active Status
                            $g1_pass = ((int)($cand['is_active'] ?? 1) === 1);
                            if (!$g1_pass) {
                                $rejection_reasons[] = __('Candidate profile is set to Inactive (is_active = 0)', 'matchmaker');
                            }

                            // Gate 2: Gender Bi-directional
                            $g2a_pass = ($target_pref_g === '' || $target_pref_g === 'any' || $target_pref_g === $c_gender || $in_list_ci($c_gender, $target_pref_g));
                            $g2b_pass = ($c_pref_g === '' || $c_pref_g === 'any' || $c_pref_g === $target_gender || $in_list_ci($target_gender, $c_pref_g));
                            $g2_pass  = $g2a_pass && $g2b_pass;
                            if (!$g2a_pass) {
                                $rejection_reasons[] = sprintf(__('Gender Gate: User #%d prefers %s, but Candidate #%d is %s', 'matchmaker'), $selected_user_id, ucfirst($target_pref_g), $cid, ucfirst($c_gender));
                            }
                            if (!$g2b_pass) {
                                $rejection_reasons[] = sprintf(__('Gender Gate: Candidate #%d prefers %s, but User #%d is %s', 'matchmaker'), $cid, ucfirst($c_pref_g), $selected_user_id, ucfirst($target_gender));
                            }

                            // Gate 3: Age Bi-directional
                            $cand_age_num = (is_numeric($cage) ? (int)$cage : 28);
                            $g3a_pass = ($cand['preferred_age_min'] <= 0 || $target_age >= $cand['preferred_age_min']) && ($cand['preferred_age_max'] <= 0 || $target_age <= $cand['preferred_age_max']);
                            $g3b_pass = ($cand_age_num >= $target_age_min && $cand_age_num <= $target_age_max);
                            $g3_pass  = $g3a_pass && $g3b_pass;
                            if (!$g3a_pass) {
                                $rejection_reasons[] = sprintf(__('Age Gate: User #%d age (%d) is outside Candidate #%d preferred age range (%d-%d)', 'matchmaker'), $selected_user_id, $target_age, $cid, $cand['preferred_age_min'], $cand['preferred_age_max']);
                            }
                            if (!$g3b_pass) {
                                $rejection_reasons[] = sprintf(__('Age Gate: Candidate #%d age (%d) is outside User #%d preferred age range (%d-%d)', 'matchmaker'), $cid, $cand_age_num, $selected_user_id, $target_age_min, $target_age_max);
                            }

                            // Gate 4: Location Bi-directional
                            $g4a_pass = empty($target_pref_loc) || strtolower($target_pref_loc) === 'any' || empty($cand['location']) || $in_list_ci($cand['location'], $target_pref_loc) || $like_match($cand['location'], $target_pref_loc);
                            $g4b_pass = empty($cand['pref_location']) || strtolower($cand['pref_location']) === 'any' || empty($target_loc) || $in_list_ci($target_loc, $cand['pref_location']) || $like_match($target_loc, $cand['pref_location']);
                            $g4_pass  = $g4a_pass && $g4b_pass;
                            if (!$g4a_pass) {
                                $rejection_reasons[] = sprintf(__('Location Gate: Candidate location "%s" is not in User preferred locations "%s"', 'matchmaker'), $cand['location'], $target_pref_loc);
                            }
                            if (!$g4b_pass) {
                                $rejection_reasons[] = sprintf(__('Location Gate: User location "%s" is not in Candidate preferred locations "%s"', 'matchmaker'), $target_loc, $cand['pref_location']);
                            }

                            // Gate 5: Religion Bi-directional
                            $g5a_pass = empty($target_pref_rel) || strtolower($target_pref_rel) === 'any' || empty($cand['religion']) || $in_list_ci($cand['religion'], $target_pref_rel) || $like_match($cand['religion'], $target_pref_rel);
                            $g5b_pass = empty($cand['pref_religion']) || strtolower($cand['pref_religion']) === 'any' || empty($target_rel) || $in_list_ci($target_rel, $cand['pref_religion']) || $like_match($target_rel, $cand['pref_religion']);
                            $g5_pass  = $g5a_pass && $g5b_pass;
                            if (!$g5a_pass) {
                                $rejection_reasons[] = sprintf(__('Religion Gate: Candidate religion "%s" does not match User preferred religion "%s"', 'matchmaker'), $cand['religion'], $target_pref_rel);
                            }
                            if (!$g5b_pass) {
                                $rejection_reasons[] = sprintf(__('Religion Gate: User religion "%s" does not match Candidate preferred religion "%s"', 'matchmaker'), $target_rel, $cand['pref_religion']);
                            }

                            // Gate 6: Modesty Bi-directional
                            $g6a_pass = empty($target_pref_mod) || strtolower($target_pref_mod) === 'any' || empty($cand['modesty']) || $in_list_ci($cand['modesty'], $target_pref_mod) || $like_match($cand['modesty'], $target_pref_mod);
                            $g6b_pass = empty($cand['pref_modesty']) || strtolower($cand['pref_modesty']) === 'any' || empty($target_mod) || $in_list_ci($target_mod, $cand['pref_modesty']) || $like_match($target_mod, $cand['pref_modesty']);
                            $g6_pass  = $g6a_pass && $g6b_pass;
                            if (!$g6a_pass) {
                                $rejection_reasons[] = sprintf(__('Modesty Gate: Candidate modesty "%s" does not match User preferred modesty "%s"', 'matchmaker'), $cand['modesty'], $target_pref_mod);
                            }
                            if (!$g6b_pass) {
                                $rejection_reasons[] = sprintf(__('Modesty Gate: User modesty "%s" does not match Candidate preferred modesty "%s"', 'matchmaker'), $target_mod, $cand['pref_modesty']);
                            }

                            // Gate 7: Pair Existence in DB
                            $u1 = min($selected_user_id, $cid);
                            $u2 = max($selected_user_id, $cid);
                            $existing_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$matches_table} WHERE user_one_id = %d AND user_two_id = %d", $u1, $u2), ARRAY_A);
                            $g7_pass = empty($existing_row);
                            if (!$g7_pass) {
                                $rejection_reasons[] = sprintf(__('Existing Pair Check: Pair (User #%d ↔ Candidate #%d) already exists in wp_matches with Status "%s" (Match ID #%d)', 'matchmaker'), $selected_user_id, $cid, $existing_row['status'], $existing_row['id']);
                            }

                            // Overall Hard Gate Evaluation
                            $all_hard_gates_pass = ($g1_pass && $g2_pass && $g3_pass && $g4_pass && $g5_pass && $g6_pass && $g7_pass);

                            // Score calculation
                            $score = \Matchmaker\Core\MatchingEngine::instance()->compute_flexible_score($target_user, $cand);
                        ?>

                            <div style="border:1px solid <?php echo $all_hard_gates_pass ? '#829067' : '#e2e8f0'; ?>; border-radius:8px; margin-top:20px; overflow:hidden; background:#fff;">
                                <div style="padding:15px; background:<?php echo $all_hard_gates_pass ? '#F0F4EC' : '#f8fafc'; ?>; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0;">
                                    <div>
                                        <strong style="font-size:15px;">Candidate #<?php echo $cid; ?>: <?php echo esc_html($cuser ? $cuser->display_name : 'No Name'); ?></strong>
                                        <span class="mm-badge mm-badge-<?php echo esc_attr($cand['user_type']); ?>" style="margin-left:8px;"><?php echo esc_html(strtoupper($cand['user_type'])); ?></span>
                                        <span style="margin-left:8px; color:#64748b; font-size:12px;"><?php echo esc_html(ucfirst($cand['gender'])); ?>, <?php echo esc_html($cage); ?> yrs, <?php echo esc_html($cand['location']); ?></span>
                                    </div>
                                    <div>
                                        <?php if ($all_hard_gates_pass) : ?>
                                            <span style="background:#16a34a; color:#fff; padding:4px 12px; border-radius:12px; font-weight:bold; font-size:12px;">🟢 PASSED ALL HARD GATES (Score <?php echo (int)$score; ?>/6)</span>
                                        <?php else : ?>
                                            <span style="background:#dc2626; color:#fff; padding:4px 12px; border-radius:12px; font-weight:bold; font-size:12px;">🔴 EXCLUDED (<?php echo count($rejection_reasons); ?> Gate Failures)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div style="padding:15px;">
                                    <table class="wp-list-table widefat fixed striped" style="margin-bottom:15px; font-size:12px;">
                                        <thead>
                                            <tr>
                                                <th style="width:130px;"><?php esc_html_e('Matching Gate', 'matchmaker'); ?></th>
                                                <th><?php esc_html_e('Target User #', 'matchmaker'); ?><?php echo $selected_user_id; ?> Value</th>
                                                <th><?php esc_html_e('Candidate #', 'matchmaker'); ?><?php echo $cid; ?> Value</th>
                                                <th style="width:100px; text-align:center;"><?php esc_html_e('Result', 'matchmaker'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><strong>1. Active Status</strong></td>
                                                <td>is_active = <?php echo (int)($target_user['is_active'] ?? 1); ?></td>
                                                <td>is_active = <?php echo (int)($cand['is_active'] ?? 1); ?></td>
                                                <td style="text-align:center;"><?php echo $g1_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>2. Gender Gate</strong></td>
                                                <td>Gender: <strong><?php echo esc_html(ucfirst($target_gender)); ?></strong> | Wants: <strong><?php echo esc_html(ucfirst($target_pref_g)); ?></strong></td>
                                                <td>Gender: <strong><?php echo esc_html(ucfirst($c_gender)); ?></strong> | Wants: <strong><?php echo esc_html(ucfirst($c_pref_g)); ?></strong></td>
                                                <td style="text-align:center;"><?php echo $g2_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>3. Age Gate</strong></td>
                                                <td>Age: <strong><?php echo esc_html($target_age); ?></strong> | Wants: <strong><?php echo $target_age_min; ?>-<?php echo $target_age_max; ?></strong></td>
                                                <td>Age: <strong><?php echo esc_html($cage); ?></strong> | Wants: <strong><?php echo (int)$cand['preferred_age_min']; ?>-<?php echo (int)$cand['preferred_age_max']; ?></strong></td>
                                                <td style="text-align:center;"><?php echo $g3_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>4. Location Gate</strong></td>
                                                <td>Loc: <strong><?php echo esc_html($target_loc); ?></strong> | Pref: <strong><?php echo esc_html($target_pref_loc); ?></strong></td>
                                                <td>Loc: <strong><?php echo esc_html($cand['location']); ?></strong> | Pref: <strong><?php echo esc_html($cand['pref_location']); ?></strong></td>
                                                <td style="text-align:center;"><?php echo $g4_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>5. Religion Gate</strong></td>
                                                <td>Rel: <strong><?php echo esc_html($target_rel); ?></strong> | Pref: <strong><?php echo esc_html($target_pref_rel); ?></strong></td>
                                                <td>Rel: <strong><?php echo esc_html($cand['religion']); ?></strong> | Pref: <strong><?php echo esc_html($cand['pref_religion']); ?></strong></td>
                                                <td style="text-align:center;"><?php echo $g5_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>6. Modesty Gate</strong></td>
                                                <td>Mod: <strong><?php echo esc_html($target_mod); ?></strong> | Pref: <strong><?php echo esc_html($target_pref_mod); ?></strong></td>
                                                <td>Mod: <strong><?php echo esc_html($cand['modesty']); ?></strong> | Pref: <strong><?php echo esc_html($cand['pref_modesty']); ?></strong></td>
                                                <td style="text-align:center;"><?php echo $g6_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>7. Pair Uniqueness</strong></td>
                                                <td>User #<?php echo $selected_user_id; ?></td>
                                                <td>Candidate #<?php echo $cid; ?></td>
                                                <td style="text-align:center;"><?php echo $g7_pass ? '<span style="color:#16a34a;font-weight:bold;">PASSED ✅</span>' : '<span style="color:#dc2626;font-weight:bold;">FAILED ❌</span>'; ?></td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <!-- Rejection Reasons or Match Success Explanation -->
                                    <div style="padding:12px; border-radius:6px; background:<?php echo $all_hard_gates_pass ? '#f0fdf4' : '#fef2f2'; ?>; border:1px solid <?php echo $all_hard_gates_pass ? '#bbf7d0' : '#fecaca'; ?>;">
                                        <strong style="color:<?php echo $all_hard_gates_pass ? '#166534' : '#991b1b'; ?>;">
                                            <?php if ($all_hard_gates_pass) : ?>
                                                <?php esc_html_e('✅ MATCH ELIGIBLE: Candidate passes all 7 bi-directional hard gates with a flexible score of ' . $score . '/6.', 'matchmaker'); ?>
                                            <?php else : ?>
                                                <?php esc_html_e('❌ EXCLUSION REASON(S): Why this candidate did not match:', 'matchmaker'); ?>
                                            <?php endif; ?>
                                        </strong>
                                        <?php if (!empty($rejection_reasons)) : ?>
                                            <ul style="margin:8px 0 0 20px; color:#991b1b; font-size:12px;">
                                                <?php foreach ($rejection_reasons as $reason) : ?>
                                                    <li><?php echo esc_html($reason); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        </div>
        <?php
    }
}
