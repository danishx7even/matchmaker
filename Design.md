# Matchmaker Plugin — Official Design System & Visual Guidelines

This document defines the official design tokens, typography, CSS component classes, status colors, and color balance matrix for the Matchmaker plugin.

---

## 1. Color Palette & Brand Tokens

| Token Name | Hex Value | Primary Application |
| :--- | :--- | :--- |
| **Primary Brand** | `#CC723F` | Primary interactive CTAs, active stepper indicators, active tab badges, brand highlight pills |
| **Primary Hover / Active** | `#b6602f` | Button hover and active states (No green hover colors) |
| **Obsidian Dark / Accent** | `#1D1E20` | High-contrast headings, secondary buttons, dark outline CTAs, table headers, border accents |
| **Warm Canvas / Bg** | `#F8F2ED` | Events tab background, portal wrapper canvas, card subtle striping |
| **Sage Green / Success** | `#829067` | Success badges, `approved` & `matched` status tags |
| **Dark Pine Green** | `#144D34` | Subheadings, deep accent elements |
| **Danger Red** | `#D93025` | Decline buttons, `rejected` & `admin_rejected` status tags |
| **Pure White** | `#FFFFFF` | Form wrapper background, active input fields, modal containers |
| **Deep Charcoal** | `#1F1E1D` | Standard body copy, form descriptions |

---

## 2. Color Balance & Layout Application Matrix

To ensure visual elegance and high contrast, views balance **`#1D1E20`** (Obsidian Dark) and **`#CC723F`** (Warm Primary Ochre):

1. **Where `#1D1E20` is Used**:
   - **Headings & Titles**: H1 page titles, H2 section headings, H3 card headers (`.font-cormorant`, `.mm-events-title`, `.portal-nav-brand`, `.az-user-name`).
   - **Secondary & Dark Action Controls**: `.btn-outline-dark`, `.btn-dark`, table header cells (`thead th` in Admin Pool & Matches Queue).
   - **High-Contrast Accents**: Modal header banners, subtle container borders, card title bars (`.az-card-title`, `.mm-card-header`).
   - **Form Structure**: Step indicator inactive text, field grouping titles.

2. **Where `#CC723F` is Used**:
   - **Primary Action CTAs**: Main call-to-action buttons (`.btn-primary`, `.elementor-button`, "Accept Match", "Submit Profile", "Upgrade Membership").
   - **Active State Highlights**: Active step indicators (`.e-form__indicators__indicator--state-active`), active tab navigation buttons (`.portal-nav-btn.active`).
   - **Badges & Counts**: Unread notification counter badge (`.mm-notif-badge`), match compatibility score pills (`.score-pill`).
   - **Link Hover & Focus**: Interactive link hovers, active outline borders.

---

## 3. Typography Rules

- **Display & Section Headings**: `'Cormorant SC', serif`
  - **H1 / Page Titles**: `32px` · Semi-Bold (600) · `letter-spacing: 1.5px` · Uppercase
  - **H2 / Section Titles**: `22px` · Medium (500) · `letter-spacing: 1px` · Uppercase
  - **H3 / Card Titles**: `18px` · Medium (500) · `letter-spacing: 0.5px`
- **Body & UI Elements**: `'Inter', sans-serif`
  - **Field Labels**: `13px` · Medium (500) / Semi-Bold (600) · Uppercase
  - **Inputs & Selects**: `14px` · Regular (400)
  - **Buttons**: `14px` · Semi-Bold (600) · `letter-spacing: 0.5px`

---

## 4. Match Status Lifecycle & Badge Classes

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

## 5. Mobile & Tablet Responsiveness
- **Canvas Width**: `1100px` max-width with `36px` border radii.
- **Breakpoints**:
  - `@media (max-width: 900px)`: Sidebars stack vertically, dual comparison becomes single-column scroll.
  - `@media (max-width: 768px)`: Padding reduces to `16px`, modal overlays switch to full-width sheet.
  - `@media (max-width: 480px)`: Action buttons expand to full width `100%`, tab navigation enables horizontal scroll.
