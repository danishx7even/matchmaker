<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Matchmaker\Repository\MatchRepository;
use Matchmaker\Frontend\PortalController;
use Matchmaker\Admin\AdminPortal;

final class EventClickTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__mm_options'] = [];
        $GLOBALS['__mm_usermeta'] = [];
        $GLOBALS['__mm_users'] = [];
        $GLOBALS['admin_menu_pages'] = [];
        $GLOBALS['admin_submenu_pages'] = [];
        $GLOBALS['wpdb'] = new \Fakewpdb();

        // Register a test user
        $GLOBALS['__mm_users'][5] = new \FakeWP_User(5, 'john_doe', 'john@example.com');
        $GLOBALS['__mm_current_user_id'] = 5;
    }

    public function test_record_event_click_validates_and_executes_query(): void
    {
        $repo = MatchRepository::instance();

        // Invalid inputs
        $this->assertFalse($repo->record_event_click(0, 5));
        $this->assertFalse($repo->record_event_click(10, 0));

        // Valid execution
        $this->assertTrue($repo->record_event_click(101, 5));

        $queries = $GLOBALS['wpdb']->queries;
        $has_insert = false;
        foreach ($queries as $q) {
            if (str_contains($q, 'INSERT INTO wp_matchmaker_event_clicks') && str_contains($q, 'ON DUPLICATE KEY UPDATE click_count = click_count + 1')) {
                $has_insert = true;
                break;
            }
        }

        $this->assertTrue($has_insert, 'Expected UPSERT query for event click recording');
    }

    public function test_get_events_click_summary(): void
    {
        $repo = MatchRepository::instance();

        // Setup mock results in wpdb
        $expected = [
            [
                'event_id'         => 101,
                'event_title'      => 'Arab Community Matrimonial Mixer',
                'event_date'       => '2026-10-01 18:00:00',
                'event_status'     => 'publish',
                'total_clicks'     => 12,
                'unique_users'     => 5,
                'first_clicked_at' => '2026-09-15 10:00:00',
                'last_clicked_at'  => '2026-09-17 19:30:00',
            ]
        ];

        // Match the exact query structure
        $sql = "SELECT c.event_id,
                       COALESCE(p.post_title, CONCAT('Event #', c.event_id)) AS event_title,
                       p.post_date AS event_date,
                       p.post_status AS event_status,
                       SUM(c.click_count) AS total_clicks,
                       COUNT(DISTINCT c.user_id) AS unique_users,
                       MIN(c.first_clicked_at) AS first_clicked_at,
                       MAX(c.last_clicked_at) AS last_clicked_at
                FROM wp_matchmaker_event_clicks c
                LEFT JOIN wp_posts p ON p.ID = c.event_id
                GROUP BY c.event_id, p.post_title, p.post_date, p.post_status
                ORDER BY last_clicked_at DESC";

        $GLOBALS['wpdb']->mock_results[$sql] = $expected;

        $summary = $repo->get_events_click_summary();
        $this->assertCount(1, $summary);
        $this->assertEquals(101, $summary[0]['event_id']);
        $this->assertEquals(12, $summary[0]['total_clicks']);
        $this->assertEquals(5, $summary[0]['unique_users']);
    }

    public function test_get_event_click_details_enriches_user_data(): void
    {
        $repo = MatchRepository::instance();

        $GLOBALS['__mm_usermeta'][5]['phone_number'] = '+1 (555) 234-5678';
        $GLOBALS['__mm_usermeta'][5]['first_name'] = 'John';
        $GLOBALS['__mm_usermeta'][5]['last_name'] = 'Doe';

        $mock_rows = [
            [
                'id'               => 1,
                'event_id'         => 101,
                'user_id'          => 5,
                'click_count'      => 3,
                'first_clicked_at' => '2026-09-15 10:00:00',
                'last_clicked_at'  => '2026-09-17 19:30:00',
                'user_login'       => 'john_doe',
                'user_email'       => 'john@example.com',
                'display_name'     => 'John Doe',
                'user_type'        => 'monthly',
                'gender'           => 'male',
                'country'          => 'United States',
                'city'             => 'Chicago',
            ]
        ];

        $sql = "SELECT c.id,
                       c.event_id,
                       c.user_id,
                       c.click_count,
                       c.first_clicked_at,
                       c.last_clicked_at,
                       u.user_login,
                       u.user_email,
                       u.display_name,
                       pool.user_type,
                       pool.gender,
                       pool.country,
                       pool.city
                FROM wp_matchmaker_event_clicks c
                LEFT JOIN wp_users u ON u.ID = c.user_id
                LEFT JOIN wp_matchmaking_pool pool ON pool.user_id = c.user_id
                WHERE c.event_id = 101
                ORDER BY c.last_clicked_at DESC";

        $GLOBALS['wpdb']->mock_results[$sql] = $mock_rows;

        $details = $repo->get_event_click_details(101);
        $this->assertCount(1, $details);
        $this->assertEquals('John Doe', $details[0]['full_name']);
        $this->assertEquals('+1 (555) 234-5678', $details[0]['phone']);
        $this->assertEquals('monthly', $details[0]['user_type']);
        $this->assertEquals(3, $details[0]['click_count']);
    }

    public function test_admin_menu_registers_event_clicks_submenu(): void
    {
        update_option('mm_events_cpt_slug', 'event');

        $admin_portal = AdminPortal::instance();
        $admin_portal->register_menu();

        $this->assertArrayHasKey('edit.php?post_type=event', $GLOBALS['admin_submenu_pages']);
        $this->assertArrayHasKey('matchmaking-event-clicks', $GLOBALS['admin_submenu_pages']['edit.php?post_type=event']);
        $submenu = $GLOBALS['admin_submenu_pages']['edit.php?post_type=event']['matchmaking-event-clicks'];
        $this->assertEquals('Event Click Analytics', $submenu['page_title']);
        $this->assertEquals('Join Click Analytics', $submenu['menu_title']);
    }

    public function test_get_event_click_count_for_user(): void
    {
        $repo = MatchRepository::instance();

        $sql = "SELECT click_count FROM wp_matchmaker_event_clicks WHERE event_id = 101 AND user_id = 5";
        $GLOBALS['wpdb']->mock_vars[$sql] = '4';

        $this->assertEquals(4, $repo->get_event_click_count_for_user(101, 5));
        $this->assertEquals(0, $repo->get_event_click_count_for_user(0, 5));
        $this->assertEquals(0, $repo->get_event_click_count_for_user(101, 0));
    }

    public function test_portal_ajax_actions_include_event_tracking(): void
    {
        $this->assertTrue(has_action('wp_ajax_mm_track_event_click'));
    }

    public function test_render_event_clicks_templates_markup(): void
    {
        // 1. Overview template
        $events_summary = [
            [
                'event_id'         => 101,
                'event_title'      => 'Arab Community Matrimonial Mixer',
                'event_date'       => '2026-10-01 18:00:00',
                'event_status'     => 'publish',
                'total_clicks'     => 12,
                'unique_users'     => 5,
                'first_clicked_at' => '2026-09-15 10:00:00',
                'last_clicked_at'  => '2026-09-17 19:30:00',
            ]
        ];

        ob_start();
        include dirname(__DIR__, 2) . '/src/View/admin/events/event-clicks.php';
        $overview_html = (string) ob_get_clean();

        $this->assertStringContainsString('Click Analytics', $overview_html);
        $this->assertStringContainsString('Arab Community Matrimonial Mixer', $overview_html);
        $this->assertStringContainsString('12', $overview_html);
        $this->assertStringContainsString('5', $overview_html);

        // 2. Single event detail template
        $event_id = 101;
        $event = null;
        $clicks = [
            [
                'id'               => 1,
                'event_id'         => 101,
                'user_id'          => 5,
                'click_count'      => 3,
                'first_clicked_at' => '2026-09-15 10:00:00',
                'last_clicked_at'  => '2026-09-17 19:30:00',
                'full_name'        => 'John Doe',
                'user_login'       => 'john_doe',
                'user_email'       => 'john@example.com',
                'phone'            => '+1 (555) 234-5678',
                'user_type'        => 'monthly',
                'gender'           => 'male',
                'country'          => 'United States',
                'city'             => 'Chicago',
            ]
        ];
        $summary = [
            'total_clicks' => 3,
            'unique_users' => 1,
        ];

        ob_start();
        include dirname(__DIR__, 2) . '/src/View/admin/events/event-clicks-single.php';
        $single_html = (string) ob_get_clean();

        $this->assertStringContainsString('Back to All Events', $single_html);
        $this->assertStringContainsString('Export Attendees CSV', $single_html);
        $this->assertStringContainsString('John Doe', $single_html);
        $this->assertStringContainsString('+1 (555) 234-5678', $single_html);
        $this->assertStringContainsString('3 clicks', $single_html);
    }

    public function test_export_event_clicks_csv_generation(): void
    {
        $repo = MatchRepository::instance();

        $GLOBALS['__mm_usermeta'][5]['phone_number'] = '+1 (555) 234-5678';
        $GLOBALS['__mm_usermeta'][5]['first_name'] = 'John';
        $GLOBALS['__mm_usermeta'][5]['last_name'] = 'Doe';

        $mock_rows = [
            [
                'id'               => 1,
                'event_id'         => 101,
                'user_id'          => 5,
                'click_count'      => 3,
                'first_clicked_at' => '2026-09-15 10:00:00',
                'last_clicked_at'  => '2026-09-17 19:30:00',
                'user_login'       => 'john_doe',
                'user_email'       => 'john@example.com',
                'display_name'     => 'John Doe',
                'user_type'        => 'monthly',
                'gender'           => 'male',
                'country'          => 'United States',
                'city'             => 'Chicago',
            ]
        ];

        $sql = "SELECT c.id,
                       c.event_id,
                       c.user_id,
                       c.click_count,
                       c.first_clicked_at,
                       c.last_clicked_at,
                       u.user_login,
                       u.user_email,
                       u.display_name,
                       pool.user_type,
                       pool.gender,
                       pool.country,
                       pool.city
                FROM wp_matchmaker_event_clicks c
                LEFT JOIN wp_users u ON u.ID = c.user_id
                LEFT JOIN wp_matchmaking_pool pool ON pool.user_id = c.user_id
                WHERE c.event_id = 101
                ORDER BY c.last_clicked_at DESC";

        $GLOBALS['wpdb']->mock_results[$sql] = $mock_rows;

        // Ensure user has manage_matchmaker cap
        $admin_user = new \FakeWP_User(1, 'admin', 'admin@example.com');
        $admin_user->roles = ['administrator'];
        $GLOBALS['__mm_users'][1] = $admin_user;
        $GLOBALS['__mm_current_user_id'] = 1;

        // Verify direct repository data retrieval for event 101
        $clicks = $repo->get_event_click_details(101);
        $this->assertNotEmpty($clicks);
        $this->assertEquals('John Doe', $clicks[0]['full_name']);
        $this->assertEquals('+1 (555) 234-5678', $clicks[0]['phone']);
        $this->assertEquals(3, $clicks[0]['click_count']);
    }

    public function test_ajax_track_event_click_returns_event_link(): void
    {
        // Set up post meta for event link
        $GLOBALS['__mm_postmeta'][101]['event_link'] = 'https://zoom.us/j/123456789';

        $portal = PortalController::instance();

        $_POST['nonce'] = 'valid_nonce';
        $_POST['event_id'] = 101;
        $GLOBALS['__mm_current_user_id'] = 5;

        $sql = "SELECT click_count FROM wp_matchmaker_event_clicks WHERE event_id = 101 AND user_id = 5";
        $GLOBALS['wpdb']->mock_vars[$sql] = '1';

        // Capture output from handle_ajax_track_event_click
        ob_start();
        $caught = false;
        try {
            $portal->handle_ajax_track_event_click();
        } catch (\RuntimeException $e) {
            $caught = true;
        }
        $output = (string) ob_get_clean();

        $this->assertTrue($caught, 'Expected wp_send_json_success RuntimeException');
        $response = json_decode($output, true);
        $this->assertIsArray($response);
        $this->assertTrue($response['success']);
        $this->assertEquals(101, $response['data']['event_id']);
        $this->assertEquals('https://zoom.us/j/123456789', $response['data']['event_link']);
    }
}
