# Feature Context: Form Handler & Questionnaire

This document defines the 37-field questionnaire wizard, hydration logic, file upload handling, and standalone field shortcodes (`src/Frontend/FormController.php` and `src/Frontend/FieldGenerator.php`).

---

## 1. Shortcodes
1. **`[matchmaking_form]`**: Renders the complete 2-step profile and partner preference questionnaire.
2. **`[matchmaking_field field="..."]`**: Renders a standalone single field component anywhere on the site.

---

## 2. Multi-Step Form Structure
- **Step 1: About You (Self Profile)**: Personal details, birth date, height, religion, modesty, origin, education, occupation, smoking/drinking habits, and photo uploads.
- **Step 2: Partner Preferences**: Reciprocal search filters (gender, age window, location, height min/max, religion, modesty, origin).

---

## 3. Data Ingestion & Dual Storage
Upon submission via AJAX (`wp_ajax_mmf_submit_form`):
1. **Indexed Criteria $\rightarrow$ `wp_matchmaking_pool`**: 11 core criteria (5 mandatory + 6 flexible) are normalized and written to the dedicated pool table for high-speed SQL matching.
2. **Presentation Metadata $\rightarrow$ `wp_usermeta`**: All 37 fields, biographical notes, and media attachment IDs are saved into WordPress usermeta.
3. **Background Job Dispatch**: If the submitting member is in an active matching tier, enqueues an asynchronous matching job via `mm_enqueue_user_matching_job()`.

---

## 4. Frontend Assets & Validation
- Handled by `assets/js/matchmaking-form.js` and `assets/css/matchmaking-form.css`.
- Client-side validation per step with smooth error highlights.
- Image uploads support instant client-side preview.
