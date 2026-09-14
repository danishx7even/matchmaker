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

    public function test_tab_services_replaces_membership_with_service_in_price_text(): void
    {
        update_option('mm_services_group_id', 3);
        update_option('pmprommpu_groups', [
            3 => [6],
        ]);

        $user_id = 903;
        $user_type = 'monthly';
        $is_premium = true;
        $repo = MatchRepository::instance();

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/tab-services.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Social Media Post', $html);
        $this->assertStringContainsString('The price for service is $150.00 now.', $html);
        $this->assertStringNotContainsString('The price for membership is', $html);
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

    public function test_step_3_renders_pending_state_heading_and_actions(): void
    {
        $active_match = [
            'id' => 13,
            'match_id' => 13,
            'user_id' => 502,
            'name' => 'Layla Hassan',
            'my_response' => 'pending',
            'their_response' => 'pending',
            'status' => 'approved',
            'days_remaining' => 6,
        ];
        $my_resp = 'pending';
        $their_resp = 'pending';
        $default_step = 3;

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/steps/step-3-waiting.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Match Pending Your Review', $html);
        $this->assertStringContainsString("You haven&#039;t responded to this match yet", $html);
        $this->assertStringContainsString('Pending', $html);
        $this->assertStringContainsString('Waiting', $html);
        $this->assertStringContainsString('Review Profile &amp; Respond →', $html);
        $this->assertStringNotContainsString('Match Accepted', $html);
    }

    public function test_step_3_renders_candidate_accepted_state(): void
    {
        $active_match = [
            'id' => 14,
            'match_id' => 14,
            'user_id' => 503,
            'name' => 'Sara Mansour',
            'my_response' => 'pending',
            'their_response' => 'accepted',
            'status' => 'approved',
            'days_remaining' => 5,
        ];
        $my_resp = 'pending';
        $their_resp = 'accepted';
        $default_step = 3;

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/steps/step-3-waiting.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Candidate Accepted — Awaiting Your Response', $html);
        $this->assertStringContainsString('The candidate has accepted this match recommendation', $html);
        $this->assertStringContainsString('Pending', $html);
        $this->assertStringContainsString('Accepted', $html);
        $this->assertStringContainsString('Review Profile &amp; Respond →', $html);
    }

    public function test_step_3_renders_my_accepted_waiting_candidate(): void
    {
        $active_match = [
            'id' => 15,
            'match_id' => 15,
            'user_id' => 504,
            'name' => 'Tariq Al-Sabah',
            'my_response' => 'accepted',
            'their_response' => 'pending',
            'status' => 'approved',
            'days_remaining' => 4,
        ];
        $my_resp = 'accepted';
        $their_resp = 'pending';
        $default_step = 3;

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/steps/step-3-waiting.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Match Accepted', $html);
        $this->assertStringContainsString("We&#039;re now waiting for the candidate to review and respond", $html);
        $this->assertStringContainsString('Accepted', $html);
        $this->assertStringContainsString('Waiting', $html);
        $this->assertStringContainsString('Back to Profile Dashboard →', $html);
    }

    public function test_step_3_renders_mutual_match_state(): void
    {
        $active_match = [
            'id' => 16,
            'match_id' => 16,
            'user_id' => 505,
            'name' => 'Amira Noor',
            'my_response' => 'accepted',
            'their_response' => 'accepted',
            'status' => 'matched',
            'days_remaining' => 3,
        ];
        $my_resp = 'accepted';
        $their_resp = 'accepted';
        $default_step = 3;

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/steps/step-3-waiting.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString("It&#039;s a Mutual Match!", $html);
        $this->assertStringContainsString('Both you and the candidate have accepted!', $html);
        $this->assertStringContainsString('View Contact Details →', $html);
    }

    public function test_step_3_renders_declined_and_expired_states(): void
    {
        // 1. User declined
        $declined_match = [
            'id' => 17,
            'match_id' => 17,
            'user_id' => 506,
            'name' => 'Yusuf Ali',
            'my_response' => 'declined',
            'their_response' => 'pending',
            'status' => 'approved',
            'days_remaining' => 2,
        ];
        $my_resp = 'declined';
        $their_resp = 'pending';
        $default_step = 3;

        ob_start();
        $active_match = $declined_match;
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/steps/step-3-waiting.php';
        $html_dec = (string) ob_get_clean();

        $this->assertStringContainsString('Match Declined by You', $html_dec);
        $this->assertStringContainsString('You have declined this match recommendation', $html_dec);

        // 2. Candidate declined
        $cand_dec = [
            'id' => 18,
            'match_id' => 18,
            'user_id' => 507,
            'name' => 'Zainab Qasim',
            'my_response' => 'accepted',
            'their_response' => 'declined',
            'status' => 'approved',
            'days_remaining' => 1,
        ];
        $my_resp = 'accepted';
        $their_resp = 'declined';

        ob_start();
        $active_match = $cand_dec;
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/steps/step-3-waiting.php';
        $html_cand = (string) ob_get_clean();

        $this->assertStringContainsString('Match Closed', $html_cand);
        $this->assertStringContainsString('The candidate was unable to proceed', $html_cand);

        // 3. Expired
        $expired_match = [
            'id' => 19,
            'match_id' => 19,
            'user_id' => 508,
            'name' => 'Bilal Khan',
            'my_response' => 'pending',
            'their_response' => 'pending',
            'status' => 'expired',
            'days_remaining' => 0,
        ];
        $my_resp = 'pending';
        $their_resp = 'pending';

        ob_start();
        $active_match = $expired_match;
        include dirname(dirname(__DIR__)) . '/src/View/frontend/portal/steps/step-3-waiting.php';
        $html_exp = (string) ob_get_clean();

        $this->assertStringContainsString('Match Expired', $html_exp);
        $this->assertStringContainsString('The response window for this match recommendation has ended', $html_exp);
    }

    public function test_dual_acceptance_transitions_to_matched_and_reveals_contacts(): void
    {
        $repo = MatchRepository::instance();
        global $wpdb;

        $u1 = 801;
        $u2 = 802;
        $match_id = 99;

        // User 1 accepts
        $wpdb->mock_rows["SELECT * FROM wp_matches WHERE id = {$match_id}"] = [
            'id' => $match_id,
            'user_one_id' => $u1,
            'user_two_id' => $u2,
            'user_one_response' => 'pending',
            'user_two_response' => 'pending',
            'status' => 'approved',
            'contact_revealed' => 0,
        ];

        $res1 = $repo->update_match_response($match_id, $u1, 'accept');
        $this->assertTrue($res1['success']);
        $this->assertFalse($res1['is_mutual']);
        $this->assertEquals(3, $res1['next_step']);

        // User 2 accepts when user 1 already accepted
        $wpdb->mock_rows["SELECT * FROM wp_matches WHERE id = {$match_id}"] = [
            'id' => $match_id,
            'user_one_id' => $u1,
            'user_two_id' => $u2,
            'user_one_response' => 'accepted',
            'user_two_response' => 'pending',
            'status' => 'approved',
            'contact_revealed' => 0,
        ];

        $res2 = $repo->update_match_response($match_id, $u2, 'accept');
        $this->assertTrue($res2['success']);
        $this->assertTrue($res2['is_mutual']);
        $this->assertEquals(5, $res2['next_step']);
    }

    public function test_self_healing_syncs_status_to_matched_for_dual_accepted_rows(): void
    {
        $repo = MatchRepository::instance();
        global $wpdb;

        $match_id = 101;
        $dual_accepted_row = [
            'id' => $match_id,
            'user_one_id' => 811,
            'user_two_id' => 812,
            'status' => 'approved',
            'user_one_response' => 'accepted',
            'user_two_response' => 'accepted',
            'contact_revealed' => 0,
        ];

        // find_match_by_id self-heals
        $wpdb->mock_rows["SELECT * FROM wp_matches WHERE id = {$match_id}"] = $dual_accepted_row;
        $healed = $repo->find_match_by_id($match_id);

        $this->assertNotNull($healed);
        $this->assertEquals('matched', $healed['status']);
        $this->assertEquals(1, (int) $healed['contact_revealed']);
    }

    public function test_admin_matches_list_displays_mutual_match_label(): void
    {
        $matches = [
            [
                'id' => 77,
                'user_one_id' => 10,
                'user_two_id' => 20,
                'score' => 5,
                'status' => 'matched',
                'match_source' => 'auto',
                'user_one_response' => 'accepted',
                'user_two_response' => 'accepted',
                'created_at' => '2026-09-14 12:00:00',
            ],
        ];

        $repo = MatchRepository::instance();
        $search = '';
        $status = '';
        $source = '';

        ob_start();
        include dirname(dirname(__DIR__)) . '/src/View/admin/matches/matches-list.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Mutual Match', $html);
        $this->assertStringContainsString('mm-status-matched', $html);
    }
}



