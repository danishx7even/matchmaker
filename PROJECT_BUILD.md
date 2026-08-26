# Matchmaker Plugin Build & Architecture

This document summarizes the architecture, components, and current state of the Matchmaker plugin. It serves as a master entry point linking to detailed feature context files in the `context/` directory.

## Project Structure

The plugin is structured using a modern, class-based, object-oriented approach in PHP 8.1+.

```
matchmaker/
├── matchmaker.php                 # Main plugin bootstrap & singleton entrypoint
├── AGENTS.md                      # AI Agent operational and technical guidelines
├── PLAN.md                        # Master feature roadmap & implementation plan
├── BUILD_PLAN.md                  # Current active feature build plan & task tracking
├── HISTORY.md                     # Chronological execution log of all implemented features
├── Design.md                      # Official design system tokens, typography & status lifecycle
├── PROJECT_BUILD.md               # Master architecture overview & context index (this file)
├── context/                       # Feature-specific context, rules, and plans
│   ├── matching_engine.md         # Matching engine, scoring, hard gates, quota & triggers
│   ├── form_handler.md            # 2-step form, hydration, photos, standalone field shortcodes
│   ├── admin_portal.md            # Candidate pool, match queue, info-only tier rules & settings
│   ├── manual_match.md            # Manual matchmaker, advanced filters & manual pair creation
│   ├── pmpro_sync.md              # Membership level mapping & usermeta/pool sync
│   ├── auth_redirects.md          # Login/registration URL overrides, redirects & PMPro login design
│   └── free_registration.md       # Elementor decoupled validation, free account creation & auto-login
├── includes/                      # Core PHP classes
│   ├── Admin_Portal.php           # Admin pool browser & match queue UI
│   ├── Auth_Redirects.php         # Login, registration, role-based redirects & PMPro login page styling
│   ├── DB_Migrator.php            # Schema installer (wp_matchmaking_pool, wp_matches)
│   ├── Field_Generator.php        # Standalone field component builder
│   ├── Form_Handler.php           # Shortcodes & AJAX handler for frontend form
│   ├── Free_Reg_Handler.php       # Elementor decoupled validation & free registration
│   ├── Matching_Engine.php        # Bi-directional SQL queries & AS background jobs
│   ├── PMPro_Sync.php             # PMPro membership level <-> user_type synchronization
│   └── Test_Seeder.php            # Test user & profile seeder (10 test users across all tiers)
└── assets/                        # CSS and JS assets
    ├── css/
    │   ├── admin-matchmaker.css   # Admin portal styles
    │   └── matchmaking-form.css   # Frontend form styling
    └── js/
        ├── admin-matchmaker.js    # Admin portal scripts
        └── matchmaking-form.js    # Frontend form logic (AJAX, steps, previews)
```

## Feature Context & Rules Index

For detailed architectural rules, business logic, and strict constraints regarding specific plugin features, consult the corresponding context document:

1. **[Matching Engine Context](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/matching_engine.md)**
   - 4 Trigger conditions (Profile Save/Update for Monthly, Recurring Idle Cron, Admin Manual).
   - Bi-directional SQL hard gates & 6-point flexible scoring system.
   - 10 approved matches/cycle quota enforcement.
   - Non-approvable Free/Event candidate match rules.

2. **[Form & Data Collection Context](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/form_handler.md)**
   - 2-step form layout (`[matchmaking_form]`) and standalone field shortcode (`[matchmaking_field]`).
   - Profile data hydration for returning members.
   - Up to 3 photo uploads via WP Media Library.
   - Conditional asset enqueueing (`matchmaking-form.css`, `matchmaking-form.js`).

3. **[Admin Portal Context](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/admin_portal.md)**
   - Candidate pool browser search and filtering.
   - Global **Matches** sub-menu page (`matchmaking-matches`) with minimalistic list view and search by user/candidate name/email and match status filter.
   - **Single Match View** (`view_match=ID`): Displays side-by-side dual candidate profiles, photos, search preferences, and directional user responses.
   - Synchronous real-time manual scoring trigger.
   - Matchmaking Admin Settings Page sub-menu.
   - **Info-Only Rule**: Any match involving a `free` or `event` user (as participant or candidate) hides the Approve button (`Info Only (Free/Event)` status) across all pages.
   - **Exact Name Responses**: Formats user responses with exact participant display names.
   - **[Design System Guidelines](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/Design.md)**: Color tokens (`#CC723F`, `#F8F2ED`, `#829067`, `#A4302A`), typography rules (`Cormorant SC` / `Inter`), and match status lifecycle definitions.

4. **[PMPro Sync Context](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/pmpro_sync.md)**
   - Level ID mapping (`Level 3 = monthly`, `Level 4/5 = one_on_one`, `Level 6 = event`, `Level 2 = free`).
   - Automatic usermeta and pool table synchronization on membership change.

5. **[Authentication & Redirects Context](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/auth_redirects.md)**
   - `wp-login.php` redirection to PMPro login card.
   - Role-based login redirection (`/membership-account/` for subscribers).
   - Checkout completion redirect (`/personal-matchmaking-questionnaire/`).
   - PMPro login card CSS/JS styling overrides (Marcellus SC font, custom subtitles, signup link).

6. **[Free User Registration Context](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/free_registration.md)**
   - Elementor Pro decoupled form hooks (`validation` vs `new_record`).
   - Inline email, password, and phone number validation.
   - Automatic PMPro Level 2 free user account creation and safe auto-login.

7. **[Manual Match Context](file:///home/dani/Local%20Sites/arabzawaj/app/public/wp-content/plugins/matchkmaker/context/manual_match.md)**
   - Dedicated Manual Matchmaker interface (`manual_match=USER_ID`).
   - Advanced candidate search filters pre-populated with target user preferences.
   - Pair creation with default `pending_review` status and flexible score computation.
