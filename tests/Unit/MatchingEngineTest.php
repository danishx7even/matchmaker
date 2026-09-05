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

        $this->assertNotContains('No Preference', $gen->options_modesty());
        $this->assertContains('No Preference', $gen->options_pref_modesty());

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

        // 4. Verify specific country list
        $countries = $gen->options_country();
        $this->assertContains('United States', $countries);
        $this->assertContains('United Kingdom', $countries);
        $this->assertContains('Pakistan', $countries);
        $this->assertContains('Saudi Arabia', $countries);
    }
}
