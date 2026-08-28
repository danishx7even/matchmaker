<?php
declare(strict_types=1);

namespace Matchmaker\Service;

use Matchmaker\Repository\MatchRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MatchService
 * @package Matchmaker\Service
 */
class MatchService {

    /**
     * @var MatchService|null
     */
    private static ?MatchService $instance = null;

    /**
     * Get the singleton instance.
     *
     * @return MatchService
     */
    public static function instance(): MatchService {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * MatchService constructor.
     */
    private function __construct() {}

    /**
     * Compute flexible score between two users.
     *
     * @param array $user_pool The user's pool data.
     * @param array $candidate_pool The candidate's pool data.
     * @return int
     */
    public function compute_flexible_score(array $user_pool, array $candidate_pool): int {
        $score = 0;

        // 1. Origin mutual match
        if (
            isset($user_pool['origin_country'], $user_pool['pref_origin'], $candidate_pool['origin_country'], $candidate_pool['pref_origin']) &&
            $user_pool['pref_origin'] === $candidate_pool['origin_country'] &&
            $candidate_pool['pref_origin'] === $user_pool['origin_country']
        ) {
            $score += 1;
        }

        // 2. Languages shared overlap
        if (!empty($user_pool['languages']) && !empty($candidate_pool['languages'])) {
            $u_langs = is_array($user_pool['languages']) ? $user_pool['languages'] : explode(',', $user_pool['languages']);
            $c_langs = is_array($candidate_pool['languages']) ? $candidate_pool['languages'] : explode(',', $candidate_pool['languages']);
            $u_langs = array_map('trim', $u_langs);
            $c_langs = array_map('trim', $c_langs);
            if (count(array_intersect($u_langs, $c_langs)) > 0) {
                $score += 1;
            }
        }

        // 3. Height mutual in-range
        if (isset($user_pool['height'], $user_pool['pref_height_min'], $user_pool['pref_height_max'], $candidate_pool['height'], $candidate_pool['pref_height_min'], $candidate_pool['pref_height_max'])) {
            $u_h = (int)$user_pool['height'];
            $c_h = (int)$candidate_pool['height'];
            $u_h_min = (int)$user_pool['pref_height_min'];
            $u_h_max = (int)$user_pool['pref_height_max'];
            $c_h_min = (int)$candidate_pool['pref_height_min'];
            $c_h_max = (int)$candidate_pool['pref_height_max'];

            if ($c_h >= $u_h_min && $c_h <= $u_h_max && $u_h >= $c_h_min && $u_h <= $c_h_max) {
                $score += 1;
            }
        }

        // 4. Job non-empty
        if (!empty($user_pool['profession']) && !empty($candidate_pool['profession'])) {
            $score += 1;
        }

        // 5. Smoking pref match
        if (isset($user_pool['smoking'], $candidate_pool['pref_smoking'])) {
            if ($candidate_pool['pref_smoking'] === 'no_preference' || $user_pool['smoking'] === $candidate_pool['pref_smoking']) {
                $score += 1;
            }
        }

        // 6. Drinking pref match
        if (isset($user_pool['drinking'], $candidate_pool['pref_drinking'])) {
            if ($candidate_pool['pref_drinking'] === 'no_preference' || $user_pool['drinking'] === $candidate_pool['pref_drinking']) {
                $score += 1;
            }
        }

        return $score;
    }

    /**
     * Check if the pair is info-only (free/event users).
     *
     * @param string $type_a The user A type.
     * @param string $type_b The user B type.
     * @return bool
     */
    public function is_info_only_pair(string $type_a, string $type_b): bool {
        return in_array($type_a, ['free', 'event'], true) || in_array($type_b, ['free', 'event'], true);
    }

    /**
     * Get quota used for the cycle.
     *
     * @param int $user_id The user ID.
     * @return int
     */
    public function get_quota_used(int $user_id): int {
        return (int) get_user_meta($user_id, 'cycle_matches_count', true);
    }

    /**
     * Handle match response.
     *
     * @param int $match_id The match ID.
     * @param int $user_id The user ID responding.
     * @param string $action The action taken.
     * @return array
     */
    public function handle_match_response(int $match_id, int $user_id, string $action): array {
        $repo = MatchRepository::instance();
        $result = $repo->update_match_response($match_id, $user_id, $action);

        $match = $repo->find_match_by_id($match_id);
        if ($match) {
            NotificationService::instance()->flush_user_unread_transient((int) ($match['user_one_id'] ?? 0));
            NotificationService::instance()->flush_user_unread_transient((int) ($match['user_two_id'] ?? 0));
        }

        return $result;
    }

    /**
     * Process admin approve match.
     *
     * @param int $match_id The match ID.
     * @param int $admin_id The admin ID.
     * @return array
     */
    public function process_admin_approve(int $match_id, int $admin_id): array {
        $repo = MatchRepository::instance();
        $match = $repo->find_match_by_id($match_id);

        if ($match) {
            $pool1 = $repo->get_user_pool((int)$match['user_one_id']);
            $pool2 = $repo->get_user_pool((int)$match['user_two_id']);
            $type1 = $pool1['user_type'] ?? 'free';
            $type2 = $pool2['user_type'] ?? 'free';

            if ($this->is_info_only_pair($type1, $type2)) {
                return [
                    'success' => false,
                    'message' => __('Cannot approve match: one or both users belong to Free or Event membership tier (upgrade required).', 'matchmaker'),
                ];
            }

            $u1_id = (int) $match['user_one_id'];
            $u2_id = (int) $match['user_two_id'];

            if ($repo->has_active_approved_match($u1_id)) {
                $u1_obj = get_userdata($u1_id);
                return [
                    'success' => false,
                    'message' => sprintf(__('Cannot approve match: %s already has an active approved match awaiting response.', 'matchmaker'), $u1_obj ? $u1_obj->display_name : "User #{$u1_id}"),
                ];
            }

            if ($repo->has_active_approved_match($u2_id)) {
                $u2_obj = get_userdata($u2_id);
                return [
                    'success' => false,
                    'message' => sprintf(__('Cannot approve match: %s already has an active approved match awaiting response.', 'matchmaker'), $u2_obj ? $u2_obj->display_name : "User #{$u2_id}"),
                ];
            }
        }

        $result = $repo->approve_match($match_id, $admin_id);

        if ($result['success'] ?? false) {
            NotificationService::instance()->send_approval_emails($match_id);
        }

        return $result;
    }

    /**
     * Process admin reject match.
     *
     * @param int $match_id The match ID.
     * @return bool
     */
    public function process_admin_reject(int $match_id): bool {
        return MatchRepository::instance()->reject_match($match_id);
    }
}
