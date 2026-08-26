# Matchmaker Plugin — Master Architectural & Execution Plan

## 1. Executive Summary & Plugin Architecture
The **Matchmaker** plugin (`matchmaker/`) is an enterprise-grade matchmaking engine built for WordPress and Paid Memberships Pro (PMPro). It implements a high-performance, indexed SQL architecture for profile filtering, scoring, asynchronous matching, frontend form rendering, and administrative review workflows.

---

## 2. Database Schema Specification

### 2.1 Table: `wp_matchmaking_pool`
Stores the 11 core matching attributes (5 mandatory + 6 flexible) and their reciprocal partner preferences.

```sql
CREATE TABLE `wp_matchmaking_pool` (
    `user_id`              BIGINT UNSIGNED NOT NULL,
    
    -- Mandatory Matching Fields (Bi-directional gate)
    `gender`               ENUM('male','female') NOT NULL,
    `pref_gender`          ENUM('male','female') NOT NULL,
    `birth_date`           DATE NOT NULL,
    `preferred_age_min`    TINYINT UNSIGNED NOT NULL,
    `preferred_age_max`    TINYINT UNSIGNED NOT NULL,
    `location`             VARCHAR(191) NOT NULL,
    `pref_location`        VARCHAR(255) NOT NULL,
    `religion`             VARCHAR(100) NOT NULL,
    `pref_religion`        VARCHAR(255) NOT NULL,
    `modesty`              VARCHAR(50)  NOT NULL,
    `pref_modesty`         VARCHAR(255) NOT NULL,

    -- Flexible Matching Fields (Scored 0–6)
    `origin`               VARCHAR(100) DEFAULT NULL,
    `pref_origin`          VARCHAR(255) DEFAULT NULL,
    `languages`            VARCHAR(255) DEFAULT NULL,
    `pref_languages`       VARCHAR(255) DEFAULT NULL,
    `height_cm`            SMALLINT UNSIGNED DEFAULT NULL,
    `preferred_height_min` SMALLINT UNSIGNED DEFAULT NULL,
    `preferred_height_max` SMALLINT UNSIGNED DEFAULT NULL,
    `job`                  VARCHAR(150) DEFAULT NULL,
    `smoking`              VARCHAR(50)  DEFAULT NULL,
    `pref_smoking`         VARCHAR(100) DEFAULT NULL,
    `drinking`             VARCHAR(50)  DEFAULT NULL,
    `pref_drinking`        VARCHAR(100) DEFAULT NULL,

    -- System & Status
    `user_type`            ENUM('monthly','one_on_one','free','event') NOT NULL,
    `is_active`            TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY  (`user_id`),
    KEY `idx_match_core` (`is_active`, `gender`, `pref_gender`),
    KEY `idx_birth_date` (`birth_date`),
    KEY `idx_location`   (`location`),
    KEY `idx_religion`   (`religion`),
    KEY `idx_user_type`  (`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
2.2 Table: wp_matchesTracks candidate pair relationships, directionality, approval lifecycle, and contact privacy.SQLCREATE TABLE `wp_matches` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_one_id`          BIGINT UNSIGNED NOT NULL,  -- LEAST(id1, id2)
    `user_two_id`          BIGINT UNSIGNED NOT NULL,  -- GREATEST(id1, id2)
    `initiator_user_id`    BIGINT UNSIGNED NOT NULL,  -- Quota owner

    `status`               ENUM('pending_review','approved','admin_rejected','matched','rejected') NOT NULL DEFAULT 'pending_review',
    `user_one_response`    ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    `user_two_response`    ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    
    `match_source`         ENUM('auto','manual') NOT NULL DEFAULT 'auto',
    `score`                SMALLINT UNSIGNED DEFAULT NULL,
    `contact_revealed`     TINYINT(1) NOT NULL DEFAULT 0,

    `approved_by`          BIGINT UNSIGNED DEFAULT NULL,
    `approved_at`          DATETIME DEFAULT NULL,
    `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY  (`id`),
    UNIQUE KEY `uniq_pair` (`user_one_id`, `user_two_id`),
    KEY `idx_user_one`     (`user_one_id`),
    KEY `idx_user_two`     (`user_two_id`),
    KEY `idx_initiator`    (`initiator_user_id`),
    KEY `idx_status`       (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
3. Modular Feature Implementation BreakdownModule 1: Plugin Bootstrap & Entrypoint (matchmaker.php)Initializes the Matchmaker_Plugin core orchestrator.Defines constants: MATCHMAKER_VERSION, MATCHMAKER_PATH, MATCHMAKER_URL.Autoloads classes from includes/.Module 2: Schema Migrator (includes/class-db-migrator.php)Handles database creation and delta upgrades via dbDelta().Verifies and updates mm_matchmaking_db_version.Module 3: PMPro Synchronization (includes/class-pmpro-sync.php)Hooks pmpro_after_change_membership_level.Maps Level 2 $\rightarrow$ free, Level 3 $\rightarrow$ monthly, Level 4/5 $\rightarrow$ one_on_one, Level 6 $\rightarrow$ event.Updates user_type across wp_usermeta and wp_matchmaking_pool.Promotes existing profiles upgrading to monthly directly into the matching queue.Module 4: Standalone Field Generator (includes/class-field-generator.php)Encapsulates options dictionaries (countries, religions, origins, heights, marital statuses).Houses render_field($name, $values) for standardized inputs (text, select, multiselect, date, radio, upload, range).Implements multiselect label joining (comma-separated list output instead of tally counts).Module 5: Form Handler & Shortcodes (includes/class-form-handler.php)Registers [matchmaking_form redirect="/path/"] and [matchmaking_field name="..."].Manages 2-step navigation, file preview readers, and submission animations.Enqueues assets: assets/css/matchmaking-form.css and assets/js/matchmaking-form.js.AJAX Handler mmf_submit_form:Parses 37 fields.Sanitizes cm heights and comma lists.Ingests matching columns into wp_matchmaking_pool via REPLACE INTO.Ingests presentation fields into wp_usermeta.Ingests file uploads into WP Media Library via media_handle_upload().Dispatches Action Scheduler background matching job.Module 6: Free Registration Decoupled Handler (includes/class-free-reg-handler.php)Hooks Elementor Pro Form elementor_pro/forms/validation to render inline errors without 500 fatal clashes.Hooks elementor_pro/forms/new_record to create user, set subscriber role, assign PMPro Level 2, and log user in via wp_signon().Enqueues assets/js/phone-mask.js for international phone formatting.Module 7: Matching Engine & Scheduler (includes/class-matching-engine.php)Triggers:Profile creation (form_submit).Profile update (form_update).Membership upgrade (tier_upgrade).Weekly interval check (weekly_recurring).SQL Execution:Runs bi-directional evaluation across gender, age range, location (FIND_IN_SET), religion (FIND_IN_SET), and modesty (FIND_IN_SET).Computes 6-point flexible score.Excludes existing pairs in wp_matches.Inserts top 10 candidates as pending_review.Module 8: Authentication, Redirects & Access Control (includes/class-auth-redirects.php)Redirects wp-login.php GET requests to PMPro login.Filters login_url and login_redirect (Admins $\rightarrow$ Dashboard, Subscribers $\rightarrow$ Account page).Filters pmpro_confirmation_url $\rightarrow$ /personal-matchmaking-questionnaire/.Registers shortcode [logout_url redirect="..."].Suppresses WordPress admin bar for subscribers.Applies clean styling and client-side subtitle/sign-up enhancements to PMPro login card.Module 9: Admin Management Portal (includes/class-admin-portal.php)Registers Top-Level Menu Matchmaking (matchmaking-pool).Pool Browser: Search by name/email/location, filter by tier and gender, view photo thumbnails and match counters.User Detail View: Full profile readout, preferences card, photo gallery.Match Approval & Quota Guard:Approve CTA checks cycle_matches_count < 10.Rejection CTA sets status = 'admin_rejected'.Manual "Run Auto-Match Scoring" button to execute algorithm on demand.Enqueues assets/css/admin-matchmaker.css.4. Execution Workflow[User Registration (Level 2/3/4)]
          │
          ▼
[Redirect to Questionnaire Form] ──> Submits 37 fields via AJAX
          │
          ├──> 1. Ingest 11 Traits to `wp_matchmaking_pool`
          ├──> 2. Ingest 26 Attributes to `wp_usermeta`
          └──> 3. Enqueue Action Scheduler Task (`mm_run_async_matching_job`)
                      │
                      ▼
          [Asynchronous Matching Engine]
                      │
                      ├──> Bi-directional Hard Gates (Gender, Age, Location, Religion, Modesty)
                      ├──> Flexible 6-pt Scoring
                      └──> Create `pending_review` rows in `wp_matches`
                                  │
                                  ▼
                      [Admin Review Portal]
                                  │
                      ┌───────────┴───────────┐
                      ▼                       ▼
               [Admin Rejects]         [Admin Approves]
                      │                       │
               status = admin_rejected        ├──> Verify Quota < 10
                                              ├──> Increment Quota (cycle_matches_count)
                                              └──> status = approved
                                                          │
                                                          ▼
                                              [User Dashboard Notification]
                                                          │
                                              ┌───────────┴───────────┐
                                              ▼                       ▼
                                       [Mutual Accept]         [Either Rejects]
                                              │                       │
                                       status = matched        status = rejected
                                       contact_revealed = 1