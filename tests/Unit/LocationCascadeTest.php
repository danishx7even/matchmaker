<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Matchmaker\Frontend\FieldGenerator;

final class LocationCascadeTest extends TestCase
{
    public function test_hierarchy_data_loads_countries(): void
    {
        $fg = FieldGenerator::instance();
        $countries = $fg->options_country();

        $this->assertEquals('Select country', $countries[0]);
        $this->assertContains('United States', $countries);
        $this->assertContains('Saudi Arabia', $countries);
        $this->assertContains('Egypt', $countries);
        $this->assertGreaterThan(200, count($countries));
    }

    public function test_user_state_and_city_cascading(): void
    {
        $fg = FieldGenerator::instance();

        // 1. Unknown or empty country returns 'Select state'
        $empty_states = $fg->options_user_state('');
        $this->assertEquals(['Select state'], $empty_states);

        // 2. United States states
        $us_states = $fg->options_user_state('United States');
        $this->assertEquals('Select state', $us_states[0]);
        $this->assertContains('California', $us_states);
        $this->assertContains('New York', $us_states);
        $this->assertContains('Texas', $us_states);

        // 3. California cities
        $ca_cities = $fg->options_user_city('United States', 'California');
        $this->assertEquals('Select city', $ca_cities[0]);
        $this->assertContains('Los Angeles', $ca_cities);
        $this->assertContains('San Francisco', $ca_cities);
    }

    public function test_pref_state_and_city_with_any_options(): void
    {
        $fg = FieldGenerator::instance();

        // 1. Preferred country starts with Any Country
        $pref_countries = $fg->options_pref_country();
        $this->assertEquals('Any Country', $pref_countries[0]);

        // 2. When pref_country is 'Any Country' or empty, pref_state returns ['Any State']
        $any_states = $fg->options_pref_state('Any Country');
        $this->assertEquals(['Any State'], $any_states);

        // 3. When specific country is selected, returns ['Any State', ...states]
        $us_pref_states = $fg->options_pref_state('United States');
        $this->assertEquals('Any State', $us_pref_states[0]);
        $this->assertContains('California', $us_pref_states);

        // 4. When pref_state is 'Any State', pref_city returns ['Any City']
        $any_cities = $fg->options_pref_city('United States', 'Any State');
        $this->assertEquals(['Any City'], $any_cities);

        // 5. When specific state is selected, returns ['Any City', ...cities]
        $ca_pref_cities = $fg->options_pref_city('United States', 'California');
        $this->assertEquals('Any City', $ca_pref_cities[0]);
        $this->assertContains('Los Angeles', $ca_pref_cities);
    }

    public function test_readonly_email_rendering(): void
    {
        $fg = FieldGenerator::instance();
        $html = $fg->render_single_field('email', ['email' => 'user@example.com']);

        $this->assertStringContainsString('readonly', $html);
        $this->assertStringContainsString('is-readonly', $html);
        $this->assertStringContainsString('user@example.com', $html);
    }

    public function test_citizenship_and_pref_citizenship_contain_full_country_list(): void
    {
        $fg = FieldGenerator::instance();

        $citizenships = $fg->options_citizenship();
        $this->assertEquals('Select citizenship', $citizenships[0]);
        $this->assertContains('Saudi Arabia', $citizenships);
        $this->assertContains('United States', $citizenships);
        $this->assertContains('Morocco', $citizenships);
        $this->assertContains('Pakistan', $citizenships);
        $this->assertGreaterThan(200, count($citizenships));

        $pref_citizenships = $fg->options_pref_citizenship();
        $this->assertEquals('Any Citizenship', $pref_citizenships[0]);
        $this->assertContains('Saudi Arabia', $pref_citizenships);
        $this->assertContains('United States', $pref_citizenships);
        $this->assertContains('Morocco', $pref_citizenships);
        $this->assertContains('Pakistan', $pref_citizenships);
        $this->assertGreaterThan(200, count($pref_citizenships));
    }

    public function test_origin_and_pref_origin_contain_ethnicity_data(): void
    {
        $fg = FieldGenerator::instance();

        $origins = $fg->options_origin();
        $this->assertEquals('Select origin', $origins[0]);
        $this->assertContains('Algerian', $origins);
        $this->assertContains('Egyptian', $origins);
        $this->assertContains('Pakistani', $origins);
        $this->assertContains('Saudi, Saudi Arabian', $origins);
        $this->assertContains('Moroccan', $origins);
        $this->assertGreaterThan(50, count($origins));

        $pref_origins = $fg->options_pref_origin();
        $this->assertEquals('Any Origin', $pref_origins[0]);
        $this->assertContains('Algerian', $pref_origins);
        $this->assertContains('Egyptian', $pref_origins);
        $this->assertContains('Pakistani', $pref_origins);
        $this->assertContains('Saudi, Saudi Arabian', $pref_origins);
        $this->assertContains('Moroccan', $pref_origins);
        $this->assertGreaterThan(50, count($pref_origins));
    }

    public function test_searchable_select_markup_rendering(): void
    {
        $fg = FieldGenerator::instance();

        // 1. Country field (user_country) has >5 items, should render search input
        $country_html = $fg->render_single_field('user_country', ['user_country' => 'United States']);
        $this->assertStringContainsString('custom-select-search-wrap', $country_html);
        $this->assertStringContainsString('custom-select-search-input', $country_html);
        $this->assertStringContainsString('has-search', $country_html);

        // 2. Origin field (user_origin) has ethnicity items, should render search input
        $origin_html = $fg->render_single_field('user_origin', ['user_origin' => 'Egyptian']);
        $this->assertStringContainsString('custom-select-search-wrap', $origin_html);
        $this->assertStringContainsString('custom-select-search-input', $origin_html);

        // 3. Citizenship multiselect (pref_citizenship) should render search input
        $pref_cit_html = $fg->render_single_field('pref_citizenship', ['pref_citizenship' => 'Saudi Arabia']);
        $this->assertStringContainsString('custom-select-search-wrap', $pref_cit_html);
        $this->assertStringContainsString('custom-select-search-input', $pref_cit_html);
    }
}


