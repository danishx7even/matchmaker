# Feature Context: Free User Registration

This document defines the architecture, rules, and constraints for Elementor Pro decoupled free registration (`includes/Free_Reg_Handler.php`).

## 1. Core Responsibilities
- Intercepts Elementor Pro Form submissions to register free users safely.
- Decouples validation (`elementor_pro/forms/validation`) from creation (`elementor_pro/forms/new_record`).
- Assigns PMPro Level 2 (Free Membership) and logs the user in automatically upon successful registration.

## 2. Decoupled Form Hooks & Logic
- **Inline Validation Hook (`elementor_pro/forms/validation`)**:
  - Validates email address format and uniqueness via `email_exists()`.
  - Validates password length (minimum 6 characters).
  - Validates phone number format and E.164 compliance.
- **Account Creation Hook (`elementor_pro/forms/new_record`)**:
  - Creates a new WordPress user with `subscriber` role via `wp_create_user()`.
  - Assigns PMPro Level 2 (Free Registration) using `pmpro_changeMembershipLevel(2, $user_id)`.
  - Sets `user_type` usermeta to `'free'`.
  - Performs safe auto-login (`wp_set_current_user`, `wp_set_auth_cookie`).
