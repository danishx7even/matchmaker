# Matchmaker Plugin — Complete System Architecture & Operational Guide (Project Info)

## 1. Executive Summary & Overview
The **Matchmaker Plugin** is a high-performance, tier-gated WordPress matchmaking engine designed for high-touch Islamic & matrimony platforms (specifically Arab Zawaj). It decouples frontend user interactions from intensive matching logic by utilizing **Action Scheduler** background processing, custom indexed SQL tables, WordPress **Heartbeat API** real-time polling, and an admin management portal.

---

## 2. Database Schema & Architecture

All core matchmaker data is segregated from standard WordPress posts into three custom tables created via `DB_Migrator.php` (`dbDelta`):

### 2.1 Profile Criteria Table (`wp_matchmaking_pool`)
Stores normalized matching criteria for instant SQL indexing without `wp_usermeta` key-value JOIN overhead.
- `user_id` (PK): User ID.
- `gender` / `pref_gender`: `'male'` | `'female'`.
- `birth_date`: Date of birth (used for dynamic age calculations).
- `preferred_age_min` / `preferred_age_max`: Age window.
- `location` / `pref_location`: Geographical location.
- `religion` / `pref_religion`: Religious values & practices.
- `modesty` / `pref_modesty`: Modesty level preferences.
- `languages` / `pref_languages`: Spoken languages.
- `height_cm` / `preferred_height_min` / `preferred_height_max`: Height parameters.
- `user_type`: Tier classification (`'monthly'`, `'one_on_one'`, `'free'`, `'event'`).
- `is_active`: Binary active status (`1` or `0`).

### 2.2 Matches Table (`wp_matches`)
Tracks generated candidate pairs, approval lifecycle, and mutual responses.
- `id` (PK): Match ID.
- `user_one_id` / `user_two_id`: Member IDs enforcing canonical ordering ($\min(\text{ID}_A, \text{ID}_B) = \text{user\_one\_id}$, $\max(\text{ID}_A, \text{ID}_B) = \text{user\_two\_id}$).
- `initiator_user_id`: ID of the user whose search execution created this match pair.
- `status`: Match lifecycle state:
  - `pending_review`: System-generated match waiting for admin approval.
  - `approved`: Approved by admin; visible on user dashboard.
  - `admin_rejected`: Declined by admin.
  - `matched`: Mutual acceptance (both users accepted).
  - `rejected`: Declined by one or both users, or auto-expired after 7 days.
- `user_one_response` / `user_two_response`: `'pending'`, `'accepted'`, `'rejected'`.
- `score`: Dynamic bi-directional compatibility score ($0 - 100$).
- `contact_revealed`: `1` if mutual match achieved and contact info revealed.

### 2.3 Persistent Notifications Table (`wp_matchmaker_notifications`)
Logs user-specific notifications for instant toast popups and unread badge counters.
- `id` (PK): Notification ID.
- `user_id`: Target recipient member ID.
- `match_id`: Associated match ID.
- `type`: Notification type (`'match_approved'`, `'mutual_match'`, etc.).
- `title` / `message`: Content text.
- `is_read`: `0` (unread) or `1` (read).

---

## 3. End-to-End Operational Lifecycle & Flow

```
[ User Registration / Questionnaire ]
                  │
                  ▼
[ 1. Ingest Criteria into wp_matchmaking_pool & usermeta ]
                  │
                  ▼
[ 2. Queue Async Matching Job via Action Scheduler ]
                  │
                  ▼
[ 3. Matching Engine (Bi-Directional SQL Calculation) ]
                  │
                  ▼
[ 4. Insert Candidate Pairs into wp_matches (status = pending_review) ]
                  │
                  ▼
[ 5. Admin Portal Review (Matchmaking -> Admin Review) ]
         ┌────────┴────────┐
  (Approve)             (Reject)
         │                 │
         ▼                 ▼
[ Increment Billing   [ Set status =
  Quota & Set status    admin_rejected ]
  = approved ]
         │
         ├───> [ Insert row in wp_matchmaker_notifications ]
         └───> [ Dispatch Email Alerts via WP Mail & Rich Editor ]
         │
         ▼
[ 6. Member Portal Dashboard ([matchmaker_member_portal]) ]
         │
         ├───> [ Heartbeat 15s Pulse: Toast Alert & Bell Badge ]
         │
         ▼
[ 7. 5-State Interactive Match Flow ]
  ├── State 1: New Match Discovery Card (Countdown Timer)
  ├── State 2: Full Profile Review + Floating Action Dock
  ├── State 3: Response Submitted (Accepted / Declined)
  ├── State 4: Decline Confirmation Modal
  └── State 5: Mutual Match Revealed (Contact Details Unveiled)
```

---

## 4. Subsystem Detailed Breakdown

### 4.1 Tier System & Billing Quotas (Paid Memberships Pro)
- Managed via `PMPro_Sync.php` listening to `pmpro_after_change_membership_level`.
- **Free (`free`) & Event (`event`)**: Upsell banner shown on dashboard; matching engine bypassed.
- **Monthly Matchmaker (`monthly`) & 1-on-1 (`one_on_one`)**: Full access to matching queue up to **10 approved matches per cycle**.
- Admin approval increments `cycle_matches_count` in user meta. If count reaches 10, admin approval is gated until cycle renewal.

### 4.2 Async Matching Engine (`Matching_Engine.php`)
- Submitted questionnaires enqueue an asynchronous task via `as_enqueue_async_action('mm_run_async_matching_job', [$user_id])`.
- **Daily Queue Worker**: `mm_daily_check_weekly_matching_queue` scans for idle active members ($\ge 7$ days without new matches).
- **Auto-Expiration Worker**: Unanswered approved matches auto-expire after 7 days ($168$ hours), setting `status = 'rejected'`.

### 4.3 Admin Portal (`Admin_Portal.php`)
- Custom admin dashboard under **Matchmaking** top-level menu (`priority 30`).
- Features match approval queue, manual match creation, criteria search, and **Email Settings** with WordPress rich text editor (`wp_editor`) supporting dynamic placeholders (`{user_name}`, `{candidate_name}`, `{candidate_age}`, `{candidate_location}`, `{dashboard_url}`).

### 4.4 Heartbeat API & Notification Manager (`Notification_Manager.php`)
- Configures 15-second Heartbeat polling on `/dashboard/`.
- Client pulse sends `mm_poll_notifications: true`.
- Server queries `wp_matchmaker_notifications` for `is_read = 0` for the current user.
- Returning or active members instantly see top-right slide-out toast alerts (`#mm-toast-box`) and header bell badge updates.
- Viewing a match or opening the Matches tab triggers `wp_ajax_mm_mark_notifications_read` AJAX request, updating `is_read = 1` and flushing transients.

### 4.5 Member Dashboard & 5-State UI (`Match_Flow_Handler.php`, `member-portal.css`, `member-portal.js`)
- Enqueued via shortcode `[matchmaker_member_portal]`.
- Top navigation bar featuring `<button>` elements with `all: unset !important` theme overrides to prevent theme CSS pollution.
- Interactive 5-state step navigation for candidate review, detailed meta grid, lifestyle pills, match countdown timers, decline confirmation modals, and mutual contact unveiling.

---

## 5. Security & Performance Rules
1. **PHP 8.1 Strict Types**: All PHP files declare `declare(strict_types=1);`.
2. **Prepared Queries**: Every SQL call uses `$wpdb->prepare()`.
3. **Nonces**: AJAX requests validated with `wp_verify_nonce($nonce, 'mm_portal_nonce')`.
4. **No Direct CPT Bloat**: Matching data kept strictly in custom indexed tables.
5. **No Full-Table Scans on HTTP**: Async background execution guarantees sub-50ms HTTP form submission responses.
