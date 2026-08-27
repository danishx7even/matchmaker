<?php
declare(strict_types=1);
namespace Matchmaker;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Admin_Portal
 *
 * Registers the top-level Matchmaking admin menu and sub-pages.
 * Handles pool browser, single user detail view, manual match creation with advanced filters,
 * single match detail view (side-by-side), minimalistic matches queue, and match approval/rejection.
 */
class Admin_Portal {

    private static ?self $instance = null;

    /* -------------------------------------------------------
       Singleton
    ------------------------------------------------------- */
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

    private function boot(): void
    {
        add_action('admin_menu',            [$this, 'register_menu'], 30);
        add_action('admin_init',            [$this, 'handle_admin_actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /* -------------------------------------------------------
       Admin Asset Enqueue
    ------------------------------------------------------- */
    public function enqueue_admin_assets(string $hook): void
    {
        if (!str_contains($hook, 'matchmaking-pool') && !str_contains($hook, 'matchmaking-matches') && !str_contains($hook, 'matchmaking-settings')) {
            return;
        }

        $plugin_url = plugin_dir_url(dirname(__FILE__));
        $version    = defined('MM_VERSION') ? MM_VERSION : '1.0.0';

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

    /* -------------------------------------------------------
       Register Admin Menu & Submenus
    ------------------------------------------------------- */
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
            __('Matches', 'matchmaker'),
            __('Matches', 'matchmaker'),
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
    }

    /* -------------------------------------------------------
       Handle Admin Actions: Approve / Reject / Trigger / Manual Match / Seed
    ------------------------------------------------------- */
    public function handle_admin_actions(): void
    {
        $page = sanitize_text_field(wp_unslash($_GET['page'] ?? ''));
        if (!in_array($page, ['matchmaking-pool', 'matchmaking-matches', 'matchmaking-settings'], true)) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $action   = sanitize_text_field(wp_unslash($_GET['mm_action'] ?? ''));
        $match_id = (int) ($_GET['match_id'] ?? 0);
        $user_id  = (int) ($_GET['user_id'] ?? 0);
        $nonce    = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));

        if (empty($action)) {
            return;
        }

        global $wpdb;
        $matches_table = $wpdb->prefix . 'matches';
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';

        // --- APPROVE ---
        if ($action === 'approve' && $match_id > 0 && wp_verify_nonce($nonce, 'mm_approve_' . $match_id)) {
            $match = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$matches_table} WHERE id = %d", $match_id)
            );

            if (!$match) {
                add_settings_error('mm_admin_notices', 'not_found', __('Match record not found.', 'matchmaker'), 'error');
                return;
            }

            // Check if EITHER user or candidate has free or event type
            $u1_type = (string) $wpdb->get_var($wpdb->prepare("SELECT user_type FROM {$pool_table} WHERE user_id = %d", $match->user_one_id));
            $u2_type = (string) $wpdb->get_var($wpdb->prepare("SELECT user_type FROM {$pool_table} WHERE user_id = %d", $match->user_two_id));

            if (in_array($u1_type, ['free', 'event'], true) || in_array($u2_type, ['free', 'event'], true)) {
                add_settings_error(
                    'mm_admin_notices',
                    'tier_approval_blocked',
                    __('Approval blocked: Matches involving a Free or Event tier user are informational only and cannot be approved.', 'matchmaker'),
                    'error'
                );
                return;
            }

            $initiator_id  = (int) $match->initiator_user_id;
            $current_quota = (int) get_user_meta($initiator_id, 'cycle_matches_count', true);

            if ($current_quota >= 10) {
                add_settings_error(
                    'mm_admin_notices',
                    'quota_exceeded',
                    __('Approval blocked: This user has already used their 10-match monthly quota.', 'matchmaker'),
                    'error'
                );
                return;
            }

            $updated = $wpdb->update(
                $matches_table,
                [
                    'status'      => 'approved',
                    'approved_by' => get_current_user_id(),
                    'approved_at' => current_time('mysql'),
                ],
                ['id' => $match_id],
                ['%s', '%d', '%s'],
                ['%d']
            );

            if ($updated !== false) {
                update_user_meta($initiator_id, 'cycle_matches_count', $current_quota + 1);

                // Trigger email notifications and flush heartbeat transients
                if (class_exists('\Matchmaker\Notification_Manager')) {
                    Notification_Manager::instance()->send_approval_emails($match_id);
                }

                add_settings_error(
                    'mm_admin_notices',
                    'match_approved',
                    sprintf(
                        /* translators: 1: match ID, 2: quota count */
                        __('Match #%1$d approved and notification emails sent to both members. Quota used: %2$d/10.', 'matchmaker'),
                        $match_id,
                        $current_quota + 1
                    ),
                    'success'
                );
            }
        }

        // --- REJECT ---
        if ($action === 'reject' && $match_id > 0 && wp_verify_nonce($nonce, 'mm_reject_' . $match_id)) {
            $wpdb->update(
                $matches_table,
                ['status' => 'admin_rejected'],
                ['id'     => $match_id],
                ['%s'],
                ['%d']
            );
            add_settings_error(
                'mm_admin_notices',
                'match_rejected',
                sprintf(__('Match #%d marked as rejected.', 'matchmaker'), $match_id),
                'info'
            );
        }

        // --- TRIGGER AUTO-MATCH ---
        if ($action === 'trigger_match' && $user_id > 0 && wp_verify_nonce($nonce, 'mm_trigger_' . $user_id)) {
            $in_pool = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$pool_table} WHERE user_id = %d AND is_active = 1", $user_id));

            if ($in_pool === 0) {
                add_settings_error(
                    'mm_admin_notices',
                    'not_in_pool',
                    sprintf(__('User #%d does not have an active profile in the matchmaking pool.', 'matchmaker'), $user_id),
                    'error'
                );
            } else {
                mm_enqueue_user_matching_job($user_id, 'admin_manual_trigger');
                add_settings_error(
                    'mm_admin_notices',
                    'match_triggered',
                    sprintf(__('Asynchronous auto-matching schedule enqueued successfully for User #%d via Action Scheduler.', 'matchmaker'), $user_id),
                    'success'
                );
            }
        }

        // --- SAVE SETTINGS ---
        if ($action === 'save_settings' && wp_verify_nonce($nonce, 'mm_save_settings')) {
            $recurrence_days = isset($_POST['mm_auto_match_recurrence_days']) ? max(1, (int) $_POST['mm_auto_match_recurrence_days']) : 7;
            $max_candidates  = isset($_POST['mm_max_candidates_per_run']) ? max(1, (int) $_POST['mm_max_candidates_per_run']) : 10;

            update_option('mm_auto_match_recurrence_days', $recurrence_days);
            update_option('mm_max_candidates_per_run', $max_candidates);

            if (isset($_POST['mm_email_approval_subject'])) {
                update_option('mm_email_approval_subject', sanitize_text_field(wp_unslash((string) $_POST['mm_email_approval_subject'])));
            }

            if (isset($_POST['mm_email_approval_template'])) {
                update_option('mm_email_approval_template', wp_kses_post(wp_unslash((string) $_POST['mm_email_approval_template'])));
            }

            add_settings_error(
                'mm_admin_notices',
                'settings_saved',
                __('Matchmaking and email settings saved successfully.', 'matchmaker'),
                'success'
            );
        }

        // --- CREATE MANUAL MATCH ---
        if ($action === 'create_manual_match' && $user_id > 0 && $match_id > 0 && wp_verify_nonce($nonce, 'mm_create_manual_' . $user_id . '_' . $match_id)) {
            $candidate_id = $match_id;
            $u1_id        = min($user_id, $candidate_id);
            $u2_id        = max($user_id, $candidate_id);

            $p1 = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $user_id), ARRAY_A);
            $p2 = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $candidate_id), ARRAY_A);

            $score = ($p1 && $p2) ? Matching_Engine::instance()->compute_flexible_score($p1, $p2) : 0;

            $inserted = $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$matches_table}
                    (user_one_id, user_two_id, initiator_user_id, status, match_source, score)
                 VALUES (%d, %d, %d, 'pending_review', 'admin_manual', %d)",
                $u1_id, $u2_id, $user_id, $score
            ));

            if ($inserted) {
                $new_match_id = $wpdb->insert_id;
                add_settings_error(
                    'mm_admin_notices',
                    'manual_match_created',
                    sprintf(
                        /* translators: 1: match ID, 2: target user ID, 3: candidate ID */
                        __('Manual match pairing #%1$d created between User #%2$d and Candidate #%3$d with status Pending Review.', 'matchmaker'),
                        $new_match_id,
                        $user_id,
                        $candidate_id
                    ),
                    'success'
                );
            } else {
                add_settings_error(
                    'mm_admin_notices',
                    'manual_match_exists',
                    __('A match pairing already exists between these two users.', 'matchmaker'),
                    'warning'
                );
            }
        }

        // --- SEED TEST USERS ---
        if ($action === 'seed_test_users' && wp_verify_nonce($nonce, 'mm_seed_test_users')) {
            if (class_exists('\Matchmaker\Test_Seeder')) {
                $seeded = Test_Seeder::run();
                add_settings_error(
                    'mm_admin_notices',
                    'seeded_test_users',
                    sprintf(__('Successfully created %d test candidate profiles across all membership tiers and ran auto-matching for Monthly users!', 'matchmaker'), count($seeded)),
                    'success'
                );
            }
        }
    }

    /* -------------------------------------------------------
       Router: Candidate Pool Page
    ------------------------------------------------------- */
    public function render_admin_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'matchmaker'));
        }

        $view_user_id    = (int) ($_GET['view_user'] ?? 0);
        $view_match_id   = (int) ($_GET['view_match'] ?? 0);
        $manual_match_id = (int) ($_GET['manual_match'] ?? 0);

        echo '<div class="wrap mm-admin-wrap">';
        settings_errors('mm_admin_notices');

        if ($manual_match_id > 0) {
            $this->render_manual_match_view($manual_match_id);
        } elseif ($view_match_id > 0) {
            $this->render_single_match_view($view_match_id);
        } elseif ($view_user_id > 0) {
            $this->render_user_detail_view($view_user_id);
        } else {
            $this->render_pool_browser();
        }

        echo '</div>';
    }

    /* -------------------------------------------------------
       View: Pool Browser Table
    ------------------------------------------------------- */
    private function render_pool_browser(): void
    {
        global $wpdb;
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';
        $matches_table = $wpdb->prefix . 'matches';

        $search    = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $user_type = sanitize_text_field(wp_unslash($_GET['user_type'] ?? ''));
        $gender    = sanitize_text_field(wp_unslash($_GET['gender'] ?? ''));

        $where = ['1=1'];
        $args  = [];

        if (!empty($user_type)) {
            $where[] = 'p.user_type = %s';
            $args[]  = $user_type;
        }
        if (!empty($gender)) {
            $where[] = 'p.gender = %s';
            $args[]  = $gender;
        }
        if (!empty($search)) {
            $where[] = '(u.user_email LIKE %s OR u.display_name LIKE %s OR p.location LIKE %s)';
            $wc      = '%' . $wpdb->esc_like($search) . '%';
            $args[]  = $wc;
            $args[]  = $wc;
            $args[]  = $wc;
        }

        $where_sql = implode(' AND ', $where);

        $query = "
            SELECT p.*, u.user_email, u.display_name,
                (SELECT COUNT(*) FROM {$matches_table} m
                    WHERE (m.user_one_id = p.user_id OR m.user_two_id = p.user_id) AND m.status = 'approved') AS approved_matches,
                (SELECT COUNT(*) FROM {$matches_table} m
                    WHERE (m.user_one_id = p.user_id OR m.user_two_id = p.user_id) AND m.status = 'pending_review') AS pending_matches
            FROM {$pool_table} p
            INNER JOIN {$wpdb->users} u ON p.user_id = u.ID
            WHERE {$where_sql}
            ORDER BY p.updated_at DESC
        ";

        $results = !empty($args)
            ? $wpdb->get_results($wpdb->prepare($query, ...$args))
            : $wpdb->get_results($query);

        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e('Matchmaking Candidate Pool', 'matchmaker'); ?></h1>
        <hr class="wp-header-end">

        <form method="get" class="mm-filter-bar">
            <input type="hidden" name="page" value="matchmaking-pool">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php esc_attr_e('Search name, email, location…', 'matchmaker'); ?>">

            <select name="user_type">
                <option value=""><?php esc_html_e('All Membership Tiers', 'matchmaker'); ?></option>
                <option value="monthly"   <?php selected($user_type, 'monthly');    ?>><?php esc_html_e('Monthly Member', 'matchmaker'); ?></option>
                <option value="one_on_one"<?php selected($user_type, 'one_on_one'); ?>><?php esc_html_e('1-on-1 Member', 'matchmaker'); ?></option>
                <option value="free"      <?php selected($user_type, 'free');       ?>><?php esc_html_e('Free User', 'matchmaker'); ?></option>
                <option value="event"     <?php selected($user_type, 'event');      ?>><?php esc_html_e('Event User', 'matchmaker'); ?></option>
            </select>

            <select name="gender">
                <option value=""><?php esc_html_e('All Genders', 'matchmaker'); ?></option>
                <option value="male"  <?php selected($gender, 'male');   ?>><?php esc_html_e('Male', 'matchmaker'); ?></option>
                <option value="female"<?php selected($gender, 'female'); ?>><?php esc_html_e('Female', 'matchmaker'); ?></option>
            </select>

            <button type="submit" class="button"><?php esc_html_e('Filter Pool', 'matchmaker'); ?></button>
            <?php if (!empty($search) || !empty($user_type) || !empty($gender)): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool')); ?>" class="button-link">
                    <?php esc_html_e('Reset', 'matchmaker'); ?>
                </a>
            <?php endif; ?>

            <?php
            $seed_url = wp_nonce_url(
                admin_url('admin.php?page=matchmaking-pool&mm_action=seed_test_users'),
                'mm_seed_test_users'
            );
            ?>
            <a href="<?php echo esc_url($seed_url); ?>" class="button button-primary" style="margin-left:auto;">
                + <?php esc_html_e('Generate 10 Test Users & Matches', 'matchmaker'); ?>
            </a>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:50px;"><?php esc_html_e('Photo', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Name / Email', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Tier', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Age / Gender', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Location / Origin', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Modesty / Religion', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Matches (Pend. / Appr.)', 'matchmaker'); ?></th>
                    <th style="width:160px;"><?php esc_html_e('Actions', 'matchmaker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr><td colspan="8"><?php esc_html_e('No candidate profiles found matching criteria.', 'matchmaker'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($results as $row): ?>
                        <?php
                            $photo1  = get_user_meta((int) $row->user_id, 'user_photo1', true);
                            $age     = !empty($row->birth_date)
                                ? (new \DateTime())->diff(new \DateTime($row->birth_date))->y
                                : 'N/A';
                            $detail_url = admin_url('admin.php?page=matchmaking-pool&view_user=' . (int) $row->user_id);
                        ?>
                        <tr>
                            <td>
                                <div class="mm-avatar-thumb">
                                    <?php if (!empty($photo1)): ?>
                                        <img src="<?php echo esc_url($photo1); ?>" alt="">
                                    <?php else: ?>
                                        <span class="dashicons dashicons-admin-users"></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url($detail_url); ?>">
                                        <?php echo esc_html($row->display_name ?: 'User #' . $row->user_id); ?>
                                    </a>
                                </strong><br>
                                <span class="description"><?php echo esc_html($row->user_email); ?></span>
                            </td>
                            <td>
                                <span class="mm-badge mm-badge-<?php echo esc_attr($row->user_type); ?>">
                                    <?php echo esc_html(strtoupper(str_replace('_', ' ', $row->user_type))); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($age . ' yrs / ' . ucfirst($row->gender)); ?></td>
                            <td><?php echo esc_html($row->location . ' (' . ($row->origin ?: 'N/A') . ')'); ?></td>
                            <td><?php echo esc_html($row->religion . ' / ' . ($row->modesty ?: 'N/A')); ?></td>
                            <td>
                                <span class="mm-count-pending"><?php echo (int) $row->pending_matches; ?> <?php esc_html_e('Pending', 'matchmaker'); ?></span> /
                                <span class="mm-count-approved"><?php echo (int) $row->approved_matches; ?> <?php esc_html_e('Appr.', 'matchmaker'); ?></span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($detail_url); ?>" class="button button-small">
                                    <?php esc_html_e('View Profile &amp; Matches', 'matchmaker'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /* -------------------------------------------------------
       View: User Detail & Match Queue
    ------------------------------------------------------- */
    private function render_user_detail_view(int $user_id): void
    {
        global $wpdb;
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';
        $matches_table = $wpdb->prefix . 'matches';

        $pool = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $user_id),
            ARRAY_A
        );
        $user = get_userdata($user_id);

        if (!$pool || !$user) {
            echo '<p>' . esc_html__('User not found in matchmaking pool.', 'matchmaker') . '</p>';
            return;
        }

        $age          = !empty($pool['birth_date']) ? (new \DateTime())->diff(new \DateTime($pool['birth_date']))->y : 'N/A';
        $quota_used   = (int) get_user_meta($user_id, 'cycle_matches_count', true);
        $trigger_url  = wp_nonce_url(
            admin_url("admin.php?page=matchmaking-pool&mm_action=trigger_match&user_id={$user_id}&view_user={$user_id}"),
            'mm_trigger_' . $user_id
        );
        $manual_match_url = admin_url("admin.php?page=matchmaking-pool&manual_match={$user_id}");

        // Fetch all matches involving this user
        $matches = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*,
                CASE WHEN m.user_one_id = %d THEN m.user_two_id   ELSE m.user_one_id    END AS candidate_id,
                CASE WHEN m.user_one_id = %d THEN m.user_one_response ELSE m.user_two_response END AS my_response,
                CASE WHEN m.user_one_id = %d THEN m.user_two_response ELSE m.user_one_response END AS their_response
             FROM {$matches_table} m
             WHERE m.user_one_id = %d OR m.user_two_id = %d
             ORDER BY (m.status = 'pending_review') DESC, m.score DESC, m.created_at DESC",
            $user_id, $user_id, $user_id, $user_id, $user_id
        ));
        ?>

        <!-- Detail Header -->
        <div class="mm-detail-header">
            <div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool')); ?>">
                    &larr; <?php esc_html_e('Back to Candidate Pool', 'matchmaker'); ?>
                </a>
                <h2>
                    <?php echo esc_html($user->display_name); ?>
                    <span class="mm-badge mm-badge-<?php echo esc_attr($pool['user_type']); ?>">
                        <?php echo esc_html(strtoupper(str_replace('_', ' ', $pool['user_type']))); ?>
                    </span>
                </h2>
                <p class="description">
                    <?php esc_html_e('Email:', 'matchmaker'); ?> <?php echo esc_html($user->user_email); ?> |
                    <?php esc_html_e('Phone:', 'matchmaker'); ?> <?php echo esc_html(get_user_meta($user_id, 'phone_number', true) ?: 'N/A'); ?> |
                    <?php esc_html_e('Monthly Quota:', 'matchmaker'); ?> <strong><?php echo $quota_used; ?> / 10 <?php esc_html_e('Used', 'matchmaker'); ?></strong>
                </p>
            </div>
            <div>
                <a href="<?php echo esc_url($manual_match_url); ?>" class="button button-secondary button-hero" style="margin-right:8px;">
                    + <?php esc_html_e('Manual Match', 'matchmaker'); ?>
                </a>
                <a href="<?php echo esc_url($trigger_url); ?>" class="button button-primary button-hero">
                    <?php esc_html_e('Run Auto-Match Scoring', 'matchmaker'); ?>
                </a>
            </div>
        </div>

        <!-- Profile Cards -->
        <div class="mm-grid-two">
            <!-- Self Profile -->
            <div class="mm-card">
                <h3><?php esc_html_e('Candidate Self Profile', 'matchmaker'); ?></h3>
                <table class="mm-kv-table">
                    <tr><th><?php esc_html_e('Age / Birthday', 'matchmaker'); ?></th>
                        <td><?php echo esc_html($age . ' yrs (' . $pool['birth_date'] . ')'); ?></td></tr>
                    <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th>
                        <td><?php echo esc_html(ucfirst($pool['gender'])); ?></td></tr>
                    <tr><th><?php esc_html_e('Location', 'matchmaker'); ?></th>
                        <td><?php echo esc_html($pool['location']); ?></td></tr>
                    <tr><th><?php esc_html_e('Citizenship', 'matchmaker'); ?></th>
                        <td><?php echo esc_html(get_user_meta($user_id, 'user_citizenship', true) ?: 'N/A'); ?></td></tr>
                    <tr><th><?php esc_html_e('Origin / Ethnicity', 'matchmaker'); ?></th>
                        <td><?php echo esc_html($pool['origin'] ?: 'N/A'); ?></td></tr>
                    <tr><th><?php esc_html_e('Religion / Modesty', 'matchmaker'); ?></th>
                        <td><?php echo esc_html($pool['religion'] . ' / ' . $pool['modesty']); ?></td></tr>
                    <tr><th><?php esc_html_e('Height', 'matchmaker'); ?></th>
                        <td><?php echo esc_html($pool['height_cm'] ? $pool['height_cm'] . ' cm' : 'N/A'); ?></td></tr>
                    <tr><th><?php esc_html_e('Languages', 'matchmaker'); ?></th>
                        <td><?php echo esc_html($pool['languages'] ?: 'N/A'); ?></td></tr>
                    <tr><th><?php esc_html_e('Job / Career', 'matchmaker'); ?></th>
                        <td><?php echo esc_html($pool['job'] ?: 'N/A'); ?></td></tr>
                    <tr><th><?php esc_html_e('Smoking / Drinking', 'matchmaker'); ?></th>
                        <td><?php echo esc_html(($pool['smoking'] ?: 'N/A') . ' / ' . ($pool['drinking'] ?: 'N/A')); ?></td></tr>
                    <tr><th><?php esc_html_e('Marital / Children', 'matchmaker'); ?></th>
                        <td><?php echo esc_html(
                            (get_user_meta($user_id, 'user_marital_status', true) ?: 'N/A')
                            . ' / '
                            . (get_user_meta($user_id, 'user_children', true) ?: 'N/A')
                        ); ?></td></tr>
                    <tr><th><?php esc_html_e('Social Links', 'matchmaker'); ?></th>
                        <td><?php echo nl2br(esc_html(get_user_meta($user_id, 'user_social_links', true) ?: 'N/A')); ?></td></tr>
                </table>

                <h4><?php esc_html_e('Profile Photos', 'matchmaker'); ?></h4>
                <div class="mm-photos-grid">
                    <?php foreach (['user_photo1', 'user_photo2', 'user_photo3'] as $pk):
                        $purl = get_user_meta($user_id, $pk, true);
                        if (!empty($purl)): ?>
                            <a href="<?php echo esc_url($purl); ?>" target="_blank" rel="noopener">
                                <img src="<?php echo esc_url($purl); ?>" alt="">
                            </a>
                    <?php endif; endforeach; ?>
                </div>
            </div>

            <!-- Partner Preferences -->
            <div class="mm-card">
                <h3><?php esc_html_e('Partner Search Preferences', 'matchmaker'); ?></h3>
                <table class="mm-kv-table">
                    <tr><th><?php esc_html_e('Pref. Gender', 'matchmaker'); ?></th>
                        <td><?php echo esc_html(ucfirst($pool['pref_gender'])); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Age Range', 'matchmaker'); ?></th>
                        <td><?php echo esc_html($pool['preferred_age_min'] . ' – ' . $pool['preferred_age_max'] . ' yrs'); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Locations', 'matchmaker'); ?></th>
                        <td><?php echo esc_html($pool['pref_location'] ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Citizenship', 'matchmaker'); ?></th>
                        <td><?php echo esc_html(get_user_meta($user_id, 'pref_citizenship', true) ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Origin', 'matchmaker'); ?></th>
                        <td><?php echo esc_html($pool['pref_origin'] ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Religion / Modesty', 'matchmaker'); ?></th>
                        <td><?php echo esc_html(($pool['pref_religion'] ?: 'Any') . ' / ' . ($pool['pref_modesty'] ?: 'Any')); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Height Range', 'matchmaker'); ?></th>
                        <td><?php echo esc_html(
                            ($pool['preferred_height_min'] ?: 'Min') . ' cm – ' .
                            ($pool['preferred_height_max'] ?: 'Max') . ' cm'
                        ); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Languages', 'matchmaker'); ?></th>
                        <td><?php echo esc_html($pool['pref_languages'] ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Lifestyle', 'matchmaker'); ?></th>
                        <td><?php echo esc_html(
                            'Smoking: ' . ($pool['pref_smoking'] ?: 'Any') .
                            ' | Drinking: ' . ($pool['pref_drinking'] ?: 'Any')
                        ); ?></td></tr>
                    <tr><th><?php esc_html_e('Ideal Partner Note', 'matchmaker'); ?></th>
                        <td><em><?php echo nl2br(esc_html(get_user_meta($user_id, 'pref_additional_info', true) ?: __('No additional notes provided.', 'matchmaker'))); ?></em></td></tr>
                </table>
            </div>
        </div>

        <!-- Match Queue Table -->
        <div class="mm-card" style="margin-top:24px;">
            <h3>
                <?php
                printf(
                    /* translators: %d: number of match pairings */
                    esc_html__('Match History &amp; Approval Queue (%d)', 'matchmaker'),
                    count($matches)
                );
                ?>
            </h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:40px;"><?php esc_html_e('Photo', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Candidate', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Tier', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Flex. Score', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Source', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Status', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('User Responses', 'matchmaker'); ?></th>
                        <th style="width:180px;"><?php esc_html_e('Admin Action', 'matchmaker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($matches)): ?>
                        <tr>
                            <td colspan="8">
                                <?php esc_html_e('No match pairings generated yet. Click "Run Auto-Match Scoring" above to find candidates.', 'matchmaker'); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($matches as $m):
                            $c_id     = (int) $m->candidate_id;
                            $c_user   = get_userdata($c_id);
                            $c_pool   = $wpdb->get_row(
                                $wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $c_id),
                                ARRAY_A
                            );
                            $c_photo  = get_user_meta($c_id, 'user_photo1', true);
                            $c_age    = !empty($c_pool['birth_date'])
                                ? (new \DateTime())->diff(new \DateTime($c_pool['birth_date']))->y
                                : 'N/A';

                            $user_tier    = $pool['user_type'] ?? 'free';
                            $cand_tier    = $c_pool['user_type'] ?? 'free';
                            $is_info_only = in_array($user_tier, ['free', 'event'], true) || in_array($cand_tier, ['free', 'event'], true);

                            $appr_url = wp_nonce_url(
                                admin_url("admin.php?page=matchmaking-pool&mm_action=approve&match_id={$m->id}&view_user={$user_id}"),
                                'mm_approve_' . $m->id
                            );
                            $rej_url  = wp_nonce_url(
                                admin_url("admin.php?page=matchmaking-pool&mm_action=reject&match_id={$m->id}&view_user={$user_id}"),
                                'mm_reject_' . $m->id
                            );
                            $view_match_url = admin_url("admin.php?page=matchmaking-matches&view_match={$m->id}");
                        ?>
                            <tr>
                                <td>
                                    <div class="mm-avatar-thumb">
                                        <?php if (!empty($c_photo)): ?>
                                            <img src="<?php echo esc_url($c_photo); ?>" alt="">
                                        <?php else: ?>
                                            <span class="dashicons dashicons-admin-users"></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $c_id)); ?>">
                                            <?php echo esc_html($c_user ? $c_user->display_name : 'Candidate #' . $c_id); ?>
                                        </a>
                                    </strong><br>
                                    <span class="description"><?php echo esc_html($c_age . ' yrs | ' . ($c_pool['location'] ?? '')); ?></span>
                                    <br><a href="<?php echo esc_url($view_match_url); ?>" style="font-size:11px;"><?php esc_html_e('View Match Details &rarr;', 'matchmaker'); ?></a>
                                </td>
                                <td>
                                    <span class="mm-badge mm-badge-<?php echo esc_attr($cand_tier); ?>">
                                        <?php echo esc_html(strtoupper(str_replace('_', ' ', $cand_tier))); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo (int) $m->score; ?></strong> / 6 pts</td>
                                <td><?php echo esc_html(ucfirst((string) $m->match_source)); ?></td>
                                <td>
                                    <span class="mm-status mm-status-<?php echo esc_attr($m->status); ?>">
                                        <?php echo esc_html(str_replace('_', ' ', strtoupper((string) $m->status))); ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <strong><?php echo esc_html($user->display_name); ?>:</strong> <?php echo esc_html(ucfirst((string) $m->my_response ?: 'pending')); ?><br>
                                        <strong><?php echo esc_html($c_user ? $c_user->display_name : 'Candidate #' . $c_id); ?>:</strong> <?php echo esc_html(ucfirst((string) $m->their_response ?: 'pending')); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($m->status === 'pending_review'): ?>
                                        <?php if ($is_info_only): ?>
                                            <span class="description" style="display:block;margin-bottom:4px;color:#d97706;font-weight:500;">
                                                <?php esc_html_e('Info Only (Free/Event)', 'matchmaker'); ?>
                                            </span>
                                            <a href="<?php echo esc_url($rej_url); ?>"
                                               class="button button-small button-link-delete mm-reject-link">
                                                <?php esc_html_e('Reject', 'matchmaker'); ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="<?php echo esc_url($appr_url); ?>"
                                               class="button button-small button-primary">
                                                <?php esc_html_e('Approve', 'matchmaker'); ?>
                                            </a>
                                            <a href="<?php echo esc_url($rej_url); ?>"
                                               class="button button-small button-link-delete mm-reject-link">
                                                <?php esc_html_e('Reject', 'matchmaker'); ?>
                                            </a>
                                        <?php endif; ?>
                                    <?php elseif ($m->status === 'approved'): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color:green;"></span>
                                        <?php esc_html_e('Approved', 'matchmaker'); ?>
                                    <?php else: ?>
                                        <em><?php echo esc_html(ucfirst((string) $m->status)); ?></em>
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

    /* -------------------------------------------------------
       View: Dedicated Manual Matchmaker Page with Advanced Filters
    ------------------------------------------------------- */
    private function render_manual_match_view(int $user_id): void
    {
        global $wpdb;
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';
        $matches_table = $wpdb->prefix . 'matches';

        $pool = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $user_id),
            ARRAY_A
        );
        $user = get_userdata($user_id);

        if (!$pool || !$user) {
            echo '<p>' . esc_html__('Target user profile not found in matchmaking pool.', 'matchmaker') . '</p>';
            return;
        }

        $user_age   = !empty($pool['birth_date']) ? (new \DateTime())->diff(new \DateTime($pool['birth_date']))->y : 'N/A';
        $quota_used = (int) get_user_meta($user_id, 'cycle_matches_count', true);

        // Pre-populate filter values from GET params if submitted, else default to target user's saved preferences
        $f_gender   = sanitize_text_field(wp_unslash($_GET['f_gender']   ?? $pool['pref_gender'] ?? ''));
        $f_age_min  = isset($_GET['f_age_min'])  ? (int) $_GET['f_age_min']  : (int) ($pool['preferred_age_min'] ?? 18);
        $f_age_max  = isset($_GET['f_age_max'])  ? (int) $_GET['f_age_max']  : (int) ($pool['preferred_age_max'] ?? 80);
        $f_location = sanitize_text_field(wp_unslash($_GET['f_location'] ?? $pool['pref_location'] ?? ''));
        $f_origin   = sanitize_text_field(wp_unslash($_GET['f_origin']   ?? $pool['pref_origin'] ?? ''));
        $f_religion = sanitize_text_field(wp_unslash($_GET['f_religion'] ?? $pool['pref_religion'] ?? ''));
        $f_modesty  = sanitize_text_field(wp_unslash($_GET['f_modesty']  ?? $pool['pref_modesty'] ?? ''));

        // Query candidates excluding self & existing pairings
        $where = ['c.user_id != %d AND c.is_active = 1'];
        $args  = [$user_id];

        if (!empty($f_gender)) {
            $where[] = 'c.gender = %s';
            $args[]  = strtolower(trim($f_gender));
        }

        $where[] = 'TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE()) BETWEEN %d AND %d';
        $args[]  = $f_age_min;
        $args[]  = $f_age_max;

        if (!empty($f_location) && strtolower($f_location) !== 'any') {
            $where[] = 'FIND_IN_SET(c.location, REPLACE(%s, ", ", ",")) > 0';
            $args[]  = $f_location;
        }

        if (!empty($f_religion) && strtolower($f_religion) !== 'any') {
            $where[] = 'FIND_IN_SET(c.religion, REPLACE(%s, ", ", ",")) > 0';
            $args[]  = $f_religion;
        }

        if (!empty($f_modesty) && strtolower($f_modesty) !== 'any') {
            $where[] = 'FIND_IN_SET(c.modesty, REPLACE(%s, ", ", ",")) > 0';
            $args[]  = $f_modesty;
        }

        if (!empty($f_origin) && strtolower($f_origin) !== 'any') {
            $where[] = 'FIND_IN_SET(c.origin, REPLACE(%s, ", ", ",")) > 0';
            $args[]  = $f_origin;
        }

        // Bi-directional candidate preference check: Candidate B must accept Target User A's location, religion, modesty, and gender
        if (!empty($pool['location']) && strtolower($pool['location']) !== 'any') {
            $where[] = "(c.pref_location IS NULL OR c.pref_location = '' OR LOWER(TRIM(c.pref_location)) = 'any' OR FIND_IN_SET(%s, REPLACE(c.pref_location, ', ', ',')) > 0)";
            $args[]  = $pool['location'];
        }

        if (!empty($pool['religion']) && strtolower($pool['religion']) !== 'any') {
            $where[] = "(c.pref_religion IS NULL OR c.pref_religion = '' OR LOWER(TRIM(c.pref_religion)) = 'any' OR FIND_IN_SET(%s, REPLACE(c.pref_religion, ', ', ',')) > 0)";
            $args[]  = $pool['religion'];
        }

        if (!empty($pool['modesty']) && strtolower($pool['modesty']) !== 'any') {
            $where[] = "(c.pref_modesty IS NULL OR c.pref_modesty = '' OR LOWER(TRIM(c.pref_modesty)) = 'any' OR FIND_IN_SET(%s, REPLACE(c.pref_modesty, ', ', ',')) > 0)";
            $args[]  = $pool['modesty'];
        }

        if (!empty($pool['gender'])) {
            $where[] = "(c.pref_gender IS NULL OR c.pref_gender = '' OR LOWER(TRIM(c.pref_gender)) = %s OR LOWER(TRIM(c.pref_gender)) = 'any')";
            $args[]  = strtolower(trim($pool['gender']));
        }

        // Exclude candidates already matched with this user
        $where[] = "NOT EXISTS (
            SELECT 1 FROM {$matches_table} m
            WHERE m.user_one_id = LEAST(%d, c.user_id)
              AND m.user_two_id = GREATEST(%d, c.user_id)
        )";
        $args[] = $user_id;
        $args[] = $user_id;

        $where_sql = implode(' AND ', $where);

        $sql = "
            SELECT c.*, u.user_email, u.display_name
            FROM {$pool_table} c
            INNER JOIN {$wpdb->users} u ON c.user_id = u.ID
            WHERE {$where_sql}
            ORDER BY c.updated_at DESC
        ";

        $candidates = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) ?: [];

        $reset_url = admin_url("admin.php?page=matchmaking-pool&manual_match={$user_id}");
        ?>

        <!-- Header -->
        <div class="mm-detail-header">
            <div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $user_id)); ?>">
                    &larr; <?php esc_html_e('Back to Candidate Profile', 'matchmaker'); ?>
                </a>
                <h2>
                    <?php esc_html_e('Manual Matchmaker for:', 'matchmaker'); ?> <?php echo esc_html($user->display_name); ?>
                    <span class="mm-badge mm-badge-<?php echo esc_attr($pool['user_type']); ?>">
                        <?php echo esc_html(strtoupper(str_replace('_', ' ', $pool['user_type']))); ?>
                    </span>
                </h2>
                <p class="description">
                    <?php esc_html_e('Gender:', 'matchmaker'); ?> <?php echo esc_html(ucfirst($pool['gender'])); ?> |
                    <?php esc_html_e('Age:', 'matchmaker'); ?> <?php echo esc_html($user_age . ' yrs'); ?> |
                    <?php esc_html_e('Location:', 'matchmaker'); ?> <?php echo esc_html($pool['location']); ?> |
                    <?php esc_html_e('Quota Used:', 'matchmaker'); ?> <strong><?php echo $quota_used; ?> / 10</strong>
                </p>
            </div>
        </div>

        <!-- Advanced Filter Form -->
        <div class="mm-card" style="margin-bottom:24px;">
            <h3><?php esc_html_e('Advanced Candidate Filters', 'matchmaker'); ?></h3>
            <form method="get">
                <input type="hidden" name="page" value="matchmaking-pool">
                <input type="hidden" name="manual_match" value="<?php echo $user_id; ?>">

                <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:16px;">
                    <div style="flex:1 1 200px;">
                        <label><strong><?php esc_html_e('Candidate Gender', 'matchmaker'); ?></strong></label><br>
                        <select name="f_gender" style="width:100%;">
                            <option value="female" <?php selected(strtolower($f_gender), 'female'); ?>><?php esc_html_e('Female', 'matchmaker'); ?></option>
                            <option value="male"   <?php selected(strtolower($f_gender), 'male');   ?>><?php esc_html_e('Male', 'matchmaker'); ?></option>
                        </select>
                    </div>

                    <div style="flex:1 1 200px;">
                        <label><strong><?php esc_html_e('Age Range (Min – Max)', 'matchmaker'); ?></strong></label><br>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <input type="number" name="f_age_min" value="<?php echo esc_attr($f_age_min); ?>" style="width:70px;">
                            <span>–</span>
                            <input type="number" name="f_age_max" value="<?php echo esc_attr($f_age_max); ?>" style="width:70px;">
                        </div>
                    </div>

                    <div style="flex:1 1 200px;">
                        <label><strong><?php esc_html_e('Candidate Location', 'matchmaker'); ?></strong></label><br>
                        <input type="text" name="f_location" value="<?php echo esc_attr($f_location); ?>" placeholder="e.g. Riyadh" style="width:100%;">
                    </div>

                    <div style="flex:1 1 200px;">
                        <label><strong><?php esc_html_e('Religion', 'matchmaker'); ?></strong></label><br>
                        <input type="text" name="f_religion" value="<?php echo esc_attr($f_religion); ?>" placeholder="e.g. Islam" style="width:100%;">
                    </div>

                    <div style="flex:1 1 200px;">
                        <label><strong><?php esc_html_e('Modesty', 'matchmaker'); ?></strong></label><br>
                        <input type="text" name="f_modesty" value="<?php echo esc_attr($f_modesty); ?>" placeholder="e.g. Hijab" style="width:100%;">
                    </div>

                    <div style="flex:1 1 200px;">
                        <label><strong><?php esc_html_e('Origin / Ethnicity', 'matchmaker'); ?></strong></label><br>
                        <input type="text" name="f_origin" value="<?php echo esc_attr($f_origin); ?>" placeholder="e.g. Saudi Arabia" style="width:100%;">
                    </div>
                </div>

                <button type="submit" class="button button-primary"><?php esc_html_e('Apply Search Filters', 'matchmaker'); ?></button>
                <a href="<?php echo esc_url($reset_url); ?>" class="button-link" style="margin-left:12px;"><?php esc_html_e('Reset to Saved Preferences', 'matchmaker'); ?></a>
            </form>
        </div>

        <!-- Filtered Candidate Results Table -->
        <div class="mm-card">
            <h3>
                <?php
                printf(
                    /* translators: %d: count of available candidates */
                    esc_html__('Available Candidate Search Results (%d)', 'matchmaker'),
                    count($candidates)
                );
                ?>
            </h3>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:40px;"><?php esc_html_e('Photo', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Candidate', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Tier', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Age / Gender', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Location / Origin', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Religion / Modesty', 'matchmaker'); ?></th>
                        <th><?php esc_html_e('Flex. Score', 'matchmaker'); ?></th>
                        <th style="width:160px;"><?php esc_html_e('Action', 'matchmaker'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($candidates)): ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e('No available candidates found matching the selected filter criteria.', 'matchmaker'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($candidates as $cand):
                            $cand_id   = (int) $cand['user_id'];
                            $cand_user = get_userdata($cand_id);
                            $cand_photo= get_user_meta($cand_id, 'user_photo1', true);
                            $cand_age  = !empty($cand['birth_date']) ? (new \DateTime())->diff(new \DateTime($cand['birth_date']))->y : 'N/A';
                            $score     = Matching_Engine::instance()->compute_flexible_score($pool, $cand);

                            $create_url = wp_nonce_url(
                                admin_url("admin.php?page=matchmaking-pool&mm_action=create_manual_match&user_id={$user_id}&match_id={$cand_id}&manual_match={$user_id}"),
                                'mm_create_manual_' . $user_id . '_' . $cand_id
                            );
                        ?>
                            <tr>
                                <td>
                                    <div class="mm-avatar-thumb">
                                        <?php if (!empty($cand_photo)): ?>
                                            <img src="<?php echo esc_url($cand_photo); ?>" alt="">
                                        <?php else: ?>
                                            <span class="dashicons dashicons-admin-users"></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $cand_id)); ?>">
                                            <?php echo esc_html($cand_user ? $cand_user->display_name : 'Candidate #' . $cand_id); ?>
                                        </a>
                                    </strong><br>
                                    <span class="description"><?php echo esc_html($cand['user_email']); ?></span>
                                </td>
                                <td>
                                    <span class="mm-badge mm-badge-<?php echo esc_attr($cand['user_type']); ?>">
                                        <?php echo esc_html(strtoupper(str_replace('_', ' ', $cand['user_type']))); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($cand_age . ' yrs / ' . ucfirst($cand['gender'])); ?></td>
                                <td><?php echo esc_html($cand['location'] . ' (' . ($cand['origin'] ?: 'N/A') . ')'); ?></td>
                                <td><?php echo esc_html($cand['religion'] . ' / ' . ($cand['modesty'] ?: 'N/A')); ?></td>
                                <td><strong><?php echo $score; ?></strong> / 6 pts</td>
                                <td>
                                    <a href="<?php echo esc_url($create_url); ?>" class="button button-small button-primary">
                                        + <?php esc_html_e('Create Match', 'matchmaker'); ?>
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

    /* -------------------------------------------------------
       Router & View: All Matches Sub-Menu Page
    ------------------------------------------------------- */
    public function render_matches_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'matchmaker'));
        }

        $view_match_id = (int) ($_GET['view_match'] ?? 0);

        echo '<div class="wrap mm-admin-wrap">';
        settings_errors('mm_admin_notices');

        if ($view_match_id > 0) {
            $this->render_single_match_view($view_match_id);
        } else {
            $this->render_matches_list_view();
        }

        echo '</div>';
    }

    /* -------------------------------------------------------
       View: Minimalistic Matches List View
    ------------------------------------------------------- */
    private function render_matches_list_view(): void
    {
        global $wpdb;
        $matches_table = $wpdb->prefix . 'matches';
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';

        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        $status = sanitize_text_field(wp_unslash($_GET['status'] ?? ''));

        $where = ['1=1'];
        $args  = [];

        if (!empty($status) && $status !== 'all') {
            $where[] = 'm.status = %s';
            $args[]  = $status;
        }

        if (!empty($search)) {
            $where[] = '(u1.display_name LIKE %s OR u1.user_email LIKE %s OR u2.display_name LIKE %s OR u2.user_email LIKE %s)';
            $wc      = '%' . $wpdb->esc_like($search) . '%';
            $args[]  = $wc;
            $args[]  = $wc;
            $args[]  = $wc;
            $args[]  = $wc;
        }

        $where_sql = implode(' AND ', $where);

        $query = "
            SELECT m.*,
                u1.display_name AS u1_name, u1.user_email AS u1_email,
                u2.display_name AS u2_name, u2.user_email AS u2_email,
                p1.user_type AS u1_type, p1.gender AS u1_gender, p1.location AS u1_location, p1.birth_date AS u1_birth,
                p2.user_type AS u2_type, p2.gender AS u2_gender, p2.location AS u2_location, p2.birth_date AS u2_birth
            FROM {$matches_table} m
            LEFT JOIN {$wpdb->users} u1 ON m.user_one_id = u1.ID
            LEFT JOIN {$wpdb->users} u2 ON m.user_two_id = u2.ID
            LEFT JOIN {$pool_table} p1 ON m.user_one_id = p1.user_id
            LEFT JOIN {$pool_table} p2 ON m.user_two_id = p2.user_id
            WHERE {$where_sql}
            ORDER BY (m.status = 'pending_review') DESC, m.created_at DESC
        ";

        $results = !empty($args)
            ? $wpdb->get_results($wpdb->prepare($query, ...$args))
            : $wpdb->get_results($query);

        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e('Matchmaking History &amp; Queue', 'matchmaker'); ?></h1>
        <hr class="wp-header-end">

        <form method="get" class="mm-filter-bar">
            <input type="hidden" name="page" value="matchmaking-matches">
            <input type="search" name="s" value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php esc_attr_e('Search user or candidate name/email…', 'matchmaker'); ?>">

            <select name="status">
                <option value="all"><?php esc_html_e('All Match Statuses', 'matchmaker'); ?></option>
                <option value="pending_review" <?php selected($status, 'pending_review'); ?>><?php esc_html_e('Pending Review', 'matchmaker'); ?></option>
                <option value="approved"       <?php selected($status, 'approved');       ?>><?php esc_html_e('Approved', 'matchmaker'); ?></option>
                <option value="matched"        <?php selected($status, 'matched');        ?>><?php esc_html_e('Matched (Both Accepted)', 'matchmaker'); ?></option>
                <option value="admin_rejected" <?php selected($status, 'admin_rejected'); ?>><?php esc_html_e('Admin Rejected', 'matchmaker'); ?></option>
                <option value="rejected"       <?php selected($status, 'rejected');       ?>><?php esc_html_e('User Rejected', 'matchmaker'); ?></option>
            </select>

            <button type="submit" class="button"><?php esc_html_e('Filter Matches', 'matchmaker'); ?></button>
            <?php if (!empty($search) || (!empty($status) && $status !== 'all')): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-matches')); ?>" class="button-link">
                    <?php esc_html_e('Reset', 'matchmaker'); ?>
                </a>
            <?php endif; ?>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:45px;"><?php esc_html_e('ID', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Matched Pair (User 1 &amp; User 2)', 'matchmaker'); ?></th>
                    <th style="width:80px;"><?php esc_html_e('Score', 'matchmaker'); ?></th>
                    <th style="width:80px;"><?php esc_html_e('Source', 'matchmaker'); ?></th>
                    <th style="width:130px;"><?php esc_html_e('Status', 'matchmaker'); ?></th>
                    <th><?php esc_html_e('Participant Responses', 'matchmaker'); ?></th>
                    <th style="width:170px;"><?php esc_html_e('Admin Action', 'matchmaker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr>
                        <td colspan="7"><?php esc_html_e('No match records found matching criteria.', 'matchmaker'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($results as $row):
                        $u1_id    = (int) $row->user_one_id;
                        $u2_id    = (int) $row->user_two_id;
                        $u1_type  = $row->u1_type ?: 'free';
                        $u2_type  = $row->u2_type ?: 'free';

                        $is_info_only = in_array($u1_type, ['free', 'event'], true) || in_array($u2_type, ['free', 'event'], true);

                        $appr_url = wp_nonce_url(
                            admin_url("admin.php?page=matchmaking-matches&mm_action=approve&match_id={$row->id}"),
                            'mm_approve_' . $row->id
                        );
                        $rej_url  = wp_nonce_url(
                            admin_url("admin.php?page=matchmaking-matches&mm_action=reject&match_id={$row->id}"),
                            'mm_reject_' . $row->id
                        );
                        $view_match_url = admin_url("admin.php?page=matchmaking-matches&view_match={$row->id}");
                    ?>
                        <tr>
                            <td>#<?php echo (int) $row->id; ?></td>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $u1_id)); ?>">
                                        <?php echo esc_html($row->u1_name ?: 'User #' . $u1_id); ?>
                                    </a>
                                </strong>
                                <span class="mm-badge mm-badge-<?php echo esc_attr($u1_type); ?>">
                                    <?php echo esc_html(strtoupper(str_replace('_', ' ', $u1_type))); ?>
                                </span>
                                &amp;
                                <strong>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-pool&view_user=' . $u2_id)); ?>">
                                        <?php echo esc_html($row->u2_name ?: 'User #' . $u2_id); ?>
                                    </a>
                                </strong>
                                <span class="mm-badge mm-badge-<?php echo esc_attr($u2_type); ?>">
                                    <?php echo esc_html(strtoupper(str_replace('_', ' ', $u2_type))); ?>
                                </span>
                                <br>
                                <span class="description"><?php echo esc_html(($row->u1_location ?: 'N/A') . ' | ' . ($row->u2_location ?: 'N/A')); ?></span>
                                &nbsp;&bull;&nbsp;
                                <a href="<?php echo esc_url($view_match_url); ?>" style="font-weight:600;">
                                    <?php esc_html_e('View Full Match Details &rarr;', 'matchmaker'); ?>
                                </a>
                            </td>
                            <td><strong><?php echo (int) $row->score; ?></strong> / 6</td>
                            <td><?php echo esc_html(ucfirst((string) $row->match_source)); ?></td>
                            <td>
                                <span class="mm-status mm-status-<?php echo esc_attr($row->status); ?>">
                                    <?php echo esc_html(str_replace('_', ' ', strtoupper((string) $row->status))); ?>
                                </span>
                            </td>
                            <td>
                                <small>
                                    <strong><?php echo esc_html($row->u1_name ?: 'User #' . $u1_id); ?>:</strong> <?php echo esc_html(ucfirst((string) $row->user_one_response ?: 'pending')); ?><br>
                                    <strong><?php echo esc_html($row->u2_name ?: 'User #' . $u2_id); ?>:</strong> <?php echo esc_html(ucfirst((string) $row->user_two_response ?: 'pending')); ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($row->status === 'pending_review'): ?>
                                    <?php if ($is_info_only): ?>
                                        <span class="description" style="display:block;margin-bottom:4px;color:#d97706;font-weight:500;">
                                            <?php esc_html_e('Info Only (Free/Event)', 'matchmaker'); ?>
                                        </span>
                                        <a href="<?php echo esc_url($rej_url); ?>"
                                           class="button button-small button-link-delete mm-reject-link">
                                            <?php esc_html_e('Reject', 'matchmaker'); ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo esc_url($appr_url); ?>"
                                           class="button button-small button-primary">
                                            <?php esc_html_e('Approve', 'matchmaker'); ?>
                                        </a>
                                        <a href="<?php echo esc_url($rej_url); ?>"
                                           class="button button-small button-link-delete mm-reject-link">
                                            <?php esc_html_e('Reject', 'matchmaker'); ?>
                                        </a>
                                    <?php endif; ?>
                                <?php elseif ($row->status === 'approved'): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color:#829067;"></span>
                                    <?php esc_html_e('Approved', 'matchmaker'); ?>
                                <?php else: ?>
                                    <em><?php echo esc_html(ucfirst((string) $row->status)); ?></em>
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

    /* -------------------------------------------------------
       View: Single Match Detail View (Side-by-Side Dual Profiles)
    ------------------------------------------------------- */
    private function render_single_match_view(int $match_id): void
    {
        global $wpdb;
        $matches_table = $wpdb->prefix . 'matches';
        $pool_table    = $wpdb->prefix . 'matchmaking_pool';

        $match = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$matches_table} WHERE id = %d", $match_id)
        );

        if (!$match) {
            echo '<p>' . esc_html__('Match pairing not found.', 'matchmaker') . '</p>';
            return;
        }

        $u1_id = (int) $match->user_one_id;
        $u2_id = (int) $match->user_two_id;

        $u1 = get_userdata($u1_id);
        $u2 = get_userdata($u2_id);

        $p1 = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $u1_id), ARRAY_A);
        $p2 = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $u2_id), ARRAY_A);

        if (!$u1 || !$u2 || !$p1 || !$p2) {
            echo '<p>' . esc_html__('User profile data incomplete for this match pairing.', 'matchmaker') . '</p>';
            return;
        }

        $u1_tier      = $p1['user_type'] ?? 'free';
        $u2_tier      = $p2['user_type'] ?? 'free';
        $is_info_only = in_array($u1_tier, ['free', 'event'], true) || in_array($u2_tier, ['free', 'event'], true);

        $u1_age = !empty($p1['birth_date']) ? (new \DateTime())->diff(new \DateTime($p1['birth_date']))->y : 'N/A';
        $u2_age = !empty($p2['birth_date']) ? (new \DateTime())->diff(new \DateTime($p2['birth_date']))->y : 'N/A';

        $appr_url = wp_nonce_url(
            admin_url("admin.php?page=matchmaking-matches&mm_action=approve&match_id={$match->id}&view_match={$match->id}"),
            'mm_approve_' . $match->id
        );
        $rej_url  = wp_nonce_url(
            admin_url("admin.php?page=matchmaking-matches&mm_action=reject&match_id={$match->id}&view_match={$match->id}"),
            'mm_reject_' . $match->id
        );
        ?>

        <!-- Match Header -->
        <div class="mm-detail-header">
            <div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=matchmaking-matches')); ?>">
                    &larr; <?php esc_html_e('Back to Matches List', 'matchmaker'); ?>
                </a>
                <h2>
                    <?php esc_html_e('Match Pairing', 'matchmaker'); ?> #<?php echo (int) $match->id; ?>:
                    <?php echo esc_html($u1->display_name); ?> &amp; <?php echo esc_html($u2->display_name); ?>
                </h2>
                <p class="description">
                    <?php esc_html_e('Score:', 'matchmaker'); ?> <strong><?php echo (int) $match->score; ?> / 6 pts</strong> |
                    <?php esc_html_e('Source:', 'matchmaker'); ?> <?php echo esc_html(ucfirst((string) $match->match_source)); ?> |
                    <?php esc_html_e('Status:', 'matchmaker'); ?>
                    <span class="mm-status mm-status-<?php echo esc_attr($match->status); ?>">
                        <?php echo esc_html(str_replace('_', ' ', strtoupper((string) $match->status))); ?>
                    </span>
                </p>
            </div>
            <div>
                <?php if ($match->status === 'pending_review'): ?>
                    <?php if ($is_info_only): ?>
                        <span class="description" style="display:block;margin-bottom:6px;color:#d97706;font-weight:600;text-align:right;">
                            <?php esc_html_e('Info Only (Free/Event Tier)', 'matchmaker'); ?>
                        </span>
                        <a href="<?php echo esc_url($rej_url); ?>" class="button button-secondary mm-reject-link">
                            <?php esc_html_e('Reject Match', 'matchmaker'); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo esc_url($appr_url); ?>" class="button button-primary button-hero">
                            <?php esc_html_e('Approve Match', 'matchmaker'); ?>
                        </a>
                        <a href="<?php echo esc_url($rej_url); ?>" class="button button-secondary mm-reject-link" style="margin-left:8px;">
                            <?php esc_html_e('Reject Match', 'matchmaker'); ?>
                        </a>
                    <?php endif; ?>
                <?php elseif ($match->status === 'approved'): ?>
                    <span class="dashicons dashicons-yes-alt" style="color:#829067;font-size:24px;vertical-align:middle;"></span>
                    <strong style="color:#829067;font-size:16px;"><?php esc_html_e('Match Approved', 'matchmaker'); ?></strong>
                <?php else: ?>
                    <span class="description" style="font-size:14px;"><?php echo esc_html(str_replace('_', ' ', strtoupper((string) $match->status))); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- User Responses Card -->
        <div class="mm-card" style="margin-bottom:20px;">
            <h3><?php esc_html_e('Directional User Responses', 'matchmaker'); ?></h3>
            <p style="font-size:14px;margin:0;">
                <strong><?php echo esc_html($u1->display_name); ?>:</strong>
                <span class="mm-status mm-status-<?php echo esc_attr($match->user_one_response ?: 'pending'); ?>">
                    <?php echo esc_html(strtoupper((string) $match->user_one_response ?: 'PENDING')); ?>
                </span>
                &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                <strong><?php echo esc_html($u2->display_name); ?>:</strong>
                <span class="mm-status mm-status-<?php echo esc_attr($match->user_two_response ?: 'pending'); ?>">
                    <?php echo esc_html(strtoupper((string) $match->user_two_response ?: 'PENDING')); ?>
                </span>
            </p>
        </div>

        <!-- Side-by-Side Dual Profiles -->
        <div class="mm-grid-two">
            <!-- User 1 Card -->
            <div class="mm-card">
                <h3>
                    <?php echo esc_html($u1->display_name); ?>
                    <span class="mm-badge mm-badge-<?php echo esc_attr($u1_tier); ?>">
                        <?php echo esc_html(strtoupper(str_replace('_', ' ', $u1_tier))); ?>
                    </span>
                </h3>
                <p class="description"><?php echo esc_html($u1->user_email); ?> | <?php echo esc_html($u1_age . ' yrs | ' . ($p1['location'] ?? 'N/A')); ?></p>
                <table class="mm-kv-table">
                    <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($p1['gender'])); ?></td></tr>
                    <tr><th><?php esc_html_e('Citizenship / Origin', 'matchmaker'); ?></th><td><?php echo esc_html((get_user_meta($u1_id, 'user_citizenship', true) ?: 'N/A') . ' / ' . ($p1['origin'] ?: 'N/A')); ?></td></tr>
                    <tr><th><?php esc_html_e('Religion / Modesty', 'matchmaker'); ?></th><td><?php echo esc_html($p1['religion'] . ' / ' . $p1['modesty']); ?></td></tr>
                    <tr><th><?php esc_html_e('Height', 'matchmaker'); ?></th><td><?php echo esc_html($p1['height_cm'] ? $p1['height_cm'] . ' cm' : 'N/A'); ?></td></tr>
                    <tr><th><?php esc_html_e('Languages', 'matchmaker'); ?></th><td><?php echo esc_html($p1['languages'] ?: 'N/A'); ?></td></tr>
                    <tr><th><?php esc_html_e('Job', 'matchmaker'); ?></th><td><?php echo esc_html($p1['job'] ?: 'N/A'); ?></td></tr>
                    <tr><th><?php esc_html_e('Smoking / Drinking', 'matchmaker'); ?></th><td><?php echo esc_html(($p1['smoking'] ?: 'N/A') . ' / ' . ($p1['drinking'] ?: 'N/A')); ?></td></tr>
                    <tr><th><?php esc_html_e('Marital / Children', 'matchmaker'); ?></th><td><?php echo esc_html((get_user_meta($u1_id, 'user_marital_status', true) ?: 'N/A') . ' / ' . (get_user_meta($u1_id, 'user_children', true) ?: 'N/A')); ?></td></tr>
                </table>

                <h4><?php esc_html_e('Partner Search Preferences', 'matchmaker'); ?></h4>
                <table class="mm-kv-table">
                    <tr><th><?php esc_html_e('Pref. Gender / Age', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($p1['pref_gender']) . ' (' . $p1['preferred_age_min'] . '–' . $p1['preferred_age_max'] . ' yrs)'); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Location', 'matchmaker'); ?></th><td><?php echo esc_html($p1['pref_location'] ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Religion / Modesty', 'matchmaker'); ?></th><td><?php echo esc_html(($p1['pref_religion'] ?: 'Any') . ' / ' . ($p1['pref_modesty'] ?: 'Any')); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Height Range', 'matchmaker'); ?></th><td><?php echo esc_html(($p1['preferred_height_min'] ?: 'Min') . ' – ' . ($p1['preferred_height_max'] ?: 'Max') . ' cm'); ?></td></tr>
                </table>

                <h4><?php esc_html_e('Photos', 'matchmaker'); ?></h4>
                <div class="mm-photos-grid">
                    <?php foreach (['user_photo1', 'user_photo2', 'user_photo3'] as $pk):
                        $purl = get_user_meta($u1_id, $pk, true);
                        if (!empty($purl)): ?>
                            <a href="<?php echo esc_url($purl); ?>" target="_blank" rel="noopener">
                                <img src="<?php echo esc_url($purl); ?>" alt="">
                            </a>
                    <?php endif; endforeach; ?>
                </div>
            </div>

            <!-- User 2 Card -->
            <div class="mm-card">
                <h3>
                    <?php echo esc_html($u2->display_name); ?>
                    <span class="mm-badge mm-badge-<?php echo esc_attr($u2_tier); ?>">
                        <?php echo esc_html(strtoupper(str_replace('_', ' ', $u2_tier))); ?>
                    </span>
                </h3>
                <p class="description"><?php echo esc_html($u2->user_email); ?> | <?php echo esc_html($u2_age . ' yrs | ' . ($p2['location'] ?? 'N/A')); ?></p>
                <table class="mm-kv-table">
                    <tr><th><?php esc_html_e('Gender', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($p2['gender'])); ?></td></tr>
                    <tr><th><?php esc_html_e('Citizenship / Origin', 'matchmaker'); ?></th><td><?php echo esc_html((get_user_meta($u2_id, 'user_citizenship', true) ?: 'N/A') . ' / ' . ($p2['origin'] ?: 'N/A')); ?></td></tr>
                    <tr><th><?php esc_html_e('Religion / Modesty', 'matchmaker'); ?></th><td><?php echo esc_html($p2['religion'] . ' / ' . $p2['modesty']); ?></td></tr>
                    <tr><th><?php esc_html_e('Height', 'matchmaker'); ?></th><td><?php echo esc_html($p2['height_cm'] ? $p2['height_cm'] . ' cm' : 'N/A'); ?></td></tr>
                    <tr><th><?php esc_html_e('Languages', 'matchmaker'); ?></th><td><?php echo esc_html($p2['languages'] ?: 'N/A'); ?></td></tr>
                    <tr><th><?php esc_html_e('Job', 'matchmaker'); ?></th><td><?php echo esc_html($p2['job'] ?: 'N/A'); ?></td></tr>
                    <tr><th><?php esc_html_e('Smoking / Drinking', 'matchmaker'); ?></th><td><?php echo esc_html(($p2['smoking'] ?: 'N/A') . ' / ' . ($p2['drinking'] ?: 'N/A')); ?></td></tr>
                    <tr><th><?php esc_html_e('Marital / Children', 'matchmaker'); ?></th><td><?php echo esc_html((get_user_meta($u2_id, 'user_marital_status', true) ?: 'N/A') . ' / ' . (get_user_meta($u2_id, 'user_children', true) ?: 'N/A')); ?></td></tr>
                </table>

                <h4><?php esc_html_e('Partner Search Preferences', 'matchmaker'); ?></h4>
                <table class="mm-kv-table">
                    <tr><th><?php esc_html_e('Pref. Gender / Age', 'matchmaker'); ?></th><td><?php echo esc_html(ucfirst($p2['pref_gender']) . ' (' . $p2['preferred_age_min'] . '–' . $p2['preferred_age_max'] . ' yrs)'); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Location', 'matchmaker'); ?></th><td><?php echo esc_html($p2['pref_location'] ?: 'Any'); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Religion / Modesty', 'matchmaker'); ?></th><td><?php echo esc_html(($p2['pref_religion'] ?: 'Any') . ' / ' . ($p2['pref_modesty'] ?: 'Any')); ?></td></tr>
                    <tr><th><?php esc_html_e('Pref. Height Range', 'matchmaker'); ?></th><td><?php echo esc_html(($p2['preferred_height_min'] ?: 'Min') . ' – ' . ($p2['preferred_height_max'] ?: 'Max') . ' cm'); ?></td></tr>
                </table>

                <h4><?php esc_html_e('Photos', 'matchmaker'); ?></h4>
                <div class="mm-photos-grid">
                    <?php foreach (['user_photo1', 'user_photo2', 'user_photo3'] as $pk):
                        $purl = get_user_meta($u2_id, $pk, true);
                        if (!empty($purl)): ?>
                            <a href="<?php echo esc_url($purl); ?>" target="_blank" rel="noopener">
                                <img src="<?php echo esc_url($purl); ?>" alt="">
                            </a>
                    <?php endif; endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /* -------------------------------------------------------
       Router & View: Admin Settings Sub-Menu Page
    ------------------------------------------------------- */
    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Access denied.', 'matchmaker'));
        }

        $recurrence_days  = (int) get_option('mm_auto_match_recurrence_days', 7);
        $max_candidates   = (int) get_option('mm_max_candidates_per_run', 10);
        $default_subject  = "You Have a New Match Available on Arab Zawaj!";
        $default_template = "<p>Dear {user_name},</p>\n<p>We are excited to inform you that our matchmaking team has approved a new match for you!</p>\n<p><strong>Candidate Details:</strong></p>\n<ul>\n    <li><strong>Name:</strong> {candidate_name}</li>\n    <li><strong>Age:</strong> {candidate_age} Years Old</li>\n    <li><strong>Location:</strong> {candidate_location}</li>\n</ul>\n<p>Please log in to your dashboard to review this candidate's full profile and submit your response within 7 days.</p>\n<p><a href='{dashboard_url}' style='background-color: #CC723F; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>View Your Match Now →</a></p>\n<p>Warm regards,<br>The Arab Zawaj Matchmaking Team</p>";
        $email_subject    = (string) get_option('mm_email_approval_subject', $default_subject);
        $email_template   = (string) get_option('mm_email_approval_template', $default_template);

        $save_url         = admin_url('admin.php?page=matchmaking-settings&mm_action=save_settings');
        $nonce            = wp_create_nonce('mm_save_settings');
        ?>
        <div class="wrap mm-admin-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Matchmaking & Email Settings', 'matchmaker'); ?></h1>
            <hr class="wp-header-end">
            <?php settings_errors('mm_admin_notices'); ?>

            <div class="mm-card" style="max-width:850px;margin-top:20px;">
                <form method="post" action="<?php echo esc_url($save_url); ?>">
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">

                    <h2><?php esc_html_e('Engine & Queue Settings', 'matchmaker'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="mm_auto_match_recurrence_days">
                                        <?php esc_html_e('Auto-Match Recurrence (Days)', 'matchmaker'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="number" min="1" max="60" name="mm_auto_match_recurrence_days"
                                           id="mm_auto_match_recurrence_days" value="<?php echo esc_attr($recurrence_days); ?>" class="small-text">
                                    <p class="description">
                                        <?php esc_html_e('Number of idle days after which a monthly member will automatically be re-queued for an auto-matching job (default: 7 days).', 'matchmaker'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="mm_max_candidates_per_run">
                                        <?php esc_html_e('Max Matches per Run', 'matchmaker'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="number" min="1" max="50" name="mm_max_candidates_per_run"
                                           id="mm_max_candidates_per_run" value="<?php echo esc_attr($max_candidates); ?>" class="small-text">
                                    <p class="description">
                                        <?php esc_html_e('Maximum number of candidate match pairings created in a single auto-matching execution run (default: 10).', 'matchmaker'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <hr style="margin:30px 0;border:0;border-top:1px solid #ddd;">

                    <h2><?php esc_html_e('Match Approval Email Notification Settings', 'matchmaker'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('This email is sent automatically to both members when an admin approves a match.', 'matchmaker'); ?>
                    </p>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="mm_email_approval_subject">
                                        <?php esc_html_e('Email Subject Line', 'matchmaker'); ?>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" name="mm_email_approval_subject" id="mm_email_approval_subject"
                                           value="<?php echo esc_attr($email_subject); ?>" class="large-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="mm_email_approval_template">
                                        <?php esc_html_e('Email Body Template', 'matchmaker'); ?>
                                    </label>
                                </th>
                                <td>
                                    <?php
                                    wp_editor($email_template, 'mm_email_approval_template', [
                                        'textarea_name' => 'mm_email_approval_template',
                                        'textarea_rows' => 12,
                                        'media_buttons' => false,
                                        'teeny'         => false,
                                    ]);
                                    ?>
                                    <div style="background:#f9f9f9;border:1px solid #e5e5e5;padding:12px 16px;border-radius:6px;margin-top:12px;">
                                        <strong><?php esc_html_e('Available Placeholder Variables:', 'matchmaker'); ?></strong>
                                        <ul style="margin:6px 0 0 18px;list-style:disc;">
                                            <code>{user_name}</code> — <?php esc_html_e("Recipient member's full name", 'matchmaker'); ?><br>
                                            <code>{candidate_name}</code> — <?php esc_html_e("Matched candidate's display name", 'matchmaker'); ?><br>
                                            <code>{candidate_age}</code> — <?php esc_html_e("Matched candidate's age", 'matchmaker'); ?><br>
                                            <code>{candidate_location}</code> — <?php esc_html_e("Matched candidate's location", 'matchmaker'); ?><br>
                                            <code>{dashboard_url}</code> — <?php esc_html_e("Direct link to member match dashboard (/dashboard/)", 'matchmaker'); ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Save Matchmaking & Email Settings', 'matchmaker'); ?></button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
