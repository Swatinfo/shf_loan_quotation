# Database

## Overview

SQLite database with 30+ tables. See `.claude/database-schema.md` for complete column-level schema.

## Migration Strategy

- Migrations in `database/migrations/` with Laravel standard naming
- Initial tables use `0001_01_01_XXXXXX` prefix (system tables)
- Feature tables use `2026_MM_DD_HHMMSS` prefix
- Incremental migrations for column additions/changes (e.g., `add_X_to_Y_table`)

## Audit Columns

`HasAuditColumns` trait automatically sets:
- `created_by` — set on creation if authenticated
- `updated_by` — set on create and update if column exists
- `deleted_by` — set on soft delete if column exists

Uses `Schema::hasColumn()` for defensive checking.

## Soft Deletes

Tables with soft deletes (`deleted_at`):
- quotations, banks, branches, products, customers, loan_details

## Key Relationships

### User → Organization
- Users have multiple roles via `role_user`
- Users belong to branches via `user_branches`
- Users assigned to banks via `bank_employees`
- Users assigned to locations via `location_user`

### Loan → Everything
- loan_details → quotation, customer, branch, bank, product, location
- loan_details → stage_assignments, loan_documents, valuation_details, disbursement_detail
- loan_details → stage_transfers, stage_queries, remarks, loan_progress

### Stage Configuration
- stages (master) → product_stages (per-product config)
- product_stages → product_stage_users (per-branch/location user assignments)

### Quotation → Loan
- quotations.loan_id → loan_details.id (set on conversion)

## Table Groups

| Group | Tables |
|-------|--------|
| System | users, sessions, password_reset_tokens, cache, jobs |
| Auth | roles, permissions, role_user, role_permission, user_permissions, task_role_permissions |
| Config | app_config, app_settings, bank_charges |
| Organization | banks, branches, locations, products, bank_employees, user_branches, bank_location, location_user, location_product |
| Quotation | quotations, quotation_banks, quotation_emi, quotation_documents |
| Loan | loan_details, customers, loan_documents, valuation_details, disbursement_details, remarks |
| Workflow | stages, stage_assignments, stage_transfers, stage_queries, query_responses, loan_progress, product_stages, product_stage_users |
| Communication | shf_notifications, activity_logs |
| Tasks | general_tasks, general_task_comments |
| DVR | daily_visit_reports |
