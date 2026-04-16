# Frontend

## Stack

- **CSS:** Bootstrap 5.3 + custom `shf.css` (local vendor, no build step)
- **JS:** jQuery 3.7.1 + custom `shf-app.js` / `shf-loans.js` (local vendor)
- **Icons:** SVG inline (no icon library)
- **Fonts:** Jost (headings), Archivo (body) — local woff2 files
- **Vendor:** bootstrap, datatables, datepicker, jquery, leaflet, sortablejs, sweetalert2

All vendor files in `public/vendor/`. Loaded globally in `layouts/app.blade.php`.

## CSS Design System (`public/css/shf.css`)

### Custom Properties (`:root`)
| Variable | Value | Usage |
|----------|-------|-------|
| `--primary-dark` | #3a3536bf | Semi-transparent dark |
| `--primary-dark-solid` | #3a3536 | Solid dark |
| `--accent` | #f15a29 | Orange accent |
| `--accent-warm` | #f47929 | Warm orange |
| `--accent-light` | #f99d3e | Light orange |
| `--accent-dim` | rgba(241,90,41,0.10) | Subtle orange bg |
| `--bg` | #f8f8f8 | Page background |
| `--bg-alt` | #e6e7e8 | Alt background |
| `--text` | #1a1a1a | Body text |
| `--text-muted` | #6b7280 | Muted text |
| `--border` | #bcbec0 | Border color |
| `--red` | #c0392b | Error/danger |
| `--green` | #27ae60 | Success |
| `--radius` | 10px | Border radius |

### Font Scale
`--shf-text-2xs` (0.65rem) → `--shf-text-xs` (0.75rem) → `--shf-text-sm` (0.8rem) → `--shf-text-base` (0.875rem) → `--shf-text-md` (0.95rem) → `--shf-text-lg` (1.1rem) → `--shf-text-xl` (1.25rem)

Utility classes: `shf-text-2xs` through `shf-text-xl`

### Prefix Convention
All custom CSS classes use `shf-` prefix.

### Key Component Classes

**Layout:** shf-section, shf-section-header, shf-section-body, shf-section-number, shf-section-title, shf-page-header, shf-page-title, shf-card

**Buttons:** btn-accent, btn-accent-sm, btn-accent-outline, btn-accent-outline-white, shf-btn-success, shf-btn-warning, shf-btn-danger, shf-btn-gray, shf-btn-minimal

**Forms:** shf-form-label, shf-input, shf-input-sm, shf-input-readonly, shf-amount-input, shf-amount-wrap, shf-amount-raw, shf-select-compact, shf-validation-error

**Badges:** shf-badge + color variants (orange, blue, gray, green, purple, red), shf-badge-stage-* (per-stage colors)

**Stats:** shf-stat-card, shf-stat-border-blue/green/accent/warning, shf-stat-icon-*

**Tabs:** shf-tabs, shf-tab, shf-tab-pane-hidden

**Tables:** Bootstrap classes with SHF overrides (Jost headers, accent hover), shf-table-mobile for card conversion

**Documents:** shf-doc-grid, shf-doc-item, shf-doc-received/pending/rejected

**Stages:** shf-stage-pending/in-progress/completed/rejected/skipped, shf-stage-dot, shf-phase-pill

**Icons:** shf-icon-2xs through shf-icon-xl

**Max widths:** shf-max-w-sm (28rem), shf-max-w-md (42rem), shf-max-w-lg (48rem), shf-max-w-xl (56rem)

## Responsive Design

### Breakpoints
- Navbar: `navbar-expand-xl` (1200px)
- Filters: `col-6 col-md-auto`
- Tables 5+ cols: dual layout — desktop table (`d-none d-md-block`) + mobile cards (`d-md-none`)

### Mobile Cards
Built from DataTable `drawCallback` using `shf-card` class. DataTable hidden on mobile via `shf-dt-section table.dataTable` display rule.

### Filter Collapse
Filters auto-collapse on mobile (`shf-filter-body-collapse`), expanded on desktop via JS.

## JavaScript

### SHF Namespace (`shf-app.js`)
| Function | Purpose |
|----------|---------|
| `SHF.validateForm($form, rules)` | Client-side validation |
| `SHF.validateBeforeAjax($container, rules, url, data)` | Validate then AJAX POST |
| `SHF.formatIndianNumber(num)` | Indian comma system |
| `SHF.numberToWordsEn(num)` | English number words |
| `SHF.numberToWordsGu(num)` | Gujarati number words |
| `SHF.bilingualAmountWords(num)` | "English / Gujarati" |
| `SHF.initAmountFields()` | Init `.shf-amount-input` |

### Validation Rules
```javascript
{
    fieldName: {
        required: bool, maxlength: int, minlength: int,
        min: number, max: number, email: bool, numeric: bool,
        pattern: RegExp, patternMsg: string, dateFormat: 'd/m/Y',
        custom: function(val, $field, $form) { return errorMsg | null; }
    }
}
```

### Auto-Behaviors (shf-app.js)
- Form novalidate on all forms
- Toast auto-dismiss with fade animation
- Password toggle (`.shf-password-toggle[data-target]`)
- Modal auto-show (`data-bs-show-on-load="true"`)
- SweetAlert confirm delete (`.shf-confirm-delete`)
- Collapsible sections (`.shf-collapsible[data-target]`)
- Amount field auto-init with Indian formatting
- Auto-expand textareas

### SHFLoans Namespace (`shf-loans.js`)
- `SHFLoans.initProductDropdown()` — bank-dependent product filtering
- `SHFLoans.showToast(message, type)` — loan-page toast
- `SHFLoans.init()` — called on ready

### DataTable Pattern
```javascript
dom: 'rt<"shf-dt-bottom"ip>'
// Wrap in shf-section shf-dt-section
// Empty state via drawCallback
// Mobile cards built in drawCallback
```

### Dates
- Bootstrap Datepicker (loaded globally, `vendor/datepicker/`)
- Do NOT load datepicker JS again in views
- Do NOT use native `<input type="date">`
