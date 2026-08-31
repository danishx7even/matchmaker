# Arab Zawaj Matchmaker Plugin

An enterprise-grade, high-touch matrimony matchmaking engine and member portal for WordPress. 

The Matchmaker plugin decouples intensive candidate compatibility calculations from user web requests using **Action Scheduler** background processing, indexed SQL tables, WordPress **Heartbeat API** real-time polling, and an Admin Management Portal.

---

## Key Highlights

- **Asynchronous Matching Engine**: Offloads heavy matching logic to Action Scheduler background workers with candidate chunking and limit enforcement.
- **Bi-Directional Hard Gates & 6-Point Scoring**: Evaluates mutual compatibility across strict gates (Gender, Age Min/Max, Marital Status, Education) and flexible weighted scoring (Location, Origin/Ethnicity, Religion, Modesty, Height, Smoking/Drinking).
- **5-State Interactive Member Match Review**: Clean 2-tab frontend canvas (`[matchmaker_member_portal]`) featuring 5 distinct states: Discovery & Match Details, Full Profile Review, Waiting for Candidate Response, Declined Confirmation, and Mutual Match Contact Reveal.
- **Admin Management Hub**: Comprehensive control center for candidate pools, dual-profile match review, manual matchmaking with live filters, settings, dynamic PMPro plan matrix connector, and candidate gate debugger.
- **Centralized Structured Logging**: Dedicated logs for matching lifecycle events, admin actions, in-app notifications, and transactional HTML email dispatches with JSON inspection.
- **Environment Modes**: Switch between **Live Mode** and **Test Mode** with a safe 1-click test data reset tool (clears test matches, notifications, and logs while preserving candidate profiles).
- **Decoupled Integrations**: Seamless integration with **Paid Memberships Pro (PMPro)** and **Elementor Pro** free registration forms.
- **Automated Test Suite**: Built-in PHPUnit-compatible test runner and mock harness verifying units and end-to-end integration flows.

---

## System Architecture

```
matchmaker/
├── matchmaker.php                     # Main bootstrap & PSR-4 autoloader
├── AGENTS.md                          # Master Operational Guide & Architecture Invariants
├── BUILD_PLAN.md                      # Active Task Status File
├── HISTORY.md                         # Permanent Chronological Execution Log
├── README.md                          # Repository Documentation
│
├── context/                           # Technical Specifications & Feature Context
│   ├── matching_engine.md             # Hard gates, 6-point scoring, SQL queries
│   ├── admin_portal.md                # Admin pool browser, matches queue, settings
│   ├── member_portal.md               # 5-state member review flow, contact reveal
│   ├── pmpro_sync.md                  # PMPro level connector matrix, tier sync
│   ├── form_handler.md                # 37-field questionnaire wizard & shortcodes
│   ├── notifications.md               # Heartbeat API 15s polling, email templates
│   ├── free_registration.md           # Elementor Pro form decoupled validation
│   ├── auth_and_routing.md            # Dynamic page URL resolvers & redirects
│   ├── design_system.md               # Brand tokens (#CC723F), badges, layout
│   └── testing_guide.md               # Test suite & mock layer architecture
│
├── src/                               # PSR-4 Root: Matchmaker\
│   ├── Repository/
│   │   └── MatchRepository.php        # Single Database Authority ($wpdb queries)
│   ├── Service/
│   │   ├── MatchService.php           # Matching rules, flexible scoring & responses
│   │   ├── ProfileService.php         # Profile data assembly & dynamic page URLs
│   │   └── NotificationService.php    # In-app notifications & email delivery
│   ├── Core/
│   │   ├── DBMigrator.php             # Database installer & delta migrations (v2.4.0)
│   │   ├── MatchingEngine.php         # Async matching calculation & background jobs
│   │   ├── PMProSync.php              # Dynamic PMPro level mapping & tier sync
│   │   ├── FreeRegHandler.php         # Decoupled Elementor Free Registration handler
│   │   └── TestSeeder.php             # Test user & mock pool data seeder
│   ├── Frontend/
│   │   ├── AuthController.php         # Role-based redirects, PMPro login cards & logout URL
│   │   ├── FieldGenerator.php         # Form HTML input generator primitives
│   │   ├── FormController.php         # [matchmaking_form] & [matchmaking_field]
│   │   └── PortalController.php       # [matchmaker_member_portal] / [az_profile]
│   ├── Admin/
│   │   └── AdminPortal.php            # Admin menus, controller actions, settings & logs
│   ├── View/                          # Modular View Presentation Templates
│   │   ├── frontend/
│   │   │   └── portal/
│   │   │       ├── portal.php         # Member portal shell & tab navigation
│   │   │       ├── tab-profile.php    # Member profile tab
│   │   │       ├── tab-matches.php    # Matches tab wrapper
│   │   │       └── steps/             # 5 Modular Match Flow Steps
│   │   │           ├── step-1-discovery.php
│   │   │           ├── step-2-profile.php
│   │   │           ├── step-3-waiting.php
│   │   │           ├── step-4-decline.php
│   │   │           └── step-5-contact.php
│   │   └── admin/                     # Admin View Templates
│   │       ├── pool/                  # Candidate pool views & manual matchmaker
│   │       │   ├── pool-list.php
│   │       │   ├── user-single.php
│   │       │   └── manual-match.php
│   │       ├── matches/               # Matches queue & comparison review
│   │       │   ├── matches-list.php
│   │       │   └── match-single.php
│   │       ├── settings/              # Settings, Mode & Reset, PMPro Matrix
│   │       │   └── settings.php
│   │       └── logs/                  # 3-Tab Logs Hub & inspection modal
│   │           ├── logs.php
│   │           ├── tab-match-logs.php
│   │           ├── tab-notification-logs.php
│   │           ├── tab-debugger.php
│   │           └── modal-log-detail.php
│   └── functions.php                  # Global helper wrappers
│
├── assets/
│   ├── css/                           # admin-matchmaker.css, member-portal.css, matchmaking-form.css
│   └── js/                            # admin-matchmaker.js, member-portal.js, matchmaking-form.js, phone-mask.js
│
└── tests/                             # Automated Test Suite & Test Runner
    ├── bootstrap.php                  # Mock environment for WP Core, PMPro & Action Scheduler
    ├── run_tests.php                  # CLI test runner
    ├── DBMigratorTest.php             # Schema migration tests
    ├── Unit/                          # Settings, Quotas, Scoring, Modes, Logs, Manual Match tests
    └── Integration/                   # End-to-end user lifecycle flow test
```

---

## Core Features

### 1. Matching Engine & Flexible Scoring
- **Bi-Directional Hard Gates**: Candidates must mutually satisfy hard criteria before scoring:
  - Opposite Gender (unless otherwise specified)
  - Bi-directional Age compatibility within preferred ranges
  - Candidate Pool Active status (`is_active = 1`)
  - No existing active or mutually accepted match pair in the current billing cycle
- **6-Point Flexible Compatibility Score**:
  1. **Location Match** (1 point): Candidate location matches target preference.
  2. **Origin / Ethnicity Match** (1 point): Candidate ethnic origin matches target preference.
  3. **Religion Match** (1 point): Candidate religion/denomination matches target preference.
  4. **Modesty Level Match** (1 point): Candidate modesty/dress level matches target preference.
  5. **Height Compatibility** (1 point): Candidate height (cm) falls within preferred min/max.
  6. **Lifestyle Habits** (1 point): Compatible smoking and drinking habits.
- **Asynchronous Execution**: Jobs are scheduled via `as_schedule_single_action()` when users register, update profiles, or when triggered by administrators.

### 2. Member Portal Experience
Rendered via `[matchmaker_member_portal]` or `[az_profile]`:
- **Tab 1: My Profile**: Displays the member's matrimonial criteria, metadata, and quick edit links.
- **Tab 2: Matches Hub (5-Step Interactive Review)**:
  - **Step 1: Discovery**: Summary card highlighting key compatible traits and a prompt to explore the profile.
  - **Step 2: Full Profile Review**: Comprehensive candidate review with photos, biography, background details, and action bar (**Accept Match** / **Decline Match**).
  - **Step 3: Waiting for Candidate**: Displayed when the member accepts, waiting for the candidate to review and respond.
  - **Step 4: Match Declined**: Friendly confirmation state allowing the member to await their next match.
  - **Step 5: Mutual Match & Contact Reveal**: Triggered upon mutual acceptance, unlocking contact information (Email, Phone, WhatsApp, Wali/Guardian details).

### 3. Admin Management Portal
- **Candidate Pool Browser**: Search, filter, and inspect member profiles, tier badges, and cycle quotas.
- **Manual Matchmaker Tool**: Advanced filtering tool allowing administrators to search candidate pool profiles with customized criteria (Gender, Age Min/Max, Location, Origin, Religion, Modesty), view ranked results by compatibility score, and create match pairs with 1 click.
- **Global Matches Queue**: Review pending match pairs with a side-by-side dual-profile comparison tool, approve matches (incrementing monthly cycle quota), or reject matches.
- **Settings & Dynamic Connector**:
  - **Environment Mode**: Toggle between **Live Mode** and **Test Mode**. In Test Mode, a secure reset tool allows clearing test matches, notifications, and logs without wiping candidate profiles.
  - **PMPro Tier Matrix**: Dynamically map Paid Memberships Pro levels to internal tiers (`monthly`, `one_on_one`, `event`, `free`).
  - **Quota & Expiry Management**: Configure maximum monthly matches and match review expiration windows.
  - **Dynamic Page Permalinks**: Configure permalinks for Dashboard, Questionnaire Wizard, and Login/Registration pages.
  - **Email Notification Templates**: Customizable templates for Match Dispatched, Reminder, and Mutual Match alerts.

### 4. Real-Time Heartbeat & Notification System
- Polling via WordPress **Heartbeat API** (every 15 seconds) checks for match status changes and pending actions.
- Displays responsive toast notifications and unread badge counters without full page reloads.
- Transactional HTML emails dispatched upon match generation, reminder deadlines, and mutual match reveal.

### 5. Structured Activity & Audit Logging
All critical plugin events are recorded in `wp_matchmaker_logs`:
- **Match Lifecycle**: Engine job dispatch, candidate evaluation, manual match searches, match generation, approvals, rejections, user accepts/declines, and expirations.
- **In-App & Email Notifications**: Toast deliveries and transactional HTML email dispatches with recipient and subject tracking.
- **Gate Debugger**: Interactive admin tool to test any two user IDs against the bi-directional matching rules to inspect gate decisions.

---

## Database Tables

The plugin installs and maintains custom tables with proper indexing:

| Table | Purpose |
| :--- | :--- |
| `wp_matchmaking_pool` | Canonical matchmaking criteria (gender, age, location, origin, religion, modesty, height, lifestyle, tier, active status). |
| `wp_matches` | Match pairs (`user_one_id = min(A,B)`, `user_two_id = max(A,B)`), status (`pending_review`, `approved`, `accepted`, `rejected`, `expired`), compatibility score, and responses. |
| `wp_matchmaker_notifications` | In-app notification queue and read receipts for Heartbeat API polling. |
| `wp_matchmaker_logs` | Structured audit trail tracking event types, details payload (JSON), reference IDs, and user associations. |

---

## Shortcodes Reference

| Shortcode | Description |
| :--- | :--- |
| `[matchmaker_member_portal]` | Main Member Dashboard (Canvas shell with Profile & 5-Step Matches tabs). Alias: `[az_profile]`. |
| `[matchmaking_form]` | Complete 37-field multi-step matchmaking registration & profile update questionnaire wizard. |
| `[matchmaking_field name="..."]` | Embeds individual matchmaking form fields (e.g. `[matchmaking_field name="user_location"]`). |
| `[logout_url]` | Outputs a dynamic, nonced WordPress logout URL with safe redirection to the login page. |

---

## Running Automated Tests

The plugin includes a test harness that runs without requiring a live WordPress server or external database:

```bash
# Run all unit, integration, and migration test suites:
php tests/run_tests.php
```

```text
========================================================
 Arab Zawaj Matchmaker — Automated Test Suite
========================================================

Suite: DBMigratorTest
  ✔ test_maybe_migrate_creates_tables_and_updates_option
  ✔ test_maybe_migrate_skips_when_already_installed

Suite: Matchmaker\Tests\Unit\SettingsAndPlanMappingTest
  ✔ test_default_tier_mapping_fallback
  ✔ test_custom_pmpro_tier_mapping
  ✔ test_dynamic_page_url_resolvers
  ✔ test_elementor_free_registration_form_id_matching

Suite: Matchmaker\Tests\Unit\QuotaAndExpiryTest
  ✔ test_default_and_custom_quota_settings
  ✔ test_default_and_custom_expiry_settings
  ✔ test_quota_blockade_on_limit_reached

Suite: Matchmaker\Tests\Unit\MatchingEngineTest
  ✔ test_dynamic_candidate_limits
  ✔ test_compute_flexible_score

Suite: Matchmaker\Tests\Unit\ModeAndResetTest
  ✔ test_default_environment_mode_is_live
  ✔ test_environment_mode_switch
  ✔ test_reset_test_matchmaking_data

Suite: Matchmaker\Tests\Unit\LoggingTest
  ✔ test_log_event_inserts_record
  ✔ test_get_logs_queries_with_type_filter
  ✔ test_get_logs_count

Suite: Matchmaker\Tests\Unit\ManualMatchmakerTest
  ✔ test_get_manual_match_candidates_filters_and_scores
  ✔ test_create_manual_match_pair

Suite: Matchmaker\Tests\Integration\EndToEndFlowTest
  ✔ test_complete_matchmaking_flow

========================================================
 Test Results Summary
========================================================
Total Tests Run : 20
Passed          : 20
Failed          : 0

🎉 ALL TESTS PASSED SUCCESSFULLY (0 failures, 0 errors)
```

---

## Requirements

- **WordPress**: 6.0+
- **PHP**: 8.1+ (strict typing, explicit type hints)
- **MySQL / MariaDB**: 5.7+ / 10.3+
- **Recommended Integrations**: Paid Memberships Pro, Elementor Pro, Action Scheduler

---

## License

Proprietary — Developed for Arab Zawaj.
