Here is the comprehensive Markdown specification document for your AI agent.

---

### File: `matchmaker/NOTIFICATIONS_AND_MATCH_FLOW.md`

```markdown
# Matchmaker — Notification System & User Match Flow Specification

This document details the exact interaction between the **WordPress Heartbeat API**, the **Two-Layer Match Status Model**, and the **Interactive Frontend Matching Flow** inside the `matchmaker/` plugin.

---

## 1. Two-Layer Status & Decision Architecture

The matchmaking system splits pair lifecycles into a **Global Status** (`status`) and individual **Directional Responses** (`user_one_response`, `user_two_response`)[cite: 7]. This separation ensures zero state collisions and clean UX gating.


```

```
                          [Engine Matches Candidate]
                                      │
                                      ▼
                           status = 'pending_review'
                           user_one_response = 'pending'
                           user_two_response = 'pending'
                                      │
                          ┌───────────┴───────────┐
                          ▼                       ▼
                   [Admin Rejects]         [Admin Approves]
                          │                       │
                status = 'admin_rejected'   status = 'approved'
                (Hidden from users)         (Visible on User Dashboard)
                                                  │
                                      ┌───────────┴───────────┐
                                      ▼                       ▼
                                [User A Accepts]       [Either User Declines]
                           user_one_response = 'accepted'         │
                           user_two_response = 'pending'   status = 'rejected'
                                      │
                                      ▼
                                [User B Accepts]
                           user_two_response = 'accepted'
                                      │
                                      ▼
                               status = 'matched'
                               contact_revealed = 1

```

```

### Complete State Matrix

| Lifecycle Stage | Global `status` | `user_one_response` | `user_two_response` | Dashboard Visibility & UI State |
| :--- | :--- | :--- | :--- | :--- |
| **1. Algorithm Output** | `pending_review`[cite: 7] | `pending`[cite: 7] | `pending`[cite: 7] | **Admin Review Only** (Hidden from front-end users)[cite: 3]. |
| **2. Admin Approved** | `approved`[cite: 7] | `pending`[cite: 7] | `pending`[cite: 7] | **State 1: Discovery Card** shows "You Have a New Match". |
| **3. One-Sided Accept** | `approved`[cite: 7] | `accepted` | `pending`[cite: 7] | **State 3: Waiting State** for accepter; **State 1** for pending user. |
| **4. Mutual Match** | `matched`[cite: 7] | `accepted`[cite: 7] | `accepted`[cite: 7] | **State 5: Unlocked Card** (`contact_revealed = 1`) reveals direct contact. |
| **5. User Declines** | `rejected`[cite: 7] | `rejected` | `pending` / `accepted` | Archived/removed from both users' active match feeds. |
| **6. Admin Disallows** | `admin_rejected`[cite: 7] | `pending`[cite: 7] | `pending`[cite: 7] | **Admin Only** (Deducts zero user quota)[cite: 3]. |

---

## 2. Notification System Architecture (WordPress Heartbeat API)

The notification system uses core WordPress Heartbeat with strict optimization to prevent server overhead.

### 2.1 Technical Constraints & Optimization Rules
1. **Targeted Page Scope**: Heartbeat is enqueued **strictly** on `/membership-account/` and `/matches/`. It is completely dequeued across the rest of the site.
2. **Tier-Gated Polling**: Free and event users (`user_type IN ('free', 'event')`) are skipped entirely—no background pulse requests are executed for them[cite: 5].
3. **60-Second Throttled Interval**: Uses `heartbeat_settings` to set an interval of 60 seconds (throttling to 120s when the browser tab loses focus).
4. **Transient Caching with Write-Time Invalidation**:
   - Pulses read from `get_transient("mm_unread_count_{$user_id}")` with a 60s TTL.
   - On admin approval (`mm_match_approved`) or user action (`mm_match_response_updated`), the transient is explicitly flushed via `delete_transient()`.
5. **Lightweight Integer Payload**: Returns only a single integer key `matchmaker_unread_count` rather than heavyweight profile objects.

### 2.2 Server-to-Client Polling Cycle


```

Browser Tab (wp.heartbeat @ 60s)
│ (Sends tick payload: { mm_poll_notifications: true })
▼
Server: `Matchmaker_Notification_Manager::handle_heartbeat_pulse`
│
├──> Reads `mm_unread_count_{$user_id}` (60s Transient)
│     └── If miss: COUNT(*) FROM wp_matches WHERE status='approved' AND response='pending'
│
▼
Returns JSON: { matchmaker_unread_count: 2 }
│
▼
Browser: `heartbeat-tick` Event Listener
│
├──> Updates Bell Badge Count: `.mm-bell-badge`
└──> If (currentCount > lastKnownCount): Fires `#mm-toast-box` ("New Match Available")

```

---

## 3. Five-State Interactive Frontend Match Flow

The companion HTML template provides 5 distinct views that transition dynamically based on user interaction:

### State 1: Dashboard Discovery (`#step-1`)
* Triggered when `status = 'approved'` and the user's response is `pending`[cite: 7].
* Displays candidate summary, location, languages, quote box, and a 7-day expiration timer.
* Primary CTA: **"View Match"** $\rightarrow$ Transitions to State 2.

### State 2: Full Profile Review (`#step-2`)
* Displays verified badge, photo gallery, biography, background tags (nationality, religion, career), and lifestyle traits.
* Sensitive contact details (email, phone, Instagram) remain completely hidden (`contact_revealed = 0`)[cite: 4, 7].
* Persistent Sticky Bottom Dock:
  * **"Decline Match"** $\rightarrow$ Opens State 4 (Decline Confirmation).
  * **"Accept Match →"** $\rightarrow$ Submits acceptance AJAX and opens State 3.

### State 3: Accepted — Waiting for Response (`#step-3`)
* Displayed when the user has accepted, but the other party has not yet answered.
* Shows explicit status pill: `Your Response: ✓ Accepted` vs. `Their Response: ⏳ Waiting`.
* CTA: **"Back to Dashboard"**.

### State 4: Decline Confirmation Modal (`#step-4`)
* Confirmation prompt: *"Are you sure you want to decline this match? Once declined, this match will no longer be available to you."*
* Options:
  * **"Keep Match"** $\rightarrow$ Returns to State 2 (Profile View).
  * **"Decline Match →"** $\rightarrow$ Updates response to `rejected` and returns to Dashboard.

### State 5: Mutual Match — Revealed Contact Info (`#step-5`)
* Activated when **both** parties accept (`status = 'matched'`, `contact_revealed = 1`)[cite: 7].
* Unveils direct verified details:
  * **Phone / WhatsApp**: E.g. `+971 50 123 4567`
  * **Email Address**: E.g. `AISHA.M@EXAMPLE.COM`
  * **Instagram Handle**: E.g. `@AISHA.M_DXB`
* Includes privacy and respectful communication guidelines.

---

## 4. Design Tokens Applied

```css
/* Color Palette */
--primary:           #CC723F; /* Buttons, active tabs, accent borders */
--primary-hover:     #B6602F;
--secondary:         #F8F2ED; /* Canvas background, subtle card fills */
--text-dark:         #000000; /* Headings and primary body copy */
--sec-accent:        #829067; /* Verified indicators, approved tags */
--forest-green:      #144D34; /* Success headers, "It's a Match!" title */
--card-gray:         #F4F6F8; /* Profile summary pill boxes */
--danger-bg:         #FEE8E8; /* Decline modal icon circle */
--danger-border:     #D93025;

/* Typography */
Headings & Display:  'Cormorant SC', serif (Uppercase, letter-spacing: 0.8px)
Body & UI Controls:  'Inter', sans-serif (Weights: 400, 500, 600, 700)

```

```

```