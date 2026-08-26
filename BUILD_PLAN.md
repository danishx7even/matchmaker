# Current Build Plan

## Active Task
**Profile Form Action Scheduler Job Enqueueing Fix**

---

## Objectives & Scope
1. **Root Cause Analysis**:
   - Diagnosed why 2-step profile form submission/update did not consistently create an Action Scheduler job.
   - Identified 2 contributing factors:
     1. Strict tier check in `Form_Handler.php` (`$user_type === 'monthly'`): If PMPro returned a non-monthly level ID, or if the tier was set via usermeta, the job enqueue was bypassed.
     2. AS Async Deduplication: `as_enqueue_async_action` was ignoring re-queue requests if an identical action was already pending.
2. **Implementation Fix**:
   - Updated `Form_Handler.php` to resolve `$effective_type` from usermeta, pool, PMPro, or admin roles, ensuring matching is queued whenever eligible members update their profile form.
   - Updated `mm_enqueue_user_matching_job()` in `Matching_Engine.php` to use `as_schedule_single_action(time(), ...)` with stale task clearing via `as_unschedule_all_actions()`.

---

## Step-by-Step Execution Plan

- [x] **Step 1: Effective Tier Resolution in `Form_Handler.php`**
  - Updated section 11 of `handle_ajax()` to check `$effective_type`.
- [x] **Step 2: Reliable Scheduling in `Matching_Engine.php`**
  - Updated `mm_enqueue_user_matching_job()` to use `as_schedule_single_action()`.
- [x] **Step 3: History & Documentation Logging**
  - Updated `BUILD_PLAN.md` and `HISTORY.md`.

---

## Verification & Completion Criteria
- [x] Profile form create/update always enqueues a single Action Scheduler job.
- [x] Stale pending jobs are cleared before re-queuing to prevent deduplication blocks.
