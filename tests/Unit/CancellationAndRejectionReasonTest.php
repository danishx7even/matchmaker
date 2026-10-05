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
}
