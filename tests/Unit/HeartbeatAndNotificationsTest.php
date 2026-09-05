<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use Matchmaker\Service\NotificationService;
use Matchmaker\Repository\MatchRepository;
use FakePMProLevel;

class HeartbeatAndNotificationsTest
{
    private NotificationService $service;
    private MatchRepository $repo;

    public function setUp(): void
    {
        $GLOBALS['__mm_options']   = [];
        $GLOBALS['__mm_usermeta']  = [];
        $GLOBALS['wpdb']->queries  = [];
        $GLOBALS['wpdb']->mock_rows = [];
        $GLOBALS['wpdb']->mock_vars = [];

        // Level ID 3 is the default Monthly Tier in PMProSync
        $GLOBALS['__mm_user_pmpro_level'][1] = 3;
        update_user_meta(1, 'user_type', 'monthly');

        $this->service = NotificationService::instance();
        $this->repo    = MatchRepository::instance();
    }

    public function test_heartbeat_settings_frequency(): void
    {
        $settings = $this->service->configure_heartbeat_frequency(['interval' => 60]);
        if (($settings['interval'] ?? 0) !== 15) {
            throw new \RuntimeException("Expected Heartbeat API interval to be set to 15 seconds, got: " . ($settings['interval'] ?? 'null'));
        }
    }

    public function test_heartbeat_pulse_for_active_member(): void
    {
        global $wpdb;

        // User ID 1 is logged in (from Fake get_current_user_id)
        $GLOBALS['wpdb']->mock_vars["SELECT user_type FROM wp_matchmaking_pool WHERE user_id = 1"] = 'monthly';
        $GLOBALS['wpdb']->mock_vars["SELECT COUNT(*) FROM wp_matchmaker_notifications WHERE user_id = 1 AND is_read = 0"] = 3;

        $response = $this->service->handle_heartbeat_pulse([], ['screen_id' => 'matchmaker_dashboard']);

        if (($response['mm_unread_count'] ?? null) !== 3) {
            throw new \RuntimeException("Expected Heartbeat pulse unread count to be 3, got: " . var_export($response, true));
        }
    }

    public function test_mark_notifications_read_clears_unread(): void
    {
        global $wpdb;
        $user_id = 1;

        $this->repo->mark_notifications_read($user_id);

        $queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str, 'wp_matchmaker_notifications')) {
            throw new \RuntimeException("Expected notification query on wp_matchmaker_notifications");
        }
    }
}
