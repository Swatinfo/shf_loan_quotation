# Activity Log

## Overview

System-wide activity logging for audit trail and user action tracking.

## Model: `ActivityLog`

### Fields
- user_id — who performed the action
- action — action identifier string
- subject_type, subject_id — polymorphic subject reference
- properties — JSON additional data
- ip_address — client IP
- user_agent — client user agent
- created_at — timestamp

### Static Method
```php
ActivityLog::log($action, $subject = null, $properties = [])
```

Auto-fills: user_id, ip_address, user_agent from request.

## Logged Actions

| Action | When | Properties |
|--------|------|------------|
| login | User logs in | — |
| logout | User logs out | — |
| impersonate_start | Admin starts impersonating | impersonator, impersonated |
| impersonate_end | Admin stops impersonating | impersonator, impersonated |
| quotation_created | Quotation generated | customer_name, loan_amount, bank_count |
| quotation_deleted | Quotation deleted | customer_name |
| loan_created | Loan created | loan_number |
| loan_converted | Quotation → loan | loan_number, quotation_id |
| loan_deleted | Loan deleted | loan_number |
| loan_status_changed | Status updated | loan_number, old_status, new_status |
| stage_updated | Stage status changed | loan_number, stage, status |
| stage_transferred | Stage transferred | loan_number, stage, from, to |
| stage_rejected | Loan rejected | loan_number, stage, reason |
| remark_added | Remark added | loan_number |
| disbursement_processed | Disbursement done | loan_number, type, amount |
| document_status | Doc status changed | loan_number, document |
| task_created | General task created | title |
| task_status_changed | Task status updated | title, status |
| user_created | User created | name, email |
| user_updated | User updated | name |
| user_deleted | User deleted | name |
| user_toggled | User active toggled | name, is_active |
| role_created | Role created | name |
| role_updated | Role updated | name |
| permissions_updated | Permission matrix saved | — |
| settings_updated | Settings section saved | section |
| settings_reset | Settings reset to defaults | — |
| master_stages_updated | Stage config saved | — |
| bank_created/updated | Bank created/updated | name |
| branch_created/updated | Branch created/updated | name |
| product_created/updated | Product created/updated | name |
| product_stages_updated | Product stage config saved | product |
| location_created/updated | Location saved | name, type |

## Activity Log Page

### Controller: `DashboardController`
- `activityLog()` — renders activity log view
- `activityLogData()` — server-side DataTable endpoint

### Permission
`view_activity_log` required.

### DataTable
- Columns: date, user, action, subject, details
- Filterable by user, action type, date range
- Searchable

### View
`activity-log.blade.php` — single page with DataTable.
