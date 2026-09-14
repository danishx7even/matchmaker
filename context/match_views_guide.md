# Matchmaker Plugin — Complete Match States & Views Reference Guide

This document provides a comprehensive, exhaustive reference for all match lifecycle states, portal view templates (Step 1 through Step 5), status badges, copy text, countdown timers, and button actions in the Matchmaker member portal.

---

## 1. Match Review Lifecycle & 5-Step Architecture

The Member Portal matches interface (`tab-matches.php`) implements a unified 5-step interactive workflow:

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│     Step 1      │ ───►  │     Step 2      │ ───►  │     Step 3      │
│ Discovery Card  │       │ Full Profile    │       │ Response Status │
│ (Active Match)  │       │ Review & Dock   │       │ & Waiting State │
└─────────────────┘       └─────────────────┘       └─────────────────┘
        │                         │                          ▲
        │                         ▼                          │
        │                 ┌─────────────────┐                │
        │                 │     Step 4      │ ───────────────┘
        │                 │ Decline Modal   │ (If Declined)
        │                 └─────────────────┘
        ▼
┌─────────────────┐
│     Step 5      │
│ Mutual Match &  │ (If Both Accepted)
│ Contact Reveal  │
└─────────────────┘
```

| Step | Template File | Primary Purpose | Default Trigger Condition |
| :--- | :--- | :--- | :--- |
| **Step 1** | `steps/step-1-discovery.php` | Summary match recommendation card with avatar, quote, key tags, and countdown timer. | Initial view for fresh approved matches awaiting member review. |
| **Step 2** | `steps/step-2-profile.php` | Full profile review with photo, background details, lifestyle tags, and fixed bottom action dock. | Opened when user clicks "View Match" or "Review Profile & Respond". |
| **Step 3** | `steps/step-3-waiting.php` | Response status hub comparing both parties' decisions, next steps guidance, and dynamic routing. | Automatically shown when user has responded (accepted/declined) while awaiting candidate, or when user clicks "View Status". |
| **Step 4** | `steps/step-4-decline.php` | Decline confirmation screen preventing accidental rejection of match recommendations. | Opened when user clicks "Decline Match" on Step 2. |
| **Step 5** | `steps/step-5-contact.php` | Mutual match celebration screen revealing direct verified phone, email, and social links. | Automatically shown when both parties accept (`status === 'matched'`). |

---

## 2. Match Expiry & Countdown Timer Rules

1. **Configurable Response Window**:
   - Configured via WordPress Admin: **Matchmaker > Settings > General > Match Review Expiry Duration** (`mm_match_expiry_days`, default: **7 days**).
2. **Timer Start Point**:
   - The countdown starts when an admin approves the match (`approved_at` timestamp).
   - If `approved_at` is empty, fallback uses `created_at` or `updated_at`.
3. **Timer Calculation Formula**:
   $$\text{Deadline} = \text{approved\_at} + (\text{expiry\_days} \times 86400)$$
   $$\text{Days Remaining} = \max\left(0, \left\lceil \frac{\text{Deadline} - \text{current\_time}}{\text{DAY\_IN\_SECONDS}} \right\rceil\right)$$
4. **Active State Retention**:
   - `days_remaining` is computed for **all active approved matches** regardless of whether the current user has already accepted or is pending.
   - The match only transitions to `expired` if the deadline passes ($\text{Days Remaining} \le 0$) without both parties accepting, or via the automated daily Action Scheduler expiration worker (`check_7day_match_expirations()`).

---

## 3. Exhaustive State Matrix & View Specifications

### Case 1: Fresh Match — Both Pending (`pending` + `pending`)
- **Condition**: Match approved by admin; neither member has submitted a decision yet.
- **Initial Portal Tab View**: **Step 1 (Discovery Card)**

#### Step 1 View:
- **Badge**: `★ ACTIVE MATCH RECOMMENDATION`
- **Heading**: `You Have a New Match`
- **Description**: *"We've found someone we think could be a meaningful match for you based on your shared values and requirements."*
- **Response Section Heading**: `Your Response`
- **Response Section Copy**: *"Take your time to review this profile. You have X days to accept or decline this match before it expires."*
- **Timer Card**: Clock Icon + `Time Remaining: X days remaining`
- **Action Buttons**:
  - `[View Match →]` (Primary Orange Button $\rightarrow$ opens **Step 2**)
  - `[View Status]` (Outline Dark Button $\rightarrow$ opens **Step 3**)

#### Step 2 Action Dock:
- **Prompt Heading**: `What do you think?`
- **Prompt Copy**: *"You have X days to respond to this match."*
- **Buttons**:
  - `[Decline Match]` (Outline Dark Button $\rightarrow$ opens **Step 4**)
  - `[Accept Match →]` (Primary Orange Button $\rightarrow$ submits AJAX `accept`)

#### Step 3 Status View:
- **Status Icon Bubble**: Gray/Neutral Clock Icon
- **Main Heading**: `Match Pending Your Review`
- **Main Description**: *"You haven't responded to this match yet. Please review the candidate's profile and choose to accept or decline before the match expires."*
- **Your Response Card**: `⚪ Pending`
- **Their Response Card**: `⚪ Waiting`
- **What's Next Note**: `Review the candidate's profile. If both of you accept, you will instantly unlock each other's approved direct contact details.`
- **Action Buttons**:
  - `[Review Profile & Respond →]` (Primary Orange Button $\rightarrow$ opens **Step 2**)
  - `[Back to Profile Dashboard →]` (Outline Dark Button $\rightarrow$ switches to Profile Tab)

---

### Case 2: You Accepted, Candidate Pending (`accepted` + `pending`)
- **Condition**: Current user clicked "Accept Match"; candidate has not responded yet. Expiry deadline has not elapsed.
- **Initial Portal Tab View**: **Step 3 (Response Status & Waiting)**

#### Step 1 View (if navigated back):
- **Response Section Heading**: `Match Accepted`
- **Response Section Copy**: *"You've accepted this match. We're now waiting for the candidate to review and respond."*
- **Timer Card**: Hidden (User has already completed their action).
- **Action Buttons**:
  - `[View Match →]` (Primary Orange Button $\rightarrow$ opens **Step 2**)
  - `[View Status]` (Outline Dark Button $\rightarrow$ opens **Step 3**)

#### Step 2 Action Dock:
- **Prompt Heading**: `Match Accepted`
- **Prompt Copy**: *"You have accepted this match. Awaiting candidate response."*
- **Buttons**:
  - `[View Status →]` (Primary Orange Button $\rightarrow$ opens **Step 3**)

#### Step 3 Status View:
- **Status Icon Bubble**: Vibrant Orange Checkmark Bubble
- **Main Heading**: `Match Accepted`
- **Main Description**: *"You've accepted this match. We're now waiting for the candidate to review and respond."*
- **Your Response Card**: `✓ Accepted` (Green/Gold Accepted Badge)
- **Their Response Card**: `⚪ Waiting` (Neutral Waiting Badge with remaining response window)
- **What's Next Note**: `If they also accept, you'll both receive access to each other's approved direct contact information.`
- **Action Buttons**:
  - `[Back to Profile Dashboard →]` (Primary Orange Button $\rightarrow$ switches to Profile Tab)

---

### Case 3: Candidate Accepted First, You Pending (`pending` + `accepted`)
- **Condition**: Candidate accepted the match recommendation; current user has not yet submitted a decision.
- **Initial Portal Tab View**: **Step 1 (Discovery Card)**

#### Step 1 View:
- **Heading**: `You Have a New Match`
- **Response Section Heading**: `Your Response`
- **Response Section Copy**: *"Take your time to review this profile. You have X days to accept or decline this match before it expires."*
- **Timer Card**: Clock Icon + `Time Remaining: X days remaining`
- **Action Buttons**:
  - `[View Match →]` (Primary Orange Button $\rightarrow$ opens **Step 2**)
  - `[View Status]` (Outline Dark Button $\rightarrow$ opens **Step 3**)

#### Step 2 Action Dock:
- **Prompt Heading**: `What do you think?`
- **Prompt Copy**: *"You have X days to respond to this match."*
- **Buttons**:
  - `[Decline Match]` (Outline Dark Button $\rightarrow$ opens **Step 4**)
  - `[Accept Match →]` (Primary Orange Button $\rightarrow$ submits AJAX `accept` $\rightarrow$ triggers instant Step 5 mutual match!)

#### Step 3 Status View:
- **Status Icon Bubble**: Gray/Neutral Clock Icon
- **Main Heading**: `Candidate Accepted — Awaiting Your Response`
- **Main Description**: *"The candidate has accepted this match recommendation! Please review their profile and submit your response to connect."*
- **Your Response Card**: `⚪ Pending`
- **Their Response Card**: `✓ Accepted`
- **What's Next Note**: `Review the candidate's profile. If both of you accept, you will instantly unlock each other's approved direct contact details.`
- **Action Buttons**:
  - `[Review Profile & Respond →]` (Primary Orange Button $\rightarrow$ opens **Step 2**)
  - `[Back to Profile Dashboard →]` (Outline Dark Button $\rightarrow$ switches to Profile Tab)

---

### Case 4: Mutual Match — Both Accepted (`accepted` + `accepted` / `matched`)
- **Condition**: Both parties have submitted `accept`. Match row status transitioned to `matched` and `contact_revealed = 1`.
- **Initial Portal Tab View**: **Step 5 (Direct Contact Reveal & Celebration)**

#### Step 1 View (if navigated back):
- **Special Header**: `★ IT'S A MATCH!` (Forest Green #144D34)
- **Copy**: *"Both of you have accepted the match! You can now view each other's direct contact details."*
- **Action Buttons**:
  - `[View Contact Details →]` (Primary Orange Button $\rightarrow$ opens **Step 5**)
  - `[View Full Profile]` (Outline Dark Button $\rightarrow$ opens **Step 2**)

#### Step 2 Action Dock:
- **Prompt Heading**: `It's a Match!`
- **Prompt Copy**: *"Both of you have accepted the match."*
- **Buttons**:
  - `[View Contact Details →]` (Primary Orange Button $\rightarrow$ opens **Step 5**)

#### Step 3 Status View:
- **Status Icon Bubble**: Green/Gold Checkmark Icon
- **Main Heading**: `It's a Mutual Match!`
- **Main Description**: *"Both you and the candidate have accepted! You can now view each other's approved direct contact details."*
- **Your Response Card**: `✓ Accepted`
- **Their Response Card**: `✓ Accepted`
- **What's Next Note**: `You can reach out directly via phone, email, or social media to begin your conversation.`
- **Action Buttons**:
  - `[View Contact Details →]` (Primary Orange Button $\rightarrow$ opens **Step 5**)
  - `[Back to Profile Dashboard →]` (Outline Dark Button $\rightarrow$ switches to Profile Tab)

#### Step 5 Direct Contact Reveal View:
- **Hero Avatar**: Gradient Orange Bubble with Rings / Sparks Icon
- **Main Title**: `It's a Match!` (#144D34 Forest Green)
- **Subtitle**: *"You both accepted the match. Now you can connect directly outside Arab Zawaj."*
- **Profile Summary**: Candidate Photo, Full Name, Age, Location, `★ Mutual Match Accepted` Gold Tag
- **Contact Cards**:
  - 📞 **Phone Number**: Direct verified telephone string
  - ✉️ **Email Address**: Direct verified user email
  - 🔗 **Social / Handle**: Social links & handles
- **Advisory Notice**: `ⓘ Important Note: Our platform does not provide internal chat messaging. You can now contact each other directly using the information above. Please communicate respectfully.`
- **Action Buttons**:
  - `[Back to Profile Dashboard →]` (Primary Orange Button $\rightarrow$ switches to Profile Tab)
  - `[Pause Subscription]` (Outline Dark Button $\rightarrow$ redirects to `/membership-account/`)

---

### Case 5: Declined by Current User (`declined` / `rejected`)
- **Condition**: Current user submitted "Decline Match" on Step 4. Match row status updated to `rejected`.
- **Initial Portal Tab View**: **Step 3 (Status Screen)**

#### Step 3 Status View:
- **Status Icon Bubble**: Danger Soft Red ✕ Bubble
- **Main Heading**: `Match Declined by You`
- **Main Description**: *"You have declined this match recommendation. This profile is now closed."*
- **Your Response Card**: `✕ Declined` (Red Declined Badge)
- **Their Response Card**: `⚪ Waiting` / `—`
- **Next Steps Note**: `Our matchmakers will continue curating fresh potential matches for you in your next matching cycle.`
- **Action Buttons**:
  - `[Back to Profile Dashboard →]` (Primary Orange Button $\rightarrow$ switches to Profile Tab)

---

### Case 6: Declined by Candidate (`their_is_declined`)
- **Condition**: Candidate submitted "Decline Match". Match row status updated to `rejected`.
- **Initial Portal Tab View**: **Step 3 (Status Screen)**

#### Step 3 Status View:
- **Status Icon Bubble**: Danger Soft Red ✕ Bubble
- **Main Heading**: `Match Closed`
- **Main Description**: *"The candidate was unable to proceed with this match recommendation at this time."*
- **Your Response Card**: `✓ Accepted` / `⚪ Pending`
- **Their Response Card**: `✕ Declined` (Red Declined Badge)
- **Next Steps Note**: `Our matchmakers will continue curating fresh potential matches for you in your next matching cycle.`
- **Action Buttons**:
  - `[Back to Profile Dashboard →]` (Primary Orange Button $\rightarrow$ switches to Profile Tab)

---

### Case 7: Match Expired (`is_expired`)
- **Condition**: $N$ days elapsed since `approved_at` without both parties accepting, or status set to `expired`.
- **Initial Portal Tab View**: **Step 3 (Status Screen)**

#### Step 3 Status View:
- **Status Icon Bubble**: Neutral Gray Expiry Clock Bubble
- **Main Heading**: `Match Expired`
- **Main Description**: *"The response window for this match recommendation has ended."*
- **Your Response Card**: `⚪ Expired` (or `✓ Accepted` if user accepted in time)
- **Their Response Card**: `⚪ Expired`
- **Next Steps Note**: `Our matchmakers will prepare new match recommendations for your profile in the upcoming cycle.`
- **Action Buttons**:
  - `[Back to Profile Dashboard →]` (Primary Orange Button $\rightarrow$ switches to Profile Tab)

---

## 4. UI/UX Loading & Dynamic Freshness Specification

1. **Tab Loading Overlay (`.mm-tab-loader`)**:
   - Automatically injected and activated upon tab switching (`switchTab()`) and match response submissions (`submitResponse()`).
   - Uses `rgba(255, 255, 255, 0.78)` background with a lightweight `backdrop-filter: blur(2px)` and brand `#CC723F` CSS spinner.
   - Smooth 0.15s opacity transition without blocking execution or introducing artificial `setTimeout` delays.
2. **Fresh Server-Side Data Fetching**:
   - Every tab click or status transition invokes `MM_Portal.reloadTabAJAX()`, triggering WordPress `wp-admin/admin-ajax.php` action `mm_reload_tab_content`.
   - The server queries database state in real-time (`find_approved_matches_for_user()`, `get_match_stats()`, `get_user_type()`), guaranteeing zero stale cache on responses.
