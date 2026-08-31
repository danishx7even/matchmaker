# Feature Context: Real-Time Heartbeat Notifications & Email Alerts

This document defines the real-time polling notification system, unread badge counters, slide-out toast alerts, and transactional email notifications (`src/Service/NotificationService.php` and `assets/js/member-portal.js`).

---

## 1. WordPress Heartbeat API Integration
- **Interval**: Fast 15-second polling interval enabled exclusively on the member portal page (`/dashboard/`).
- **Heartbeat Hook**: `heartbeat_received` / `heartbeat_nopriv_received`.
- **Transient Caching**: Unread notification counts are cached in transients (`mm_unread_count_{user_id}`) for 60 seconds to minimize database overhead.
- **Payload**: Transmits unread count and latest unread notification objects (id, title, message, created_at).

---

## 2. UI Notifications (Badge & Toast Alerts)
- **Top Bell Icon Badge**: Updates in real-time when new matches are approved.
- **Matches Tab Badge Counter**: Increments automatically to highlight pending action items.
- **Slide-Out Toast Alert**: Top-right toast container (`#mm-toast-box`) displays animated alert when a new match is approved.
- **Instant Badge Clearing**: Clicking "View Match" or switching to the Matches tab triggers AJAX endpoint `wp_ajax_mm_mark_notifications_read`, immediately marking notifications as read (`is_read = 1`) and resetting badges to 0.

---

## 3. Persistent Notifications Table (`wp_matchmaker_notifications`)
- `id` (PK), `user_id`, `match_id`, `type` (`'match_approved'`), `title`, `message`, `is_read` (`0` or `1`), `created_at`.
- Ensures returning or offline members immediately receive their toast alerts upon loading the dashboard.

---

## 4. Email Approval Notifications
- Triggered automatically when an administrator approves a match pair.
- Sent to both User 1 and User 2.
- Uses configurable subject (`mm_email_approval_subject`) and rich HTML template (`mm_email_approval_template`).
- Replaces dynamic template tags:
  - `{user_name}`: Recipient display name.
  - `{candidate_name}`: Matched candidate display name.
  - `{candidate_age}`: Matched candidate age in years.
  - `{candidate_location}`: Matched candidate city/country.
  - `{dashboard_url}`: Direct link to configured member portal page.
