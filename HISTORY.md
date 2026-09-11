# Matchmaker Plugin — Execution & Feature History Log

This document maintains a chronological, step-by-step history of all features, architecture refactors, design system implementations, and operational rules built in this plugin.

---

## Chronological Task & Feature Log

### Task 1: Procedural Codebase Refactoring to OOP Architecture
- **Objective**: Refactor legacy procedural PHP files into a clean, modern, class-based PSR-4 PHP 8.1+ structure with strict typing (`declare(strict_types=1);`).
- **Implemented**:
  - `matchmaker.php`: Bootstrap entrypoint with singleton initialization.
  - `DB_Migrator.php`: Database schema installer (`wp_matchmaking_pool` & `wp_matches`).
  - `Form_Handler.php` & `Field_Generator.php`: Shortcode handlers (`[matchmaking_form]`, `[matchmaking_field]`), 2-step form rendering, field hydration, and secure AJAX submission.
  - `Matching_Engine.php`: Asynchronous bi-directional matchmaking engine via Action Scheduler with 6-point flexible scoring.
  - `Admin_Portal.php`: Top-level admin management portal, candidate pool browser table, and user profile detail views.
  - `PMPro_Sync.php`: Syncs Paid Memberships Pro membership levels (`monthly`, `one_on_one`, `event`, `free`) to usermeta and pool records.
  - `Auth_Redirects.php`: Managed login/logout redirects, subscriber admin bar visibility, and PMPro confirmation page redirects.
  - `Free_Reg_Handler.php`: Decoupled Elementor Pro Form validation and user creation hooks.

---

### Task 2: Separate Assets Infrastructure (`assets/`)
- **Objective**: Implement clean asset management and conditional enqueueing for frontend and admin scripts.
- **Implemented**:
  - Created `/assets/css/` and `/assets/js/`.
  - `assets/css/matchmaking-form.css`: Frontend questionnaire styling, custom select boxes, step indicators, photo previews.
  - `assets/js/matchmaking-form.js`: Multi-step navigation, client-side validation, AJAX submission.
  - `assets/css/admin-matchmaker.css`: Admin candidate pool table, badges, status colors, and card layouts.
  - `assets/js/admin-matchmaker.js`: Admin rejection dialogs and interactive elements.
  - Conditional asset loading: Enqueued form assets only when shortcodes are detected in post content.

---

### Task 3: PMPro Login Page Design Integration
- **Objective**: Port custom login styling and JavaScript from legacy code into the OOP framework.
- **Implemented**:
  - Integrated `custom_pmpro_login_page_design()` into `Auth_Redirects.php`.
  - Added Marcellus SC / Poppins / Inter font styling for PMPro login cards.
  - Added subtitle *"Please enter your email and password below."* and *"Sign Into Your Account"* title.
  - Added *"Don't have an account? Sign Up"* callout linking dynamically to PMPro Level 3 checkout.

---

### Task 4: Auto-Matching Engine Refinement & Profile Form Gating
- **Objective**: Fix auto-matching triggers, type safety, and fallback execution.
- **Implemented**:
  - Gated auto-matching **exclusively** to 2-step profile form submissions/updates for `monthly` users.
  - Removed auto-matching trigger on PMPro membership level checkout (requiring users to fill out their profile form first).
  - Added mixed type casting in `Matching_Engine::handle_as_matching_job()` to resolve strict PHP 8.1 `TypeError` issues with Action Scheduler parameter types.
  - Implemented synchronous fallback execution if Action Scheduler functions are missing in the host environment.

---

### Task 5: Admin Real-Time Scoring & Real-Time Pool Checks
- **Objective**: Ensure clicking "Run Auto-Match Scoring" in the Admin Pool Portal provides instant, visible results.
- **Implemented**:
  - Updated `Admin_Portal.php` so clicking the manual scoring button executes `Matching_Engine::run_matching_for_user()` synchronously in real-time.
  - Added pool profile existence check (`in_pool === 0` error notice if profile form not filled out yet).

---

### Task 6: Test Data Seeder (`Test_Seeder.php`) & Admin Seeder Button
- **Objective**: Provide an easy way for admins to generate candidate data and matches for testing.
- **Implemented**:
  - Created `Test_Seeder.php` generating 10 candidate profiles across all membership tiers (`monthly`, `one_on_one`, `free`, `event`).
  - Added **`+ Generate 10 Test Users & Matches`** CTA button on the Admin Candidate Pool Browser page.

---

### Task 7: Free / Event Candidate Info-Only Rule & Approval Blockade
- **Objective**: Enforce business rule that matches involving `free` or `event` tier users cannot be approved.
- **Implemented**:
  - Updated `Admin_Portal.php` to hide the **Approve** button for matches where EITHER User 1 OR User 2 has `free` or `event` tier.
  - Displayed `Info Only (Free/Event)` label instead.
  - Added server-side validation in `handle_admin_actions()` blocking direct URL approval for Free/Event matches.

---

### Task 8: Global "Matches" Sub-Menu Page & Exact Display Name Responses
- **Objective**: Provide a central match management interface and clearer user response labeling.
- **Implemented**:
  - Registered sub-menu page **Matches** (`admin.php?page=matchmaking-matches`).
  - Minimalistic list view with search filter (user/candidate name or email) and status filter (`All`, `Pending Review`, `Approved`, `Matched`, `Admin Rejected`, `User Rejected`).
  - Formatted user responses with exact participant display names (e.g., `Ahmad Al-Mansoor: Accept`).

---

### Task 9: Design System Documentation (`Design.md`) & Single Match Detail View
- **Objective**: Establish design system guidelines and create a side-by-side dual profile comparison page.
- **Implemented**:
  - Created `Design.md` in root directory detailing color tokens (`#CC723F`, `#F8F2ED`, `#829067`, `#A4302A`), typography rules (`Cormorant SC` / `Inter`), and match status lifecycle definitions.
  - Updated `assets/css/admin-matchmaker.css` to use official design tokens.
  - Added **Single Match View** (`view_match=ID`): Dual-column side-by-side grid comparing complete profiles, search preferences, photos, and responses of both matched participants.

---

### Task 10: Manual Matchmaker Feature with Advanced Filters
- **Objective**: Allow admins to manually pair a target user with any candidate in the pool using multi-criteria filters.
- **Implemented**:
  - Added **`+ Manual Match`** CTA button on Single User Detail page header.
  - Created dedicated Manual Match view (`manual_match=USER_ID`) with target user summary.
  - Advanced filter form pre-populated with target user's saved preferences (Gender, Age Min/Max, Location, Religion, Modesty Level, Origin).
  - Search query excludes target user and candidates already paired in `wp_matches`.
  - Candidate results display flexible compatibility score (0–6 pts) and a **`+ Create Match`** action button.
  - Manual matches created with default status `pending_review` (`match_source = 'admin_manual'`).

---

### Task 11: Feature Context Directory (`context/`) & Master Index (`PROJECT_BUILD.md`)
- **Objective**: Maintain modular feature context documentation for agentic AI context retrieval.
- **Implemented**:
  - Created `context/matching_engine.md`, `context/form_handler.md`, `context/admin_portal.md`, `context/manual_match.md`, `context/pmpro_sync.md`, `context/auth_redirects.md`, `context/free_registration.md`.
  - Consolidated master index and architecture in `PROJECT_BUILD.md`.

---

### Task 12: Repository Cleanup & Standardized Build Documentation (`BUILD_PLAN.md` & `HISTORY.md`)
- **Objective**: Remove obsolete files and establish project tracking standards.
- **Implemented**:
  - Removed `old_files/` directory and temporary `update.md`.
  - Created `BUILD_PLAN.md` for tracking active tasks.
  - Created `HISTORY.md` (this file) for complete chronological execution logging.

---

### Task 13: Action Scheduler Verification, Async Queue Hooks & Admin Settings Sub-Page
- **Objective**: Verify Action Scheduler installation, configure async matching triggers, create Settings sub-page, and build a configurable recurring idle queue.
- **Implemented**:
  - Verified Action Scheduler in `vendor/woocommerce/action-scheduler`.
  - Added fallback loader in `matchmaker.php` ensuring Action Scheduler is loaded reliably.
  - Enqueued asynchronous Action Scheduler jobs (`as_enqueue_async_action`) on profile creation (`form_submit`), profile update (`form_update`), and admin candidate pool browser trigger (`admin_manual_trigger`).
  - Registered `Matchmaking -> Settings` sub-menu page (`admin.php?page=matchmaking-settings`).
  - Implemented configurable options: `mm_auto_match_recurrence_days` (default 7 days) and `mm_max_candidates_per_run` (default 10).
  - Updated `Matching_Engine::check_weekly_queue()` to dynamically check monthly member idle duration against the admin-configured recurrence threshold.

---

### Task 14: Auto-Matching Engine SQL Hard Gate Refactoring for Flexible Preferences
- **Objective**: Resolve issue where auto-matching schedule completion failed to create matches despite matching candidates existing on the Manual Match page.
- **Implemented**:
  - Identified root cause in `Matching_Engine::query_candidates()`: strict `FIND_IN_SET` failed on `'Any'`, empty string `''`, NULL, or comma-separated lists containing spaces (e.g. `'Riyadh, Jeddah'`).
  - Refactored bi-directional SQL hard gates for `pref_location`, `pref_religion`, and `pref_modesty` using `REPLACE(col, ', ', ',')` and explicit `'Any'` / empty checks.
  - Synchronized candidate search query in `Admin_Portal.php` Manual Matchmaker view.

---

### Task 15: Modesty Option Alignment, Select Placeholder Disabling & Bi-Directional Manual Match Alignment
- **Objective**: Align modesty option dictionary, disable select placeholder items, and enforce bi-directional candidate preferences on the Manual Matchmaker page.
- **Implemented**:
  - Added `'Modest'` to `Field_Generator::options_modesty()`.
  - Updated `Field_Generator::select()`, `matchmaking-form.js`, and `Form_Handler.php` so placeholder options starting with `'Select'` are rendered with `disabled` attribute, blocked in JS, and sanitized to empty string `""` on AJAX save.
  - Resolved candidate matching behavior for User `283496463` (`Khobar`) vs Candidate `283496456` (`Riyadh` only preference): Candidate `283496456` explicitly specified `pref_location = 'Riyadh'`, rejecting partners in `Khobar`.
  - Enforced Candidate B preference gates in `Admin_Portal::render_manual_match_view()` so Manual Match suggestions match the Auto-Matching Engine bi-directional requirements 100%.

---

### Task 16: Centralized Daily Recurring Schedule Registration via Activation & Deactivation Hooks
- **Objective**: Standardize the registration of the daily recurring Action Scheduler queue inspector so it registers cleanly once on plugin activation.
- **Implemented**:
  - Created `Matching_Engine::activate()` and `Matching_Engine::deactivate()`.
  - Hooked activation to `register_activation_hook` in `matchmaker.php` to register the daily recurring Action Scheduler action (`mm_daily_check_weekly_matching_queue`) upon plugin activation.
  - Hooked deactivation to `register_deactivation_hook` in `matchmaker.php` to unschedule all recurring actions when the plugin is deactivated.

---

### Task 17: Architectural Loose Coupling & Dependency Audit
- **Objective**: Audit plugin files and feature components for loose coupling, modularity, and maintainability.
- **Implemented**:
  - Audited all 10 PHP classes in `includes/`: `DB_Migrator`, `Field_Generator`, `Form_Handler`, `Matching_Engine`, `Admin_Portal`, `PMPro_Sync`, `Auth_Redirects`, `Free_Reg_Handler`, `Test_Seeder`.
  - Confirmed strict separation of concerns: matching engine is isolated from UI, field generator is isolated from processing logic, and integrations (PMPro, Elementor, Auth) operate via standard WP hooks.
  - Verified that features can be modified, disabled, or removed by changing a single line in `matchmaker.php` without causing cascading breaking changes across the system.

---

### Task 18: Profile Form Action Scheduler Job Enqueueing Fix
- **Objective**: Fix issue where submitting or updating the 2-step profile form did not create an Action Scheduler job.
- **Implemented**:
  - Identified 2 root causes:
    1. Overly strict `$user_type === 'monthly'` condition in `Form_Handler.php`: If PMPro level lookup returned free/null despite usermeta tier being monthly, the enqueue call was skipped.
    2. AS Async Deduplication: `as_enqueue_async_action` ignored new jobs if an identical action was already pending.
  - Updated `Form_Handler.php` to resolve `$effective_type` (checking usermeta, PMPro, and admin capabilities).
  - Refactored `mm_enqueue_user_matching_job()` in `Matching_Engine.php` to use `as_schedule_single_action(time(), ...)` with stale action clearing via `as_unschedule_all_actions()`.

---

### Task 19: Member Dashboard, 5-State Interactive Match Flow, Heartbeat Notifications & Email Approval Alerts
- **Objective**: Implement unified tabbed Member Dashboard (`[matchmaker_member_portal]`), 5-State Interactive Match Flow (`match-steps.php`), Heartbeat API Notification System (`new-features.md`), and Email Approval Notifications with Admin Template Editor.
- **Implemented**:
  - Created `Notification_Manager.php`: Handles WordPress Heartbeat pulse processing, 60s transient caching (`mm_unread_count_{$user_id}`), and automated HTML email dispatch on match approval.
  - Created `Match_Flow_Handler.php`: Renders shortcode `[matchmaker_member_portal]`, Profile tab, Matches tab (5-State view step views for premium members or Free tier upsell banner), and handles AJAX endpoint `wp_ajax_mm_submit_match_response` for match Accept & Decline actions.
  - Updated `Admin_Portal.php`: Added Email Subject input and Rich Email Template Editor (`wp_editor`) in **Matchmaking -> Settings** with dynamic placeholders (`{user_name}`, `{candidate_name}`, `{candidate_age}`, `{candidate_location}`, `{dashboard_url}`). Triggered email notifications upon match approval.
  - Updated `Matching_Engine.php`: Added 7-day auto-expiration worker in `check_weekly_queue()` to auto-reject unanswered matches after 168 hours.
  - Created `member-portal.css` and `member-portal.js`: Handled tab navigation, 5-state view step switching, AJAX match response, Heartbeat bell badge counter, and top-right slide-out toast alerts (`#mm-toast-box`).

---

### Task 22: Dual Step 1 CTAs, Inside-Canvas Footer Dock, Back Arrow Navigation, AJAX Tab Reloading & Mobile Responsiveness
- **Objective**: Implement 2 CTAs on Step 1 ("View Match" & "View Status"), position Step 2 footer dock inside canvas, render top back arrow navigation, enable dynamic AJAX tab content reloading, ensure 100% mobile responsiveness, and eliminate green hover/active button colors.
- **Implemented**:
  - Updated `Match_Flow_Handler.php`:
    - Added dual CTAs (`View Match →` & `View Status`) to Step 1 discovery card.
    - Moved Step 2 footer action dock inside the `.mm-portal-canvas` container at the bottom.
    - Added dynamic state check to Step 2 footer: shows `Decline Match` & `Accept Match →` if pending, or `View Status →` if already responded.
    - Added top navigation back button (`← Back to Matches`) to Step 2 profile view.
    - Added `handle_ajax_reload_tab()` endpoint (`wp_ajax_mm_reload_tab_content`) to re-render tab HTML dynamically via AJAX.
  - Updated `member-portal.css`:
    - Changed `.floating-action-footer` to `position: relative; width: 100%; border-radius: 0 0 36px 36px;` inside the canvas bottom.
    - Removed all green hover/active colors from buttons, replacing them with primary brand color `#CC723F` or primary hover `#b6602f`.
    - Added comprehensive `@media (max-width: 900px)`, `@media (max-width: 768px)`, and `@media (max-width: 480px)` responsive breakpoints for mobile & tablet support.
  - Updated `member-portal.js`:
    - Added `MM_Portal.goBackStep()` navigation helper with history stack.
    - Updated `MM_Portal.switchTab()` to trigger `MM_Portal.reloadTabAJAX()` for dynamic content updates on tab clicks.

---

### Task 23: Database Scalability, Dynamic PMPro Plan Connector, Configurable Quotas & Expirations, and Automated PHPUnit Test Suite
- **Objective**: Scale the plugin architecture to efficiently support large candidate pools, eliminate all hardcoded static values (membership plan IDs, monthly match quotas, match expiry windows, page routing slugs, Elementor form ID), enhance the admin settings interface, and establish automated PHPUnit test coverage.
- **Implemented**:
  - `src/Core/DBMigrator.php`:
    - Added composite database indexes to `wp_matchmaking_pool` (`idx_active_gender_type (is_active, gender, user_type)`) and `wp_matches` (`idx_pair_status (user_one_id, user_two_id, status)`, `idx_status_updated (status, updated_at)`, `idx_initiator_created (initiator_user_id, created_at)`).
    - Bumped schema version to `2.3.0`.
  - `src/Core/PMProSync.php`:
    - Replaced hardcoded switch with dynamic option lookup `mm_pmpro_tier_mapping` and fallback defaults.
    - Added helper methods `get_levels_for_tier()`, `is_tier_level()`, and `get_primary_level_for_tier()`.
  - `src/Admin/AdminPortal.php`:
    - Added **PMPro Membership Plan Connector** table: dynamically reads all registered PMPro levels and allows admins to assign tiers using dropdowns.
    - Added **Matchmaking Quota & Expiry Rules**: configurable Max Matches per Cycle (`mm_max_cycle_matches`), Match Expiry Days (`mm_match_expiry_days`), Idle Recurrence Days (`mm_auto_match_recurrence_days`), and Max Candidates per Run (`mm_max_candidates_per_run`).
    - Added **Page Routing & Form Integration**: WordPress page dropdown selectors (`wp_dropdown_pages`) for Dashboard, Questionnaire, Account, Checkout, Events, and Elementor Free Registration Form ID.
    - Updated Candidate Profile header and manual matchmaker view to display dynamic monthly quota.
  - `src/Repository/MatchRepository.php` & `src/Service/MatchService.php`:
    - Updated `approve_match()` to enforce dynamic cycle quota limits.
    - Updated `check_7day_match_expirations()` and deadline calculations (`find_all_matches_for_user`, `get_match_stats`) to use dynamic match review expiry days.
  - `src/Core/FreeRegHandler.php`:
    - Dynamic Elementor Form ID matching and dynamic Free tier PMPro level assignment with normalized `'free'` user_type meta.
  - `src/Service/ProfileService.php` & `src/Frontend/AuthController.php`:
    - Dynamic page URL resolvers checking configured WordPress page IDs with `get_permalink()` before falling back to defaults.
  - `src/Core/MatchingEngine.php`:
    - Batch chunking (100 users per batch) in idle queue Action Scheduler job and dynamic candidates limit.
  - `src/View/frontend/portal/tab-matches.php` & `tab-profile.php`:
    - Dynamic checkout URLs and dynamic countdown text with 100% design and styling preservation.
  - `tests/`:
    - Created comprehensive test bootstrap (`tests/bootstrap.php`), automated test runner (`tests/run_tests.php`), and full test suite (`DBMigratorTest.php`, `SettingsAndPlanMappingTest.php`, `QuotaAndExpiryTest.php`, `MatchingEngineTest.php`, `EndToEndFlowTest.php`).
    - Verified all 12 tests pass with 0 failures and 0 errors.

---

### Task 24: Environment Mode System, Structured Event & Notification Logging, 3-Tab Match Logs Hub, and MVC View Separation
- **Objective**: Implement Environment Mode (Test Mode vs Live Mode) with a safe data reset tool; implement centralized structured activity and email transmission logging in dedicated `wp_matchmaker_logs` table; update the Match Logs page with 3 interactive tabs ("Match Logs", "Notification & Email Logs", "Candidate Gate Debugger") and a JSON/Email inspection modal; modularize all Member Portal 5-step views and Admin Portal views into dedicated pure PHP template files.
- **Implemented**:
  - `src/Core/DBMigrator.php`:
    - Added `wp_matchmaker_logs` table schema (tracking `log_type`, `event_type`, `title`, `message`, `details_json`, `reference_id`, `user_id`, `recipient`, `status`, `created_at`).
    - Bumped database schema version to `2.4.0`.
  - `src/Repository/MatchRepository.php`:
    - Added Environment Mode getters (`get_environment_mode()`, `is_test_mode()`).
    - Added `reset_test_matchmaking_data()` method: purges `wp_matches`, `wp_matchmaker_notifications`, `wp_matchmaker_logs`, and user cycle match counters while preserving `wp_matchmaking_pool` records.
    - Added structured logging CRUD operations (`log_event()`, `get_logs()`, `get_logs_count()`, `get_log_by_id()`).
  - Logging Integration across Core & Service Layers:
    - `MatchingEngine.php`: Logs engine execution runs, skips (due to inactivity, ineligible tier, or existing mutual match), candidate evaluations, and generated matches.
    - `MatchService.php`: Logs admin approvals, quota/tier approval blockades, admin rejections, and member accept/decline responses.
    - `NotificationService.php`: Logs in-app notification creations and transactional HTML email dispatches with full recipient and body metadata.
  - Frontend Member Portal Step View Separation (`src/View/frontend/portal/steps/`):
    - `step-1-discovery.php`: Step 1 Discovery card.
    - `step-2-profile.php`: Step 2 Full candidate profile review & dock.
    - `step-3-waiting.php`: Step 3 Waiting for candidate response.
    - `step-4-decline.php`: Step 4 Match declined confirmation modal card.
    - `step-5-contact.php`: Step 5 Mutual match contact reveal.
    - Refactored `tab-matches.php` to cleanly include modular step templates.
  - Admin Portal MVC Architecture & Modular Views (`src/View/admin/`):
    - `pool/pool-list.php`: Candidate Pool list view table.
    - `pool/user-single.php`: Candidate profile detail view.
    - `pool/manual-match.php`: Manual matchmaker candidate search and pair tool.
    - `matches/matches-list.php`: Matches Queue table with filters.
    - `matches/match-single.php`: Dual-profile comparison review view.
    - `settings/settings.php`: Settings view with Environment Mode toggle, Test Mode Reset danger card, PMPro matrix, Quotas, Dynamic page routing, and Email notification templates.
    - `logs/logs.php`: 3-Tab hub container (`match_logs`, `notification_logs`, `debugger`).
    - `logs/tab-match-logs.php`: Match engine and lifecycle logs table.
    - `logs/tab-notification-logs.php`: Notification and email transmission logs table.
    - `logs/tab-debugger.php`: Candidate gate evaluation debugger tool.
    - `logs/modal-log-detail.php`: Interactive modal overlay for detail view and HTML email preview.
  - `AdminPortal.php`:
    - Refactored to lean controller delegating rendering to view files.
    - Added handlers for `mm_environment_mode` save and `mm_reset_test_data` reset POST action.
  - Assets (`admin-matchmaker.css` & `admin-matchmaker.js`):
    - Added modal dialog styles, backdrop blur, test mode reset danger styling, and JavaScript modal controller with JSON pretty-printing and rendered email preview.
  - Automated Testing (`tests/`):
    - Added `ModeAndResetTest.php` and `LoggingTest.php`.
    - Verified all 18 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 25: Manual Matchmaker Search Filtering, Compatibility Score Ranking & Event Logging
- **Objective**: Ensure the Manual Matchmaker tool (`manual_match=USER_ID`) accurately searches the candidate pool using advanced filter inputs (Gender, Age Min/Max, Location, Origin, Religion, Modesty), automatically ranks candidates by compatibility score in descending order, provides a one-click "+ Create Match Pair" action, and logs all search and match creation activities into `wp_matchmaker_logs`.
- **Implemented**:
  - `src/Repository/MatchRepository.php`:
    - Refactored `get_manual_match_candidates()`:
      - Fixed MySQL strict mode `Incorrect DATE value: ''` error in age clause by removing invalid string comparisons against the `DATE` column (`birth_date`).
      - Removed `f_marital_status` and `f_education` from candidate query per user requirements.
      - Fixed query matching for text criteria (Location, Origin, Religion, Modesty) using flexible `LIKE`, `FIND_IN_SET`, and case-insensitive matching.
      - Added dynamic preference scoring for each candidate based on admin-specified filter values.
      - Sorted candidate results in descending order by `compatibility_score`.
      - Added automatic logging of `manual_match_search` event into `wp_matchmaker_logs` recording filter parameters and matching candidates count.
    - Updated `create_match()` to log `manual_match_created` event with match pair IDs, compatibility score, and admin user ID.
  - `src/Admin/AdminPortal.php`:
    - Updated `render_manual_match_view()` to provide intelligent candidate gender defaulting (target user's `pref_gender` or inverted user gender) and handle all filter parameters.
  - `src/View/admin/pool/manual-match.php`:
    - Cleaned up filter form: removed Education and Marital Status fields; focused on Gender, Age Range, Location, Origin, Religion, and Modesty Level.
    - Ranked candidate results table displaying candidate photo, name, email, criteria, compatibility score badge (`X / 6`), and `+ Create Match Pair` action button.
  - Verified User #283496466 against Live Local DB:
    - Successfully returned the 2 expected candidate matches: Candidate #283496465 (`eren`, 3/6 score) and Candidate #283496467 (`Armin`, 3/6 score).
  - `tests/Unit/ManualMatchmakerTest.php`:
    - Created unit tests verifying filter execution, match exclusion, score computation, and event logging.
    - Verified all 20 automated tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 26: Create Comprehensive Root README.md for GitHub Repository
- **Objective**: Author a professional, complete, and well-structured `README.md` at the root of the repository for GitHub, detailing the architecture, features, directory layout, database schema, shortcodes, test runner, and integrations.
- **Implemented**:
  - `README.md`:
    - Added System Architecture diagram with full directory tree and PSR-4 namespace mapping.
    - Detailed Core Features: Asynchronous Action Scheduler background matching, bi-directional hard gates, 6-point flexible compatibility scoring, 5-state member portal match flow, admin management hub, manual matchmaker, test/live environment modes, and structured audit logs.
    - Documented all 4 custom database tables (`wp_matchmaking_pool`, `wp_matches`, `wp_matchmaker_notifications`, `wp_matchmaker_logs`).
    - Added Shortcodes Reference table (`[matchmaker_member_portal]`, `[matchmaking_form]`, `[matchmaking_field]`, `[logout_url]`).
    - Added Automated Testing Guide with CLI command and output.
    - Documented system requirements and integrations (PMPro, Elementor Pro, Action Scheduler).
  - Verified test suite passes with 100% success rate (20/20 tests passed).

---

### Task 27: Fix Undefined "message" Array Key on Match Approval & Audit Notifications and Emails
- **Objective**: Fix PHP notice/warning `Undefined array key "message" in AdminPortal.php on line 256` when approving a match; audit and ensure transactional approval emails and mutual match notifications operate correctly with proper headers, dynamic dashboard permalinks, in-app alerts, and audit logging.
- **Implemented**:
  - `src/Repository/MatchRepository.php`:
    - Updated `approve_match()` to return `'message'` string in success return array.
    - Updated `update_match_response()` to invoke `send_mutual_match_notifications()` when both members accept a match.
  - `src/Admin/AdminPortal.php`:
    - Added defensive null-coalescing fallback for `$result['message']` in match approval handler.
  - `src/Service/NotificationService.php`:
    - Updated `send_approval_emails()` to resolve dynamic dashboard permalinks via `ProfileService::instance()->get_dashboard_url()`.
    - Fixed closure variable assignment for `wp_mail_content_type` filter addition and removal.
    - Added `send_mutual_match_notifications()`: generates in-app notifications in `wp_matchmaker_notifications` and dispatches transactional HTML emails to both matched members when a match is mutually accepted.
  - `tests/Unit/NotificationAndApprovalTest.php`:
    - Added unit test suite covering approval response format, email dispatches, and mutual match notifications.
    - Verified all 22 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 28: Full-Spectrum Codebase Audit, Comprehensive Test Expansion & Verification
- **Objective**: Perform an exhaustive audit across all features, flows, and database interactions in the plugin; author comprehensive unit and integration test suites covering every aspect, controller, shortcode, hook, and query without removing any features or styling.
- **Implemented**:
  - `src/Service/MatchService.php`:
    - Unified `compute_flexible_score()` to delegate directly to `MatchingEngine::compute_flexible_score()` using standardized pool column names (`origin`, `languages`, `height_cm`, `preferred_height_min`, `preferred_height_max`, `job`, `smoking`, `pref_smoking`, `drinking`, `pref_drinking`).
  - `tests/bootstrap.php`:
    - Added comprehensive stubs for `add_shortcode`, `do_shortcode`, `shortcode_atts`, `has_shortcode`, `wp_create_nonce`, `wp_logout_url`, `wp_get_current_user`, `user_can`, `esc_html_e`, `esc_textarea`, `WP_User` alias, and transient stubs.
  - `tests/Unit/FormWizardAndShortcodesTest.php`:
    - Added tests for `FieldGenerator::render_single_field()`, `FormController::render_standalone_field()`, and `FormController::render_form()`.
  - `tests/Unit/FreeRegHandlerTest.php`:
    - Added tests for single and comma-separated Elementor Pro form IDs and matching logic.
  - `tests/Unit/HeartbeatAndNotificationsTest.php`:
    - Added tests for 15s Heartbeat API configuration, active member unread count polling, and mark-as-read updates.
  - `tests/Unit/AuthAndRedirectsTest.php`:
    - Added tests for role-based redirects (admins to wp-admin, members with completed profile to dashboard, new members to questionnaire) and `[logout_url]` shortcode.
  - `tests/Unit/GateDebuggerAndScoringTest.php`:
    - Added unit tests for 6-point flexible scoring (perfect score 6, partial scoring).
  - `tests/Unit/AdminWorkflowTest.php`:
    - Added unit tests for pool search query building and settings option persistence.
  - `tests/Integration/FullMemberLifecycleFlowTest.php`:
    - Added end-to-end integration test simulating the entire multi-user journey: Registration -> Profile wizard -> Matching Engine -> Admin approval -> In-app alert -> Member portal 5-step flow -> Mutual match contact reveal -> Test Mode safe reset.
  - `tests/run_tests.php`:
    - Registered all 16 test suites.
    - Verified all 38 automated test cases pass with 100% success rate (0 errors, 0 failures).
  - Created Comprehensive System Audit Report artifact.

---

### Task 29: Inspect Button Delegated Modal Handling, AJAX View Reload on Match State Changes & Brand Active Colors
- **Objective**: Fix inspect button in Match Logs hub; ensure Member Portal always reloads matches view via AJAX whenever match status/decisions change (accept, decline, mutual match); enforce official brand colors (`#CC723F` / `#b6602f`) on active button states across Member Portal, Questionnaire, and Admin Portal (eliminating generic green colors).
- **Implemented**:
  - `assets/js/admin-matchmaker.js`:
    - Re-architected modal click listener using jQuery document event delegation (`$(document).on('click', '.mm-btn-view-log', ...)`).
    - Added resilient DOM ready check (`document.readyState === 'loading'` fallback) to ensure script initialization even when executed in the footer after DOMContentLoaded.
    - Handled JSON payload parsing, raw string formatting, email HTML previews, and modal backdrop escape/close handlers.
  - `src/View/admin/logs/tab-match-logs.php`:
    - Added defensive serialization on `$meta_json` so `data-payload` is always a clean string.
  - `assets/css/admin-matchmaker.css`:
    - Elevated `.mm-modal-overlay` `z-index` to `999999 !important` so it stays above all WordPress admin bars and wrappers.
    - Added brand active color rules for admin action buttons (`#b6602f !important`).
  - `assets/js/member-portal.js`:
    - Updated `reloadTabAJAX(tabName, targetStep)` to accept an optional target step and navigate to it upon receiving fresh HTML.
    - Updated `submitResponse()` so that every accept/decline decision immediately reloads the matches tab via AJAX, fetching fresh DB state.
  - `src/View/frontend/portal/tab-matches.php` & Step Views:
    - Added dynamic `$default_step` calculation (step 5 for mutual matches, step 3 for accepted waiting state, step 1 for fresh matches) so server-rendered HTML immediately has the correct `.view-state.active`.
  - CSS Active Button State Styling (`member-portal.css`, `matchmaking-form.css`, `admin-matchmaker.css`):
    - Added explicit rules for `.btn-primary:active`, `.btn-primary.active`, `.btn-outline-dark:active`, `.btn-outline-danger:active`, `#matchmaking_form .elementor-button:active`, and `.button-primary:active`.
    - Enforced `#b6602f` / `#CC723F` with `!important` to eliminate generic green button active states.
  - Verified test suite: all 38 unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 30: In-Canvas Events Tab with Elementor Loop Template & Event Tier Tab Gating
- **Objective**: Display ACF "Event" custom post type posts inside the Member Portal Events tab using Elementor loop item template (`template_id = 395`) with pagination matching the design system, and restrict tab visibility for `event` tier members so they only see Profile and Events tabs (Matches tab hidden).
- **Implemented**:
  - `src/View/frontend/portal/portal.php`:
    - Updated navigation header to conditionally hide the Matches button for users with `user_type === 'event'`.
    - Converted Events nav button to an in-canvas AJAX tab (`data-tab="events"`).
    - Included `#mm-tab-events` container hosting `tab-events.php`.
  - `src/View/frontend/portal/tab-events.php`:
    - Created in-canvas events loop querying published `event` CPT posts with pagination.
    - Integrated Elementor Loop Item Template rendering via `\Elementor\Plugin::instance()->frontend->get_builder_content_for_display()`.
    - Added design system fallback event card with thumbnail, date pill, title, excerpt, and CTA button.
    - Added AJAX pagination controls with `data-mm-action="paginate-events"`.
  - `src/Frontend/PortalController.php`:
    - Updated `handle_ajax_reload_tab()` to support `tab === 'events'` with `page` parameter.
    - Added tab gating preventing access to `matches` for members with `user_type === 'event'`.
    - Added `render_events_html()` method.
  - `assets/js/member-portal.js`:
    - Updated `reloadTabAJAX(tabName, targetStep, page)` to pass `page` parameter for pagination.
    - Added delegated event handler for `[data-mm-action="paginate-events"]`.
  - `assets/css/member-portal.css`:
    - Added responsive grid layout for `.mm-events-grid`, card container `.mm-events-container`, and pagination controls `.mm-pagination` using brand tokens (`#CC723F` / `#b6602f`).
  - `src/Admin/AdminPortal.php` & `src/View/admin/settings/settings.php`:
    - Added Member Portal Events Configuration section (`mm_events_cpt_slug`, `mm_events_template_id`, `mm_events_per_page`).
  - `src/Service/ProfileService.php`:
    - Updated `get_user_type()` to robustly check PMPro membership level, `user_type`, `az_user_type`, and pool user_type.
  - `tests/Unit/PortalAndEventsTest.php`:
    - Created unit tests verifying `event` tier tab gating, non-event tier matches visibility, AJAX events tab rendering, pagination, and settings persistence.
  - Verified test suite: all 43 unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 31: 7-Point Feature Updates, Location Schema Migration (Country/State/City), Mandatory Fields & Rejection Alerts
- **Objective**: Execute comprehensive feature updates:
  1. **Rejection Notifications**: When a user rejects/declines a match, the other candidate receives an in-app notification about the rejection that clears upon clicking the bell or visiting the matches tab.
  2. **Free User Matchmaking & Membership CTA Card**: Free users can have matches created and approved by admin without quota blocking; the 5-step match flow is accessible to them, and standard membership upgrade cards (`.mm-upsell-card`) are shown on the Profile tab and at the bottom of the Matches tab.
  3. **Mandatory Questionnaire Fields & 3 Mandatory Photos**: All form fields in the questionnaire are mandatory with a red asterisk `*`; photo slots 1, 2, and 3 are required on both client-side and server-side.
  4. **"Prefer not to say" Option**: Add `"Prefer not to say"` to Preferred Marital Status, Children Preference, and Preferred Education Level, supporting it as a flexible match in the matching engine.
  5. **Remove Preferred Social Link**: Removed `pref_social_links` from form, DB, and meta while preserving user's own `user_social_links`.
  6. **Salary Currency in USD**: Changed salary currency from SAR to USD (`$`) in `FieldGenerator::options_income()`, profile tabs, and step views.
  7. **Replace Location with Country, State, City**: In `wp_matchmaking_pool`, replaced `location` and `pref_location` with `country`, `state`, `city`, `pref_country`, `pref_state`, `pref_city`. Country input is a single-select with specific country names (e.g., United States, UK, Pakistan, Saudi Arabia, etc.), Preferred Country is a multiselect with specific countries and "Any Country", while State and City are text inputs.
- **Implemented**:
  - `src/Core/DBMigrator.php`:
    - Bumped schema version to `2.5.0`.
    - Added columns `country`, `state`, `city`, `pref_country`, `pref_state`, `pref_city` with indices `idx_country_city` and `idx_country`.
    - Added automated schema patch migrating existing `location` data and dropping legacy columns.
  - `src/Frontend/FieldGenerator.php`:
    - Added `options_country()` and `options_pref_country()`.
    - Added `"Prefer not to say"` to `options_marital()`, `options_children()`, and `options_education()`.
    - Updated `options_income()` to USD ranges (`USD $3,000 – $4,999`, etc.).
    - Added red asterisk `*` (`<span class="mm-required-star" style="color:#e11d48;font-weight:700;">*</span>`) to all field labels.
    - Added text/select/multiselect configs for country, state, city, pref_country, pref_state, pref_city.
    - Removed `pref_social_links`.
  - `src/Frontend/FormController.php` & `assets/js/matchmaking-form.js`:
    - Step 1: Rendered `user_country`, `user_state`, `user_city`; Step 2: Rendered `pref_country`, `pref_state`, `pref_city`.
    - Added client-side (`validateStep`) and server-side validation enforcing that all 3 photo slots are filled.
  - `src/Service/NotificationService.php`:
    - Added `send_rejection_notification(int $match_id, int $declined_by_user_id)` dispatching in-app notification to the candidate and flushing unread transient.
    - Updated Heartbeat API pulse check allowing Free users with unread notifications to receive real-time badge updates (only skipping `event` tier).
  - `src/Service/MatchService.php` & `src/Repository/MatchRepository.php`:
    - Updated `handle_match_response()`: on decline/rejection, automatically dispatches rejection notification to candidate.
    - Updated `is_info_only_pair()`: only event tier is info-only; free users are permitted.
    - Updated `approve_match()`: free users can be approved by admin without quota blockades.
    - Updated `search_pool()`, `find_approved_matches_for_user()`, `get_all_matches()`, and manual candidate search to handle country, state, city.
  - `src/Core/MatchingEngine.php`:
    - Updated `query_candidates()` for bi-directional country/state/city gate evaluations and "Prefer not to say" flexible matching.
  - Portal & Admin Views (`src/View/`):
    - `tab-matches.php`: Allowed free users with approved matches to see their active match flow, and rendered `.mm-upsell-card` at the bottom of the Matches tab for free members.
    - `tab-profile.php`: Displayed combined location (`city, state, country`), preferred location (`pref_city, pref_state, pref_country`), and upsell card.
    - `steps/step-1-discovery.php`, `steps/step-2-profile.php`, `steps/step-5-contact.php`: Displayed formatted candidate location.
    - `user-single.php`, `pool-list.php`, `match-single.php`: Updated location display and allowed free user approval.
  - Automated Testing (`tests/`):
    - Added unit tests for rejection notification dispatch, free user admin approval, USD income options, "Prefer not to say" options, and country list.
    - Verified all 46 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 32: Settings Gear Icon Fix, Declined Match Notification Stale Invalidation & Events Equal Height / Background
- **Objective**:
  1. Fix the settings gear icon in the member portal header which had a clipped/broken SVG path.
  2. Invalidate stale `match_approved` notifications when a match is declined by either candidate so that a user who has not logged in does not see a phantom "New Match Available!" toast or badge count.
  3. Ensure all event cards in the Events tab have identical equal heights (`align-items: stretch`, flex column stretch) and the Events tab has `#F8F2ED` background color.
- **Implemented**:
  - `src/View/frontend/portal/portal.php`:
    - Replaced corrupted SVG path on the settings link with the full 24x24 Lucide/Feather `settings` gear icon.
  - `src/Repository/MatchRepository.php`:
    - Added `dismiss_notifications_for_match(int $match_id, ?string $type = null)` to invalidate unread notifications (`is_read = 1`).
    - Hooked notification invalidation into `reject_match()`, `expire_match()`, and `handle_match_response()`.
  - `src/Service/NotificationService.php`:
    - Updated `send_rejection_notification()` to call `dismiss_notifications_for_match($match_id, 'match_approved')` before dispatching the rejection notification and flushing transients.
  - `assets/css/member-portal.css`:
    - Added `#F8F2ED` background color to `#mm-tab-events` and `.mm-events-container`.
    - Configured equal card heights for `.mm-event-card`, `.mm-event-loop-item`, `.mm-event-card-body`, and `.mm-event-card-footer` using CSS Grid `align-items: stretch` and flexbox `flex: 1 1 auto` / `margin-top: auto`.
  - `tests/Unit/NotificationAndApprovalTest.php`:
    - Added unit test assertion validating notification dismissal on decline.
  - Verified test suite: all 46 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 33: Events Tab CSS Deduplication & Dynamic Background Image Injection
- **Objective**:
  1. Eliminate raw/duplicate CSS blocks rendered by Elementor in the Events tab when rendering loop templates.
  2. Fix event card background image rendering by binding post featured images / ACF thumbnails dynamically onto loop item containers and card thumbnails.
- **Implemented**:
  - `src/View/frontend/portal/tab-events.php`:
    - Extracted and deduplicated all `<style>` tags from Elementor loop template outputs so that template CSS is output cleanly only once in a hidden container (`.mm-elementor-template-styles`), stripping repeated style blocks from inside individual card markups.
    - Added dynamic thumbnail resolution checking `get_the_post_thumbnail_url()`, ACF `event_image`, `image`, and `_thumbnail_id` meta.
    - Injected dynamic `background-image: url(...) !important; background-size: cover !important; background-position: center center !important;` into top-level Elementor container style and applied `--mm-event-bg` CSS custom property with `data-has-bg="1"`.
    - Added thumbnail image fallback in native event cards.
  - `assets/css/member-portal.css`:
    - Added rules for `.mm-event-loop-item[data-has-bg="1"]` targeting container elements, featured image placeholders, and inner sections to display `--mm-event-bg` background images.
    - Added hidden scoping rules for `.mm-elementor-template-styles` (`display: none !important; visibility: hidden !important; height: 0 !important; overflow: hidden !important;`).
  - `tests/Unit/PortalAndEventsTest.php`:
    - Added `test_tab_events_renders_thumbnail_and_deduplicates_elementor_css` verifying thumbnail binding, background style presence, and clean rendering.
  - Verified test suite: all 47 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 34: Restore Pure Elementor Loop Template Rendering (Remove Automated BG Image Overrides)
- **Objective**:
  1. Remove forced automated background image injections and `--mm-event-bg` inline styles from the Elementor loop item container, allowing the Elementor template (#395) to render its exact native design untouched.
  2. Maintain CSS deduplication so that Elementor `<style>` tags are output cleanly once in a hidden container rather than repeating inside card HTML.
- **Implemented**:
  - `src/View/frontend/portal/tab-events.php`:
    - Removed regex background-image injection and `--mm-event-bg` attributes from `.mm-event-loop-item`.
    - Maintained clean single-instance template CSS output.
  - `assets/css/member-portal.css`:
    - Removed `.mm-event-loop-item[data-has-bg="1"]` background overrides.
  - `tests/Unit/PortalAndEventsTest.php`:
    - Updated unit tests to verify pure template rendering without forced background styles.
  - Verified test suite: all 47 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 35: 5-Point System Updates: Income Ranges, Age/Height Range Enforcement, Any Citizenship, No Preference & #1D1E20 Color System
- **Objective**:
  1. Income Ranges in USD: `0-100k USD`, `100k-500k USD`, `500k-1million USD`, `1 million + USD`.
  2. Preferred Age & Height: Enforce that max value is strictly higher than min value and min value is strictly less than max value (dynamic select synchronization + step validation + server-side normalization).
  3. Preferred Citizenship: Include `"Any Citizenship"` as the first option.
  4. Replace `"Prefer not to say"` with `"No Preference"` across all questionnaire dropdowns.
  5. Include `#1D1E20` in `Design.md` / `context/design_system.md` and balance `#1D1E20` (Obsidian Dark) with `#CC723F` (Primary Ochre) across views and stylesheets.
- **Implemented**:
  - `src/Frontend/FieldGenerator.php`:
    - Updated `options_income()` to `['Select range', 'No Preference', '0-100k USD', '100k-500k USD', '500k-1million USD', '1 million + USD']`.
    - Added `options_pref_citizenship()` with `"Any Citizenship"` as the primary first option.
    - Replaced `"Prefer not to say"` with `"No Preference"` across `options_marital()`, `options_children()`, `options_religion()`, `options_modesty()`, `options_drinking()`, `options_smoking()`, `options_prayer()`, and `options_education()`.
  - `assets/js/matchmaking-form.js`:
    - Added real-time change synchronization for `preferred_age_min` / `preferred_age_max` and `preferred_height_min` / `preferred_height_max`.
    - Added strict `min < max` range checks in `validateStep(2)`.
  - `src/Frontend/FormController.php`:
    - Enforced server-side normalization for preferred age and height ranges ensuring min < max.
  - `src/Core/MatchingEngine.php`:
    - Supported `'no preference'` as pass-through in bi-directional queries and flexible scoring.
  - `Design.md` & `context/design_system.md`:
    - Documented `#1D1E20` token ("Obsidian Dark / Accent") and defined color balance role matrix.
  - `assets/css/` (`member-portal.css`, `admin-matchmaker.css`, `matchmaking-form.css`):
    - Configured `--obsidian: #1D1E20;` and `--text-dark: #1D1E20;` balancing typography with `#CC723F` CTAs and active states.
  - `tests/`:
    - Updated `MatchingEngineTest.php` and `FormWizardAndShortcodesTest.php` with assertions for new income ranges, "No Preference", "Any Citizenship", and range validation.
  - Verified test suite: all 48 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 36: Top Form Error Display & Balanced #1D1E20 Backgrounds / Buttons Implementation
- **Objective**:
  1. Relocate form error notification banner to the very top of the questionnaire form (directly under step indicator progress).
  2. Implement smooth scroll-to-top on validation error trigger so users immediately see any missing fields or invalid range constraints.
  3. Apply `#1D1E20` (Obsidian Dark) to backgrounds and buttons in high-contrast balanced areas (Previous Step button, admin table headers, secondary buttons) while maintaining Primary Ochre `#CC723F` for main CTAs and active states.
- **Implemented**:
  - `src/Frontend/FormController.php`:
    - Moved `<div class="mmf-form-message">` from the bottom to the very top of the form directly beneath `.mm-step-indicator`.
  - `assets/js/matchmaking-form.js`:
    - Enhanced `showMessage(text, type)` to construct styled icon banners and automatically call `.scrollIntoView({ behavior: 'smooth', block: 'center' })` to focus the user's viewport on the top error banner upon validation failure.
  - `assets/css/matchmaking-form.css`:
    - Styled top `.mmf-form-message.error` with high-visibility red border (`#FCA5A5`), soft background (`#FEF2F2`), and dark red text (`#991B1B`).
    - Styled `.e-form__buttons__wrapper__button-previous` and `[data-direction="previous"]` with `#1D1E20` solid background, white text, and `#333538` hover state.
  - `assets/css/member-portal.css` & `assets/css/admin-matchmaker.css`:
    - Integrated `--obsidian: #1D1E20;` across dark outline buttons, secondary action controls, and admin table headers.
  - Verified test suite: all 48 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 37: Real-Time Age/Height Range Error Display & Form Primary CTAs Updated to #1D1E20
- **Objective**:
  1. Fix issue where selecting min age/height $\ge$ max age/height did not display the error banner (eliminated the previous auto-mutation logic that silently altered dropdown selections, replacing it with active validation on dropdown `change`, step transition, and AJAX submission).
  2. Enhance `showMessage(text, type)` in `matchmaking-form.js` to guarantee `display: block`, build `.mmf-alert-content` with icons (`⚠️`/`✅`), and execute `scrollIntoView({ behavior: 'smooth', block: 'center' })`.
  3. Apply `#1D1E20` (Obsidian Dark) as the primary background color for all primary CTA buttons on the form (`.elementor-button`, `button[type="submit"]`, `.e-form__buttons__wrapper__button-next`), paired with high-contrast `#F8F2ED` / `#1D1E20` previous button styling.
  4. Implement server-side validation error handling in `FormController.php` for `pref_age_min >= pref_age_max` and `pref_h_min >= pref_h_max`.
- **Implemented**:
  - `assets/js/matchmaking-form.js`:
    - Implemented `checkAgeRange(silent)` and `checkHeightRange(silent)` with `.has-error` class toggling on `.custom-select-wrapper`.
    - Added `change` event listeners on `preferred_age_min`, `preferred_age_max`, `preferred_height_min`, and `preferred_height_max` that immediately show the error banner and scroll into view when min $\ge$ max.
    - Updated `showMessage(text, type)` with auto-scrolling and rich warning formatting.
  - `src/Frontend/FormController.php`:
    - Added server-side validation rejecting submissions where `pref_age_min >= pref_age_max` or `pref_h_min >= pref_h_max` with `wp_send_json_error()`.
  - `assets/css/matchmaking-form.css`:
    - Styled `.elementor-button` and submit buttons with `#1D1E20` background (`#333538` hover, `#000000` active).
    - Styled `.e-form__buttons__wrapper__button-previous` with `#F8F2ED` background and `#1D1E20` text.
    - Added `.custom-select-wrapper.has-error .custom-select-display` red bottom border (`#e11d48`).
  - `tests/Unit/FormWizardAndShortcodesTest.php`:
    - Added `test_range_validation_logic` verifying range inequality detection.
  - Verified test suite: all 49 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 38: Instant Error Dismissal on Value Entry and Correction
- **Objective**:
  - Automatically and immediately dismiss the top error alert and clear `.has-error` visual cues as soon as the user enters a value into a missing field, selects a dropdown option, checks a radio option, uploads required photos, or adjusts min/max age/height into a valid range.
- **Implemented**:
  - `assets/js/matchmaking-form.js`:
    - Added `input` and `change` event listeners across all form inputs (`input, select, textarea`) that dynamically remove `.has-error` and clear active field error banners when a valid value is entered.
    - Updated `onAgeChange` and `onHeightChange` to clear top alerts immediately when valid `min < max` ranges are restored.
    - Added instant photo upload listeners clearing photo requirement alerts once all 3 photos have files or previews attached.
  - Verified test suite: all 49 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 39: Balanced Application of #1D1E20 Across Matching Step Flow & Profile Views
- **Objective**:
  - Incorporate the new `#1D1E20` (Obsidian Dark) color token across selected areas of the member portal (profile tab, match steps, buttons, badges, contact reveals) while preserving `#CC723F` (Primary Ochre) and `#F8F2ED` (Warm Cream) for brand harmony.
- **Implemented**:
  - `assets/css/member-portal.css`:
    - **Buttons**:
      - `.btn-primary` (e.g. "View Match →", "Accept Match →", "Keep Match", "Back to Profile Dashboard →", "Get Monthly Membership →") styled with `#1D1E20` background, `#333538` hover, and `#000000` active state.
      - `.btn-outline-dark` (e.g. "View Status", "Decline Match") styled with `#1D1E20` border/text and `#1D1E20` hover fill.
    - **Profile Tab**:
      - `.az-edit-btn`: Styled with `#1D1E20` border and text with `#1D1E20` solid hover.
      - `.az-badge`: Styled with `#1D1E20` background and white text.
      - `.az-stat-box` & `.az-stat-num`: Styled stat cards with `#F8F2ED` warm background, `#e8ded0` border, and `#1D1E20` numbers.
      - `.az-value` and `.az-user-name`: Displayed in `#1D1E20` for high-contrast readability.
    - **Matching Steps**:
      - `.candidate-tags-row span`: Styled candidate tags with `#F8F2ED` background and `#1D1E20` text.
      - `.contact-icon-bubble`: Styled with `#1D1E20` background and white icons.
      - `.contact-data-text .val`: Rendered in `#1D1E20` font weight 600.
      - `.notice-gray-box`: Styled with `#F8F2ED` background, `#e8ded0` border, and `#1D1E20` text.
  - Verified test suite: all 49 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 40: Remove "No Preference" from Step 1 User Info & Preserve in Step 2 Preferences
- **Objective**:
  - Remove "No Preference" from all personal info dropdowns on Step 1 (where the member is declaring their own profile attributes: marital status, children, religion, modesty, drinking, smoking, prayer, education, income).
  - Preserve "No Preference" (and "Any Country" / "Any Citizenship") across all partner preference dropdowns on Step 2.
- **Implemented**:
  - `src/Frontend/FieldGenerator.php`:
    - Cleaned `options_religion()`, `options_marital()`, `options_children()`, `options_modesty()`, `options_drinking()`, `options_smoking()`, `options_prayer()`, `options_education()`, and `options_income()` to provide only concrete user options without "No Preference".
    - Added corresponding `options_pref_*()` methods containing "No Preference" for Step 2 partner preference fields.
    - Updated `$select_configs` in `render_single_field()` so `pref_*` fields use the `options_pref_*()` sets while `user_*` fields use the user info sets.
  - `tests/Unit/MatchingEngineTest.php` & `tests/Unit/FormWizardAndShortcodesTest.php`:
    - Added assertions verifying that Step 1 user options do NOT contain "No Preference" while Step 2 preference options DO contain "No Preference".
  - Verified test suite: all 49 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 41: PMPro Multi-Group Priority Sync, Dynamic Location Cascading Dropdowns, Readonly Email & 6-Digit Email Verification Module
- **Objective**:
  - Handle PMPro multiple level groups and resolve user tier priority (`one_on_one` > `monthly` > `event` > `free`) upon checkout, auto-cancelling the old Free level in Group 1 when paid levels are acquired.
  - Make the questionnaire `email` field strictly readonly with subtle lock styling so users cannot modify their registered email.
  - Dynamically populate cascading Country -> State -> City dropdowns for Step 1 (`user_country`, `user_state`, `user_city`) and Step 2 (`pref_country`, `pref_state`, `pref_city`) using `assets/file/hierarchy_names.json`, with "Any" wildcards for preferences.
  - Implement a 6-digit email verification module (`EmailVerificationService`) with 24-hour expiration, 60-second cooldown, branded HTML email template, and restricted screen access to Dashboard and Form Wizard until verified.
- **Implemented**:
  - `src/Core/PMProSync.php`:
    - Added `TIER_PRIORITY = ['one_on_one' => 4, 'monthly' => 3, 'event' => 2, 'free' => 1]`.
    - Enhanced `get_current_user_type($user_id)` to query `pmpro_getMembershipLevelsForUser()` across multiple groups and return the highest-priority tier.
    - Added `maybe_cancel_free_levels($user_id)` to automatically cancel Free level 2 when upgrading to paid tiers.
    - Hooked into `pmpro_after_all_membership_level_changes` and `pmpro_after_checkout` with robust support for PMPro's array of users argument structure.
  - `src/Service/EmailVerificationService.php`:
    - Created email verification service handling 6-digit cryptographic code generation (`random_int(100000, 999999)`), 24h expiration, 60s cooldown timer, HTML email dispatch, and AJAX verification (`mm_verify_email_code`, `mm_resend_verification_code`).
    - Hooked into `user_register` and `pmpro_after_checkout`.
  - `src/View/frontend/portal/email-verification.php`:
    - Created modern OTP input screen with `#1D1E20` primary button, `#CC723F` accents, live 60s cooldown countdown timer, and inline alert feedback.
  - `src/Frontend/PortalController.php` & `src/Frontend/FormController.php`:
    - Intercept unverified members and render `email-verification.php` until code is verified.
    - Block unverified profile form submissions in AJAX handler.
    - Enqueue `hierarchyUrl` in script localization for form JS.
  - `src/Frontend/FieldGenerator.php`:
    - Made `email` field readonly (`<input type="email" ... readonly class="... is-readonly">`).
    - Added `get_hierarchy_data()`, `options_country()`, `options_user_state()`, `options_user_city()`, `options_pref_country()`, `options_pref_state()`, `options_pref_city()`.
    - Rendered location fields as dynamic select dropdowns with `location-cascade-group`.
  - `assets/js/matchmaking-form.js` & `assets/css/matchmaking-form.css`:
    - Implemented `updateCustomSelect()` and asynchronous `loadHierarchy()` for instant Country -> State -> City cascading.
    - Styled readonly inputs with disabled tone and distinct border.
  - `tests/Unit/EmailVerificationTest.php` & `tests/Unit/LocationCascadeTest.php`:
    - Added unit test suites verifying email code generation, expiry, 60s cooldown, correct verification, location hierarchy loading, state/city cascading, and readonly email rendering.
  - Verified test suite: all 59 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

---

### Task 43: Fix Email Verification Dispatch, Cooldown Management & Admin Audit Logging
- **Objective**:
  - Resolve email dispatch failures and "Could not resend code." errors during email verification.
  - Implement comprehensive logging for all email verification lifecycle events (dispatches, failures, OTP verifications, expired/invalid attempts) in the Admin Logs under Settings (`wp_matchmaker_logs`).
  - Ensure failed mail transmissions do not lock the user in a cooldown timer, allowing immediate resend retry.
- **Implemented**:
  - `src/Service/EmailVerificationService.php`:
    - Sanitized `From:` header to prevent invalid RFC 822 domain/port formatting (stripping ports/www, validating domain syntax, falling back to valid `admin_email`).
    - Added `wp_mail_content_type` filter for HTML MIME formatting and hooked into `wp_mail_failed` to capture granular error reasons from WordPress/PHPMailer.
    - Integrated with `MatchRepository::instance()->log_event()`:
      - Logged `verification_code_sent` (`success`) with full email HTML payload (`details_json['body_html']`) for admin email preview.
      - Logged `verification_code_failed` (`error`) with detailed error messages when `wp_mail()` or user lookup fails.
      - Logged `email_verified` (`success`) when a user successfully enters the correct OTP code.
      - Logged `email_verify_failed` (`warning`) when an expired or incorrect code is submitted.
    - Updated cooldown handling so `mm_verification_last_sent_at` is only updated when an email is successfully dispatched, resetting cooldown to 0 on failure for immediate retry.
  - `src/View/frontend/portal/email-verification.php`:
    - Improved frontend AJAX response error extraction to display clear server-provided error messages instead of generic fallbacks.
  - `src/View/admin/logs/tab-notification-logs.php`:
    - Added `verification_code_sent`, `verification_code_failed`, and `email_verified` filter options to the Admin Notification & Email Logs filter dropdown.
  - `tests/Unit/EmailVerificationTest.php` & `tests/bootstrap.php`:
    - Added unit test `test_email_verification_logs_events_on_success_and_failure` asserting `wp_matchmaker_logs` insertions and cooldown bypass on failed send.
    - Added `wp_parse_url` mock and configurable `__mm_wp_mail_return` flag in bootstrap.
  - Verified test suite: all 62 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 44: Add Verification Email Admin Settings & Comprehensive Resend Mechanism Enhancement
- **Objective**:
  - Add a dedicated "6. Email Verification Code & Delivery Settings" section to the Matchmaker Settings page (`/wp-admin/admin.php?page=matchmaking-settings`).
  - Allow administrators to configure:
    - Sender Email (`mm_email_verify_from_email`)
    - Sender Name (`mm_email_verify_from_name`)
    - Email Subject (`mm_email_verify_subject`) with `{code}` placeholder
    - Email Body (`mm_email_verify_template`) with rich WYSIWYG editor and placeholders (`{code}`, `{user_name}`, `{user_email}`, `{site_name}`, `{expiry_hours}`)
    - Code Expiration Hours (`mm_email_verify_expiry_hours`, default 24h)
    - Resend Cooldown Seconds (`mm_email_verify_cooldown_seconds`, default 60s)
  - Ensure the resend mechanism works seamlessly by adding `wp_ajax_nopriv_` hooks, passing `user_id` authenticated with user-bound nonce `mm_verify_nonce_{$user_id}`, applying `credentials: 'same-origin'` to all fetch calls, and hooking `wp_mail_from` and `wp_mail_from_name` with priority 999 for full SMTP plugin compatibility.
- **Implemented**:
  - `src/Admin/AdminPortal.php`:
    - Saved and retrieved options: `mm_email_verify_from_email`, `mm_email_verify_from_name`, `mm_email_verify_subject`, `mm_email_verify_template`, `mm_email_verify_expiry_hours`, `mm_email_verify_cooldown_seconds`.
    - Passed verification settings to `src/View/admin/settings/settings.php`.
  - `src/View/admin/settings/settings.php`:
    - Created Section 6 with From Email, From Name, Subject input, WYSIWYG editor for body template with placeholder guide table, Code Expiry input, and Resend Cooldown input.
  - `src/Service/EmailVerificationService.php`:
    - Added getters: `get_expiry_seconds()`, `get_cooldown_seconds()`, `get_sender_email()`, `get_sender_name()`, `get_email_subject()`.
    - Updated `get_email_html()` with support for custom body templates and placeholder replacements.
    - Updated `send_verification_email()` to filter `wp_mail_from` and `wp_mail_from_name` with priority 999.
    - Registered `wp_ajax_nopriv_mm_verify_email_code` and `wp_ajax_nopriv_mm_resend_verification_code`.
    - Added user-bound nonce authentication (`mm_verify_nonce_{$user_id}`) in `handle_ajax_verify()` and `handle_ajax_resend()`.
  - `src/View/frontend/portal/email-verification.php`:
    - Added hidden input for `user_id` and generated user-bound nonce.
    - Included `user_id` and `credentials: 'same-origin'` in all fetch calls.
  - `tests/Unit/EmailVerificationTest.php`:
    - Added `test_custom_verification_settings_affect_subject_template_and_sender` testing placeholder replacement, custom from name/email, and configurable durations.
  - Verified test suite: all 63 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 45: Handle Local "Could Not Instantiate Mail Function" & Support Test Mode Email Simulation
- **Objective**:
  - Resolve the error `"Could not instantiate mail function."` which occurs when WordPress / PHPMailer attempts to use PHP's default `mail()` function on a local or staging server without an active local sendmail binary or SMTP provider.
  - Allow developers and administrators to test the entire email verification workflow seamlessly without needing a configured SMTP server when in Test Mode (`is_test_mode()`).
  - Provide clear, actionable guidance in Matchmaker Settings explaining SMTP requirements for live production environments.
- **Implemented**:
  - `src/Service/EmailVerificationService.php`:
    - Added Test Mode simulation in `generate_and_send_code()`: when `MatchRepository::instance()->is_test_mode()` is active and `wp_mail` fails due to local mail server unavailability, the plugin logs a simulation event and returns the 6-digit OTP code directly in the alert banner, enabling instant OTP testing without an SMTP server.
    - Added user-friendly diagnostic guidance for Live Mode when `Could not instantiate mail function.` is reported.
  - `src/View/admin/settings/settings.php`:
    - Added an informative callout box in Section 6 explaining SMTP setup and Test Mode simulation.
  - `tests/Unit/EmailVerificationTest.php`:
    - Added `test_test_mode_simulates_email_dispatch_on_mail_failure` asserting that in Test Mode, offline mail functions return the generated verification code in the response.
  - Verified test suite: all 64 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 46: Resolve AJAX 400 on Verification Code Resend & Verification
- **Objective**:
  - Fix HTTP 400 Bad Request returned by WordPress `admin-ajax.php` when requesting OTP verification or code resend (`mm_resend_verification_code` / `mm_verify_email_code`).
- **Root Cause**:
  - `EmailVerificationService::instance()` was not initialized during `plugins_loaded` in `matchmaker.php`. When AJAX requests hit `admin-ajax.php`, the frontend shortcode controllers are not rendered, meaning `EmailVerificationService` had not been instantiated, leaving `wp_ajax_` and `wp_ajax_nopriv_` action hooks unregistered. WordPress `admin-ajax.php` terminates with HTTP 400 when an unregistered action is called.
- **Implemented**:
  - `matchmaker.php`:
    - Added `\Matchmaker\Service\EmailVerificationService::instance();` to `plugins_loaded` bootstrap sequence so all verification AJAX hooks (`wp_ajax_mm_resend_verification_code`, `wp_ajax_nopriv_mm_resend_verification_code`, `wp_ajax_mm_verify_email_code`, `wp_ajax_nopriv_mm_verify_email_code`) are permanently registered on every WordPress request.
  - `tests/bootstrap.php`:
    - Added `has_action` stub to test environment mock layer.
  - `tests/Unit/EmailVerificationTest.php`:
    - Added `test_ajax_hooks_are_registered` confirming all verification and resend AJAX hooks are active.
  - Verified test suite: all 65 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 47: Fix User Type Synchronization on PMPro Membership Cancellation & Modification
- **Objective**:
  - Resolve issue where cancelling or modifying a PMPro membership level (e.g. cancelling Monthly for a user) left the member stuck as `monthly` in both `wp_usermeta` and `wp_matchmaking_pool`.
- **Root Cause**:
  - `PMProSync::get_current_user_type()` was falling back to stale `usermeta` `user_type` (`monthly`) when PMPro reported 0 active levels upon cancellation. Furthermore, `sync_pmpro_level_to_user_type()` only updated `resolved_user_type` when the new level rank was strictly greater than the old rank, preventing any downgrade or cancellation to `free`.
- **Implemented**:
  - `src/Core/PMProSync.php`:
    - Refactored `get_current_user_type()`: When PMPro functions are available and report no active levels (or level ID is 0), it returns `'free'`. The `usermeta` fallback is only used if PMPro functions are completely absent from the environment.
    - Updated `sync_pmpro_level_to_user_type()` to accept flexible parameter types (`mixed $level_id`, `int $user_id`, `mixed $old_level_id = null`) and properly downgrade `user_type` to `'free'` or the new lower tier in both `wp_usermeta` and `wp_matchmaking_pool`.
    - Added `pmpro_membership_post_membership_expiry` action hook via `handle_expiry_sync()`.
  - `src/Service/ProfileService.php`:
    - Updated `get_user_type()` to delegate directly to `PMProSync::instance()->get_current_user_type()` with self-healing synchronization for `usermeta` and `wp_matchmaking_pool`.
  - `src/Admin/AdminPortal.php`:
    - Updated `render_single_user_view()` to self-heal and sync `user_type` if PMPro membership state has changed.
  - `tests/Unit/SettingsAndPlanMappingTest.php`:
    - Added `test_pmpro_membership_cancellation_downgrades_user_type_to_free`.
    - Added `test_pmpro_membership_downgrade_updates_user_type`.
    - Added `test_pmpro_expiry_sync_downgrades_to_free`.
  - Verified test suite: all 68 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 48: Full Country Options in Citizenship and Preferred Citizenship
- **Objective**:
  - Show the comprehensive country dataset (all 240+ countries from `hierarchy_names.json`) in the Step 1 `user_citizenship` dropdown and Step 2 `pref_citizenship` multiselect dropdown.
  - Maintain `'Select citizenship'` as placeholder for user citizenship and keep `'Any Citizenship'` intact as the primary option for preferred citizenship.
  - Ensure citizenship selection remains completely independent with zero side-effects on state and city dropdowns.
- **Implemented**:
  - `src/Frontend/FieldGenerator.php`:
    - Updated `options_citizenship()` to dynamically pull the full country list from `options_country()` and replace `'Select country'` with `'Select citizenship'`.
    - Updated `options_pref_citizenship()` to include the full country list with `'Any Citizenship'` as the leading option.
  - `tests/Unit/LocationCascadeTest.php`:
    - Added `test_citizenship_and_pref_citizenship_contain_full_country_list` asserting that both citizenship and preferred citizenship dropdowns contain all 240+ countries with correct leading placeholders.
  - Verified test suite: all 69 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 49: Fix Critical Error on Matches Queue Admin Page
- **Objective**:
  - Resolve the critical fatal error encountered when opening the Matches Queue admin page (`/wp-admin/admin.php?page=matchmaking-matches`).
- **Root Cause**:
  - `AdminPortal::render_matches_list_view()` called `$repo->search_matches($filters)`, but `MatchRepository` only defined `get_all_matches()`, throwing `Fatal error: Uncaught Error: Call to undefined method MatchRepository::search_matches()`.
  - In addition, array access on `$p1` / `$p2` in `matches-list.php` and `match-single.php` lacked null-safety guards when match candidate pool records are missing or null.
- **Implemented**:
  - `src/Repository/MatchRepository.php`:
    - Added `search_matches(array $filters = []): array` supporting `status`, `source`, and `search` query parameters.
    - Updated `get_all_matches(array $filters = []): array` with `source` filtering support.
  - `src/View/admin/matches/matches-list.php` & `src/View/admin/matches/match-single.php`:
    - Added null-safe checks (`is_array($p1)` / `is_array($p2)`) for pool records and candidate attributes.
  - `tests/Unit/AdminWorkflowTest.php`:
    - Added `test_search_matches_query_generation` verifying query structure and filter parameters.
  - Verified test suite: all 70 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 50: Manual Matchmaker Location Filters & Matching Engine Verification
- **Objective**:
  - Add Country, State, and City filters (with hierarchy dataset options & dynamic cascading JavaScript) as well as Citizenship filter to the Admin Manual Matchmaking tool.
  - Audit and verify how Country, State, City, and Citizenship participate in the automated matching engine (`MatchingEngine.php` SQL bi-directional hard gates and `MatchService.php` 6-point flexible scoring).
  - Update `context/matching_engine.md` with complete, crystal-clear architectural documentation explaining the matching feature end-to-end.
- **Implemented**:
  - `src/Admin/AdminPortal.php`:
    - Updated `render_manual_match_view(int $user_id)` to extract `f_country`, `f_state`, `f_city`, and `f_citizenship` from `$_GET` with smart fallbacks from the target user's pool profile (`pref_country`, `pref_state`, `pref_city`, `pref_citizenship`), passing them into `$filters`.
  - `src/View/admin/pool/manual-match.php`:
    - Added Country (`f_country`), State / Region (`f_state`), City (`f_city`), and Citizenship (`f_citizenship`) select dropdowns populated with full datasets from `FieldGenerator`.
    - Integrated dynamic cascading JavaScript so selecting a Country automatically populates corresponding States, and selecting a State populates Cities.
    - Updated Candidate Results Table with dedicated columns for `Country / City`, `Citizenship / Origin`, and `Religion / Modesty`.
  - `src/Repository/MatchRepository.php`:
    - Enhanced `get_manual_match_candidates()` to evaluate `f_country`, `f_state`, `f_city`, `f_citizenship`, and `f_location` in SQL where clauses and forward them into the `$scoring_profile`.
  - `src/Core/MatchingEngine.php`:
    - Verified and wired bi-directional SQL hard gates for `country` / `pref_country`, `state` / `pref_state`, and `city` / `pref_city` with full `'Any Country'`, `'Any State'`, `'Any City'`, and wildcard/fallback support.
  - `context/matching_engine.md`:
    - Completely rewritten and expanded with comprehensive documentation covering triggers, Action Scheduler async processing, Phase 1 bi-directional SQL hard gates, Phase 2 6-point flexible scoring, limits, billing quota rules, manual matchmaker comparison, and database index mapping.
  - `tests/Unit/ManualMatchmakerTest.php` & `tests/Unit/MatchingEngineTest.php`:
    - Added tests asserting Country, State, City, and Citizenship filters in manual matchmaking and Matching Engine SQL candidate queries.
  - Verified test suite: all 71 automated unit and integration tests pass with 100% success rate (0 errors, 0 failures).

---

### Task 52: Improved Verification Email Design & Fix Duplicate Code Email Issue
- **Objective**:
  - Redesign the verification code email template to feature a luxury, modern, responsive layout compatible across all email clients (Gmail, Apple Mail, Outlook) with inline CSS, bold OTP card, security callout, and brand header/footer.
  - Investigate and resolve the issue where users received two emails with different verification codes simultaneously upon registration.
- **Root Cause of Duplicate Code Emails**:
  - `FreeRegHandler.php` called `wp_create_user()`, which automatically fired the WordPress core `user_register` action hook (triggering `EmailVerificationService::on_user_register` and sending Code #1).
  - Immediately following `wp_create_user()`, `FreeRegHandler.php` invoked `generate_and_send_code($user_id, true)` explicitly, generating Code #2, overwriting Code #1, and triggering a second simultaneous email.
  - Furthermore, on PMPro checkout both `user_register` and `pmpro_after_checkout` fired back-to-back with `$force = true` lacking in-flight deduplication.
- **Implemented**:
  - `src/Service/EmailVerificationService.php`:
    - Added static in-memory request-level tracking `$sent_in_request` to eliminate duplicate email dispatches during the same PHP request lifecycle.
    - Added near-instant hook deduplication check: if a forced dispatch request (`$force = true`) occurs within 15 seconds of a code already generated, it reuses the existing code without generating a new one or sending another email.
    - Updated cooldown logic: on non-forced requests (e.g. user-initiated resend), cooldown check is strictly enforced with user-friendly countdown timer.
    - Redesigned `wrap_email_layout()` and `get_email_html()` with 100% inline CSS and nested tables, dark luxury brand header (`#1D1E20`), gold/copper accents (`#CC723F`), styled OTP card with 38px monospace digits, security guidance callout box, and Islamic signoff ("Barakallahu Feekum").
    - Added `reset_in_memory_state()` utility for testing and long-running processes.
  - `src/Core/FreeRegHandler.php`:
    - Verified registration relies solely on core `user_register` hook integration for clean single-code generation.
  - `src/Admin/AdminPortal.php`:
    - Updated `$default_verify_template` to match the enhanced layout structure with `{code}`, `{user_name}`, `{site_name}`, and `{expiry_hours}` placeholders.
### Task 54: Add "Matchmaker Admin" Role with Restricted WP-Admin Access
- **Objective**:
  - Add a dedicated `matchmaker_admin` ("Matchmaker Admin") user role with custom capability `manage_matchmaker` that can access exclusively matchmaking-related administrative screens in WordPress wp-admin.
- **Implemented**:
  - `src/Admin/AdminPortal.php`:
    - Added `register_role_and_caps()` method that registers the `matchmaker_admin` role with `manage_matchmaker` and `read` capabilities, and grants `manage_matchmaker` to `administrator`.
    - Hooked `register_role_and_caps` to `init` and `DBMigrator::activate()`.
    - Updated all menu and submenu capability requirements from `manage_options` to `manage_matchmaker`.
    - Added `restrict_admin_menus_for_matchmaker_admin()` on `admin_menu` (priority 9999) to remove core WordPress menus (Dashboard, Posts, Media, Pages, Comments, Appearance, Plugins, Users, Tools, Settings) and third-party plugin menus (PMPro, Elementor) for users with `manage_matchmaker` who do not have full `manage_options`.
    - Added `enforce_matchmaker_admin_screen_restrictions()` on `admin_init` redirecting non-whitelisted admin screen visits to `admin.php?page=matchmaking-pool`.
    - Updated `handle_admin_actions()` and `render_logs_page()` to authorize using `manage_matchmaker`.
  - `src/Frontend/AuthController.php`:
    - Updated `custom_role_based_login_redirect()` to automatically direct `matchmaker_admin` users directly to the Pool Browser (`admin.php?page=matchmaking-pool`).
    - Updated `custom_hide_admin_bar_for_subscribers()` to preserve admin bar visibility for `manage_matchmaker` holders.
  - `src/Service/EmailVerificationService.php`:
    - Updated `is_user_verified()` so `manage_matchmaker` holders bypass verification gating.
  - `tests/bootstrap.php`:
    - Added WP Role, menu registration, and capability mocking stubs.
  - `tests/Unit/AdminWorkflowTest.php` & `tests/Unit/AuthAndRedirectsTest.php`:
    - Added automated unit tests verifying role registration, capability inheritance, menu stripping, and login redirects.
  - `context/admin_portal.md`:
### Task 55: Populate Origin / Ethnicity and Preferred Origin from ethinicity.json
- **Objective**:
  - Ingest the full nationalities dataset from `assets/file/ethinicity.json` to populate options for `user_origin` (Origin / Ethnicity) and `pref_origin` (Preferred Origin).
  - Ensure that changes to Origin / Ethnicity have **no impact** on the Country, State, and City cascading selections.
  - Ensure `pref_origin` has a first option of **"Any Origin"** followed by all nationalities, and that selecting "Any Origin" matches candidates seamlessly in the matching engine and manual matchmaker.
- **Implemented**:
  - `src/Frontend/FieldGenerator.php`:
    - Added `get_ethnicity_data(): array` to read and decode `assets/file/ethinicity.json` with fallback defaults.
    - Updated `options_origin(): array` to return sorted nationalities with `'Select origin'` placeholder.
    - Updated `options_pref_origin(): array` to return `'Any Origin'` as the first option followed by all nationalities.
  - `src/Core/MatchingEngine.php`:
    - Updated `compute_flexible_score()` origin matching closure `$in_list` to support `'Any Origin'`, `'No Preference'`, and `'Any'` alongside comma-separated list matching.
  - `src/Repository/MatchRepository.php`:
    - Updated `get_manual_match_candidates()` so passing `'Any Origin'` or `'Any'` does not incorrectly filter out candidates via strict WHERE clauses, while still scoring specific origin preferences accurately.
  - `src/View/admin/pool/manual-match.php`:
    - Replaced the `f_origin` text input with a `<select>` dropdown populated dynamically with `$origin_options` from `$fg->options_pref_origin()`.
  - `context/matching_engine.md`:
    - Updated flexible scoring documentation for Dimension 1 (Origin / Ethnicity) referencing `ethinicity.json` and `'Any Origin'`.
  - `tests/Unit/LocationCascadeTest.php` & `tests/Unit/MatchingEngineTest.php`:
    - Added comprehensive unit tests validating `options_origin()`, `options_pref_origin()`, and flexible score calculation for `'Any Origin'`.
### Task 56: Gender-Specific Modesty and Preferred Modesty Options
- **Objective**:
  - Show modesty options based on gender for both self-profile (`user_modesty`) and preferred partner modesty (`pref_modesty`).
  - For Female: `Full Veil`, `Abaya & Hijab`, `Hijab Only`, `Modest`, `Modern / Casual`
  - For Male: `Traditional`, `Conservative`, `Moderate`, `Casual Modern`, `Trend-focused`
- **Implemented**:
  - `src/Frontend/FieldGenerator.php`:
    - Added `options_modesty_female()`, `options_modesty_male()`, `options_modesty(string $gender = 'female')`.
    - Added `options_pref_modesty_female()`, `options_pref_modesty_male()`, `options_pref_modesty(string $gender = 'female')` with `'No Preference'`.
    - Updated `render_single_field()` to dynamically evaluate `$user_gender` for `user_modesty` and `$pref_gender` (or opposite of `$user_gender`) for `pref_modesty`.
  - `assets/js/matchmaking-form.js`:
    - Added real-time gender-based modesty synchronization.
    - Switching `user_gender` radio dynamically updates `user_modesty` dropdown options and auto-syncs `pref_gender` to opposite gender.
    - Switching `pref_gender` radio dynamically updates `pref_modesty` dropdown options in real time.
  - `src/View/admin/pool/manual-match.php`:
    - Replaced `f_modesty` text input with gender-aware `<select>` dropdown populated via `$fg->options_pref_modesty($cand_gender)`.
    - Added client-side JavaScript syncing `#mm_f_modesty` when candidate gender `#mm_f_gender` changes.
  - `tests/Unit/MatchingEngineTest.php` & `tests/Unit/FormWizardAndShortcodesTest.php`:
### Task 57: Robust Real-Time Modesty and Preferred Modesty Option Synchronization
- **Objective**:
  - Fix real-time updating of `user_modesty` and `pref_modesty` select options when changing `user_gender` and `pref_gender` radio selections.
  - Eliminate browser script caching issues by adding dynamic filemtime versioning to frontend assets.
- **Implemented**:
  - `assets/js/matchmaking-form.js`:
    - Upgraded `updateCustomSelect()` to support multi-layer selector fallbacks (by form query, document ID `form-field-{name}`, and name attribute).
    - Added helper functions `getUserGender()` and `getPrefGender()` to robustly extract active gender states.
    - Added comprehensive multi-level event listeners across `'change'`, `'click'`, Elementor option wrapper clicks, and jQuery event hooks.
    - Implemented immediate initial synchronization on script load so options match pre-selected gender values instantly.
    - Added automatic mutual gender suggestion (switching `user_gender` to Male auto-checks Female for `pref_gender` and vice-versa).
  - `src/Frontend/FormController.php`:
    - Updated asset enqueueing to use dynamic `filemtime()` for `matchmaking-form.js` and `matchmaking-form.css`, ensuring fresh scripts are loaded across all user browsers without caching delays.
  - **Verification**: Ran full automated test suite (`tests/run_tests.php`) — all 78 unit and integration tests passed with 100% success rate (0 errors, 0 failures).

---

### Task 58: Photo Required Mark, Yearly Income Range Labels, 48h Unverified User Purge, PMPro Email Change Verification & 60min Expiry
- **Objective**:
  1. Add required red asterisk (`*`) to the Profile Photos section header and individual upload field labels.
  2. Rename `"Income Range"` $\rightarrow$ `"Yearly Income Range"` and `"Preferred Income Range"` $\rightarrow$ `"Preferred Yearly Income Range"` across frontend forms and admin single user view.
  3. Implement automatic 48-hour unverified account purging with Action Scheduler daily recurring job `mm_purge_unverified_users_job` (canceling PMPro memberships, deleting from pool, and removing user accounts while preserving admins).
  4. Intercept email changes on PMPro account / edit profile pages: preserve existing `user_email`, store new email in `mm_pending_new_email`, dispatch 6-digit OTP to the new email address, and render non-intrusive warning notice banner and modal popup on PMPro Account and Member Dashboard pages without locking access.
  5. Update verification code expiration default to 60 minutes (3600 seconds) and display "60 minutes" in email templates.
- **Implemented**:
  - `src/Frontend/FieldGenerator.php`:
    - Updated `upload()` to include label with `<span class="mm-required-star" style="color:#e11d48;font-weight:700;">*</span>`.
    - Updated `section_open()` to accept `$required = false` parameter and render `<span class="mm-required-star" style="color:#e11d48;font-weight:700;">*</span>` on section headers.
    - Updated `$select_configs` for `'user_income'` $\rightarrow$ `'Yearly Income Range'` and `'pref_income'` $\rightarrow$ `'Preferred Yearly Income Range'`.
  - `src/Frontend/FormController.php`:
    - Passed `$required = true` to `section_open('camera', 'Profile Photos', ..., 'upload-section', true)`.
  - `src/View/admin/pool/user-single.php`:
    - Updated table header labels to `"Yearly Income Range"` and `"Preferred Yearly Income Range"`.
  - `src/Repository/MatchRepository.php`:
    - Added `delete_pool_user(int $user_id): void` to encapsulate candidate deletion and match pair cleanup in compliance with the Single Database Authority rule.
  - `src/Service/EmailVerificationService.php`:
    - Updated `CODE_EXPIRY_SECONDS` constant and `get_expiry_seconds()` default to `3600` (60 minutes).
    - Updated `get_email_html()` to display `"60 minutes"` when 1 hour is configured.
    - Implemented `purge_unverified_users()` and daily Action Scheduler recurring worker `mm_purge_unverified_users_job`.
    - Hooked into `user_profile_update_errors` and `personal_options_update` via `intercept_profile_email_update()` and `intercept_personal_options_update()` to intercept PMPro / WordPress profile email updates.
    - Implemented `generate_and_send_pending_code()`, `send_pending_email_verification()`, and `verify_pending_email_code()` to handle OTP lifecycle for pending email changes.
    - Registered AJAX actions `wp_ajax_mm_verify_pending_email_code` and `wp_ajax_mm_resend_pending_email_code`.
    - Implemented `render_pending_email_notice()` and `render_pending_email_notice_on_pmpro_account()` hooked to `pmpro_account_preheader` and `pmpro_member_profile_edit_after_panel`.
  - `src/View/frontend/portal/portal.php`:
    - Added `render_pending_email_notice()` above tab panels for seamless notice banner rendering on the Member Dashboard.
  - `tests/bootstrap.php`:
    - Added `wp_delete_user`, `WP_Error`, `is_wp_error`, and `Fakewpdb::delete` test doubles.
    - Updated `wp_update_user` mock to support updating `user_email`.
  - `tests/Unit/EmailVerificationTest.php` & `tests/Unit/FormWizardAndShortcodesTest.php`:
    - Added unit tests for 60-minute expiry, 48-hour purge worker, profile email update interception, pending email verification, notice banner rendering, required photo mark, and yearly income labels.
  - **Verification**: Ran full automated test suite (`tests/run_tests.php`) — all 85 tests passed with 100% success rate (0 errors, 0 failures).

---

### Task 59: PMPro Profile Email Interception & Multi-Layer Notice Delivery
- **Objective**: Fix email change interception on PMPro's custom frontend member profile edit form (`[pmpro_member_profile_edit]`) and ensure the pending email verification notice banner and OTP popup modal render reliably on all PMPro account views and member dashboards.
- **Root Cause & Discovery**:
  - Investigated local PMPro plugin source code (`paid-memberships-pro/includes/profile.php`).
  - Discovered PMPro frontend form does **not** trigger WordPress core's `user_profile_update_errors` (which only runs in WP admin `edit_user()` context).
  - PMPro triggers its own action: `do_action_ref_array('pmpro_user_profile_update_errors', array(&$errors, $update, &$user))` where `$errors` is an array (not `WP_Error`) and `$user` is a `stdClass` object.
  - PMPro triggers `do_action('pmpro_personal_options_update', $user->ID)` before validation.
  - PMPro calls `wp_update_user($user)` directly if `$errors` is empty.
  - Standard shortcode rendering bypasses `pmpro_account_preheader` template action.
- **Implemented**:
  - `src/Service/EmailVerificationService.php`:
    - **Layer 1 (`pmpro_user_profile_update_errors`)**: Added `intercept_pmpro_profile_update(mixed &$errors, bool $update, \stdClass &$user)` supporting array errors, detecting new email, saving to `mm_pending_new_email`, generating and dispatching 6-digit OTP to the new email, and reverting `$user->user_email`, `$_POST['user_email']`, and `$_POST['email']` to the current verified email.
    - **Layer 2 (`pmpro_personal_options_update`)**: Added `intercept_pmpro_personal_options_update(int $user_id)`.
    - **Layer 3 (`wp_pre_insert_user_data`)**: Added universal WordPress core filter `intercept_wp_pre_insert_user_data()` to prevent unverified email changes from persisting into `wp_users` table across any frontend or backend caller, while bypassing during OTP-verified updates via `$GLOBALS['__mm_updating_verified_email']`.
    - **Layer 4 (Multi-Hook Banner Delivery)**: Hooked `the_content` (`filter_the_content_for_pending_email_notice()`), `pmpro_shortcode_account`, `pmpro_shortcode_member_profile_edit` (`filter_pmpro_shortcode_notice()`), and `pmpro_account_bullets_top` (`render_pending_email_notice_on_pmpro_account()`) with deduplication checks (`!str_contains($content, 'mm-pending-email-notice')`).
  - `tests/bootstrap.php`:
    - Added `apply_filters` and `do_action` support for WordPress hook testing.
    - Enhanced `wp_update_user` stub to run `wp_pre_insert_user_data` and accept `stdClass` objects.
  - `tests/Unit/EmailVerificationTest.php`:
    - Added unit test `test_pmpro_profile_update_hook_intercepts_array_errors_and_resets_user_object()`.
    - Added unit test `test_pmpro_personal_options_update_hook_saves_pending_email()`.
    - Added unit test `test_wp_pre_insert_user_data_prevents_unverified_email_update()`.
    - Added unit test `test_content_and_shortcode_filters_prepend_notice_banner()`.
  - **Verification**: Ran full automated test suite (`tests/run_tests.php`) — all 89 unit and integration tests passed with 100% success rate (0 errors, 0 failures).

---

### Task 60: PMPro Notice Page Scoping, Enhanced UI Design, Email Update Settings & Religion-Neutral Templates
- **Objective**:
  1. Scope pending email verification notice strictly to PMPro Membership Account and Edit Profile pages (remove from dashboard and questionnaire/form wizard).
  2. Redesign the pending email notice banner and OTP popup modal with a modern, elegant, brand-aligned UI (`#CC723F`, `#1D1E20`, rounded pill buttons, subtle shadows, clean typography, SVG icons).
  3. Add admin settings fields for Email Update Verification subject and body template (`mm_email_verify_update_subject`, `mm_email_verify_update_template`) in the Admin Settings page with WYSIWYG editor and tag variables table.
  4. Remove religion-specific greetings and footer text (e.g. "Assalamu Alaikum", "Barakallahu Feekum • May Allah bless your journey", "PREMIUM MUSLIM MATCHMAKING") across all default email templates.
- **Implemented**:
  - `src/View/frontend/portal/portal.php`:
    - Removed `render_pending_email_notice()` call so the Member Dashboard is clean and uncluttered.
  - `src/Service/EmailVerificationService.php`:
    - Updated `filter_the_content_for_pending_email_notice()` to strictly allow PMPro account / edit profile pages while explicitly blocking dashboard and questionnaire shortcodes (`[az_profile]`, `[matchmaker_member_portal]`, `[matchmaking_form]`, `[matchmaking_field]`).
    - Redesigned `render_pending_email_notice()` with warm gradient banner, SVG email icon, terracotta `#CC723F` interactive button, and an elegant OTP modal with backdrop blur, rounded corners, and clear PIN inputs.
    - Updated `send_pending_email_verification()` to dynamically pull custom subject (`mm_email_verify_update_subject`) and template (`mm_email_verify_update_template`) with merge tags (`{code}`, `{user_name}`, `{new_email}`, `{site_name}`, `{expiry_hours}`).
    - Removed religion-specific text from `wrap_email_layout()` (sub-badge updated to "PREMIUM ARAB MATCHMAKING", footer updated to "Thank you for being part of Arab Zawaj") and greetings in `get_email_html()` and `send_pending_email_verification()`.
  - `src/Admin/AdminPortal.php` & `src/View/admin/settings/settings.php`:
    - Added settings options saving and retrieval for `mm_email_verify_update_subject` and `mm_email_verify_update_template`.
    - Added rich WYSIWYG editor and available placeholders table in the Email Verification Settings card.
    - Updated default verification email template preview to remove religious terms.
  - `tests/Unit/EmailVerificationTest.php`:
    - Added unit test `test_dashboard_and_form_wizard_do_not_render_pending_email_notice()`.
    - Added unit test `test_custom_update_email_settings_affect_subject_and_template()`.
    - Added unit test `test_default_emails_do_not_contain_religious_terms()`.
  - **Verification**: Ran automated test runner (`tests/run_tests.php`) — all 92 unit and integration tests passed with 100% success rate (0 errors, 0 failures).

---

### Task 61: PMPro Profile Edit Post-Submission Redirect & Modal Popup Visibility Fix
- **Objective**:
  1. Redirect members to the PMPro Membership Account page immediately upon successful submission of the frontend profile edit form (`$_POST['action'] === 'update-profile'`).
  2. Fix popup modal opening on the edit profile page by adding inline handlers, global helper functions (`window.mmOpenPendingVerifyModal`), document delegated click listeners, and DOM body relocation to bypass any CSS stacking context or overflow traps.
- **Implemented**:
  - `src/Frontend/AuthController.php`:
    - Hooked `profile_update` action to `redirect_after_pmpro_profile_update()`.
    - Detects frontend `$_POST['action'] === 'update-profile'` submissions and executes safe redirection to `ProfileService::get_membership_account_url()` with graceful fallback for sent headers.
  - `src/Service/EmailVerificationService.php`:
    - Redesigned popup modal trigger in `render_pending_email_notice()`:
      - Added `window.mmOpenPendingVerifyModal` and `window.mmClosePendingVerifyModal`.
      - Added dynamic DOM relocation (`document.body.appendChild(modal)`) upon opening to eliminate containment/stacking context issues from parent form or theme containers.
      - Attached direct inline `onclick`, delegated `document.addEventListener('click')`, and overlay backdrop dismiss handlers.
      - Increased modal overlay `z-index` to `999999`.
  - `tests/bootstrap.php` & `tests/Unit/AuthAndRedirectsTest.php`:
    - Added `wp_safe_redirect` and `wp_redirect` stubs in test harness.
    - Added unit test `test_pmpro_profile_update_redirects_to_membership_account_page()`.
  - **Verification**: Ran automated test suite (`tests/run_tests.php`) — all 93 unit and integration tests passed with 100% success rate (0 errors, 0 failures).

---

### Task 62: Fix wpautop & wptexturize Filter Priority and HTML/JS Syntax Corruption
- **Objective**:
  - Resolve HTML and JavaScript corruption on PMPro edit profile pages where WordPress core `wpautop` and `wptexturize` filters were injecting unexpected `<p>`, `</p>`, and converting `&&` into `&#038;&#038;` inside JavaScript event handlers.
- **Implemented**:
  - `src/Service/EmailVerificationService.php`:
    - Updated hook priorities for `the_content`, `pmpro_shortcode_account`, and `pmpro_shortcode_member_profile_edit` from priority `5` to priority `99` so they run AFTER WordPress core `wpautop` (priority 10) and `wptexturize` (priority 10).
    - Removed HTML comment tags (`<!-- ... -->`) from `render_pending_email_notice()` that triggered core `wpautop` paragraph insertions.
    - Replaced all inline logical `&&` expressions in HTML `onclick` attributes with safe standard condition syntax (`if (window.mmOpenPendingVerifyModal) { ... }`).
    - Added dedicated backdrop click dismissal via JavaScript event listener (`modal.addEventListener('click')`).
- **Verification**:
  - Executed automated test suite (`tests/run_tests.php`) — all 93 unit and integration tests passed with 100% success rate (0 errors, 0 failures).

### Task 63: 3-Group Multi-Membership Architecture (Base Subscriptions + 1-on-1 VIP Add-on Service)
- **Objective**:
  - Restructure multiple membership levels into 3 distinct PMPro groups:
    - **Group 1 (Free Base Tier)**: Free registration plan.
    - **Group 2 (Paid Recurring Subscriptions)**: Monthly Matchmaking and Event Access.
    - **Group 3 (One-Time VIP Add-on Service)**: 1-on-1 VIP Matchmaking service that coexists with any base tier.
  - Upgrading to a Group 2 subscription cancels lingering Group 1 (Free) levels, while Group 3 (1-on-1) is never cancelled by subscription changes.
- **Implemented**:
  - `src/Core/DBMigrator.php`: Added `has_one_on_one tinyint(1) NOT NULL DEFAULT 0` column and index `KEY idx_one_on_one` in `wp_matchmaking_pool` (DB version 2.6.0).
  - `src/Repository/MatchRepository.php`:
    - Added `mm_has_one_on_one` to `META_KEYS`.
    - Added `update_pool_one_on_one(int $user_id, bool $has_one_on_one): void` and `has_one_on_one(int $user_id): bool`.
    - Added `has_one_on_one` query filtering support in `search_pool()`.
  - `src/Core/PMProSync.php`:
    - Added `BASE_TIER_PRIORITY` (`monthly` => 3, `event` => 2, `free` => 1) for base subscription resolution.
    - Added `has_active_one_on_one_service(int $user_id): bool` checking active Group 3 levels.
    - Updated `get_current_user_type(int $user_id): string` to resolve base subscription tier independently.
    - Updated `maybe_cancel_free_levels(int $user_id): void` to only cancel Group 1 Free levels, preserving Group 3 levels.
    - Updated `sync_pmpro_level_to_user_type()` and `sync_all_membership_levels()` to persist both `user_type` and `has_one_on_one`.
  - `src/Frontend/PortalController.php`, `src/View/frontend/portal/portal.php`, `src/View/frontend/portal/tab-profile.php`:
    - Displayed sleek `★ 1-on-1 VIP` badge in Member Portal header.
    - Displayed dedicated `1-on-1 VIP Matchmaking Active` info card in Member Profile tab.
  - `src/Admin/AdminPortal.php`, `src/View/admin/pool/pool-list.php`, `src/View/admin/settings/settings.php`:
    - Added 1-on-1 VIP Service filter toggle (All / Has 1-on-1 / No 1-on-1) in Admin Pool Browser.
    - Displayed `★ 1-on-1 VIP` badge in Candidate Pool list rows.
    - Updated PMPro plan connector dropdown labels to explicitly show Group 1, Group 2, and Group 3 designations.
  - `tests/Unit/SettingsAndPlanMappingTest.php`, `tests/Unit/AdminWorkflowTest.php`, `tests/Unit/PortalAndEventsTest.php`:
    - Added unit test `test_one_on_one_coexists_with_free_and_monthly_tiers()`.
    - Added unit test `test_pool_search_query_with_has_one_on_one_filter()`.
    - Added unit test `test_portal_renders_one_on_one_vip_badge_and_profile_card()`.
- **Verification**:
  - Ran automated test runner (`tests/run_tests.php`) — all 95 unit and integration tests passed with 100% success rate (0 errors, 0 failures).

### Task 64: Fix 1-on-1 VIP Service Detection, Multi-Source Fallbacks & Pool Filtering
- **Objective**:
  - Resolve issue where 1-on-1 VIP badges and filter were not displaying in the Admin Candidate Pool and Member Portal.
- **Implemented**:
  - `src/Core/DBMigrator.php`: Added direct `ALTER TABLE` column patch and automatic SQL backfill (`UPDATE wp_matchmaking_pool SET has_one_on_one = 1 WHERE user_type = 'one_on_one'`).
  - `src/Core/PMProSync.php`: Enhanced `has_active_one_on_one_service()` to verify live PMPro membership levels (`pmpro_getMembershipLevelsForUser` / `pmpro_getMembershipLevelForUser`), usermeta `mm_has_one_on_one`, and legacy usermeta `user_type = 'one_on_one'`.
  - `src/Service/ProfileService.php`: Added `has_one_on_one(int $user_id): bool` with automatic self-healing sync to `wp_usermeta` and `wp_matchmaking_pool`.
  - `src/Repository/MatchRepository.php`:
    - Updated `has_one_on_one(int $user_id)` to check PMProSync, pool table rows, and usermeta.
    - Updated `search_pool()` filtering so filtering for 1-on-1 VIP matches both `has_one_on_one = 1` and `user_type = 'one_on_one'`.
  - `src/View/admin/pool/pool-list.php`: Updated table row condition `$c_has_vip` to check `has_one_on_one`, `user_type = 'one_on_one'`, and `MatchRepository::has_one_on_one($uid)`.
  - `src/View/frontend/portal/portal.php` & `src/View/frontend/portal/tab-profile.php`: Updated VIP check to verify all sources.
- **Verification**:
  - Ran automated test runner (`tests/run_tests.php`) — all 95 unit and integration tests passed with 100% success rate (0 errors, 0 failures).

---

### Task 65: PMPro Services Group (ID: 3), Services Tab in Member Portal, Event Member Parity, Settings 5-Tab Reorganization with Custom Level Tags, Admin Service Purchase Email, and Pool Browser Services Filter
- **Objective**:
  - Implement full PMPro Services Group support (default Group ID: 3) as add-on purchases requiring an active base membership plan (`free`, `monthly`, `event`).
  - Introduce a dedicated "Services" tab (`data-tab="services"`) in the Member Portal displaying a dynamic responsive grid of all Group 3 PMPro service levels with dynamic descriptions, custom level tags, formatted prices, and checkout CTAs.
  - Fix desktop and mobile padding/spacing for `.mm-upsell-card` and `.mm-upsell-btn` ("Monthly membership required").
  - Provide full feature parity for Event members with Free members (can view Matches tab, receive admin-approved matches without quota restrictions, receive in-app notifications, and view the Monthly upsell card).
  - Reorganize Admin Settings into 5 modern, tabbed sections with URL hash navigation (*General & Mode*, *Membership & Services*, *Quotas & Matching*, *Email Templates*, *Shortcodes & System*).
  - Add custom level Tag/Badge name inputs for all registered PMPro levels in Admin Settings.
  - Implement Admin Service Purchase transactional email notification with customizable template and placeholder tokens.
  - Update Candidate Pool Browser filter for Services / VIP.
- **Implemented**:
  - `src/Core/PMProSync.php`:
    - Removed standalone `one_on_one` from base level mapping (`DEFAULT_LEVEL_MAPPING`).
    - Added `get_services_group_id()`, `get_services_levels()`, `is_service_level(int $level_id): bool`.
    - Added `get_level_tags()` and `get_level_tag(int $level_id, string $default): string`.
    - Hooked `pmpro_registration_checks` via `check_service_requires_basic_membership()` to block checkout if user lacks a basic plan.
    - Hooked `pmpro_after_checkout` via `handle_checkout_sync()` to trigger admin email alert when a service level is purchased.
    - Updated `has_active_one_on_one_service()` to dynamically check all Group 3 service levels.
  - `src/Service/NotificationService.php`:
    - Added `send_admin_service_purchase_notification(int $user_id, int $level_id, mixed $morder = null): bool` with placeholder replacements (`{site_name}`, `{service_name}`, `{service_price}`, `{user_name}`, `{user_email}`, `{user_id}`, `{purchase_date}`, `{admin_profile_url}`).
    - Logged structured event `admin_service_purchase_alert` in `wp_matchmaker_logs`.
    - Removed event tier heartbeat pulse suppression so event members receive in-app notifications.
  - `src/Service/MatchService.php` & `src/Repository/MatchRepository.php`:
    - Updated `is_info_only_pair()` and `process_admin_approve()` / `approve_match()` to allow admin approval for event members.
    - Updated `format_tier_label()` to incorporate custom level tags.
  - `src/Frontend/PortalController.php` & Views:
    - Created `src/View/frontend/portal/tab-services.php` rendering service cards with custom tags, prices, descriptions, and dynamic action buttons (`✓ Active Service` vs `Purchase Service →`).
    - Updated `portal.php` to render Matches and Services navigation tabs for all member tiers.
    - Fixed padding and margin styling for `.mm-upsell-card` and `.mm-upsell-btn` in `assets/css/member-portal.css`.
  - `src/Admin/AdminPortal.php` & Admin Views:
    - Re-architected `src/View/admin/settings/settings.php` into 5 clean, responsive tabs with hash persistence in `assets/js/admin-matchmaker.js`.
    - Added Services Group ID configuration and custom Tag/Badge input fields for each PMPro level.
    - Updated `src/View/admin/pool/pool-list.php` Services filter and table headers.
    - Removed event-only approval restrictions in `match-single.php` and `user-single.php`.
  - `tests/`:
    - Updated `SettingsAndPlanMappingTest.php`, `PortalAndEventsTest.php`, `HeartbeatAndNotificationsTest.php`, and added `test_send_admin_service_purchase_notification()` in `NotificationAndApprovalTest.php`.
- **Verification**:
---

### Task 66: Fix Admin Settings Tabs Switching & Remove Header Card from Services Tab
- **Objective**:
  - Remove "Accelerate Your Matrimony Journey" header card from the Member Portal Services tab (`src/View/frontend/portal/tab-services.php`).
  - Resolve tab navigation in Admin Settings page to ensure instant switching across all 5 panels (*General & Mode*, *Membership & Services*, *Quotas & Matching*, *Email Templates*, *Shortcodes & System*) with hash-based URL routing.
- **Implemented**:
  - `src/View/frontend/portal/tab-services.php`: Removed the `.mm-services-header-card` banner so only the service card grid is rendered directly.
  - `src/View/admin/settings/settings.php`: Embedded a resilient, dependency-free vanilla JS tab switcher handling direct tab clicks, active class toggling, inline style management, hash URL persistence, and `hashchange` browser events.
  - `assets/js/admin-matchmaker.js`: Enhanced delegated tab click handler with regex-clean tab key resolution and `hashchange` event listeners.
  - `src/Admin/AdminPortal.php`: Updated `enqueue_admin_assets()` to ensure styles and scripts are loaded across all screen hook variants.
  - `tests/Unit/PortalAndEventsTest.php`: Updated test assertions to match the clean services grid markup.
- **Verification**:
---

### Task 67: Comprehensive Dynamic PMPro Service Levels Discovery & Multi-Source Group Retrieval
- **Objective**:
  - Ensure all PMPro membership levels in the Services Group (Group ID: 3 or configured group) and add-on levels are dynamically retrieved and presented in the Member Portal Services tab.
- **Implemented**:
  - `src/Core/PMProSync.php`:
    - Completely overhauled `get_services_levels()` with multi-source discovery:
      1. PMPro 3.0+ & MMPU functions (`pmpro_getLevelsForGroup`, `pmpro_getMembershipLevelsForGroup`, `pmpro_get_levels_for_group`, `pmprommpu_get_levels_for_group`).
      2. PMPro 3.0+ Level Group objects (`pmpro_get_level_groups()`).
      3. Registered level properties (`group_id`, `group`, `membership_group_id`, `groups`) and level meta (`pmpro_get_level_meta` with `group_id`, `group`, `pmprommpu_group`).
      4. PMPro options (`pmpro_groups`, `pmpro_level_groups`, `pmprommpu_groups`).
      5. Direct database inspection across `wp_pmpro_membership_levels` and all junction tables (`wp_pmpro_membership_levels_groups`, `wp_pmpro_membership_level_groups`, `wp_pmpro_groups_levels`, `wp_pmpro_group_levels`, `wp_pmpro_levels_groups`).
      6. Plugin tier mapping (`mm_pmpro_tier_mapping`) explicit `service` assignments.
      7. Dynamic hydration of complete `PMPro_Level` objects with names, prices, descriptions, and checkout URLs.
    - Updated `is_service_level()` to verify all dynamic discovery sources.
  - `src/View/admin/settings/settings.php`:
    - Added `<option value="service">Service / Add-on (Group 3)</option>` into the PMPro Plan Connector table so admins can also explicitly map levels to Services if needed.
  - `tests/Unit/SettingsAndPlanMappingTest.php`:
    - Added `test_dynamic_services_levels_discovery()` verifying multi-source level discovery.
- **Verification**:
  - Ran automated test runner (`tests/run_tests.php`) — all 98 unit and integration tests passed with 100% success rate (0 errors, 0 failures).

---

## 2026-09-11 — Task 68: Dynamic Service Special Tag Display, Banner Removal & Service Purchase Redirect to Dashboard

- **Objective**:
  1. Remove the static `.mm-vip-service-card` banner from `tab-profile.php`.
  2. Dynamically display service tags/badges according to the actual service(s) purchased by the user (e.g. "Social Media Post", custom configured tags, or other Group 3 services) in Member Portal header, Member Profile tab, and Admin Pool views.
  3. Redirect users directly to the Member Portal Dashboard (`/dashboard/`) when purchasing an add-on service instead of redirecting to the matchmaking questionnaire form.
- **Changes**:
  - `src/Core/PMProSync.php`:
    - Added `get_user_active_services(int $user_id): array` to discover and return all active service levels held by the member with their dynamic display tags (`get_level_tag()`) or level names.
    - Updated `has_active_one_on_one_service()` to leverage `get_user_active_services()`.
  - `src/View/frontend/portal/tab-profile.php`:
    - Completely removed the `.mm-vip-service-card` banner.
    - Added dynamic active service tags (`.az-badge-service`) alongside the membership tier badge in the card header.
  - `src/View/frontend/portal/portal.php`:
    - Updated portal header actions to dynamically render active service badges for each service held by the user.
  - `src/Frontend/AuthController.php`:
    - Updated `custom_pmpro_level_based_registration_redirect()` to verify `PMProSync::is_service_level()`, routing service checkouts directly to `get_dashboard_url()` and membership plan checkouts to `get_form_url()`.
  - `src/View/admin/pool/pool-list.php` & `src/View/admin/pool/user-single.php`:
    - Updated candidate listing and profile views to render dynamic service tags for each active service held by candidate members.
  - `tests/Unit/SettingsAndPlanMappingTest.php`, `tests/Unit/AuthAndRedirectsTest.php`, `tests/Unit/PortalAndEventsTest.php`:
    - Added unit test `test_get_user_active_services_returns_dynamic_tags_and_names()`.
    - Added unit test `test_pmpro_service_checkout_redirects_to_dashboard_and_membership_redirects_to_questionnaire()`.
    - Updated `test_portal_renders_dynamic_service_tag_and_removes_vip_banner()`.
- **Verification**:
  - Ran automated test runner (`tests/run_tests.php`) — all 100 unit and integration tests passed with 100% success rate (0 errors, 0 failures).

---

## 2026-09-11 — Task 69: Profile Tab Service Contact Notice & Repeat Service Purchases

- **Objective**:
  1. Add a branded contact notice banner on the Profile tab (`tab-profile.php`) for members with purchased services stating: *"You will be contacted by our team by your email or phone number regarding your [Service Name]"*.
  2. Enable unlimited repeat purchases for all add-on services on the Services tab (`tab-services.php`), displaying *"Purchase Again →"* alongside a *"Purchased / Active"* indicator.
  3. Ensure PMPro allows repeated checkouts for service levels without throwing "already a member" duplicate blockers.
- **Changes**:
  - `src/View/frontend/portal/tab-profile.php`:
    - Added branded `.mm-service-notice-card` banner rendering the contact notice dynamically with all active service names/tags.
  - `src/View/frontend/portal/tab-services.php`:
    - Updated service card footer to always display the active purchase CTA button (`Purchase Again →` for previously purchased services and `Purchase Service →` for new services) alongside an active indicator.
  - `src/Core/PMProSync.php`:
    - Added `filter_pmpro_has_membership_level_for_checkout()` and `filter_pmprommpu_checkout_level()` to bypass PMPro duplicate level restrictions during service checkout.
    - Added filter `pmpro_allow_duplicate_level_checkouts` returning `true`.
  - `tests/Unit/PortalAndEventsTest.php` & `tests/Unit/SettingsAndPlanMappingTest.php`:
    - Added assertions for `mm-service-notice-card` and team contact message in `test_portal_renders_dynamic_service_tag_and_removes_vip_banner()`.
    - Added unit test `test_service_repeat_checkout_filters_bypass_duplicate_checks()`.
- **Verification**:
  - Executed automated test runner (`tests/run_tests.php`) — all 101 unit and integration tests passed with 100% success rate (0 errors, 0 failures).

---

## 2026-09-11 — Task 70: Service Badges Placement, Base Membership Retention & Cancellation Blocking with Active Services

- **Objective**:
  1. Relocate service badges: remove service badges from Portal header (`portal.php`) and from beside the basic membership badge (`tab-profile.php`), displaying them exclusively inside the Purchased Service notice card on `tab-profile.php`.
  2. Format the Purchased Service card layout:
     - Title: "Purchased Service"
     - Message: "You will be contacted by our team by your email or phone number regarding your service."
     - Badges: Dynamic service badges (`{{badges}}`) displayed inside the notice card.
  3. Ensure base membership (Free / Monthly / Event) remains intact and is never removed when a user purchases an add-on service.
  4. Block cancellation of base membership plans if the user has any active add-on service(s).
- **Changes**:
  - `src/View/frontend/portal/portal.php`:
    - Removed service badges from `.header-actions`.
  - `src/View/frontend/portal/tab-profile.php`:
    - Restructured `.mm-service-notice-card` with exact heading "Purchased Service", message text, and `.mm-service-badges-wrap` containing the service badges.
    - Cleaned `.az-mm-header` to only render the base tier badge (`● Monthly Member`, etc.).
  - `src/Core/PMProSync.php`:
    - Updated `get_current_user_type()` and `sync_pmpro_level_to_user_type()` to ensure add-on service levels never overwrite or cancel base memberships (Free tier remains completely intact upon purchasing any service).
    - Added `block_base_membership_cancellation_with_active_services()` and `maybe_block_cancel_page_for_active_services()` hooked to `pmpro_cancel_membership_level` and `template_redirect`, preventing users from cancelling their base membership while they have active services.
  - `tests/bootstrap.php`:
    - Updated `pmpro_cancelMembershipLevel` stub to apply `pmpro_cancel_membership_level` filter.
  - `tests/Unit/PortalAndEventsTest.php` & `tests/Unit/SettingsAndPlanMappingTest.php`:
    - Updated assertions verifying no header service badges and verifying exact Purchased Service card format.
    - Added unit test `test_service_purchase_keeps_free_membership_intact()`.
    - Added unit test `test_active_services_block_base_membership_cancellation()`.
- **Verification**:
  - Executed automated test runner (`tests/run_tests.php`) — all 103 unit and integration tests passed with 100% success rate (0 errors, 0 failures).

---

## 2026-09-11 — Task 71: Optional Parent Application Checkbox, Pool Browser Filter, and Profile Tab Display

- **Objective**:
  1. Add an optional checkbox on the first form step beneath the "Personal Information" header and above the "Full Name" and "Email" fields with label: `"I am a parent applying on behalf of my child"`.
  2. Save and persist this boolean value (`is_parent_applying`) in `wp_matchmaking_pool` and `wp_usermeta`.
  3. If checked, display this information in the Member Portal Profile tab under user details.
  4. Add a filter in the Admin Candidate Pool Browser (`admin.php?page=matchmaking-pool`) for parent application status and show an indicator/badge in candidate list and single user views.
- **Changes**:
  - `src/Core/DBMigrator.php`:
    - Added `is_parent_applying tinyint(1) NOT NULL DEFAULT 0` column and index `KEY idx_parent_applying (is_parent_applying)` to `wp_matchmaking_pool` schema and migration alter table logic. Bumped DB schema version to `2.7.0`.
  - `src/Repository/MatchRepository.php`:
    - Added `'is_parent_applying'` to `META_KEYS`.
    - Added `is_parent_applying` filter handling to `search_pool($filters)`.
  - `src/Frontend/FieldGenerator.php` & `assets/css/matchmaking-form.css`:
    - Added `is_parent_applying` checkbox rendering in `render_single_field()`.
    - Styled `.elementor-field-group-is_parent_applying` with `flex: 1 1 100% !important; width: 100% !important; min-width: 100% !important;` to ensure it spans the entire width of its row directly above Full Name and Email.
  - `src/Frontend/FormController.php`:
    - Included `is_parent_applying` in `get_user_form_values()`.
    - Rendered `is_parent_applying` at the top of Step 1 in `render_form()`.
    - Extracted and persisted `is_parent_applying` in `handle_ajax()` into both pool payload and user meta.
  - `src/View/frontend/portal/tab-profile.php`:
    - Added "Application Type: Parent applying on behalf of child" row under the "About Me" user details when `is_parent_applying` is true.
  - `src/Admin/AdminPortal.php` & `src/View/admin/pool/pool-list.php` & `user-single.php`:
    - Added `filter_parent_applying` dropdown (All Applications / Parent Applying / Self Applying) in Candidate Pool Browser.
    - Added `👨‍👩‍👧 Parent Applying` badge in Candidate list table rows and candidate profile header / details table.
  - `tests/DBMigratorTest.php`, `tests/Unit/FormWizardAndShortcodesTest.php`, `tests/Unit/AdminWorkflowTest.php`:
    - Updated version check to `2.7.0` and verified schema migration creates `is_parent_applying`.
    - Added unit test verifying field generator renders `is_parent_applying` and form shortcode output includes the checkbox.
    - Added unit test verifying `search_pool()` query generation with `is_parent_applying` filter.
- **Verification**:
  - Executed automated test runner (`tests/run_tests.php`) — all 103 unit and integration tests passed with 100% success rate (0 errors, 0 failures).

---
