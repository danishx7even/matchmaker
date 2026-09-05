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
     * @param array<string, mixed> $user_pool The user's pool data.
     * @param array<string, mixed> $candidate_pool The candidate's pool data.
     * @return int
     */
    public function compute_flexible_score(array $user_pool, array $candidate_pool): int {
        return \Matchmaker\Core\MatchingEngine::instance()->compute_flexible_score($user_pool, $candidate_pool);
    }

    /**
     * Check if the pair is info-only (e.g. event users who do not participate in matching).
     *
     * @param string $type_a The user A type.
     * @param string $type_b The user B type.
     * @return bool
     */
    public function is_info_only_pair(string $type_a, string $type_b): bool {
        return in_array($type_a, ['event'], true) || in_array($type_b, ['event'], true);
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
     * Get max matches quota per cycle from settings.
     *
     * @return int
     */
    public function get_max_cycle_matches(): int {
        return MatchRepository::instance()->get_max_cycle_matches();
    }

    /**
     * Get match review expiry days from settings.
     *
     * @return int
     */
    public function get_match_expiry_days(): int {
        return MatchRepository::instance()->get_match_expiry_days();
    }

    /**
     * Handle match response.
     *
     * @param int $match_id The match ID.
     * @param int $user_id The user ID responding.
     * @param string $action The action taken ('accepted' or 'rejected' or 'decline').
     * @return array
     */
    public function handle_match_response(int $match_id, int $user_id, string $action): array {
        $repo = MatchRepository::instance();
        $norm_action = in_array(strtolower(trim($action)), ['decline', 'declined', 'reject', 'rejected'], true) ? 'decline' : 'accept';
        $result = $repo->update_match_response($match_id, $user_id, $norm_action);

        $match = $repo->find_match_by_id($match_id);
        if ($match) {
            NotificationService::instance()->flush_user_unread_transient((int) ($match['user_one_id'] ?? 0));
            NotificationService::instance()->flush_user_unread_transient((int) ($match['user_two_id'] ?? 0));
        }

        if ($norm_action === 'decline') {
            NotificationService::instance()->send_rejection_notification($match_id, $user_id);
        }

        $user_obj = get_userdata($user_id);
        $user_name = $user_obj ? $user_obj->display_name : "User #{$user_id}";

        $repo->log_event(
            'match_lifecycle',
            $norm_action === 'accept' ? 'user_accepted' : 'user_rejected',
            sprintf(__('Member %s: %s Match #%d', 'matchmaker'), ucfirst($action), $user_name, $match_id),
            sprintf(__('User #%d responded with "%s" for match #%d. Result status: %s.', 'matchmaker'), $user_id, $action, $match_id, $result['status'] ?? 'unknown'),
            [
                'match_id'      => $match_id,
                'user_id'       => $user_id,
                'action'        => $action,
                'result_status' => $result['status'] ?? '',
                'match'         => $match,
            ],
            $match_id,
            $user_id,
            null,
            $norm_action === 'accept' ? 'success' : 'warning'
        );

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
                $msg = __('Cannot approve match: one or both users belong to Event membership tier.', 'matchmaker');
                $repo->log_event('match_lifecycle', 'admin_approval_blocked', sprintf(__('Approval Blocked for Match #%d (Event Tier)', 'matchmaker'), $match_id), $msg, ['match_id' => $match_id, 'type1' => $type1, 'type2' => $type2], $match_id, $admin_id, null, 'warning');
                return [
                    'success' => false,
                    'message' => $msg,
                ];
            }

            $u1_id = (int) $match['user_one_id'];
            $u2_id = (int) $match['user_two_id'];

            if ($repo->has_active_approved_match($u1_id)) {
                $u1_obj = get_userdata($u1_id);
                $msg = sprintf(__('Cannot approve match: %s already has an active approved match awaiting response.', 'matchmaker'), $u1_obj ? $u1_obj->display_name : "User #{$u1_id}");
                $repo->log_event('match_lifecycle', 'admin_approval_blocked', sprintf(__('Approval Blocked for Match #%d (Active Match Pending)', 'matchmaker'), $match_id), $msg, ['match_id' => $match_id, 'active_user_id' => $u1_id], $match_id, $admin_id, null, 'warning');
                return [
                    'success' => false,
                    'message' => $msg,
                ];
            }

            if ($repo->has_active_approved_match($u2_id)) {
                $u2_obj = get_userdata($u2_id);
                $msg = sprintf(__('Cannot approve match: %s already has an active approved match awaiting response.', 'matchmaker'), $u2_obj ? $u2_obj->display_name : "User #{$u2_id}");
                $repo->log_event('match_lifecycle', 'admin_approval_blocked', sprintf(__('Approval Blocked for Match #%d (Active Match Pending)', 'matchmaker'), $match_id), $msg, ['match_id' => $match_id, 'active_user_id' => $u2_id], $match_id, $admin_id, null, 'warning');
                return [
                    'success' => false,
                    'message' => $msg,
                ];
            }
        }

        $result = $repo->approve_match($match_id, $admin_id);

        if ($result['success'] ?? false) {
            $repo->log_event(
                'match_lifecycle',
                'admin_approved',
                sprintf(__('Admin Approved Match #%d', 'matchmaker'), $match_id),
                sprintf(__('Match #%d approved by Admin #%d. Match is now active and awaiting member review.', 'matchmaker'), $match_id, $admin_id),
                [
                    'match_id' => $match_id,
                    'admin_id' => $admin_id,
                    'match'    => $match,
                ],
                $match_id,
                $admin_id,
                null,
                'success'
            );

            NotificationService::instance()->send_approval_emails($match_id);
        } else {
            $repo->log_event(
                'match_lifecycle',
                'admin_approval_failed',
                sprintf(__('Admin Approval Failed for Match #%d', 'matchmaker'), $match_id),
                $result['message'] ?? 'Quota exceeded or database error.',
                [
                    'match_id' => $match_id,
                    'admin_id' => $admin_id,
                    'result'   => $result,
                ],
                $match_id,
                $admin_id,
                null,
                'error'
            );
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
        $repo = MatchRepository::instance();
        $res = $repo->reject_match($match_id);

        $repo->log_event(
            'match_lifecycle',
            'admin_rejected',
            sprintf(__('Admin Rejected Match #%d', 'matchmaker'), $match_id),
            sprintf(__('Match #%d was manually rejected/archived by admin.', 'matchmaker'), $match_id),
            ['match_id' => $match_id, 'rejected_by' => get_current_user_id()],
            $match_id,
            get_current_user_id(),
            null,
            'warning'
        );

        return $res;
    }
}
