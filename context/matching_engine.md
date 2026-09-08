# Feature Context: Matching Engine

This document is the authoritative technical reference for the **Matchmaking Engine** (`src/Core/MatchingEngine.php`, `src/Service/MatchService.php`, and `src/Repository/MatchRepository.php`). It provides a comprehensive breakdown of the bi-directional hard gates, the 6-point flexible scoring algorithm, batch processing constraints, and comparison with manual matchmaking.

---

## 1. System Overview & Architecture

The Matchmaker matching engine is an asynchronous, high-touch matrimony pairing system designed to operate at scale without impacting front-end response times.

```
                  ┌────────────────────────────────────────────────────────┐
                  │                    TRIGGER EVENT                       │
                  │ (Form Submit / Tier Upgrade / Weekly Cron / Admin Run) │
                  └───────────────────────────┬────────────────────────────┘
                                              │
                                              ▼
                  ┌────────────────────────────────────────────────────────┐
                  │             Action Scheduler Background Job            │
                  │               `mm_run_async_matching_job`              │
                  └───────────────────────────┬────────────────────────────┘
                                              │
                                              ▼
                  ┌────────────────────────────────────────────────────────┐
                  │        Phase 1: Bi-Directional SQL Hard Gates          │
                  │   (Gender, Age, Country, State/City, Religion, Modesty) │
                  └───────────────────────────┬────────────────────────────┘
                                              │
                                              ▼
                  ┌────────────────────────────────────────────────────────┐
                  │       Phase 2: PHP Flexible Scoring (0–6 Points)       │
                  │    (Origin, Languages, Height, Profession, Smoking,    │
                  │                        Drinking)                       │
                  └───────────────────────────┬────────────────────────────┘
                                              │
                                              ▼
                  ┌────────────────────────────────────────────────────────┐
                  │          Phase 3: Top N Selection & Insertion          │
                  │ (Rank by score DESC, take top `max_candidates`, insert │
                  │            into `wp_matches` as `pending_review`)      │
                  └────────────────────────────────────────────────────────┘
```

---

## 2. Trigger Scenarios & Execution Modes

| Trigger Name | Mechanism | Eligibility / Context |
| :--- | :--- | :--- |
| **`form_submit` / `form_update`** | Action Scheduler async job | Fired when a member submits or updates their questionnaire wizard via `FormController`. |
| **`tier_upgrade`** | Action Scheduler async job | Fired by `PMProSync` when a user upgrades from Free/Event to a paid subscription (`monthly` or `one_on_one`). |
| **`idle_recurring`** | Daily Action Scheduler cron | Evaluates paid subscribers whose last matching run is older than `mm_auto_match_recurrence_days` (default: 7 days). Chunked in batches of 100 users. |
| **`admin_manual_trigger`** | Synchronous or async | Fired from Admin Portal (`AdminPortal::render_user_detail_view`) for any specific user profile. |

---

## 3. Phase 1: Bi-Directional Hard Gates (SQL Level)

The SQL query executed in `MatchingEngine::query_candidates()` enforces that **both Member A and Candidate B satisfy each other's criteria simultaneously**. If either party fails any hard gate, no match pair is created.

### Bi-Directional Gate Rules:

1. **Active & Not Self**:
   - `c.user_id != target_user_id`
   - `c.is_active = 1` (excludes deactivated/hidden profiles).

2. **Gender Compatibility**:
   - Candidate's `gender` matches Member A's `pref_gender` (or `'any'`).
   - Candidate's `pref_gender` matches Member A's `gender` (or `'any'`).

3. **Age Range Compatibility**:
   - Member A's calculated age is within Candidate B's `[preferred_age_min, preferred_age_max]`.
   - Candidate B's calculated age is within Member A's `[preferred_age_min, preferred_age_max]`.

4. **Country Compatibility**:
   - Candidate B's `country` matches Member A's `pref_country` (supporting `'Any Country'`, `'Any'`, comma-separated lists, and legacy location fallback).
   - Member A's `country` matches Candidate B's `pref_country` (supporting `'Any Country'`, `'Any'`, comma-separated lists, and legacy location fallback).

5. **State & Region Compatibility**:
   - If Member A specifies a specific `pref_state` (not `'Any State'`), Candidate B's `state` must match.
   - If Candidate B specifies a specific `pref_state` (not `'Any State'`), Member A's `state` must match.

6. **City Compatibility**:
   - If Member A specifies a specific `pref_city` (not `'Any City'`), Candidate B's `city` must match.
   - If Candidate B specifies a specific `pref_city` (not `'Any City'`), Member A's `city` must match.

7. **Religious Alignment**:
   - Candidate B's `religion` matches Member A's `pref_religion` (or `'Any'` / `'No Preference'`).
   - Member A's `religion` matches Candidate B's `pref_religion` (or `'Any'` / `'No Preference'`).

8. **Modesty Level Alignment**:
   - Candidate B's `modesty` matches Member A's `pref_modesty` (or `'Any'` / `'No Preference'`).
   - Member A's `modesty` matches Candidate B's `pref_modesty` (or `'Any'` / `'No Preference'`).

9. **Existing Pair & Mutual Match Exclusions**:
   - `NOT EXISTS`: Pair does not already exist in `wp_matches` in any status (`user_one_id = min(A, B)`, `user_two_id = max(A, B)`).
   - `NOT EXISTS`: Candidate does not already have an active `matched` (mutually accepted) record in the current calendar month.

---

## 4. Phase 2: Flexible Scoring Algorithm (0–6 Points)

For all candidates passing Phase 1 hard gates, `MatchingEngine::compute_flexible_score()` calculates a dynamic compatibility score from **0 to 6**:

| Dimension | Points | Evaluation Rule |
| :--- | :---: | :--- |
| **1. Origin / Ethnicity** | **+1 pt** | Mutual match: User A's `origin` is in Candidate B's `pref_origin` **AND** Candidate B's `origin` is in User A's `pref_origin`. |
| **2. Languages Spoken** | **+1 pt** | At least one shared spoken language in comma-delimited `languages` lists. |
| **3. Height Compatibility** | **+1 pt** | Candidate height $\in [\text{User preferred\_height\_min}, \text{User preferred\_height\_max}]$ **AND** User height $\in [\text{Cand preferred\_height\_min}, \text{Cand preferred\_height\_max}]$. |
| **4. Profession / Employment** | **+1 pt** | Candidate has a non-empty `job` field provided. |
| **5. Smoking Alignment** | **+1 pt** | Candidate's `smoking` value is listed in User A's `pref_smoking`. |
| **6. Drinking Alignment** | **+1 pt** | Candidate's `drinking` value is listed in User A's `pref_drinking`. |

**Total Range**: $0 \le \text{Score} \le 6$

---

## 5. Candidate Limits, Quota Deductions & Match Creation

1. **Top Candidate Selection**:
   - Candidates are sorted in PHP: `ORDER BY score DESC`.
   - The top $N$ candidates are sliced up to `mm_max_candidates_per_run` (default: 10).

2. **Match Record Insertion**:
   - Each pair is inserted into `wp_matches` via `MatchRepository::create_match()` with:
     - `user_one_id = min(user_id, candidate_id)`
     - `user_two_id = max(user_id, candidate_id)`
     - `initiator_user_id = user_id`
     - `status = 'pending_review'`
     - `source = 'auto'`
     - `compatibility_score = score`

3. **Billing Quota Protection**:
   - Creating `pending_review` rows **does NOT deduct** the monthly match quota.
   - Quota (`cycle_matches_count`) is only incremented when an **Admin approves** the match in `AdminPortal` or `MatchService::process_admin_approve()`.

---

## 6. Manual Matchmaking vs Automated Matching Engine

| Feature | Automated Matching Engine (`MatchingEngine.php`) | Manual Matchmaking Tool (`manual-match.php`) |
| :--- | :--- | :--- |
| **Execution** | Asynchronous background worker via Action Scheduler. | Synchronous, real-time admin search interface. |
| **Hard Gates** | Strict bi-directional SQL gate. | Admin-controllable interactive filter form (`f_country`, `f_state`, `f_city`, `f_citizenship`, `f_gender`, `f_age_min`, `f_age_max`, etc.). |
| **Location Filters** | Structured Country, State, City bi-directional gate with wildcards. | Dynamic cascading Country $\to$ State $\to$ City dropdowns from hierarchy dataset + free text keyword search. |
| **Citizenship** | Preserved in metadata (`usermeta`). | Dedicated filter dropdown (`f_citizenship`) querying `user_citizenship` meta. |
| **Pair Creation** | Automatic top $N$ insertion as `source = 'auto'`. | Admin clicks `+ Create Match Pair` for chosen candidate as `source = 'manual'`. |
| **Scoring** | 0–6 flexible score calculated for top candidate ranking. | Live 0–6 score badge displayed in results table for every candidate. |

---

## 7. Performance & Indexing Map

The `wp_matchmaking_pool` table includes dedicated compound indexes to ensure fast query execution even with 100,000+ member profiles:

- `KEY idx_match_core (is_active, gender, pref_gender)`
- `KEY idx_active_gender_type (is_active, gender, user_type)`
- `KEY idx_birth_date (birth_date)`
- `KEY idx_country_city (country, city)`
- `KEY idx_country (country)`
- `KEY idx_religion (religion)`
- `KEY idx_user_type (user_type)`
