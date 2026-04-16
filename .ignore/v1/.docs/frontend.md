# Frontend Documentation

## Design System

### Brand Colors (CSS Variables)

Defined in `:root` in `public/css/shf.css`:

| Variable | Value | Usage |
|----------|-------|-------|
| `--primary-dark` | `#3a3536bf` | Dark gray with transparency |
| `--primary-dark-solid` | `#3a3536` | Solid dark gray (navbar, headers) |
| `--primary-dark-light` | `#3a353696` | Light dark gray (gradient endpoints) |
| `--accent` | `#f15a29` | Primary accent orange |
| `--accent-warm` | `#f47929` | Warm orange (gradient endpoints) |
| `--accent-light` | `#f99d3e` | Light accent (badge text) |
| `--accent-dim` | `rgba(241, 90, 41, 0.10)` | Accent tint for backgrounds/hover |
| `--bg` | `#f8f8f8` | Page background |
| `--bg-alt` | `#e6e7e8` | Alternate background |
| `--text` | `#1a1a1a` | Primary text |
| `--text-muted` | `#6b7280` | Muted/secondary text |
| `--border` | `#bcbec0` | Default border color |
| `--white` | `#ffffff` | White |
| `--red` | `#c0392b` | Error/danger |
| `--green` | `#27ae60` | Success |
| `--radius` | `10px` | Default border radius |
| `--shadow` | `0 1px 3px ...` | Standard box shadow |
| `--shadow-lg` | `0 4px 24px ...` | Large box shadow |

### Font Size Scale (CSS Variables)

| Variable | Value | Usage |
|----------|-------|-------|
| `--shf-text-2xs` | `0.65rem` (10.4px) | Badges, tiny labels |
| `--shf-text-xs` | `0.75rem` (12px) | Secondary labels, hints |
| `--shf-text-sm` | `0.8rem` (12.8px) | Form labels, small text |
| `--shf-text-base` | `0.875rem` (14px) | Body text default |
| `--shf-text-md` | `0.95rem` (15.2px) | Slightly emphasized |
| `--shf-text-lg` | `1.1rem` (17.6px) | Section headings |
| `--shf-text-xl` | `1.25rem` (20px) | Page titles |

### Fonts

- **Jost** (display font): Used for headings, nav links, section titles, buttons. Weights: 400, 500, 600, 700.
- **Archivo** (body font): Used for body text, forms, labels. Weights: 400, 500, 600.
- All served as local woff2 files from `/fonts/` directory.
- Applied via: `body { font-family: 'Archivo', sans-serif; }` and `.font-display { font-family: 'Jost', sans-serif; }`

---

## CSS Classes Reference (`shf-` prefix)

All custom classes in `public/css/shf.css`. **Never use Tailwind. Always use these.**

### Typography

| Class | Description |
|-------|-------------|
| `.shf-text-2xs` | Font size 0.65rem |
| `.shf-text-xs` | Font size 0.75rem |
| `.shf-text-sm` | Font size 0.8rem |
| `.shf-text-base` | Font size 0.875rem |
| `.shf-text-md` | Font size 0.95rem |
| `.shf-text-lg` | Font size 1.1rem |
| `.shf-text-xl` | Font size 1.25rem |
| `.font-display` | Jost font family |
| `.font-body` | Archivo font family |

### Layout / Sections

| Class | Description |
|-------|-------------|
| `.shf-section` | White card container with border + shadow + border-radius |
| `.shf-section-header` | Dark gradient header bar (flex, centered) |
| `.shf-section-number` | Orange circle with number (24x24px) |
| `.shf-section-title` | White title text (Jost, 0.95rem, 600 weight) |
| `.shf-section-body` | Content area with 20px padding |
| `.shf-page-header` | Full-width dark gradient header with flex layout |
| `.shf-card` | White card with border, radius, shadow |

### Buttons

| Class | Description |
|-------|-------------|
| `.btn-accent` | Orange gradient pill button (primary CTA) |
| `.btn-accent-sm` | Smaller orange gradient pill button |
| `.btn-accent-outline` | Orange outline pill button (`color: var(--accent)`, NOT white) |
| `.btn-accent-outline-white` | White outline pill for dark backgrounds |
| `.shf-header-back` | Back arrow link for dark header backgrounds |
| `.shf-btn-icon` | SVG icon alignment inside Bootstrap `.btn` |

### Badges

| Class | Description |
|-------|-------------|
| `.shf-badge` | Base badge (inline-flex, pill, 0.7rem, 600 weight) |
| `.shf-badge-orange` | Orange accent badge |
| `.shf-badge-blue` | Blue badge |
| `.shf-badge-gray` | Gray badge |
| `.shf-badge-green` | Green badge |
| `.shf-badge-purple` | Purple badge |
| `.shf-badge-red` | Red badge |
| `.shf-badge-username` | Navbar username badge (transparent bg, white text) |

#### Stage Badges (Loan listing)

| Class | Stage |
|-------|-------|
| `.shf-badge-stage-inquiry` | Inquiry |
| `.shf-badge-stage-doc-selection` | Document Selection |
| `.shf-badge-stage-doc-collection` | Document Collection |
| `.shf-badge-stage-app-number` | App Number |
| `.shf-badge-stage-bsm-osv` | BSM/OSV |
| `.shf-badge-stage-legal` | Legal |
| `.shf-badge-stage-valuation` | Valuation |
| `.shf-badge-stage-sanction-decision` | Sanction Decision |
| `.shf-badge-stage-rate-pf` | Rate & PF |
| `.shf-badge-stage-sanction` | Sanction |
| `.shf-badge-stage-docket` | Docket |
| `.shf-badge-stage-kfs` | KFS |
| `.shf-badge-stage-esign` | E-Sign |
| `.shf-badge-stage-disbursement` | Disbursement |
| `.shf-badge-stage-otc` | OTC |

### Tags / Chips

| Class | Description |
|-------|-------------|
| `.shf-tag` | Inline pill tag (accent-dim bg, accent border) |
| `.shf-tag-remove` | Small round remove button inside tag |
| `.shf-chip` | Selection chip (toggleable, becomes accent on `:checked`/`.active`) |

### Forms

| Class | Description |
|-------|-------------|
| `.shf-form-label` | Uppercase label (0.7rem, 600 weight, letter-spaced) |
| `.shf-input` | Standard text input (8px 12px padding, 0.88rem, accent focus ring) |
| `.shf-input-sm` | Smaller text input (5px 10px padding, 0.8rem) |
| `.shf-input.is-invalid` | Red border + red focus ring on invalid |
| `.shf-checkbox` | Accent-colored checkbox (18x18px) |
| `.shf-toggle` | Custom toggle switch (36x20px, accent when checked) |
| `.shf-datepicker` | Class used to init Bootstrap Datepicker on inputs |
| `.shf-per-page` | Per-page selector wrapper (inline-flex, accent border) |
| `.shf-per-page-total` | Total count text inside per-page |

Note: `select.shf-input` and `select.shf-input-sm` get custom dropdown arrow via `background-image`. `textarea.shf-input` gets `min-height: 4.5rem` and `resize: vertical`.

### Tables

| Class | Description |
|-------|-------------|
| `.table` | Bootstrap base table (0.85rem font, Jost headers, uppercase) |
| `.table-hover` | Accent-dim hover background |
| `.shf-table-mobile` | Mobile-responsive card layout (hides `<thead>`, uses `data-label`) |

### Tabs

| Class | Description |
|-------|-------------|
| `.shf-tabs` | Tab container (dark bg, horizontal scroll, flex) |
| `.shf-tab` | Individual tab (Jost, 0.8rem, bottom border on `.active`) |
| `.shf-tab.active` | Active tab (white text, accent bottom border) |
| `.shf-tab-close` | Close button styling |

### Navigation

| Class | Description |
|-------|-------------|
| `.shf-nav-link` | Navbar link (60% white, Jost, bottom border transition) |
| `.shf-nav-active` | Active navbar link (white, accent bottom border) |
| `.shf-text-white-70` | `color: rgba(255,255,255,0.7)` (used in nav) |
| `.shf-icon-sm` | Small icon size for nav |
| `.shf-icon-md` | Medium icon size |
| `.shf-header-icon` | Icon in page header |
| `.shf-page-title` | Page title in header |

### Toast / Flash Messages

| Class | Description |
|-------|-------------|
| `.shf-toast-wrapper` | Fixed bottom-right container (z-index: 999) |
| `.shf-toast` | Toast notification (dark bg, flex, animated) |
| `.shf-toast.success` | Green left border |
| `.shf-toast.error` | Red left border |
| `.shf-toast.warning` | Amber left border |
| `.shf-toast-close` | Close button inside toast |

### Stats Cards

| Class | Description |
|-------|-------------|
| `.shf-stat-card` | Stats card (white, accent left border, flex) |
| `.shf-stat-icon` | Circular icon container (40x40, accent-dim bg) |
| `.shf-stat-value` | Large stat number (Jost, 1.5rem, 700 weight) |
| `.shf-stat-label` | Small label below value (0.75rem, muted) |

### Document Collection (Loan System)

| Class | Description |
|-------|-------------|
| `.shf-doc-grid` | 2-column CSS grid for documents |
| `.shf-doc-item` | Document item row (clickable, with hover) |
| `.shf-doc-item.checked` | Checked document (accent-dim bg, accent border) |
| `.shf-doc-received` | Green left border + green bg |
| `.shf-doc-pending` | Gray left border |
| `.shf-doc-rejected` | Red left border + red bg |
| `.shf-doc-actions` | Action buttons, fade in on hover |
| `.doc-sortable-ghost` | SortableJS drag ghost style |
| `.doc-drag-handle` | Drag handle (accent on hover) |

### Loan Stages (Workflow)

| Class | Description |
|-------|-------------|
| `.shf-stage-pending` | Secondary left border (4px) |
| `.shf-stage-in-progress` | Primary/blue left border (4px) |
| `.shf-stage-completed` | Success/green left border (4px) |
| `.shf-stage-rejected` | Danger/red left border (4px) |
| `.shf-stage-skipped` | Warning/yellow left border (4px) |
| `.shf-remark-item` | Remark/comment item with bottom border |

### Pagination

| Class | Description |
|-------|-------------|
| `.shf-pagination` | Pagination wrapper (hides first div, accent active page) |

### DataTables Overrides

| Class | Description |
|-------|-------------|
| `.shf-dt-top` | Top bar wrapper (length selector) |
| `.shf-dt-bottom` | Bottom bar wrapper (info + pagination) |
| `.dataTables_filter` | Hidden (custom filters used instead) |
| `.no-sort` | Non-sortable column header |

### Datepicker Overrides

Bootstrap Datepicker is themed to use accent colors for active/today states, body font, and SHF shadow/radius.

---

## JavaScript Modules

### `public/js/shf-app.js`

Core jQuery-based utilities, loaded on every page via `layouts/app.blade.php`.

| Feature | Description |
|---------|-------------|
| **HTML5 validation disable** | `$('form').attr('novalidate', 'novalidate')` — all validation is server-side |
| **Radio auto-check** | Clicking a radio auto-checks its adjacent checkbox (multi-select with default pattern) |
| **Textarea auto-expand** | Fallback for browsers without `field-sizing: content` CSS support |
| **Toast auto-dismiss** | Fades in toasts, auto-removes after `data-auto-dismiss` ms (default 5000) |
| **Toast close** | `.shf-toast-close` click handler |
| **Password toggle** | `.shf-password-toggle` with `data-target` — toggles password/text input type |
| **Saved message fade** | `.shf-saved-msg` — fades out after 2s |
| **Modal auto-show** | `[data-bs-show-on-load="true"]` — opens Bootstrap modal on page load (for validation errors) |
| **Delete confirmation** | `.shf-confirm-delete` form submit — SweetAlert2 confirmation dialog |
| **Collapsible sections** | `.shf-collapsible[data-target]` — slideToggle with arrow rotation and filter count badge |
| **Filter collapse on mobile** | `window.shfCollapseFiltersOnMobile()` — auto-collapse filters below 768px |
| **Filter expand on desktop** | Filters start expanded at >= 768px |

### `public/js/shf-loans.js`

Loan workflow module, loaded via `@push('scripts')` on loan pages.

| Feature | Description |
|---------|-------------|
| `SHFLoans.csrfToken` | Reads CSRF token from meta tag |
| `SHFLoans.initProductDropdown()` | Cascading bank/product dropdown — filters products when bank changes |
| `SHFLoans.showToast(message, type)` | Shows temporary Bootstrap alert toast (success/error/info) |
| `SHFLoans.init()` | Initializes all loan-page behaviors on `$(document).ready()` |

### Other JS Files

| File | Description |
|------|-------------|
| `public/js/offline-manager.js` | IndexedDB-based offline queue + sync (OfflineManager) |
| `public/js/pdf-renderer.js` | Client-side PDF rendering via print dialog (offline fallback) |
| Service Worker (`/sw.js`) | PWA service worker registration |

---

## Vendor Dependencies

All served locally from `public/vendor/` (no CDN, no build step).

| Library | Version | Files |
|---------|---------|-------|
| **Bootstrap** | 5.3 | `vendor/bootstrap/css/bootstrap.min.css`, `vendor/bootstrap/js/bootstrap.bundle.min.js` |
| **jQuery** | 3.7.1 | `vendor/jquery/jquery-3.7.1.min.js` |
| **Bootstrap Datepicker** | — | `vendor/datepicker/css/bootstrap-datepicker3.min.css`, `vendor/datepicker/js/bootstrap-datepicker.min.js` |
| **DataTables** | — | `vendor/datatables/css/dataTables.bootstrap5.min.css`, `vendor/datatables/js/*.min.js` |
| **SweetAlert2** | — | `vendor/sweetalert2/sweetalert2.min.css`, `vendor/sweetalert2/sweetalert2.all.min.js` |
| **SortableJS** | — | `vendor/sortablejs/Sortable.min.js` |

### Load Order (in `layouts/app.blade.php`)

1. CSS: Bootstrap CSS -> Datepicker CSS -> `shf.css` -> SweetAlert2 CSS -> `@stack('styles')`
2. JS: jQuery -> Bootstrap Bundle -> Datepicker -> SortableJS -> SweetAlert2 -> `shf-app.js` -> `@stack('scripts')` -> `offline-manager.js` -> `pdf-renderer.js` -> Service Worker

---

## Responsive Breakpoints

| Breakpoint | Width | Usage |
|------------|-------|-------|
| **Desktop** | >= 992px (`lg`) | Full navbar expanded, all nav items visible |
| **Tablet** | <= 768px (`md`) | Reduced padding, smaller fonts, doc grid 1-col, filters collapse, `shf-dt-top` hidden |
| **Mobile** | <= 575.98px (`sm`) | Document items wrap, actions full-width |
| **Small phone** | <= 480px | Minimal padding, smallest font sizes |

### Responsive Patterns

- **Navbar**: `navbar-expand-lg` (992px). Desktop nav uses `d-none d-lg-flex`, mobile nav uses `d-lg-none`.
- **Filters**: `col-6 col-md-auto` — pair on mobile, auto-width on desktop.
- **Tables 5+ columns**: Dual layout — desktop table (`d-none d-md-block`) + mobile card (`d-md-none`) using `.shf-table-mobile` with `data-label` attributes.
- **Datepicker inputs**: Never use native `<input type="date">` — always Bootstrap Datepicker with class `.shf-datepicker`.

---

## Layout Structure

### `layouts/app.blade.php` (Authenticated)

```
<!DOCTYPE html>
<html>
<head>
    - Meta tags (charset, viewport, csrf-token)
    - Favicons (ico, png 16/32/96, apple-touch-icon)
    - PWA manifest + theme-color meta
    - CSS: Bootstrap -> Datepicker -> shf.css -> SweetAlert2
    - @stack('styles')
</head>
<body class="font-body bg-body-tertiary">
    <div class="min-vh-100">
        @include('layouts.navigation')

        @hasSection('header')
            <header> @yield('header') </header>
        @endif

        <!-- Flash toasts: success, error, warning -->

        <main> @yield('content') </main>
    </div>

    <!-- PWA Install Banner -->
    <!-- Offline Status Banner -->

    JS: jQuery -> Bootstrap -> Datepicker -> SortableJS -> SweetAlert2 -> shf-app.js
    @stack('scripts')
    offline-manager.js -> pdf-renderer.js -> SW registration + PWA install logic
</body>
</html>
```

**Available sections/stacks:**
- `@section('header')` — Page heading (optional, dark gradient)
- `@section('content')` — Main page content
- `@push('styles')` — Additional CSS
- `@push('scripts')` — Additional JS (loaded after vendor scripts)

### `layouts/guest.blade.php` (Unauthenticated)

Centered card layout on dark background. Only loads Bootstrap CSS + `shf.css` + jQuery + Bootstrap JS + `shf-app.js`. No datepicker, no SweetAlert2, no DataTables.

**Available sections:**
- `@section('content')` — Card content

---

## Navigation Structure

### Desktop (>= 992px)

Horizontal navbar with:
- **Logo** (left)
- **Nav links** (left, `d-none d-lg-flex`): Dashboard, New Quotation, Loans, Users, Quotation Settings, Loan Settings, Roles, Activity Log
- **Right section** (`d-none d-lg-flex`): Impersonation banner/button, Notification bell (with badge), Role badge, User dropdown (Profile, Log Out)

### Mobile (< 992px)

Collapsible hamburger menu (`#shfNavbar`) with:
- Same nav links as vertical list
- User info section at bottom (name, email, role badge, Profile, Log Out)

### Permission-Gated Links

| Link | Permission/Check |
|------|-----------------|
| New Quotation | `create_quotation` |
| Loans | `view_loans` |
| Users | `view_users` |
| Quotation Settings | `view_settings` |
| Loan Settings | `manage_workflow_config` |
| Roles | `isSuperAdmin()` |
| Activity Log | `view_activity_log` |

### Notification Badge Polling

JavaScript in `navigation.blade.php` polls `api.notifications.count` every 60 seconds. Badge shows count (max "99+") or hides when 0.

### Impersonation

Desktop has a dropdown with search-as-you-type user finder. Uses SweetAlert2 confirmation before impersonating. Only visible to users with `@canImpersonate` and when not already impersonating. Impersonation banner shown when active.
