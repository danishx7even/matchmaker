# Matchmaker Plugin — AI Agent Operational & Technical Guide

## 1. Environment & Workspace Constraints
- **Target Plugin Directory**: `wp-content/plugins/matchmaker/`
- **Scope Restriction**: Under no circumstances should files outside `matchmaker/` be created, edited, or modified.
- **PHP Version Target**: PHP 8.1+ with strict typing (`declare(strict_types=1);`) across all files.
- **Dependencies**: WordPress Core, Paid Memberships Pro (PMPro), Action Scheduler (Standalone or Bundled).

---

## 2. Architecture & File Structure Mapping

All development within `matchmaker/` must adhere to the following modular architecture:

matchmaker/
├── matchmaker.php                 # Main plugin bootstrap & singleton entrypoint
├── AGENTS.md                      # Agent technical guidelines (this file)
├── PLAN.md                        # Master implementation and feature plan
├── BUILD_PLAN.md                  # Current active feature build plan & task tracking
├── HISTORY.md                     # Chronological execution log of all implemented features
├── Design.md                      # Official design system tokens, typography & status lifecycle
├── PROJECT_BUILD.md               # Master architecture overview & context index
├── context/                       # Feature-specific context files
├── includes/                      # Core PHP classes
└── assets/                        # CSS and JS assets


---

## 3. Core Operational Rules for AI Agents

1. **Strict File Boundaries**:
   - Work strictly within `matchmaker/`.
   - Never write snippet hacks into theme `functions.php` or root files.
2. **Canonical Pair Enforcement**:
   - In `wp_matches`, always guarantee:
     $$\text{user\_one\_id} = \min(\text{ID}_A, \text{ID}_B)$$
     $$\text{user\_two\_id} = \max(\text{ID}_A, \text{ID}_B)$$
   - Quota and intent ownership must always be assigned to `initiator_user_id`.
3. **No Direct CPT Bloat**:
   - Never register a Custom Post Type for matchmaking profiles. All match data belongs in `wp_matchmaking_pool` (indexed matching criteria) and `wp_usermeta` (presentation metadata).
4. **Decouple Frontend Validation from Actions**:
   - For Elementor forms, keep `elementor_pro/forms/validation` separated from `elementor_pro/forms/new_record`.
   - For native forms (`[matchmaking_form]`), handle AJAX processing with nonces and JSON responses.
5. **No Full-Table Scan on HTTP Requests**:
   - Profile matching logic must always run asynchronously via **Action Scheduler** (`as_enqueue_async_action()`), never blocking the AJAX form submission response.
6. **Billing Quota Gating (10 Matches / Cycle)**:
   - Match generation creates `pending_review` rows that do **not** deduct quota.
   - Admin approval transitions status to `approved` and increments `cycle_matches_count` **only** if count is $< 10$.

---

## 4. Hook & Filter Reference Map

| Component | Target Hook / Filter | Priority | Purpose |
| :--- | :--- | :---: | :--- |
| **Schema Migration** | `admin_init` | 10 | Runs `dbDelta()` if `mm_matchmaking_db_version` is outdated. |
| **Tier Sync** | `pmpro_after_change_membership_level` | 10 | Updates `user_type` in meta and pool; queues match job on level 3. |
| **Free Reg Validation** | `elementor_pro/forms/validation` | 10 | Validates email, password length, and phone format inline. |
| **Free Reg Creation** | `elementor_pro/forms/new_record` | 10 | Creates user, assigns PMPro level 2, logs user in safely. |
| **Form AJAX** | `wp_ajax_mmf_submit_form` | 10 | Ingests 37 fields to pool + usermeta, handles media library uploads. |
| **Async Matching** | `mm_run_async_matching_job` | 10 | Background worker executing bi-directional SQL match algorithm. |
| **Weekly Queue** | `mm_daily_check_weekly_matching_queue` | 10 | Action Scheduler daily recurring task checking idle members ($\ge$ 7 days). |
| **Login Redirect** | `login_redirect` | 10 | Routes admins to `/wp-admin/` and members to `/membership-account/`. |
| **Reg Confirmation** | `pmpro_confirmation_url` | 10 | Redirects completed checkouts to `/personal-matchmaking-questionnaire/`. |
| **Admin Menu** | `admin_menu` | 30 | Registers Top-Level **Matchmaking** management portal. |

