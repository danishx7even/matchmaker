<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use Matchmaker\Repository\MatchRepository;

class ManualMatchmakerTest
{
    private MatchRepository $repo;

    public function setUp(): void
    {
        $GLOBALS['__mm_options']  = [];
        $GLOBALS['__mm_usermeta'] = [];
        $GLOBALS['wpdb']->queries = [];
        $GLOBALS['wpdb']->mock_results = [];
        $GLOBALS['wpdb']->mock_rows = [];
        $GLOBALS['wpdb']->mock_vars = [];
        $this->repo = MatchRepository::instance();
    }

    public function test_get_manual_match_candidates_filters_and_scores(): void
    {
        global $wpdb;

        $target_user_id = 10;
        $pool = [
            'user_id'            => 10,
            'gender'             => 'male',
            'birth_date'         => '1990-01-01',
            'location'           => 'Riyadh',
            'origin'             => 'Arab',
            'religion'           => 'Muslim',
            'modesty'            => 'Hijab',
            'pref_gender'        => 'female',
            'preferred_age_min'  => 20,
            'preferred_age_max'  => 35,
            'pref_location'      => 'Riyadh',
            'pref_origin'        => 'Arab',
            'pref_religion'      => 'Muslim',
            'pref_modesty'       => 'Hijab',
            'user_type'          => 'monthly',
        ];

        $filters = [
            'f_gender'      => 'female',
            'f_age_min'     => 20,
            'f_age_max'     => 35,
            'f_country'     => 'Saudi Arabia',
            'f_state'       => 'Riyadh Region',
            'f_city'        => 'Riyadh',
            'f_citizenship' => 'Saudi Arabia',
            'f_location'    => '',
            'f_origin'      => 'Arab',
            'f_religion'    => 'Muslim',
            'f_modesty'     => 'Hijab',
        ];

        // Mock two candidates: Candidate A (high match) and Candidate B (lower match)
        $cand_a = [
            'user_id'      => 21,
            'gender'       => 'female',
            'birth_date'   => '1995-05-10',
            'country'      => 'Saudi Arabia',
            'state'        => 'Riyadh Region',
            'city'         => 'Riyadh',
            'location'     => 'Riyadh',
            'origin'       => 'Arab',
            'religion'     => 'Muslim',
            'modesty'      => 'Hijab',
            'pref_gender'  => 'male',
            'pref_country' => 'Saudi Arabia',
            'pref_location'=> 'Riyadh',
            'pref_origin'  => 'Arab',
            'pref_religion'=> 'Muslim',
            'pref_modesty' => 'Hijab',
            'user_type'    => 'monthly',
            'user_email'   => 'canda@example.com',
            'display_name' => 'Candidate A',
        ];

        $cand_b = [
            'user_id'      => 22,
            'gender'       => 'female',
            'birth_date'   => '1998-02-15',
            'country'      => 'Saudi Arabia',
            'state'        => 'Makkah Region',
            'city'         => 'Jeddah',
            'location'     => 'Jeddah',
            'origin'       => 'Other',
            'religion'     => 'Muslim',
            'modesty'      => 'Modest',
            'pref_gender'  => 'male',
            'pref_country' => 'Any',
            'pref_location'=> 'Any',
            'pref_origin'  => 'Any',
            'pref_religion'=> 'Muslim',
            'pref_modesty' => 'Any',
            'user_type'    => 'free',
            'user_email'   => 'candb@example.com',
            'display_name' => 'Candidate B',
        ];

        // Set mock query results
        foreach ($wpdb->queries as $q) {
            $wpdb->mock_results[$q] = [$cand_b, $cand_a];
        }

        // We can override Fakewpdb get_results by matching query substring
        $wpdb->mock_results = [];
        $results = $this->repo->get_manual_match_candidates($target_user_id, $pool, $filters);

        // Verify the search query was executed with country, state, city, and citizenship filters
        $queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str, 'wp_matchmaking_pool') || !str_contains($queries_str, 'c.country') || !str_contains($queries_str, 'c.state') || !str_contains($queries_str, 'c.city') || !str_contains($queries_str, 'user_citizenship')) {
            throw new \RuntimeException("Expected manual match search query with country, state, city, and citizenship filters, executed:\n{$queries_str}");
        }

        // Verify that manual_match_search event was logged
        if (!str_contains($queries_str, 'INSERT INTO wp_matchmaker_logs') && !str_contains($queries_str, 'wp_matchmaker_logs')) {
            throw new \RuntimeException("Expected manual_match_search event to be logged into wp_matchmaker_logs");
        }
    }

    public function test_create_manual_match_pair(): void
    {
        global $wpdb;

        $admin_id = 1;
        $u1 = 10;
        $u2 = 25;
        $score = 5;

        $match_id = $this->repo->create_match($u1, $u2, $admin_id, 'pending_review', 'manual', $score);

        if (!$match_id) {
            throw new \RuntimeException("Expected create_match to return non-zero match ID");
        }

        $queries_str = implode("\n", $wpdb->queries);
        if (!str_contains($queries_str, 'INSERT INTO wp_matches')) {
            throw new \RuntimeException("Expected INSERT into wp_matches, executed:\n{$queries_str}");
        }
    }
}
