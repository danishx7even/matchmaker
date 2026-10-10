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

        // 1. Text Search (Name / Email)
        $filters = [
            'search' => 'Farhan',
            'status' => 'pending_review',
            'source' => 'auto',
        ];

        $matches = $this->repo->search_matches($filters);

        $queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str, 'wp_matches') || !str_contains($queries_str, 'm.status =') || !str_contains($queries_str, 'm.match_source =') || !str_contains($queries_str, 'u1.display_name LIKE')) {
            throw new \RuntimeException("Expected matches search query with status, match_source, and display_name LIKE, executed:\n{$queries_str}");
        }

        if (!is_array($matches)) {
            throw new \RuntimeException("Expected search_matches to return an array");
        }

        // 2. Numeric Match ID Search (e.g. '42', '#42', 'match #42')
        $wpdb->queries = [];
        $this->repo->search_matches(['search' => '42']);
        $q_num = implode("\n", $wpdb->queries);
        if (!str_contains($q_num, 'm.id = 42')) {
            throw new \RuntimeException("Expected search_matches('42') to query 'm.id = 42', got:\n{$q_num}");
        }

        $wpdb->queries = [];
        $this->repo->search_matches(['search' => '#42']);
        $q_hash = implode("\n", $wpdb->queries);
        if (!str_contains($q_hash, 'm.id = 42')) {
            throw new \RuntimeException("Expected search_matches('#42') to query 'm.id = 42', got:\n{$q_hash}");
        }

        $wpdb->queries = [];
        $this->repo->search_matches(['search' => 'match #42']);
        $q_match_hash = implode("\n", $wpdb->queries);
        if (!str_contains($q_match_hash, 'm.id = 42')) {
            throw new \RuntimeException("Expected search_matches('match #42') to query 'm.id = 42', got:\n{$q_match_hash}");
        }

        $wpdb->queries = [];
        $this->repo->search_matches_count(['search' => '#42']);
        $q_count = implode("\n", $wpdb->queries);
        if (!str_contains($q_count, 'm.id = 42')) {
            throw new \RuntimeException("Expected search_matches_count('#42') to query 'm.id = 42', got:\n{$q_count}");
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

    public function test_admin_handle_reset_pending_action(): void
    {
        $GLOBALS['__mm_settings_errors'] = [];
        $admin_id = 1;
        $GLOBALS['__mm_current_user_id'] = $admin_id;

        $match_id = 88;
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matches WHERE id = 88"] = [
            'id'                        => 88,
            'user_one_id'               => 501,
            'user_two_id'               => 502,
            'initiator_user_id'         => 501,
            'status'                    => 'rejected',
            'approved_by'               => 1,
            'approved_at'               => current_time('mysql'),
            'user_one_response'         => 'rejected',
            'user_two_response'         => 'pending',
            'user_one_rejection_reason' => 'Too far distance',
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
        $_GET['mm_action'] = 'reset_pending';
        $_GET['match_id']  = '88';
        $_GET['_wpnonce']  = wp_create_nonce('mm_reset_pending_88');

        $this->admin->handle_admin_actions();

        $errors = \get_settings_errors('mm_admin_notices');
        if (empty($errors)) {
            throw new \RuntimeException("Expected settings error notice for reset_pending_success");
        }
        if (($errors[0]['code'] ?? '') !== 'reset_pending_success') {
            throw new \RuntimeException("Expected code 'reset_pending_success', got " . ($errors[0]['code'] ?? ''));
        }
        if (!str_contains($errors[0]['message'] ?? '', 'Match #88 has been reset to pending review')) {
            throw new \RuntimeException("Expected success message for Match #88, got " . ($errors[0]['message'] ?? ''));
        }

        unset($_GET['page'], $_GET['mm_action'], $_GET['match_id'], $_GET['_wpnonce'], $GLOBALS['__mm_current_user_id']);
    }

    public function test_reset_match_to_pending_in_repository(): void
    {
        $repo = MatchRepository::instance();

        // 1. Rejected match
        $match_id = 89;
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matches WHERE id = 89"] = [
            'id'                        => 89,
            'user_one_id'               => 501,
            'user_two_id'               => 502,
            'initiator_user_id'         => 501,
            'status'                    => 'admin_rejected',
            'approved_by'               => null,
            'approved_at'               => null,
            'user_one_response'         => 'pending',
            'user_two_response'         => 'pending',
            'user_one_rejection_reason' => null,
            'user_two_rejection_reason' => null,
        ];
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 501"] = [
            'user_id'   => 501,
            'user_type' => 'monthly',
        ];
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 502"] = [
            'user_id'   => 502,
            'user_type' => 'free',
        ];

        $res = $repo->reset_match_to_pending(89, 1);
        if (!$res['success']) {
            throw new \RuntimeException("Expected reset_match_to_pending to succeed for admin_rejected match");
        }

        // 2. Non-reversible match (e.g. pending_review or matched) should fail
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matches WHERE id = 90"] = [
            'id'          => 90,
            'user_one_id' => 501,
            'user_two_id' => 502,
            'status'      => 'pending_review',
        ];
        $res_invalid = $repo->reset_match_to_pending(90, 1);
        if ($res_invalid['success']) {
            throw new \RuntimeException("Expected reset_match_to_pending to fail for pending_review match");
        }
    }

    public function test_matches_list_view_renders_view_comparison_cta(): void
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
            [
                'id'                => 12,
                'user_one_id'       => 601,
                'user_two_id'       => 604,
                'score'             => 4,
                'status'            => 'rejected',
                'match_source'      => 'auto',
                'user_one_response' => 'rejected',
                'user_two_response' => 'pending',
                'created_at'        => '2026-09-21 12:00:00',
            ],
        ];

        // User 601 is monthly, User 602 is free
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

        // Must render View Comparison for all matches
        if (!str_contains($html, 'view_match=10') || !str_contains($html, 'view_match=11') || !str_contains($html, 'view_match=12')) {
            throw new \RuntimeException("Expected all matches to render View Comparison links");
        }
        if (!str_contains($html, 'View Comparison')) {
            throw new \RuntimeException("Expected matches list to render View Comparison button label");
        }
        if (!str_contains($html, 'mm_action=approve&amp;match_id=10') && !str_contains($html, 'mm_action=approve&match_id=10')) {
            throw new \RuntimeException("Expected pending match #10 to render Approve CTA");
        }
        if (!str_contains($html, 'mm_action=reject&amp;match_id=10') && !str_contains($html, 'mm_action=reject&match_id=10')) {
            throw new \RuntimeException("Expected pending match #10 to render Reject CTA");
        }
    }

    public function test_single_match_view_renders_appropriate_ctas(): void
    {
        $repo = MatchRepository::instance();

        // 1. Pending Match Review
        $match_id = 10;
        $match = [
            'id'                => 10,
            'user_one_id'       => 601,
            'user_two_id'       => 602,
            'score'             => 5,
            'status'            => 'pending_review',
            'match_source'      => 'auto',
            'user_one_response' => 'pending',
            'user_two_response' => 'pending',
        ];
        $u1_id = 601;
        $u2_id = 602;
        $u1 = get_userdata(601);
        $u2 = get_userdata(602);
        $p1 = ['user_id' => 601, 'gender' => 'male'];
        $p2 = ['user_id' => 602, 'gender' => 'female'];
        $m1 = [];
        $m2 = [];
        $back_url = 'admin.php?page=matchmaking-matches';
        $approve_url = 'admin.php?page=matchmaking-matches&mm_action=approve&match_id=10';
        $reject_url = 'admin.php?page=matchmaking-matches&mm_action=reject&match_id=10';
        $cancel_url = 'admin.php?page=matchmaking-matches&mm_action=cancel_approved&match_id=10';
        $reset_url = 'admin.php?page=matchmaking-matches&mm_action=reset_pending&match_id=10';
        $st = 'pending_review';

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/admin/matches/match-single.php';
        $html_pending = (string) ob_get_clean();

        if (!str_contains($html_pending, 'Approve Match') || !str_contains($html_pending, 'Reject Match')) {
            throw new \RuntimeException("Expected pending match to render Approve Match and Reject Match buttons");
        }

        // 2. Rejected Match Review
        $st = 'rejected';
        $match['status'] = 'rejected';
        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/admin/matches/match-single.php';
        $html_rejected = (string) ob_get_clean();

        if (!str_contains($html_rejected, 'Reset to Pending') || !str_contains($html_rejected, 'mm_action=reset_pending')) {
            throw new \RuntimeException("Expected rejected match to render Reset to Pending button");
        }

        // 3. Approved Match Review
        $st = 'approved';
        $match['status'] = 'approved';
        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/admin/matches/match-single.php';
        $html_approved = (string) ob_get_clean();

        if (!str_contains($html_approved, 'Cancel Approval') || !str_contains($html_approved, 'mm_action=cancel_approved')) {
            throw new \RuntimeException("Expected approved match to render Cancel Approval button");
        }

        // 4. Verify Dual Full Profiles and Criteria Breakdown Table Rendering
        $breakdown = [
            'criteria' => [
                [
                    'id'            => 'religion',
                    'label'         => 'Religion / Sect',
                    'category'      => 'gate',
                    'target_val'    => 'Muslim (Prefers: Sunni)',
                    'candidate_val' => 'Muslim (Prefers: Any)',
                    'is_match'      => true,
                    'note'          => 'Religious preferences compatible',
                ],
                [
                    'id'            => 'origin',
                    'label'         => 'Origin / Ethnicity',
                    'category'      => 'score',
                    'target_val'    => 'Arab (Prefers: Arab)',
                    'candidate_val' => 'Arab (Prefers: Arab)',
                    'is_match'      => true,
                    'note'          => '+1 pt: Origin preferences match',
                ],
            ],
            'flexible_score'          => 5,
            'matching_criteria_count' => 2,
            'total_criteria_count'    => 2,
        ];

        $m1 = [
            'user_photo1'          => 'https://example.com/u1_photo1.jpg',
            'user_about_me'        => 'I am an adventurous engineer.',
            'pref_additional_info' => 'Looking for a kind partner.',
            'user_citizenship'     => 'United Kingdom',
            'pref_citizenship'     => 'United Kingdom',
        ];
        $m2 = [
            'user_photo1'          => 'https://example.com/u2_photo1.jpg',
            'user_about_me'        => 'Passionate teacher and traveler.',
            'pref_additional_info' => 'Looking for a pious companion.',
            'user_citizenship'     => 'United Kingdom',
            'pref_citizenship'     => 'United Kingdom',
        ];

        $st = 'pending_review';
        $match['status'] = 'pending_review';

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/admin/matches/match-single.php';
        $html_full = (string) ob_get_clean();

        if (!str_contains($html_full, 'Self Profile') || !str_contains($html_full, 'Partner Preferences')) {
            throw new \RuntimeException("Expected match-single.php to render Self Profile and Partner Preferences sections for both candidates");
        }
        if (!str_contains($html_full, 'About Myself:') || !str_contains($html_full, 'About My Perfect Match:')) {
            throw new \RuntimeException("Expected match-single.php to render About Myself and About My Perfect Match cards");
        }
        if (!str_contains($html_full, 'mm-breakdown-table') || !str_contains($html_full, 'Side-by-Side Compatibility Criteria Comparison')) {
            throw new \RuntimeException("Expected match-single.php to render the Side-by-Side Compatibility Breakdown Table");
        }
        if (!str_contains($html_full, 'mm-pill-match') || !str_contains($html_full, 'Religion / Sect')) {
            throw new \RuntimeException("Expected match-single.php breakdown table to render criteria labels and match pills");
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
        $meta = [
            'user_photo1' => 'https://example.com/p1.jpg',
        ];
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

        // Check lightbox trigger on photo gallery
        if (!str_contains($html, 'data-mm-lightbox="profile-gallery"')) {
            throw new \RuntimeException("Expected user-single.php profile photos to have data-mm-lightbox attribute");
        }
        if (!str_contains($html, 'class="mm-lightbox-trigger"')) {
            throw new \RuntimeException("Expected user-single.php profile photos to have mm-lightbox-trigger class");
        }
    }

    public function test_pool_browser_table_does_not_contain_lightbox_triggers(): void
    {
        $pool_users = [
            (object) [
                'user_id'             => 201,
                'display_name'        => 'Test Candidate',
                'user_email'          => 'candidate@example.com',
                'gender'              => 'female',
                'birth_date'          => '1995-05-15',
                'location'            => 'Dubai, UAE',
                'city'                => 'Dubai',
                'state'               => 'Dubai',
                'country'             => 'AE',
                'origin'              => 'Emirati',
                'religion'            => 'Muslim',
                'modesty'             => 'Hijab',
                'user_photo1'         => 'https://example.com/photo1.jpg',
                'status'              => 'active',
                'created_at'          => '2026-09-01 10:00:00',
                'cycle_matches_count' => 2,
            ]
        ];
        $total_users = 1;
        $page = 1;
        $per_page = 20;
        $total_pages = 1;
        $filters = [];

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/admin/pool/pool-list.php';
        $html = (string) ob_get_clean();

        if (str_contains($html, 'data-mm-lightbox')) {
            throw new \RuntimeException("Expected pool-list.php table thumbnails NOT to contain data-mm-lightbox");
        }
    }

    public function test_admin_notes_get_and_save_in_repository(): void
    {
        $user_id = 301;
        $test_notes = '<p>Candidate is <strong>highly recommended</strong>. Prefers relocation to UAE.</p>';

        $saved = $this->repo->save_admin_notes($user_id, $test_notes);
        if (!$saved) {
            throw new \RuntimeException("Expected save_admin_notes to return true");
        }

        $retrieved = $this->repo->get_admin_notes($user_id);
        if ($retrieved !== $test_notes) {
            throw new \RuntimeException("Expected get_admin_notes to return saved HTML content. Got: {$retrieved}");
        }

        // Test empty / unassigned notes
        $empty_notes = $this->repo->get_admin_notes(9999);
        if ($empty_notes !== '') {
            throw new \RuntimeException("Expected empty string for user without notes. Got: {$empty_notes}");
        }
    }

    public function test_get_subscription_start_date(): void
    {
        $user_id = 302;
        $user = new \FakeWP_User($user_id, 'Start Date Candidate', 'startdate@example.com');
        $user->user_registered = '2026-06-26 14:00:00';
        $GLOBALS['__mm_users'][$user_id] = $user;

        $date_joined = $this->repo->get_subscription_start_date($user_id);
        if ($date_joined !== '06/26/2026') {
            throw new \RuntimeException("Expected get_subscription_start_date fallback to format m/d/Y '06/26/2026'. Got: {$date_joined}");
        }
    }

    public function test_pool_list_view_renders_notes_cta_and_modal(): void
    {
        $candidates = [
            [
                'user_id'             => 202,
                'display_name'        => 'Notes Candidate',
                'user_email'          => 'notes_cand@example.com',
                'gender'              => 'female',
                'birth_date'          => '1995-05-15',
                'location'            => 'Dubai, UAE',
                'city'                => 'Dubai',
                'state'               => 'Dubai',
                'country'             => 'AE',
                'origin'              => 'Emirati',
                'religion'            => 'Muslim',
                'modesty'             => 'Hijab',
                'user_photo1'         => 'https://example.com/photo1.jpg',
                'status'              => 'active',
                'created_at'          => '2026-09-01 10:00:00',
                'cycle_matches_count' => 2,
            ]
        ];
        $total_users = 1;
        $page = 1;
        $per_page = 20;
        $total_pages = 1;
        $filters = [];

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/admin/pool/pool-list.php';
        $html = (string) ob_get_clean();

        if (!str_contains($html, 'mm-open-notes-btn')) {
            throw new \RuntimeException("Expected pool-list.php to render mm-open-notes-btn CTA");
        }
        if (!str_contains($html, 'Notes')) {
            throw new \RuntimeException("Expected pool-list.php to render 'Notes' button text");
        }
        if (!str_contains($html, 'id="mm-admin-notes-modal"')) {
            throw new \RuntimeException("Expected pool-list.php to render #mm-admin-notes-modal popup container");
        }
    }

    public function test_user_single_view_renders_date_joined_and_notes_sidebar(): void
    {
        $user_id = 203;
        $user_obj = new \FakeWP_User($user_id, 'Ruoa Hafid', 'ruoahafid@gmail.com');
        $user_obj->user_registered = '2026-06-26 10:00:00';
        $GLOBALS['__mm_users'][$user_id] = $user_obj;

        $pool = [
            'user_id'    => $user_id,
            'gender'     => 'female',
            'user_type'  => 'monthly',
            'birth_date' => '1995-01-01',
            'height_cm'  => 165,
            'city'       => 'Dubai',
            'state'      => 'Dubai',
            'country'    => 'AE',
        ];
        $meta = [
            'phone_number' => '9297968652',
            'user_photo1'  => 'https://example.com/photo.jpg',
        ];
        $matches = [];
        $age = '30';
        $height = "5' 5\"";
        $quota_used = 1;
        $has_mutual = false;
        $back_url = '#';
        $manual_url = '#';
        $trigger_url = '#';
        $subscription_start_date = '06/26/2026';
        $admin_notes = '<p>Candidate requested preferred match in Dubai.</p>';

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/admin/pool/user-single.php';
        $html = (string) ob_get_clean();

        if (!str_contains($html, 'Date joined:')) {
            throw new \RuntimeException("Expected user-single.php to render 'Date joined:' label in header");
        }
        if (!str_contains($html, '06/26/2026')) {
            throw new \RuntimeException("Expected user-single.php to render '06/26/2026' in header");
        }
        if (!str_contains($html, 'mm-admin-notes-sidebar-card')) {
            throw new \RuntimeException("Expected user-single.php to render mm-admin-notes-sidebar-card in sidebar");
        }
        if (!str_contains($html, 'mm-save-notes-btn')) {
            throw new \RuntimeException("Expected user-single.php to render mm-save-notes-btn");
        }
    }

    public function test_ajax_get_and_save_admin_notes(): void
    {
        $user_id = 303;
        $user_obj = new \FakeWP_User($user_id, 'Ajax Candidate', 'ajax@example.com');
        $GLOBALS['__mm_users'][$user_id] = $user_obj;
        $GLOBALS['__mm_current_user_id'] = 1;
        $GLOBALS['__mm_is_admin'] = true;

        // Save notes via AJAX
        $_POST['nonce'] = wp_create_nonce('mm_admin_nonce');
        $_POST['user_id'] = $user_id;
        $_POST['notes'] = '<p>Saved via <em>AJAX</em> handler.</p>';

        try {
            $this->admin->ajax_save_admin_notes();
        } catch (\RuntimeException $e) {
            // Success response throws wp_send_json_success RuntimeException in mock environment
        }

        $saved_in_repo = $this->repo->get_admin_notes($user_id);
        if (!str_contains($saved_in_repo, 'Saved via <em>AJAX</em> handler.')) {
            throw new \RuntimeException("Expected ajax_save_admin_notes to save formatted notes to repo. Got: {$saved_in_repo}");
        }

        // Retrieve notes via AJAX
        try {
            $this->admin->ajax_get_admin_notes();
        } catch (\RuntimeException $e) {
            // Success response
        }
    }

    public function test_search_pool_count_and_pagination(): void
    {
        global $wpdb;
        $wpdb->queries = [];

        $filters = [
            'search'    => 'Fatima',
            'gender'    => 'female',
            'user_type' => 'monthly',
        ];

        $this->repo->search_pool($filters, 20, 40);
        $queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str, 'LIMIT 20 OFFSET 40')) {
            throw new \RuntimeException("Expected search_pool with limit and offset to contain 'LIMIT 20 OFFSET 40', got: {$queries_str}");
        }

        $wpdb->queries = [];
        $this->repo->search_pool_count($filters);
        $count_queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($count_queries_str, 'SELECT COUNT(*)') || !str_contains($count_queries_str, 'wp_matchmaking_pool')) {
            throw new \RuntimeException("Expected search_pool_count to execute SELECT COUNT(*), got: {$count_queries_str}");
        }
    }

    public function test_search_matches_count_and_pagination(): void
    {
        global $wpdb;
        $wpdb->queries = [];

        $filters = [
            'status' => 'pending_review',
            'source' => 'auto',
        ];

        $this->repo->search_matches($filters, 25, 50);
        $queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str, 'LIMIT 25 OFFSET 50')) {
            throw new \RuntimeException("Expected search_matches with limit and offset to contain 'LIMIT 25 OFFSET 50', got: {$queries_str}");
        }

        $wpdb->queries = [];
        $this->repo->search_matches_count($filters);
        $count_queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($count_queries_str, 'SELECT COUNT(*)') || !str_contains($count_queries_str, 'wp_matches')) {
            throw new \RuntimeException("Expected search_matches_count to execute SELECT COUNT(*), got: {$count_queries_str}");
        }
    }

    public function test_pool_list_view_renders_textarea_and_pagination(): void
    {
        $candidates = [
            [
                'user_id'             => 101,
                'gender'              => 'male',
                'user_type'           => 'monthly',
                'birth_date'          => '1990-01-01',
                'country'             => 'AE',
                'created_at'          => '2026-09-01 10:00:00',
                'cycle_matches_count' => 1,
            ]
        ];
        $total_candidates = 50;
        $current_page     = 2;
        $per_page         = 20;
        $total_pages      = 3;
        $search           = '';
        $gender           = '';
        $tier             = '';

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/admin/pool/pool-list.php';
        $html = (string) ob_get_clean();

        if (!str_contains($html, 'id="mm-modal-admin-notes-textarea"')) {
            throw new \RuntimeException("Expected pool-list.php to render textarea with id mm-modal-admin-notes-textarea");
        }
        if (!str_contains($html, 'tablenav-pages')) {
            throw new \RuntimeException("Expected pool-list.php to render tablenav-pages pagination container");
        }
        if (!str_contains($html, '50 candidates')) {
            throw new \RuntimeException("Expected pool-list.php to render '50 candidates' displaying-num text");
        }
    }

    public function test_matches_list_view_renders_pagination(): void
    {
        $matches = [
            [
                'id'            => 1,
                'user_one_id'   => 101,
                'user_two_id'   => 102,
                'score'         => 5,
                'status'        => 'pending_review',
                'match_source'  => 'auto',
                'created_at'    => '2026-09-20 12:00:00',
            ]
        ];
        $total_matches = 45;
        $current_page  = 1;
        $per_page      = 20;
        $total_pages   = 3;
        $search        = '';
        $status        = '';
        $source        = '';

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/admin/matches/matches-list.php';
        $html = (string) ob_get_clean();

        if (!str_contains($html, 'tablenav-pages')) {
            throw new \RuntimeException("Expected matches-list.php to render tablenav-pages pagination container");
        }
        if (!str_contains($html, '45 matches')) {
            throw new \RuntimeException("Expected matches-list.php to render '45 matches' displaying-num text");
        }
        if (!str_contains($html, 'placeholder="Search match ID, name, email"')) {
            throw new \RuntimeException("Expected matches-list.php to render 'Search match ID, name, email' placeholder");
        }
    }

    public function test_cancel_approved_match_updates_status_without_invalid_columns(): void
    {
        global $wpdb;
        $match_id = 88;
        $admin_id = 1;

        $wpdb->mock_rows["SELECT * FROM wp_matches WHERE id = 88"] = [
            'id'                => 88,
            'user_one_id'       => 601,
            'user_two_id'       => 602,
            'initiator_user_id' => 601,
            'status'            => 'approved',
            'approved_by'       => 1,
            'approved_at'       => '2026-09-25 10:00:00',
            'user_one_response' => 'pending',
            'user_two_response' => 'pending',
        ];
        $wpdb->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 601"] = [
            'user_id'   => 601,
            'user_type' => 'monthly',
        ];
        $wpdb->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 602"] = [
            'user_id'   => 602,
            'user_type' => 'free',
        ];

        $res = $this->repo->cancel_approved_match($match_id, $admin_id);
        if (empty($res['success'])) {
            throw new \RuntimeException("Expected cancel_approved_match to succeed, got: " . json_encode($res));
        }

        // Verify update calls on wp_matches do NOT contain user_one_responded_at
        $queries_str = implode("\n", $wpdb->queries);
        if (str_contains($queries_str, 'user_one_responded_at') || str_contains($queries_str, 'user_two_responded_at')) {
            throw new \RuntimeException("cancel_approved_match should not reference non-existent responded_at columns in query: {$queries_str}");
        }
    }
}



