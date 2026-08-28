# Matchmaker Plugin — Master Architecture & Feature Inventory

This document serves as the **authoritative blueprint and feature index** for the Matchmaker WordPress plugin. It outlines the plugin's architecture, file structure, database schema, shortcodes, core feature modules, WordPress hook integrations, and refactoring guidelines.

---

## 1. Overview & Operational Constraints

- **Plugin Directory**: `wp-content/plugins/matchmaker/` (or `matchkmaker/`)
- **PHP Version Target**: PHP 8.1+ with strict type declarations (`declare(strict_types=1);`).
- **Dependencies**: WordPress Core (6.0+), Paid Memberships Pro (PMPro), Action Scheduler.
- **Design Tokens**: Primary `#CC723F` (Desert Gold), Background `#F8F2ED`, Accent `#829067`, Green `#144D34`, Danger `#D93025`. Typography: `Cormorant SC` & `Inter`.

---

## 2. Complete File & Directory Map

```
matchmaker/
├── matchmaker.php                 # Plugin entrypoint, autoloader & singleton initialization
├── AGENTS.md                      # AI agent operational guidelines and canonical rules
├── PLAN.md                        # Master implementation roadmap
├── BUILD_PLAN.md                  # Active feature build plan & task tracking
├── HISTORY.md                     # Chronological execution log of implemented features
├── Design.md                      # Official design tokens, typography & status lifecycle
├── PROJECT_BUILD.md               # Master architecture overview & context index (this file)
├── profile.php                    # Legacy profile template (shortcode fallback)
├── match-steps.php                # Reference template for 5-state match flow
├── context/                       # Feature-specific context & business logic specs
│   ├── matching_engine.md         # Matching engine, scoring, hard gates, quota & triggers
│   ├── form_handler.md            # 2-step form, hydration, photos, standalone field shortcodes
│   ├── admin_portal.md            # Candidate pool, match queue, info-only tier rules & settings
│   ├── manual_match.md            # Manual matchmaker, advanced filters & manual pair creation
│   ├── pmpro_sync.md              # PMPro membership level mapping & usermeta/pool sync
│   ├── auth_redirects.md          # Login/registration URL overrides, redirects & PMPro login styling
│   └── free_registration.md       # Elementor decoupled validation, free registration & auto-login
├── includes/                      # Core PHP classes (PSR-4 namespace Matchmaker)
│   ├── Admin_Portal.php           # Admin pool browser, matches list, single match view, manual matchmaker & settings
│   ├── Auth_Redirects.php         # Login, registration, role-based redirects & wp-login.php styling
│   ├── DB_Migrator.php            # Schema installer & dbDelta migrations (wp_matchmaking_pool, wp_matches)
│   ├── Field_Generator.php        # Standalone field component builder ([matchmaking_field])
│   ├── Form_Handler.php           # 2-step questionnaire form shortcode & AJAX handler
│   ├── Free_Reg_Handler.php       # Elementor Pro form hooks for free registration & auto-login
│   ├── functions.php              # Global helper function mm_enqueue_user_matching_job()
│   ├── Legacy_Form_Wrapper.php    # Backward-compatibility loader for legacy assets
│   ├── Match_Flow_Handler.php     # Unified Member Portal shortcode ([matchmaker_member_portal]), tabs & 5-state match flow
│   ├── Matching_Engine.php        # Bi-directional SQL queries, 6-point scoring & Action Scheduler jobs
│   ├── Notification_Manager.php   # Approval email dispatch, heartbeat polling & unread badge counters
│   ├── PMPro_Sync.php             # PMPro level change listener & user_type synchronizer
│   └── Test_Seeder.php            # Test user & candidate pool data seeder
└── assets/                        # CSS and JS assets
    ├── css/
    │   ├── admin-matchmaker.css   # WordPress admin portal stylesheet
    │   ├── matchmaking-form.css   # Frontend questionnaire form stylesheet
    │   └── member-portal.css      # Member portal dashboard, profile, tabs & 5-state flow stylesheet
    └── js/
        ├── admin-matchmaker.js    # Admin portal modal & AJAX logic
        ├── matchmaking-form.js    # Frontend form navigation, hydration & photo previews
        └── member-portal.js       # Member portal JS event delegation, tab switching & AJAX match actions
```

---

## 3. Database Schema & Data Models

The plugin creates and maintains two custom database tables via `DB_Migrator.php`:

### 3.1 `wp_matchmaking_pool`
Indexed candidate profile criteria used for high-performance matching queries.

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `BIGINT(20) UNSIGNED` | Primary key. |
| `user_id` | `BIGINT(20) UNSIGNED` | WordPress User ID (Unique). |
| `gender` | `VARCHAR(20)` | `male` or `female`. |
| `location` | `VARCHAR(100)` | Primary residence / country. |
| `origin` | `VARCHAR(100)` | Ethnic background / nationality. |
| `birth_date` | `DATE` | Date of birth (used for age calculation). |
| `height_cm` | `INT` | Height in centimeters. |
| `religion` | `VARCHAR(100)` | Religious affiliation. |
| `modesty` | `VARCHAR(100)` | Religiosity / modesty level. |
| `education` | `VARCHAR(100)` | Highest education level. |
| `job` | `VARCHAR(100)` | Profession / occupation. |
| `marital_status` | `VARCHAR(50)` | Single, divorced, widowed, etc. |
| `smoking` | `VARCHAR(20)` | Smoking habit. |
| `drinking` | `VARCHAR(20)` | Drinking habit. |
| `languages` | `TEXT` | Languages spoken (comma-separated). |
| `pref_gender` | `VARCHAR(20)` | Target match gender (`male` or `female`). |
| `pref_location` | `VARCHAR(100)` | Target preferred location. |
| `pref_origin` | `VARCHAR(100)` | Target preferred origin. |
| `preferred_age_min` | `INT` | Target minimum age preference. |
| `preferred_age_max` | `INT` | Target maximum age preference. |
| `user_type` | `VARCHAR(50)` | Tier: `free`, `monthly`, `one_on_one`, `event`. |
| `status` | `VARCHAR(20)` | Profile status (`active`, `paused`). |
| `created_at` | `DATETIME` | Profile creation timestamp. |
| `updated_at` | `DATETIME` | Profile last update timestamp. |

### 3.2 `wp_matches`
Stores bi-directional candidate match pairs, compatibility scores, and approval/response states.

| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | `BIGINT(20) UNSIGNED` | Primary key. |
| `user_one_id` | `BIGINT(20) UNSIGNED` | Lower User ID ($\min(\text{ID}_A, \text{ID}_B)$). |
| `user_two_id` | `BIGINT(20) UNSIGNED` | Higher User ID ($\max(\text{ID}_A, \text{ID}_B)$). |
| `initiator_user_id` | `BIGINT(20) UNSIGNED` | User ID who triggered match generation. |
| `score` | `FLOAT` | Calculated match compatibility score (0 to 100). |
| `status` | `VARCHAR(50)` | Status: `pending_review`, `approved`, `matched`, `rejected`, `expired`. |
| `user_one_response` | `VARCHAR(20)` | Response: `pending`, `accepted`, `rejected`. |
| `user_two_response` | `VARCHAR(20)` | Response: `pending`, `accepted`, `rejected`. |
| `contact_revealed` | `TINYINT(1)` | `1` when mutual acceptance occurs, else `0`. |
| `cycle_month` | `VARCHAR(10)` | Billing cycle marker (e.g. `2026-08`). |
| `created_at` | `DATETIME` | Match creation timestamp. |
| `updated_at` | `DATETIME` | Match last status update timestamp. |

> [!IMPORTANT]
> **Canonical Pair Enforcement Rule**:
> In `wp_matches`, `user_one_id` MUST always equal $\min(\text{ID}_A, \text{ID}_B)$ and `user_two_id` MUST always equal $\max(\text{ID}_A, \text{ID}_B)$ to prevent duplicate inverse records. Quota and intent ownership are tracked via `initiator_user_id`.

### 3.3 `wp_usermeta` Keys
- `phone_number` — Primary contact phone.
- `user_social_links` — Social handle or contact link.
- `user_marital_status` — Detailed marital status.
- `user_education` — Detailed education level.
- `user_prayer` — Prayer habits.
- `user_photo1`, `user_photo2`, `user_photo3` — Uploaded profile photo URLs.
- `pref_additional_info` — Freeform text description of partner preferences.
- `user_type` — Membership tier cache (`free`, `monthly`, `one_on_one`, `event`).
- `cycle_matches_count` — Monthly match count incremented upon admin match approval.
- `cycle_start_date` — Current billing cycle start timestamp.

---

## 4. Plugin Shortcodes Reference

| Shortcode Tag | Handler Class | Description & Parameters | Example Usage |
| :--- | :--- | :--- | :--- |
| `[matchmaker_member_portal]` | `Match_Flow_Handler` | Main unified interactive Member Portal dashboard with tabbed navigation (Profile, Matches), 5-state match review flow, unread badge counter, and AJAX actions. | `[matchmaker_member_portal]` |
| `[az_profile]` | `Match_Flow_Handler` | Backward-compatible alias for the Member Portal dashboard. | `[az_profile]` |
| `[matchmaking_form]` | `Form_Handler` | 2-step registration & questionnaire form. Accepts optional `redirect` attribute. | `[matchmaking_form redirect="/dashboard/"]` |
| `[matchmaking_field]` | `Field_Generator` | Renders a standalone questionnaire input field by name. Requires `name` attribute. | `[matchmaking_field name="height_cm"]` |
| `[logout_url]` | `Auth_Redirects` | Outputs a safe WordPress logout URL. Accepts optional `redirect` attribute. | `<a href="[logout_url]">Logout</a>` |

---

## 5. Detailed Feature Architecture

### Feature 1: Member Portal & 5-State Interactive Match Flow
- **Class**: [`includes/Match_Flow_Handler.php`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/includes/Match_Flow_Handler.php)
- **Assets**: [`assets/css/member-portal.css`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/assets/css/member-portal.css), [`assets/js/member-portal.js`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/assets/js/member-portal.js)
- **Functionality**:
  1. **Top Header Navigation**: Dynamic user photo (no Gravatar), rounded initial placeholder fallback, notification bell counter, settings redirect (`/dashboard/`), tab switcher.
  2. **Profile Tab (`render_profile_tab_html`)**: Displays member details, dynamic tier badge (`Monthly Member`, `1-on-1 VIP Member`, `Event Member`, `Free Member`), match statistics, lifestyle details, and partner preferences.
  3. **Matches Tab (`render_matches_tab_view`)**: Keeps the `.mm-flow-container` white background card intact across all states. Evaluates `$user_type`:
     - Non-premium (`free`, `event`): Renders Free Tier Upgrade Banner (`render_free_upsell_html`).
     - Premium (`monthly`, `one_on_one`): Renders 5-State Interactive Match Flow (`render_matches_tab_html`).
  4. **5-State Interactive Match Flow**:
     - **Step 1 (Dashboard Discovery)**: Target match hero card, location, languages, quota text, time remaining counter.
     - **Step 2 (Full Profile Review)**: Full candidate bio, physical attributes, religious habits, background info, Accept / Decline CTA buttons.
     - **Step 3 (Match Status & Responses)**: Dynamically loads **Your Response** (`✓ Accepted`, `✕ Declined`, `ⓘ Pending`) and **Their Response** (`✓ Accepted`, `✕ Declined`, `⏳ Waiting`) directly from DB.
     - **Step 4 (Decline Confirmation Modal)**: Confirmation step with "Keep Match" vs "Decline Match" CTAs.
     - **Step 5 (Mutual Match Contact Reveal)**: Revealed candidate phone number, email address, and social links upon mutual acceptance.
  5. **AJAX Endpoints**:
     - `wp_ajax_mm_submit_match_response`: Updates `user_one_response` / `user_two_response` in DB, flushes transients, and returns `next_step` (Step 3 or Step 5 on mutual match).
     - `wp_ajax_mm_reload_portal_tab`: Dynamically reloads tab HTML via AJAX.

### Feature 2: Matching Engine & Bi-Directional Scoring
- **Class**: [`includes/Matching_Engine.php`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/includes/Matching_Engine.php), [`includes/functions.php`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/includes/functions.php)
- **Functionality**:
  1. **Triggers**: Profile Save/Update (Monthly tier), Weekly Recurring Cron, Admin Manual Trigger.
  2. **Hard Gates**: Opposite gender, age range criteria ($min \le age \le max$), active status, no existing match pair in `wp_matches`.
  3. **6-Point Scoring**: Calculates compatibility scores based on Location, Origin, Religion, Modesty, Lifestyle, and Education.
  4. **Billing Quota Gating**: 10 approved matches per billing cycle limit. Admin match approval increments `cycle_matches_count`.
  5. **Background Execution**: Runs asynchronously via Action Scheduler (`as_schedule_single_action` / `mm_run_async_matching_job`).

### Feature 3: 2-Step Registration Questionnaire & Standalone Fields
- **Class**: [`includes/Form_Handler.php`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/includes/Form_Handler.php), [`includes/Field_Generator.php`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/includes/Field_Generator.php)
- **Assets**: [`assets/css/matchmaking-form.css`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/assets/css/matchmaking-form.css), [`assets/js/matchmaking-form.js`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/assets/js/matchmaking-form.js)
- **Functionality**:
  1. Renders 37 questionnaire fields across 2 step panels (`[matchmaking_form]`).
  2. Hydrates existing values for returning members.
  3. Handles media uploads for up to 3 profile photos via WP Media Library.
  4. `[matchmaking_field name="..."]` allows embedding single fields into Elementor or custom pages.

### Feature 4: Elementor Pro Free User Registration
- **Class**: [`includes/Free_Reg_Handler.php`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/includes/Free_Reg_Handler.php)
- **Functionality**:
  1. Decouples validation (`elementor_pro/forms/validation`) from creation (`elementor_pro/forms/new_record`).
  2. Validates email, password length, and phone format inline.
  3. Creates user, assigns PMPro Level 2 (Free), sets `user_type = free`, and safely initiates a logged-in user session.

### Feature 5: PMPro Membership Tier Synchronization
- **Class**: [`includes/PMPro_Sync.php`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/includes/PMPro_Sync.php)
- **Functionality**:
  1. Maps PMPro Level IDs (`Level 3 = monthly`, `Level 4/5 = one_on_one`, `Level 6 = event`, `Level 2 = free`).
  2. Listens to `pmpro_after_change_membership_level` hook and updates `user_type` in `wp_usermeta` and `wp_matchmaking_pool`.
  3. Enqueues async matching job upon upgrading to Level 3 (`monthly`).

### Feature 6: Admin Portal, Candidate Browser & Settings
- **Class**: [`includes/Admin_Portal.php`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/includes/Admin_Portal.php)
- **Assets**: [`assets/css/admin-matchmaker.css`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/assets/css/admin-matchmaker.css), [`assets/js/admin-matchmaker.js`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/assets/js/admin-matchmaker.js)
- **Functionality**:
  1. Registers top-level **Matchmaking** WP-Admin menu (`admin_menu` priority 30).
  2. **Candidate Pool Browser**: Search, gender/location/status filters, profile detail modal view.
  3. **Matches List**: Status filtering, search by candidate name/email, direct link to single match view (`view_match=ID`).
  4. **Single Match View**: Dual candidate side-by-side profile cards, score preview, directional user responses (`user_one_response`, `user_two_response`), and match approval/rejection actions.
  5. **Manual Matchmaker (`manual_match=USER_ID`)**: Advanced candidate filter pre-population, manual pair creation with default `pending_review` status.
  6. **Info-Only Rule**: Matches involving a `free` or `event` candidate hide approval buttons (`Info Only (Free/Event)` status).
  7. **Admin Settings Page**: Match approval email subject/body editor with placeholder legend and **Available Matchmaker Plugin Shortcodes** reference table.

### Feature 7: Email Notifications & Heartbeat Polling
- **Class**: [`includes/Notification_Manager.php`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/includes/Notification_Manager.php)
- **Functionality**:
  1. Listens to match approval events and dispatches HTML emails using `wp_mail()`.
  2. Interpolates template placeholders (`{user_name}`, `{candidate_name}`, `{candidate_age}`, `{candidate_location}`, `{dashboard_url}`).
  3. Maintains transient-cached unread notification counters per user.
  4. Hooks into WordPress Heartbeat API (`heartbeat_received`) to push real-time unread match counts (`mm_check_notifications`).
  5. Action Scheduler recurring daily task (`mm_daily_check_weekly_matching_queue`) checks idle members ($\ge 7$ days) and triggers matching.

### Feature 8: Authentication Overrides & Login Styling
- **Class**: [`includes/Auth_Redirects.php`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/includes/Auth_Redirects.php)
- **Functionality**:
  1. Intercepts `login_redirect`: Routes admins to `/wp-admin/` and subscribers to `/dashboard/`.
  2. Intercepts `pmpro_confirmation_url`: Redirects completed checkout sessions to `/personal-matchmaking-questionnaire/`.
  3. Customizes `wp-login.php` with custom typography (Marcellus SC), styled login cards, and signup links.

---

## 6. Complete Hook & Filter Registry

| Target Hook / Filter | Priority | Component File | Purpose |
| :--- | :---: | :--- | :--- |
| `admin_init` | 10 | `DB_Migrator.php` | Runs `dbDelta()` schema migrations when `mm_matchmaking_db_version` is updated. |
| `admin_menu` | 30 | `Admin_Portal.php` | Registers Top-Level **Matchmaking** admin menu, pool browser, matches list, and settings pages. |
| `pmpro_after_change_membership_level` | 10 | `PMPro_Sync.php` | Updates `user_type` in meta and pool; queues match job on level 3 upgrade. |
| `elementor_pro/forms/validation` | 10 | `Free_Reg_Handler.php` | Validates email, password length, and phone format inline. |
| `elementor_pro/forms/new_record` | 10 | `Free_Reg_Handler.php` | Creates user, assigns PMPro level 2 (Free), logs user in safely. |
| `wp_ajax_mmf_submit_form` | 10 | `Form_Handler.php` | Ingests 37 questionnaire fields into pool + usermeta, handles photo uploads. |
| `wp_ajax_mm_submit_match_response` | 10 | `Match_Flow_Handler.php` | Ingests member accept/decline decision for a match, updates DB, flushes transients. |
| `wp_ajax_mm_reload_portal_tab` | 10 | `Match_Flow_Handler.php` | Reloads Member Portal tab contents dynamically via AJAX. |
| `heartbeat_received` | 10 | `Notification_Manager.php` | Responds to WP Heartbeat API with real-time unread match badge count. |
| `login_redirect` | 10 | `Auth_Redirects.php` | Routes admins to `/wp-admin/` and subscribers to `/dashboard/`. |
| `pmpro_confirmation_url` | 10 | `Auth_Redirects.php` | Redirects completed checkouts to `/personal-matchmaking-questionnaire/`. |
| `mm_run_async_matching_job` | 10 | `Matching_Engine.php` | Action Scheduler background worker executing bi-directional SQL match algorithm. |
| `mm_daily_check_weekly_matching_queue` | 10 | `Notification_Manager.php` | Action Scheduler daily recurring task checking idle members ($\ge 7$ days). |

---

## 7. Structural Refactoring & Reorganization Checklist

When refactoring or reorganizing the plugin structure in future tasks, adhere strictly to the following checklist to prevent missing features or introducing regressions:

- [ ] **Preserve Canonical Pair Logic**: Ensure `user_one_id = min(A, B)` and `user_two_id = max(A, B)` in all database queries.
- [ ] **Preserve Shortcode Tag Compatibility**: Retain `[matchmaker_member_portal]`, `[az_profile]`, `[matchmaking_form]`, `[matchmaking_field]`, and `[logout_url]`.
- [ ] **Preserve Container Wrappers**: Keep `.mm-portal-canvas` and `.mm-flow-container` parent white background card containers intact across all portal tab switches.
- [ ] **Preserve Dynamic User Responses**: Ensure Step 3 in the match flow evaluates `$my_response` and `$their_response` dynamically from DB (`wp_matches`).
- [ ] **Preserve Billing Quota Gating**: Verify that match approval increments `cycle_matches_count` and enforces the 10 match limit for monthly subscribers.
- [ ] **Preserve Info-Only Rule**: Verify that matches involving `free` or `event` candidates hide admin approval buttons across all admin views.
- [ ] **Preserve JS Event Delegation**: Avoid inline `onclick` handlers; maintain data attributes (`data-mm-action`, `data-mm-redirect`, `data-step`) handled by `member-portal.js`.
- [ ] **Preserve Action Scheduler Triggers**: Ensure async matching jobs use `as_schedule_single_action()` via `mm_enqueue_user_matching_job()`.
