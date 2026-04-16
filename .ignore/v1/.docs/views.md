# Views Reference

All Blade view files in `resources/views/`, organized by feature area.

---

## Layouts

### `layouts/app.blade.php`
- **Purpose**: Main authenticated layout
- **Includes**: `layouts.navigation`
- **Sections**: `header` (optional), `content`
- **Stacks**: `styles`, `scripts`
- **JS**: jQuery 3.7, Bootstrap 5.3, Datepicker, SortableJS, SweetAlert2, shf-app.js, offline-manager.js, pdf-renderer.js, SW registration
- **Features**: Flash toasts (success/error/warning), PWA install banner, offline status banner

### `layouts/guest.blade.php`
- **Purpose**: Unauthenticated layout (login, register, password reset)
- **Sections**: `content`
- **JS**: jQuery 3.7, Bootstrap 5.3, shf-app.js (minimal stack)
- **Style**: Dark background, centered white card with accent top border

### `layouts/navigation.blade.php`
- **Purpose**: Main navbar (included by `app.blade.php`)
- **Features**: Desktop horizontal nav + mobile collapsible, permission-gated links, notification bell with 60s polling, impersonation dropdown with search, user menu with role badge
- **JS**: Inline notification badge polling, impersonation search with SweetAlert2 confirm

---

## Auth Views

All extend `layouts.guest`, define `content` section only.

| View | Purpose |
|------|---------|
| `auth/login.blade.php` | Login form (email + password + remember me) |
| `auth/register.blade.php` | Registration form (disabled in production) |
| `auth/forgot-password.blade.php` | Password reset email request |
| `auth/reset-password.blade.php` | Password reset form (with token) |
| `auth/confirm-password.blade.php` | Password confirmation for secure areas |
| `auth/verify-email.blade.php` | Email verification notice |

---

## Dashboard

### `dashboard.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `styles` (DataTables CSS), `scripts`
- **JS**: DataTables (3 instances: quotations, tasks, loans), Bootstrap Datepicker, tab switching with lazy-init
- **Features**: Stats cards, tabbed interface (Quotations/Tasks/Loans), date range filters, per-page selectors, mobile card view for quotations, dual-layout tables

---

## Quotations

### `quotations/create.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **Features**: Multi-section form (bank selection, customer details, tenure chips, document checkboxes, charges)

### `quotations/show.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Features**: Quotation detail view with PDF download, comparison table, document list

### `quotations/convert.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **JS**: Bootstrap Datepicker (DOB field)
- **Features**: Convert quotation to loan form (customer details, bank/product selection)

---

## Loans

### `loans/index.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `styles` (DataTables CSS), `scripts`
- **JS**: DataTables (server-side), Bootstrap Datepicker (date filters)
- **Features**: Filterable loan list (search, stage, bank, product, assignee, date range, docket date), stage badges, action buttons

### `loans/create.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **JS**: `shf-loans.js` (product dropdown cascade)
- **Features**: New loan form (customer name, bank, product, amount, tenure)

### `loans/edit.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **JS**: `shf-loans.js` (product dropdown cascade)
- **Features**: Edit loan details form

### `loans/show.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **Features**: Loan detail view with stage overview, assignment info, action links (stages, documents, timeline, transfers, valuation, disbursement), remarks/queries section

### `loans/show-temp.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Features**: Temporary/alternate loan detail view

### `loans/stages.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **Includes**: `loans.partials.stage-notes-form` (multiple times per stage)
- **JS**: Bootstrap Datepicker, SweetAlert2 confirmations
- **Features**: Multi-stage workflow interface, stage completion forms, stage-specific fields, query/remark handling

### `loans/documents.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **Features**: Document collection management (received/pending/rejected status, file upload)

### `loans/timeline.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Features**: Loan lifecycle timeline (stage transitions, assignments, activities)

### `loans/transfers.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Features**: Stage assignment transfer history

### `loans/valuation.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **JS**: Bootstrap Datepicker
- **Features**: Property valuation form (valuation date, amount, valuer details, remarks)

### `loans/disbursement.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **JS**: Bootstrap Datepicker, dynamic cheque row addition
- **Features**: Disbursement tracking form (date, amount, mode, cheque details with dynamic rows)

### `loans/partials/stage-notes-form.blade.php`
- **Purpose**: Reusable partial for stage-specific form fields
- **Included by**: `loans/stages.blade.php`
- **Features**: Dynamic field rendering (text, date, textarea, select), datepicker support for date fields, readonly support

---

## Settings (Quotation)

### `settings/index.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **JS**: SortableJS (drag-reorder documents), tag input handling
- **Features**: Tabbed settings (Banks, Products, Tenures, Charges, Documents per customer type), inline CRUD, document reordering

### `settings/__index.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **Purpose**: Alternate/legacy settings index

### `settings/workflow.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **Features**: Workflow stage configuration

### `settings/workflow-product-stages.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **Features**: Product-specific stage configuration (enable/disable stages per product)

---

## Loan Settings

### `loan-settings/index.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **Features**: Loan workflow configuration (stage settings, role assignments, document type management, workflow rules)

---

## Users

### `users/index.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `styles` (DataTables CSS), `scripts`
- **JS**: DataTables (server-side)
- **Features**: User list with role badges, active/inactive status, search, action buttons (edit, impersonate)

### `users/create.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **Features**: New user form (name, email, password, role selection, branch, bank assignments)

### `users/edit.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **Features**: Edit user form (same fields as create + active toggle, permission overrides)

---

## Roles

### `roles/index.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Features**: Role list with permission counts, user counts, action buttons

### `roles/create.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **Features**: New role form with permission grid (grouped checkboxes)

### `roles/edit.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Features**: Edit role form with permission grid

---

## Permissions

### `permissions/index.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Features**: Permission matrix view (roles x permissions grid)

---

## Notifications

### `notifications/index.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `scripts`
- **Features**: Notification list with mark-as-read, mark-all-read, notification type icons

---

## Profile

### `profile/edit.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Includes**: `profile.partials.update-profile-information-form`, `profile.partials.update-password-form`, `profile.partials.delete-user-form`
- **Features**: Three-section profile page (info, password, delete account)

### `profile/partials/update-profile-information-form.blade.php`
- **Purpose**: Name + email update form
- **Included by**: `profile/edit.blade.php`

### `profile/partials/update-password-form.blade.php`
- **Purpose**: Password change form (current + new + confirm)
- **Included by**: `profile/edit.blade.php`

### `profile/partials/delete-user-form.blade.php`
- **Purpose**: Account deletion with password confirmation
- **Included by**: `profile/edit.blade.php`

---

## Activity Log

### `activity-log.blade.php`
- **Extends**: `layouts.app`
- **Sections**: `header`, `content`
- **Stacks**: `styles` (DataTables CSS), `scripts`
- **JS**: DataTables (server-side), Bootstrap Datepicker (date range filter)
- **Features**: Filterable activity log (user, action, date range), expandable property details

---

## Other

### `welcome.blade.php`
- **Purpose**: Landing/welcome page (likely unused or redirects to login)

---

## Common Patterns

### View Inheritance
All authenticated views follow:
```blade
@extends('layouts.app')

@section('header')
    {{-- Page title with icon --}}
@endsection

@section('content')
    {{-- Main content --}}
@endsection

@push('scripts')
    {{-- Page-specific JS --}}
@endpush
```

### DataTables Usage
Views using DataTables push the CSS via `@push('styles')`:
```blade
@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatables/css/dataTables.bootstrap5.min.css') }}">
@endpush
```
And initialize in `@push('scripts')` with server-side processing.

Used in: `dashboard.blade.php`, `loans/index.blade.php`, `users/index.blade.php`, `activity-log.blade.php`

### Bootstrap Datepicker Usage
Initialized on `.shf-datepicker` elements in `@push('scripts')`:
```javascript
$('.shf-datepicker').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true });
```
Never use native `<input type="date">`.

Used in: `dashboard.blade.php`, `loans/index.blade.php`, `loans/stages.blade.php`, `loans/valuation.blade.php`, `loans/disbursement.blade.php`, `quotations/convert.blade.php`, `activity-log.blade.php`

### SortableJS Usage
Used in `settings/index.blade.php` for document reordering via drag handles.
