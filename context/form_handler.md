# Feature Context: Form & Data Collection

This document defines the architecture, rules, and constraints for the Matchmaking Questionnaire Form (`includes/Form_Handler.php` & `includes/Field_Generator.php`).

## 1. Core Responsibilities
- Provides shortcodes `[matchmaking_form]` (full 2-step form) and `[matchmaking_field name="..."]` (standalone field).
- Hydrates existing user profile data from `wp_matchmaking_pool` and `wp_usermeta`.
- Processes form submission via AJAX (`wp_ajax_mmf_submit_form`).
- Enqueues frontend assets (`assets/css/matchmaking-form.css`, `assets/js/matchmaking-form.js`) conditionally only on pages containing the form shortcodes.

## 2. Form Architecture & 2-Step Layout
The form is divided into two distinct steps:
- **Step 1: About You**
  - Personal information, location, background, physical attributes, lifestyle, family, education, and finance.
  - Required client-side validation: Full Name and Email must be filled before proceeding to Step 2.
  - Profile Photos: Supports up to 3 photo uploads, processed via WP Media Library (`media_handle_upload()`).
- **Step 2: Partner Preferences**
  - Preferred age range, location, background, partner attributes, lifestyle preferences, and ideal match notes (`pref_additional_info`).

## 3. Data Processing & Security
- **Nonce Verification**: Validates `mmf_nonce` on AJAX submission.
- **Authentication**: Requires user to be logged in.
- **Data Hygiene**: Sanitizes text inputs (`sanitize_text_field`), textareas (`sanitize_textarea_field`), emails (`sanitize_email`), and normalizes multi-select array inputs into comma-delimited strings.
- **Dual Table Storage**:
  - Indexed matching criteria are saved in `wp_matchmaking_pool` via `$wpdb->replace()`.
  - Presentation and display attributes are saved in `wp_usermeta` via `update_user_meta()`.
- **Auto-Matching Trigger**: If the user is a `monthly` tier, `mm_enqueue_user_matching_job()` is executed after successful database update.
