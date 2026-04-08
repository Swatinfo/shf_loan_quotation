# Blade Views Reference

## Overview

All views are in `resources/views/`. The application uses the `@extends` / `@section` pattern exclusively — never Blade component slots (`<x-app-layout>`, `{{ $slot }}`).

## Layouts

### layouts/app.blade.php (194 lines)
**Purpose**: Master layout for all authenticated pages.

**Structure**:
```html
<!DOCTYPE html>
<html>
<head>
    <!-- CSRF meta tag -->
    <!-- Bootstrap 5.3 CSS (local) -->
    <!-- shf.css (design system) -->
    <!-- SweetAlert2 CDN (https://cdn.jsdelivr.net/npm/sweetalert2@11) -->
    <!-- @stack('styles') -->
</head>
<body style="background: #f8f8f8; min-height: 100vh">
    @include('layouts.navigation')

    @hasSection('header')
    <header><!-- Gradient header bar --></header>
    @endif

    <!-- Flash messages (success, error, warning) → jQuery toast -->

    <main>@yield('content')</main>

    <!-- PWA install banner (24h localStorage cooldown) -->
    <!-- Offline status banner (fixed bottom) -->

    <!-- Scripts: jQuery, Bootstrap JS, DataTables, Sortable, shf-app.js -->
    <!-- Service Worker registration -->
    <!-- OfflineManager + config-loader -->
    <!-- @stack('scripts') -->
</body>
</html>
```

**Available Sections**:
| Section | Purpose |
|---------|---------|
| `@yield('content')` | Main page content |
| `@hasSection('header')` | Optional page header (gradient background) |
| `@stack('styles')` | Additional CSS |
| `@stack('scripts')` | Additional JS |

### layouts/guest.blade.php (36 lines)
**Purpose**: Minimal layout for auth pages (login, forgot password, etc.).

**Structure**:
- Dark background (`#3a3536`)
- Centered flex container
- Company logo at top
- White card (max-width 28rem, orange top border)
- `@yield('content')` inside card
- Minimal scripts: jQuery, Bootstrap JS, shf-app.js only

### layouts/navigation.blade.php (349 lines)
**Purpose**: Top navigation bar (included in app.blade.php).

**Features**:
- Dark navbar with blur backdrop (`rgba(58,53,54,0.85)`)
- Logo on left (28px height)
- `navbar-expand-lg` (collapses at 992px)
- Mobile responsive hamburger menu
- Permission-based menu items:

| Link | Route | Permission |
|------|-------|-----------|
| Dashboard | dashboard | auth (always) |
| New Quotation | quotations.create | create_quotation |
| Loans | loans.index | view_loans |
| Users | users.index | view_users |
| Loan Settings | loan-settings.index | view_loans |
| Quotation Settings | settings.index | view_settings |
| Permissions | permissions.index | manage_permissions |
| Activity Log | dashboard.activityLog | view_activity_log |

- Active route highlighting via `request()->routeIs()`
- Impersonation UI: search dropdown to impersonate a user, amber banner when impersonating with "Leave" button to stop
- Notification bell with unread badge count (60-second polling interval)
- User role badges (colored: orange for super_admin, blue for admin, gray for staff)
- User dropdown (right): Profile link, Logout button

## Page Views

### welcome.blade.php
- **Standalone HTML** (does NOT extend any layout)
- Logo, app title, description
- Links to login/register/dashboard

### dashboard.blade.php
- **Extends**: layouts.app
- **Stats**: 3 stat cards (total, today, this_month) in responsive grid
- **Filters**: Customer type, date range, created_by (admin only)
- **Table**: DataTables with AJAX source `/dashboard/quotation-data`
- **Dual layout**: Desktop table + mobile card layout
- **Scripts**: DataTables initialization with server-side processing

### activity-log.blade.php
- **Extends**: layouts.app
- **Filters**: User dropdown, action text, date range with datepicker
- **Table**: Action, User, Details (JSON properties), Timestamp
- **Pagination**: 50 per page

## Quotation Views

### quotations/create.blade.php
- **Extends**: layouts.app
- **Multi-section numbered form**:
  1. Customer Info (name + type dropdown, bilingual)
  2. Documents (dynamic checklist per customer type)
  3. Loan Amount
  4. Bank Details (add/remove banks with ROI + charges)
  5. Tenures (chip/pill selection)
  6. Additional Notes
  7. Generate button (AJAX POST)
- **JS**: Dynamic section visibility, validation, AJAX submission

### quotations/show.blade.php
- **Extends**: layouts.app
- **Header**: Breadcrumb, title, Download + Delete buttons
- **Content**: Customer details, bank tables, EMI tables, charges, documents
- **Authorization**: Download (download_pdf), Delete (delete_quotations)
- **Delete**: Confirmation modal with AJAX DELETE

### quotations/convert.blade.php
- **Extends**: layouts.app
- **Permission**: convert_to_loan
- **3-step conversion form**:
  1. Summary of quotation (customer details, loan amount)
  2. Select Bank (radio cards from quotation's banks)
  3. Review & Convert
- **Dependent dropdowns**: bank → product, branch selection
- **JS**: Step navigation, bank radio card selection, dependent dropdown loading

## Loan Views

### loans/index.blade.php
- **Extends**: layouts.app
- **Stat cards**: Active, completed, rejected, on_hold loan counts
- **Filters**: Status, customer_type, bank, branch, date range
- **Table**: DataTables with AJAX source `/loans/data`
- **Dual layout**: Desktop table (`d-none d-md-block`) + mobile cards (`d-md-none`)
- **Owner column**: Shows current stage assignee

### loans/create.blade.php
- **Extends**: layouts.app
- **Permission**: create_loan
- **Form sections**:
  - Customer Info: name, customer type, phone, email
  - Loan Details: amount, bank → product (dependent dropdown), branch, ROI, charges

### loans/edit.blade.php
- **Extends**: layouts.app
- **Permission**: edit_loan
- **Same structure as create**, pre-populated with existing loan data

### loans/show.blade.php
- **Extends**: layouts.app
- **Hub view** — central page for a single loan:
  - Ownership banner: current owner + time since assignment
  - Collapsible customer details (uses `shf-collapsible` class with arrow rotation)
  - Quick links: Stages, Documents, Timeline, Remarks, Valuation, Disbursement
  - Status action buttons: complete, reject, cancel, on_hold
  - Edit/Delete actions
  - SweetAlert confirmation dialogs for destructive actions

### loans/stages.blade.php
- **Extends**: layouts.app
- **Progress bar**: Numbered stage dots showing completion status
- **Stage cards list**: Each card shows status indicator (pending/in_progress/completed/skipped/rejected)
- **Parallel stages**: Stage 4 shown in 2x2 grid layout
- **Actions per card**: assign, transfer, skip, reject, raise query
- **Transfer modal**: Select user + reason
- **Query UI**: Conversation thread for stage queries
- **Includes partials**: `progress-bar.blade.php`, `stage-card.blade.php`

### loans/timeline.blade.php
- **Extends**: layouts.app
- **Vertical timeline** with color-coded entries:
  - Stage starts, completions, transfers, queries, remarks, disbursement
  - Each entry: icon, title, description, user, timestamp

### loans/documents.blade.php
- **Extends**: layouts.app
- **Document collection progress bar** (percentage complete)
- **Document list**: Bilingual names (EN/GU)
- **Status dropdowns**: pending/received/rejected/waived per document
- **SweetAlert**: Prompts for rejection reason
- **Add custom document form** at bottom

### loans/transfers.blade.php
- **Extends**: layouts.app
- **Full transfer timeline/history** for a loan
- **Each entry**: from → to user, reason, date

### loans/disbursement.blade.php
- **Extends**: layouts.app
- **Decision tree UI**: fund_transfer vs cheque vs demand_draft
- **Conditional fields**: Based on selected disbursement type
- **OTC toggle**: Over The Counter mode with branch/clearance fields

### loans/valuation.blade.php
- **Extends**: layouts.app
- **Valuation form**: type, property details, market/government value, valuator info

### loans/show-temp.blade.php
- **Temporary placeholder** (legacy, from Phase 2)

### loans/partials/progress-bar.blade.php
- **Partial**: Numbered dots progress bar showing stage completion status

### loans/partials/stage-card.blade.php
- **Partial**: Individual stage card with status, assignee, actions, transfer button, query UI

### loans/partials/stage-notes-form.blade.php
- **Partial**: Stage-specific notes form (CIBIL score, rate/PF, sanction, docket, KFS, e-sign)

### loans/partials/stage-cibil-check.blade.php
- **Partial**: CIBIL score input (range 300-900)

### loans/partials/stage-rate-pf.blade.php
- **Partial**: Interest rate, processing fee, admin charges fields

### loans/partials/stage-sanction.blade.php
- **Partial**: Sanction reference number and date

### loans/partials/stage-docket.blade.php
- **Partial**: Docket number and login date

### loans/partials/stage-kfs.blade.php
- **Partial**: KFS reference field

### loans/partials/stage-esign.blade.php
- **Partial**: E-sign status (blocks progression if stage not 'completed')

### loans/partials/remarks.blade.php
- **Partial**: Remarks list display + add remark form

## User Views

### users/index.blade.php
- **Extends**: layouts.app
- **Table**: Name, Email, Role (badge), Status, Created, Actions
- **Dual layout**: Desktop table + mobile cards
- **Actions**: Edit, Toggle Active (PATCH), Delete (with confirmation)
- **Badges**: Role-colored (orange/blue/gray)

### users/create.blade.php
- **Extends**: layouts.app
- **Form**: Name, Email, Phone, Password, Confirm Password, Role, Active checkbox
- **Container**: max-width 42rem

### users/edit.blade.php
- **Extends**: layouts.app
- **Form**: Same as create + password optional + Permission Overrides section
- **Task role fields**: task_role, task_bank_id, employee_id, default_branch_id (for loan workflow assignment)
- **Permission Overrides**: Grouped checkboxes with Grant/Deny/Default radio buttons per permission
- **Container**: max-width 56rem

## Settings Views

### settings/index.blade.php
- **Extends**: layouts.app
- **Tabbed interface**: 8 tabs with permission-based visibility
- **Tab content**: Each in `settings-tab-pane` div (show/hide via JS)
- **Tab types**: Forms (company, charges, GST, services), Tag lists (banks, tenures), Document editor (documents), Editable table (bank charges)
- **Reset**: Red button in header with confirmation

### settings/workflow-product-stages.blade.php
- **Extends**: layouts.app
- **Per-product stage toggle/configuration**
- **Breadcrumb**: Links back to Loan Settings

## Loan Settings Views

### loan-settings/index.blade.php
- **Extends**: layouts.app
- **5 tabs**:
  1. **Banks**: Shared banks table with CRUD operations
  2. **Branches**: Branch CRUD
  3. **Stage Master**: Master stage configuration
  4. **Products & Stages**: Per-bank product CRUD with links to stage configuration
  5. **User Roles**: Manage task_role, task_bank_id, employee_id, branch assignments per user

## Notification Views

### notifications/index.blade.php
- **Extends**: layouts.app
- **Notification cards**: Each with type badge (info/success/warning/error/stage_update/assignment)
- **Actions**: Mark read, Mark all read
- **Links**: Navigate to related loans/stages from notification

## Permission Views

### permissions/index.blade.php
- **Extends**: layouts.app
- **Matrix**: Permissions (rows) x Roles (columns)
- **Grouped**: By permission group with colored headers
- **Super Admin**: Always checked, disabled
- **Form**: PUT to permissions.update

## Profile Views

### profile/edit.blade.php
- **Extends**: layouts.app
- **3 sections** (via partials):
  1. Profile Information (`update-profile-information-form`)
  2. Update Password (`update-password-form`)
  3. Delete Account — only for super_admin (`delete-user-form`)

### profile/partials/update-profile-information-form.blade.php
- Name + Email fields
- Email verification status + resend button
- Form: PATCH to profile.update

### profile/partials/update-password-form.blade.php
- Current Password, New Password, Confirm Password
- Password visibility toggles
- Form: PUT to password.update

### profile/partials/delete-user-form.blade.php
- Red delete button → Bootstrap confirmation modal
- Password verification required
- Form: DELETE to profile.destroy

## Auth Views

All extend `layouts.guest`.

| View | Purpose | Form Action |
|------|---------|-------------|
| auth/login.blade.php | Login form (email + password + toggle) | POST /login |
| auth/register.blade.php | Registration form (disabled in production) | POST /register |
| auth/forgot-password.blade.php | Request password reset email | POST /forgot-password |
| auth/reset-password.blade.php | Set new password with token | POST /reset-password |
| auth/confirm-password.blade.php | Re-confirm password for sensitive ops | POST /password/confirm |
| auth/verify-email.blade.php | Email verification prompt + resend | POST /email/verification-notification |

## Common Patterns

### Flash Messages
Handled in `layouts/app.blade.php`:
```blade
@if(session('success'))
    <div class="shf-toast shf-toast-success">{{ session('success') }}</div>
@endif
```

### Error Display
```blade
@if($errors->any())
    <ul class="list-unstyled mb-0">
        @foreach($errors->all() as $error)
            <li style="color: #c0392b">{{ $error }}</li>
        @endforeach
    </ul>
@endif
```

### Permission Checks in Views
```blade
@if(auth()->user()->hasPermission('create_quotation'))
    <a href="{{ route('quotations.create') }}" class="btn btn-accent">New Quotation</a>
@endif
```

### Named Routes
Always use `route()` helper for URL generation:
```blade
<a href="{{ route('quotations.show', $quotation) }}">View</a>
<form action="{{ route('users.destroy', $user) }}" method="POST">
```

### CSRF + Method Spoofing
```blade
<form method="POST" action="{{ route('users.update', $user) }}">
    @csrf
    @method('PUT')
</form>
```

### SweetAlert2 Confirmation Dialogs
Used extensively in loan views for destructive actions (delete, reject, cancel):
```javascript
Swal.fire({
    title: 'Are you sure?',
    text: 'This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#f15a29',
    confirmButtonText: 'Yes, proceed'
}).then((result) => {
    if (result.isConfirmed) {
        // Submit form or AJAX call
    }
});
```
SweetAlert2 is loaded from CDN in `layouts/app.blade.php`.

### Collapsible Sections
Used in loan show page for accordion-style content:
```html
<div class="shf-collapsible" data-target="#details">
    <span>Section Title</span>
    <span class="arrow">&#9660;</span> <!-- Rotates on toggle -->
</div>
<div id="details" class="collapse">
    <!-- Content -->
</div>
```

### Dependent Dropdown Pattern
Used for bank → product selection in loan forms:
```javascript
$('#bank_id').on('change', function() {
    var bankId = $(this).val();
    $.get('/api/products/' + bankId, function(data) {
        var $product = $('#product_id').empty().append('<option value="">Select Product</option>');
        data.forEach(function(p) {
            $product.append('<option value="' + p.id + '">' + p.name + '</option>');
        });
    });
});
```
