# Feature Context: Authentication & Redirection Flows

This document defines the architecture, rules, and constraints for authentication redirects and login page styling (`includes/Auth_Redirects.php`).

## 1. Core Responsibilities
- Overrides login and registration URLs to point to PMPro custom pages.
- Manages role-based login redirects.
- Provides `[logout_url]` shortcode.
- Manages PMPro checkout completion redirection.
- Hides WordPress admin bar for subscribers.
- Applies custom CSS and JS design overrides to the PMPro login page.

## 2. Redirection Rules
- **Login Page Override (`login_url` / `login_init`)**: Intercepts `wp-login.php` GET requests (except logout/reset password) and redirects users to the PMPro login page (`pmpro_url('login')`).
- **Role-Based Login Redirect (`login_redirect`)**:
  - Administrators -> `/wp-admin/`
  - Subscribers / Members -> `/membership-account/`
- **Post-Checkout Registration Redirect (`pmpro_confirmation_url`)**: Redirects users completing checkout to `/personal-matchmaking-questionnaire/` to fill out their profile form.
- **Logout Shortcode (`[logout_url redirect="..."]`)**: Generates safe logout URL redirecting to home URL by default.

## 3. PMPro Login Page Design Overrides (`custom_pmpro_login_page_design()`)
Hooked to `wp_footer` when on PMPro login page:
- Applied Typography: Marcellus SC for title, Poppins for subtitle and signup text, Inter for forgot password link.
- Title & Subtitle: Replaces title text with *"Sign Into Your Account"* and inserts subtitle *"Please enter your email and password below."*.
- Bottom Signup Callout: Inserts *"Don't have an account? Sign Up"* linking to PMPro Level 3 checkout (`/membership-checkout/?pmpro_level=3`).
- Forgot Password Link: Renames *"Lost Password?"* link to *"Forget password"*.
