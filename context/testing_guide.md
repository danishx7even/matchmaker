# Feature Context: Automated Testing Suite & Verification Guide

This document describes the automated test architecture, test cases, and instructions for running the test suite (`tests/`).

---

## 1. Test Suite Architecture

```
tests/
├── bootstrap.php                        # Mock layer for WordPress Core, PMPro & Action Scheduler
├── run_tests.php                        # CLI test runner script
├── DBMigratorTest.php                   # Database schema and migration tests
├── Unit/
│   ├── SettingsAndPlanMappingTest.php   # PMPro plan matrix, page routing, and form ID matching
│   ├── QuotaAndExpiryTest.php           # Quota enforcement, limits, and expiry duration
│   ├── MatchingEngineTest.php           # 6-point flexible scoring and candidate limits
│   └── EventClickTrackingTest.php       # Join Event click tracking, admin menu, AJAX, CSV export
└── Integration/
    └── EndToEndFlowTest.php             # Full flow: Level sync -> Match -> Approve -> Mutual reveal
```

---

## 2. Test Execution Command

To execute the automated test suite in this environment:

```bash
LD_LIBRARY_PATH="/home/dani/.config/Local/lightning-services/php-8.2.29+0/bin/linux/shared-libs:/home/dani/.config/Local/lightning-services/php-8.2.29+0/bin/linux/lib" \
"/home/dani/.config/Local/lightning-services/php-8.2.29+0/bin/linux/bin/php" tests/run_tests.php
```

Expected: **168 tests, 0 failures, 0 errors.**

---

## 3. Test Coverage Summary

| Test File | Coverage Area |
| :--- | :--- |
| `DBMigratorTest.php` | Verifies creation of `wp_matchmaking_pool`, `wp_matches`, `wp_matchmaker_notifications`, and `wp_matchmaker_event_clicks` tables; schema version assertion (`2.9.0`). |
| `Unit/SettingsAndPlanMappingTest.php` | Dynamic PMPro fallback and custom matrix mapping; page routing options; Elementor form ID resolution. |
| `Unit/QuotaAndExpiryTest.php` | Quota limit gatekeeping and expiration calculations. |
| `Unit/MatchingEngineTest.php` | 0–6 flexible compatibility point calculations and candidate limit enforcement. |
| `Unit/EventClickTrackingTest.php` | Click upsert query correctness; increment-on-duplicate behavior; summary and detail query return types; admin menu page registration; AJAX hook registration; view template file existence; CSV output generation. |
| `Integration/EndToEndFlowTest.php` | Full subscriber lifecycle from tier upgrade to mutual match contact reveal. |

---

## 4. Bootstrap Mock Layer (`tests/bootstrap.php`)

The bootstrap file provides a complete WordPress stub environment so tests run without a live WP installation. Key stubs and mocks:

### Global Stubs
| Stub | Purpose |
| :--- | :--- |
| `Fakewpdb` class | `$wpdb` mock with `$prefix`, `$posts`, `$users`, `$usermeta`, `prepare()`, `insert()`, `get_var()`, `get_results()`, `get_row()`, `query()` |
| `add_action()` / `do_action()` | Hook registration stubs |
| `add_filter()` / `apply_filters()` | Filter registration stubs |
| `get_option()` / `update_option()` | Options API stubs |
| `wp_verify_nonce()` / `wp_create_nonce()` | Nonce stubs |
| `sanitize_text_field()` / `absint()` / `esc_html()` / `esc_attr()` | Sanitization stubs |
| `current_user_can()` / `get_current_user_id()` | Auth stubs |
| `wp_send_json_success()` / `wp_send_json_error()` | AJAX response stubs |
| `date_i18n()` | WP date formatting stub (returns `date($format, $timestamp ?: time())`) |
| `human_time_diff()` | WP human time diff stub (returns `'X minutes ago'`) |
| `get_avatar()` | WP avatar stub (returns empty string) |

### PMPro Stubs
| Stub | Purpose |
| :--- | :--- |
| `pmpro_getMembershipLevelForUser()` | Returns a `stdClass` with `id` and `name` |
| `pmpro_getLevel()` | Returns a `stdClass` level object |
| `pmpro_getAllLevels()` | Returns an array of level stubs |

### Action Scheduler Stubs
| Stub | Purpose |
| :--- | :--- |
| `as_schedule_single_action()` | Returns a mock action ID |
| `as_enqueue_async_action()` | Returns a mock action ID |

---

## 5. Adding New Tests

1. Create a new test file in `tests/Unit/` or `tests/Integration/`.
2. Extend `\PHPUnit\Framework\TestCase`.
3. Add a `require_once` line and the class name to the `$test_classes` array in `tests/run_tests.php`.
4. Run the test suite to verify zero regressions before submitting.
