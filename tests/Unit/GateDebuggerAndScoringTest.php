<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Unit;

use Matchmaker\Service\MatchService;

class GateDebuggerAndScoringTest
{
    private MatchService $service;

    public function setUp(): void
    {
        $this->service = MatchService::instance();
    }

    public function test_perfect_score_evaluates_to_6_points(): void
    {
        $u1 = [
            'user_id'              => 10,
            'origin'               => 'Arab',
            'pref_origin'          => 'Arab',
            'languages'            => 'Arabic, English',
            'height_cm'            => 180,
            'preferred_height_min' => 155,
            'preferred_height_max' => 175,
            'job'                  => 'Software Engineer',
            'smoking'              => 'Non-Smoker',
            'pref_smoking'         => 'Non-Smoker',
            'drinking'             => 'No',
            'pref_drinking'        => 'No',
        ];

        $u2 = [
            'user_id'              => 20,
            'origin'               => 'Arab',
            'pref_origin'          => 'Arab',
            'languages'            => 'Arabic, French',
            'height_cm'            => 165,
            'preferred_height_min' => 175,
            'preferred_height_max' => 190,
            'job'                  => 'Doctor',
            'smoking'              => 'Non-Smoker',
            'pref_smoking'         => 'Non-Smoker',
            'drinking'             => 'No',
            'pref_drinking'        => 'No',
        ];

        $score = $this->service->compute_flexible_score($u1, $u2);
        if ($score !== 6) {
            throw new \RuntimeException("Expected perfect match score of 6, got: " . $score);
        }
    }

    public function test_partial_match_scoring(): void
    {
        $u1 = [
            'user_id'              => 10,
            'origin'               => 'European', // Mismatch with Arab
            'pref_origin'          => 'Asian',
            'languages'            => 'English',
            'height_cm'            => 180,
            'preferred_height_min' => 155,
            'preferred_height_max' => 175,
            'job'                  => 'Engineer',
            'smoking'              => 'Occasional Smoker',
            'pref_smoking'         => 'Non-Smoker',
            'drinking'             => 'No',
            'pref_drinking'        => 'No',
        ];

        $u2 = [
            'user_id'              => 20,
            'origin'               => 'Arab',
            'pref_origin'          => 'Arab',
            'languages'            => 'Arabic, English',
            'height_cm'            => 165,
            'preferred_height_min' => 175,
            'preferred_height_max' => 190,
            'job'                  => 'Teacher',
            'smoking'              => 'Non-Smoker',
            'pref_smoking'         => 'Non-Smoker',
            'drinking'             => 'No',
            'pref_drinking'        => 'No',
        ];

        $score = $this->service->compute_flexible_score($u1, $u2);
        // Origin = 0, Languages = 1, Height = 1, Job = 1, Smoking = 1 (candidate Non-Smoker in u1 pref_smoking), Drinking = 1 -> Total = 5
        if ($score < 3 || $score > 5) {
            throw new \RuntimeException("Expected partial match score between 3 and 5, got: " . $score);
        }
    }
}
