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
   - **Approval Email & Verification Templates**: WYSIWYG editors with dynamic placeholders and sender parameters.
   - **Bulk Email System**: Dynamic recipient filtering, message composition, and batched dispatch.
   - **System Logs Viewer (`#tab-file-logs`)**: Dedicated dual-view log inspector for `info.log` (general events, matching jobs, emails) and `error.log` (failures, exceptions). Features real-time line filtering, line limits (100–1000/All), auto-scroll, AJAX log refresh, log clear, and direct `.log` file downloads. Logs are stored securely in `wp-content/uploads/matchmaker-logs/` with `.htaccess` and `index.php` guards.

4. **Match Logs, Notifications & Diagnostics (`page=matchmaking-logs`)** — Template: `src/View/admin/logs/logs.php`:
   - **Tab 1: Match Logs** (`tab=match_logs`, template: `src/View/admin/logs/tab-match-logs.php`): Match lifecycle and background engine events with event type filters, search, pagination, and JSON metadata inspection modal.
   - **Tab 2: Notification & Email Logs** (`tab=notification_logs`, template: `src/View/admin/logs/tab-notification-logs.php`): In-app alerts and transactional email dispatches with rendered HTML email preview modal.
   - **Tab 3: Candidate Gate Debugger** (`tab=debugger`, template: `src/View/admin/logs/tab-debugger.php`): Live runner tool to audit all 7 bi-directional matching gates against the pool.

5. **Join Click Analytics (`edit.php?post_type=tribe_events&page=matchmaking-event-clicks`)** — Sub-menu under the **Events CPT** top-level menu:
   - **Overview Table** (`src/View/admin/events/event-clicks.php`): Lists all events with at least one click, showing Event Title (linked), Total Clicks, Unique Members, and Last Activity timestamp.
   - **Single Event Detail** (`src/View/admin/events/event-clicks-single.php`): Accessed via `?event_id=<ID>`. Lists every member who clicked, with their display name, email, avatar, click count, first-clicked timestamp, and last-clicked timestamp.
   - **CSV Export**: Triggered via `?mm_action=export_event_clicks&event_id=<ID>`. Downloads a CSV file with columns: Member Name, Email, Click Count, First Clicked, Last Clicked.
   - Data sourced from `wp_matchmaker_event_clicks` table via `MatchRepository::get_events_click_summary()` and `get_event_click_details()`.
   - See [`context/event_click_tracking.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/event_click_tracking.md) for the complete feature reference.

---

## 2. Match Approval, Cancellation & Quota Enforcement Rules
- **Approve Action**: Transitions status to `approved`, sets `approved_at` timestamp, increments initiator's `cycle_matches_count` by 1 (for monthly/paid tiers), logs persistent notification and structured log event, and dispatches approval email.
- **Quota Limit Gate**: For paid monthly tiers, if initiator's `cycle_matches_count >= mm_max_cycle_matches` (default: 10), approval is blocked with an admin warning notice.
- **Unified CTAs Across Tiers (Free, Monthly, Event)**:
  - Matches for Free and Event tiers display the standard `Approve` and `Reject` buttons, allowing admins to deliver matches to basic and event members as desired without quota gating.
- **Cancel Approval Action (`cancel_approved`)**:
  - Reverts an `approved` match back to `pending_review`.
  - Clears approval metadata (`approved_by = NULL`, `approved_at = NULL`) and resets user responses to `pending`.
  - Automatically invalidates active unread `match_approved` notifications for both users.
  - Decrements / restores the monthly quota (`cycle_matches_count`) for both users if they are on monthly paid tiers.
- **Reject Action**: Sets status to `admin_rejected`. Preserves initiator quota.

---

## 3. Matchmaker Admin & Events Organizer Roles & Access Control
- **Matchmaker Admin**:
  - **Role Slug**: `matchmaker_admin` (Display Name: `Matchmaker Admin`).
  - **Capability**: `manage_matchmaker` (also assigned to `administrator`).
  - **Restricted wp-admin Access**: Matchmaker Admins only see and can only access matchmaking pages (`matchmaking-pool`, `matchmaking-matches`, `matchmaking-settings`, `matchmaking-logs`) and their own profile (`profile.php`).
  - Standard WordPress core menus (`index.php`, `plugins.php`, `themes.php`, `users.php`, `tools.php`, `options-general.php`, `edit.php`, etc.), Events CPT menu, and third-party menus (PMPro, Elementor) are stripped.
  - Direct navigation to unauthorized screens redirects to `admin_url('admin.php?page=matchmaking-pool')`.
  - Login redirect automatically sends Matchmaker Admins to the Pool Browser.

- **Events Organizer**:
  - **Role Slug**: `events_organizer` (Display Name: `Events Organizer`).
  - **Capabilities**: `manage_events_organizer`, `manage_matchmaker` (for analytics access), standard post/event capabilities (`edit_events`, `publish_events`, `delete_events`, `upload_files`, etc.).
  - **Restricted wp-admin Access**: Events Organizers only see and can only access the Events CPT menu (`edit.php?post_type={$cpt_slug}`), its submenus (All Events, Add New, Categories/Taxonomies, Join Click Analytics `matchmaking-event-clicks`), and edit their own profile (`profile.php`).
  - Matchmaking top-level menu (`matchmaking-pool`), WordPress core menus, and third-party menus (PMPro, Elementor) are stripped.
  - Direct navigation to unauthorized screens redirects to `admin_url('edit.php?post_type=' . $cpt_slug)`.
  - Login redirect automatically sends Events Organizers to `admin_url('edit.php?post_type=' . $cpt_slug)`.

