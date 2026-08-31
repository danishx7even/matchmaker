# Feature Context: PMPro Sync & Dynamic Plan Connector

This document defines the architecture, options, and hooks for Paid Memberships Pro synchronization (`src/Core/PMProSync.php`).

---

## 1. Core Responsibilities
- Maps PMPro membership levels to Matchmaker tiers (`monthly`, `one_on_one`, `event`, `free`).
- Synchronizes `user_type` in `wp_usermeta` and `wp_matchmaking_pool` upon checkout or administrative level changes.
- Eliminates hardcoded plan IDs via dynamic admin settings configuration.

---

## 2. Dynamic Plan Connector Matrix
Admins configure mappings in **Matchmaking > Settings**. Stored in WordPress option `mm_pmpro_tier_mapping` as an associative array `[ level_id (int) => tier_slug (string) ]`.

### Default Fallback Mapping
If no custom mapping is configured, PMProSync gracefully falls back to:
- **Level 3** $\rightarrow$ `monthly` (Bi-directional matching, monthly quota enforced).
- **Level 4, 5** $\rightarrow$ `one_on_one` (VIP high-touch matching).
- **Level 6** $\rightarrow$ `event` (Event participation only, upsell on matches tab).
- **Level 2 (or other)** $\rightarrow$ `free` (Free tier, upsell banner).

---

## 3. Helper API
- `PMProSync::instance()->get_user_type_by_level_id(int $level_id): string`
- `PMProSync::instance()->get_levels_for_tier(string $tier): array`
- `PMProSync::instance()->is_tier_level(int $level_id, string $tier): bool`
- `PMProSync::instance()->get_primary_level_for_tier(string $tier, int $fallback = 0): int`

---

## 4. Hook Integration
- **`pmpro_after_change_membership_level`**: Listens for level changes, normalizes `user_type`, updates usermeta, and updates `wp_matchmaking_pool` row if the member profile exists.
- **Form Prerequisite**: Membership level upgrade does **not** trigger matching directly; members must complete their 2-step profile form to initiate matching.
