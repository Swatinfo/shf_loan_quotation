# Views

## Layout Architecture

### `layouts/app.blade.php` (authenticated)
- PWA metadata, favicons, manifest
- Bootstrap 5.3 CSS + SHF CSS
- Navigation include
- Page header with gradient background
- Flash message toasts (success/error/warning)
- PWA install banner (24h cooldown)
- Offline status banner
- Service Worker registration
- All vendor JS loaded: jQuery, Bootstrap, Datepicker, SweetAlert2, SortableJS
- Custom JS: shf-app.js, offline-manager.js, pdf-renderer.js
- `@stack('styles')` and `@stack('scripts')` for page-specific assets

### `layouts/guest.blade.php` (unauthenticated)
- Dark background, centered white card with orange border
- Logo + company name
- Used for login, password reset, registration

### `layouts/navigation.blade.php`
- Dark navbar with blur backdrop and orange bottom border
- Permission-based nav links
- Notification bell with count (60s polling)
- Impersonation search + banner
- Mobile hamburger with role badge

## View Pattern

All views use `@extends('layouts.app')` / `@extends('layouts.guest')` with `@section` blocks.

**Never** use Blade component wrappers (`<x-app-layout>`).

## Page Structure Convention

1. **Stat cards** — top row with count/summary cards (`shf-stat-card`)
2. **Filter section** — collapsible filters with dropdowns, date pickers
3. **DataTable section** — server-side table wrapped in `shf-section shf-dt-section`
4. **Mobile cards** — built in DataTable `drawCallback`
5. **Modals** — for create/edit forms on pages that need them

## View Files

### Dashboard
- `dashboard.blade.php` — tabs for quotations, loans, tasks, DVR with stat cards and DataTables

### Quotations
- `quotations/create.blade.php` — multi-step form: customer, banks, charges, documents
- `quotations/show.blade.php` — detail view with download/convert/delete actions
- `quotations/convert.blade.php` — quotation to loan conversion form

### Loans
- `loans/index.blade.php` — DataTable with stat cards and filters
- `loans/create.blade.php` — loan creation form
- `loans/edit.blade.php` — loan edit form
- `loans/show.blade.php` — collapsible sections: customer, stages, documents, valuation, disbursement
- `loans/stages.blade.php` — visual workflow pipeline
- `loans/timeline.blade.php` — chronological event list
- `loans/documents.blade.php` — document grid with status/upload/download
- `loans/disbursement.blade.php` — disbursement form and tracking
- `loans/valuation.blade.php` — property valuation form
- `loans/valuation-map.blade.php` — Leaflet map for property location
- `loans/transfers.blade.php` — transfer history timeline
- `loans/partials/stage-notes-form.blade.php` — stage notes modal partial

### Users
- `users/index.blade.php` — DataTable with role/status filters
- `users/create.blade.php` — create form with role multi-select, branches, banks
- `users/edit.blade.php` — edit form + password change

### Roles
- `roles/index.blade.php` — role list with user counts
- `roles/create.blade.php` — create form with permission checkboxes
- `roles/edit.blade.php` — edit form with permission checkboxes

### Settings
- `settings/index.blade.php` — tabbed interface for all config
- `settings/workflow.blade.php` — workflow configuration
- `settings/workflow-product-stages.blade.php` — product-specific stage matrix

### Loan Settings
- `loan-settings/index.blade.php` — banks, products, branches, stages, locations

### Tasks
- `general-tasks/index.blade.php` — DataTable with view/status/priority filters
- `general-tasks/show.blade.php` — task detail with comments

### DVR
- `dvr/index.blade.php` — DataTable with contact/purpose/follow-up filters
- `dvr/show.blade.php` — visit detail with chain and follow-up info

### Reports
- `reports/turnaround.blade.php` — turnaround time report with two tabs (Overall TAT, Stage-wise TAT), DataTables, filters (date range, bank, product, branch, user, stage)

### Other
- `permissions/index.blade.php` — permission matrix (non-Loans group)
- `notifications/index.blade.php` — notification list
- `activity-log.blade.php` — system activity log
- `profile/edit.blade.php` — profile with update-info, change-password, delete-account partials

## Permission Checks in Views

Views use `auth()->user()->hasPermission('slug')` or `auth()->user()->isSuperAdmin()` for conditional rendering:
- Nav links visibility
- Action buttons (create, edit, delete, download)
- Admin-only features

## Asset Versioning

CSS/JS loaded with cache-busting: `?v={{ config('app.shf_version') }}`
