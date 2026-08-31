# Feature Context: Auth, Redirects & Dynamic Page Routing

This document defines dynamic page URL resolution, role-based login/logout redirects, and PMPro login card design (`src/Frontend/AuthController.php` and `src/Service/ProfileService.php`).

---

## 1. Dynamic Page URL Resolvers (`ProfileService.php`)
Replaces hardcoded page paths with WordPress Page ID settings configured in **Matchmaking > Settings**:
- `get_dashboard_url()`: Checks option `mm_page_dashboard_id` via `get_permalink()`, falls back to `/dashboard/`.
- `get_form_url()`: Checks option `mm_page_questionnaire_id`, falls back to `/personal-matchmaking-questionnaire/`.
- `get_membership_account_url()`: Checks option `mm_page_account_id` or PMPro account page, falls back to `/membership-account/`.
- `get_membership_checkout_url(int $level_id)`: Checks option `mm_page_checkout_id` or PMPro checkout page with `?level=X`.
- `get_events_url()`: Checks option `mm_page_events_id`, falls back to `/events-2/`.

---

## 2. Role-Based Login & Checkout Redirects
- **Admin Users**: Redirected directly to `/wp-admin/`.
- **Subscriber / Member Users**: Redirected to configured Member Dashboard (`ProfileService::get_dashboard_url()`).
- **PMPro Checkout Confirmation**: Redirects newly subscribed members to the Questionnaire form (`ProfileService::get_form_url()`).

---

## 3. Shortcode: `[logout_url]`
- Outputs a secure, nonced WordPress logout URL.
- Supports `redirect` attribute: `[logout_url redirect="/login/"]`.

---

## 4. PMPro Login Page Styling
- Injected on PMPro login pages to match Arab Zawaj luxury aesthetic.
- Applies Cormorant SC typography, subtitle instructions, and sign-up upsell link.
