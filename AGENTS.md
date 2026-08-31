# Matchmaker Plugin — AI Agent Master Operational & Architecture Guide

This document is the **single authoritative entry point** and complete system overview for any AI Agent working on the Matchmaker plugin. All development, refactoring, and task workflows must strictly follow this guide.

---

## 1. System Overview & Architecture Map

The **Matchmaker Plugin** is an enterprise-grade, high-touch matrimony matchmaking engine for WordPress. It decouples high-overhead matching calculations from user web requests using **Action Scheduler** background processing, custom indexed SQL tables, WordPress **Heartbeat API** real-time polling, and a comprehensive Admin Management Portal.

### Complete Directory & Component Map

```
matchmaker/
├── matchmaker.php                     # Main plugin bootstrap & PSR-4 autoloader
├── AGENTS.md                          # 🌟 SINGLE MASTER ENTRY POINT & OPERATIONAL GUIDE (this file)
├── BUILD_PLAN.md                      # 📝 ACTIVE TASK WORKING FILE (Set when working, clear when done)
├── HISTORY.md                         # 📜 PERMANENT CHRONOLOGICAL EXECUTION LOG
│
├── context/                           # 📚 Detailed Domain & Feature Context References
│   ├── matching_engine.md             # Hard gates, 6-point scoring, SQL queries, batch chunking
│   ├── admin_portal.md                # Admin pool browser, matches queue, manual matchmaker, settings
│   ├── member_portal.md               # 5-state interactive match review flow, tab navigation, contact reveal
│   ├── pmpro_sync.md                  # Dynamic PMPro level connector matrix, tier sync
│   ├── form_handler.md                # 37-field questionnaire wizard, hydration, file uploads, shortcodes
│   ├── notifications.md               # Heartbeat API 15s polling, toast alerts, unread badges, email templates
│   ├── free_registration.md           # Elementor Pro form decoupled validation, user creation, auto-login
│   ├── auth_and_routing.md            # Dynamic page URL resolvers, login/logout redirects, PMPro login styling
│   ├── design_system.md               # Color tokens (#CC723F), typography, status badges, responsive layout
│   └── testing_guide.md               # PHPUnit test suite, bootstrap stubs, test runner instructions
│
├── src/                               # PSR-4 Root Namespace: Matchmaker\
│   ├── Repository/
│   │   └── MatchRepository.php        # SINGLE DATABASE AUTHORITY (all $wpdb queries live here)
│   ├── Service/
│   │   ├── MatchService.php           # Matching business rules, flexible scoring & responses
│   │   ├── ProfileService.php         # Profile data assembly, dynamic page URL resolvers
│   │   └── NotificationService.php    # Email dispatch, Heartbeat API & notifications
│   ├── Core/
│   │   ├── DBMigrator.php             # Database schema installer & dbDelta migration (v2.3.0)
│   │   ├── MatchingEngine.php         # Async matching calculation & Action Scheduler batch workers
│   │   ├── PMProSync.php              # Dynamic PMPro plan mapping & user_type synchronization
│   │   ├── FreeRegHandler.php         # Decoupled Elementor Free Registration handler
│   │   └── TestSeeder.php             # Test user & candidate pool mock data seeder
│   ├── Frontend/
│   │   ├── AuthController.php         # Role-based redirects, PMPro login cards & [logout_url]
│   │   ├── FieldGenerator.php         # Matchmaking form HTML input generator primitives
│   │   ├── FormController.php         # [matchmaking_form] & [matchmaking_field] shortcodes
│   │   └── PortalController.php       # [matchmaker_member_portal] / [az_profile] shortcode
│   ├── Admin/
│   │   └── AdminPortal.php            # Admin portal menus, pool browser, matches queue, settings & debugger
│   ├── View/                          # Pure PHP presentation template views
│   │   └── frontend/
│   │       └── portal/
│   │           ├── portal.php         # Portal canvas wrapper & header
│   │           ├── tab-profile.php    # Member profile tab
│   │           └── tab-matches.php    # 5-step interactive matches flow
│   └── functions.php                  # Global helper wrappers (mm_enqueue_user_matching_job)
│
├── assets/
│   ├── css/                           # admin-matchmaker.css, member-portal.css, matchmaking-form.css
│   └── js/                            # admin-matchmaker.js, member-portal.js, matchmaking-form.js, phone-mask.js
│
└── tests/                             # Automated PHPUnit & Integration Test Suite
    ├── bootstrap.php                  # Full mock layer for WP Core, PMPro & Action Scheduler
    ├── run_tests.php                  # CLI test runner script
    ├── DBMigratorTest.php             # Schema migration tests
    ├── Unit/                          # Settings, PMPro mapping, Quotas, Scoring tests
    └── Integration/                   # End-to-end user lifecycle flow test
```

---

## 2. Mandatory Task Management SOP for AI Agents

Every agent must follow this exact 4-step protocol whenever executing work:

### Step 1: Initialize the Active Task
- Before writing any code, open [`BUILD_PLAN.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/BUILD_PLAN.md).
- Replace the `IDLE` state with the active task title, objectives, scope, and a step-by-step checklist.

### Step 2: Track Progress During Execution
- As files are edited or created, check off completed steps in `BUILD_PLAN.md`.

### Step 3: Verify with Automated Tests
- Run the automated test suite to confirm zero regressions:
  ```bash
  LD_LIBRARY_PATH="/home/dani/.config/Local/lightning-services/php-8.2.29+0/bin/linux/shared-libs:/home/dani/.config/Local/lightning-services/php-8.2.29+0/bin/linux/lib" \
  "/home/dani/.config/Local/lightning-services/php-8.2.29+0/bin/linux/bin/php" tests/run_tests.php
  ```

### Step 4: Finalize & Clear Active Task
1. **Log Completed Work**: Append a detailed task entry to [`HISTORY.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/HISTORY.md) detailing the objective, files modified, and verification results.
2. **Clear Active Task File**: Reset [`BUILD_PLAN.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/BUILD_PLAN.md) back to its clean `IDLE` state.

---

## 3. Core Architectural Rules & Invariants

1. **Strict File Boundaries**:
   - Work strictly within `wp-content/plugins/matchmaker/` (or `matchkmaker/`). Never edit root WordPress files or theme `functions.php`.
2. **Zero Hardcoded / Static Values**:
   - **Never hardcode PMPro Level IDs** (use `PMProSync::instance()->get_tier_mapping()` or `get_primary_level_for_tier()`).
   - **Never hardcode Quotas / Expirations** (use `MatchRepository::get_max_cycle_matches()` and `get_match_expiry_days()`).
   - **Never hardcode Page Permalinks** (use `ProfileService::instance()->get_dashboard_url()`, `get_form_url()`, etc.).
   - **Never hardcode Elementor Form IDs** (use `FreeRegHandler::matches_form_id()`).
3. **Canonical Match Pair Ordering**:
   - In `wp_matches`, always enforce:
     $$\text{user\_one\_id} = \min(\text{ID}_A, \text{ID}_B)$$
     $$\text{user\_two\_id} = \max(\text{ID}_A, \text{ID}_B)$$
   - Search intent ownership belongs to `initiator_user_id`.
4. **Billing Quota Gating**:
   - Generating matches creates `pending_review` rows that do **not** deduct monthly quota.
   - Admin approval transitions status to `approved` and increments `cycle_matches_count` **only** if count is $< \text{max\_cycle\_matches}$ (default: 10).
5. **No Direct CPT Bloat**:
   - Do not create Custom Post Types for matchmaking profiles. All criteria belong in `wp_matchmaking_pool` (indexed SQL criteria) and `wp_usermeta` (presentation metadata).
6. **Asynchronous Background Matching**:
   - Matching calculations run in background workers via **Action Scheduler** (`as_schedule_single_action()`), never blocking HTTP responses.
7. **Strict Typing & PHP 8.1+ Standards**:
   - Every PHP file must begin with `declare(strict_types=1);` and use explicit parameter and return type hints.

---

## 4. Context References Index (Deep-Dive Guides)

When working on a specific feature, consult its dedicated context document:

| Domain / Feature | Context Reference File | Description |
| :--- | :--- | :--- |
| **Matching Engine** | [`context/matching_engine.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/matching_engine.md) | Bi-directional hard gates, 6-point flexible scoring, batch chunking & candidate limits. |
| **Admin Portal** | [`context/admin_portal.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/admin_portal.md) | Pool browser, matches queue, manual matchmaker, settings UI & plan matrix connector. |
| **Member Portal** | [`context/member_portal.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/member_portal.md) | 2-tab dashboard canvas, 5-state interactive match flow & mutual match contact reveal. |
| **PMPro Sync** | [`context/pmpro_sync.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/pmpro_sync.md) | Dynamic membership plan matrix, level-to-tier mappings & hook integrations. |
| **Form Handler** | [`context/form_handler.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/form_handler.md) | 37-field questionnaire wizard, 2-step navigation, file uploads & standalone shortcodes. |
| **Notifications** | [`context/notifications.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/notifications.md) | 15s Heartbeat API polling, unread counters, toast popups & transactional email templates. |
| **Free Registration** | [`context/free_registration.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/free_registration.md) | Elementor Pro form decoupled validation, user account creation & auto-login. |
| **Auth & Routing** | [`context/auth_and_routing.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/auth_and_routing.md) | Dynamic page URL resolvers, role-based redirects & PMPro login card styling. |
| **Design System** | [`context/design_system.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/design_system.md) | Official brand color tokens (`#CC723F`), typography, status badges & mobile breakpoints. |
| **Testing Guide** | [`context/testing_guide.md`](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/testing_guide.md) | PHPUnit test suite structure, mock layers & test execution commands. |
