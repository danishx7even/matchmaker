# Feature Context: Matching Engine

This document defines the architecture, SQL queries, scoring rules, and batching constraints for the Matchmaking Engine (`src/Core/MatchingEngine.php` and `src/Service/MatchService.php`).

---

## 1. Core Responsibilities
- Evaluates bi-directional hard gates against active candidates in `wp_matchmaking_pool`.
- Computes a dynamic 0–6 flexible compatibility score in PHP.
- Generates candidate pairs into `wp_matches` with `pending_review` status.
- Executes asynchronously in background via **Action Scheduler** (`mm_run_async_matching_job`).
- Processes recurring idle subscribers via daily Action Scheduler cron job with cursor batching (100 users per batch).

---

## 2. Trigger Scenarios
1. **Profile Questionnaire Submission / Update**: Triggered via `FormController` when an eligible member submits or updates their 2-step profile form.
2. **Weekly Idle Queue Cron**: Triggered daily via Action Scheduler (`mm_daily_check_weekly_matching_queue`) for subscribers whose `mm_last_match_run` is older than `mm_auto_match_recurrence_days` (default: 7 days).
3. **Admin On-Demand Manual Run**: Executed synchronously in real-time from the Admin Portal (`view_user=ID` or debugger).

---

## 3. Bi-Directional Hard Gates (SQL Query)
Both Member A and Candidate B must satisfy each other's mandatory criteria simultaneously:
- **Active Status**: `is_active = 1` for both profiles.
- **Opposite Gender**: A's gender matches B's `pref_gender` AND B's gender matches A's `pref_gender`.
- **Age Window**: A's calculated age $\in [\text{B's preferred\_age\_min}, \text{B's preferred\_age\_max}]$ AND B's calculated age $\in [\text{A's preferred\_age\_min}, \text{A's preferred\_age\_max}]$.
- **Location Compatibility**: Evaluates `country` and `pref_country` (supporting dynamic cascading dropdowns and `'Any Country'` / `'Any'` wildcards in both directions).
- **Religious Alignment**: Mutual match between `religion` and `pref_religion` (or `'No Preference'` / `'Any'`).
- **Modesty Alignment**: Mutual match between `modesty` and `pref_modesty` (or `'No Preference'` / `'Any'`).
- **Uniqueness Check**: Pairing must not already exist in `wp_matches` (where `user_one_id = min(A, B)` and `user_two_id = max(A, B)`).

---

## 4. Flexible Scoring Algorithm (0–6 Points)
Candidates passing hard gates are evaluated on 6 flexible dimensions in PHP:
1. **Origin (1 pt)**: Candidate origin matches user preference, or user origin matches candidate preference.
2. **Languages (1 pt)**: At least one shared spoken language.
3. **Height (1 pt)**: Mutual height within each other's preferred min/max range.
4. **Profession / Job (1 pt)**: Candidate has listed an occupation.
5. **Smoking (1 pt)**: Candidate smoking habit matches user's preference.
6. **Drinking (1 pt)**: Candidate drinking habit matches user's preference.

---

## 5. Candidate Generation Limits, Quotas & Verification Gating
- **Max Candidates per Run**: Configurable option `mm_max_candidates_per_run` (default: 10). Only top $N$ scored candidates are inserted.
- **Quota Impact**: Generated `pending_review` rows do **NOT** deduct monthly quota. Quota is only deducted upon Admin Approval.
- **Batch Processing**: Idle queue job queries candidate pool in batches of 100 to prevent memory spikes on large subscriber databases.
- **Email Verification Gating**: Unverified members are restricted by `EmailVerificationService` with a 6-digit verification code screen (24-hour expiration, 60-second cooldown) and cannot access Member Portal or submit the form until verified.
- **Multi-Group PMPro Tier Prioritization**: Instant checkout resolution prioritizes highest tier (`one_on_one` > `monthly` > `event` > `free`) and auto-cancels old Free tiers in separate level groups.
