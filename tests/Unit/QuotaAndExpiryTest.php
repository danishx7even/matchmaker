<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Matchmaker\Repository\MatchRepository;
use Matchmaker\Service\MatchService;

final class QuotaAndExpiryTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__mm_options'] = [];
        $GLOBALS['__mm_usermeta'] = [];
        $GLOBALS['wpdb'] = new \Fakewpdb();
    }

    public function test_default_and_custom_quota_settings(): void
    {
        $repo = MatchRepository::instance();
        $service = MatchService::instance();

        // Default quota
        $this->assertEquals(10, $repo->get_max_cycle_matches());
        $this->assertEquals(10, $service->get_max_cycle_matches());

        // Custom quota
        update_option('mm_max_cycle_matches', 25);
        $this->assertEquals(25, $repo->get_max_cycle_matches());
        $this->assertEquals(25, $service->get_max_cycle_matches());
    }

    public function test_default_and_custom_expiry_settings(): void
    {
        $repo = MatchRepository::instance();
        $service = MatchService::instance();

        // Default expiry
        $this->assertEquals(7, $repo->get_match_expiry_days());
        $this->assertEquals(7, $service->get_match_expiry_days());

        // Custom expiry
        update_option('mm_match_expiry_days', 14);
        $this->assertEquals(14, $repo->get_match_expiry_days());
        $this->assertEquals(14, $service->get_match_expiry_days());
    }

    public function test_quota_blockade_on_limit_reached(): void
    {
        $repo = MatchRepository::instance();
        update_option('mm_max_cycle_matches', 5);

        $user_id = 101;
        // User already has 5 matches approved this cycle
        update_user_meta($user_id, 'cycle_matches_count', 5);
        update_user_meta($user_id, 'mm_cycle_month', gmdate('Y-m'));

        // Mock match row
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matches WHERE id = 1"] = [
            'id' => 1,
            'user_one_id' => 101,
            'user_two_id' => 102,
            'initiator_user_id' => 101,
            'status' => 'pending_review',
        ];
        $GLOBALS['wpdb']->mock_vars["SELECT user_type FROM wp_matchmaking_pool WHERE user_id = 101"] = 'monthly';
        $GLOBALS['wpdb']->mock_vars["SELECT user_type FROM wp_matchmaking_pool WHERE user_id = 102"] = 'monthly';

        $result = $repo->approve_match(1, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('5-match monthly quota', $result['message']);
    }

    public function test_days_remaining_calculated_when_user_has_accepted_and_not_expired(): void
    {
        $repo = MatchRepository::instance();
        update_option('mm_match_expiry_days', 7);

        $user_id = 201;
        $other_id = 202;

        // Mock approved match created today where user has accepted and other is pending
        $today = current_time('mysql');
        $match_row = [
            'id' => 88,
            'user_one_id' => $user_id,
            'user_two_id' => $other_id,
            'initiator_user_id' => $user_id,
            'status' => 'approved',
            'user_one_response' => 'accepted',
            'user_two_response' => 'pending',
            'score' => 5,
            'contact_revealed' => 0,
            'approved_at' => $today,
            'created_at' => $today,
            'updated_at' => $today,
        ];

        $GLOBALS['wpdb']->mock_results["SELECT * FROM wp_matches\n                 WHERE (user_one_id = 201 OR user_two_id = 201)\n                   AND status IN ('approved', 'matched')\n                 ORDER BY created_at DESC"] = [$match_row];
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 202"] = [
            'user_id' => 202,
            'city' => 'Doha',
            'country' => 'Qatar',
            'birth_date' => '1995-05-15',
        ];

        $matches = $repo->find_approved_matches_for_user($user_id);
        $this->assertNotEmpty($matches);
        $this->assertEquals(88, $matches[0]['match_id']);
        $this->assertEquals('accepted', $matches[0]['my_response']);
        $this->assertEquals('pending', $matches[0]['their_response']);
        // days_remaining MUST be 7 (or positive), not 0
        $this->assertGreaterThanOrEqual(6, $matches[0]['days_remaining']);
        $this->assertLessThanOrEqual(7, $matches[0]['days_remaining']);

        // Test get_match_stats also returns positive days_remaining
        $GLOBALS['wpdb']->mock_rows["SELECT id, user_one_id, user_two_id, user_one_response, user_two_response, approved_at, updated_at, created_at\n                 FROM wp_matches\n                 WHERE (user_one_id = 201 OR user_two_id = 201)\n                   AND status = 'approved'\n                 ORDER BY created_at DESC LIMIT 1"] = $match_row;

        $stats = $repo->get_match_stats($user_id);
        $this->assertGreaterThanOrEqual(6, $stats['days_remaining']);
        $this->assertLessThanOrEqual(7, $stats['days_remaining']);
    }
}

