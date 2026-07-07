# Loan Workflow System - Developer Reference

Comprehensive technical reference for the 10-stage loan lifecycle system. Covers architecture, stage logic, phase-based workflows, assignment algorithm, disbursement flows, and permission resolution.

---

## Architecture Overview

### Controllers (10)

| Controller | Responsibility |
|------------|---------------|
| `LoanController` | CRUD, list with DataTables, status changes, timeline view |
| `LoanConversionController` | Quotation-to-loan conversion form and processing |
| `LoanStageController` | Stage status updates, assignment, transfer, skip, reject, queries, phase actions (ratePf/sanction/legal/docket/esign), saveNotes |
| `LoanDocumentController` | Document collection CRUD, status updates |
| `LoanValuationController` | Property valuation form, auto-completes technical_valuation stage |
| `LoanDisbursementController` | Disbursement form (fund_transfer/cheque), OTC clearance |
| `LoanRemarkController` | Add/list remarks (general or stage-specific) |
| `NotificationController` | List notifications, mark read, unread count API |
| `LoanSettingsController` | Loan settings index (banks/products/branches/stages/roles), master stage config, task role permissions |
| `WorkflowConfigController` | Bank/product/branch CRUD, product stage configuration |

### Services (10)

| Service | File | Key Methods |
|---------|------|-------------|
| `LoanStageService` | `app/Services/LoanStageService.php` | `initializeStages`, `updateStageStatus`, `handleStageCompletion`, `autoAssignStage`, `findBestAssignee`, `transferStage`, `rejectLoan`, `checkParallelCompletion`, `recalculateProgress` |
| `LoanConversionService` | `app/Services/LoanConversionService.php` | `convertFromQuotation`, `createDirectLoan` |
| `LoanDocumentService` | `app/Services/LoanDocumentService.php` | `populateFromQuotation`, `populateFromDefaults`, `updateStatus`, `getProgress`, `allRequiredResolved` |
| `DisbursementService` | `app/Services/DisbursementService.php` | `processDisbursement` |
| `NotificationService` | `app/Services/NotificationService.php` | `notify`, `notifyStageAssignment`, `notifyStageCompleted`, `notifyLoanCompleted`, `markRead`, `markAllRead`, `getUnreadCount` |
| `RemarkService` | `app/Services/RemarkService.php` | `addRemark`, `getRemarks` |
| `StageQueryService` | `app/Services/StageQueryService.php` | `raiseQuery`, `respondToQuery`, `resolveQuery`, `getQueriesForStage` |
| `LoanTimelineService` | `app/Services/LoanTimelineService.php` | `getTimeline` |
| `PermissionService` | `app/Services/PermissionService.php` | `userHasPermission`, `userRolesHavePermission`, `getUserPermissions`, `clearUserCache`, `clearRoleCache`, `clearAllCaches` |
| `ConfigService` | `app/Services/ConfigService.php` | `load`, `get`, `updateSection`, `updateMany`, `reset` |

### Models (17 loan-specific)

`LoanDetail`, `StageAssignment`, `LoanDocument`, `LoanProgress`, `StageTransfer`, `StageQuery`, `QueryResponse`, `ValuationDetail`, `DisbursementDetail`, `Remark`, `ShfNotification`, `Bank`, `Branch`, `Product`, `Stage`, `ProductStage`, `Role`

See `.docs/models.md` for full model documentation.

---

## Stage System

### All Stages (17 seeded)

| # | stage_key | Name | Type | Parent | Default Role (configurable) |
|---|-----------|------|------|--------|----------------------------|
| 1 | `inquiry` | Loan Inquiry | sequential | -- | -- |
| 2 | `document_selection` | Document Selection | sequential | -- | -- |
| 3 | `document_collection` | Document Collection | sequential | -- | -- |
| 4 | `parallel_processing` | Parallel Processing | parallel | -- | (label only, never assigned) |
| 4a | `app_number` | Application Number | sequential | parallel_processing | branch_manager, loan_advisor |
| 4b | `bsm_osv` | BSM/OSV Approval | sequential | parallel_processing | bank_employee |
| 4c | `legal_verification` | Legal Verification | sequential | parallel_processing | branch_manager, loan_advisor |
| 4d | `technical_valuation` | Technical Valuation | sequential | parallel_processing | branch_manager, office_employee |
| 4e | `sanction_decision` | Loan Sanction Decision | sequential | parallel_processing | office_employee, branch_manager, bdo |
| 4f | `property_valuation` | Property Valuation | sequential | parallel_processing | (product-specific, e.g. LAP) |
| 5 | `rate_pf` | Rate & PF Request | sequential | -- | loan_advisor, branch_manager |
| 6 | `sanction` | Sanction Letter | sequential | -- | loan_advisor, branch_manager |
| 7 | `docket` | Docket Login | sequential | -- | loan_advisor, branch_manager |
| 8 | `kfs` | KFS Generation | sequential | -- | branch_manager, loan_advisor, office_employee |
| 9 | `esign` | E-Sign & eNACH | sequential | -- | branch_manager, loan_advisor, bank_employee |
| 10 | `disbursement` | Disbursement | decision | -- | loan_advisor, branch_manager |
| 11 | `otc_clearance` | OTC Clearance | sequential | -- | branch_manager, loan_advisor, office_employee |

### Parallel Sub-Stage Dependencies

```
4a (app_number) must complete first
    → 4b (bsm_osv) starts
        → 4c, 4d, 4e (+ 4f if applicable) start in parallel
            → All complete → parallel_processing completes → rate_pf (requires is_sanctioned)
```

**Note:** `default_role` is stored as JSON array on the `stages` table and is configurable via Loan Settings > Stage Master. The values above are initial seeder defaults.

### Base Stage Keys (initialized per loan)

```php
$baseStageKeys = [
    'inquiry', 'document_selection', 'document_collection',
    'parallel_processing', 'app_number', 'bsm_osv', 'legal_verification', 'technical_valuation', 'sanction_decision',
    'rate_pf', 'sanction', 'docket', 'kfs', 'esign', 'disbursement', 'otc_clearance',
];
```

16 stage assignments created per loan. `property_valuation` is product-specific and added via product stage config.

### Stage Statuses

`pending` -> `in_progress` -> `completed` | `skipped` | `rejected`

Transitions validated by `StageAssignment::canTransitionTo()`.

### Stage Initialization Flow

1. `initializeStages(LoanDetail)` creates 15 `StageAssignment` records + 1 `LoanProgress` record
2. Sub-stages have `is_parallel_stage=true` and `parent_stage_key='parallel_processing'`
3. Main stage count excludes sub-stages (used for progress percentage)

---

## Stage Completion Logic

### `handleStageCompletion(LoanDetail, completedStageKey)`

Called automatically after any stage is marked `completed` or `skipped`.

**For parallel sub-stages:**
- Calls `checkParallelCompletion()` to check if all sub-stages are done
- If all done: marks parent `parallel_processing` as completed, advances to next main stage

**For sequential stages:**
- Gets next main stage via `getNextStage()`, skipping stages that don't exist in this loan
- Updates `loan.current_stage`, calls `autoAssignStage`, auto-starts the next stage
- If next is `parallel_processing`: sets parent to `in_progress`, calls `autoAssignParallelSubStages()`

**Special terminal logic:**
- `disbursement` completed + `fund_transfer` type: skips `otc_clearance`, sets loan status to `completed`
- `disbursement` completed + `cheque` type: auto-advances to `otc_clearance` via normal flow
- `otc_clearance` completed: sets loan status to `completed`, sends `notifyLoanCompleted`

### Stage Start Prerequisites (`canStartStage`)

- Sub-stages: parent must be `in_progress`
- `rate_pf`: special case -- can start when ANY parallel sub-stage is completed (does not wait for all)
- Other stages: previous main stage must be `completed` or `skipped`
- Post-parallel stages also require `parallel_processing` parent to be completed

---

## Phase-Based Stage Workflows

Several stages have multi-phase workflows tracked in `stage_assignments.notes` (JSON column). Phases represent transfer cycles between task owner and bank employee.

### Rate & PF (`rate_pf`)

**Phases:** `rate_pf_phase` (1 -> 2 -> 3)

| Phase | Owner | Action |
|-------|-------|--------|
| 1 | Task owner (advisor) | Fill all 10 required fields: `interest_rate`, `repo_rate`, `bank_rate`, `rate_offered_date`, `rate_valid_until`, `bank_reference`, `processing_fee`, `admin_charges`, `processing_fee_gst`, `total_pf` |
| 2 | Bank employee | Review/modify rate details. Triggered by `ratePfAction(send_to_bank)`. Snapshots `original_values` for diff hints. |
| 3 | Task owner | Final review. Triggered by `ratePfAction(return_to_owner)`. Stage auto-completes. |

**Controller:** `LoanStageController::ratePfAction()`
- Validates all 10 fields before any action
- `send_to_bank`: snapshots original values, transfers to bank employee
- `return_to_owner`: transfers back to original assignee

### Sanction (`sanction`)

**Phases:** `sanction_phase` (1 -> 2 -> 3)

| Phase | Owner | Action |
|-------|-------|--------|
| 1 | Task owner | Initial state |
| 2 | Bank employee | Generate sanction letter. Triggered by `sanctionAction(send_for_sanction)`. |
| 3 | Task owner | Fill `sanction_date`, `sanctioned_amount`, `emi_amount`. Triggered by `sanctionAction(sanction_generated)`. Auto-calculates docket date. |

**Controller:** `LoanStageController::sanctionAction()`
- `send_for_sanction`: transfers to `loan.assigned_bank_employee` or finds bank_employee by `task_bank_id`
- `sanction_generated`: transfers back to original assignee (skips bank employees)

**Docket date auto-calculation** (in `saveNotes`):
When sanction_date is saved, reads `docket_days_offset` from `app_number` stage notes:
- Offset 1/2/3: `sanction_date + offset days` -> `loan.due_date`
- Offset 0 + `custom_docket_date`: uses the custom date

### Legal Verification (`legal_verification`)

**Phases:** `legal_phase` (1 -> 2 -> 3)

| Phase | Owner | Action |
|-------|-------|--------|
| 1 | Task owner | Suggest legal advisor via `suggested_legal_advisor` text field |
| 2 | Bank employee | Review and initiate legal. Triggered by `legalAction(send_to_bank)`. |
| 3 | Task owner | Confirmed legal advisor stored. Triggered by `legalAction(initiate_legal)`. Stage auto-completes. |

### Docket (`docket`)

**Phases:** `docket_phase` (1 -> 2 -> 3)

| Phase | Owner | Action |
|-------|-------|--------|
| 1 | Task owner | Fill `login_date` (required) |
| 2 | Office employee | Docket login. Triggered by `docketAction(send_to_office)`. Requires `login_date`. |
| 3 | Task owner | Returned after office completion. **Intercepted in `updateStatus`**: when office employee tries to complete phase 2, controller advances to phase 3 and transfers back instead of completing. |

**Completion intercept** (`updateStatus`):
When `docket` stage completion is requested and `docket_phase === '2'`, the controller:
1. Sets `docket_phase` to `'3'`
2. Transfers back to `docket_original_assignee`
3. Returns success without actually completing the stage
4. Stage completes when task owner completes in phase 3 (checked by `isStageDataComplete`)

### E-Sign (`esign`)

**Phases:** `esign_phase` (1 -> 2 -> 3 -> 4)

| Phase | Owner | Action |
|-------|-------|--------|
| 1 | Advisor (task owner) | Sends to bank. Triggered by `esignAction(send_for_esign)`. |
| 2 | Bank employee | Generates E-Sign & eNACH docs. Triggered by `esignAction(esign_generated)`. |
| 3 | Task owner | Completes with customer. Triggered by `esignAction(esign_customer_done)`. |
| 4 | Bank employee | Final confirmation. Triggered by `esignAction(esign_complete)`. Stage completes. |

### Application Number (`app_number`)

No multi-phase transfer. Stage-specific notes:
- `application_number`: synced to `loan_details.application_number`
- `docket_days_offset`: values `1`, `2`, `3`, or `0` (custom). Used to auto-calculate docket date from sanction date.
- `custom_docket_date`: used when `docket_days_offset === '0'`

Auto-completes when both `application_number` and `docket_days_offset` are filled.

### Sanction Decision (`sanction_decision`)

**New parallel sub-stage (4e)** — escalation ladder for loan sanction approval.

**Controller:** `LoanStageController::sanctionDecisionAction()`

| Action | Who | Effect | Required |
|--------|-----|--------|----------|
| `approve` | Any assignee | `loan.is_sanctioned = true`, stage completes | None |
| `escalate_to_bm` | Office Employee | Transfers to Branch Manager | `decision_remarks` |
| `escalate_to_bdo` | Branch Manager | Transfers to BDO | `decision_remarks` |
| `reject` | BM or BDO | Rejects entire loan, all stages locked | `rejection_reason` (min 10 chars) |

**Notes JSON:** `decision_remarks`, `escalation_history[]`, `rejection_reason`, `decision_action`, `decided_by`

**Gate:** `rate_pf` stage requires `loan.is_sanctioned = true` before it can start.

### Phase Transfer User Selection

All phase-based transfers now show a **user selection dropdown** filtered by role, rather than auto-picking:
- API: `GET /loans/{loan}/stages/{stageKey}/eligible-users?role={role}`
- All phase actions accept optional `transfer_to` parameter (falls back to auto-find)

### Loan Cancellation

Only `super_admin`, `admin`, `branch_manager`, or `bdo` can cancel loans. Controller enforces server-side + blade hides button for others.

---

## Stage Completion Validation

### `isStageDataComplete(stageKey, assignment)` — Auto-completion check

| Stage | Completion Criteria |
|-------|-------------------|
| `app_number` | `application_number` non-empty AND `docket_days_offset` non-empty |
| `bsm_osv` | Always true (no required data) |
| `legal_verification` | `legal_phase === '3'` |
| `technical_valuation` / `property_valuation` | Always false (auto-completed by ValuationController) |
| `rate_pf` | `rate_pf_phase === '3'` |
| `sanction` | `sanction_phase === '3'` AND `sanction_date` non-empty AND `sanctioned_amount` non-empty |
| `docket` | `docket_phase` is `'2'` or `'3'` |
| `kfs` | Always true |
| `sanction_decision` | Always false (completed via `sanctionDecisionAction`, not `saveNotes`) |
| `esign` | `esign_phase === '4'` |
| `otc_clearance` | `handover_date` non-empty |

### `getFieldErrors(stageKey, data)` — Required field validation on save

| Stage | Required Fields |
|-------|----------------|
| `app_number` | `application_number`, `docket_days_offset` |
| `sanction` (phase 3 only) | `sanction_date`, `sanctioned_amount`, `emi_amount` |
| `docket` | `login_date` |
| `otc_clearance` | `handover_date` |
| `bsm_osv`, `legal_verification`, `rate_pf`, `kfs`, `esign` | None (notes optional or validated elsewhere) |
| `technical_valuation`, `property_valuation` | Validated via `valuation_details` table, not notes |

### Pre-completion Checks (`updateStatus` controller)

Before allowing `status=completed`:
1. **Pending queries**: blocks if `hasPendingQueries()` returns true
2. **Document collection**: blocks unless `allRequiredResolved()` (all required docs received/waived)
3. **Valuation stages** (`technical_valuation`, `property_valuation`): blocks unless `valuation_details` record exists with `final_valuation`
4. **Other stages**: blocks unless `isStageDataComplete()` returns true. Returns specific missing field names.

---

## Auto-Assignment Algorithm

### `findBestAssignee(stageKey, branchId, bankId, productId, loanCreatorId)`

Returns the best user ID for a stage, evaluated in priority order:

| Priority | Source | Condition |
|----------|--------|-----------|
| -1 | Product Stage Config | `ProductStage` has branch/city/state-specific user or default user assigned |
| 0 | Bank Default Employee | `banks.default_employee_id` (if stage has `bank_employee` role) |
| 1 | Bank Employee + Branch | User in `bank_employees` pivot for the bank AND in `user_branches` for the branch |
| 2 | Bank Employee Any Branch | User in `bank_employees` pivot for the bank (any branch) |
| 0b | Default Office Employee | `user_branches.is_default_office_employee=true` for the branch (if stage has `office_employee` role) |
| 3 | Loan Creator | Falls back to loan creator if they match an eligible role (`loan_advisor` or `branch_manager`) |
| 4 | Branch Match | Any user with eligible role in the same branch |
| 5 | Any Match | Any active user with an eligible role |

**Role eligibility** is read from `stages.default_role` (JSON array) via `getStageRoleEligibility()`.

### `autoAssignParallelSubStages(LoanDetail)`

When `parallel_processing` starts:
- All sub-stages with no assignee get auto-assigned via `findBestAssignee`
- All sub-stages are set to `in_progress` with `started_at = now()`

---

## Loan Creation Flows

### Direct Creation (`LoanConversionService::createDirectLoan`)

1. Creates `LoanDetail` with `current_stage = 'inquiry'`
2. Populates documents from config defaults by customer_type
3. Initializes 15 stage assignments + progress
4. No stages auto-completed

### Quotation Conversion (`LoanConversionService::convertFromQuotation`)

1. Creates `LoanDetail` with `current_stage = 'document_collection'`
2. Copies customer data + selected bank's charges/ROI
3. Populates documents from quotation
4. Initializes stages, auto-completes `inquiry` + `document_selection`
5. Auto-assigns `document_collection` stage
6. Sets `quotation.loan_id` back-reference

---

## Disbursement Flow

### Types: `fund_transfer`, `cheque`

**Fund Transfer:**
- Fields: `loan_account_number` (stored as `bank_account_number`)
- Flow: DisbursementService completes `disbursement` stage -> `handleStageCompletion` skips `otc_clearance` -> loan status = `completed`

**Cheque:**
- Fields: `cheques` JSON array `[{cheque_number, cheque_date, cheque_amount}]`
- Validation: sum of `cheque_amount` must be <= `amount_disbursed`
- Flow: DisbursementService completes `disbursement` stage -> `handleStageCompletion` auto-advances to `otc_clearance` -> OTC completion -> loan status = `completed`

### OTC Clearance

- Required field: `handover_date` (in stage notes)
- When `otc_clearance` is completed, `handleStageCompletion` sets loan to `completed` and calls `notifyLoanCompleted`

---

## Valuation Form

**Model:** `ValuationDetail`
**Controller:** `LoanValuationController`

- Always `valuation_type = 'property'`
- Property types: `residential_bunglow`, `residential_flat`, `commercial`, `industrial`, `land`, `mixed`
- Auto-calculated fields:
  - `land_valuation = land_area * land_rate`
  - `construction_valuation = construction_area * construction_rate`
  - `final_valuation = land_valuation + construction_valuation`
  - `market_value = final_valuation`
- Google Maps embed uses `latitude` + `longitude`
- On save: auto-completes `technical_valuation` stage if pending/in_progress

---

## Query System

Queries block stage completion until resolved.

### Flow

1. **Raise** (`StageQueryService::raiseQuery`): creates `StageQuery` with `status=pending`
2. **Respond** (`respondToQuery`): adds `QueryResponse`, sets query `status=responded`
3. **Resolve** (`resolveQuery`): sets query `status=resolved`, unblocks stage
4. Only the user who raised the query can resolve it (enforced in controller)

### Blocking Check

`StageAssignment::hasPendingQueries()` returns true if any queries have `status` in `['pending', 'responded']`.

---

## Permission Resolution (3-tier)

### `PermissionService::userHasPermission(User, slug)`

| Priority | Check | Result |
|----------|-------|--------|
| 1 | `user.hasRole('super_admin')` | Always `true` |
| 2 | `user_permissions` table (grant/deny) | Explicit override |
| 3 | `role_permission` pivot (any of user's roles) | Role grant |

Uses the unified `roles` / `role_user` / `role_permission` tables. No separate system role vs task role.

### Caching

- User overrides: `user_perms:{userId}` (5 min TTL)
- User role IDs: `user_role_ids:{userId}` (5 min TTL)
- Role permissions: `role_perms:{roleIds}` (5 min TTL, sorted comma-joined IDs)
- Clear all: `PermissionService::clearAllCaches()`

### Unified Roles (7)

| Role Slug | Description |
|-----------|-------------|
| `super_admin` | Full system access, bypasses all permissions |
| `admin` | System administration, settings, user management |
| `branch_manager` | Branch-level management, quotations, loan stages |
| `bdo` | Business Development Officer (same access as Branch Manager) |
| `loan_advisor` | Quotation creation, loan processing stages |
| `bank_employee` | Bank-side loan processing only |
| `office_employee` | Office operations, valuations, docket review, OTC |

### Loan Creation Authorization

```php
User::canCreateLoans(): bool
// true if super_admin, admin, or hasAnyRole(ADVISOR_ELIGIBLE_ROLES)
```

`ADVISOR_ELIGIBLE_ROLES = ['branch_manager', 'bdo', 'loan_advisor']`

`User::scopeAdvisorEligible()` filters to users with these roles + `is_active=true`.

---

## Loan Visibility (Authorization)

### `authorizeView(LoanDetail)` — used in LoanController and LoanStageController

A user can view a loan if ANY of these are true:
1. Has `view_all_loans` permission
2. Is the loan creator (`created_by`)
3. Is the assigned advisor (`assigned_advisor`)
4. Is assigned to any stage of the loan
5. Is a `branch_manager` with a branch matching `loan.branch_id`

### `LoanDetail::scopeVisibleTo(User)`

Applies the same logic as a query scope for list filtering.

---

## Timeline

### `LoanTimelineService::getTimeline(LoanDetail)`

Builds a chronologically sorted collection of timeline entries from:

1. **Quotation created** (if converted) or **Loan created** (if direct)
2. **Stage started/completed/skipped** from `stage_assignments.started_at` / `completed_at`
3. **Transfers** from `stage_transfers`
4. **Queries raised + responses** from `stage_queries` + `query_responses`
5. **Remarks** from `remarks`
6. **Rejection** (if status = rejected)
7. **Disbursement** from `disbursement_details`
8. **Completion** (if status = completed)

Each entry has: `type`, `date`, `title`, `description`, `user`, `icon`, `color`.

---

## Routes

### Loan CRUD

| Method | URI | Controller@Action | Permission |
|--------|-----|-------------------|------------|
| GET | `/loans` | LoanController@index | view_loans |
| GET | `/loans/data` | LoanController@loanData | view_loans |
| GET | `/loans/create` | LoanController@create | create_loan |
| POST | `/loans` | LoanController@store | create_loan |
| GET | `/loans/{loan}` | LoanController@show | view_loans |
| GET | `/loans/{loan}/timeline` | LoanController@timeline | view_loans |
| GET | `/loans/{loan}/edit` | LoanController@edit | edit_loan |
| PUT | `/loans/{loan}` | LoanController@update | edit_loan |
| POST | `/loans/{loan}/status` | LoanController@updateStatus | edit_loan |
| DELETE | `/loans/{loan}` | LoanController@destroy | delete_loan |

### Quotation Conversion

| Method | URI | Controller@Action | Permission |
|--------|-----|-------------------|------------|
| GET | `/quotations/{quotation}/convert` | LoanConversionController@showConvertForm | convert_to_loan |
| POST | `/quotations/{quotation}/convert` | LoanConversionController@convert | convert_to_loan |

### Stage Workflow

| Method | URI | Controller@Action | Permission |
|--------|-----|-------------------|------------|
| GET | `/loans/{loan}/stages` | LoanStageController@index | view_loans |
| GET | `/loans/{loan}/transfers` | LoanStageController@transferHistory | view_loans |
| POST | `/loans/{loan}/stages/{stageKey}/status` | LoanStageController@updateStatus | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/assign` | LoanStageController@assign | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/transfer` | LoanStageController@transfer | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/reject` | LoanStageController@reject | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/query` | LoanStageController@raiseQuery | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/notes` | LoanStageController@saveNotes | manage_loan_stages |
| POST | `/loans/{loan}/stages/{stageKey}/skip` | LoanStageController@skip | skip_loan_stages |
| POST | `/loans/queries/{query}/respond` | LoanStageController@respondToQuery | manage_loan_stages |
| POST | `/loans/queries/{query}/resolve` | LoanStageController@resolveQuery | manage_loan_stages |

### Phase Action Routes (on LoanStageController)

| Method | URI | Controller@Action | Permission |
|--------|-----|-------------------|------------|
| POST | `/loans/{loan}/stages/rate-pf/action` | LoanStageController@ratePfAction | manage_loan_stages |
| POST | `/loans/{loan}/stages/sanction/action` | LoanStageController@sanctionAction | manage_loan_stages |
| POST | `/loans/{loan}/stages/legal/action` | LoanStageController@legalAction | manage_loan_stages |
| POST | `/loans/{loan}/stages/docket/action` | LoanStageController@docketAction | manage_loan_stages |
| POST | `/loans/{loan}/stages/esign/action` | LoanStageController@esignAction | manage_loan_stages |

### Documents

| Method | URI | Controller@Action | Permission |
|--------|-----|-------------------|------------|
| GET | `/loans/{loan}/documents` | LoanDocumentController@index | view_loans |
| POST | `/loans/{loan}/documents/{document}/status` | LoanDocumentController@updateStatus | manage_loan_documents |
| POST | `/loans/{loan}/documents` | LoanDocumentController@store | manage_loan_documents |
| DELETE | `/loans/{loan}/documents/{document}` | LoanDocumentController@destroy | manage_loan_documents |

### Valuation

| Method | URI | Controller@Action | Permission |
|--------|-----|-------------------|------------|
| GET | `/loans/{loan}/valuation` | LoanValuationController@show | manage_loan_stages |
| POST | `/loans/{loan}/valuation` | LoanValuationController@store | manage_loan_stages |

### Disbursement

| Method | URI | Controller@Action | Permission |
|--------|-----|-------------------|------------|
| GET | `/loans/{loan}/disbursement` | LoanDisbursementController@show | manage_loan_stages |
| POST | `/loans/{loan}/disbursement` | LoanDisbursementController@store | manage_loan_stages |

### Remarks

| Method | URI | Controller@Action | Permission |
|--------|-----|-------------------|------------|
| GET | `/loans/{loan}/remarks` | LoanRemarkController@index | view_loans |
| POST | `/loans/{loan}/remarks` | LoanRemarkController@store | add_remarks |

### Notifications

| Method | URI | Controller@Action | Permission |
|--------|-----|-------------------|------------|
| GET | `/notifications` | NotificationController@index | auth |
| GET | `/api/notifications/count` | NotificationController@unreadCount | auth |
| POST | `/notifications/{notification}/read` | NotificationController@markRead | auth |
| POST | `/notifications/read-all` | NotificationController@markAllRead | auth |

### Loan Settings & Workflow Config

| Method | URI | Controller@Action | Permission |
|--------|-----|-------------------|------------|
| GET | `/loan-settings` | LoanSettingsController@index | view_loans |
| POST | `/loan-settings/banks` | WorkflowConfigController@storeBank | manage_workflow_config |
| DELETE | `/loan-settings/banks/{bank}` | WorkflowConfigController@destroyBank | manage_workflow_config |
| POST | `/loan-settings/products` | WorkflowConfigController@storeProduct | manage_workflow_config |
| GET | `/loan-settings/products/{product}/stages` | WorkflowConfigController@productStages | manage_workflow_config |
| POST | `/loan-settings/products/{product}/stages` | WorkflowConfigController@saveProductStages | manage_workflow_config |
| POST | `/loan-settings/branches` | WorkflowConfigController@storeBranch | manage_workflow_config |
| DELETE | `/loan-settings/branches/{branch}` | WorkflowConfigController@destroyBranch | manage_workflow_config |
| DELETE | `/loan-settings/products/{product}` | WorkflowConfigController@destroyProduct | manage_workflow_config |
| POST | `/loan-settings/users/{user}/role` | LoanSettingsController@updateUserRole | manage_workflow_config |
| POST | `/loan-settings/master-stages` | LoanSettingsController@saveMasterStages | manage_workflow_config |

---

## Database Tables (Loan System)

### Core

| Table | Key Columns | Notes |
|-------|-------------|-------|
| `loan_details` | `loan_number` (unique, SHF-YYYYMM-XXXX), `customer_name`, `customer_type`, `loan_amount`, `status`, `current_stage`, `bank_id`, `product_id`, `branch_id`, `created_by`, `assigned_advisor`, `assigned_bank_employee`, `due_date`, `rejected_*` | Soft deletes, audit columns |
| `stage_assignments` | `loan_id`, `stage_key` (unique per loan), `assigned_to`, `status`, `notes` (JSON), `is_parallel_stage`, `parent_stage_key`, `started_at`, `completed_at` | Audit columns |
| `loan_documents` | `loan_id`, `document_name_en/gu`, `is_required`, `status`, `received_date`, `received_by` | Audit columns |
| `loan_progress` | `loan_id` (unique), `total_stages`, `completed_stages`, `overall_percentage`, `workflow_snapshot` (JSON) | |

### Workflow History

| Table | Key Columns | Notes |
|-------|-------------|-------|
| `stage_transfers` | `stage_assignment_id`, `loan_id`, `stage_key`, `transferred_from`, `transferred_to`, `reason`, `transfer_type` (manual/auto) | No timestamps, `created_at` only |
| `stage_queries` | `stage_assignment_id`, `loan_id`, `stage_key`, `query_text`, `raised_by`, `status` (pending/responded/resolved) | |
| `query_responses` | `stage_query_id`, `response_text`, `responded_by` | No timestamps, `created_at` only |
| `remarks` | `loan_id`, `stage_key` (nullable), `user_id`, `remark` | |

### Ancillary

| Table | Key Columns | Notes |
|-------|-------------|-------|
| `valuation_details` | `loan_id`, `valuation_type`, `property_type`, `land_area`, `land_rate`, `land_valuation`, `construction_area`, `construction_rate`, `construction_valuation`, `final_valuation`, `latitude`, `longitude` | Audit columns |
| `disbursement_details` | `loan_id` (unique), `disbursement_type` (fund_transfer/cheque), `amount_disbursed`, `bank_account_number`, `cheques` (JSON), `is_otc`, `otc_cleared`, `otc_cleared_date` | Audit columns |
| `shf_notifications` | `user_id`, `title`, `message`, `type`, `is_read`, `loan_id`, `stage_key`, `link` | |

### Configuration

| Table | Key Columns | Notes |
|-------|-------------|-------|
| `banks` | `name` (unique), `code`, `is_active`, `default_employee_id` | Soft deletes, audit |
| `branches` | `name`, `code`, `is_active`, `manager_id` | Soft deletes, audit |
| `products` | `bank_id`, `name` (unique per bank), `is_active` | Soft deletes, audit |
| `stages` | `stage_key` (unique), `stage_name_en/gu`, `sequence_order`, `is_parallel`, `parent_stage_key`, `default_role` (JSON), `is_enabled` | |
| `product_stages` | `product_id`, `stage_id` (unique pair), `is_enabled`, `default_user_id`, `auto_skip`, `allow_skip` | Audit columns |
| `product_stage_users` | `product_stage_id`, `branch_id` (unique pair), `user_id` | |
| `bank_employees` | `bank_id`, `user_id` (unique pair), `is_default` | |
| `user_branches` | `user_id`, `branch_id` (unique pair), `is_default_office_employee` | |
| `task_role_permissions` | `task_role`, `permission_id` | Additive permissions for task roles |

---

## Loan Permissions (22)

| Slug | Admin | Branch Mgr / BDO | Loan Advisor | Bank Emp | Office Emp | Description |
|------|-------|-------------------|-------------|---------|------------|-------------|
| `convert_to_loan` | yes | yes | yes | no | no | Convert quotation to loan |
| `view_loans` | yes | yes | yes | yes | yes | View loan list |
| `view_all_loans` | yes | yes | no | no | no | View all loans (not just own) |
| `create_loan` | yes | yes | yes | no | no | Create new loan |
| `edit_loan` | yes | yes | yes | no | yes | Edit loan details |
| `delete_loan` | yes | no | no | no | no | Delete loan |
| `manage_loan_documents` | yes | yes | yes | no | yes | Manage document collection |
| `upload_loan_documents` | yes | yes | yes | no | yes | Upload document files |
| `download_loan_documents` | yes | yes | yes | yes | yes | Download/preview documents |
| `delete_loan_files` | yes | yes | no | no | no | Remove uploaded files |
| `manage_loan_stages` | yes | yes | yes | no | yes | Stage operations (status, assign) |
| `transfer_loan_stages` | yes | yes | yes | no | yes | Transfer stage to another user |
| `reject_loan` | yes | yes | yes | no | yes | Reject a loan application |
| `change_loan_status` | yes | yes | yes | no | yes | Put loan on hold or cancel |
| `view_loan_timeline` | yes | yes | yes | yes | yes | View loan stage timeline |
| `manage_disbursement` | yes | yes | yes | no | no | Process loan disbursement |
| `manage_valuation` | yes | yes | no | no | yes | Fill/edit valuation details |
| `raise_query` | yes | yes | yes | yes | yes | Raise queries on stages |
| `resolve_query` | yes | yes | yes | no | yes | Resolve raised queries |
| `add_remarks` | yes | yes | yes | yes | yes | Add remarks to loans |
| `manage_workflow_config` | yes | no | no | no | no | Manage loan settings |

**super_admin** always has all permissions (bypass in PermissionService).

---

## Key Constants

### `LoanDetail`

```php
STATUS_ACTIVE    = 'active'
STATUS_COMPLETED = 'completed'
STATUS_REJECTED  = 'rejected'
STATUS_CANCELLED = 'cancelled'
STATUS_ON_HOLD   = 'on_hold'

CUSTOMER_TYPE_LABELS = [
    'proprietor'      => 'Proprietor / ...',
    'partnership_llp'  => 'Partnership LLP / ...',
    'pvt_ltd'         => 'Pvt Ltd / ...',
    'salaried'        => 'Salaried / ...',
]
```

### `StageAssignment`

```php
STATUS_PENDING     = 'pending'
STATUS_IN_PROGRESS = 'in_progress'
STATUS_COMPLETED   = 'completed'
STATUS_REJECTED    = 'rejected'
STATUS_SKIPPED     = 'skipped'
```

### `User`

```php
ADVISOR_ELIGIBLE_ROLES = ['branch_manager', 'bdo', 'loan_advisor', 'office_employee']
// Roles managed via roles() BelongsToMany relationship (7 unified roles)
```

---

## Activity Log Actions

All workflow operations are logged via `ActivityLog::log()`:

| Action | Subject | When |
|--------|---------|------|
| `create_loan` | LoanDetail | Direct loan creation |
| `convert_quotation_to_loan` | LoanDetail | Quotation conversion |
| `edit_loan` | LoanDetail | Loan details updated |
| `change_loan_status` | LoanDetail | Status change (active/on_hold/cancelled) |
| `delete_loan` | null | Loan deleted |
| `update_stage_status` | StageAssignment | Any stage status change |
| `assign_stage` | StageAssignment | Manual assignment |
| `auto_assign_stage` | StageAssignment | Auto-assignment on advance |
| `transfer_stage` | StageAssignment | Stage transfer (manual or auto) |
| `reject_loan` | LoanDetail | Loan rejected at a stage |
| `process_disbursement` | DisbursementDetail | Disbursement processed |
| `save_valuation` | ValuationDetail | Valuation form saved |
