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

    public function tearDown(): void
    {
        unset($GLOBALS['__mm_current_user_id'], $GLOBALS['__mm_pool_users']);
        $_POST = [];
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

    public function test_get_criteria_comparison_breakdown_matrix(): void
    {
        $match_service = \Matchmaker\Service\MatchService::instance();

        $user = [
            'user_id'            => 10,
            'gender'             => 'male',
            'birth_date'         => '1990-01-01',
            'country'            => 'Saudi Arabia',
            'city'               => 'Riyadh',
            'location'           => 'Riyadh',
            'origin'             => 'Arab',
            'religion'           => 'Muslim',
            'modesty'            => 'Hijab',
            'pref_gender'        => 'female',
            'preferred_age_min'  => 20,
            'preferred_age_max'  => 35,
            'pref_country'       => 'Saudi Arabia',
            'pref_location'      => 'Riyadh',
            'pref_origin'        => 'Arab',
            'pref_religion'      => 'Muslim',
            'pref_modesty'       => 'Hijab',
            'height_cm'          => 180,
            'preferred_height_min' => 155,
            'preferred_height_max' => 175,
            'languages'          => 'Arabic, English',
            'pref_languages'     => 'Arabic',
            'education'          => 'Master',
            'job'                => 'Engineer',
            'smoking'            => 'no',
            'drinking'           => 'no',
            'pref_smoking'       => 'no',
            'pref_drinking'      => 'no',
            'marital_status'     => 'single',
        ];

        $candidate_match = [
            'user_id'            => 20,
            'gender'             => 'female',
            'birth_date'         => '1995-05-15',
            'country'            => 'Saudi Arabia',
            'city'               => 'Riyadh',
            'location'           => 'Riyadh',
            'origin'             => 'Arab',
            'religion'           => 'Muslim',
            'modesty'            => 'Hijab',
            'pref_gender'        => 'male',
            'preferred_age_min'  => 28,
            'preferred_age_max'  => 40,
            'pref_country'       => 'Saudi Arabia',
            'pref_location'      => 'Riyadh',
            'pref_origin'        => 'Arab',
            'pref_religion'      => 'Muslim',
            'pref_modesty'       => 'Hijab',
            'height_cm'          => 165,
            'preferred_height_min' => 170,
            'preferred_height_max' => 190,
            'languages'          => 'Arabic',
            'pref_languages'     => 'Arabic',
            'education'          => 'Bachelor',
            'job'                => 'Doctor',
            'smoking'            => 'no',
            'drinking'           => 'no',
            'pref_smoking'       => 'no',
            'pref_drinking'      => 'no',
            'marital_status'     => 'single',
        ];

        $breakdown = $match_service->get_criteria_comparison_breakdown($user, $candidate_match);

        if ($breakdown['flexible_score'] < 5) {
            throw new \RuntimeException("Expected high flexible score for matched candidate, got: {$breakdown['flexible_score']}");
        }

        if ($breakdown['total_criteria_count'] !== 11) {
            throw new \RuntimeException("Expected 11 criteria evaluated, got: {$breakdown['total_criteria_count']}");
        }

        if ($breakdown['matching_criteria_count'] < 10) {
            throw new \RuntimeException("Expected at least 10 criteria matches, got: {$breakdown['matching_criteria_count']}");
        }


        // Test mismatched candidate
        $candidate_mismatch = [
            'user_id'            => 30,
            'gender'             => 'male', // Gender mismatch
            'birth_date'         => '1950-01-01', // Age mismatch (76 yrs)
            'country'            => 'Canada',
            'city'               => 'Toronto',
            'location'           => 'Toronto',
            'origin'             => 'European',
            'religion'           => 'Christian',
            'modesty'            => 'Western',
            'pref_gender'        => 'male',
            'preferred_age_min'  => 60,
            'preferred_age_max'  => 80,
            'pref_country'       => 'Canada',
            'pref_location'      => 'Toronto',
            'pref_origin'        => 'European',
            'pref_religion'      => 'Christian',
            'pref_modesty'       => 'Western',
        ];

        $breakdown_mismatch = $match_service->get_criteria_comparison_breakdown($user, $candidate_mismatch);
        if ($breakdown_mismatch['flexible_score'] > 2) {
            throw new \RuntimeException("Expected low flexible score for mismatched candidate, got: {$breakdown_mismatch['flexible_score']}");
        }

        $gender_crit = array_values(array_filter($breakdown_mismatch['criteria'], fn($c) => $c['id'] === 'gender'))[0] ?? null;
        if (!$gender_crit || $gender_crit['is_match'] !== false) {
            throw new \RuntimeException("Expected gender mismatch criterion to be false");
        }
    }

    public function test_ajax_candidate_search_and_direct_match_flow(): void
    {
        global $wpdb;

        $admin = \Matchmaker\Admin\AdminPortal::instance();
        
        // Setup admin user
        $admin_user = new \FakeWP_User(1, 'admin', 'admin@example.com');
        $admin_user->roles = ['administrator'];
        $GLOBALS['__mm_users'][1] = $admin_user;
        $GLOBALS['__mm_current_user_id'] = 1;

        $GLOBALS['__mm_pool_users'] = [
            10 => [
                'user_id'      => 10,
                'gender'       => 'male',
                'birth_date'   => '1990-01-01',
                'country'      => 'Saudi Arabia',
                'city'         => 'Riyadh',
                'location'     => 'Riyadh',
                'origin'       => 'Arab',
                'religion'     => 'Muslim',
                'modesty'      => 'Hijab',
                'user_type'    => 'monthly',
                'pref_gender'  => 'female',
            ],
            50 => [
                'user_id'      => 50,
                'gender'       => 'female',
                'birth_date'   => '1994-06-20',
                'country'      => 'Saudi Arabia',
                'city'         => 'Jeddah',
                'location'     => 'Jeddah',
                'origin'       => 'Arab',
                'religion'     => 'Muslim',
                'modesty'      => 'Hijab',
                'user_type'    => 'free',
                'pref_gender'  => 'male',
            ],
        ];

        // 1. Test search handler
        $_POST = [
            'nonce'          => wp_create_nonce('mm_admin_nonce'),
            'target_user_id' => 10,
            'query'          => '50',
        ];

        ob_start();
        try {
            $admin->ajax_search_candidates();
        } catch (\Throwable $e) {
            // expected from wp_send_json_success / wp_send_json_error
        }
        $res = json_decode(ob_get_clean(), true);

        if (!isset($res['success']) || !$res['success']) {
            throw new \RuntimeException("Expected ajax_search_candidates to succeed: " . json_encode($res));
        }

        // 2. Test candidate breakdown handler
        $_POST = [
            'nonce'          => wp_create_nonce('mm_admin_nonce'),
            'target_user_id' => 10,
            'candidate_id'   => 50,
        ];

        ob_start();
        try {
            $admin->ajax_get_candidate_breakdown();
        } catch (\Throwable $e) {
            // expected
        }
        $res_bd = json_decode(ob_get_clean(), true);

        if (!isset($res_bd['success']) || !$res_bd['success'] || !isset($res_bd['data']['score'])) {
            throw new \RuntimeException("Expected ajax_get_candidate_breakdown to return breakdown data: " . json_encode($res_bd));
        }

        // 3. Test direct match creation handler
        $_POST = [
            'nonce'          => wp_create_nonce('mm_admin_nonce'),
            'target_user_id' => 10,
            'candidate_id'   => 50,
        ];

        ob_start();
        try {
            $admin->ajax_create_direct_match();
        } catch (\Throwable $e) {
            // expected
        }
        $res_create = json_decode(ob_get_clean(), true);

        if (!isset($res_create['success']) || !$res_create['success'] || empty($res_create['data']['match_id'])) {
            throw new \RuntimeException("Expected ajax_create_direct_match to succeed: " . json_encode($res_create));
        }
    }
}


