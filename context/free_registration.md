# Feature Context: Free Registration & Elementor Pro Form Integration

This document defines the decoupled Elementor Pro Form hooks, user account generation, and automatic login logic (`src/Core/FreeRegHandler.php`).

---

## 1. Core Responsibilities
- Intercepts submissions from Elementor Pro Form widget(s).
- Validates registration data (unique email, minimum password length, phone formatting).
- Creates WordPress subscriber user, assigns PMPro Free membership level, sets `'free'` tier meta, and signs user in seamlessly.

---

## 2. Decoupled Form Hook Architecture
- **Validation Hook (`elementor_pro/forms/validation`)**:
  - Validates email syntax and uniqueness via `email_exists()`.
  - Checks password strength/length requirements.
  - Formats and normalizes international phone numbers.
- **Record Creation Hook (`elementor_pro/forms/new_record`)**:
  - Creates WordPress user via `wp_create_user()`.
  - Dynamically assigns PMPro Free level (`PMProSync::get_primary_level_for_tier('free', 2)`).
  - Sets `user_type` meta to `'free'`.
  - Performs secure auto-login via `wp_signon()`.

---

## 3. Dynamic Form ID Configuration
- Admins configure the active Elementor Form ID in **Matchmaking > Settings** (`mm_free_reg_form_id`, default: `'2784843'`).
- Supports single or multiple comma-separated Form IDs.
- `FreeRegHandler::matches_form_id(string $form_id): bool` safely checks incoming Elementor form IDs against configured settings.
