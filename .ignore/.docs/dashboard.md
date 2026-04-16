# Dashboard & Activity Log

## Overview

The dashboard is the main landing page after login. It shows quotation statistics, a DataTables-powered quotation list with AJAX loading, loan stats (for users with `view_loans` permission), a "My Pending Stages" list for loan workflow, and links to key actions. The activity log provides an audit trail of all user actions.

## Controller: DashboardController

**File**: `app/Http/Controllers/DashboardController.php`

### Dashboard Index (`GET /dashboard`)

**Permission**: `auth` (any authenticated user)

**Statistics Calculated**:
| Stat | Description | Scope |
|------|-------------|-------|
| `total` | Total quotations | All (if `view_all_quotations`) or own |
| `today` | Quotations created today | All or own |
| `this_month` | Quotations this month | All or own |

**Permission-Based Scoping**:
- Users with `view_all_quotations` → see all quotations + stats for all users
- Users without that permission → see only own quotations + own stats

**Data Passed to View**:
- `$stats` — array with total, today, this_month counts
- `$canViewAll` — boolean for JS DataTables config
- `$users` — list of users (for "Created By" filter, admin only)

### DataTables AJAX (`GET /dashboard/quotation-data`)

**Permission**: `auth`

**Server-Side Processing** — returns JSON for jQuery DataTables.

**Request Parameters**:
| Parameter | Purpose |
|-----------|---------|
| `draw` | DataTables draw counter |
| `start` | Pagination offset |
| `length` | Page size |
| `search[value]` | Global search text |
| `order[0][column]` | Sort column index |
| `order[0][dir]` | Sort direction (asc/desc) |
| `customer_type` | Filter by customer type |
| `date_from` | Filter by start date |
| `date_to` | Filter by end date |
| `created_by` | Filter by user ID (admin only) |

**Searchable Fields**: `customer_name`, `pdf_filename`

**Sortable Columns**:
| Index | Column |
|-------|--------|
| 0 | id |
| 1 | customer_name |
| 2 | customer_type |
| 3 | loan_amount |
| 4 | created_at |

**Response JSON**:
```json
{
  "draw": 1,
  "recordsTotal": 150,
  "recordsFiltered": 42,
  "data": [
    {
      "id": 1,
      "customer_name": "John Doe",
      "customer_type": "proprietor",
      "type_badge": "<span class='shf-badge shf-badge-green'>Proprietor</span>",
      "loan_amount": "₹ 50,00,000",
      "created_at": "27 Feb 2026",
      "created_by": "Admin User",
      "actions": "<a href='...' class='btn btn-accent-sm'>View</a> ..."
    }
  ]
}
```

**Type Badge Colors**:
| Customer Type | Badge Class | Color |
|--------------|-------------|-------|
| proprietor | shf-badge-green | Green |
| partnership_llp | shf-badge-blue | Blue |
| pvt_ltd | shf-badge-orange | Orange |
| salaried | shf-badge-purple | Purple |
| all | shf-badge-gray | Gray |

**Action Buttons** (per row):
- View → always shown (link to `quotations.show`)
- Download → if user has `download_pdf` permission AND quotation has `pdf_filename`
- Delete → if user has `delete_quotations` permission (AJAX DELETE with confirmation)

### Activity Log (`GET /activity-log`)

**Permission**: `view_activity_log`

**Filters**:
| Filter | Type | Options |
|--------|------|---------|
| User | Dropdown | All users list |
| Action | Text | Free-text search on action field |
| Date From | Datepicker | Start date |
| Date To | Datepicker | End date |

**Query**:
- Eager loads `user` relationship
- Filters by `user_id`, `action` (LIKE search), date range
- Ordered by `created_at` descending (newest first)
- Paginated: 50 per page

**View**: `activity-log.blade.php`
- Responsive table with mobile card layout
- Columns: Date/Time, User, Action, Details (from `properties` JSON)
- Pagination links at bottom

## Views

### dashboard.blade.php

**Layout**: extends `layouts.app`

**Sections**:
1. **Header**: "Dashboard" title + "New Quotation" button (if `create_quotation` permission)
2. **Quotation Stats Row**: 3 stat cards (total, today, this month) with icons
3. **Loan Stats Row** (if `view_loans` permission): Active loans, Completed, Rejected, On Hold counts
4. **My Pending Stages** (if user has workflow roles): List of stages assigned to current user that need action
3. **Filter Bar**: Customer type dropdown, date range pickers, "Created By" dropdown (admin only), search box
4. **Quotation Table**: DataTables-powered with AJAX loading
   - Desktop: full table (d-none d-md-block)
   - Mobile: card layout (d-md-none)
5. **Empty State**: "No quotations found" message when no data

**Stat Card Classes**: `shf-stat-card`, `shf-stat-icon`, `shf-stat-value`, `shf-stat-label`

**DataTables Configuration** (in inline script):
- Server-side processing enabled
- AJAX source: `/dashboard/quotation-data`
- Custom filter parameters sent with each request
- Column definitions with sorting and rendering
- Responsive: adjusts columns on mobile

### activity-log.blade.php

**Layout**: extends `layouts.app`

**Sections**:
1. **Header**: "Activity Log" with clock icon
2. **Filters**: User dropdown, Action text input, Date From/To pickers, Filter/Reset buttons
3. **Log Table**: Action, User, Details, Timestamp
4. **Pagination**: Standard Laravel pagination

## Activity Log Model

**File**: `app/Models/ActivityLog.php`

**Static Method**: `log($action, $subject, $properties)`
```php
ActivityLog::log('create_quotation', $quotation, [
    'customer_name' => 'John Doe',
    'loan_amount' => 5000000,
    'filename' => 'Loan_Proposal_John_Doe_06042026_120000.pdf'
]);
```

**Captured Automatically**:
- `user_id` from `auth()->id()`
- `ip_address` from `request()->ip()`
- `user_agent` from `request()->userAgent()`
- `subject_type` and `subject_id` via polymorphic (if model passed)

**Common Actions Logged**:
| Action | Trigger |
|--------|---------|
| `login` | User logs in |
| `logout` | User logs out |
| `create_quotation` | Quotation generated |
| `delete_quotation` | Quotation deleted |
| `user_created` | New user created |
| `user_updated` | User details changed |
| `user_deleted` | User removed |
| `user_activated` | User reactivated |
| `user_deactivated` | User deactivated |
| `settings_updated` | Any settings section saved |
| `settings_reset` | Settings reset to defaults |
| `permissions_updated` | Permission matrix saved |
| `loan_created` | Loan created (directly or from conversion) |
| `loan_converted` | Quotation converted to loan |
| `loan_updated` | Loan details updated |
| `loan_deleted` | Loan soft-deleted |
| `loan_status_changed` | Loan status updated |
| `loan_rejected` | Loan rejected at a stage |
| `stage_started` | Stage moved to in_progress |
| `stage_completed` | Stage completed |
| `stage_assigned` | Stage assigned to user |
| `stage_transferred` | Stage transferred between users |
| `stage_skipped` | Stage skipped |
| `stage_query_raised` | Query raised on a stage |
| `stage_query_responded` | Query response added |
| `stage_query_resolved` | Query resolved |
| `document_status_updated` | Loan document status changed |
| `document_added` | Custom document added to loan |
| `document_removed` | Document removed from loan |
| `remark_added` | Remark added to loan |
| `disbursement_processed` | Disbursement recorded |
| `otc_cleared` | OTC disbursement cleared |
| `impersonate_start` | Admin started impersonating user |
| `impersonate_end` | Admin stopped impersonating |
