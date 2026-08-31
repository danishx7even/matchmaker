<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use Matchmaker\Repository\MatchRepository;

class ModeAndResetTest
{
    private MatchRepository $repo;

    public function setUp(): void
    {
        $GLOBALS['__mm_options']  = [];
        $GLOBALS['__mm_usermeta'] = [];
        $GLOBALS['wpdb']->queries = [];
        $GLOBALS['wpdb']->mock_results = [];
        $GLOBALS['wpdb']->mock_vars = [];
        $this->repo = MatchRepository::instance();
    }

    public function test_default_environment_mode_is_live(): void
    {
        $mode = $this->repo->get_environment_mode();
        if ($mode !== 'live') {
            throw new \RuntimeException("Expected default mode to be 'live', got '{$mode}'");
        }

        if ($this->repo->is_test_mode()) {
            throw new \RuntimeException("Expected is_test_mode() to be false by default");
        }
    }

    public function test_environment_mode_switch(): void
    {
        update_option('mm_environment_mode', 'test');
        if (!$this->repo->is_test_mode()) {
            throw new \RuntimeException("Expected is_test_mode() to be true when option is 'test'");
        }

        update_option('mm_environment_mode', 'live');
        if ($this->repo->is_test_mode()) {
            throw new \RuntimeException("Expected is_test_mode() to be false when option is 'live'");
        }
    }

    public function test_reset_test_matchmaking_data(): void
    {
        global $wpdb;
        $pool_table = $wpdb->prefix . 'matchmaking_pool';

        // Mock pool count
        $wpdb->mock_vars["SELECT COUNT(*) FROM {$pool_table}"] = 42;

        // Set some user cycle meta
        update_user_meta(101, 'cycle_matches_count', 5);
        update_user_meta(101, 'mm_cycle_matches_count', 5);
        update_user_meta(101, 'mm_last_match_run', '2026-08-30 12:00:00');

        $result = $this->repo->reset_test_matchmaking_data();

        if ($result['profiles_preserved'] !== 42) {
            throw new \RuntimeException("Expected 42 profiles preserved, got {$result['profiles_preserved']}");
        }

        // Verify TRUNCATE or DELETE queries executed
        $queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str, 'wp_matches') || !str_contains($queries_str, 'wp_matchmaker_notifications') || !str_contains($queries_str, 'wp_matchmaker_logs')) {
            throw new \RuntimeException("Expected matches, notifications, and logs tables to be cleared in queries:\n{$queries_str}");
        }

        // Verify usermeta delete query executed
        if (!str_contains($queries_str, 'DELETE FROM wp_usermeta WHERE meta_key IN')) {
            throw new \RuntimeException("Expected usermeta cycle reset query to be executed, queries:\n{$queries_str}");
        }
    }
}
