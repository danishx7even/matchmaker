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
     * Check if the pair is info-only (legacy method, all registered tiers are eligible for approval).
     *
     * @param string $type_a The user A type.
     * @param string $type_b The user B type.
     * @return bool
     */
    public function is_info_only_pair(string $type_a, string $type_b): bool {
        return false;
    }

    /**
     * Computes comprehensive field-by-field criteria comparison between two pool users.
     * Evaluates hard gates and 6-point flexible scoring rules.
     *
     * @param array<string, mixed> $user
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    public function get_criteria_comparison_breakdown(array $user, array $candidate): array
    {
        $repo = MatchRepository::instance();

        // Helper: split comma-delimited string
        $split = static function (?string $val): array {
            if (empty($val)) {
                return [];
            }
            return array_filter(array_map('trim', explode(',', $val)));
        };

        // Helper: in list or Any
        $in_list = static function (?string $needle, ?string $haystack) use ($split): bool {
            if (empty($haystack)) {
                return true;
            }
            $items = $split($haystack);
            foreach ($items as $item) {
                if (in_array(strtolower(trim($item)), ['any', 'any origin', 'no preference', 'any country', 'any citizenship', 'any religion', 'any modesty'], true)) {
                    return true;
                }
                if (!empty($needle) && strcasecmp(trim($needle), $item) === 0) {
                    return true;
                }
            }
            return false;
        };

        $criteria = [];

        // 1. Gender Compatibility
        $u_pref_g = strtolower((string) ($user['pref_gender'] ?? ''));
        $c_gender = strtolower((string) ($candidate['gender'] ?? ''));
        $c_pref_g = strtolower((string) ($candidate['pref_gender'] ?? ''));
        $u_gender = strtolower((string) ($user['gender'] ?? ''));

        $gender_match = ($u_pref_g === 'any' || empty($u_pref_g) || $u_pref_g === $c_gender) &&
                         ($c_pref_g === 'any' || empty($c_pref_g) || $c_pref_g === $u_gender);

        $criteria[] = [
            'id'            => 'gender',
            'label'         => __('Gender Compatibility', 'matchmaker'),
            'category'      => 'gate',
            'target_val'    => sprintf(__('Prefers %s (is %s)', 'matchmaker'), ucfirst($u_pref_g ?: 'Any'), ucfirst($u_gender)),
            'candidate_val' => sprintf(__('Prefers %s (is %s)', 'matchmaker'), ucfirst($c_pref_g ?: 'Any'), ucfirst($c_gender)),
            'is_match'      => $gender_match,
            'note'          => $gender_match ? __('Mutual gender preference matched', 'matchmaker') : __('Gender preference mismatch', 'matchmaker'),
        ];

        // 2. Age Range
        $u_age = $repo->calc_age((string) ($user['birth_date'] ?? ''));
        $c_age = $repo->calc_age((string) ($candidate['birth_date'] ?? ''));
        $u_min_age = (int) ($user['preferred_age_min'] ?? 18);
        $u_max_age = (int) ($user['preferred_age_max'] ?? 80);
        $c_min_age = (int) ($candidate['preferred_age_min'] ?? 18);
        $c_max_age = (int) ($candidate['preferred_age_max'] ?? 80);

        $u_accepts_c_age = ($c_age >= $u_min_age && $c_age <= $u_max_age);
        $c_accepts_u_age = ($u_age >= $c_min_age && $u_age <= $c_max_age);
        $age_match       = ($u_accepts_c_age && $c_accepts_u_age);

        $criteria[] = [
            'id'            => 'age',
            'label'         => __('Age Range', 'matchmaker'),
            'category'      => 'gate',
            'target_val'    => sprintf(__('%d yrs (Prefers %d–%d)', 'matchmaker'), $u_age, $u_min_age, $u_max_age),
            'candidate_val' => sprintf(__('%d yrs (Prefers %d–%d)', 'matchmaker'), $c_age, $c_min_age, $c_max_age),
            'is_match'      => $age_match,
            'note'          => $age_match ? __('Both within mutual age ranges', 'matchmaker') : ($u_accepts_c_age ? __('Target user is outside candidate age range', 'matchmaker') : __('Candidate is outside target age range', 'matchmaker')),
        ];

        // 3. Location / Country
        $u_loc_parts = array_filter([$user['city'] ?? '', $user['state'] ?? '', $user['country'] ?? '']);
        $u_loc = !empty($u_loc_parts) ? implode(', ', $u_loc_parts) : ($user['location'] ?? '—');
        $c_loc_parts = array_filter([$candidate['city'] ?? '', $candidate['state'] ?? '', $candidate['country'] ?? '']);
        $c_loc = !empty($c_loc_parts) ? implode(', ', $c_loc_parts) : ($candidate['location'] ?? '—');

        $u_pref_c = $user['pref_country'] ?? '';
        $c_pref_c = $candidate['pref_country'] ?? '';
        $u_country = $user['country'] ?? '';
        $c_country = $candidate['country'] ?? '';

        $loc_match = $in_list($c_country, $u_pref_c) && $in_list($u_country, $c_pref_c);

        $criteria[] = [
            'id'            => 'location',
            'label'         => __('Location / Country', 'matchmaker'),
            'category'      => 'gate',
            'target_val'    => sprintf(__('%s (Prefers: %s)', 'matchmaker'), $u_loc, $u_pref_c ?: __('Any', 'matchmaker')),
            'candidate_val' => sprintf(__('%s (Prefers: %s)', 'matchmaker'), $c_loc, $c_pref_c ?: __('Any', 'matchmaker')),
            'is_match'      => $loc_match,
            'note'          => $loc_match ? __('Location preferences aligned', 'matchmaker') : __('Country/location preferences differ', 'matchmaker'),
        ];

        // 4. Religion
        $u_rel = $user['religion'] ?? '—';
        $c_rel = $candidate['religion'] ?? '—';
        $u_pref_rel = $user['pref_religion'] ?? '';
        $c_pref_rel = $candidate['pref_religion'] ?? '';

        $rel_match = $in_list($c_rel, $u_pref_rel) && $in_list($u_rel, $c_pref_rel);

        $criteria[] = [
            'id'            => 'religion',
            'label'         => __('Religion / Sect', 'matchmaker'),
            'category'      => 'gate',
            'target_val'    => sprintf(__('%s (Prefers: %s)', 'matchmaker'), $u_rel, $u_pref_rel ?: __('Any', 'matchmaker')),
            'candidate_val' => sprintf(__('%s (Prefers: %s)', 'matchmaker'), $c_rel, $c_pref_rel ?: __('Any', 'matchmaker')),
            'is_match'      => $rel_match,
            'note'          => $rel_match ? __('Religious preferences compatible', 'matchmaker') : __('Religious preferences differ', 'matchmaker'),
        ];

        // 5. Modesty Level
        $u_mod = $user['modesty'] ?? '—';
        $c_mod = $candidate['modesty'] ?? '—';
        $u_pref_mod = $user['pref_modesty'] ?? '';
        $c_pref_mod = $candidate['pref_modesty'] ?? '';

        $mod_match = $in_list($c_mod, $u_pref_mod) && $in_list($u_mod, $c_pref_mod);

        $criteria[] = [
            'id'            => 'modesty',
            'label'         => __('Modesty Level', 'matchmaker'),
            'category'      => 'gate',
            'target_val'    => sprintf(__('%s (Prefers: %s)', 'matchmaker'), $u_mod, $u_pref_mod ?: __('Any', 'matchmaker')),
            'candidate_val' => sprintf(__('%s (Prefers: %s)', 'matchmaker'), $c_mod, $c_pref_mod ?: __('Any', 'matchmaker')),
            'is_match'      => $mod_match,
            'note'          => $mod_match ? __('Modesty levels align', 'matchmaker') : __('Modesty expectations differ', 'matchmaker'),
        ];

        // 6. Origin / Ethnicity (Flexible Point #1)
        $u_orig = $user['origin'] ?? '—';
        $c_orig = $candidate['origin'] ?? '—';
        $u_pref_orig = $user['pref_origin'] ?? '';
        $c_pref_orig = $candidate['pref_origin'] ?? '';

        $orig_match = $in_list($c_orig, $u_pref_orig) && $in_list($u_orig, $c_pref_orig);

        $criteria[] = [
            'id'            => 'origin',
            'label'         => __('Origin / Ethnicity', 'matchmaker'),
            'category'      => 'score',
            'target_val'    => sprintf(__('%s (Prefers: %s)', 'matchmaker'), $u_orig, $u_pref_orig ?: __('Any', 'matchmaker')),
            'candidate_val' => sprintf(__('%s (Prefers: %s)', 'matchmaker'), $c_orig, $c_pref_orig ?: __('Any', 'matchmaker')),
            'is_match'      => $orig_match,
            'note'          => $orig_match ? __('+1 pt: Origin preferences match', 'matchmaker') : __('Origin preferences differ', 'matchmaker'),
        ];

        // 7. Citizenship
        $u_cit = $user['citizenship'] ?? '—';
        $c_cit = $candidate['citizenship'] ?? '—';
        $u_pref_cit = $user['pref_citizenship'] ?? '';
        $c_pref_cit = $candidate['pref_citizenship'] ?? '';

        $cit_match = $in_list($c_cit, $u_pref_cit) && $in_list($u_cit, $c_pref_cit);

        $criteria[] = [
            'id'            => 'citizenship',
            'label'         => __('Citizenship', 'matchmaker'),
            'category'      => 'gate',
            'target_val'    => sprintf(__('%s (Prefers: %s)', 'matchmaker'), $u_cit, $u_pref_cit ?: __('Any', 'matchmaker')),
            'candidate_val' => sprintf(__('%s (Prefers: %s)', 'matchmaker'), $c_cit, $c_pref_cit ?: __('Any', 'matchmaker')),
            'is_match'      => $cit_match,
            'note'          => $cit_match ? __('Citizenship preferences align', 'matchmaker') : __('Citizenship preferences differ', 'matchmaker'),
        ];

        // 8. Languages (Flexible Point #2)
        $u_langs = $split($user['languages'] ?? null);
        $c_langs = $split($candidate['languages'] ?? null);
        $shared_langs = array_intersect($u_langs, $c_langs);
        $lang_match = !empty($shared_langs);

        $criteria[] = [
            'id'            => 'languages',
            'label'         => __('Spoken Languages', 'matchmaker'),
            'category'      => 'score',
            'target_val'    => !empty($u_langs) ? implode(', ', $u_langs) : '—',
            'candidate_val' => !empty($c_langs) ? implode(', ', $c_langs) : '—',
            'is_match'      => $lang_match,
            'note'          => $lang_match ? sprintf(__('+1 pt: Shared: %s', 'matchmaker'), implode(', ', $shared_langs)) : __('No common languages listed', 'matchmaker'),
        ];

        // 9. Height Range (Flexible Point #3)
        $c_h = !empty($candidate['height_cm']) ? (int) $candidate['height_cm'] : null;
        $u_h = !empty($user['height_cm']) ? (int) $user['height_cm'] : null;
        $u_h_min = !empty($user['preferred_height_min']) ? (int) $user['preferred_height_min'] : null;
        $u_h_max = !empty($user['preferred_height_max']) ? (int) $user['preferred_height_max'] : null;
        $c_h_min = !empty($candidate['preferred_height_min']) ? (int) $candidate['preferred_height_min'] : null;
        $c_h_max = !empty($candidate['preferred_height_max']) ? (int) $candidate['preferred_height_max'] : null;

        $a_in_b = ($c_h !== null && $u_h_min !== null && $u_h_max !== null && $c_h >= $u_h_min && $c_h <= $u_h_max);
        $b_in_a = ($u_h !== null && $c_h_min !== null && $c_h_max !== null && $u_h >= $c_h_min && $u_h <= $c_h_max);
        $height_match = ($a_in_b && $b_in_a);

        $criteria[] = [
            'id'            => 'height',
            'label'         => __('Height & Height Range', 'matchmaker'),
            'category'      => 'score',
            'target_val'    => ($u_h ? $repo->cm_to_feet($u_h) : '—') . ($u_h_min && $u_h_max ? sprintf(' (Prefers %s–%s)', $repo->cm_to_feet($u_h_min), $repo->cm_to_feet($u_h_max)) : ''),
            'candidate_val' => ($c_h ? $repo->cm_to_feet($c_h) : '—') . ($c_h_min && $c_h_max ? sprintf(' (Prefers %s–%s)', $repo->cm_to_feet($c_h_min), $repo->cm_to_feet($c_h_max)) : ''),
            'is_match'      => $height_match,
            'note'          => $height_match ? __('+1 pt: Heights mutually within ranges', 'matchmaker') : __('Heights outside mutual ranges', 'matchmaker'),
        ];

        // 10. Profession / Job (Flexible Point #4)
        $has_job = !empty(trim((string) ($candidate['job'] ?? '')));
        $criteria[] = [
            'id'            => 'job',
            'label'         => __('Profession / Employment', 'matchmaker'),
            'category'      => 'score',
            'target_val'    => $user['job'] ?? '—',
            'candidate_val' => $candidate['job'] ?? '—',
            'is_match'      => $has_job,
            'note'          => $has_job ? __('+1 pt: Candidate profile specifies profession', 'matchmaker') : __('No profession specified', 'matchmaker'),
        ];

        // 11. Lifestyle (Smoking & Drinking) (Flexible Points #5 & #6)
        $smoke_match = $in_list($candidate['smoking'] ?? null, $user['pref_smoking'] ?? null);
        $drink_match = $in_list($candidate['drinking'] ?? null, $user['pref_drinking'] ?? null);
        $lifestyle_match = ($smoke_match && $drink_match);

        $criteria[] = [
            'id'            => 'lifestyle',
            'label'         => __('Lifestyle (Smoking / Drinking)', 'matchmaker'),
            'category'      => 'score',
            'target_val'    => sprintf('Smoke: %s, Drink: %s', $user['smoking'] ?? '—', $user['drinking'] ?? '—'),
            'candidate_val' => sprintf('Smoke: %s, Drink: %s', $candidate['smoking'] ?? '—', $candidate['drinking'] ?? '—'),
            'is_match'      => $lifestyle_match,
            'note'          => ($smoke_match && $drink_match) ? __('+2 pts: Lifestyle preferences align', 'matchmaker') : ($smoke_match || $drink_match ? __('+1 pt: Partial lifestyle alignment', 'matchmaker') : __('Lifestyle preferences differ', 'matchmaker')),
        ];

        $flex_score = $this->compute_flexible_score($user, $candidate);
        $match_count = 0;
        foreach ($criteria as $c) {
            if (!empty($c['is_match'])) {
                $match_count++;
            }
        }

        return [
            'flexible_score'          => $flex_score,
            'max_flexible_score'      => 6,
            'total_criteria_count'    => count($criteria),
            'matching_criteria_count' => $match_count,
            'criteria'                => $criteria,
        ];
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
     * @param int         $match_id         The match ID.
     * @param int         $user_id          The user ID responding.
     * @param string      $action           The action taken ('accepted' or 'rejected' or 'decline').
     * @param string|null $rejection_reason Optional rejection feedback from the member.
     * @return array
     */
    public function handle_match_response(int $match_id, int $user_id, string $action, ?string $rejection_reason = null): array {
        $repo = MatchRepository::instance();
        $norm_action = in_array(strtolower(trim($action)), ['decline', 'declined', 'reject', 'rejected'], true) ? 'decline' : 'accept';
        $result = $repo->update_match_response($match_id, $user_id, $norm_action, $rejection_reason);

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

        $log_desc = sprintf(__('User #%d responded with "%s" for match #%d. Result status: %s.', 'matchmaker'), $user_id, $action, $match_id, $result['status'] ?? 'unknown');
        if (!empty($rejection_reason)) {
            $log_desc .= ' ' . sprintf(__('Rejection reason: %s', 'matchmaker'), $rejection_reason);
        }

        $repo->log_event(
            'match_lifecycle',
            $norm_action === 'accept' ? 'user_accepted' : 'user_rejected',
            sprintf(__('Member %s: %s Match #%d', 'matchmaker'), ucfirst($action), $user_name, $match_id),
            $log_desc,
            [
                'match_id'         => $match_id,
                'user_id'          => $user_id,
                'action'           => $action,
                'result_status'    => $result['status'] ?? '',
                'rejection_reason' => $rejection_reason ?? '',
                'match'            => $match,
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
            $u1_id = (int) $match['user_one_id'];
            $u2_id = (int) $match['user_two_id'];

            if ($repo->has_active_approved_match($u1_id, $match_id)) {
                $u1_obj  = get_userdata($u1_id);
                $u1_name = $u1_obj ? $u1_obj->display_name : "User #{$u1_id}";
                $info    = $repo->get_active_approved_match_info($u1_id, $match_id);

                if ($info) {
                    $msg = sprintf(
                        __('Cannot approve match #%d: %s already has an active approved match (#%d with %s) awaiting response (%d day(s) remaining).', 'matchmaker'),
                        $match_id,
                        $u1_name,
                        (int) $info['match_id'],
                        $info['partner_name'],
                        (int) $info['days_remaining']
                    );
                } else {
                    $msg = sprintf(__('Cannot approve match: %s already has an active approved match awaiting response.', 'matchmaker'), $u1_name);
                }

                $repo->log_event('match_lifecycle', 'admin_approval_blocked', sprintf(__('Approval Blocked for Match #%d (Active Match Pending for %s)', 'matchmaker'), $match_id, $u1_name), $msg, ['match_id' => $match_id, 'active_user_id' => $u1_id, 'active_match_info' => $info], $match_id, $admin_id, null, 'warning');
                return [
                    'success' => false,
                    'message' => $msg,
                ];
            }

            if ($repo->has_active_approved_match($u2_id, $match_id)) {
                $u2_obj  = get_userdata($u2_id);
                $u2_name = $u2_obj ? $u2_obj->display_name : "User #{$u2_id}";
                $info    = $repo->get_active_approved_match_info($u2_id, $match_id);

                if ($info) {
                    $msg = sprintf(
                        __('Cannot approve match #%d: %s already has an active approved match (#%d with %s) awaiting response (%d day(s) remaining).', 'matchmaker'),
                        $match_id,
                        $u2_name,
                        (int) $info['match_id'],
                        $info['partner_name'],
                        (int) $info['days_remaining']
                    );
                } else {
                    $msg = sprintf(__('Cannot approve match: %s already has an active approved match awaiting response.', 'matchmaker'), $u2_name);
                }

                $repo->log_event('match_lifecycle', 'admin_approval_blocked', sprintf(__('Approval Blocked for Match #%d (Active Match Pending for %s)', 'matchmaker'), $match_id, $u2_name), $msg, ['match_id' => $match_id, 'active_user_id' => $u2_id, 'active_match_info' => $info], $match_id, $admin_id, null, 'warning');
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

    /**
     * Process admin cancellation of an approved match (revert back to pending).
     *
     * @param int $match_id The match ID.
     * @param int $admin_id The admin ID performing the cancellation.
     * @return array<string, mixed> Result array.
     */
    public function process_admin_cancel_approved(int $match_id, int $admin_id): array
    {
        $repo   = MatchRepository::instance();
        $result = $repo->cancel_approved_match($match_id, $admin_id);

        if (!empty($result['success'])) {
            $repo->log_event(
                'match_lifecycle',
                'admin_cancel_approved',
                sprintf(__('Admin Cancelled Approved Match #%d', 'matchmaker'), $match_id),
                sprintf(__('Match #%d approval was cancelled and reverted to pending review by Admin #%d. Member quotas restored.', 'matchmaker'), $match_id, $admin_id),
                [
                    'match_id' => $match_id,
                    'admin_id' => $admin_id,
                ],
                $match_id,
                $admin_id,
                null,
                'warning'
            );
        }

        return $result;
    }
}
