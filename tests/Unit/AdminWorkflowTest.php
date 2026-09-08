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
}

