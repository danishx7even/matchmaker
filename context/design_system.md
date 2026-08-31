# Feature Context: Official Design System & Visual Tokens

This document defines the official design tokens, typography, CSS component classes, status colors, and responsive breakpoints for the Matchmaker plugin.

---

## 1. Color Palette & Brand Tokens

| Token Name | Hex Value | Primary Application |
| :--- | :--- | :--- |
| **Primary Brand** | `#CC723F` | Primary CTAs, active stepper indicators, active tab underlines, brand highlights |
| **Primary Hover** | `#b6602f` | Button hover and active states (No green hover colors) |
| **Warm Canvas / Bg** | `#F8F2ED` | Card containers, section backgrounds, subtle zebra striping |
| **Sage Green / Success** | `#829067` | Success badges, `approved` & `matched` status tags |
| **Dark Pine Green** | `#144D34` | Subheadings, deep accent elements |
| **Danger Red** | `#D93025` | Decline buttons, `rejected` & `admin_rejected` status tags |
| **Pure White** | `#FFFFFF` | Form wrapper background, active input fields, modal containers |
| **Deep Charcoal** | `#1F1E1D` | Primary headings, body copy, form labels |

---

## 2. Typography Rules

- **Display & Section Headings**: `'Cormorant SC', serif`
  - **H1 / Page Titles**: `32px` · Semi-Bold (600) · `letter-spacing: 1.5px` · Uppercase
  - **H2 / Section Titles**: `22px` · Medium (500) · `letter-spacing: 1px` · Uppercase
  - **H3 / Card Titles**: `18px` · Medium (500) · `letter-spacing: 0.5px`
- **Body & UI Elements**: `'Inter', sans-serif`
  - **Field Labels**: `13px` · Medium (500) / Semi-Bold (600) · Uppercase
  - **Inputs & Selects**: `14px` · Regular (400)
  - **Buttons**: `14px` · Semi-Bold (600) · `letter-spacing: 0.5px`

---

## 3. Match Status Lifecycle & Badge Classes

```css
/* Pending Review (Yellow/Gold) */
.mm-badge-pending, .mm-status-pending_review {
    background: #FFF8E6;
    color: #B78103;
    border: 1px solid #FFE27D;
}

/* Approved / Matched (Sage Green) */
.mm-badge-approved, .mm-status-approved, .mm-status-matched {
    background: #F0F4EC;
    color: #829067;
    border: 1px solid #829067;
}

/* Rejected / Admin Rejected (Red) */
.mm-badge-rejected, .mm-status-rejected, .mm-status-admin_rejected {
    background: #FDF3F2;
    color: #D93025;
    border: 1px solid #F8B4AF;
}
```

---

## 4. Mobile & Tablet Responsiveness
- **Canvas Width**: `1100px` max-width with `36px` border radii.
- **Breakpoints**:
  - `@media (max-width: 900px)`: Sidebars stack vertically, dual comparison becomes single-column scroll.
  - `@media (max-width: 768px)`: Padding reduces to `16px`, modal overlays switch to full-width sheet.
  - `@media (max-width: 480px)`: Action buttons expand to full width `100%`, tab navigation enables horizontal scroll.
