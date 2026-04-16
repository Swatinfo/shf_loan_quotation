# Database Reference

Higher-level database architecture overview. For column-level detail, see `.claude/database-schema.md`.

**Driver:** SQLite

---

## Entity-Relationship Overview

### Core / Auth Domain

```
users ──┬── role_user ──── roles ──── role_permission ──── permissions
        ├── user_permissions ──── permissions
        ├── user_branches ──── branches
        ├── bank_employees ──── banks
        ├── location_user ──── locations
        └── (self-ref: created_by)
```

- Users can have multiple roles via `role_user` pivot
- Roles can have multiple permissions via `role_permission` pivot
- Per-user permission overrides (grant/deny) via `user_permissions`
- Legacy tables exist: `role_permissions` (enum-based), `task_role_permissions`

### Organization Domain

```
locations (self-ref: parent_id for state/city hierarchy)
    ├── branches (location_id)
    ├── bank_location ──── banks
    ├── location_product ──── products
    └── location_user ──── users

banks
    ├── products (bank_id)
    ├── bank_employees ──── users
    └── bank_location ──── locations

branches
    ├── user_branches ──── users
    └── manager_id ──── users
```

### Quotation Domain

```
quotations
    ├── quotation_banks
    │       └── quotation_emi
    ├── quotation_documents
    ├── user_id ──── users
    ├── loan_id ──── loan_details
    └── location_id ──── locations

bank_charges (standalone template table, no FK relationships)
```

### Loan Domain

```
loan_details
    ├── loan_documents
    ├── stage_assignments
    │       ├── stage_transfers
    │       └── stage_queries
    │               └── query_responses
    ├── loan_progress (1:1)
    ├── valuation_details
    ├── disbursement_details (1:1)
    ├── remarks
    ├── shf_notifications
    ├── quotation_id ──── quotations
    ├── customer_id ──── customers
    ├── branch_id ──── branches
    ├── bank_id ──── banks
    ├── product_id ──── products
    ├── location_id ──── locations
    ├── created_by ──── users
    ├── assigned_advisor ──── users
    └── assigned_bank_employee ──── users
```

### Workflow Config Domain

```
stages (self-ref: parent_stage_key for parallel sub-stages)

product_stages
    ├── product_id ──── products
    ├── stage_id ──── stages
    ├── default_user_id ──── users
    └── product_stage_users
            ├── branch_id ──── branches
            ├── location_id ──── locations
            └── user_id ──── users
```

---

## Key Foreign Key Chains

### Loan Lifecycle Chain
```
loan_details
  -> stage_assignments (loan_id)
    -> stage_transfers (stage_assignment_id + loan_id)
    -> stage_queries (stage_assignment_id + loan_id)
      -> query_responses (stage_query_id)
```

This is the deepest FK chain in the system (4 levels). All use `cascadeOnDelete` from `loan_details` down.

### Quotation to Loan Conversion
```
quotations -> loan_details (quotation.loan_id)
loan_details -> quotations (loan_details.quotation_id)
```
Bidirectional nullable FKs enable quotation-to-loan conversion tracking.

### Product Workflow Configuration
```
banks -> products (bank_id)
products -> product_stages (product_id)
stages -> product_stages (stage_id)
product_stages -> product_stage_users (product_stage_id)
```

### User Assignment Hierarchy
```
users -> stage_assignments.assigned_to (current assignee)
users -> stage_transfers.transferred_from / transferred_to (transfer history)
users -> stage_queries.raised_by (query author)
users -> query_responses.responded_by (response author)
```

---

## Tables Using SoftDeletes

| Table | Columns |
|-------|---------|
| `loan_details` | `deleted_at`, `deleted_by` |
| `banks` | `deleted_at`, `deleted_by` |
| `branches` | `deleted_at`, `deleted_by` |
| `products` | `deleted_at`, `deleted_by` |
| `quotations` | `deleted_at`, `deleted_by` |
| `customers` | `deleted_at`, `deleted_by` |

All 6 soft-deletable tables also have `deleted_by` (FK to users, nullable) for audit trail.

---

## Tables with Audit Columns

### `updated_by` (FK users, nullable)

`loan_details`, `quotations`, `banks`, `branches`, `products`, `stage_assignments`, `loan_documents`, `valuation_details`, `disbursement_details`, `product_stages`

Auto-set by the `HasAuditColumns` trait on create and update.

### `deleted_by` (FK users, nullable)

`loan_details`, `quotations`, `banks`, `branches`, `products`, `customers`

Auto-set by the `HasAuditColumns` trait on soft delete.

### `created_by` (FK users)

- `users.created_by` -- nullable, nullOnDelete
- `loan_details.created_by` -- required, cascadeOnDelete
- `customers.created_by` -- nullable, nullOnDelete

---

## Index Strategy Overview

### Primary Patterns

1. **Unique constraints on natural keys:** `users.email`, `banks.name`, `roles.slug`, `permissions.slug`, `stages.stage_key`, `loan_details.loan_number`, `locations[name, parent_id]`, `products[bank_id, name]`

2. **Composite unique on pivots:** All pivot tables use composite unique indexes -- `role_user[user_id, role_id]`, `bank_employees[bank_id, user_id]`, `user_branches[user_id, branch_id]`, `product_stages[product_id, stage_id]`, `stage_assignments[loan_id, stage_key]`, etc.

3. **FK indexes:** All foreign key columns are indexed (standard Laravel behavior).

4. **Status/filter indexes:** `loan_details` has separate indexes on `status`, `current_stage`, `customer_type`. `stage_assignments` indexes `stage_key`, `assigned_to`, `status`, `parent_stage_key`.

5. **Composite status indexes:** `loan_documents[loan_id, status]`, `stage_queries[stage_assignment_id, status]`, `shf_notifications[user_id, is_read]`

6. **Polymorphic indexes:** `activity_logs[subject_type, subject_id]`

7. **Timestamp indexes:** `activity_logs.created_at`, `quotations.created_at`

### No Full-Text Indexes

SQLite does not support full-text indexes in the standard migration system. Search is done via `LIKE` queries.

---

## Migration Naming Conventions

Migrations follow Laravel conventions:
- `YYYY_MM_DD_HHMMSS_create_<table>_table.php` for new tables
- `YYYY_MM_DD_HHMMSS_add_<column>_to_<table>_table.php` for adding columns
- `YYYY_MM_DD_HHMMSS_rename_<old>_to_<new>_in_<table>_table.php` for renames

---

## Database Driver Notes (SQLite)

- **No enum type:** Enum columns in migrations use string with application-level validation
- **No native JSON type:** JSON columns stored as text; Laravel handles serialization/deserialization via model casts
- **Foreign keys:** Enabled via `PRAGMA foreign_keys = ON` (Laravel default for SQLite)
- **Boolean type:** Stored as integer (0/1); Laravel casts handle conversion
- **Decimal type:** Stored as real; precision managed at application level
- **No `ALTER COLUMN`:** Column modifications require table recreation (handled by `doctrine/dbal` or raw SQLite workarounds)
- **Single-file database:** Located at `database/database.sqlite`

---

## Table Count Summary

| Domain | Tables | Pivots |
|--------|--------|--------|
| Core/Auth | 5 + 3 legacy | 2 (`role_user`, `role_permission`) |
| Organization | 4 | 4 (`bank_employees`, `user_branches`, `location_user`, `bank_location`, `location_product`) |
| Quotation | 4 | 0 |
| Loan Core | 3 | 0 |
| Workflow | 5 | 0 |
| Specialized | 4 | 0 |
| Config/Logging | 2 | 0 |
| Laravel System | 5 (`sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`) | 0 |
| **Total** | **~35** | **~6** |
