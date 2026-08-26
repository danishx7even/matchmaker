# Feature Context: Admin Portal & Management

This document defines the architecture, rules, and constraints for the Admin Portal (`includes/Admin_Portal.php`).

## 1. Core Responsibilities
- Registers top-level **Matchmaking** admin menu and sub-pages.
- **Candidate Pool Browser**: Search, filter by gender/membership tier, inspect pool profiles.
- **Matches Sub-Menu Page (`matchmaking-matches`)**: Minimalistic global table listing all matches across the system with filtering by user/candidate name/email and match status (`pending_review`, `approved`, `matched`, `admin_rejected`, `rejected`).
- **Single Match View (`view_match=ID`)**: Full dual-column side-by-side card comparison displaying both User 1 and User 2 profiles, partner search preferences, photo galleries, directional user responses, and approval actions.
- **Single User Detail View (`view_user=ID`)**: Display self-profile alongside partner search preferences, photo gallery, and candidate match history.
- **Match Approval & Rejection Workflow**: Manage match queue statuses (`pending_review`, `approved`, `admin_rejected`).
- **Real-Time Manual Scoring**: On-demand execution of the matching engine for any candidate.

## 2. Match Approval Rules & Constraints
- **Approve Action**: Transitions status to `approved`, sets `approved_by` and `approved_at` timestamps, and increments initiator `cycle_matches_count` by 1.
- **Quota Blockade**: If `cycle_matches_count >= 10`, approval is blocked and an error notice is returned.
- **Info-Only Rule (Free/Event Tier Check)**:
  - If **EITHER** User 1 (Initiator) **OR** User 2 (Candidate) belongs to `free` or `event` tier:
  - The match is displayed **for information only** across all screens (Monthly pool page, Free pool page, and global Matches page).
  - The **Approve button is hidden** for these matches (`Info Only (Free/Event)` label displayed).
  - Server-side approval check in `handle_admin_actions()` explicitly blocks approval if either user has `free` or `event` tier.
- **Exact User Name Responses**: In match list items across all views, user responses are labeled with the exact display names of the respective users (e.g. `Ahmad Al-Mansoor: Accept`).
- **Reject Action**: Transitions status to `admin_rejected`. Can be performed on any candidate match.

## 3. Real-Time Auto-Match Scoring
- Triggered by clicking **"Run Auto-Match Scoring"** on a user's detail page.
- Verifies that the user has an active record in `wp_matchmaking_pool`.
- Executes `Matching_Engine::instance()->run_matching_for_user()` **synchronously** in real-time, refreshing the page with newly calculated candidate match pairings.

## 4. Plugin Settings Page Sub-Menu
- Sub-menu page under Matchmaking menu.
- Holds configuration for:
  - **Auto-Match Recurrence Days**: Number of days after `mm_last_match_run` before an idle Monthly user is re-queued by the weekly cron worker (default: 7 days).
  - Notification and general plugin operational settings.
