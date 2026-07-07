# Loan Task System — Feature Documentation

## Overview

Integration of a 10-stage loan processing workflow into the quotation system. Converts quotations into trackable loan tasks with document collection, stage management, parallel processing, and disbursement.

## Source

Workflow design from `F:\G Drive\Projects\shf_task` (frontend-only PWA). Backend implementation in this Laravel project.

## Stage Index

| Stage | File | What |
|-------|------|------|
| **IMP** | *(in stage-j-polish.md §7)* | **HIGHEST PRIORITY** — User impersonation with `ALLOW_IMPERSONATE_ALL` env flag (all users or super_admin only) |
| A | [stage-a-foundation.md](stage-a-foundation.md) | Database foundation — banks, branches, products, stages, user extensions |
| B | [stage-b-quotation-to-loan.md](stage-b-quotation-to-loan.md) | Quotation-to-Loan conversion bridge |
| C | [stage-c-loan-management.md](stage-c-loan-management.md) | Loan CRUD — list, create, show, edit, delete |
| D | [stage-d-document-collection.md](stage-d-document-collection.md) | Document collection workflow — tracking, toggle, progress |
| E | [stage-e-stage-workflow.md](stage-e-stage-workflow.md) | Stage workflow engine — assignments, transitions, progress |
| F | [stage-f-parallel-processing.md](stage-f-parallel-processing.md) | Parallel processing (Stage 4) — 4 sub-stages, valuation |
| G | [stage-g-advanced-stages.md](stage-g-advanced-stages.md) | Advanced stages 5-10 — stage forms, disbursement decision tree |
| H | [stage-h-dashboard-notifications.md](stage-h-dashboard-notifications.md) | Dashboard, notifications, remarks |
| I | [stage-i-workflow-config.md](stage-i-workflow-config.md) | Workflow configuration (admin) — product stages, bank/branch CRUD |
| J | [stage-j-polish.md](stage-j-polish.md) | Polish — permissions audit, activity log, scoping, CSS/JS, testing |

## Key Reference

| Topic | File |
|-------|------|
| Role system design | [role-integration.md](role-integration.md) — How existing roles (super_admin/admin/staff) combine with new workflow roles (loan_advisor/bank_employee/etc.) |
| Transfer & auto-assignment | [stage-transfer-assignment.md](stage-transfer-assignment.md) — Auto-assign on stage advance, mid-stage transfer with history, bank employee routing |

## Key Design Decisions

1. **Two-role system**: Existing `users.role` (super_admin/admin/staff) controls system access. New `task_role` column (loan_advisor/bank_employee/office_employee/branch_manager/legal_advisor) controls workflow stage assignment. See [role-integration.md](role-integration.md).
2. **Bank employee association**: Bank employees have `task_bank_id` FK linking them to a specific bank for stage assignment filtering.
3. **SQLite**: No MySQL enums/triggers. String columns with validation.
4. **No geographic tables**: Branch location as simple text fields.
5. **Document bridge**: Quotation documents copy directly to loan documents. No documents_master table.
6. **Existing patterns**: ActivityLog, ConfigService, PermissionService, shf- CSS, jQuery, Bootstrap 5.3, @extends/@section views.

## Dependency Order

```
Stage A (foundation) → Stage B (conversion) → Stage C (CRUD) → Stage D (documents)
                                                                      ↓
Stage E (workflow engine) → Stage F (parallel) → Stage G (advanced stages)
                                                        ↓
                              Stage H (dashboard/notifications) → Stage I (config) → Stage J (polish)
```

Each stage is independently deployable after its dependencies are met.
