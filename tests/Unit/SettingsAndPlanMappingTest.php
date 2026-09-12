<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Matchmaker\Core\PMProSync;
use Matchmaker\Core\FreeRegHandler;
use Matchmaker\Service\ProfileService;

final class SettingsAndPlanMappingTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__mm_options'] = [];
        $GLOBALS['__mm_usermeta'] = [];
        $GLOBALS['__mm_users'] = [];
    }

    public function test_default_tier_mapping_fallback(): void
    {
        $sync = PMProSync::instance();
        
        $this->assertEquals('monthly', $sync->get_user_type_by_level_id(3));
        $this->assertEquals('event', $sync->get_user_type_by_level_id(6));
        $this->assertEquals('free', $sync->get_user_type_by_level_id(2));
        $this->assertEquals('free', $sync->get_user_type_by_level_id(999));
        $this->assertEquals(3, $sync->get_services_group_id());
        $this->assertTrue($sync->is_service_level(4));
        $this->assertTrue($sync->is_service_level(5));
    }

    public function test_custom_pmpro_tier_mapping_and_level_tags(): void
    {
        $custom_mapping = [
            10 => 'monthly',
            12 => 'event',
            1  => 'free',
        ];
        update_option('mm_pmpro_tier_mapping', $custom_mapping);
        update_option('mm_pmpro_level_tags', [
            10 => 'Featured Monthly',
            4  => 'VIP 1-on-1',
        ]);
        update_option('mm_services_group_id', 5);

        $sync = PMProSync::instance();
        
        $this->assertEquals('monthly', $sync->get_user_type_by_level_id(10));
        $this->assertEquals('event', $sync->get_user_type_by_level_id(12));
        $this->assertEquals('free', $sync->get_user_type_by_level_id(1));
        $this->assertEquals([10], $sync->get_levels_for_tier('monthly'));
        $this->assertEquals(10, $sync->get_primary_level_for_tier('monthly'));
        $this->assertEquals('Featured Monthly', $sync->get_level_tag(10));
        $this->assertEquals('VIP 1-on-1', $sync->get_level_tag(4));
        $this->assertEquals(5, $sync->get_services_group_id());
    }

    public function test_service_checkout_gating_blocks_user_without_basic_membership(): void
    {
        $sync = PMProSync::instance();

        // 1. Guest user attempting to checkout service level 4 -> should be blocked
        $GLOBALS['__mm_current_user_id'] = 0;
        $_REQUEST['level'] = 4;
        $result = $sync->check_service_requires_basic_membership(true);
        $this->assertFalse($result);

        // 2. Logged in member with Free tier -> should be allowed
        $GLOBALS['__mm_current_user_id'] = 801;
        $GLOBALS['__mm_user_pmpro_levels'][801] = [
            new \FakePMProLevel(2, 'Free Membership'),
        ];
        $result = $sync->check_service_requires_basic_membership(true);
        $this->assertTrue($result);

        // 3. Checkout non-service level (e.g. Monthly level 3) -> should not be gated
        $_REQUEST['level'] = 3;
        $GLOBALS['__mm_current_user_id'] = 0;
        $result = $sync->check_service_requires_basic_membership(true);
        $this->assertTrue($result);

        unset($_REQUEST['level']);
        unset($GLOBALS['__mm_current_user_id']);
    }

    public function test_dynamic_page_url_resolvers(): void
    {
        $profile_service = ProfileService::instance();

        // Default URLs without configured page IDs
        $this->assertEquals('https://example.com/dashboard/', $profile_service->get_dashboard_url());
        $this->assertEquals('https://example.com/personal-matchmaking-questionnaire/', $profile_service->get_form_url());
        $this->assertEquals('https://example.com/events-2/', $profile_service->get_events_url());

        // With configured page IDs
        update_option('mm_page_dashboard_id', 42);
        update_option('mm_page_questionnaire_id', 43);
        update_option('mm_page_checkout_id', 44);

        $this->assertEquals('https://example.com/?page_id=42', $profile_service->get_dashboard_url());
        $this->assertEquals('https://example.com/?page_id=43', $profile_service->get_form_url());
        $this->assertStringContainsString('page_id=44', $profile_service->get_membership_checkout_url(3));
    }

    public function test_elementor_free_registration_form_id_matching(): void
    {
        $handler = FreeRegHandler::instance();

        // Default ID '2784843'
        $this->assertTrue($handler->matches_form_id('2784843'));
        $this->assertFalse($handler->matches_form_id('9999999'));

        // Custom comma-separated IDs
        update_option('mm_free_reg_form_id', '12345, 67890');
        $this->assertTrue($handler->matches_form_id('12345'));
        $this->assertTrue($handler->matches_form_id('67890'));
        $this->assertFalse($handler->matches_form_id('2784843'));
    }

    public function test_pmpro_sync_all_membership_levels_handles_array_and_int(): void
    {
        $sync = PMProSync::instance();
        $user_id = 701;

        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = [
            new \FakePMProLevel(3, 'Monthly Matchmaking'),
        ];

        // 1. Array format as passed by PMPro core do_action('pmpro_after_all_membership_level_changes', $old_levels_by_user)
        $pmpro_hook_data = [
            $user_id => [2], // user 701 had old level 2
        ];
        $sync->sync_all_membership_levels($pmpro_hook_data);
        $this->assertEquals('monthly', get_user_meta($user_id, 'user_type', true));

        // 2. Direct int format with 1-on-1 VIP add-on
        $user_id_2 = 702;
        $GLOBALS['__mm_user_pmpro_levels'][$user_id_2] = [
            new \FakePMProLevel(4, 'VIP 1-on-1 Matchmaking'),
        ];
        $sync->sync_all_membership_levels($user_id_2);
        // Base tier is free, but 1-on-1 VIP flag is active
        $this->assertEquals('free', get_user_meta($user_id_2, 'user_type', true));
        $this->assertEquals(1, get_user_meta($user_id_2, 'mm_has_one_on_one', true));
        $this->assertTrue($sync->has_active_one_on_one_service($user_id_2));
    }

    public function test_pmpro_membership_cancellation_downgrades_user_type_to_free(): void
    {
        $sync = PMProSync::instance();
        $user_id = 703;

        // User starts with Monthly membership
        pmpro_changeMembershipLevel(3, $user_id);
        $sync->sync_pmpro_level_to_user_type(3, $user_id, 2);
        $this->assertEquals('monthly', get_user_meta($user_id, 'user_type', true));

        // User cancels membership (level 0)
        pmpro_changeMembershipLevel(0, $user_id);
        $sync->sync_pmpro_level_to_user_type(0, $user_id, 3);

        $this->assertEquals('free', get_user_meta($user_id, 'user_type', true));
        $this->assertEquals('free', ProfileService::instance()->get_user_type($user_id));
        $this->assertEquals('free', $sync->get_current_user_type($user_id));
    }

    public function test_pmpro_membership_downgrade_updates_user_type(): void
    {
        $sync = PMProSync::instance();
        $user_id = 704;

        // User starts with Monthly membership (level 3)
        pmpro_changeMembershipLevel(3, $user_id);
        $sync->sync_pmpro_level_to_user_type(3, $user_id, 0);
        $this->assertEquals('monthly', get_user_meta($user_id, 'user_type', true));

        // User changes plan to Event Single Pass (level 6)
        pmpro_changeMembershipLevel(6, $user_id);
        $sync->sync_pmpro_level_to_user_type(6, $user_id, 3);
        $this->assertEquals('event', get_user_meta($user_id, 'user_type', true));
        $this->assertEquals('event', ProfileService::instance()->get_user_type($user_id));
    }

    public function test_pmpro_expiry_sync_downgrades_to_free(): void
    {
        $sync = PMProSync::instance();
        $user_id = 705;

        // User starts with monthly membership
        pmpro_changeMembershipLevel(3, $user_id);
        $sync->sync_pmpro_level_to_user_type(3, $user_id, 0);
        $this->assertEquals('monthly', get_user_meta($user_id, 'user_type', true));

        // Membership expires
        pmpro_changeMembershipLevel(0, $user_id);
        $sync->handle_expiry_sync($user_id, 3);

        $this->assertEquals('free', get_user_meta($user_id, 'user_type', true));
        $this->assertEquals('free', ProfileService::instance()->get_user_type($user_id));
    }

    public function test_one_on_one_coexists_with_free_and_monthly_tiers(): void
    {
        $sync = PMProSync::instance();
        $user_id = 706;

        // 1. Free (Group 1) + 1-on-1 (Group 3)
        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = [
            new \FakePMProLevel(2, 'Free Membership'),
            new \FakePMProLevel(4, '1-on-1 VIP Matchmaking'),
        ];
        $sync->sync_all_membership_levels($user_id);

        $this->assertEquals('free', $sync->get_current_user_type($user_id));
        $this->assertTrue($sync->has_active_one_on_one_service($user_id));
        $this->assertEquals('free', get_user_meta($user_id, 'user_type', true));
        $this->assertEquals(1, get_user_meta($user_id, 'mm_has_one_on_one', true));

        // 2. User buys Monthly (Group 2) -> cancels Free (Group 1), but retains 1-on-1 (Group 3)
        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = [
            new \FakePMProLevel(2, 'Free Membership'),
            new \FakePMProLevel(3, 'Monthly Membership'),
            new \FakePMProLevel(4, '1-on-1 VIP Matchmaking'),
        ];
        $sync->sync_all_membership_levels($user_id);

        $this->assertEquals('monthly', $sync->get_current_user_type($user_id));
        $this->assertTrue($sync->has_active_one_on_one_service($user_id));
        $this->assertEquals('monthly', get_user_meta($user_id, 'user_type', true));
        $this->assertEquals(1, get_user_meta($user_id, 'mm_has_one_on_one', true));

        // Free level (2) should have been cancelled
        $active_level_ids = array_map(static fn($lvl) => (int) $lvl->id, $GLOBALS['__mm_user_pmpro_levels'][$user_id]);
        $this->assertNotContains(2, $active_level_ids);
        $this->assertContains(3, $active_level_ids);
        $this->assertContains(4, $active_level_ids);
    }

    public function test_dynamic_services_levels_discovery(): void
    {
        global $wpdb;
        $sync = PMProSync::instance();

        // 1. Dynamic discovery via options (pmpro_groups / pmprommpu_groups)
        update_option('mm_services_group_id', 3);
        update_option('pmprommpu_groups', [
            3 => [4, 5, 25, 26],
        ]);

        $services = $sync->get_services_levels();
        $service_ids = array_map(static fn($s) => is_object($s) ? (int) $s->id : (int) $s, $services);

        $this->assertContains(4, $service_ids);
        $this->assertContains(5, $service_ids);
        $this->assertContains(25, $service_ids);
        $this->assertContains(26, $service_ids);
        $this->assertTrue($sync->is_service_level(25));
        $this->assertTrue($sync->is_service_level(26));

        // 2. Dynamic discovery via custom tier mapping ('service')
        update_option('pmprommpu_groups', []);
        update_option('mm_pmpro_tier_mapping', [
            3  => 'monthly',
            2  => 'free',
            30 => 'service',
        ]);

        $services2 = $sync->get_services_levels();
        $service_ids2 = array_map(static fn($s) => is_object($s) ? (int) $s->id : (int) $s, $services2);
        $this->assertContains(30, $service_ids2);
        $this->assertTrue($sync->is_service_level(30));
    }

    public function test_get_user_active_services_returns_dynamic_tags_and_names(): void
    {
        $sync = PMProSync::instance();

        update_option('mm_services_group_id', 3);
        update_option('pmprommpu_groups', [
            3 => [4, 6],
        ]);
        update_option('mm_pmpro_level_tags', [
            6 => 'Featured Post Tag',
        ]);

        $user_id = 88;
        // Mock PMPro functions level objects
        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = [
            (object) ['id' => 6, 'name' => 'Social Media Post'],
        ];

        $user_services = $sync->get_user_active_services($user_id);
        $this->assertCount(1, $user_services);
        $this->assertEquals(6, $user_services[0]['id']);
        $this->assertEquals('Social Media Post', $user_services[0]['name']);
        $this->assertEquals('Featured Post Tag', $user_services[0]['tag']);

        // Test fallback to level name if no custom tag configured
        update_option('mm_pmpro_level_tags', []);
        $user_services2 = $sync->get_user_active_services($user_id);
        $this->assertEquals('Social Media Post', $user_services2[0]['tag']);

        unset($GLOBALS['__mm_user_pmpro_levels'][$user_id]);
    }

    public function test_service_repeat_checkout_filters_bypass_duplicate_checks(): void
    {
        $sync = PMProSync::instance();

        update_option('mm_services_group_id', 3);
        update_option('pmprommpu_groups', [
            3 => [6],
        ]);

        $user_id = 99;

        // In checkout context with a service level ID
        $_REQUEST['level'] = 6;
        $_POST['submit-checkout'] = '1';

        $has_level = true;
        $filtered = $sync->filter_pmpro_has_membership_level_for_checkout($has_level, $user_id, 6);
        $this->assertFalse($filtered, 'Should return false during service checkout to allow repeat purchase');

        // Outside checkout context or non-service level
        unset($_REQUEST['level'], $_POST['submit-checkout']);
        $filtered_non_checkout = $sync->filter_pmpro_has_membership_level_for_checkout($has_level, $user_id, 2);
        $this->assertTrue($filtered_non_checkout);
    }

    public function test_service_purchase_keeps_free_membership_intact(): void
    {
        $sync = PMProSync::instance();
        $user_id = 112;

        update_option('mm_services_group_id', 3);
        update_option('pmprommpu_groups', [
            3 => [6],
        ]);

        // User has Free membership (level 2) and purchases service (level 6)
        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = [
            new \FakePMProLevel(2, 'Free Membership'),
            new \FakePMProLevel(6, 'Social Media Post'),
        ];

        $sync->sync_pmpro_level_to_user_type(6, $user_id);

        $this->assertEquals('free', $sync->get_current_user_type($user_id));
        $this->assertTrue($sync->has_active_one_on_one_service($user_id));
        $this->assertTrue(pmpro_hasMembershipLevel(2, $user_id), 'Free membership must remain intact when service is purchased');

        unset($GLOBALS['__mm_user_pmpro_levels'][$user_id]);
    }

    public function test_active_services_block_base_membership_cancellation(): void
    {
        $sync = PMProSync::instance();
        $user_id = 113;

        update_option('mm_services_group_id', 3);
        update_option('pmprommpu_groups', [
            3 => [6],
        ]);

        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = [
            new \FakePMProLevel(3, 'Monthly Matchmaking'),
            new \FakePMProLevel(6, 'Social Media Post'),
        ];

        // Attempt to cancel base tier (level 3) while service (level 6) is active
        $cancel_base_result = pmpro_cancelMembershipLevel(3, $user_id);
        $this->assertFalse($cancel_base_result, 'Base membership cancellation must be blocked while services are active');
        $this->assertTrue(pmpro_hasMembershipLevel(3, $user_id), 'Base membership must still be present');

        // Cancelling the service level (level 6) itself is permitted
        $cancel_service_result = pmpro_cancelMembershipLevel(6, $user_id);
        $this->assertTrue($cancel_service_result, 'Service level cancellation should be allowed');
        $this->assertFalse(pmpro_hasMembershipLevel(6, $user_id), 'Service level should now be cancelled');

        // Now that no services remain, base membership cancellation is permitted
        $cancel_base_now = pmpro_cancelMembershipLevel(3, $user_id);
        $this->assertTrue($cancel_base_now, 'Base membership cancellation allowed once services are gone');

        unset($GLOBALS['__mm_user_pmpro_levels'][$user_id]);
    }

    public function test_sync_all_users_user_types_consolidates_strictly_to_three_base_tiers(): void
    {
        global $wpdb;
        $sync = PMProSync::instance();

        $u1 = 801; // User with Monthly membership
        $u2 = 802; // User with Event membership
        $u3 = 803; // User with Free membership + 1-on-1 Service
        $u4 = 804; // User with legacy 'one_on_one' user_type meta

        $wpdb->users = 'wp_users';
        $GLOBALS['__mm_user_pmpro_levels'][$u1] = [new \FakePMProLevel(3, 'Monthly Matchmaking')];
        $GLOBALS['__mm_user_pmpro_levels'][$u2] = [new \FakePMProLevel(6, 'Event Access')];
        $GLOBALS['__mm_user_pmpro_levels'][$u3] = [new \FakePMProLevel(2, 'Free Membership'), new \FakePMProLevel(4, '1-on-1 VIP Matchmaking')];
        
        $wpdb->mock_cols['SELECT ID FROM wp_users'] = [$u1, $u2, $u3, $u4];

        update_user_meta($u4, 'user_type', 'one_on_one');
        update_user_meta($u4, 'mm_has_one_on_one', 1);

        $synced_count = $sync->sync_all_users_user_types();
        $this->assertEquals(4, $synced_count);

        // Verify all 4 users are strictly 'monthly', 'event', or 'free'
        $this->assertEquals('monthly', get_user_meta($u1, 'user_type', true));
        $this->assertEquals('event', get_user_meta($u2, 'user_type', true));
        $this->assertEquals('free', get_user_meta($u3, 'user_type', true));
        $this->assertEquals('free', get_user_meta($u4, 'user_type', true));

        $this->assertEquals(1, get_user_meta($u3, 'mm_has_one_on_one', true));
        $this->assertEquals(1, get_user_meta($u4, 'mm_has_one_on_one', true));

        unset($GLOBALS['__mm_user_pmpro_levels'][$u1], $GLOBALS['__mm_user_pmpro_levels'][$u2], $GLOBALS['__mm_user_pmpro_levels'][$u3]);
    }

    public function test_locked_profile_when_user_has_only_service_without_base_membership(): void
    {
        $sync = PMProSync::instance();
        $user_id = 901;

        // User has only service (level 4) and NO base membership (level 1, 2, 3)
        $GLOBALS['__mm_user_pmpro_levels'][$user_id] = [
            new \FakePMProLevel(4, '1-on-1 VIP Matchmaking'),
        ];

        $this->assertTrue($sync->has_active_one_on_one_service($user_id));
        $this->assertFalse($sync->has_active_base_membership($user_id));

        // Test locked profile screen rendering
        $portal_ctrl = \Matchmaker\Frontend\PortalController::instance();
        $locked_html = $portal_ctrl->render_locked_profile_screen($user_id);

        $this->assertStringContainsString('Base Membership Required', $locked_html);
        $this->assertStringContainsString('Active Add-on Service:', $locked_html);
        $this->assertStringContainsString('1-on-1 VIP Matchmaking', $locked_html);
        $this->assertStringContainsString('Select a Membership Plan', $locked_html);

        unset($GLOBALS['__mm_user_pmpro_levels'][$user_id]);
    }
}

