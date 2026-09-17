# Feature Context: Event Click Tracking & Analytics

This document defines the complete architecture and data flow for the **"Join Event" Click Tracking** feature: frontend interception, AJAX recording, DB storage, and the admin analytics sub-menu with CSV export (`src/Frontend/PortalController.php`, `src/Repository/MatchRepository.php`, `src/Admin/AdminPortal.php`, `assets/js/member-portal.js`, `src/View/admin/events/`).

---

## 1. Feature Overview

When a member clicks a **"Join Event"** button on any event page, the system:
1. **Intercepts** the click via a delegated JavaScript event listener before the browser navigates to the Zoom/external link.
2. **Records** the click asynchronously via a WordPress AJAX endpoint (with nonce verification and authentication gating).
3. **Redirects** the user to the external link after recording (or after a 600ms safety timeout if the AJAX call stalls).
4. **Exposes** an admin analytics sub-menu under the Events CPT with:
   - An **overview table** listing all events with total click counts.
   - A **detail page** per event showing each member, their click count, first clicked timestamp, and last clicked timestamp.
   - A **CSV export** of the detail data.

---

## 2. Database Table: `wp_matchmaker_event_clicks`

```sql
CREATE TABLE wp_matchmaker_event_clicks (
  id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id        BIGINT(20) UNSIGNED NOT NULL,
  user_id         BIGINT(20) UNSIGNED NOT NULL,
  click_count     INT(10) UNSIGNED NOT NULL DEFAULT 1,
  first_clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_clicked_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_event_user (event_id, user_id),
  KEY idx_event (event_id),
  KEY idx_user (user_id),
  KEY idx_last_clicked (last_clicked_at)
);
```

**Upsert behaviour**: `INSERT ... ON DUPLICATE KEY UPDATE click_count = click_count + 1, last_clicked_at = NOW()` — one row per (event, user) pair; `click_count` accumulates.

---

## 3. MatchRepository Methods

All DB interactions for this feature live in `src/Repository/MatchRepository.php`:

| Method | Signature | Description |
| :--- | :--- | :--- |
| `record_event_click` | `record_event_click(int $event_id, int $user_id): bool` | Upserts a click row, incrementing `click_count` on conflict. |
| `get_events_click_summary` | `get_events_click_summary(): array` | Returns all events that have at least one click: `[event_id, post_title, total_clicks, unique_clickers, last_activity]` joined against `wp_posts`. |
| `get_event_click_details` | `get_event_click_details(int $event_id): array` | Returns per-user rows for an event: `[user_id, display_name, user_email, click_count, first_clicked_at, last_clicked_at]`. |
| `get_event_click_count_for_user` | `get_event_click_count_for_user(int $event_id, int $user_id): int` | Returns the click count for a specific (event, user) pair, or `0` if not found. |

---

## 4. AJAX Endpoint: `mm_track_event_click`

**Registered in**: `PortalController::init()` via `add_action('wp_ajax_mm_track_event_click', ...)`.

**Access**: Logged-in users only (`wp_ajax_`, not `wp_ajax_nopriv_`).

**Handler**: `PortalController::handle_ajax_track_event_click()`

**Request** (POST body, `application/x-www-form-urlencoded`):

| Field | Type | Description |
| :--- | :--- | :--- |
| `nonce` | string | WordPress nonce verified against `mm_track_event_nonce` |
| `event_id` | int | The WP post ID of the event |

**Response** (JSON):
- `{"success": true, "count": <new_click_count>}` on success.
- `{"success": false, "message": "..."}` on nonce failure or unauthenticated access.

---

## 5. Frontend JavaScript (`assets/js/member-portal.js`)

### Delegated Event Listener

```javascript
document.addEventListener('click', function(e) {
    const joinBtn = e.target.closest('.join-btn, #join-btn, [data-event-action="join"], .mm-event-action-btn');
    if (!joinBtn) return;

    const link = e.target.closest('a') || joinBtn.querySelector('a');
    if (!link) return;

    e.preventDefault();
    MM_Portal.trackEventClick(eventId, link.href, link.target);
}, true);  // capture phase to ensure we intercept before navigation
```

### `MM_Portal.trackEventClick(eventId, redirectUrl, target)`

1. Sets `pointer-events: none; opacity: 0.7` on the button (visual lock).
2. Fires a `fetch` POST to `wp.ajaxurl` with `action=mm_track_event_click`, `nonce`, `event_id`.
3. On `.then()` **or** after a 600ms safety timeout: performs `window.open(redirectUrl, target)` / `window.location.href = redirectUrl`.
4. Removes the visual lock after redirect is initiated.

### `data-event-id` Convention

The event card container (or the `.join-btn` wrapper itself) must carry a `data-event-id` attribute with the WP post ID of the event. The JS reads this via `joinBtn.closest('[data-event-id]')?.dataset.eventId`.

---

## 6. Admin Analytics: "Join Click Analytics"

### Sub-Menu Registration

Registered in `AdminPortal::register_menus()` as a submenu under the `tribe_events` (Events CPT) top-level menu:

```php
add_submenu_page(
    'edit.php?post_type=tribe_events',
    'Join Click Analytics',
    'Join Click Analytics',
    'manage_matchmaker',
    'matchmaking-event-clicks',
    [$this, 'render_event_clicks_page']
);
```

**URL**: `wp-admin/edit.php?post_type=tribe_events&page=matchmaking-event-clicks`

### Overview Page (`src/View/admin/events/event-clicks.php`)

- Table columns: **Event Title**, **Total Clicks**, **Unique Members**, **Last Activity**.
- Each event title is a link to the detail page (`&event_id=<ID>`).
- Fetches data via `MatchRepository::get_events_click_summary()`.

### Detail Page (`src/View/admin/events/event-clicks-single.php`)

- Accessed via `?page=matchmaking-event-clicks&event_id=<ID>`.
- Table columns: **Member Name**, **Email**, **Click Count**, **First Clicked**, **Last Clicked**, **Avatar**.
- Fetches data via `MatchRepository::get_event_click_details(int $event_id)`.
- **Export Button**: Links to `?page=matchmaking-event-clicks&event_id=<ID>&mm_action=export_event_clicks`.

### CSV Export (`AdminPortal::export_event_clicks_csv()`)

- Triggered when `$_GET['mm_action'] === 'export_event_clicks'` in `AdminPortal::handle_admin_actions()`.
- Calls `ob_end_clean()` before emitting headers to prevent output buffering conflicts.
- CSV columns: `Member Name`, `Email`, `Click Count`, `First Clicked`, `Last Clicked`.
- Download filename: `event-clicks-<event_id>-<date>.csv`.

---

## 7. Action Flow Diagram

```
[User clicks .join-btn]
        │
        ▼
[JS: e.preventDefault() — navigation intercepted]
        │
        ▼
[JS: fetch POST → /wp-admin/admin-ajax.php
     action=mm_track_event_click
     event_id=<ID>, nonce=<nonce>]
        │
        ├─── AJAX ──▶ [PortalController::handle_ajax_track_event_click()]
        │                    │
        │                    ▼
        │            [Nonce & auth check]
        │                    │
        │                    ▼
        │            [MatchRepository::record_event_click($event_id, $user_id)]
        │                    │
        │                    ▼
        │            [INSERT ... ON DUPLICATE KEY UPDATE click_count + 1]
        │                    │
        │                    ▼
        │            [Return JSON {success: true, count: N}]
        │
        ▼
[JS: on response OR 600ms timeout → window.open(redirectUrl)]
```

---

## 8. Access Control

- **Frontend AJAX**: Requires logged-in user. Nonce verified against `mm_track_event_nonce` (localized in `wp_localize_script`).
- **Admin sub-menu**: Requires `manage_matchmaker` capability (assigned to `administrator` and `matchmaker_admin` roles).
- **CSV export**: Same capability check as the admin sub-menu page.

---

## 9. Test Coverage (`tests/Unit/EventClickTrackingTest.php`)

| Test | Description |
| :--- | :--- |
| `test_record_event_click_inserts_row` | Verifies the upsert query is formed correctly on first click. |
| `test_record_event_click_increments_count` | Verifies `click_count + 1` on subsequent clicks. |
| `test_get_events_click_summary_returns_array` | Verifies summary query returns array. |
| `test_get_event_click_details_returns_array` | Verifies detail query returns array. |
| `test_get_event_click_count_for_user_returns_int` | Verifies zero returned for non-existent record. |
| `test_admin_menu_registered` | Verifies `matchmaking-event-clicks` page is in the allowed-pages whitelist. |
| `test_ajax_hook_registered` | Verifies `wp_ajax_mm_track_event_click` action is registered. |
| `test_view_templates_exist` | Verifies `event-clicks.php` and `event-clicks-single.php` template files exist. |
| `test_export_event_clicks_csv_generation` | Verifies CSV output for a given event ID. |
