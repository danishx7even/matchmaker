<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Integration;

use Matchmaker\Repository\MatchRepository;
use Matchmaker\Service\MatchService;
use Matchmaker\Service\NotificationService;
use FakeWP_User;

class FullMemberLifecycleFlowTest
{
    private MatchRepository $repo;
    private MatchService $match_service;
    private NotificationService $notif_service;

    public function setUp(): void
    {
        $GLOBALS['__mm_options']   = [];
        $GLOBALS['__mm_usermeta']  = [];
        $GLOBALS['wpdb']->queries  = [];
        $GLOBALS['wpdb']->mock_rows = [];
        $GLOBALS['wpdb']->mock_vars = [];

        $GLOBALS['__mm_users'][101] = new FakeWP_User(101, 'Tariq', 'tariq@example.com');
        $GLOBALS['__mm_users'][102] = new FakeWP_User(102, 'Fatima', 'fatima@example.com');

        $this->repo          = MatchRepository::instance();
        $this->match_service = MatchService::instance();
        $this->notif_service = NotificationService::instance();
    }

    public function test_complete_member_lifecycle(): void
    {
        global $wpdb;

        // -------------------------------------------------------------
        // Step 1: User Profiles in Pool
        // -------------------------------------------------------------
        $user_a_id = 101;
        $user_b_id = 102;

        $pool_a = [
            'user_id'              => $user_a_id,
            'gender'               => 'male',
            'birth_date'           => '1992-05-15',
            'location'             => 'Riyadh',
            'origin'               => 'Arab',
            'religion'             => 'Muslim',
            'modesty'              => 'Hijab',
            'languages'            => 'Arabic, English',
            'height_cm'            => 180,
            'job'                  => 'Software Architect',
            'smoking'              => 'Non-Smoker',
            'drinking'             => 'No',
            'pref_gender'          => 'female',
            'preferred_age_min'    => 20,
            'preferred_age_max'    => 35,
            'pref_location'        => 'Riyadh',
            'pref_origin'          => 'Arab',
            'pref_religion'        => 'Muslim',
            'pref_modesty'         => 'Hijab',
            'preferred_height_min' => 155,
            'preferred_height_max' => 175,
            'pref_smoking'         => 'Non-Smoker',
            'pref_drinking'        => 'No',
            'user_type'            => 'monthly',
            'is_active'            => 1,
        ];

        $pool_b = [
            'user_id'              => $user_b_id,
            'gender'               => 'female',
            'birth_date'           => '1995-08-20',
            'location'             => 'Riyadh',
            'origin'               => 'Arab',
            'religion'             => 'Muslim',
            'modesty'              => 'Hijab',
            'languages'            => 'Arabic, French',
            'height_cm'            => 165,
            'job'                  => 'Medical Specialist',
            'smoking'              => 'Non-Smoker',
            'drinking'             => 'No',
            'pref_gender'          => 'male',
            'preferred_age_min'    => 25,
            'preferred_age_max'    => 38,
            'pref_location'        => 'Riyadh',
            'pref_origin'          => 'Arab',
            'pref_religion'        => 'Muslim',
            'pref_modesty'         => 'Hijab',
            'preferred_height_min' => 175,
            'preferred_height_max' => 190,
            'pref_smoking'         => 'Non-Smoker',
            'pref_drinking'        => 'No',
            'user_type'            => 'monthly',
            'is_active'            => 1,
        ];

        // -------------------------------------------------------------
        // Step 2: Compatibility Scoring & Match Creation
        // -------------------------------------------------------------
        $score = $this->match_service->compute_flexible_score($pool_a, $pool_b);
        if ($score !== 6) {
            throw new \RuntimeException("Expected perfect 6/6 compatibility score, got: " . $score);
        }

        $match_id = $this->repo->create_match($user_a_id, $user_b_id, $user_a_id, 'pending_review', 'auto', $score);
        if (!$match_id) {
            throw new \RuntimeException("Failed to create match record in repository");
        }

        // -------------------------------------------------------------
        // Step 3: Admin Review & Approval
        // -------------------------------------------------------------
        $match_row = [
            'id'                  => $match_id,
            'user_one_id'         => min($user_a_id, $user_b_id),
            'user_two_id'         => max($user_a_id, $user_b_id),
            'initiator_user_id'   => $user_a_id,
            'status'              => 'pending_review',
            'user_one_response'   => 'pending',
            'user_two_response'   => 'pending',
            'score'               => $score,
            'contact_revealed'    => 0,
        ];

        $wpdb->mock_rows = [
            "SELECT * FROM wp_matches WHERE id = {$match_id}" => $match_row,
            "SELECT * FROM wp_matchmaking_pool WHERE user_id = {$user_a_id}" => $pool_a,
            "SELECT * FROM wp_matchmaking_pool WHERE user_id = {$user_b_id}" => $pool_b,
        ];

        $wpdb->mock_vars = [
            "SELECT user_type FROM wp_matchmaking_pool WHERE user_id = {$user_a_id}" => 'monthly',
            "SELECT user_type FROM wp_matchmaking_pool WHERE user_id = {$user_b_id}" => 'monthly',
        ];

        $admin_id = 1;
        $approval_res = $this->match_service->process_admin_approve($match_id, $admin_id);

        if (empty($approval_res['success']) || empty($approval_res['message'])) {
            throw new \RuntimeException("Expected admin approval to succeed with response message: " . var_export($approval_res, true));
        }

        // -------------------------------------------------------------
        // Step 4: User A Accepts -> Step 3 Waiting State
        // -------------------------------------------------------------
        $resp_a = $this->match_service->handle_match_response($match_id, $user_a_id, 'accept');
        if ($resp_a['next_step'] !== 3 || $resp_a['is_mutual'] !== false) {
            throw new \RuntimeException("User A acceptance should transition to Step 3 (Waiting), got step: " . $resp_a['next_step']);
        }

        // -------------------------------------------------------------
        // Step 5: User B Accepts -> Mutual Match (Step 5 Contact Reveal)
        // -------------------------------------------------------------
        $match_row['user_one_response'] = 'accepted';
        $match_row['status'] = 'approved';
        $wpdb->mock_rows["SELECT * FROM wp_matches WHERE id = {$match_id}"] = $match_row;

        $resp_b = $this->match_service->handle_match_response($match_id, $user_b_id, 'accept');
        if ($resp_b['next_step'] !== 5 || $resp_b['is_mutual'] !== true) {
            throw new \RuntimeException("Mutual acceptance should transition to Step 5 (Contact Reveal), got step: " . $resp_b['next_step']);
        }

        // -------------------------------------------------------------
        // Step 6: Test Mode Reset Execution
        // -------------------------------------------------------------
        $this->repo->reset_test_matchmaking_data();
        $queries_str = implode("\n", $wpdb->queries);

        if (!str_contains($queries_str, 'TRUNCATE TABLE wp_matches') || !str_contains($queries_str, 'TRUNCATE TABLE wp_matchmaker_notifications')) {
            throw new \RuntimeException("Expected TRUNCATE TABLE queries for matches and notifications");
        }
    }
}
