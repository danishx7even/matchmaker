<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// Global helper functions — always loaded via Composer "files" autoload
// =============================================================================

if (!function_exists('mm_enqueue_user_matching_job')) {
    /**
     * Enqueue an async matching job for a user via Action Scheduler.
     *
     * Preferred: as_schedule_single_action (fires once at scheduled time, survives
     * deduplication) with stale-action clearing so profile update always triggers
     * a fresh run.
     *
     * Fallback: as_enqueue_async_action (fires ASAP).
     * Last resort: run synchronously in-process.
     *
     * @param int    $user_id The user to run matching for.
     * @param string $trigger Context label: form_submit | form_update | tier_upgrade |
     *                        admin_manual_trigger | weekly_recurring.
     */
    function mm_enqueue_user_matching_job(int $user_id, string $trigger): void
    {
        if ($user_id <= 0) {
            return;
        }

        $hook  = \Matchmaker\Matching_Engine::AS_MATCH_ACTION;
        $args  = [$user_id, $trigger];
        $group = 'matchmaker';

        if (function_exists('as_schedule_single_action')) {
            // Clear any stale pending action for this user+trigger so we never
            // hit Action Scheduler's deduplication block.
            if (function_exists('as_unschedule_all_actions')) {
                as_unschedule_all_actions($hook, $args, $group);
            }

            as_schedule_single_action(time(), $hook, $args, $group);
            error_log("[Matchmaker] Scheduled Action Scheduler job for user #{$user_id} (trigger={$trigger}).");

        } elseif (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action($hook, $args, $group);
            error_log("[Matchmaker] Enqueued async AS job for user #{$user_id} (trigger={$trigger}).");

        } else {
            // Action Scheduler not available — run synchronously.
            error_log("[Matchmaker] Action Scheduler unavailable. Running matching synchronously for user #{$user_id} (trigger={$trigger}).");
            \Matchmaker\Matching_Engine::instance()->run_matching_for_user($user_id, $trigger);
        }
    }
}
