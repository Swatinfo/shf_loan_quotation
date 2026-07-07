# Frontend Design System

## Overview

The frontend uses **Bootstrap 5.3 + jQuery 3.7** with local vendor files (no CDN, no build step). All custom styles use the `shf-` prefix. No Vite, Webpack, or npm build process — assets served directly from `public/`.

## File Structure

```
public/
├── css/
│   ├── shf.css              # Main design system (all authenticated pages)
│   ├── common.css            # Shared styles for legacy pages
│   ├── config.css            # Config/settings page styles (legacy)
│   └── index.css             # Quotation builder styles (legacy)
├── js/
│   ├── shf-app.js            # Core jQuery utilities (toasts, password toggle, modals)
│   ├── shf-loans.js          # Loan module interactions (stages, disbursement, transfers)
│   ├── pdf-renderer.js       # Client-side PDF rendering (offline mode)
│   ├── config-loader.js      # Async config fetcher with IndexedDB cache
│   ├── config-defaults.js    # Hardcoded default configuration
│   ├── config-translations.js # Bilingual labels + number-to-words
│   ├── offline-manager.js    # IndexedDB sync manager
│   └── password-ui.js        # Password strength meter + validation
├── vendor/
│   ├── bootstrap/            # Bootstrap 5.3 CSS + JS bundle
│   ├── jquery/               # jQuery 3.7.1
│   ├── datatables/           # jQuery DataTables
│   ├── sortable/             # Sortable.js (drag-and-drop)
│   └── bootstrap-datepicker/ # Date picker widget
├── fonts/
│   ├── Jost-*.woff2          # Display font
│   ├── Archivo-*.woff2       # Body font
│   └── NotoSansGujarati-*.ttf # Gujarati font
└── images/
    └── logo3.png             # Company logo
```

## Color System

### CSS Custom Properties (defined in shf.css)

```css
:root {
    --primary-dark: #3a3536;    /* Dark gray — navbar, headers, dark backgrounds */
    --accent: #f15a29;          /* Orange — buttons, links, active states */
    --accent-light: #ff7043;    /* Light orange — hover states */
    --accent-warm: #fff3e0;     /* Warm orange tint — subtle backgrounds */
    --accent-dim: #fef0eb;      /* Dimmed orange — table group headers */
    --bg-light: #f8f8f8;        /* Page background */
    --text-dark: #1a1a1a;       /* Primary text */
    --text-muted: #6c757d;      /* Secondary text */
    --border-color: #dee2e6;    /* Borders */
    --error-red: #c0392b;       /* Error states */
    --success-green: #27ae60;   /* Success states */
}
```

### Badge Color Classes
| Class | Color | Used For |
|-------|-------|----------|
| `shf-badge-green` | Green | Proprietor type |
| `shf-badge-blue` | Blue | Partnership/LLP type, Admin role |
| `shf-badge-orange` | Orange | Pvt Ltd type, Super Admin role |
| `shf-badge-purple` | Purple | Salaried type |
| `shf-badge-gray` | Gray | "All" type, Office Employee role |

## Typography

### Fonts (loaded via @font-face in shf.css)
| Font | Usage | Class |
|------|-------|-------|
| Jost | Headings, display text | `font-display` |
| Archivo | Body text, form inputs | `font-body` (default) |
| Noto Sans Gujarati | Gujarati text in PDFs | Embedded in PDF template |

All fonts loaded from local `public/fonts/` woff2 files — no external requests.

## Component Classes

### Layout Components

**Section** — Primary content container with numbered header:
```html
<div class="shf-section">
    <div class="shf-section-header">
        <span class="shf-section-number">1</span>
        <h3 class="shf-section-title">Section Title</h3>
    </div>
    <div class="shf-section-body">
        <!-- Content -->
    </div>
</div>
```

**Card** — Simple container:
```html
<div class="shf-card">
    <!-- Content -->
</div>
```

### Buttons

| Class | Appearance | Usage |
|-------|-----------|-------|
| `btn-accent` | Solid orange | Primary actions (Create, Save, Generate) |
| `btn-accent-sm` | Small solid orange | Table row actions |
| `btn-accent-outline` | Orange border, orange text | Secondary actions |
| `btn-accent-outline-white` | Orange border, white text | On dark backgrounds |

### Forms

```html
<label class="shf-form-label">Field Label</label>
<input type="text" class="shf-input">
<input type="date" class="shf-datepicker">
```

### Tables

Use Bootstrap's built-in classes — NOT custom `shf-table`:
```html
<table class="table table-hover">
    <thead>...</thead>
    <tbody>...</tbody>
</table>
```

**Lesson**: Use `table table-hover` for all tables. No dark gradient headers or shadow backgrounds.

### Badges

```html
<span class="shf-badge shf-badge-green">Active</span>
<span class="shf-badge shf-badge-orange">Super Admin</span>
```

### Tabs

```html
<div class="shf-tabs">
    <button class="shf-tab active">Tab 1</button>
    <button class="shf-tab">Tab 2</button>
</div>
```

### Stat Cards (Dashboard)

```html
<div class="shf-stat-card">
    <div class="shf-stat-icon"><!-- SVG icon --></div>
    <div class="shf-stat-value">{{ $count }}</div>
    <div class="shf-stat-label">Label</div>
</div>
```

### Toast Messages

Auto-dismissed notifications (driven by shf-app.js):
```html
<div class="shf-toast-wrapper">
    <div class="shf-toast shf-toast-success">
        Message text
        <button class="shf-toast-close">&times;</button>
    </div>
</div>
```

### Password Toggle

```html
<div class="position-relative">
    <input type="password" class="shf-input">
    <button type="button" class="shf-password-toggle">
        <span class="shf-eye-open"><!-- SVG --></span>
        <span class="shf-eye-closed" style="display:none"><!-- SVG --></span>
    </button>
</div>
```

## JavaScript Modules

### shf-app.js — Core Utilities
- Toast auto-dismiss after 5 seconds
- Toast close button handler
- Password visibility toggle (eye icon)
- "Saved" message fade-out after 2 seconds
- Modal auto-show for validation errors

### pdf-renderer.js — Client-Side PDF
- Mirrors server-side `PdfGenerationService::renderHtml()` in JavaScript
- Generates complete HTML document with embedded fonts
- Uses `Blob URL + window.print()` for PDF save
- iOS fallback: downloads `.html` file
- Sections: header, customer details, documents, EMI tables, charges table

### config-loader.js — Async Config
- Fetches from `GET /api/config/public`
- Falls back to IndexedDB cache (offline)
- Falls back to hardcoded defaults (`config-defaults.js`)
- Saves to IndexedDB for future offline use
- Merges fetched data with defaults
- Sorts tenures ascending

### config-defaults.js — Hardcoded Defaults
- Company info, banks list, tenures, IOM charges, GST
- Document lists per customer type (EN + GU)
- Used as last-resort fallback when both API and IndexedDB fail

### config-translations.js — Bilingual System
- All PDF/UI labels in English + Gujarati
- Customer type labels (bilingual)
- `numberToEnglishWords()` — Indian numbering system
- `numberToGujaratiWords()` — Gujarati script
- Helper: `getBilingualTypeLabel(type)`
- Helper: `getBilingualDocName(enName, guName)`
- Helper: `getBilingualAmountWords(amount)`

### shf-loans.js — Loan Module Interactions
- Stage status updates with SweetAlert2 confirmations
- Stage transfer modal handling
- Query raise/respond/resolve UI
- Disbursement form (decision tree: fund_transfer/cheque/demand_draft)
- OTC clearance handling
- Stage notes save (CIBIL, rate/PF, sanction, docket, KFS, e-sign)
- Loan status updates (complete, reject, cancel, on_hold)
- Dependent dropdowns (bank→product)

### password-ui.js — Password Management
- Strength meter: 5 levels (weak → strong)
- Policy checklist: length >= 8, uppercase, lowercase, digit, special char
- Penalties: letters/digits only, repeating chars, common passwords
- Confirmation matching indicator
- Toggle visibility (eye icons)

### offline-manager.js — IndexedDB Sync
See [offline-pwa.md](offline-pwa.md) for full details.

## Responsive Design Patterns

### Breakpoints (Bootstrap defaults)
| Breakpoint | Width | Usage |
|-----------|-------|-------|
| sm | 576px | — |
| md | 768px | Table/card toggle |
| lg | 992px | Navbar collapse |
| xl | 1200px | — |

### Key Patterns

**Navbar**: Use `navbar-expand-lg` (992px), NOT `navbar-expand-sm`. All visibility classes must use `d-lg-*`.

**Tables with 5+ columns**: Dual layout pattern:
```html
<!-- Desktop table -->
<div class="d-none d-md-block">
    <table class="table table-hover">...</table>
</div>
<!-- Mobile card layout -->
<div class="d-md-none">
    @foreach($items as $item)
    <div class="shf-card mb-2">...</div>
    @endforeach
</div>
```

**Filter forms**: Use `col-6 col-md-auto` — fields pair on mobile, auto-width on desktop.

**Padding**: `px-3 px-sm-4 px-lg-5` for responsive horizontal padding.

## View Pattern

All views use the `@extends` / `@section` pattern (NOT Blade component slots):

```blade
@extends('layouts.app')

@section('header')
    <h2>Page Title</h2>
@endsection

@section('content')
    <!-- Page content -->
@endsection

@push('styles')
    <!-- Additional CSS -->
@endpush

@push('scripts')
    <!-- Additional JS -->
@endpush
```

**Never use**: `<x-app-layout>`, `{{ $slot }}`, or Blade component wrappers.

## Vendor Libraries

| Library | Version | Path | Usage |
|---------|---------|------|-------|
| Bootstrap | 5.3 | `public/vendor/bootstrap/` | Grid, components, utilities |
| jQuery | 3.7.1 | `public/vendor/jquery/` | DOM manipulation, AJAX |
| DataTables | — | `public/vendor/datatables/` | Dashboard quotation table |
| Sortable.js | — | `public/vendor/sortable/` | Drag-and-drop in settings |
| Bootstrap Datepicker | — | `public/vendor/bootstrap-datepicker/` | Date inputs |

**Note**: All vendor files are local. However, **SweetAlert2** is loaded from CDN (`https://cdn.jsdelivr.net/npm/sweetalert2@11`) in `layouts/app.blade.php`. This is the only external dependency and requires internet connectivity for confirmation dialogs in the loan module.
