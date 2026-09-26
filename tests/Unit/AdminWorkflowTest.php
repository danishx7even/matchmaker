<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use Matchmaker\Admin\AdminPortal;
use Matchmaker\Repository\MatchRepository;

class AdminWorkflowTest
{
    private AdminPortal $admin;
    private MatchRepository $repo;

    public function setUp(): void
    {
        $GLOBALS['__mm_options']   = [];
        $GLOBALS['__mm_usermeta']  = [];
        $GLOBALS['wpdb']->queries  = [];
        $GLOBALS['wpdb']->mock_rows = [];
        $GLOBALS['wpdb']->mock_vars = [];

        $this->admin = AdminPortal::instance();
        $this->repo  = MatchRepository::instance();
    }

    public function test_pool_search_query_generation(): void
    {
        global $wpdb;

        $filters = [
            'search'    => 'Sarah',
            'gender'    => 'female',
            'user_type' => 'monthly',
        ];

        $this->repo->search_pool($filters);

        $queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str, 'wp_matchmaking_pool') || !str_contains($queries_str, 'gender =') || !str_contains($queries_str, 'user_type =')) {
            throw new \RuntimeException("Expected pool search query with gender and user_type, executed:\n{$queries_str}");
        }

        // Test with has_one_on_one filter
        $wpdb->queries = [];
        $filters_vip = [
            'has_one_on_one' => '1',
        ];
        $this->repo->search_pool($filters_vip);
        $queries_str_vip = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str_vip, 'has_one_on_one = 1')) {
            throw new \RuntimeException("Expected pool search query with has_one_on_one = 1, executed:\n{$queries_str_vip}");
        }

        // Test with is_parent_applying filter
        $wpdb->queries = [];
        $filters_parent = [
            'is_parent_applying' => '1',
        ];
        $this->repo->search_pool($filters_parent);
        $queries_str_parent = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str_parent, 'is_parent_applying = 1')) {
            throw new \RuntimeException("Expected pool search query with is_parent_applying = 1, executed:\n{$queries_str_parent}");
        }
    }

    public function test_admin_settings_options_save(): void
    {
        update_option('mm_max_cycle_matches', 15);
        update_option('mm_match_expiry_days', 10);
        update_option('mm_environment_mode', 'test');

        if ($this->repo->get_max_cycle_matches() !== 15) {
            throw new \RuntimeException("Expected max cycle matches to be 15");
        }

        if ($this->repo->get_match_expiry_days() !== 10) {
            throw new \RuntimeException("Expected match expiry days to be 10");
        }

        if (!$this->repo->is_test_mode()) {
            throw new \RuntimeException("Expected environment mode to be test mode");
        }
    }

    public function test_search_matches_query_generation(): void
    {
        global $wpdb;
        $wpdb->queries = [];

        $filters = [
            'search' => 'Farhan',
            'status' => 'pending_review',
            'source' => 'auto',
        ];

        $matches = $this->repo->search_matches($filters);

        $queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str, 'wp_matches') || !str_contains($queries_str, 'm.status =') || !str_contains($queries_str, 'm.match_source =')) {
            throw new \RuntimeException("Expected matches search query with status and match_source, executed:\n{$queries_str}");
        }

        if (!is_array($matches)) {
            throw new \RuntimeException("Expected search_matches to return an array");
        }
    }

    public function test_matchmaker_admin_role_and_capabilities_registration(): void
    {
        AdminPortal::register_role_and_caps();

        $role = get_role('matchmaker_admin');
        if (!$role || !$role->has_cap('manage_matchmaker') || !$role->has_cap('read')) {
            throw new \RuntimeException("Expected matchmaker_admin role with manage_matchmaker and read capabilities");
        }

        $admin_role = get_role('administrator');
        if (!$admin_role || !$admin_role->has_cap('manage_matchmaker')) {
            throw new \RuntimeException("Expected administrator role to have manage_matchmaker capability");
        }

        $mm_user = new \FakeWP_User(801, 'Matchmaker Staff', 'staff@arabzawaj.com');
        $mm_user->roles = ['matchmaker_admin'];
        $GLOBALS['__mm_users'][801] = $mm_user;

        if (!user_can(801, 'manage_matchmaker')) {
            throw new \RuntimeException("Expected matchmaker_admin user to satisfy user_can('manage_matchmaker')");
        }

        if (user_can(801, 'manage_options')) {
            throw new \RuntimeException("Expected matchmaker_admin user to NOT have manage_options capability");
        }
    }

    public function test_restrict_admin_menus_for_matchmaker_admin_removes_unauthorized_menus(): void
    {
        AdminPortal::register_role_and_caps();

        $mm_user = new \FakeWP_User(802, 'Matchmaker Staff 2', 'staff2@arabzawaj.com');
        $mm_user->roles = ['matchmaker_admin'];
        $GLOBALS['__mm_users'][802] = $mm_user;
        $GLOBALS['__mm_current_user_id'] = 802;

        $GLOBALS['admin_removed_menus'] = [];
        $this->admin->restrict_admin_menus_for_matchmaker_admin();

        if (empty($GLOBALS['admin_removed_menus']['index.php']) || empty($GLOBALS['admin_removed_menus']['plugins.php']) || empty($GLOBALS['admin_removed_menus']['options-general.php'])) {
            throw new \RuntimeException("Expected default WP core menus (index.php, plugins.php, options-general.php) to be removed for matchmaker_admin");
        }

        unset($GLOBALS['__mm_current_user_id']);
    }

    public function test_restrict_admin_menus_skips_full_administrator(): void
    {
        AdminPortal::register_role_and_caps();

        $admin_user = new \FakeWP_User(1, 'Super Admin', 'admin@arabzawaj.com');
        $admin_user->roles = ['administrator'];
        $GLOBALS['__mm_users'][1] = $admin_user;
        $GLOBALS['__mm_current_user_id'] = 1;

        $GLOBALS['admin_removed_menus'] = [];
        $this->admin->restrict_admin_menus_for_matchmaker_admin();

        if (!empty($GLOBALS['admin_removed_menus'])) {
            throw new \RuntimeException("Expected menu removal to be skipped for full administrator");
        }

        unset($GLOBALS['__mm_current_user_id']);
    }

    public function test_events_organizer_role_and_capabilities_registration(): void
    {
        AdminPortal::register_role_and_caps();

        $role = get_role('events_organizer');
        if (!$role || !$role->has_cap('manage_events_organizer') || !$role->has_cap('edit_events') || !$role->has_cap('read')) {
            throw new \RuntimeException("Expected events_organizer role with manage_events_organizer, edit_events, and read capabilities");
        }

        $admin_role = get_role('administrator');
        if (!$admin_role || !$admin_role->has_cap('manage_events_organizer')) {
            throw new \RuntimeException("Expected administrator role to have manage_events_organizer capability");
        }

        $eo_user = new \FakeWP_User(803, 'Events Lead', 'events@arabzawaj.com');
        $eo_user->roles = ['events_organizer'];
        $GLOBALS['__mm_users'][803] = $eo_user;

        if (!user_can(803, 'manage_events_organizer')) {
            throw new \RuntimeException("Expected events_organizer user to satisfy user_can('manage_events_organizer')");
        }

        if (user_can(803, 'manage_options')) {
            throw new \RuntimeException("Expected events_organizer user to NOT have manage_options capability");
        }
    }

    public function test_restrict_admin_menus_for_events_organizer(): void
    {
        update_option('mm_events_cpt_slug', 'event');
        AdminPortal::register_role_and_caps();

        $eo_user = new \FakeWP_User(804, 'Events Staff', 'staff_events@arabzawaj.com');
        $eo_user->roles = ['events_organizer'];
        $GLOBALS['__mm_users'][804] = $eo_user;
        $GLOBALS['__mm_current_user_id'] = 804;

        // Mock global $menu with standard items including Elementor and custom items
        $GLOBALS['menu'] = [
            0 => ['Dashboard', 'read', 'index.php', '', 'menu-top'],
            1 => ['Events', 'edit_posts', 'edit.php?post_type=event', '', 'menu-top'],
            2 => ['Elementor', 'edit_posts', 'elementor', '', 'menu-top'],
            3 => ['Templates', 'edit_posts', 'edit.php?post_type=elementor_library', '', 'menu-top'],
            4 => ['Submissions', 'edit_posts', 'e-form-submissions', '', 'menu-top'],
            5 => ['ThirdParty', 'edit_posts', 'third_party_addon', '', 'menu-top'],
            6 => ['', 'read', 'separator1', '', 'wp-menu-separator'],
        ];

        $GLOBALS['admin_removed_menus'] = [];
        $this->admin->restrict_admin_menus_for_matchmaker_admin();

        if (empty($GLOBALS['admin_removed_menus']['index.php']) || empty($GLOBALS['admin_removed_menus']['plugins.php']) || empty($GLOBALS['admin_removed_menus']['options-general.php'])) {
            throw new \RuntimeException("Expected default WP core menus to be removed for events_organizer");
        }

        if (empty($GLOBALS['admin_removed_menus']['elementor']) || empty($GLOBALS['admin_removed_menus']['edit.php?post_type=elementor_library']) || empty($GLOBALS['admin_removed_menus']['e-form-submissions'])) {
            throw new \RuntimeException("Expected Elementor menus (elementor, edit.php?post_type=elementor_library, e-form-submissions) to be removed for events_organizer");
        }

        if (empty($GLOBALS['admin_removed_menus']['third_party_addon'])) {
            throw new \RuntimeException("Expected non-whitelisted third-party menus to be swept and removed for events_organizer");
        }

        if (empty($GLOBALS['admin_removed_menus']['matchmaking-pool'])) {
            throw new \RuntimeException("Expected matchmaking-pool menu to be removed for events_organizer");
        }

        if (!empty($GLOBALS['admin_removed_menus']['edit.php?post_type=event'])) {
            throw new \RuntimeException("Expected Events CPT menu to be kept for events_organizer");
        }

        unset($GLOBALS['__mm_current_user_id']);
        unset($GLOBALS['menu']);
        delete_option('mm_events_cpt_slug');
    }

    public function test_admin_handle_cancel_approved_action(): void
    {
        $admin_id = 1;
        $GLOBALS['__mm_current_user_id'] = $admin_id;

        $match_id = 77;
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matches WHERE id = 77"] = [
            'id'                => 77,
            'user_one_id'       => 501,
            'user_two_id'       => 502,
            'initiator_user_id' => 501,
            'status'            => 'approved',
            'approved_by'       => 1,
            'approved_at'       => current_time('mysql'),
            'user_one_response' => 'pending',
            'user_two_response' => 'pending',
        ];
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 501"] = [
            'user_id'   => 501,
            'user_type' => 'monthly',
        ];
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 502"] = [
            'user_id'   => 502,
            'user_type' => 'free',
        ];

        $_GET['page']      = 'matchmaking-matches';
        $_GET['mm_action'] = 'cancel_approved';
        $_GET['match_id']  = '77';
        $_GET['_wpnonce']  = wp_create_nonce('mm_cancel_approved_77');

        $this->admin->handle_admin_actions();

        $errors = \get_settings_errors('mm_admin_notices');
        if (empty($errors)) {
            throw new \RuntimeException("Expected settings error notice for cancelled_approved");
        }
        if (($errors[0]['code'] ?? '') !== 'cancelled_approved') {
            throw new \RuntimeException("Expected code 'cancelled_approved', got " . ($errors[0]['code'] ?? ''));
        }
        if (!str_contains($errors[0]['message'] ?? '', 'Match #77 approval cancelled')) {
            throw new \RuntimeException("Expected success message for Match #77, got " . ($errors[0]['message'] ?? ''));
        }

        unset($_GET['page'], $_GET['mm_action'], $_GET['match_id'], $_GET['_wpnonce'], $GLOBALS['__mm_current_user_id']);
    }

    public function test_matches_list_view_renders_unified_approve_reject_and_cancel_cta(): void
    {
        $repo = MatchRepository::instance();
        $search = '';
        $status = '';
        $source = '';

        $matches = [
            [
                'id'                => 10,
                'user_one_id'       => 601,
                'user_two_id'       => 602,
                'score'             => 5,
                'status'            => 'pending_review',
                'match_source'      => 'auto',
                'user_one_response' => 'pending',
                'user_two_response' => 'pending',
                'created_at'        => '2026-09-21 12:00:00',
            ],
            [
                'id'                => 11,
                'user_one_id'       => 601,
                'user_two_id'       => 603,
                'score'             => 6,
                'status'            => 'approved',
                'match_source'      => 'auto',
                'user_one_response' => 'pending',
                'user_two_response' => 'pending',
                'created_at'        => '2026-09-21 12:00:00',
            ],
        ];

        // User 601 is monthly, User 602 is free (previously triggered is_foe)
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 601"] = [
            'user_id'   => 601,
            'user_type' => 'monthly',
        ];
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 602"] = [
            'user_id'   => 602,
            'user_type' => 'free',
        ];

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/admin/matches/matches-list.php';
        $html = (string) ob_get_clean();

        // Must NOT contain Free/Event warning
        if (str_contains($html, '⚠️ Free/Event')) {
            throw new \RuntimeException("Expected matches list to not contain Free/Event warning badge");
        }

        // Pending match #10 must render Approve and Reject buttons
        if (!str_contains($html, 'mm_action=approve') || !str_contains($html, 'match_id=10') || !str_contains($html, 'mm_action=reject')) {
            throw new \RuntimeException("Expected pending match #10 to render Approve and Reject buttons");
        }

        // Approved match #11 must render View and Cancel buttons
        if (!str_contains($html, 'view_match=11') || !str_contains($html, 'mm_action=cancel_approved') || !str_contains($html, 'mm-cancel-approval-link')) {
            throw new \RuntimeException("Expected approved match #11 to render View and Cancel buttons");
        }
    }

    public function test_pool_list_view_renders_quota_column(): void
    {
        $repo = MatchRepository::instance();
        $search = '';
        $gender = '';
        $tier = '';

        $u_monthly = 901;
        $u_free    = 902;

        update_user_meta($u_monthly, 'mm_cycle_month', gmdate('Y-m'));
        update_user_meta($u_monthly, 'cycle_matches_count', 4);

        $candidates = [
            [
                'user_id'          => $u_monthly,
                'birth_date'       => '1992-04-12',
                'gender'           => 'female',
                'user_type'        => 'monthly',
                'city'             => 'Dubai',
                'country'          => 'UAE',
                'approved_matches' => 2,
                'pending_matches'  => 1,
            ],
            [
                'user_id'          => $u_free,
                'birth_date'       => '1995-08-20',
                'gender'           => 'male',
                'user_type'        => 'free',
                'city'             => 'Riyadh',
                'country'          => 'Saudi Arabia',
                'approved_matches' => 0,
                'pending_matches'  => 0,
            ],
        ];

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/admin/pool/pool-list.php';
        $html = (string) ob_get_clean();

        // Must contain Quota column header
        if (!str_contains($html, '>Quota<')) {
            throw new \RuntimeException("Expected pool-list.php to contain 'Quota' column header");
        }

        // Monthly user 901 must show '4 / 10'
        if (!str_contains($html, '4 / 10')) {
            throw new \RuntimeException("Expected pool-list.php to show '4 / 10' for monthly user");
        }

        // Free user 902 must show '—'
        if (!str_contains($html, '—')) {
            throw new \RuntimeException("Expected pool-list.php to show '—' for free user");
        }
    }

    public function test_has_active_approved_match_auto_expires_overdue_records(): void
    {
        global $wpdb;
        $repo = MatchRepository::instance();

        $u1 = 920;
        $u2 = 921;

        // Mock overdue query to return match #9920
        $expiry_days = $repo->get_match_expiry_days();
        $stale_query = $wpdb->prepare(
            "SELECT id FROM wp_matches
                 WHERE (user_one_id = %d OR user_two_id = %d)
                   AND status = 'approved'
                   AND COALESCE(approved_at, created_at, updated_at) < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $u1,
            $u1,
            $expiry_days
        );

        $wpdb->mock_results[$stale_query] = [
            ['id' => 9920],
        ];

        // Mock remaining active count as 0
        $active_query = $wpdb->prepare(
            "SELECT COUNT(*) FROM wp_matches
                     WHERE (user_one_id = %d OR user_two_id = %d)
                       AND status = 'approved'",
            $u1,
            $u1
        );
        $wpdb->mock_vars[$active_query] = 0;

        // has_active_approved_match() should self-heal and expire this overdue match, returning false
        $has_active = $repo->has_active_approved_match($u1);
        if ($has_active !== false) {
            throw new \RuntimeException("Expected has_active_approved_match() to return false after auto-expiring overdue match");
        }

        // Check that expire_match was triggered and query was logged
        $queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str, 'UPDATE wp_matches') && !str_contains($queries_str, 'status = \'expired\'')) {
            throw new \RuntimeException("Expected expire_match query to execute during self-healing");
        }
    }

    public function test_get_active_approved_match_info_returns_blockage_details(): void
    {
        global $wpdb;
        $repo = MatchRepository::instance();

        $u1 = 930;
        $u2 = 931;

        $GLOBALS['__mm_users'][$u2] = new \FakeWP_User($u2, 'Amina Candidate', 'amina@test.com');

        $query = $wpdb->prepare(
            "SELECT * FROM wp_matches
                     WHERE (user_one_id = %d OR user_two_id = %d)
                       AND status = 'approved'
                     ORDER BY created_at DESC LIMIT 1",
            $u1,
            $u1
        );

        $wpdb->mock_rows[$query] = [
            'id'                 => 9930,
            'user_one_id'        => $u1,
            'user_two_id'        => $u2,
            'initiator_user_id'  => $u1,
            'score'              => 6,
            'status'             => 'approved',
            'user_one_response'  => 'pending',
            'user_two_response'  => 'pending',
            'approved_at'        => gmdate('Y-m-d H:i:s', time() - (2 * 86400)),
            'created_at'         => gmdate('Y-m-d H:i:s', time() - (2 * 86400)),
        ];

        $info = $repo->get_active_approved_match_info($u1);
        if (!$info || (int) $info['match_id'] !== 9930) {
            throw new \RuntimeException("Expected active match info with match_id 9930");
        }

        if ((int) $info['partner_id'] !== $u2 || $info['partner_name'] !== 'Amina Candidate') {
            throw new \RuntimeException("Expected active match info partner details to match Amina Candidate (#{$u2})");
        }

        if ($info['days_remaining'] < 1) {
            throw new \RuntimeException("Expected days_remaining > 0 for match approved 2 days ago");
        }
    }

    public function test_process_admin_approve_blockage_message_formatting(): void
    {
        global $wpdb;
        $service = \Matchmaker\Service\MatchService::instance();
        $repo    = MatchRepository::instance();

        $u1 = 940;
        $u2 = 941;
        $u3 = 942;

        $GLOBALS['__mm_users'][$u1] = new \FakeWP_User($u1, 'Tariq Member', 'tariq@test.com');
        $GLOBALS['__mm_users'][$u2] = new \FakeWP_User($u2, 'Fatima Active', 'fatima@test.com');
        $GLOBALS['__mm_users'][$u3] = new \FakeWP_User($u3, 'Sara Candidate', 'sara@test.com');

        // Match #9941 to be approved
        $wpdb->mock_rows["SELECT * FROM wp_matches WHERE id = 9941"] = [
            'id'                => 9941,
            'user_one_id'       => $u1,
            'user_two_id'       => $u3,
            'initiator_user_id' => $u1,
            'score'             => 4,
            'status'            => 'pending_review',
        ];

        $wpdb->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = {$u1}"] = [
            'user_id'   => $u1,
            'user_type' => 'monthly',
        ];
        $wpdb->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = {$u3}"] = [
            'user_id'   => $u3,
            'user_type' => 'monthly',
        ];

        // Active match query for u1 excluding match 9941
        $active_cnt_query = $wpdb->prepare(
            "SELECT COUNT(*) FROM wp_matches
                     WHERE (user_one_id = %d OR user_two_id = %d)
                       AND status = 'approved'
                       AND id != %d",
            $u1,
            $u1,
            9941
        );
        $wpdb->mock_vars[$active_cnt_query] = 1;

        $active_row_query = $wpdb->prepare(
            "SELECT * FROM wp_matches
                     WHERE (user_one_id = %d OR user_two_id = %d)
                       AND status = 'approved'
                       AND id != %d
                     ORDER BY created_at DESC LIMIT 1",
            $u1,
            $u1,
            9941
        );
        $wpdb->mock_rows[$active_row_query] = [
            'id'                 => 9940,
            'user_one_id'        => $u1,
            'user_two_id'        => $u2,
            'initiator_user_id'  => $u1,
            'score'              => 5,
            'status'             => 'approved',
            'user_one_response'  => 'pending',
            'user_two_response'  => 'pending',
            'approved_at'        => gmdate('Y-m-d H:i:s', time() - (1 * 86400)),
            'created_at'         => gmdate('Y-m-d H:i:s', time() - (1 * 86400)),
        ];

        // Try approving match #9941 while u1 already has active match #9940
        $result = $service->process_admin_approve(9941, 1);

        if ($result['success'] !== false) {
            throw new \RuntimeException("Expected process_admin_approve to fail due to active match blockage");
        }

        // Message should contain specific details: Tariq Member, match #9940, Fatima Active
        $msg = $result['message'] ?? '';
        if (!str_contains($msg, 'Tariq Member') || !str_contains($msg, '#9940') || !str_contains($msg, 'Fatima Active')) {
            throw new \RuntimeException("Expected blockage message to contain Tariq Member, Match #9940, and Fatima Active. Got:\n{$msg}");
        }
    }

    public function test_single_user_match_history_renders_all_statuses(): void
    {
        $repo = MatchRepository::instance();

        $user_id = 950;
        $u_cand1 = 951;
        $u_cand2 = 952;
        $u_cand3 = 953;

        $user_obj = new \FakeWP_User($user_id, 'Karim Test', 'karim@test.com');
        $GLOBALS['__mm_users'][$user_id] = $user_obj;
        $GLOBALS['__mm_users'][$u_cand1] = new \FakeWP_User($u_cand1, 'Zainab Accepted', 'zainab@test.com');
        $GLOBALS['__mm_users'][$u_cand2] = new \FakeWP_User($u_cand2, 'Laila Rejected', 'laila@test.com');
        $GLOBALS['__mm_users'][$u_cand3] = new \FakeWP_User($u_cand3, 'Noor Expired', 'noor@test.com');

        $pool = [
            'user_id'    => $user_id,
            'birth_date' => '1990-01-01',
            'gender'     => 'male',
            'user_type'  => 'monthly',
        ];
        $meta = [];
        $age = '34';
        $height = "5'10\"";
        $quota_used = 2;
        $has_mutual = false;
        $back_url = '#';
        $manual_url = '#';
        $trigger_url = '#';

        $matches = [
            [
                'id'                 => 101,
                'user_one_id'        => $user_id,
                'user_two_id'        => $u_cand1,
                'score'              => 5,
                'status'             => 'matched',
                'match_source'       => 'auto',
                'user_one_response'  => 'accepted',
                'user_two_response'  => 'accepted',
                'created_at'         => '2026-09-01 10:00:00',
            ],
            [
                'id'                 => 102,
                'user_one_id'        => $user_id,
                'user_two_id'        => $u_cand2,
                'score'              => 4,
                'status'             => 'rejected',
                'match_source'       => 'manual',
                'user_one_response'  => 'rejected',
                'user_two_response'  => 'pending',
                'created_at'         => '2026-09-05 11:00:00',
            ],
            [
                'id'                 => 103,
                'user_one_id'        => $user_id,
                'user_two_id'        => $u_cand3,
                'score'              => 3,
                'status'             => 'expired',
                'match_source'       => 'auto',
                'user_one_response'  => 'pending',
                'user_two_response'  => 'pending',
                'created_at'         => '2026-08-10 12:00:00',
            ],
        ];

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/admin/pool/user-single.php';
        $html = (string) ob_get_clean();

        // Check for total matches recorded
        if (!str_contains($html, '3 Total Matches Recorded')) {
            throw new \RuntimeException("Expected user-single.php to render '3 Total Matches Recorded'");
        }

        // Check for all 3 match rows
        if (!str_contains($html, '#101') || !str_contains($html, 'Mutual Match')) {
            throw new \RuntimeException("Expected user-single.php to display Mutual Match #101");
        }
        if (!str_contains($html, '#102') || !str_contains($html, 'Rejected')) {
            throw new \RuntimeException("Expected user-single.php to display Rejected Match #102");
        }
        if (!str_contains($html, '#103') || !str_contains($html, 'Expired')) {
            throw new \RuntimeException("Expected user-single.php to display Expired Match #103");
        }
    }
}


