<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use Matchmaker\Repository\MatchRepository;
use Matchmaker\Service\MatchService;
use FakeWP_User;

class NotificationAndApprovalTest
{
    private MatchRepository $repo;
    private MatchService $service;

    public function setUp(): void
    {
        $GLOBALS['__mm_options']  = [];
        $GLOBALS['__mm_usermeta'] = [];
        $GLOBALS['wpdb']->queries = [];
        $GLOBALS['wpdb']->mock_results = [];
        $GLOBALS['wpdb']->mock_rows = [];
        $GLOBALS['wpdb']->mock_vars = [];

        $GLOBALS['__mm_users'][10] = new FakeWP_User(10, 'user10', 'user10@example.com');
        $GLOBALS['__mm_users'][20] = new FakeWP_User(20, 'user20', 'user20@example.com');

        $this->repo = MatchRepository::instance();
        $this->service = MatchService::instance();
    }

    public function test_approve_match_returns_message_and_creates_notifications_and_emails(): void
    {
        global $wpdb;

        $match_id = 42;
        $admin_id = 1;

        // Mock match row
        $match = [
            'id'                  => 42,
            'user_one_id'         => 10,
            'user_two_id'         => 20,
            'initiator_user_id'   => 10,
            'status'              => 'pending_review',
            'user_one_response'   => 'pending',
            'user_two_response'   => 'pending',
            'score'               => 5,
            'contact_revealed'    => 0,
        ];

        $pool10 = [
            'user_id'    => 10,
            'gender'     => 'male',
            'user_type'  => 'monthly',
            'birth_date' => '1995-01-01',
            'location'   => 'Riyadh',
        ];

        $pool20 = [
            'user_id'    => 20,
            'gender'     => 'female',
            'user_type'  => 'monthly',
            'birth_date' => '1996-01-01',
            'location'   => 'Riyadh',
        ];

        $wpdb->mock_rows = [
            "SELECT * FROM wp_matches WHERE id = 42" => $match,
            "SELECT * FROM wp_matchmaking_pool WHERE user_id = 10" => $pool10,
            "SELECT * FROM wp_matchmaking_pool WHERE user_id = 20" => $pool20,
        ];

        $wpdb->mock_vars = [
            "SELECT user_type FROM wp_matchmaking_pool WHERE user_id = 10" => 'monthly',
            "SELECT user_type FROM wp_matchmaking_pool WHERE user_id = 20" => 'monthly',
        ];

        $result = $this->service->process_admin_approve($match_id, $admin_id);

        // 1. Assert result format contains 'success' and 'message'
        if (!isset($result['success']) || $result['success'] !== true) {
            throw new \RuntimeException("Expected process_admin_approve to succeed, got: " . var_export($result, true));
        }

        if (empty($result['message'])) {
            throw new \RuntimeException("Expected process_admin_approve result to include 'message' key");
        }

        // 2. Assert notifications were inserted and emails were sent
        $queries_str = implode("\n", $wpdb->queries);

        if (!str_contains($queries_str, 'wp_matchmaker_notifications')) {
            throw new \RuntimeException("Expected notification insert into wp_matchmaker_notifications");
        }

        if (!str_contains($queries_str, 'wp_matchmaker_logs')) {
            throw new \RuntimeException("Expected structured logs insert into wp_matchmaker_logs");
        }
    }

    public function test_mutual_match_triggers_mutual_notifications(): void
    {
        global $wpdb;

        $match_id = 55;
        $user_id  = 20;

        $match = [
            'id'                  => 55,
            'user_one_id'         => 10,
            'user_two_id'         => 20,
            'initiator_user_id'   => 10,
            'status'              => 'approved',
            'user_one_response'   => 'accepted',
            'user_two_response'   => 'pending',
            'score'               => 6,
            'contact_revealed'    => 0,
        ];

        $wpdb->mock_rows = [
            "SELECT * FROM wp_matches WHERE id = 55" => $match,
        ];

        $res = $this->service->handle_match_response($match_id, $user_id, 'accept');

        if (empty($res['is_mutual']) || $res['is_mutual'] !== true) {
            throw new \RuntimeException("Expected response to evaluate to mutual match");
        }

        $queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str, 'wp_matchmaker_notifications')) {
            throw new \RuntimeException("Expected mutual match in-app notification insert");
        }
    }

    public function test_decline_match_dispatches_rejection_notification_to_other_candidate(): void
    {
        global $wpdb;

        $match_id = 77;
        $declining_user_id = 10; // User 10 declines match with User 20

        $match = [
            'id'                  => 77,
            'user_one_id'         => 10,
            'user_two_id'         => 20,
            'initiator_user_id'   => 10,
            'status'              => 'approved',
            'user_one_response'   => 'pending',
            'user_two_response'   => 'pending',
            'score'               => 4,
            'contact_revealed'    => 0,
        ];

        $wpdb->mock_rows = [
            "SELECT * FROM wp_matches WHERE id = 77" => $match,
        ];

        $res = $this->service->handle_match_response($match_id, $declining_user_id, 'reject');

        if (empty($res['success']) || $res['success'] !== true) {
            throw new \RuntimeException("Expected decline match response to succeed");
        }

        $queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str, 'wp_matchmaker_notifications')) {
            throw new \RuntimeException("Expected rejection notification to be dispatched to candidate in wp_matchmaker_notifications");
        }

        // Verify stale 'match_approved' notification invalidation
        $found_dismiss_query = false;
        foreach ($wpdb->queries as $q) {
            if (str_contains($q, 'UPDATE wp_matchmaker_notifications SET is_read = 1') && str_contains($q, '77')) {
                $found_dismiss_query = true;
                break;
            }
        }
        if (!$found_dismiss_query) {
            throw new \RuntimeException("Expected stale 'match_approved' notifications for match #77 to be invalidated with is_read = 1");
        }
    }

    public function test_admin_can_approve_free_user_without_quota_block(): void
    {
        global $wpdb;

        $match_id = 88;
        $admin_id = 1;

        $match = [
            'id'                  => 88,
            'user_one_id'         => 10,
            'user_two_id'         => 20,
            'initiator_user_id'   => 10,
            'status'              => 'pending_review',
            'user_one_response'   => 'pending',
            'user_two_response'   => 'pending',
            'score'               => 5,
            'contact_revealed'    => 0,
        ];

        $pool10 = [
            'user_id'    => 10,
            'gender'     => 'male',
            'user_type'  => 'free',
            'birth_date' => '1995-01-01',
            'country'    => 'United States',
            'city'       => 'New York',
        ];

        $pool20 = [
            'user_id'    => 20,
            'gender'     => 'female',
            'user_type'  => 'monthly',
            'birth_date' => '1996-01-01',
            'country'    => 'United States',
            'city'       => 'New York',
        ];

        $wpdb->mock_rows = [
            "SELECT * FROM wp_matches WHERE id = 88" => $match,
            "SELECT * FROM wp_matchmaking_pool WHERE user_id = 10" => $pool10,
            "SELECT * FROM wp_matchmaking_pool WHERE user_id = 20" => $pool20,
        ];

        $wpdb->mock_vars = [
            "SELECT user_type FROM wp_matchmaking_pool WHERE user_id = 10" => 'free',
            "SELECT user_type FROM wp_matchmaking_pool WHERE user_id = 20" => 'monthly',
        ];

        $result = $this->service->process_admin_approve($match_id, $admin_id);

        if (!isset($result['success']) || $result['success'] !== true) {
            throw new \RuntimeException("Expected admin approval for free user to succeed, got: " . var_export($result, true));
        }
    }
}
