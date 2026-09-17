# Feature Context: Form Handler & Questionnaire

This document defines the questionnaire wizard, hydration logic, file upload handling, and standalone field shortcodes (`src/Frontend/FormController.php` and `src/Frontend/FieldGenerator.php`).

---

## 1. Shortcodes
1. **`[matchmaking_form]`**: Renders the complete 2-step profile and partner preference questionnaire.
2. **`[matchmaking_field field="..."]`**: Renders a standalone single field component anywhere on the site.

---

## 2. Multi-Step Form Structure

### Step 1: About You (Self Profile)
Fields collected in Step 1 (personal information):

| Field | Notes |
| :--- | :--- |
| Full Name | Required |
| Date of Birth | Required |
| Gender | Required |
| Height | Required |
| Nationality / Country | Required |
| State / City | Optional |
| Religion | Required |
| Practicing Level | Options: Actively Practicing, Practicing Regularly, Occasionally Practicing, Rarely Practicing, I'm Not Practicing |
| Modesty Level | Gender-aware options (see below) |
| Ethnic Origin | Optional |
| Languages Spoken | Optional |
| Marital Status | Optional |
| Occupation | Optional |
| Highest Education Level | Label used for own education field |
| Smoking Habits | Optional |
| Drinking Habits | Optional |
| **About Myself** | Free-text biographical description; stored in `user_about_me` usermeta |
| **Profile Photos** | Up to 3 photos. **First photo is mandatory**; second and third are optional. Section description: *"Upload 3 clear, recent photos. Only first one is mandatory and additional photos are optional."* |

### Step 2: Partner Preferences
Fields collected in Step 2 (ideal partner criteria):

| Field | Notes |
| :--- | :--- |
| Preferred Gender | Required |
| Preferred Age Range | Min / Max |
| Preferred Country | Required |
| Preferred State / City | Optional |
| Preferred Height Range | Min / Max |
| Preferred Religion | Optional |
| Preferred Practicing Level | Options: Actively Practicing, Practicing Regularly, Occasionally Practicing, Rarely Practicing, I'm Not Practicing |
| Preferred Modesty Level | Includes "No Preference" option in addition to gender-aware options |
| Preferred Ethnic Origin | Optional |
| Preferred Languages | Optional |
| **Preferred Marital Status** | **Multi-select** — user can select multiple options |
| Lowest Education Level | Label used for preferred education field (minimum acceptable level) |
| Preferred Smoking Habits | Optional |
| Preferred Drinking Habits | Optional |
| **About My Perfect Match** | Free-text description of ideal partner; stored in `user_about_ideal_partner` usermeta |

---

## 3. Modesty Level Options

The **same set of modesty options** is used everywhere the field appears (questionnaire Step 1, questionnaire Step 2 preference, manual matchmaking form, and pool browser filter):

| Value | Label |
| :--- | :--- |
| `traditional_hijab` | Traditional / Hijab |
| `modest_conservative` | Modest dress / conservative |
| `no_religious_dress` | No religious dress / trendy |

- For the **preference** field (Step 2), an additional **"No Preference"** option is always included.
- These options are the same for both male and female profiles.

---

## 4. Data Ingestion & Dual Storage
Upon submission via AJAX (`wp_ajax_mmf_submit_form`):
1. **Indexed Criteria → `wp_matchmaking_pool`**: 11 core criteria (5 mandatory + 6 flexible) are normalized and written to the dedicated pool table for high-speed SQL matching.
2. **Presentation Metadata → `wp_usermeta`**: All form fields, biographical notes (`user_about_me`, `user_about_ideal_partner`), and media attachment IDs are saved into WordPress usermeta.
3. **Background Job Dispatch**: If the submitting member is in an active matching tier, enqueues an asynchronous matching job via `mm_enqueue_user_matching_job()`.

---

## 5. Frontend Assets & Validation
- Handled by `assets/js/matchmaking-form.js` and `assets/css/matchmaking-form.css`.
- Client-side validation per step with smooth error highlights.
- Image uploads support instant client-side preview.
- Step 1 is validated and must pass before advancing to Step 2.
