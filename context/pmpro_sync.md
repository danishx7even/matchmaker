# Feature Context: PMPro Sync & Dynamic Plan Connector

This document defines the architecture, options, and hooks for Paid Memberships Pro synchronization (`src/Core/PMProSync.php`).

---

## 1. Core Responsibilities & 3-Group Architecture
The system decouples base subscriptions from one-time add-on matchmaking services:
- **Group 1 (Base Free)**: `free` (Base subscription, upsell banners displayed).
- **Group 2 (Base Subscriptions)**: `monthly` (Auto-matching with quota), `event` (Events portal access).
- **Group 3 (One-Time Add-on Service)**: `one_on_one` (VIP high-touch matchmaking service flag `has_one_on_one = true`).

A member maintains a single base `user_type` (`free`, `monthly`, or `event`) alongside an independent `has_one_on_one` service flag. Purchasing a Group 2 subscription cancels lingering Group 1 (Free) levels, while Group 3 (1-on-1) is never cancelled by subscription changes and coexists with any base tier.

---

## 2. Dynamic Plan Connector Matrix
Admins configure mappings in **Matchmaking > Settings**. Stored in WordPress option `mm_pmpro_tier_mapping` as an associative array `[ level_id (int) => tier_slug (string) ]`.

### Default Fallback Mapping
If no custom mapping is configured, PMProSync gracefully falls back to:
- **Level 3** $\rightarrow$ `monthly` (Group 2 Subscription)
- **Level 4, 5** $\rightarrow$ `one_on_one` (Group 3 Add-on Service)
- **Level 6** $\rightarrow$ `event` (Group 2 Subscription)
- **Level 2 (or other)** $\rightarrow$ `free` (Group 1 Subscription)

---

## 3. Helper API
- `PMProSync::instance()->get_current_user_type(int $user_id): string` (Resolves base tier `monthly` > `event` > `free`)
- `PMProSync::instance()->has_active_one_on_one_service(int $user_id): bool` (Checks if Group 3 level active)
- `PMProSync::instance()->get_user_type_by_level_id(int $level_id): string`
- `PMProSync::instance()->get_levels_for_tier(string $tier): array`
- `PMProSync::instance()->is_tier_level(int $level_id, string $tier): bool`
- `PMProSync::instance()->get_primary_level_for_tier(string $tier, int $fallback = 0): int`

---

## 4. Hook Integration
- **`pmpro_after_all_membership_level_changes`** & **`pmpro_after_change_membership_level`**: Synchronizes both `user_type` and `has_one_on_one` in `wp_usermeta` and `wp_matchmaking_pool`.
- **`maybe_cancel_free_levels`**: Automatically cancels Group 1 (Free) levels upon purchase of Group 2 (`monthly` or `event`), preserving Group 3 (`one_on_one`).
- **Form Prerequisite**: Membership level upgrade does **not** trigger matching directly; members must complete their 2-step profile form to initiate matching.

