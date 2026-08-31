<?php
declare(strict_types=1);

namespace Matchmaker\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Matchmaker\Core\PMProSync;
use Matchmaker\Repository\MatchRepository;
use Matchmaker\Service\MatchService;

final class EndToEndFlowTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__mm_options'] = [];
        $GLOBALS['__mm_usermeta'] = [];
        $GLOBALS['__mm_scheduled_jobs'] = [];
        $GLOBALS['wpdb'] = new \Fakewpdb();
    }

    public function test_complete_matchmaking_flow(): void
    {
        $repo = MatchRepository::instance();
        $service = MatchService::instance();
        $sync = PMProSync::instance();

        // 1. User Tier Upgrade (PMPro Level 3 -> Monthly)
        $user_id = 201;
        $sync->sync_pmpro_level_to_user_type(3, $user_id, null);

        $this->assertEquals('monthly', get_user_meta($user_id, 'user_type', true));

        // 2. Admin Approves a pending match
        $match_id = 55;
        $candidate_id = 202;

        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matches WHERE id = {$match_id}"] = [
            'id' => $match_id,
            'user_one_id' => $user_id,
            'user_two_id' => $candidate_id,
            'initiator_user_id' => $user_id,
            'status' => 'pending_review',
        ];
        $GLOBALS['wpdb']->mock_vars["SELECT user_type FROM wp_matchmaking_pool WHERE user_id = {$user_id}"] = 'monthly';
        $GLOBALS['wpdb']->mock_vars["SELECT user_type FROM wp_matchmaking_pool WHERE user_id = {$candidate_id}"] = 'monthly';

        $approve_result = $repo->approve_match($match_id, 1);
        $this->assertTrue($approve_result['success']);
        $this->assertEquals(1, (int) get_user_meta($user_id, 'cycle_matches_count', true));

        // 3. User 1 accepts match
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matches WHERE id = {$match_id}"] = [
            'id' => $match_id,
            'user_one_id' => $user_id,
            'user_two_id' => $candidate_id,
            'user_one_response' => 'pending',
            'user_two_response' => 'pending',
            'status' => 'approved',
        ];
        $resp1 = $repo->update_match_response($match_id, $user_id, 'accept');
        $this->assertTrue($resp1['success']);
        $this->assertFalse($resp1['is_mutual']);

        // 4. User 2 accepts match -> Mutual match triggers contact reveal!
        $GLOBALS['wpdb']->mock_rows["SELECT * FROM wp_matches WHERE id = {$match_id}"] = [
            'id' => $match_id,
            'user_one_id' => $user_id,
            'user_two_id' => $candidate_id,
            'user_one_response' => 'accepted',
            'user_two_response' => 'pending',
            'status' => 'approved',
        ];
        $resp2 = $repo->update_match_response($match_id, $candidate_id, 'accept');
        $this->assertTrue($resp2['success']);
        $this->assertTrue($resp2['is_mutual']);
        $this->assertEquals(5, $resp2['next_step']);
    }
}
