<?php
declare(strict_types=1);

namespace Matchmaker\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MatchingEngine
 *
 * Handles asynchronous bi-directional matchmaking via Action Scheduler.
 *
 * Triggers:
 *   - form_submit / form_update  : fired by Form_Handler after profile save
 *   - tier_upgrade               : fired by PMProSync on membership upgrade
 *   - admin_manual_trigger       : fired from Admin_Portal for any user type
 *   - weekly_recurring           : fired by the weekly idle-user cron
 *
 * Hard gates (bi-directional, both users must satisfy each other):
 *   gender, age range, location (FIND_IN_SET), religion (FIND_IN_SET), modesty (FIND_IN_SET)
 *
 * Flexible scoring (1 pt each, max 6):
 *   origin match, language overlap, height in range, job provided, smoking pref, drinking pref
 */
class MatchingEngine {

    private static ?self $instance = null;

    /** Action Scheduler hook for the per-user matching job. */
    public const AS_MATCH_ACTION = 'mm_run_async_matching_job';

    /** Action Scheduler hook for the weekly idle-user cron. */
    public const AS_WEEKLY_ACTION = 'mm_daily_check_weekly_matching_queue';

    /** How many idle days before the weekly cron re-queues a monthly user. */
    public const IDLE_DAYS = 7;

    /** Maximum candidates to insert per matching run. */
    public const MAX_CANDIDATES = 10;

    /**
     * @return self
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Register the AS worker that does the actual matching.
        add_action(self::AS_MATCH_ACTION, [$this, 'handle_as_matching_job'], 10, 2);

        // Register the weekly cron worker.
        add_action(self::AS_WEEKLY_ACTION, [$this, 'check_weekly_queue']);

        // Schedule the weekly recurring cron if it is not already scheduled.
        if (did_action('plugins_loaded')) {
            $this->maybe_schedule_weekly_cron();
        } else {
            add_action('plugins_loaded', [$this, 'maybe_schedule_weekly_cron'], 20);
        }
    }

    // -------------------------------------------------------------------------
    // Scheduling helpers
    // -------------------------------------------------------------------------

    /**
     * Fired on plugin activation via register_activation_hook.
     * Registers the daily recurring Action Scheduler task ONCE.
     */
    public static function activate(): void
    {
        if (function_exists('as_schedule_recurring_action') && function_exists('as_has_scheduled_action')) {
            if (!as_has_scheduled_action(self::AS_WEEKLY_ACTION)) {
                as_schedule_recurring_action(
                    time(),
                    DAY_IN_SECONDS,
                    self::AS_WEEKLY_ACTION,
                    [],
                    'matchmaker'
                );
            }
        }
    }

    /**
     * Fired on plugin deactivation via register_deactivation_hook.
     * Cleans up scheduled Action Scheduler tasks.
     */
    public static function deactivate(): void
    {
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions(self::AS_WEEKLY_ACTION, [], 'matchmaker');
        }
    }

    /**
     * Ensure the recurring idle-user cron exists in Action Scheduler (runs daily).
     * Fallback helper safe to call on plugins_loaded — AS deduplicates recurring groups.
     */
    public function maybe_schedule_weekly_cron(): void
    {
        self::activate();
    }

    // -------------------------------------------------------------------------
    // Action Scheduler job entry-point
    // -------------------------------------------------------------------------

    /**
     * Called by Action Scheduler with ($user_id, $trigger).
     *
     * @param mixed $user_id  The user to run matching for (cast to int).
     * @param mixed $trigger  Context string (cast to string).
     * @throws \Exception
     */
    public function handle_as_matching_job(mixed $user_id, mixed $trigger = 'auto'): void
    {
        $uid = (int) $user_id;
        $trg = (string) $trigger;

        if ($uid <= 0) {
            error_log('[Matchmaker] handle_as_matching_job called with invalid user_id: ' . var_export($user_id, true));
            return;
        }

        $this->run_matching_for_user($uid, $trg);
    }

    // -------------------------------------------------------------------------
    // Recurring idle queue worker
    // -------------------------------------------------------------------------

    /**
     * Checks the queue for idle users and auto-expires old matches.
     * 
     * @throws \Exception
     */
    public function check_weekly_queue(): void
    {
        global $wpdb;

        $pool_table      = $wpdb->prefix . 'matchmaking_pool';
        $recurrence_days = (int) get_option('mm_auto_match_recurrence_days', self::IDLE_DAYS);
        if ($recurrence_days < 1) {
            $recurrence_days = 7;
        }

        $cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . $recurrence_days . ' days'));

        // 1. Auto-expire unanswered approved matches > 7 days old and send email alerts to admin
        \Matchmaker\Repository\MatchRepository::instance()->check_7day_match_expirations();

        $sql = $wpdb->prepare(
            "SELECT p.user_id
             FROM {$pool_table} p
             WHERE p.user_type = 'monthly'
               AND p.is_active = 1
               AND (
                   NOT EXISTS (
                       SELECT 1 FROM {$wpdb->usermeta} um
                       WHERE um.user_id = p.user_id
                         AND um.meta_key = 'mm_last_match_run'
                   )
                   OR (
                       SELECT um2.meta_value FROM {$wpdb->usermeta} um2
                       WHERE um2.user_id = p.user_id
                         AND um2.meta_key = 'mm_last_match_run'
                       LIMIT 1
                   ) < %s
               )",
            $cutoff
        );

        $user_ids = $wpdb->get_col($sql);

        if (empty($user_ids)) {
            error_log('[Matchmaker] Idle queue check: no idle monthly users found.');
            return;
        }

        $queued = 0;
        foreach ($user_ids as $uid) {
            mm_enqueue_user_matching_job((int) $uid, 'idle_recurring');
            $queued++;
        }

        error_log("[Matchmaker] Idle queue check: enqueued matching jobs for {$queued} idle users (threshold={$recurrence_days} days).");
    }

    // -------------------------------------------------------------------------
    // Core matching logic
    // -------------------------------------------------------------------------

    /**
     * Run the full matching algorithm for a single user.
     *
     * @param int    $user_id
     * @param string $trigger
     * @throws \Exception
     */
    public function run_matching_for_user(int $user_id, string $trigger): void
    {
        // 1. Load the user's pool row.
        $user = \Matchmaker\Repository\MatchRepository::instance()->get_user_pool($user_id);

        if (empty($user)) {
            error_log("[Matchmaker] run_matching_for_user: user #{$user_id} not found or inactive in pool.");
            return;
        }

        // 2. Tier gate — only paid users (monthly / 1-on-1) get auto-matching UNLESS admin triggers it.
        if ($trigger !== 'admin_manual_trigger' && !in_array($user['user_type'], ['monthly', 'one_on_one'], true)) {
            error_log("[Matchmaker] Skipping user #{$user_id} — user_type={$user['user_type']}, trigger={$trigger}.");
            return;
        }

        // 3. Mutual match gate — if user already has an accepted mutual match this month, skip generating more matches.
        if (\Matchmaker\Repository\MatchRepository::instance()->has_mutual_match_this_month($user_id)) {
            error_log("[Matchmaker] Skipping user #{$user_id} — user already has a mutually accepted match this month.");
            return;
        }

        // 4. Compute user's current age.
        $user_age = (int) (new \DateTime())->diff(new \DateTime($user['birth_date']))->y;

        // 4. Run the bi-directional hard-gate SQL query.
        $candidates = $this->query_candidates($user, $user_age);

        if (empty($candidates)) {
            error_log("[Matchmaker] user #{$user_id}: no qualifying candidates found.");
            \Matchmaker\Repository\MatchRepository::instance()->set_last_match_run($user_id);
            return;
        }

        // 5. Score each candidate in PHP.
        $scored = [];
        foreach ($candidates as $candidate) {
            $score    = $this->compute_flexible_score($user, $candidate);
            $scored[] = ['row' => $candidate, 'score' => $score];
        }

        // 6. Sort by score descending, take top MAX_CANDIDATES.
        usort($scored, static fn($a, $b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, self::MAX_CANDIDATES);

        // 7. Insert pairs into wp_matches.
        $inserted = 0;
        foreach ($top as $entry) {
            $candidate_id = (int) $entry['row']['user_id'];
            $score        = (int) $entry['score'];
            $success      = $this->insert_match_pair($user_id, $candidate_id, $score);
            if ($success) {
                $inserted++;
            }
        }

        // 8. Record the last run timestamp.
        \Matchmaker\Repository\MatchRepository::instance()->set_last_match_run($user_id);

        error_log("[Matchmaker] user #{$user_id} (trigger={$trigger}): {$inserted} new pairs inserted from " . count($candidates) . " qualifying candidates.");
    }

    // -------------------------------------------------------------------------
    // SQL — bi-directional hard-gate candidate query
    // -------------------------------------------------------------------------

    /**
     * Query the pool for candidates passing ALL bi-directional hard gates.
     *
     * @param array<string,mixed> $user     Associative array of the user's pool row.
     * @param int                 $user_age Computed age of the user.
     * @return array<int,array<string,mixed>>
     */
    private function query_candidates(array $user, int $user_age): array
    {
        global $wpdb;

        $pool_table    = $wpdb->prefix . 'matchmaking_pool';
        $matches_table = $wpdb->prefix . 'matches';
        $user_id       = (int) $user['user_id'];

        $user_gender   = strtolower(trim((string) ($user['gender'] ?? '')));
        $pref_gender   = strtolower(trim((string) ($user['pref_gender'] ?? '')));
        $user_location = trim((string) ($user['location'] ?? ''));
        $pref_location = trim((string) ($user['pref_location'] ?? ''));
        $user_religion = trim((string) ($user['religion'] ?? ''));
        $pref_religion = trim((string) ($user['pref_religion'] ?? ''));
        $user_modesty  = trim((string) ($user['modesty'] ?? ''));
        $pref_modesty  = trim((string) ($user['pref_modesty'] ?? ''));

        $user_age_min  = (int) ($user['preferred_age_min'] ?? 18);
        $user_age_max  = (int) ($user['preferred_age_max'] ?? 99);

        $sql = $wpdb->prepare(
            "SELECT c.*
             FROM {$pool_table} c
             WHERE c.user_id != %d
               AND c.is_active = 1

               -- Gender gates
               AND LOWER(TRIM(c.gender)) = %s
               AND (c.pref_gender IS NULL OR c.pref_gender = '' OR LOWER(TRIM(c.pref_gender)) = %s OR LOWER(TRIM(c.pref_gender)) = 'any')

               -- Age gates
               AND %d BETWEEN c.preferred_age_min AND c.preferred_age_max
               AND TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE()) BETWEEN %d AND %d

               -- Location gates
               AND (
                   c.pref_location IS NULL OR c.pref_location = '' OR LOWER(TRIM(c.pref_location)) = 'any'
                   OR FIND_IN_SET(%s, REPLACE(c.pref_location, ', ', ',')) > 0
               )
               AND (
                   %s = '' OR LOWER(%s) = 'any'
                   OR FIND_IN_SET(c.location, REPLACE(%s, ', ', ',')) > 0
               )

               -- Religion gates
               AND (
                   c.pref_religion IS NULL OR c.pref_religion = '' OR LOWER(TRIM(c.pref_religion)) = 'any'
                   OR FIND_IN_SET(%s, REPLACE(c.pref_religion, ', ', ',')) > 0
               )
               AND (
                   %s = '' OR LOWER(%s) = 'any'
                   OR FIND_IN_SET(c.religion, REPLACE(%s, ', ', ',')) > 0
               )

               -- Modesty gates
               AND (
                   c.pref_modesty IS NULL OR c.pref_modesty = '' OR LOWER(TRIM(c.pref_modesty)) = 'any'
                   OR FIND_IN_SET(%s, REPLACE(c.pref_modesty, ', ', ',')) > 0
               )
               AND (
                   %s = '' OR LOWER(%s) = 'any'
                   OR FIND_IN_SET(c.modesty, REPLACE(%s, ', ', ',')) > 0
               )

               -- Exclude pairs that already exist in wp_matches (any status)
               AND NOT EXISTS (
                   SELECT 1 FROM {$matches_table} m
                   WHERE m.user_one_id = LEAST(%d, c.user_id)
                     AND m.user_two_id = GREATEST(%d, c.user_id)
               )

               -- Exclude candidates who already have a mutually accepted match this month
               AND NOT EXISTS (
                   SELECT 1 FROM {$matches_table} m2
                   WHERE (m2.user_one_id = c.user_id OR m2.user_two_id = c.user_id)
                     AND m2.status = 'matched'
                     AND m2.updated_at >= %s
               )",
            $user_id,
            $pref_gender,
            $user_gender,
            $user_age,
            $user_age_min,
            $user_age_max,
            $user_location,
            $pref_location, $pref_location, $pref_location,
            $user_religion,
            $pref_religion, $pref_religion, $pref_religion,
            $user_modesty,
            $pref_modesty, $pref_modesty, $pref_modesty,
            $user_id,
            $user_id,
            gmdate('Y-m-01 00:00:00')
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    // -------------------------------------------------------------------------
    // Flexible 6-point scoring
    // -------------------------------------------------------------------------

    /**
     * Compute the 0-6 flexible compatibility score between user A and candidate B.
     *
     * Dimensions (1 pt each):
     *  1. Origin     - mutual origin match
     *  2. Languages  - at least one shared language
     *  3. Height     - mutual height-in-range
     *  4. Job        - candidate has a job listed
     *  5. Smoking    - candidate's smoking in user's pref_smoking
     *  6. Drinking   - candidate's drinking in user's pref_drinking
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed> $candidate
     * @return int Score 0-6.
     */
    public function compute_flexible_score(array $user, array $candidate): int
    {
        $score = 0;

        // Helper: split comma-delimited string into a trimmed array.
        $split = static function (?string $val): array {
            if (empty($val)) {
                return [];
            }
            return array_filter(array_map('trim', explode(',', $val)));
        };

        // Helper: check if $needle appears in a comma-delimited $haystack.
        $in_list = static function (?string $needle, ?string $haystack) use ($split): bool {
            if (empty($needle) || empty($haystack)) {
                return false;
            }
            return in_array(trim($needle), $split($haystack), true);
        };

        // 1. Origin — mutual match.
        if (
            $in_list($user['origin'] ?? null, $candidate['pref_origin'] ?? null) &&
            $in_list($candidate['origin'] ?? null, $user['pref_origin'] ?? null)
        ) {
            $score++;
        }

        // 2. Languages — at least one shared language.
        $user_langs      = $split($user['languages'] ?? null);
        $candidate_langs = $split($candidate['languages'] ?? null);
        if (!empty($user_langs) && !empty($candidate_langs)) {
            if (!empty(array_intersect($user_langs, $candidate_langs))) {
                $score++;
            }
        }

        // 3. Height — mutual height-in-range.
        $candidate_height = !empty($candidate['height_cm'])            ? (int) $candidate['height_cm']            : null;
        $user_height      = !empty($user['height_cm'])                 ? (int) $user['height_cm']                 : null;
        $user_h_min       = !empty($user['preferred_height_min'])      ? (int) $user['preferred_height_min']      : null;
        $user_h_max       = !empty($user['preferred_height_max'])      ? (int) $user['preferred_height_max']      : null;
        $cand_h_min       = !empty($candidate['preferred_height_min']) ? (int) $candidate['preferred_height_min'] : null;
        $cand_h_max       = !empty($candidate['preferred_height_max']) ? (int) $candidate['preferred_height_max'] : null;

        $a_in_b_range = ($candidate_height !== null && $user_h_min !== null && $user_h_max !== null)
            && ($candidate_height >= $user_h_min && $candidate_height <= $user_h_max);
        $b_in_a_range = ($user_height !== null && $cand_h_min !== null && $cand_h_max !== null)
            && ($user_height >= $cand_h_min && $user_height <= $cand_h_max);

        if ($a_in_b_range && $b_in_a_range) {
            $score++;
        }

        // 4. Job — candidate has a non-empty job field.
        if (!empty(trim((string) ($candidate['job'] ?? '')))) {
            $score++;
        }

        // 5. Smoking preference.
        if ($in_list($candidate['smoking'] ?? null, $user['pref_smoking'] ?? null)) {
            $score++;
        }

        // 6. Drinking preference.
        if ($in_list($candidate['drinking'] ?? null, $user['pref_drinking'] ?? null)) {
            $score++;
        }

        return $score;
    }

    // -------------------------------------------------------------------------
    // wp_matches insertion
    // -------------------------------------------------------------------------

    /**
     * Insert a canonical pair into wp_matches using MatchRepository.
     *
     * Canonical ordering: user_one_id = LEAST(A,B), user_two_id = GREATEST(A,B)
     * Quota ownership:    initiator_user_id = the user whose job triggered this run.
     *
     * @param int $user_id      Initiating user (quota owner).
     * @param int $candidate_id Matched candidate.
     * @param int $score        Flexible score 0-6.
     * @return bool True if a new row was inserted, false if the pair already existed.
     */
    private function insert_match_pair(int $user_id, int $candidate_id, int $score): bool
    {
        return \Matchmaker\Repository\MatchRepository::instance()->create_match(
            $user_id,
            $candidate_id,
            $user_id,
            'pending_review',
            'auto',
            $score
        );
    }
}
