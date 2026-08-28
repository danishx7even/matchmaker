# Matchmaker Plugin — Complete System Architecture & Operational Guide (Project Info)

## 1. Executive Summary & Overview
The **Matchmaker Plugin** is a high-performance, tier-gated WordPress matchmaking engine designed for high-touch Islamic & matrimony platforms (specifically Arab Zawaj). It decouples frontend user interactions from intensive matching logic by utilizing **Action Scheduler** background processing, custom indexed SQL tables, WordPress **Heartbeat API** real-time polling, and an admin management portal.

The plugin features a clean, layered architecture with PSR-4 autoloading (`src/`), a centralized Repository database layer (`MatchRepository`), dedicated Service domain classes, plain PHP template Views, and strict PHP 8.1+ typing.

---

## 2. Directory & Component Structure (`src/`)

```
matchmaker/
├── matchmaker.php                           # Main plugin bootstrap & autoloader
├── AGENTS.md
├── PLAN.md
├── BUILD_PLAN.md
├── HISTORY.md
├── Design.md
├── PROJECT_BUILD.md
├── PROJECT_INFO.md
│
├── src/                                     # PSR-4 Root Namespace: Matchmaker\
│   ├── Repository/
│   │   └── MatchRepository.php              # SINGLE DB AUTHORITY (all $wpdb calls live here)
│   │
│   ├── Service/
│   │   ├── MatchService.php                 # Matching business rules, flexible scoring & responses
│   │   ├── ProfileService.php               # Profile data assembly & URL helpers
│   │   └── NotificationService.php          # Email dispatch, Heartbeat API & notifications
│   │
│   ├── Core/
│   │   ├── DBMigrator.php                   # Database schema installer & dbDelta migration
│   │   ├── MatchingEngine.php               # Async matching calculation & Action Scheduler jobs
│   │   ├── PMProSync.php                    # PMPro level synchronization
│   │   ├── FreeRegHandler.php               # Elementor Free Registration handler
│   │   └── TestSeeder.php                   # Mock test data generator
│   │
│   ├── Frontend/
│   │   ├── AuthController.php              # Auth redirects, PMPro login design & logout shortcode
│   │   ├── FieldGenerator.php              # Matchmaking form HTML input generator primitives
│   │   ├── FormController.php               # [matchmaking_form] & [matchmaking_field] shortcodes
│   │   └── PortalController.php             # [matchmaker_member_portal] / [az_profile] shortcode
│   │
│   ├── Admin/
│   │   └── AdminPortal.php                  # Admin dashboard menu, matches queue & settings
│   │
│   ├── View/                                # Pure PHP presentation template views
│   │   └── frontend/
│   │       └── portal/
│   │           ├── portal.php               # Portal canvas & navigation
│   │           ├── tab-profile.php          # Member profile tab
│   │           └── tab-matches.php          # 5-step interactive matches flow
│   │
│   └── functions.php                        # Global helper wrappers (mm_enqueue_user_matching_job)
│
├── assets/
│   ├── css/                                 # admin-matchmaker.css, member-portal.css, matchmaking-form.css
│   └── js/                                  # admin-matchmaker.js, member-portal.js, matchmaking-form.js, phone-mask.js
```

---

## 3. Database Schema & Architecture

All core matchmaker data is segregated from standard WordPress posts into three custom tables created via `DBMigrator.php` (`dbDelta`):

### 3.1 Profile Criteria Table (`wp_matchmaking_pool`)
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

### 3.2 Matches Table (`wp_matches`)
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
- `score`: Dynamic bi-directional compatibility score ($0 - 6$).
- `contact_revealed`: `1` if mutual match achieved and contact info revealed.

### 3.3 Persistent Notifications Table (`wp_matchmaker_notifications`)
Logs user-specific notifications for instant toast popups and unread badge counters.
- `id` (PK): Notification ID.
- `user_id`: Target recipient member ID.
- `match_id`: Associated match ID.
- `type`: Notification type (`'match_approved'`, `'mutual_match'`, etc.).
- `title` / `message`: Content text.
- `is_read`: `0` (unread) or `1` (read).

---

## 4. End-to-End Operational Lifecycle & Flow

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
[ 5. Admin Portal Review (Matchmaking -> Candidate Pool / Matches) ]
         ┌────────┴────────┐
  (Approve)             (Reject)
         │                 │
         ▼                 ▼
[ Increment Billing   [ Set status =
  Quota & Set status    admin_rejected ]
  = approved ]
         │
         ├───> [ Insert row in wp_matchmaker_notifications ]
         └───> [ Dispatch Email Alerts via WP Mail ]
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
  ├── State 3: Response Submitted (Accepted / Declined - Dynamic Status)
  ├── State 4: Decline Confirmation Modal
  └── State 5: Mutual Match Revealed (Contact Details Unveiled)
```

---

## 5. Subsystem Detailed Breakdown

### 5.1 Repository Layer (`MatchRepository.php`)
- **Single Authority**: Centralizes all database transactions (`$wpdb`) and raw meta accesses.
- Enforces canonical pair ordering, user pool upserts, meta blocks, match statistics, and raw searches.

### 5.2 Service Layer (`MatchService`, `ProfileService`, `NotificationService`)
- Encapsulates domain business rules.
- Computes 6-point flexible scoring (Origin, Languages, Height, Job, Smoking, Drinking).
- Manages user tier routing (`monthly`, `one_on_one`, `free`, `event`).
- Dispatches approval emails with dynamic template placeholders (`{user_name}`, `{candidate_name}`, `{candidate_age}`, `{candidate_location}`, `{dashboard_url}`).

### 5.3 Member Dashboard & 5-State UI (`PortalController.php`, `View/frontend/portal/`)
- Enqueued via shortcodes `[matchmaker_member_portal]` and `[az_profile]`.
- Renders rounded user photo image instead of Gravatar.
- Displays dynamic status for both "Your Response" and "Their Response" in Step 3.
- Event listeners for tab switching, match navigation, and AJAX actions.

### 5.4 Admin Portal (`AdminPortal.php`)
- Custom admin dashboard under **Matchmaking** top-level menu.
- Lists candidate pool with search/filter capabilities, approval queue, manual matchmaker, settings, and shortcode documentation.

---

## 6. Security & Coding Guidelines
1. **Strict Typing**: `declare(strict_types=1);` on all PHP files.
2. **Prepared Queries**: All SQL execution isolated in `MatchRepository.php` with `$wpdb->prepare()`.
3. **Nonces**: Validated for all AJAX (`mm_portal_nonce`) and admin actions (`_wpnonce`).
4. **No Direct CPT Bloat**: Matching data kept strictly in custom indexed tables.
5. **No Full-Table Scans on HTTP**: Async background execution via Action Scheduler guarantees sub-50ms HTTP form submission responses.
