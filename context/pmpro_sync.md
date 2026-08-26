# Feature Context: PMPro Sync & Tier Mapping

This document defines the architecture, rules, and constraints for Paid Memberships Pro synchronization (`includes/PMPro_Sync.php`).

## 1. Core Responsibilities
- Synchronizes PMPro membership levels with internal `user_type` metadata.
- Updates `user_type` in `wp_usermeta` and `wp_matchmaking_pool` whenever a user's level changes.

## 2. Membership Level Mapping
| PMPro Level ID | PMPro Level Name | Internal `user_type` | Auto-Matching Eligible |
| :--- | :--- | :--- | :---: |
| **Level 3** | Monthly Membership | `monthly` | ✅ Yes (on profile save/update) |
| **Level 4, 5** | 1-on-1 VIP Matchmaking | `one_on_one` | ❌ Admin Manual Only |
| **Level 6** | Event Participant | `event` | ❌ Admin Manual Only |
| **Level 2** (or default) | Free Registration | `free` | ❌ Admin Manual Only |

## 3. Operational Rules
- **`pmpro_after_change_membership_level` Hook**: Listens for level changes, maps level ID to `user_type`, updates usermeta, and syncs `wp_matchmaking_pool` if the user profile exists.
- **Form Prerequisite**: Membership level changes do **not** trigger matching automatically. Users are required to submit/update their profile form after level change to initiate matching.
