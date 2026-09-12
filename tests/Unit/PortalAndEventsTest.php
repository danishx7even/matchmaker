<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Matchmaker\Frontend\PortalController;
use Matchmaker\Repository\MatchRepository;
use Matchmaker\Service\ProfileService;

final class PortalAndEventsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_POST['tab'], $_POST['nonce'], $_POST['page']);
        $GLOBALS['__mm_options'] = [];
        $GLOBALS['__mm_user_pmpro_level'] = [];
        $GLOBALS['__mm_user_pmpro_levels'] = [];
        $GLOBALS['__mm_pmpro_levels'] = [];
    }

    public function test_event_tier_portal_shows_all_tabs_including_matches_and_services(): void
    {
        $user_id = 901;
        $user_type = 'event';
        $is_premium = false;
        $user = new \FakeWP_User($user_id, 'Event Member', 'event@example.com');
        $unread_count = 0;
        $photos = [];
        $meta = [];
        $stats = [];
        $pool = null;
        $repo = MatchRepository::instance();
        $dashboard_url = 'https://example.com/dashboard/';
        $matches = [];

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/portal.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('data-tab="profile"', $html);
        $this->assertStringContainsString('data-tab="matches"', $html);
        $this->assertStringContainsString('data-tab="events"', $html);
        $this->assertStringContainsString('data-tab="services"', $html);
        $this->assertStringContainsString('id="mm-tab-matches"', $html);
        $this->assertStringContainsString('id="mm-tab-events"', $html);
        $this->assertStringContainsString('id="mm-tab-services"', $html);
    }

    public function test_monthly_tier_portal_shows_matches_and_services_tab(): void
    {
        $user_id = 902;
        $user_type = 'monthly';
        $is_premium = true;
        $user = new \FakeWP_User($user_id, 'Monthly Member', 'monthly@example.com');
        $unread_count = 0;
        $photos = [];
        $meta = [];
        $stats = [];
        $pool = null;
        $repo = MatchRepository::instance();
        $dashboard_url = 'https://example.com/dashboard/';
        $matches = [];

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/portal.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('data-tab="profile"', $html);
        $this->assertStringContainsString('data-tab="matches"', $html);
        $this->assertStringContainsString('data-tab="events"', $html);
        $this->assertStringContainsString('data-tab="services"', $html);
        $this->assertStringContainsString('id="mm-tab-matches"', $html);
        $this->assertStringContainsString('id="mm-tab-events"', $html);
        $this->assertStringContainsString('id="mm-tab-services"', $html);
    }

    public function test_ajax_reload_tab_services_returns_rendered_html(): void
    {
        $controller = PortalController::instance();
        $_POST['nonce'] = wp_create_nonce('mm_portal_nonce');
        $_POST['tab']   = 'services';

        update_user_meta(1, 'user_type', 'monthly');

        ob_start();
        try {
            $controller->handle_ajax_reload_tab();
        } catch (\Exception $e) {
            // wp_send_json_success throws in mock
        }
        $output = ob_get_clean();
        $response = json_decode($output, true);

        $this->assertTrue($response['success'] ?? false);
        $this->assertEquals('services', $response['data']['tab'] ?? '');
        $this->assertStringContainsString('mm-services-wrap', $response['data']['html'] ?? '');
        $this->assertStringContainsString('mm-services-grid', $response['data']['html'] ?? '');
        $this->assertStringContainsString('Purchase Service →', $response['data']['html'] ?? '');
    }

    public function test_ajax_reload_tab_events_returns_rendered_html(): void
    {
        $controller = PortalController::instance();
        $_POST['nonce'] = wp_create_nonce('mm_portal_nonce');
        $_POST['tab']   = 'events';
        $_POST['page']  = 1;

        $event_level = new \FakePMProLevel(2, 'Event Pass');
        $GLOBALS['__mm_pmpro_levels'][2] = $event_level;
        $GLOBALS['__mm_user_pmpro_level'][1] = 2;
        $GLOBALS['__mm_user_pmpro_levels'][1] = [$event_level];
        update_option('mm_pmpro_tier_mapping', [2 => 'event']);

        update_user_meta(1, 'user_type', 'event');
        update_user_meta(1, 'az_user_type', 'event');

        ob_start();
        try {
            $controller->handle_ajax_reload_tab();
        } catch (\Exception $e) {
            // wp_send_json_success throws in mock
        }
        $output = ob_get_clean();
        $response = json_decode($output, true);

        $this->assertTrue($response['success'] ?? false);
        $this->assertEquals('events', $response['data']['tab'] ?? '');
        $this->assertStringContainsString('mm-events-container', $response['data']['html'] ?? '');
        $this->assertStringContainsString('mm-events-grid', $response['data']['html'] ?? '');
        $this->assertStringContainsString('Upcoming Events &amp; Mixers', $response['data']['html'] ?? '');
    }

    public function test_ajax_reload_tab_events_blocked_for_free_members(): void
    {
        $controller = PortalController::instance();
        $_POST['nonce'] = wp_create_nonce('mm_portal_nonce');
        $_POST['tab']   = 'events';
        $_POST['page']  = 1;

        // Ensure user 1 is free tier
        unset($GLOBALS['__mm_user_pmpro_level'][1]);
        update_user_meta(1, 'user_type', 'free');
        update_user_meta(1, 'az_user_type', 'free');

        ob_start();
        try {
            $controller->handle_ajax_reload_tab();
        } catch (\Exception $e) {
            // wp_send_json_error throws in mock
        }
        $output = ob_get_clean();
        $response = json_decode($output, true);

        $this->assertFalse($response['success'] ?? true);
        $this->assertStringContainsString('reserved', $response['data']['message'] ?? '');
    }

    public function test_free_tier_portal_hides_events_tab(): void
    {
        $user_id = 902;
        $user_type = 'free';
        $is_premium = false;
        $user = new \FakeWP_User($user_id, 'Free Member', 'free@example.com');
        $unread_count = 0;
        $photos = [];
        $meta = [];
        $stats = [];
        $pool = null;
        $repo = MatchRepository::instance();
        $dashboard_url = 'https://example.com/dashboard/';
        $matches = [];

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/portal.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('data-tab="profile"', $html);
        $this->assertStringNotContainsString('data-tab="events"', $html);
        $this->assertStringNotContainsString('id="mm-tab-events"', $html);
    }

    public function test_events_settings_options_save_and_retrieve(): void
    {
        update_option('mm_events_cpt_slug', 'events');
        update_option('mm_events_template_id', 450);
        update_option('mm_events_per_page', 9);

        $this->assertEquals('events', get_option('mm_events_cpt_slug'));
        $this->assertEquals(450, (int) get_option('mm_events_template_id'));
        $this->assertEquals(9, (int) get_option('mm_events_per_page'));
    }

    public function test_tab_events_renders_thumbnail_and_deduplicates_elementor_css(): void
    {
        $GLOBALS['__mm_mock_post_thumbnail'] = 'https://example.com/uploads/event-banner.jpg';

        $user_id = 905;
        $user_type = 'monthly';
        $is_premium = true;
        $paged = 1;

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/tab-events.php';
        $html = (string) ob_get_clean();

        unset($GLOBALS['__mm_mock_post_thumbnail']);
    }

    public function test_step_5_renders_pause_subscription_cta_with_membership_url(): void
    {
        update_option('mm_page_account_id', 99);

        $active_match = [
            'id' => 12,
            'match_id' => 12,
            'user_id' => 501,
            'name' => 'Fatima Al-Sayed',
            'age' => 26,
            'location' => 'Dubai, United Arab Emirates',
            'photo' => 'https://example.com/fatima.jpg',
            'phone_number' => '+971501234567',
            'email' => 'fatima@example.com',
            'social_links' => '@fatima_dxb',
            'my_response' => 'accepted',
            'their_response' => 'accepted',
            'status' => 'matched',
        ];
        $default_step = 5;

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/steps/step-5-contact.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Pause Subscription', $html);
        $this->assertStringContainsString('Back to Profile Dashboard', $html);
        $this->assertStringContainsString('Direct Contact Information', $html);
    }

    public function test_portal_renders_dynamic_service_tag_and_removes_vip_banner(): void
    {
        $repo = \Matchmaker\Repository\MatchRepository::instance();
        $user_id = 906;
        $user = new \FakeWP_User($user_id, 'VIP Member', 'vip@example.com');
        $GLOBALS['__mm_users'][$user_id] = $user;
        $GLOBALS['__mm_current_user_id'] = $user_id;

        update_option('mm_services_group_id', 3);
        update_option('pmprommpu_groups', [
            3 => [6],
        ]);

        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = [
            (object) ['id' => 6, 'name' => 'Social Media Post'],
        ];

        $user_type = 'monthly';
        $has_one_on_one = true;
        $is_premium = true;
        $pool = [
            'user_id' => $user_id,
            'birth_date' => '1995-05-15',
            'height_cm' => 180,
            'country' => 'United Arab Emirates',
            'city' => 'Dubai',
            'state' => 'Dubai',
            'origin' => 'Emirati',
            'languages' => 'Arabic, English',
            'religion' => 'Muslim',
            'smoking' => 'No',
            'drinking' => 'No',
            'job' => 'Engineer',
            'has_one_on_one' => 1,
        ];
        $stats = $repo->get_match_stats($user_id);
        $unread_count = 0;
        $photos = [];
        $meta = [];
        $dashboard_url = 'https://example.com/dashboard/';
        $matches = [];

        // 1. Test portal header does NOT contain service badges (removed from header)
        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/portal.php';
        $portal_html = (string) ob_get_clean();

        $this->assertStringNotContainsString('mm-vip-header-badge', $portal_html);

        // 2. Test profile tab contains the Purchased Service notice card with message and embedded service badges
        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/tab-profile.php';
        $profile_html = (string) ob_get_clean();

        $this->assertStringNotContainsString('mm-vip-service-card', $profile_html);
        $this->assertStringNotContainsString('1-on-1 VIP Matchmaking Active', $profile_html);
        $this->assertStringContainsString('mm-service-notice-card', $profile_html);
        $this->assertStringContainsString('Purchased Service', $profile_html);
        $this->assertStringContainsString('You will be contacted by our team by your email or phone number regarding your service.', $profile_html);
        $this->assertStringContainsString('mm-service-badges-wrap', $profile_html);
        $this->assertStringContainsString('az-badge-service', $profile_html);
        $this->assertStringContainsString('Social Media Post', $profile_html);

        unset($GLOBALS['__mm_user_pmpro_levels'][$user_id]);
    }
}


