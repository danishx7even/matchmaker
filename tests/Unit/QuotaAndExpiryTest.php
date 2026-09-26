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

    public function test_decrement_quota_reduces_counter_and_stops_at_zero(): void
    {
        $repo = MatchRepository::instance();
        $user_id = 301;
        update_user_meta($user_id, 'mm_cycle_month', gmdate('Y-m'));
        update_user_meta($user_id, 'cycle_matches_count', 3);

        $repo->decrement_quota($user_id);
        $this->assertEquals(2, (int) get_user_meta($user_id, 'cycle_matches_count', true));

        $repo->decrement_quota($user_id);
        $this->assertEquals(1, (int) get_user_meta($user_id, 'cycle_matches_count', true));

        $repo->decrement_quota($user_id);
        $this->assertEquals(0, (int) get_user_meta($user_id, 'cycle_matches_count', true));

        // Decrementing when 0 should remain 0
        $repo->decrement_quota($user_id);
        $this->assertEquals(0, (int) get_user_meta($user_id, 'cycle_matches_count', true));
    }

    public function test_cancel_approved_match_reverts_status_and_rolls_back_quota(): void
    {
        $repo    = MatchRepository::instance();
        $service = MatchService::instance();
        $u1_id   = 401;
        $u2_id   = 402;
        $admin_id= 1;
        $match_id= 99;

        // Set monthly cycle quota for both users
        update_user_meta($u1_id, 'mm_cycle_month', gmdate('Y-m'));
        update_user_meta($u1_id, 'cycle_matches_count', 4);
        update_user_meta($u2_id, 'mm_cycle_month', gmdate('Y-m'));
        update_user_meta($u2_id, 'cycle_matches_count', 2);

        // Mock pool records (both monthly tier)
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 401"] = [
            'user_id'   => 401,
            'user_type' => 'monthly',
        ];
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 402"] = [
            'user_id'   => 402,
            'user_type' => 'monthly',
        ];

        // Mock approved match
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matches WHERE id = 99"] = [
            'id'                    => 99,
            'user_one_id'           => 401,
            'user_two_id'           => 402,
            'initiator_user_id'     => 401,
            'status'                => 'approved',
            'approved_by'           => 1,
            'approved_at'           => current_time('mysql'),
            'user_one_response'     => 'pending',
            'user_two_response'     => 'pending',
        ];

        // Attempt cancel through MatchService
        $result = $service->process_admin_cancel_approved(99, $admin_id);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('reverted to pending review', $result['message']);

        // Check that quotas are decremented
        $this->assertEquals(3, (int) get_user_meta($u1_id, 'cycle_matches_count', true));
        $this->assertEquals(1, (int) get_user_meta($u2_id, 'cycle_matches_count', true));

        // Test cancelling a non-approved match fails
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matches WHERE id = 100"] = [
            'id'          => 100,
            'user_one_id' => 401,
            'user_two_id' => 402,
            'status'      => 'pending_review',
        ];
        $fail_result = $service->process_admin_cancel_approved(100, $admin_id);
        $this->assertFalse($fail_result['success']);
        $this->assertStringContainsString('Only approved matches can be cancelled', $fail_result['message']);
    }

    public function test_approve_match_increments_quota_for_monthly_user_when_paired_with_free_or_event(): void
    {
        $repo = MatchRepository::instance();
        update_option('mm_max_cycle_matches', 10);

        // Case 1: User 1 is Free (Initiator), User 2 is Monthly (Candidate)
        $free_user_id    = 701;
        $monthly_user_id = 702;

        update_user_meta($free_user_id, 'mm_cycle_month', gmdate('Y-m'));
        update_user_meta($free_user_id, 'cycle_matches_count', 0);
        update_user_meta($monthly_user_id, 'mm_cycle_month', gmdate('Y-m'));
        update_user_meta($monthly_user_id, 'cycle_matches_count', 0);

        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 701"] = [
            'user_id'   => 701,
            'user_type' => 'free',
        ];
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 702"] = [
            'user_id'   => 702,
            'user_type' => 'monthly',
        ];

        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matches WHERE id = 120"] = [
            'id'                => 120,
            'user_one_id'       => 701,
            'user_two_id'       => 702,
            'initiator_user_id' => 701, // Free user is initiator
            'status'            => 'pending_review',
        ];

        $result = $repo->approve_match(120, 1);
        $this->assertTrue($result['success']);

        // Monthly user quota must be incremented to 1
        $this->assertEquals(1, (int) get_user_meta($monthly_user_id, 'cycle_matches_count', true));
        // Free user quota must remain 0
        $this->assertEquals(0, (int) get_user_meta($free_user_id, 'cycle_matches_count', true));

        // Case 2: Candidate (User 2) is Monthly and has reached max quota (10)
        update_user_meta($monthly_user_id, 'cycle_matches_count', 10);

        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matches WHERE id = 121"] = [
            'id'                => 121,
            'user_one_id'       => 701,
            'user_two_id'       => 702,
            'initiator_user_id' => 701,
            'status'            => 'pending_review',
        ];

        $block_result = $repo->approve_match(121, 1);
        $this->assertFalse($block_result['success']);
        $this->assertStringContainsString('10-match monthly quota', $block_result['message']);
    }

    public function test_recalculate_user_quota_and_all_quotas(): void
    {
        $repo = MatchRepository::instance();
        $cur_month = gmdate('Y-m');

        // Setup 1: Monthly User 801 who had 0 counter in meta due to historical bug, but has 3 approved/matched matches
        $u801 = 801;
        update_user_meta($u801, 'cycle_matches_count', 0);
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 801"] = [
            'user_id'   => 801,
            'user_type' => 'monthly',
        ];

        $sql801 = $GLOBALS['wpdb']->prepare(
            "SELECT COUNT(*) FROM wp_matches
             WHERE (user_one_id = %d OR user_two_id = %d)
               AND status IN ('approved', 'matched', 'rejected', 'expired')
               AND (
                   (approved_at IS NOT NULL AND DATE_FORMAT(approved_at, '%%Y-%%m') = %s)
                   OR (approved_at IS NULL AND DATE_FORMAT(created_at, '%%Y-%%m') = %s)
               )",
            801,
            801,
            $cur_month,
            $cur_month
        );
        $GLOBALS['wpdb']->mock_vars[$sql801] = 3;

        $count801 = $repo->recalculate_user_quota(801);
        $this->assertEquals(3, $count801);
        $this->assertEquals(3, (int) get_user_meta(801, 'cycle_matches_count', true));
        $this->assertEquals($cur_month, get_user_meta(801, 'mm_cycle_month', true));

        // Setup 2: Free User 802 - should return 0 and delete quota meta
        update_user_meta(802, 'cycle_matches_count', 5);
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 802"] = [
            'user_id'   => 802,
            'user_type' => 'free',
        ];
        $count802 = $repo->recalculate_user_quota(802);
        $this->assertEquals(0, $count802);
        $this->assertEquals('', get_user_meta(802, 'cycle_matches_count', true));

        // Setup 3: Recalculate all monthly users
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matchmaking_pool WHERE user_id = 803"] = [
            'user_id'   => 803,
            'user_type' => 'monthly',
        ];
        $sql803 = $GLOBALS['wpdb']->prepare(
            "SELECT COUNT(*) FROM wp_matches
             WHERE (user_one_id = %d OR user_two_id = %d)
               AND status IN ('approved', 'matched', 'rejected', 'expired')
               AND (
                   (approved_at IS NOT NULL AND DATE_FORMAT(approved_at, '%%Y-%%m') = %s)
                   OR (approved_at IS NULL AND DATE_FORMAT(created_at, '%%Y-%%m') = %s)
               )",
            803,
            803,
            $cur_month,
            $cur_month
        );
        $GLOBALS['wpdb']->mock_vars[$sql803] = 1;

        $GLOBALS['wpdb']->mock_cols["SELECT user_id FROM wp_matchmaking_pool WHERE user_type = 'monthly'"] = [801, 803];

        $summary = $repo->recalculate_all_user_quotas();
        $this->assertEquals(2, $summary['updated_count']);
        $this->assertEquals(3, $summary['user_quotas'][801]);
        $this->assertEquals(1, $summary['user_quotas'][803]);
    }

    public function test_get_match_stats_uses_monthly_quota_count_excluding_pending_records(): void
    {
        $repo = MatchRepository::instance();
        $user_id = 901;
        $cur_month = gmdate('Y-m');

        // Set monthly quota count to 2
        update_user_meta($user_id, 'cycle_matches_count', 2);
        update_user_meta($user_id, 'mm_cycle_month', $cur_month);

        $stats = $repo->get_match_stats($user_id);

        // received_this_term must equal the monthly quota count (2), regardless of pending matches in DB
        $this->assertEquals(2, $stats['received_this_term']);
    }
}



