# Feature Context: Member Portal & 5-State Interactive Match Flow

This document details the frontend member portal dashboard, 2-tab layout, and the 5-state interactive match decision flow (`src/Frontend/PortalController.php`, `src/View/frontend/portal/`, `assets/css/member-portal.css`, `assets/js/member-portal.js`).

---

## 1. Shortcode & Navigation Structure
- **Shortcode**: `[matchmaker_member_portal]` (alias: `[az_profile]`).
- **Canvas Layout**: Contained within an 1100px max-width luxury card with 36px border radii (`src/View/frontend/portal/portal.php`).
- **Top Header Bar**: Welcome greeting, Bell notification icon with live unread counter badge, and Tab Navigation (`Profile` & `Matches`).
- **Dynamic Tab Switching**: Instant tab switching powered by JavaScript and AJAX endpoint `wp_ajax_mm_reload_tab_content`.
- **View Templates Directory**:
  - `src/View/frontend/portal/portal.php`: Main portal wrapper and tab navigation.
  - `src/View/frontend/portal/tab-profile.php`: Member profile tab with user self criteria and preferences.
  - `src/View/frontend/portal/tab-matches.php`: Matches tab controller delegating to step templates.
  - `src/View/frontend/portal/steps/`:
    - `step-1-discovery.php`: Step 1 Discovery card.
    - `step-2-profile.php`: Step 2 Full candidate profile review & accept/decline dock.
    - `step-3-waiting.php`: Step 3 Waiting for candidate response.
    - `step-4-decline.php`: Step 4 Match declined confirmation modal card.
    - `step-5-contact.php`: Step 5 Mutual match contact reveal.

---

## 2. 5-State Interactive Match Flow
When an approved match is available, the Matches tab renders an interactive 5-state workflow:

### State 1: Discovery Card (`step-1-discovery.php`)
- Displays candidate first name, age, city/country, blur photo preview, and matching score badge.
- Dynamic match expiration countdown text (e.g. `Expires in X days`).
- **Dual CTAs**:
  - `View Match →` (Navigates directly to full profile view in Step 2).
  - `View Status` (Navigates to decision status in Step 3/4).

### State 2: Full Profile Review & Decision (`step-2-profile.php`)
- Complete candidate details: Background, religious observance, lifestyle, photos, and mutual compatibility highlights.
- Top navigation: `← Back to Matches` arrow.
- Inside-canvas action footer:
  - If pending: `Decline Match` (secondary button) & `Accept Match →` (primary `#CC723F` button).
  - If already responded: `View Status →`.

### State 3: Waiting for Candidate Response (`step-3-waiting.php`)
- Rendered when the current user has clicked **Accept**, but the candidate has not responded yet.
- Shows friendly hourglass animation and reminder that candidate has $N$ days to respond.

### State 4: Match Declined (Graceful Close) (`step-4-decline.php`)
- Rendered if either party clicked **Decline** or the match expired past `mm_match_expiry_days`.
- Empathetic message reassuring the member that new matches are being prepared.

### State 5: Mutual Match (Contact Revealed) (`step-5-contact.php`)
- Triggered when **both** parties accept (`user_one_response = 'accepted'` AND `user_two_response = 'accepted'`).
- Status automatically updates to `matched` and `contact_revealed = 1`.
- Unlocks candidate full name, verified phone number, email address, and direct messaging link.

---

## 3. Tier-Gating & Upsell Experience
- **Monthly / 1-on-1 VIP Members**: Full access to Profile and Matches tabs.
- **Free / Event Tier Members**: Profile tab is accessible; Matches tab displays a high-converting luxury upsell card directing users to configured membership checkout (`ProfileService::get_membership_checkout_url()`).
