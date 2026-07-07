# Dashboard & Activity Log

## Overview

The dashboard (`/dashboard`) is the main landing page after login. It displays role-aware stats, three tabbed data tables (My Tasks, Loans, Quotations), and auto-selects the most relevant tab based on the user's pending work.

**Route**: `GET /dashboard` — `DashboardController@index` — no special permission (any authenticated user)

**View**: `resources/views/dashboard.blade.php`

---

## Stats Cards

The dashboard shows up to 6 stat cards depending on user permissions:

### Quotation Stats (visible if user has any quotation permission)
Shown when `create_quotation`, `view_own_quotations`, or `view_all_quotations` is granted.

| Card | Description |
|------|-------------|
| **Quotations** | Total count (own or all, based on `view_all_quotations`) |
| **Today** | Quotations created today |
| **This Month** | Quotations created this calendar month |

### Loan Stats (visible if user has `view_loans` or a workflow role)
| Card | Description |
|------|-------------|
| **Active Loans** | Count of loans with `status = 'active'` visible to user |
| **My Tasks** | `StageAssignment` records assigned to user with status `pending` or `in_progress` |
| **Completed** | Loans completed this calendar month |

Card columns adjust automatically: `col-md-2` when both quotation and loan stats are shown, `col-md-4` when only one group is visible.

---

## Dashboard Tabs

Three tabs appear conditionally. The **default tab** is chosen automatically:

1. **My Tasks** — if user has pending tasks (`myTasks > 0`)
2. **Loans** — if user has recent active loans but no pending tasks
3. **Quotations** — fallback if user has quotation access

### My Tasks Tab

Server-side DataTable loading assignments where `assigned_to = current user` and status is `pending` or `in_progress`.

**AJAX endpoint**: `GET /dashboard/task-data` — `DashboardController@taskData`

**Filters**:
- Stage (dropdown of enabled stages, filtered by role — bank employees see only their relevant stages)
- Status (`in_progress` / `pending`)
- Search (loan number, customer name, bank name)

**Columns**: Loan #, Customer (with type badge), Bank (with product + location), Amount, Stage, Status, Assigned (relative time), Actions (Stages button)

**Sort order**: In-progress tasks first, then by `updated_at` descending.

**Dual layout**: Desktop table (`d-none d-md-block`) + mobile cards (`d-md-none`).

### Loans Tab

Server-side DataTable showing loans visible to the current user. Excludes completed/rejected/cancelled by default.

**AJAX endpoint**: `GET /dashboard/loan-data` — `DashboardController@dashboardLoanData`

**Filters**:
- Status (active, completed, rejected, on_hold, cancelled)
- Stage (current stage)
- Bank, Branch
- Customer type
- Role (filter by assignee role on current stage)
- Date range (from/to)
- Search (loan number, customer name, bank name)

**Columns**: Loan #, Customer, Bank (with product + location), Amount, Stage (badge), Status (badge), Created, Actions (View + Stages buttons)

### Quotations Tab

Server-side DataTable for quotation history.

**AJAX endpoint**: `GET /dashboard/quotation-data` — `DashboardController@quotationData`

**Filters**:
- Page length (10/20/50/100)
- Search (customer name, PDF filename)
- Customer type
- Loan status (`not_converted` default, `active`, `converted`, `completed`, `rejected`, `all`)
- Date range (from/to)
- Created by (only visible with `view_all_quotations`)

**Columns**: ID, Customer, Type (badge), Amount, Banks, Created By (if `view_all`), Date, Actions

**Actions per row**:
- View (always)
- Download PDF (if `download_pdf` permission)
- Delete (if `delete_quotations` permission)
- Convert to Loan (if `convert_to_loan` permission and not already converted)
- View Loan (if already converted)

**Permission scoping**: Users without `view_all_quotations` see only their own quotations.

---

## DataTables Integration

All three tables use **server-side processing** via jQuery DataTables with AJAX.

**Pattern**:
- Each endpoint returns `{ draw, recordsTotal, recordsFiltered, data }` JSON
- Custom filters are sent as extra AJAX parameters alongside DataTables' built-in `search.value`, `start`, `length`, `order`
- Default page length: 25 (tasks/loans), 20 (quotations)
- Mobile cards are rendered client-side from the same AJAX data

**Assets**: `vendor/datatables/css/dataTables.bootstrap5.min.css` loaded via `@push('styles')`

---

## Activity Log

Separate page for system-wide activity logging.

**Route**: `GET /activity-log` — `DashboardController@activityLog`
**Permission**: `view_activity_log`
**View**: `resources/views/activity-log.blade.php`

### AJAX Data

**Route**: `GET /activity-log/data` — `DashboardController@activityLogData`

**Filters**:
- User (dropdown of all users)
- Action type (distinct action values from DB)
- Date range (from/to with Bootstrap Datepicker)
- Search (action text or user name)
- Page length (10/25/50/100)

**Columns**: Date (formatted with time), User, Action (color-coded badge), Subject (model type + ID), Details (customer name, loan amount, or section), IP Address

**Action badge colors**:
| Action | Badge |
|--------|-------|
| `login` | `shf-badge-green` |
| `logout` | `shf-badge-gray` |
| `create_quotation`, `create_user`, `update_user`, `create_loan` | `shf-badge-blue` |
| `delete_quotation`, `delete_user` | `shf-badge-red` |
| `update_settings`, `update_permissions`, `save_product_stages`, `impersonate_start` | `shf-badge-orange` |
| `impersonate_end` | `shf-badge-gray` |

**Dual layout**: Desktop table + mobile cards (same pattern as dashboard tables).

---

## Role-Based Content Summary

| Role | Sees Quotation Stats | Sees Loan Stats | Default Tab |
|------|---------------------|----------------|-------------|
| super_admin | Yes | Yes | Based on tasks |
| admin | Yes | Yes | Based on tasks |
| branch_manager | Yes | Yes | Based on tasks |
| bdo | Yes | Yes | Based on tasks |
| loan_advisor | Yes | Yes | Based on tasks |
| bank_employee | No (typically) | Yes | Tasks or Loans |
| office_employee | No (typically) | Yes | Tasks or Loans |

Visibility depends on actual permission grants, not role alone. The controller checks `hasPermission()` and `hasWorkflowRole()`.

---

## Key Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/DashboardController.php` | All dashboard + activity log logic |
| `resources/views/dashboard.blade.php` | Dashboard view with 3 tabbed DataTables |
| `resources/views/activity-log.blade.php` | Activity log page |
| `public/js/shf-app.js` | DataTable initialization + filter JS |
