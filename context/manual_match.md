# Feature Context: Manual Matchmaker

This document defines the architecture, rules, and constraints for the Manual Matchmaker feature (`includes/Admin_Portal.php`).

## 1. Core Responsibilities
- Provides an admin workflow to manually pair a target user with any candidate in the pool using multi-criteria advanced filters.
- Accessed via the **`+ Manual Match`** CTA button on the Single User Detail page (`admin.php?page=matchmaking-pool&manual_match=USER_ID`).

## 2. Advanced Search Filters
The Manual Match interface pre-populates search filters by default using the target user's saved partner preferences, while allowing the admin to tweak or override any criteria:
- **Gender**: Defaults to target user's `pref_gender`.
- **Age Range (Min / Max)**: Defaults to target user's `preferred_age_min` and `preferred_age_max`.
- **Location**: Candidate location filter.
- **Origin / Ethnicity**: Candidate origin filter.
- **Religion**: Candidate religion filter.
- **Modesty Level**: Candidate modesty filter.

## 3. Search Results & Pairing Rules
- **Exclusion of Self**: The target user is excluded from candidate search results.
- **Exclusion of Existing Pairings**: Candidates who already have a match pairing record with the target user in `wp_matches` (under any status) are excluded from the candidate search results.
- **Flexible Score Display**: Each candidate result row displays the computed 0–6 flexible compatibility score against the target user.
- **Create Match Action**:
  - Clicking **`+ Create Match`** inserts a new row into `wp_matches` using `INSERT IGNORE`.
  - Canonical ID ordering: `user_one_id = min(target, candidate)` and `user_two_id = max(target, candidate)`.
  - Initial status: Assigned **`pending_review`** by default (`match_source = 'admin_manual'`).
  - Quota Impact: 0 quota deducted at creation.
  - Subsequent Approval: The match can be approved later from the match queue provided neither the target user nor the candidate has a `free` or `event` tier.
