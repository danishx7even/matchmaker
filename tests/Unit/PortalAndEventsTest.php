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
        $GLOBALS['__mm_pmpro_levels'] = [];
    }

    public function test_event_tier_portal_hides_matches_tab(): void
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

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/portal.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('data-tab="profile"', $html);
        $this->assertStringContainsString('data-tab="events"', $html);
        $this->assertStringNotContainsString('data-tab="matches"', $html);
        $this->assertStringNotContainsString('id="mm-tab-matches"', $html);
        $this->assertStringContainsString('id="mm-tab-events"', $html);
    }

    public function test_monthly_tier_portal_shows_matches_tab(): void
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

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/portal.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('data-tab="profile"', $html);
        $this->assertStringContainsString('data-tab="matches"', $html);
        $this->assertStringContainsString('data-tab="events"', $html);
        $this->assertStringContainsString('id="mm-tab-matches"', $html);
        $this->assertStringContainsString('id="mm-tab-events"', $html);
    }

    public function test_ajax_reload_tab_gating_blocks_event_tier_matches(): void
    {
        $controller = PortalController::instance();
        $_POST['nonce'] = wp_create_nonce('mm_portal_nonce');
        $_POST['tab']   = 'matches';

        // Set user 1 as event type
        $GLOBALS['__mm_user_pmpro_level'][1] = 6;
        update_user_meta(1, 'user_type', 'event');
        update_user_meta(1, 'az_user_type', 'event');

        ob_start();
        try {
            $controller->handle_ajax_reload_tab();
        } catch (\Exception $e) {
            // wp_send_json_error throws in mock
        }
        $output = ob_get_clean();
        $response = json_decode($output, true);

        $this->assertFalse($response['success'] ?? true);
        $this->assertStringContainsString('not available for Event memberships', $response['data']['message'] ?? '');
    }

    public function test_ajax_reload_tab_events_returns_rendered_html(): void
    {
        $controller = PortalController::instance();
        $_POST['nonce'] = wp_create_nonce('mm_portal_nonce');
        $_POST['tab']   = 'events';
        $_POST['page']  = 1;

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

        // Native fallback card should contain the resolved thumbnail
        $this->assertStringContainsString('https://example.com/uploads/event-banner.jpg', $html);
        $this->assertStringContainsString('mm-event-thumb', $html);

        unset($GLOBALS['__mm_mock_post_thumbnail']);
    }
}

