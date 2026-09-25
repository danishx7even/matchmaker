<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Matchmaker\Core\MatchingEngine;

final class MatchingEngineTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__mm_options'] = [];
        $GLOBALS['__mm_scheduled_jobs'] = [];
        $GLOBALS['wpdb'] = new \Fakewpdb();
    }

    public function test_dynamic_candidate_limits(): void
    {
        $engine = MatchingEngine::instance();

        // Default limit
        $this->assertEquals(10, $engine->get_max_candidates_limit());

        // Custom limit
        update_option('mm_max_candidates_per_run', 15);
        $this->assertEquals(15, $engine->get_max_candidates_limit());
    }

    public function test_compute_flexible_score(): void
    {
        $engine = MatchingEngine::instance();

        $userA = [
            'user_id'              => 1,
            'origin'               => 'Saudi',
            'pref_origin'          => 'Egyptian',
            'languages'            => 'Arabic, English',
            'height_cm'            => 180,
            'preferred_height_min' => 155,
            'preferred_height_max' => 170,
            'job'                  => 'Software Engineer',
            'smoking'              => 'non_smoker',
            'pref_smoking'         => 'non_smoker',
            'drinking'             => 'never',
            'pref_drinking'        => 'never',
        ];

        // Candidate B with 100% full 6-point match
        $candB = [
            'user_id'              => 2,
            'origin'               => 'Egyptian',
            'pref_origin'          => 'Saudi',
            'languages'            => 'Arabic, French',
            'height_cm'            => 165,
            'preferred_height_min' => 175,
            'preferred_height_max' => 190,
            'job'                  => 'Doctor',
            'smoking'              => 'non_smoker',
            'pref_smoking'         => 'non_smoker',
            'drinking'             => 'never',
            'pref_drinking'        => 'never',
        ];

        $score = $engine->compute_flexible_score($userA, $candB);
        $this->assertEquals(6, $score, 'Candidate B should match all 6 flexible scoring dimensions.');

        // Candidate C with partial match
        $candC = [
            'user_id'              => 3,
            'origin'               => 'Moroccan',
            'pref_origin'          => 'Moroccan',
            'languages'            => 'Spanish',
            'height_cm'            => 165,
            'preferred_height_min' => 175,
            'preferred_height_max' => 190,
            'job'                  => '', // no job
            'smoking'              => 'regular_smoker', // does not match pref
            'pref_smoking'         => 'non_smoker',
            'drinking'             => 'never',
            'pref_drinking'        => 'never',
        ];

        $scoreC = $engine->compute_flexible_score($userA, $candC);
        // Only height (1pt) + drinking (1pt) match = 2 pts
        $this->assertEquals(2, $scoreC);

        // Candidate D with 'Any Origin' in pref_origin matches userA's origin
        $candD = $candC;
        $candD['pref_origin'] = 'Any Origin';
        $userA_any = $userA;
        $userA_any['pref_origin'] = 'Any Origin';
        $scoreD = $engine->compute_flexible_score($userA_any, $candD);
        // Height (1pt) + drinking (1pt) + origin (1pt) = 3 pts
        $this->assertEquals(3, $scoreD);
    }

    public function test_field_generator_options_usd_and_no_preference_and_citizenship(): void
    {
        $gen = \Matchmaker\Frontend\FieldGenerator::instance();

        // 1. Verify USD income ranges: user options do NOT have No Preference, pref options DO have No Preference
        $incomes = $gen->options_income();
        $this->assertContains('0-100k USD', $incomes);
        $this->assertContains('100k-500k USD', $incomes);
        $this->assertContains('500k-1million USD', $incomes);
        $this->assertContains('1 million + USD', $incomes);
        $this->assertNotContains('No Preference', $incomes);

        $pref_incomes = $gen->options_pref_income();
        $this->assertContains('No Preference', $pref_incomes);
        $this->assertContains('0-100k USD', $pref_incomes);

        // 2. Verify Step 1 options do NOT contain "No Preference", Step 2 pref options DO contain "No Preference"
        $this->assertNotContains('No Preference', $gen->options_marital());
        $this->assertContains('No Preference', $gen->options_pref_marital());

        $this->assertNotContains('No Preference', $gen->options_children());
        $this->assertContains('No Preference', $gen->options_pref_children());

        $this->assertNotContains('No Preference', $gen->options_education());
        $this->assertContains('No Preference', $gen->options_pref_education());

        $this->assertNotContains('No Preference', $gen->options_religion());
        $this->assertContains('No Preference', $gen->options_pref_religion());

        $this->assertNotContains('No Preference', $gen->options_modesty('female'));
        $this->assertContains('Traditional / Hijab', $gen->options_modesty('female'));
        $this->assertContains('Modest dress / conservative', $gen->options_modesty('female'));
        $this->assertContains('No religious dress / trendy', $gen->options_modesty('female'));

        $this->assertNotContains('No Preference', $gen->options_modesty('male'));
        $this->assertContains('Traditional / Hijab', $gen->options_modesty('male'));
        $this->assertContains('Modest dress / conservative', $gen->options_modesty('male'));
        $this->assertContains('No religious dress / trendy', $gen->options_modesty('male'));

        $this->assertContains('No Preference', $gen->options_pref_modesty('female'));
        $this->assertContains('No Preference', $gen->options_pref_modesty('male'));
        $this->assertContains('Traditional / Hijab', $gen->options_pref_modesty('female'));
        $this->assertContains('Traditional / Hijab', $gen->options_pref_modesty('male'));

        $this->assertNotContains('No Preference', $gen->options_drinking());
        $this->assertContains('No Preference', $gen->options_pref_drinking());

        $this->assertNotContains('No Preference', $gen->options_smoking());
        $this->assertContains('No Preference', $gen->options_pref_smoking());

        $this->assertNotContains('No Preference', $gen->options_prayer());
        $this->assertContains('No Preference', $gen->options_pref_prayer());

        // 3. Verify "Any Citizenship" as first preferred citizenship option
        $pref_citizenship = $gen->options_pref_citizenship();
        $this->assertEquals('Any Citizenship', $pref_citizenship[0]);
        $this->assertContains('Saudi Arabia', $pref_citizenship);
        $this->assertContains('United States', $pref_citizenship);

        // 4. Verify "Any Origin" as first preferred origin option and origin list
        $pref_origin = $gen->options_pref_origin();
        $this->assertEquals('Any Origin', $pref_origin[0]);
        $this->assertContains('Algerian', $pref_origin);
        $this->assertContains('Pakistani', $pref_origin);

        $origin = $gen->options_origin();
        $this->assertEquals('Select origin', $origin[0]);
        $this->assertContains('Algerian', $origin);
        $this->assertContains('Pakistani', $origin);

        // 5. Verify specific country list
        $countries = $gen->options_country();
        $this->assertContains('United States', $countries);
        $this->assertContains('United Kingdom', $countries);
        $this->assertContains('Pakistan', $countries);
        $this->assertContains('Saudi Arabia', $countries);
    }

    public function test_matching_engine_sql_query_evaluates_country_state_city_gates(): void
    {
        global $wpdb;
        $wpdb->queries = [];

        $engine = MatchingEngine::instance();
        
        $user = [
            'user_id'            => 10,
            'gender'             => 'male',
            'pref_gender'        => 'female',
            'birth_date'         => '1992-05-15',
            'preferred_age_min'  => 20,
            'preferred_age_max'  => 35,
            'country'            => 'Saudi Arabia',
            'pref_country'       => 'Saudi Arabia',
            'state'              => 'Riyadh Region',
            'pref_state'         => 'Riyadh Region',
            'city'               => 'Riyadh',
            'pref_city'          => 'Riyadh',
            'religion'           => 'Muslim',
            'pref_religion'      => 'Muslim',
            'modesty'            => 'Hijab',
            'pref_modesty'       => 'Hijab',
            'user_type'          => 'monthly',
        ];

        // Set mock pool return for get_user_pool
        $wpdb->mock_results = [];

        // Reflection to call private query_candidates
        $ref = new \ReflectionClass($engine);
        $method = $ref->getMethod('query_candidates');
        $method->setAccessible(true);
        $candidates = $method->invoke($engine, $user, 32);

        $queries_str = implode("\n", $wpdb->queries);
        $this->assertStringContainsString('c.pref_country', $queries_str);
        $this->assertStringContainsString('c.pref_state', $queries_str);
        $this->assertStringContainsString('c.pref_city', $queries_str);
        $this->assertStringContainsString('c.country', $queries_str);
        $this->assertStringContainsString('c.state', $queries_str);
        $this->assertStringContainsString('c.city', $queries_str);

        // Crucial test: Ensure zero unreplaced %s or %d placeholders remain in the executed SQL
        $this->assertFalse((bool) preg_match('/%[s|d|f]/', $queries_str), 'All SQL placeholders must be completely and accurately bound without leftover placeholders.');
    }

    public function test_query_candidates_placeholder_parity_with_any_preferences(): void
    {
        global $wpdb;
        $wpdb->queries = [];

        $engine = MatchingEngine::instance();

        $user_any = [
            'user_id'            => 15,
            'gender'             => 'female',
            'pref_gender'        => 'any',
            'birth_date'         => '1996-01-01',
            'preferred_age_min'  => 0,
            'preferred_age_max'  => 0,
            'country'            => '',
            'pref_country'       => 'any',
            'state'              => '',
            'pref_state'         => 'any',
            'city'               => '',
            'pref_city'          => 'any',
            'religion'           => '',
            'pref_religion'      => 'No Preference',
            'modesty'            => '',
            'pref_modesty'       => 'No Preference',
            'user_type'          => 'free',
        ];

        $ref = new \ReflectionClass($engine);
        $method = $ref->getMethod('query_candidates');
        $method->setAccessible(true);
        $method->invoke($engine, $user_any, 28);

        $last_query = end($wpdb->queries) ?: '';
        $this->assertNotEmpty($last_query);
        $this->assertFalse((bool) preg_match('/%[s|d|f]/', $last_query), 'SQL with "any" preferences must have 100% placeholder parity.');
    }

    public function test_run_matching_for_user_creates_matches_end_to_end(): void
    {
        global $wpdb;
        $wpdb->queries = [];

        $user_id = 101;
        $user_data = [
            'user_id'              => $user_id,
            'gender'               => 'male',
            'pref_gender'          => 'female',
            'birth_date'           => '1990-01-01',
            'preferred_age_min'    => 20,
            'preferred_age_max'    => 35,
            'country'              => 'United States',
            'pref_country'         => 'United States',
            'state'                => 'California',
            'pref_state'           => 'California',
            'city'                 => 'Los Angeles',
            'pref_city'            => 'Los Angeles',
            'religion'             => 'Muslim',
            'pref_religion'        => 'Muslim',
            'modesty'              => 'Modest dress / conservative',
            'pref_modesty'         => 'Modest dress / conservative',
            'origin'               => 'Arab',
            'pref_origin'          => 'Arab',
            'languages'            => 'Arabic, English',
            'height_cm'            => 180,
            'preferred_height_min' => 155,
            'preferred_height_max' => 170,
            'job'                  => 'Engineer',
            'smoking'              => 'non_smoker',
            'pref_smoking'         => 'non_smoker',
            'drinking'             => 'never',
            'pref_drinking'        => 'never',
            'user_type'            => 'monthly',
            'is_active'            => 1,
        ];

        $candidate_id = 102;
        $candidate_data = [
            'user_id'              => $candidate_id,
            'gender'               => 'female',
            'pref_gender'          => 'male',
            'birth_date'           => '1994-06-01',
            'preferred_age_min'    => 25,
            'preferred_age_max'    => 40,
            'country'              => 'United States',
            'pref_country'         => 'United States',
            'state'                => 'California',
            'pref_state'           => 'California',
            'city'                 => 'Los Angeles',
            'pref_city'            => 'Los Angeles',
            'religion'             => 'Muslim',
            'pref_religion'        => 'Muslim',
            'modesty'              => 'Modest dress / conservative',
            'pref_modesty'         => 'Modest dress / conservative',
            'origin'               => 'Arab',
            'pref_origin'          => 'Arab',
            'languages'            => 'Arabic, English',
            'height_cm'            => 165,
            'preferred_height_min' => 175,
            'preferred_height_max' => 185,
            'job'                  => 'Designer',
            'smoking'              => 'non_smoker',
            'pref_smoking'         => 'non_smoker',
            'drinking'             => 'never',
            'pref_drinking'        => 'never',
            'user_type'            => 'monthly',
            'is_active'            => 1,
        ];

        // Mock pool table query for get_user_pool
        $pool_table = $wpdb->prefix . 'matchmaking_pool';
        $user_pool_sql = $wpdb->prepare("SELECT * FROM {$pool_table} WHERE user_id = %d", $user_id);
        $wpdb->mock_rows[$user_pool_sql] = $user_data;

        $engine = MatchingEngine::instance();
        $user_age = (int) (new \DateTime())->diff(new \DateTime($user_data['birth_date']))->y;

        // Query candidates through reflection to get the exact prepared SQL key
        $ref = new \ReflectionClass($engine);
        $method = $ref->getMethod('query_candidates');
        $method->setAccessible(true);
        $method->invoke($engine, $user_data, $user_age);

        $cand_sql = end($wpdb->queries);
        $wpdb->mock_results[$cand_sql] = [$candidate_data];

        // Also mock pair uniqueness check
        $matches_table = $wpdb->prefix . 'matches';
        $u1 = min($user_id, $candidate_id);
        $u2 = max($user_id, $candidate_id);
        $exists_sql = $wpdb->prepare("SELECT id FROM {$matches_table} WHERE user_one_id = %d AND user_two_id = %d LIMIT 1", $u1, $u2);
        $wpdb->mock_vars[$exists_sql] = null;

        // Run matching for user
        $engine->run_matching_for_user($user_id, 'form_submit');

        // Verify that INSERT INTO wp_matches was executed
        $inserted_matches = array_filter($wpdb->queries, static fn($q) => str_starts_with(trim($q), "INSERT INTO {$matches_table}"));
        $this->assertNotEmpty($inserted_matches, 'Matching run must insert a new pair into wp_matches.');

        $insert_q = reset($inserted_matches);
        $this->assertStringContainsString((string)$u1, $insert_q);
        $this->assertStringContainsString((string)$u2, $insert_q);
        $this->assertStringContainsString('pending_review', $insert_q);
    }
}
