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
}
