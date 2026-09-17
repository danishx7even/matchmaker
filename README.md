# Matchmaker Plugin

> **Enterprise-grade, high-touch matrimony matchmaking engine for WordPress.**

The Matchmaker Plugin is a sophisticated matchmaking platform built as a WordPress plugin. It decouples high-overhead matching calculations from user web requests using **Action Scheduler** background processing, custom indexed SQL tables, WordPress **Heartbeat API** real-time polling, and a comprehensive Admin Management Portal.

---

## Features at a Glance

| Feature | Description |
| :--- | :--- |
| 🔍 **Async Matching Engine** | Bi-directional hard gates + 6-point flexible scoring, running entirely in background workers (Action Scheduler). Never blocks HTTP responses. |
| 🖥️ **Admin Management Portal** | Full pool browser, match queue with approval/rejection, manual matchmaker tool, logs/debugger, and a plan connector for PMPro. |
| 👤 **Member Dashboard Portal** | Luxury 2-tab dashboard with a 5-state interactive match decision flow and mutual contact reveal. |
| 📋 **Questionnaire Wizard** | Multi-step profile + partner preferences form with "About Myself" and "About My Perfect Match" biographical fields, photo uploads (first photo mandatory, two optional), and multi-select preference fields. |
| 🎪 **Event Click Tracking** | Intercepts "Join Event" button clicks on event pages, records per-user click counts in a dedicated DB table, and exposes a full analytics sub-menu in the WP admin with CSV export. |
| 🔔 **Real-Time Notifications** | Heartbeat API 15-second polling for live unread badge counters and slide-out toast alerts on match approval. |
| 🔗 **PMPro Integration** | Dynamic membership plan connector matrix — no hardcoded level IDs. Tier transitions automatically trigger background matching jobs. |
| 📧 **Transactional Emails** | Configurable rich HTML approval email with WYSIWYG template editor and dynamic tags. |
| 🆓 **Free Registration** | Decoupled Elementor Pro form integration for free-tier user registration with auto-login. |
| ✅ **Automated Test Suite** | PHPUnit test suite with 168+ passing tests covering schema, quota enforcement, scoring, PMPro sync, event tracking, and end-to-end lifecycle. |

---

## Requirements

- WordPress 6.0+
- PHP 8.1+ (strict types enforced throughout)
- Paid Memberships Pro (PMPro) for membership tier management
- Elementor Pro (optional, for Free Registration form integration)
- Action Scheduler (bundled via composer)

---

## Installation

1. Upload the `matchkmaker/` directory to `wp-content/plugins/`.
2. Activate the plugin from **Plugins → Installed Plugins**.
3. The plugin automatically creates all required database tables on activation (`DBMigrator::run()`).
4. Navigate to **Matchmaking → Settings** to configure:
   - PMPro plan-to-tier connector matrix
   - Quota and expiry rules
   - Page routing (Dashboard, Form, Events, etc.)
   - Approval email template

---

## Architecture Overview

```
matchmaker/
├── matchmaker.php                     # Main plugin bootstrap & PSR-4 autoloader
├── AGENTS.md                          # 🌟 AI Agent master operational guide
├── BUILD_PLAN.md                      # 📝 Active task working file
├── HISTORY.md                         # 📜 Permanent chronological execution log
│
├── context/                           # Detailed domain & feature context references
│   ├── matching_engine.md             # Hard gates, 6-point scoring, SQL queries, batch chunking
│   ├── admin_portal.md                # Admin pool browser, matches queue, manual matchmaker, settings
│   ├── member_portal.md               # 5-state interactive match review flow, tab navigation, contact reveal
│   ├── pmpro_sync.md                  # Dynamic PMPro level connector matrix, tier sync
│   ├── form_handler.md                # Questionnaire wizard, hydration, file uploads, shortcodes
│   ├── notifications.md               # Heartbeat API 15s polling, toast alerts, unread badges, email templates
│   ├── free_registration.md           # Elementor Pro form decoupled validation, user creation, auto-login
│   ├── auth_and_routing.md            # Dynamic page URL resolvers, login/logout redirects, PMPro login styling
│   ├── design_system.md               # Color tokens (#CC723F), typography, status badges, responsive layout
│   ├── testing_guide.md               # PHPUnit test suite, bootstrap stubs, test runner instructions
│   └── event_click_tracking.md        # Join Event click recording, analytics sub-menu, CSV export
│
├── src/                               # PSR-4 Root Namespace: Matchmaker\
│   ├── Repository/
│   │   └── MatchRepository.php        # SINGLE DATABASE AUTHORITY — all $wpdb queries live here
│   ├── Service/
│   │   ├── MatchService.php           # Matching business rules, flexible scoring & responses
│   │   ├── ProfileService.php         # Profile data assembly, dynamic page URL resolvers
│   │   └── NotificationService.php    # Email dispatch, Heartbeat API & notifications
│   ├── Core/
│   │   ├── DBMigrator.php             # Database schema installer & dbDelta migration (v2.9.0)
│   │   ├── MatchingEngine.php         # Async matching calculation & Action Scheduler batch workers
│   │   ├── PMProSync.php              # Dynamic PMPro plan mapping & user_type synchronization
│   │   ├── FreeRegHandler.php         # Decoupled Elementor Free Registration handler
│   │   └── TestSeeder.php             # Test user & candidate pool mock data seeder
│   ├── Frontend/
│   │   ├── AuthController.php         # Role-based redirects, PMPro login cards & [logout_url]
│   │   ├── FieldGenerator.php         # Matchmaking form HTML input generator primitives
│   │   ├── FormController.php         # [matchmaking_form] & [matchmaking_field] shortcodes
│   │   └── PortalController.php       # [matchmaker_member_portal] / [az_profile] + AJAX handlers
│   ├── Admin/
│   │   └── AdminPortal.php            # Admin portal menus, pool browser, matches queue, settings & analytics
│   └── View/                          # Pure PHP presentation template views
│       ├── frontend/portal/           # Member portal canvas, profile tab, matches flow steps
│       └── admin/
│           ├── pool/                  # Pool browser list & user detail views
│           ├── matches/               # Match queue list & single match views
│           ├── settings/              # Settings page
│           ├── logs/                  # Log tabs & debugger
│           └── events/                # Event click analytics overview & detail views
│
├── assets/
│   ├── css/                           # admin-matchmaker.css, member-portal.css, matchmaking-form.css
│   └── js/                            # admin-matchmaker.js, member-portal.js, matchmaking-form.js, phone-mask.js
│
└── tests/                             # PHPUnit & integration test suite (168+ tests)
    ├── bootstrap.php                  # Full mock layer for WP Core, PMPro & Action Scheduler
    ├── run_tests.php                  # CLI test runner
    ├── DBMigratorTest.php             # Schema migration tests
    ├── Unit/                          # Settings, PMPro mapping, quotas, scoring, event tracking
    └── Integration/                   # End-to-end user lifecycle flow test
```

---

## Database Tables

| Table | Purpose |
| :--- | :--- |
| `wp_matchmaking_pool` | Indexed criteria for every active member (gender, age, religion, modesty, location, etc.) |
| `wp_matches` | Every match pair with canonical pair ordering (`user_one_id = min`, `user_two_id = max`) |
| `wp_matchmaker_notifications` | Persistent in-app notifications per user (match approvals, messages) |
| `wp_matchmaker_logs` | Structured match lifecycle and engine event logs |
| `wp_matchmaker_event_clicks` | Per-user "Join Event" button click counts with timestamps |

---

## Key Shortcodes

| Shortcode | Description |
| :--- | :--- |
| `[matchmaker_member_portal]` | Renders the full member dashboard (alias: `[az_profile]`) |
| `[matchmaking_form]` | Renders the complete 2-step profile + partner preferences questionnaire |
| `[matchmaking_field field="..."]` | Renders a single standalone form field anywhere on the site |
| `[logout_url]` | Dynamic logout URL for use in navigation links |

---

## Questionnaire Form Fields Summary

### Step 1 — About You
- Full name, date of birth, gender, height, nationality/country
- Religion, modesty level (gender-aware options), smoking/drinking habits
- Occupation, education level (labelled "Highest Education Level")
- Languages spoken, ethnic origin
- **About Myself** — free-text biographical description
- Profile photos (first photo mandatory; second and third are optional)

### Step 2 — Partner Preferences
- Preferred gender, age range, height range, country/location
- Preferred religion, modesty level (labelled "Lowest Education Level" for education)
- Preferred marital status (multi-select)
- Preferred smoking/drinking habits
- **About My Perfect Match** — free-text description of ideal partner

---

## Admin Portal Navigation

1. **Matchmaking → Candidate Pool** — Browse, filter, search all pool members; single user detail with manual matchmaker.
2. **Matchmaking → Matches Queue** — Review, approve, or reject pending match pairs; single match side-by-side view.
3. **Matchmaking → Settings** — PMPro plan connector, quota rules, page routing, Elementor form ID, approval email template.
4. **Matchmaking → Logs** — Match logs, notification logs, candidate gate debugger.
5. **Events → Join Click Analytics** — Per-event click counts overview and per-member breakdown with CSV export.

---

## Running the Test Suite

```bash
LD_LIBRARY_PATH="/home/dani/.config/Local/lightning-services/php-8.2.29+0/bin/linux/shared-libs:/home/dani/.config/Local/lightning-services/php-8.2.29+0/bin/linux/lib" \
"/home/dani/.config/Local/lightning-services/php-8.2.29+0/bin/linux/bin/php" tests/run_tests.php
```

Expected output: **168 tests, 0 failures, 0 errors.**

---

## Core Architectural Invariants

1. **No Hardcoded IDs or Values** — PMPro Level IDs, quotas, expiry days, page permalinks, and Elementor Form IDs must always be read from options or service methods.
2. **Single Database Authority** — All `$wpdb` queries live **only** in `src/Repository/MatchRepository.php`. Never bypass this.
3. **Canonical Match Pair Ordering** — `user_one_id = min(A, B)`, `user_two_id = max(A, B)`. `initiator_user_id` tracks who triggered the search.
4. **Async-Only Matching** — Matching calculations always run via Action Scheduler, never synchronously during HTTP requests.
5. **Strict PHP 8.1+** — Every file starts with `declare(strict_types=1);` with full parameter and return type hints.
6. **No CPT Bloat** — Matchmaking profiles are never stored as Custom Post Types. Criteria → `wp_matchmaking_pool`; metadata → `wp_usermeta`.

---

## Developer Notes

- Refer to `AGENTS.md` for the complete AI agent operational guide and task SOP.
- Each feature domain has a dedicated `context/*.md` deep-dive reference document.
- `HISTORY.md` contains the full chronological log of every task executed on this plugin.
