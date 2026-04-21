# Blade Views

Laravel Blade templates live under `resources/views/newtheme/`. All pages use the `@extends` / `@section` pattern — **not** Blade component wrappers (`<x-app-layout>`). Pre-newtheme blades are archived at `.ignore/old_code_backup/resources/views/` (tracked in git).

## Layouts

### `newtheme/layouts/app.blade.php` — authenticated layout

Structure:

```blade
<html>
  <head>
    <!-- Meta: CSRF, viewport, PWA meta, favicons, manifest link -->
    <!-- newtheme CSS bundle (shf.css, shf-extras.css, shf-workflow.css, shf-modals.css) -->
    @stack('page-styles')
  </head>
  <body>
    @include('newtheme.partials.header', ['pageKey' => $pageKey ?? ''])

    <main>@yield('content')</main>

    @include('newtheme.partials.bottom-nav')
    @include('newtheme.partials.fab')
    @include('newtheme.partials.create-task-modal')
    @include('newtheme.partials.create-dvr-modal')

    <!-- newtheme vendors: jQuery, Bootstrap Datepicker, SortableJS, SweetAlert2 -->
    <!-- newtheme runtime: shf-newtheme.js, shf-interactive.js, shf-dropdown.js, shf-tab-persist.js -->
    <!-- push-notifications.js, shf-create-task.js, shf-create-dvr.js -->
    @stack('page-scripts')
  </body>
</html>
```

### `newtheme/layouts/guest.blade.php` — unauthenticated layout

Light accent-tinted background, centered `.auth-card`. Used for login, password reset, verify-email, register, confirm-password. Loads only jQuery + `shf-newtheme.js` plus whatever pushes to `@stack('scripts')`.

### `newtheme/partials/header.blade.php` — top bar

Replaces the pre-newtheme `layouts/navigation.blade.php`. Permission-gated menu (Dashboard, Quotations, Loans, Tasks, DVR, Customers, Users, Settings, Loan Settings, Reports, Activity Log), user dropdown (Profile, Impersonate, Logout), notification bell with unread count polled every 60s.

Mobile (< xl / < 1200 px): top nav items collapse into the bottom nav. Navigation served by `newtheme/partials/bottom-nav.blade.php` (5 slots: `Dashboard · Loans · DVR · Tasks · More`) + `newtheme/partials/fab.blade.php` (create-actions FAB), both included in `newtheme/layouts/app.blade.php`. See `frontend.md` → "Mobile chrome" for the CSS contract (`--shf-bottom-nav-height`, `body.has-bottom-nav`).

## Page template

Every page view follows:

```blade
@extends('newtheme.layouts.app', ['pageKey' => 'your-page-key'])

@section('title', 'Page Title — SHF')

@section('content')
  <div class="container py-4">
    <div class="shf-page-header">
      <h1 class="shf-page-title">Page Title</h1>
      <div>{{-- action buttons --}}</div>
    </div>

    {{-- shf-section blocks, DataTable, etc. --}}
  </div>
@endsection

@push('page-styles')
  {{-- page-specific CSS, e.g., asset('newtheme/pages/your-page.css') --}}
@endpush

@push('page-scripts')
  {{-- page-specific JS, e.g., asset('newtheme/pages/your-page.js') --}}
@endpush
```

## View directory map

```
resources/views/
├── layouts/
│   ├── app.blade.php
│   ├── guest.blade.php
│   └── navigation.blade.php
├── auth/                          (Breeze defaults: login, forgot-password, ...)
├── profile/
│   ├── edit.blade.php
│   └── partials/
│       ├── update-profile-information-form.blade.php
│       ├── update-password-form.blade.php
│       └── delete-user-form.blade.php
├── dashboard.blade.php
├── activity-log.blade.php
├── quotations/
│   ├── create.blade.php
│   ├── show.blade.php
│   └── convert.blade.php
├── loans/
│   ├── index.blade.php
│   ├── create.blade.php
│   ├── edit.blade.php
│   ├── show.blade.php
│   ├── stages.blade.php
│   ├── documents.blade.php
│   ├── valuation.blade.php
│   ├── valuation-map.blade.php
│   ├── disbursement.blade.php
│   ├── timeline.blade.php
│   ├── transfers.blade.php
│   └── partials/
│       └── stage-notes-form.blade.php
├── loan-settings/
│   └── index.blade.php
├── settings/
│   ├── index.blade.php
│   ├── workflow.blade.php
│   └── workflow-product-stages.blade.php
├── users/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── edit.blade.php
├── roles/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── edit.blade.php
├── permissions/
│   └── index.blade.php
├── dvr/
│   ├── index.blade.php
│   └── show.blade.php
├── general-tasks/
│   ├── index.blade.php
│   └── show.blade.php
├── reports/
│   └── turnaround.blade.php
└── notifications/
    └── index.blade.php
```

## Common patterns

### Inline validation errors

```blade
<input name="customer_name" class="shf-input @error('customer_name') is-invalid @enderror"
       value="{{ old('customer_name', $loan->customer_name ?? '') }}">
@error('customer_name')
  <div class="shf-validation-error">{{ $message }}</div>
@enderror
```

### Required field marker

```blade
<label class="shf-form-label">Customer Name <span class="text-danger">*</span></label>
```

### Indian currency input

```blade
<div class="shf-amount-wrap">
  <input type="text" class="shf-input shf-amount-input" value="{{ old('loan_amount', '') }}"
         data-amount-words="#loanAmountWords">
  <input type="hidden" class="shf-amount-raw" name="loan_amount" value="{{ old('loan_amount', '') }}">
</div>
<div id="loanAmountWords" class="shf-text-xs shf-text-gray"></div>
```

`SHF.initAmountFields()` wires this up automatically.

### Section with number badge

```blade
<div class="shf-section">
  <div class="shf-section-header">
    <span class="shf-section-number">1</span>
    <h3 class="shf-section-title">Customer Information</h3>
  </div>
  <div class="shf-section-body">
    {{-- form fields --}}
  </div>
</div>
```

### Collapsible section

```blade
<div class="shf-section-header shf-collapsible shf-clickable" data-target="#section1Body">
  <h3 class="shf-section-title">Notes</h3>
  <svg class="shf-collapse-arrow">...</svg>
</div>
<div id="section1Body" class="shf-section-body shf-collapse-hidden">
  {{-- content --}}
</div>
```

`shf-app.js` binds the slide toggle automatically.

### DataTable section

```blade
<div class="shf-section shf-dt-section">
  <table id="loansTable" class="table table-hover w-100">
    <thead><tr>{{-- th columns --}}</tr></thead>
    <tbody></tbody>
  </table>
</div>
```

```javascript
$('#loansTable').DataTable({
  processing: true,
  serverSide: true,
  ajax: { url: '{{ route("loans.data") }}', data: (d) => { d.status = $('#filterStatus').val(); } },
  columns: [ /* ... */ ],
  order: [[ /* default */ ]],
  dom: 'rt<"shf-dt-bottom"ip>',
  drawCallback: function() {
    // Build mobile cards if needed; show empty state
  }
});
```

### Filter section with count badge

Pattern: heading with filter count → collapsible filter body → Filter / Clear buttons → DataTable reloads on apply.

```blade
<div class="shf-section">
  <div class="shf-section-header shf-collapsible shf-filter-open" data-target="#filterBody">
    <h3 class="shf-section-title">Filters
      <span id="filterCount" class="shf-filter-count shf-collapse-hidden">0</span>
    </h3>
    <svg class="shf-collapse-arrow">...</svg>
  </div>
  <div id="filterBody" class="shf-section-body">
    <div class="row g-3">
      {{-- col-6 col-md-auto filter controls --}}
    </div>
    <button class="btn-accent btn-accent-sm">Filter</button>
    <button class="btn-accent-outline btn-accent-sm" id="clearFilters">Clear</button>
  </div>
</div>
```

### Tabs

```blade
<div class="shf-tabs">
  <a href="?tab=locations" class="shf-tab {{ $tab==='locations' ? 'active' : '' }}">Locations</a>
  <a href="?tab=branches" class="shf-tab {{ $tab==='branches' ? 'active' : '' }}">Branches</a>
</div>
```

## Stage notes partial

`resources/views/loans/partials/stage-notes-form.blade.php` is a reusable generator for stage-specific data forms. Render with:

```blade
@include('loans.partials.stage-notes-form', [
  'assignment' => $assignment,
  'loan' => $loan,
  'fields' => [
    ['name' => 'application_number', 'label' => 'Application Number', 'type' => 'text', 'required' => true, 'col' => 6],
    ['name' => 'docket_days_offset', 'label' => 'Docket Days Offset', 'type' => 'number', 'min' => 0, 'col' => 6],
    // ...
  ],
  'disabled' => $isLocked,
  'hideSubmit' => false,
])
```

Supported field types: `text`, `textarea`, `select` (with `options`), `number`, `currency`, `date`. Posts to `loans.stages.notes`.

## Dashboard modals

- `#dashCreateTaskModal` and `#dashCreateDvrModal` — **embedded in the dashboard view**, not a separate page. When the user clicks "New Task" / "New Visit" from the dashboard, the modal opens; never redirect away just to show a creation modal.

## Blade rules

- **Always** `@extends` / `@section`. Never `<x-app-layout>` component wrappers.
- Keep all custom classes `shf-*` prefixed.
- No inline styles unless truly one-off. Prefer utility classes in `shf.css`.
- Inline validation (per-field `@error`) — no separate validation summary.
- When changing view structure, update `frontend.md` and this file.

## See also

- `frontend.md` — full CSS/JS catalog
- `dashboard.md` — dashboard layout specifics
- `loans.md`, `quotations.md`, etc. — feature-level view notes
