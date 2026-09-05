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
}
