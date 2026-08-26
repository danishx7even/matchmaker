# Feature Context: Matching Engine

This document defines the architecture, rules, and operational constraints for the Matchmaking Engine (`includes/Matching_Engine.php`).

## 1. Core Responsibilities
The Matching Engine processes candidate profiles in `wp_matchmaking_pool`, evaluates bi-directional hard gates, calculates a 0–6 flexible compatibility score, and writes match pairings into `wp_matches`.

## 2. Trigger Conditions
Auto-matching runs in 4 distinct scenarios:

1. **Profile Creation**: Triggered synchronously/asynchronously via `Form_Handler` when a **Monthly** user completes the 2-step profile form for the first time.
2. **Profile Update**: Triggered via `Form_Handler` when a **Monthly** user updates their existing profile form.
3. **Recurring Idle-User Run**: Triggered for **Monthly** users whose `mm_last_match_run` timestamp is older than the configured recurrence days (default: 7 days). Configurable via the Matchmaking Admin Settings Page.
4. **Admin Manual Trigger**: Executed synchronously on-demand from the Admin Pool Browser for **any** user type.

> **Constraint**: Auto-matching does **NOT** run automatically upon PMPro membership level upgrade. Users must complete/update their 2-step profile form to initiate auto-matching.

## 3. Bi-Directional Hard Gates (SQL Query)
Both Candidate A and Candidate B must satisfy each other's criteria simultaneously:
- **Gender**: A's gender matches B's preferred gender AND B's gender matches A's preferred gender.
- **Age**: A's age falls within B's preferred age min/max AND B's age falls within A's preferred age min/max.
- **Location**: A's location is in B's preferred locations AND B's location is in A's preferred locations (`FIND_IN_SET`).
- **Religion**: A's religion is in B's preferred religion list AND B's religion is in A's preferred religion list.
- **Modesty**: A's modesty level is in B's preferred modesty list AND B's modesty level is in A's preferred modesty list.
- **Uniqueness**: Pairing must not already exist in `wp_matches` under any status (`LEAST(A, B)` / `GREATEST(A, B)`).

## 4. Flexible Scoring (0–6 Scale in PHP)
Candidates passing hard gates are assigned 1 point for each matching dimension:
1. **Origin**: Mutual origin match.
2. **Languages**: At least 1 shared language.
3. **Height**: Mutual height within preferred min/max range.
4. **Job**: Candidate has listed a job/profession.
5. **Smoking**: Candidate smoking habit matches user's preference.
6. **Drinking**: Candidate drinking habit matches user's preference.

## 5. Quota & Approval Rules
- **Cycle Quota**: 10 approved matches per monthly cycle per user.
- **Quota Deduction**: Quota count (`cycle_matches_count` usermeta) is incremented **ONLY** upon Admin Approval, not when pending matches are generated.
- **Quota Exceeded**: Approval is blocked if `cycle_matches_count >= 10`.
- **Free / Event Candidate Restriction**: Matches where the candidate is `free` or `event` tier are marked as **Info-Only** and cannot be approved by admins (Approve button is hidden).
