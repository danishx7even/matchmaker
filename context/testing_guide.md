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
│   └── MatchingEngineTest.php           # 6-point flexible scoring and candidate limits
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

---

## 3. Test Coverage Summary
- **Schema Migration**: Verifies creation of `wp_matchmaking_pool`, `wp_matches`, and `wp_matchmaker_notifications` tables and version checking.
- **Dynamic Plan Mapping**: Verifies PMPro fallback and custom matrix mapping.
- **Dynamic Quota & Expiry**: Verifies quota limit gatekeeping and expiration calculations.
- **Scoring Engine**: Verifies 0–6 flexible compatibility point calculations.
- **End-to-End Flow**: Verifies full subscriber lifecycle from tier upgrade to mutual match contact reveal.
