# Coding Feedback & Preferences

Consolidated rules from past sessions. Follow these in all future work.

## Frontend Stack
- **Bootstrap 5.3 + jQuery 3.7** -- local vendor files, no build step
- Newtheme is the only theme. All views live under `resources/views/newtheme/`.
- Vendor libraries loaded globally in `newtheme/layouts/app.blade.php`: jQuery, Bootstrap Datepicker, SortableJS, SweetAlert2, `shf-newtheme.js`, `push-notifications.js`
- Vendor directory (`public/newtheme/vendor/`): bootstrap, datepicker, jquery, leaflet, sortablejs, sweetalert2
- Newtheme design-system CSS: `public/newtheme/assets/shf.css`, `shf-extras.css`, `shf-workflow.css`, `shf-modals.css`
- Legacy `shf-*` CSS preserved at `public/newtheme/css/shf.css` for screens that still render legacy markup (loan stages, documents, valuation, quotation show, settings)
- Core JS in `public/newtheme/js/shf-app.js` (SHF.* namespace, quotation create form only)
- `shf-` prefix for legacy custom CSS classes. Newtheme CSS uses page-scoped classnames (see `public/newtheme/pages/*.css`).
- **Archived pre-newtheme source**: `.ignore/old_code_backup/` (tracked in git for restore)

## Responsive Design
- **Navbar**: `navbar-expand-xl` (1200px breakpoint) -- all desktop nav visibility uses `d-xl-*` classes. Hamburger visible on tablet.
- **Filters**: `col-6 col-md-auto` pattern -- pair on mobile, auto-width on desktop
- **Tables 5+ columns**: Dual layout -- desktop table (`d-none d-md-block`) + mobile card (`d-md-none`)
- **Mobile cards**: Built from DataTable `drawCallback` using `shf-card` class
- **Filter collapse**: Filters auto-collapse on mobile (`shf-filter-body-collapse`), expanded on desktop via JS in `shf-app.js`

## CSS Rules

### CSS Custom Properties (`:root`)
- **Colors**: `--primary-dark` (#3a3536bf), `--primary-dark-solid` (#3a3536), `--primary-dark-light` (#3a353696), `--accent` (#f15a29), `--accent-warm` (#f47929), `--accent-light` (#f99d3e), `--accent-dim` (rgba(241,90,41,0.10)), `--bg` (#f8f8f8), `--bg-alt` (#e6e7e8), `--text` (#1a1a1a), `--text-muted` (#6b7280), `--border` (#bcbec0), `--white`, `--red` (#c0392b), `--green` (#27ae60)
- **Font sizes**: `--shf-text-2xs` (0.65rem) through `--shf-text-xl` (1.25rem) -- use utility classes `shf-text-2xs` through `shf-text-xl`
- **Radius/shadow**: `--radius` (10px), `--shadow`, `--shadow-lg`

### Component Classes
- **Sections**: `shf-section` (white card with border), `shf-section-header` (dark gradient), `shf-section-number` (orange circle), `shf-section-title`, `shf-section-body`
- **Buttons**: `btn-accent` (orange gradient pill), `btn-accent-sm` (smaller), `btn-accent-outline` (uses `color: var(--accent)` on light backgrounds), `btn-accent-outline-white` (for dark backgrounds like section headers), `shf-btn-success`, `shf-btn-warning`, `shf-btn-danger`, `shf-btn-gray`, `shf-btn-danger-alt`, `shf-btn-minimal`
- **Cards**: `shf-card` (white with border/shadow), `shf-stat-card` (left border accent, icon + value + label), `shf-stat-card-blue`/`accent`/`green`/`warning` (colored left border variants)
- **Forms**: `shf-form-label` (uppercase tiny label), `shf-input` (standard), `shf-input-sm` (compact), `shf-input-readonly`, `shf-amount-input` / `shf-amount-wrap` / `shf-amount-raw` (Indian currency formatting), `shf-select-compact`, `shf-validation-error`
- **Badges**: `shf-badge` base + color variants: `shf-badge-orange`, `shf-badge-blue`, `shf-badge-gray`, `shf-badge-green`, `shf-badge-purple`, `shf-badge-red`
- **Stage badges**: `shf-badge-stage-inquiry`, `shf-badge-stage-doc-selection`, `shf-badge-stage-doc-collection`, `shf-badge-stage-app-number`, `shf-badge-stage-bsm-osv`, `shf-badge-stage-legal`, `shf-badge-stage-valuation`, `shf-badge-stage-sanction-decision`, `shf-badge-stage-rate-pf`, `shf-badge-stage-sanction`, `shf-badge-stage-docket`, `shf-badge-stage-kfs`, `shf-badge-stage-esign`, `shf-badge-stage-disbursement`, `shf-badge-stage-otc`
- **Stage status**: `shf-stage-pending`, `shf-stage-in-progress`, `shf-stage-completed`, `shf-stage-rejected`, `shf-stage-skipped` (border-left color indicators)
- **Stage cards**: `shf-stage-card`, `shf-stage-card--child`, `shf-stage-header`, `shf-stage-header-title`, `shf-stage-body`, `shf-stage-roles`
- **Phase pills**: `shf-phase-pill` + `shf-phase-pill--done`, `shf-phase-pill--active`, `shf-phase-pill--pending`, `shf-phase-chevron`
- **Role colors**: `shf-role-loan-advisor` (#2563eb), `shf-role-bank-employee` (#d97706), `shf-role-branch-manager` (#059669), `shf-role-office-employee` (#7c3aed), `shf-role-task-owner` (#4b5563) -- plus `shf-role-bg-*` background variants
- **Tabs**: `shf-tabs` (dark background container), `shf-tab` (individual tab button), `shf-tab.active`, `shf-tab-pane-hidden`, `shf-tab-close`
- **Tags/Chips**: `shf-tag` + `shf-tag-remove` (removable tag), `shf-chip` (toggle selection chip, e.g., tenures)
- **Documents**: `shf-doc-grid`, `shf-doc-item`, `shf-doc-received`, `shf-doc-pending`, `shf-doc-rejected`, `shf-doc-highlight`, `shf-doc-actions`, `doc-sortable-ghost`, `doc-drag-handle`
- **Icons**: `shf-icon-xs` (10px), `shf-icon-2xs` (12px), `shf-icon-sm` (14px), `shf-icon-md` (16px), `shf-icon-lg` (20px), `shf-icon-xl` (32px), `shf-icon-inline`, `shf-header-icon`
- **Navigation**: `shf-nav-link`, `shf-nav-active` (orange bottom border), `shf-navbar-bg`, `shf-header-back`
- **Toasts**: `shf-toast-wrapper`, `shf-toast` (success/error/warning variants), `shf-toast-close`
- **Empty state**: `shf-empty-state-icon`, `shf-empty-icon-blue`, `shf-empty-icon-accent`, `shf-empty-icon-green`
- **Stage notes**: `shf-stage-notes`, `shf-phase-step`, `shf-phase-num`, `shf-role-dot`, `shf-note-line`, `shf-transfer-line`, `shf-transfer-hint`
- **Stat cards (colored)**: `shf-stat-border-blue`/`green`/`accent`/`warning`, `shf-stat-icon-blue`/`green`/`accent`/`warning`
- **Stage pipeline**: `shf-stage-dot`, `shf-stage-dot--current`, `shf-stage-connector`, `shf-stage-highlight`
- **Toggles/Checkboxes**: `shf-checkbox` (accent-colored), `shf-toggle` (switch), `shf-password-toggle`
- **Filters**: `shf-collapsible`, `shf-filter-open`, `shf-filter-count`, `shf-filter-body-collapse`, `shf-collapse-arrow`, `shf-collapse-hidden`
- **Other layout**: `shf-page-header` (dark gradient), `shf-page-title`, `shf-parallel-grid` (2-col on desktop, 1-col mobile), `shf-per-page`, `shf-pagination`, `shf-add-form-wrapper`/`toggle`/`body`, `shf-timeline-line`, `shf-guest-card`, `shf-section-no-top-radius`, `shf-settings-tab-header`, `shf-impersonation-banner`, `shf-upload-label`, `shf-remark-item`, `shf-notification-badge`
- **Utility text**: `shf-text-accent`, `shf-text-white-muted`, `shf-text-white-70`, `shf-text-nowrap`, `shf-text-gray`, `shf-text-gray-light`, `shf-text-dark-alt`, `shf-text-error`, `shf-text-success-alt`, `text-prewrap`, `text-inherit`, `shf-font-mono`, `shf-clickable`, `shf-border-accent`, `shf-border-top-light`, `bg-accent-dim`
- **Max widths**: `shf-max-w-sm` (28rem), `shf-max-w-md` (42rem), `shf-max-w-lg` (48rem), `shf-max-w-xl` (56rem), `shf-max-w-20` (20rem), `shf-max-w-36` (36rem)
- **Progress**: `shf-progress-sm` (6px), `shf-progress-md` (8px)

### Tables
- Use Bootstrap built-in classes (`table`, `table-hover`) -- the CSS applies SHF-themed overrides automatically (Jost headers, accent hover, adjusted padding)
- Mobile responsive table: `shf-table-mobile` class transforms table rows into card layout via `data-label` attributes

### Dates
- Bootstrap Datepicker (local vendor, path: `public/newtheme/vendor/datepicker/`) -- loaded globally in `newtheme/layouts/app.blade.php`
- Do NOT load datepicker JS again in individual views
- Do NOT use native `<input type="date">`

### DataTable Sections
- Wrap in `shf-section shf-dt-section`
- Use `dom: 'rt<"shf-dt-bottom"ip>'` for DataTables config
- Empty state via `drawCallback`
- On mobile, `shf-dt-section table.dataTable` is hidden -- build mobile cards in `drawCallback`

### Filter Buttons
- Include SVG icons, use `btn-accent btn-accent-sm` for Filter button, `btn-accent-outline btn-accent-sm` for Clear button

## JS Patterns

### SHF namespace (`public/newtheme/js/shf-app.js`)
- `SHF.validateForm($form, rules)` -- client-side validation with rule types: required, maxlength, minlength, min, max, email, numeric, pattern, patternMsg, dateFormat, custom
- `SHF.validateBeforeAjax($container, rules, url, data)` -- validate then AJAX POST
- `SHF.formatIndianNumber(num)` -- format number with Indian comma system (lakh/crore)
- `SHF.numberToWordsEn(num)` -- English amount in words (Crore/Lakh/Thousand)
- `SHF.numberToWordsGu(num)` -- Gujarati amount in words
- `SHF.bilingualAmountWords(num)` -- combined English / Gujarati words
- `SHF.initAmountFields()` -- auto-init `.shf-amount-input` fields with formatting
- Auto-behaviors: form novalidate, toast auto-dismiss, password toggle (`.shf-password-toggle`), saved message fade (`.shf-saved-msg`), modal auto-show (`data-bs-show-on-load`), SweetAlert confirm delete (`.shf-confirm-delete`), collapsible sections (`.shf-collapsible[data-target]`), filter collapse on mobile, auto-expand textareas (fallback for `field-sizing: content`)

### SHFLoans namespace (archived — not currently loaded)
- The `SHFLoans.*` helpers used to live in `public/js/shf-loans.js`. That file now sits in the archive at `.ignore/old_code_backup/public/js/shf-loans.js`. If loan-module JS needs to come back, copy it into `public/newtheme/js/` and load it explicitly from the relevant newtheme blade.

### Global JS (in `newtheme/layouts/app.blade.php`)
- Notification badge polling via `updateNotifBadge()` every 60 seconds
- Impersonate user search with SweetAlert confirmation
- Service Worker registration
- OfflineManager network listeners and auto-sync
- PWA install prompt (24h dismiss cooldown)

## Config / Settings
- **JSON casts**: When a model has `'array'` cast, pass raw arrays -- never manually `json_encode` (double-encoding bug)
- **Tag inputs**: Auto-add pending values on form submit -- users expect Save to capture typed-but-not-added values
- **Settings tabs**: All doc type tabs must render inputs on page load, not just the active tab (prevents silent data loss)

## Views
- **Always** `@extends`/`@section` pattern -- never Blade component wrappers (`<x-app-layout>`)
- Update CLAUDE.md and reference docs in the same change as view architecture updates
- **New pages**: Match existing page patterns exactly (stat cards, filter sections, DataTable config, empty states)

## Dashboard
- **Default tab priority**: overdue personal tasks -> loan tasks -> pending personal tasks -> active loans -> unconverted quotations -> personal tasks fallback
- **Tab visibility**: permission-based. **Default selection**: data-based (check actual counts, not just permissions)
- **Create modals**: Open on dashboard directly -- never redirect to another page just to show a modal

## General Tasks
- **Visibility**: own + assigned + BDH branch users + admin view-all (read-only)
- **No permission gate** on routes -- all logged-in users can use tasks
- **After create**: redirect to task show page, not list

## Testing
- Auth/Profile tests (Breeze defaults) have pre-existing failures due to `EnsureUserIsActive` middleware + disabled registration -- do NOT debug during unrelated work
