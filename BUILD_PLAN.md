# Current Build Plan

## Active Task
**Member Dashboard & Match Flow Refinements: Persistent Notifications Table, 15s Heartbeat, Instant Badge Clearing, Primary Tab Hovers & Screenshot Layout Alignment**

---

## Objectives & Scope
1. **Persistent Notifications Table (`wp_matchmaker_notifications`)**:
   - Added `wp_matchmaker_notifications` database table schema in `DB_Migrator.php`.
   - Logging unread notifications on match approval so offline/returning members immediately see their unread match toast alert upon opening `/dashboard/`.
2. **15-Second Heartbeat API Interval**:
   - Updated `Notification_Manager.php` to set Heartbeat interval to 15s on `/dashboard/` for fast toast alerts.
3. **Instant Badge Clearing (`is_read = 1`)**:
   - Added `mm_mark_notifications_read` AJAX handler in `Notification_Manager.php` and `MM_Portal.markNotificationsRead()` in `member-portal.js`.
   - Fires automatically when member clicks "View Match" or switches to the Matches tab, instantly resetting the bell badge and Matches tab counter to 0.
4. **Primary Brand Color (`#CC723F`) Tab Hovers**:
   - Updated `member-portal.css` so tab hovers and active underlines strictly use `#CC723F`.
5. **Exact Screenshot Layout & Canvas Width (1100px)**:
   - Expanded canvas width to `1100px`.
   - Aligned 5-state step views pixel-for-pixel with the 5 reference images (excluding the inner left sub-sidebar).
   - Added Gear settings icon and Forms link to top header bar.

---

## Step-by-Step Execution Plan

- [x] **Step 1: Database Migration Update**
  - Updated `DB_Migrator.php` with `wp_matchmaker_notifications` table schema and updated database version to `2.1.0`.
- [x] **Step 2: Notification_Manager Refinements**
  - Set Heartbeat interval to 15s.
  - Added `create_notification()` and `handle_ajax_mark_read()` endpoints.
- [x] **Step 3: CSS Stylesheet Refinements**
  - Updated `member-portal.css` for `#CC723F` tab hovers, 1100px canvas width, and screenshot design tokens.
- [x] **Step 4: JS Member Portal Refinements**
  - Added `markNotificationsRead()` helper in `member-portal.js`.
  - Added initial check for unread toast alerts on page load for returning members.
- [x] **Step 5: Match_Flow_Handler Header Updates**
  - Added Gear settings icon and Forms tab link to `Match_Flow_Handler.php`.
- [x] **Step 6: Autoloader & Documentation Update**
  - Regenerated Composer autoloader and updated `BUILD_PLAN.md` and `HISTORY.md`.

---

## Verification & Completion Criteria
- [x] `wp_matchmaker_notifications` table created with `is_read` column.
- [x] Heartbeat polling runs every 15 seconds on `/dashboard/`.
- [x] Clicking "View Match" or opening Matches tab immediately clears bell badge count and tab badge count.
- [x] Returning offline members with unread matches immediately get slide-out toast popups on dashboard load.
- [x] Tab hovers use `#CC723F` primary brand color.
- [x] Portal canvas width set to `1100px` with exact screenshot design matching.
