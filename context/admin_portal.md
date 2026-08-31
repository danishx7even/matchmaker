# Feature Context: Admin Portal & Management

This document defines the architecture, views, workflows, and settings for the WordPress Admin Portal (`src/Admin/AdminPortal.php`).

---

## 1. Core Responsibilities & Navigation Structure

Top-level admin menu **Matchmaking** (`admin.php?page=matchmaking-pool`):

1. **Candidate Pool Browser (`page=matchmaking-pool`)** — Template: `src/View/admin/pool/pool-list.php`:
   - Filter by tier (`All`, `Monthly`, `1-on-1 VIP`, `Event`, `Free`), search by name/email.
   - Status indicators (Active/Inactive, In Pool, Quota Used vs Configured Max).
   - Single User Detail View (`view_user=ID`, template: `src/View/admin/pool/user-single.php`): Side-by-side profile & search preferences, photo gallery, match history, auto-match scoring & manual matchmaker triggers.
   - Manual Matchmaker View (`manual_match=ID`, template: `src/View/admin/pool/manual-match.php`): Advanced filtering and compatibility score preview.

2. **Matches Queue (`page=matchmaking-matches`)** — Template: `src/View/admin/matches/matches-list.php`:
   - Global match list with status filter (`pending_review`, `approved`, `matched`, `admin_rejected`, `rejected`, `expired`).
   - Single Match View (`view_match=ID`, template: `src/View/admin/matches/match-single.php`): Dual-column side-by-side comparison of User 1 vs User 2, compatibility score, user response statuses, and action buttons.

3. **Settings & Plan Connector (`page=matchmaking-settings`)** — Template: `src/View/admin/settings/settings.php`:
   - **Environment Mode System**: Toggle between "Live / Production Mode" and "Test Mode". When in Test Mode, displays the "Reset Test Matchmaking Data" danger card to safely wipe matches, notifications, logs, and user cycle counters while preserving candidate pool profiles.
   - **PMPro Membership Plan Connector**: Live matrix mapping PMPro levels to Matchmaker tiers.
   - **Quota & Expiration Rules**: `mm_max_cycle_matches`, `mm_match_expiry_days`, `mm_auto_match_recurrence_days`, `mm_max_candidates_per_run`.
   - **Page Routing & Elementor Integration**: `wp_dropdown_pages()` selectors for Dashboard, Questionnaire, Account, Checkout, Events pages, and Elementor Free Registration Form ID.
   - **Approval Email Template**: WYSIWYG editor with dynamic tags (`{user_name}`, `{candidate_name}`, `{candidate_age}`, `{candidate_location}`, `{dashboard_url}`).

4. **Match Logs, Notifications & Diagnostics (`page=matchmaking-logs`)** — Template: `src/View/admin/logs/logs.php`:
   - **Tab 1: Match Logs** (`tab=match_logs`, template: `src/View/admin/logs/tab-match-logs.php`): Match lifecycle and background engine events with event type filters, search, pagination, and JSON metadata inspection modal.
   - **Tab 2: Notification & Email Logs** (`tab=notification_logs`, template: `src/View/admin/logs/tab-notification-logs.php`): In-app alerts and transactional email dispatches with rendered HTML email preview modal.
   - **Tab 3: Candidate Gate Debugger** (`tab=debugger`, template: `src/View/admin/logs/tab-debugger.php`): Live runner tool to audit all 7 bi-directional matching gates against the pool.

---

## 2. Match Approval & Quota Enforcement Rules
- **Approve Action**: Transitions status to `approved`, sets `approved_at` timestamp, increments initiator's `cycle_matches_count` by 1, logs persistent notification and structured log event, and dispatches approval email.
- **Quota Limit Gate**: If initiator's `cycle_matches_count >= mm_max_cycle_matches` (default: 10), approval is blocked with an admin warning notice.
- **Info-Only Gating (Free / Event Tiers)**:
  - If **either** User 1 or User 2 is in `free` or `event` tier, the match is rendered for information only.
  - The Approve button is replaced with an `Info Only (Free/Event)` badge to prevent free users from consuming paid match workflows.
- **Reject Action**: Sets status to `admin_rejected`. Preserves initiator quota.
