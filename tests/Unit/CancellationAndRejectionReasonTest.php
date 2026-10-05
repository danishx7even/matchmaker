<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Matchmaker\Repository\MatchRepository;
use Matchmaker\Service\MatchService;
use Matchmaker\Service\NotificationService;
use Matchmaker\Core\PMProSync;
use Matchmaker\Frontend\PortalController;

final class CancellationAndRejectionReasonTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__mm_options']   = [];
        $GLOBALS['__mm_usermeta']  = [];
        $GLOBALS['__mm_users']     = [];
        $GLOBALS['__mm_actions']   = [];
        $GLOBALS['__mm_filters']   = [];
        $GLOBALS['wpdb']           = new \Fakewpdb();
        unset($_POST, $_GET, $_REQUEST);
    }

    public function test_match_decline_with_reason_stores_rejection_reason_and_logs(): void
    {
        $wpdb = $GLOBALS['wpdb'];
        $repo = MatchRepository::instance();

        $match_id = 101;
        $match_row = [
            'id'                        => $match_id,
            'user_one_id'               => 10,
            'user_two_id'               => 20,
            'initiator_user_id'         => 10,
            'status'                    => 'approved',
            'user_one_response'         => 'pending',
            'user_two_response'         => 'pending',
            'user_one_rejection_reason' => null,
            'user_two_rejection_reason' => null,
            'contact_revealed'          => 0,
        ];

        $wpdb->mock_rows["SELECT * FROM wp_matches WHERE id = {$match_id}"] = $match_row;

        $reason_text = 'Looking for someone living closer to Dubai.';
        $result = MatchService::instance()->handle_match_response($match_id, 10, 'decline', $reason_text);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['next_step']);

        // Verify update query executed on wp_matches
        $has_update = false;
        foreach ($wpdb->queries as $q) {
            if (str_contains($q, 'UPDATE wp_matches')) {
                $has_update = true;
                break;
            }
        }
        $this->assertTrue($has_update);
    }

    public function test_match_decline_via_portal_controller_validates_minimum_length(): void
    {
        $wpdb = $GLOBALS['wpdb'];
        $match_id = 102;
        $match_row = [
            'id'                        => $match_id,
            'user_one_id'               => 15,
            'user_two_id'               => 25,
            'initiator_user_id'         => 15,
            'status'                    => 'approved',
            'user_one_response'         => 'pending',
            'user_two_response'         => 'pending',
            'user_one_rejection_reason' => null,
            'user_two_rejection_reason' => null,
            'contact_revealed'          => 0,
        ];
        $wpdb->mock_rows["SELECT * FROM wp_matches WHERE id = {$match_id}"] = $match_row;

        $GLOBALS['__mm_current_user_id'] = 15;
        $_POST['nonce'] = 'test_portal_nonce';
        $_POST['match_id'] = $match_id;
        $_POST['response_action'] = 'decline';
        $_POST['rejection_reason'] = 'bad'; // < 5 chars

        $controller = PortalController::instance();

        ob_start();
        try {
            $controller->handle_ajax_match_response();
        } catch (\RuntimeException $e) {
            // caught
        }
        $json_output = (string) ob_get_clean();
        $this->assertStringContainsString('at least 5 characters', $json_output);

        // Now with valid length
        $_POST['rejection_reason'] = 'Age preference differs from my criteria';
        ob_start();
        try {
            $controller->handle_ajax_match_response();
        } catch (\RuntimeException $e) {
            // caught
        }
        $valid_json = (string) ob_get_clean();
        $this->assertStringContainsString('"success":true', $valid_json);
    }

    public function test_pmpro_cancellation_saves_reason_details_and_logs(): void
    {
        $user_id  = 55;
        $level_id = 3; // monthly tier (DEFAULT_LEVEL_MAPPING 3 => 'monthly')

        $_REQUEST['mm_cancellation_reason']  = 'Found partner through Arab Zawaj';
        $_REQUEST['mm_cancellation_details'] = 'Met a wonderful match and getting married next month! Thank you.';

        $sync = PMProSync::instance();
        $res = $sync->defer_cancellation_to_end_of_period(true, $level_id, $user_id);

        $this->assertFalse($res); // Should return false to halt immediate level removal

        $saved_reason  = get_user_meta($user_id, 'mm_cancellation_reason', true);
        $saved_details = get_user_meta($user_id, 'mm_cancellation_details', true);
        $saved_date    = get_user_meta($user_id, 'mm_cancellation_date', true);

        $this->assertEquals('Found partner through Arab Zawaj', $saved_reason);
        $this->assertEquals('Met a wonderful match and getting married next month! Thank you.', $saved_details);
        $this->assertNotEmpty($saved_date);
    }

    public function test_match_history_includes_rejection_reasons(): void
    {
        $wpdb = $GLOBALS['wpdb'];
        $repo = MatchRepository::instance();

        $history_query = "SELECT * FROM wp_matches
                 WHERE (user_one_id = 10 OR user_two_id = 10) AND status IN ('approved', 'matched', 'archived', 'rejected', 'expired')
                 ORDER BY COALESCE(approved_at, created_at) DESC, id DESC";

        $wpdb->mock_results[$history_query] = [
            [
                'id'                        => 201,
                'user_one_id'               => 10,
                'user_two_id'               => 30,
                'initiator_user_id'         => 10,
                'status'                    => 'rejected',
                'user_one_response'         => 'rejected',
                'user_two_response'         => 'pending',
                'user_one_rejection_reason' => 'Distance is too far.',
                'user_two_rejection_reason' => null,
                'created_at'                => '2026-10-01 12:00:00',
                'approved_at'               => null,
                'updated_at'                => '2026-10-01 12:00:00',
            ],
        ];

        $history = $repo->find_match_history_for_user(10);
        $this->assertNotEmpty($history);
        $this->assertEquals('Distance is too far.', $history[0]['my_rejection_reason']);
        $this->assertEquals('Distance is too far.', $history[0]['user_one_rejection_reason']);
    }

    public function test_cancelled_membership_card_details_shows_cancelled_active_status(): void
    {
        $user_id = 77;
        $level_id = 3;
        $future_exp = date('Y-m-d H:i:s', time() + (20 * 86400));

        update_user_meta($user_id, 'mm_subscription_cancelled_at', date('Y-m-d H:i:s'));
        update_user_meta($user_id, 'mm_subscription_expires_at', $future_exp);
        update_user_meta($user_id, 'mm_cancelled_level_id', $level_id);

        $level_obj = (object) [
            'id'             => $level_id,
            'name'           => 'Monthly Gold',
            'user_id'        => $user_id,
            'startdate'      => date('Y-m-d H:i:s', time() - (10 * 86400)),
            'enddate'        => $future_exp,
            'billing_amount' => '49.00',
        ];

        $sync = PMProSync::instance();
        $details = $sync->get_membership_level_card_details($level_obj, $user_id);

        $this->assertTrue($details['is_subscription_cancelled']);
        $this->assertStringContainsString('Cancelled (Active until', $details['status']);
        $this->assertEquals('mm-badge-cancelled-active', $details['status_badge_class']);
        $this->assertStringContainsString('Subscription cancelled. Access remains active until', $details['cancelled_notice']);
    }

    public function test_cancelled_membership_action_links_and_can_cancel_suppression(): void
    {
        $user_id = 88;
        $level_id = 3;
        $future_exp = date('Y-m-d H:i:s', time() + (15 * 86400));

        update_user_meta($user_id, 'mm_subscription_cancelled_at', date('Y-m-d H:i:s'));
        update_user_meta($user_id, 'mm_subscription_expires_at', $future_exp);
        update_user_meta($user_id, 'mm_cancelled_level_id', $level_id);

        $sync = PMProSync::instance();

        // 1. Action links should have cancel removed
        $links = [
            'change' => '<a href="/change">Change</a>',
            'cancel' => '<a href="/cancel">Cancel</a>',
            'pmpro_cancel' => '<a href="/cancel">Cancel</a>',
        ];

        $level_obj = (object) ['id' => $level_id, 'enddate' => $future_exp];
        $filtered = $sync->filter_pmpro_member_action_links($links, $level_obj, $user_id);

        $this->assertArrayNotHasKey('cancel', $filtered);
        $this->assertArrayNotHasKey('pmpro_cancel', $filtered);
        $this->assertArrayHasKey('change', $filtered);

        // 2. Can cancel filter should return false
        $can_cancel = $sync->filter_pmpro_can_cancel_membership_level(true, $user_id, $level_obj);
        $this->assertFalse($can_cancel);
    }

    public function test_monthly_recurring_level_with_future_enddate_detected_as_cancelled_without_usermeta(): void
    {
        $user_id = 99;
        $level_id = 3;
        $future_exp = date('Y-m-d H:i:s', time() + (30 * 86400));

        // No mm_subscription_cancelled_at in usermeta
        $level_obj = (object) [
            'id'             => $level_id,
            'name'           => 'Monthly Membership',
            'user_id'        => $user_id,
            'startdate'      => date('Y-m-d H:i:s', time() - (5 * 86400)),
            'enddate'        => $future_exp,
            'billing_amount' => '50.00',
        ];

        $sync = PMProSync::instance();

        // 1. is_level_subscription_cancelled should return true
        $this->assertTrue($sync->is_level_subscription_cancelled($user_id, $level_obj));

        // 2. Card details should show cancelled status
        $details = $sync->get_membership_level_card_details($level_obj, $user_id);
        $this->assertTrue($details['is_subscription_cancelled']);
        $this->assertStringContainsString('Cancelled (Active until', $details['status']);
        $this->assertEquals('mm-badge-cancelled-active', $details['status_badge_class']);

        // 3. Action links should strip cancel link
        $links = [
            'change' => '<a href="/change">Change</a>',
            'cancel' => '<a href="/cancel">Cancel</a>',
        ];
        $filtered = $sync->filter_pmpro_member_action_links($links, $level_obj, $user_id);
        $this->assertArrayNotHasKey('cancel', $filtered);
    }

    public function test_get_user_cancellation_info_resolves_all_fields(): void
    {
        $user_id = 105;
        $future_exp = date('Y-m-d H:i:s', time() + (14 * 86400));

        update_user_meta($user_id, 'mm_subscription_cancelled_at', date('Y-m-d H:i:s'));
        update_user_meta($user_id, 'mm_subscription_expires_at', $future_exp);
        update_user_meta($user_id, 'mm_cancellation_reason', 'Taking a temporary break');
        update_user_meta($user_id, 'mm_cancellation_details', 'Busy with studies this semester.');
        update_user_meta($user_id, 'mm_cancellation_date', '2026-10-05 14:00:00');

        $sync = PMProSync::instance();
        $info = $sync->get_user_cancellation_info($user_id);

        $this->assertNotNull($info);
        $this->assertTrue($info['is_cancelled']);
        $this->assertEquals('Taking a temporary break', $info['reason']);
        $this->assertEquals('Busy with studies this semester.', $info['details']);
        $this->assertNotEmpty($info['expires_at']);
        $this->assertNotEmpty($info['cancellation_date']);
    }

    public function test_get_user_cancellation_info_with_only_pmpro_enddate_and_no_reason(): void
    {
        $user_id = 106;
        $level_id = 3;
        $future_exp = date('Y-m-d H:i:s', time() + (10 * 86400));

        $level_obj = (object) [
            'id'             => $level_id,
            'name'           => 'Monthly Tier',
            'user_id'        => $user_id,
            'enddate'        => $future_exp,
            'billing_amount' => '30.00',
        ];

        // Mock pmpro_getMembershipLevelsForUser
        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = [$level_obj];

        $sync = PMProSync::instance();
        $info = $sync->get_user_cancellation_info($user_id);

        $this->assertNotNull($info);
        $this->assertTrue($info['is_cancelled']);
        $this->assertEquals('Not specified', $info['reason']);
        $this->assertNotEmpty($info['expires_at']);
    }

    public function test_get_user_cancellation_info_returns_null_for_active_user(): void
    {
        $user_id = 107;
        // Clean user with no cancellation data
        delete_user_meta($user_id, 'mm_subscription_cancelled_at');
        delete_user_meta($user_id, 'mm_subscription_expires_at');
        delete_user_meta($user_id, 'mm_cancellation_reason');
        delete_user_meta($user_id, 'mm_cancellation_details');

        $sync = PMProSync::instance();
        $info = $sync->get_user_cancellation_info($user_id);

        $this->assertNull($info);
    }
}


