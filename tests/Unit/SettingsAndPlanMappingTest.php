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
        $this->assertEquals('one_on_one', $sync->get_user_type_by_level_id(4));
        $this->assertEquals('one_on_one', $sync->get_user_type_by_level_id(5));
        $this->assertEquals('event', $sync->get_user_type_by_level_id(6));
        $this->assertEquals('free', $sync->get_user_type_by_level_id(2));
        $this->assertEquals('free', $sync->get_user_type_by_level_id(999));
    }

    public function test_custom_pmpro_tier_mapping(): void
    {
        $custom_mapping = [
            10 => 'monthly',
            11 => 'one_on_one',
            12 => 'event',
            1  => 'free',
        ];
        update_option('mm_pmpro_tier_mapping', $custom_mapping);

        $sync = PMProSync::instance();
        
        $this->assertEquals('monthly', $sync->get_user_type_by_level_id(10));
        $this->assertEquals('one_on_one', $sync->get_user_type_by_level_id(11));
        $this->assertEquals('event', $sync->get_user_type_by_level_id(12));
        $this->assertEquals('free', $sync->get_user_type_by_level_id(1));
        $this->assertEquals([10], $sync->get_levels_for_tier('monthly'));
        $this->assertEquals(10, $sync->get_primary_level_for_tier('monthly'));
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
}
