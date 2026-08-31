<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use Matchmaker\Repository\MatchRepository;

class LoggingTest
{
    private MatchRepository $repo;

    public function setUp(): void
    {
        $GLOBALS['__mm_options']  = [];
        $GLOBALS['__mm_usermeta'] = [];
        $GLOBALS['wpdb']->queries = [];
        $GLOBALS['wpdb']->mock_results = [];
        $GLOBALS['wpdb']->mock_rows = [];
        $GLOBALS['wpdb']->mock_vars = [];
        $this->repo = MatchRepository::instance();
    }

    public function test_log_event_inserts_record(): void
    {
        global $wpdb;

        $log_id = $this->repo->log_event(
            'match_lifecycle',
            'admin_approved',
            'Match Approved by Admin #1',
            'Admin #1 approved match #50.',
            ['match_id' => 50, 'admin_id' => 1],
            50,
            1,
            'user@example.com',
            'success'
        );

        $queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str, 'INSERT INTO wp_matchmaker_logs') && !str_contains($queries_str, 'wp_matchmaker_logs')) {
            throw new \RuntimeException("Expected INSERT into wp_matchmaker_logs, queries:\n{$queries_str}");
        }
    }

    public function test_get_logs_queries_with_type_filter(): void
    {
        global $wpdb;

        $logs_table = $wpdb->prefix . 'matchmaker_logs';
        $expected_query = "SELECT * FROM {$logs_table} WHERE log_type IN ('email','notification') ORDER BY id DESC LIMIT 25 OFFSET 0";

        $mock_log = [
            'id' => 1,
            'log_type' => 'email',
            'event_type' => 'email_sent',
            'title' => 'Match Approval Email',
            'message' => 'Email sent to user@example.com',
            'details_json' => '{"subject":"New Match"}',
            'status' => 'success',
            'created_at' => '2026-08-31 06:00:00',
        ];

        $wpdb->mock_results[$expected_query] = [$mock_log];

        $results = $this->repo->get_logs('email,notification', 25, 0);

        if (empty($results) || (int)$results[0]['id'] !== 1) {
            throw new \RuntimeException("Expected get_logs to return mock log record with ID 1");
        }
    }

    public function test_get_logs_count(): void
    {
        global $wpdb;
        $logs_table = $wpdb->prefix . 'matchmaker_logs';
        $count_query = "SELECT COUNT(*) FROM {$logs_table} WHERE log_type IN ('match_engine')";

        $wpdb->mock_vars[$count_query] = 17;

        $count = $this->repo->get_logs_count('match_engine');
        if ($count !== 17) {
            throw new \RuntimeException("Expected get_logs_count to return 17, got {$count}");
        }
    }
}
