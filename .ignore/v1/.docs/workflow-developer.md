# Loan Workflow Developer Reference

Technical reference for the loan stage workflow system. Covers stage keys, initialization, assignment algorithms, transitions, and all related models and services.

## Key Files

| File | Purpose |
|------|---------|
| `app/Services/LoanStageService.php` | Core workflow engine -- transitions, assignment, parallel logic, progress |
| `app/Services/StageQueryService.php` | Query raise/respond/resolve lifecycle |
| `app/Http/Controllers/LoanStageController.php` | HTTP layer -- validation, multi-phase actions, authorization |
| `app/Models/Stage.php` | Stage definition (DB-driven, not hardcoded) |
| `app/Models/StageAssignment.php` | Per-loan stage instance with status, assignee, notes |
| `app/Models/ProductStage.php` | Product-specific stage config (skip, assignee, enabled) |
| `app/Models/ProductStageUser.php` | Location-aware user assignment per product-stage |
| `app/Models/StageTransfer.php` | Transfer history records |
| `app/Models/StageQuery.php` | Query records (blocks stage completion) |
| `app/Models/QueryResponse.php` | Responses to queries |

## Stage Keys and Ordering

Stages are stored in the `stages` table and ordered by `sequence_order`. The base 16 stage keys initialized for every loan:

| # | Stage Key | Type | Parent |
|---|-----------|------|--------|
| 1 | `inquiry` | main | -- |
| 2 | `document_selection` | main | -- |
| 3 | `document_collection` | main | -- |
| 4 | `parallel_processing` | main (parent) | -- |
| 4a | `app_number` | sub | `parallel_processing` |
| 4b | `bsm_osv` | sub | `parallel_processing` |
| 4c | `legal_verification` | sub | `parallel_processing` |
| 4d | `technical_valuation` | sub | `parallel_processing` |
| 4e | `sanction_decision` | sub (decision) | `parallel_processing` |
| 5 | `rate_pf` | main | -- |
| 6 | `sanction` | main | -- |
| 7 | `docket` | main | -- |
| 8 | `kfs` | main | -- |
| 9 | `esign` | main | -- |
| 10 | `disbursement` | main | -- |
| 11 | `otc_clearance` | main | -- |

Main stage ordering is determined by `Stage::enabled()->mainStages()` which filters `parent_stage_key IS NULL` and orders by `sequence_order`.

### Stage Model (`Stage`)

```
stage_key         string    Unique identifier
stage_name_en     string    English display name
stage_name_gu     string    Gujarati display name
sequence_order    int       Sort order
is_enabled        bool      Whether stage is active
is_parallel       bool      True for parallel_processing parent
parent_stage_key  string    Links sub-stages to parent
stage_type        string    'decision' for sanction_decision
default_role      array     JSON array of role slugs eligible for this stage
sub_actions       array     JSON array of available sub-actions
```

Scopes: `enabled()`, `mainStages()`, `subStagesOf($parentKey)`

## Stage Initialization

`LoanStageService::initializeStages(LoanDetail $loan)`

Called when a new loan is created. Creates:

1. **StageAssignment** records for all 16 base stage keys (only enabled ones)
   - Sub-stages get `is_parallel_stage = true` and `parent_stage_key = 'parallel_processing'`
   - All start with `status = 'pending'`
2. **LoanProgress** record with `total_stages` = count of main stages (non-parallel), `completed_stages = 0`

### Auto-Complete on Quotation Conversion

`autoCompleteStages(LoanDetail $loan, array $stageKeys)` -- marks specified stages as completed with current timestamp. Used when converting a quotation to a loan (inquiry stage may already be satisfied).

## Auto-Assignment Algorithm

`findBestAssignee(string $stageKey, ?int $branchId, ?int $bankId, ?int $productId, ?int $loanCreatorId): ?int`

Returns the user ID of the best-fit assignee. Priority order:

### Priority -1: Product-Stage-User (location hierarchy)
If `$productId` is set:
1. Look up `ProductStage` for this product + stage
2. Resolve branch location hierarchy: `branch_id` -> `city_id` (via `branch.location_id`) -> `state_id` (via `location.parent_id`)
3. Check `ProductStageUser` records in order: branch-specific -> city-level -> state-level -> product default
4. Return if active user found

### Priority 0: Bank's Default Employee
If stage has `bank_employee` in eligible roles and `$bankId` is set:
- Check `banks.default_employee_id` -- return if active

### Priority 1: Bank Employee (bank + branch match)
- `User` with `employerBanks` pivot matching `$bankId` AND `branches` pivot matching `$branchId`

### Priority 2: Bank Employee (bank only)
- `User` with `employerBanks` pivot matching `$bankId` (any branch)

### Priority 2.5: Other eligible roles in branch
- Non-bank-employee roles from eligibility list, filtered by `$branchId`

### Priority 0b: Default Office Employee
If `office_employee` in eligible roles and `$branchId` set:
- `User` with `office_employee` role where `user_branches.is_default_office_employee = true` for the branch

### Priority 3: Loan Creator Fallback
If `loan_advisor`, `branch_manager`, or `bdo` in eligible roles:
- Return `$loanCreatorId` if that user is active and has one of those roles

### Priority 4: Branch-based role match
- Any user with eligible role + matching branch

### Priority 5: Any matching role
- First active user with any eligible role (no location filter)

## Stage Transitions

`updateStageStatus(LoanDetail $loan, string $stageKey, string $newStatus, ?int $userId): StageAssignment`

### Allowed Transitions (from `StageAssignment::canTransitionTo`)

```
pending     -> in_progress, skipped
in_progress -> completed, rejected
rejected    -> in_progress
completed   -> (terminal)
skipped     -> (terminal)
```

### Transition Side Effects

On status change:
- `in_progress`: Sets `started_at` if not already set
- `completed` / `rejected` / `skipped`: Sets `completed_at` and `completed_by`
- `completed` / `skipped`: Triggers `handleStageCompletion()`

### Completion Blocking

Before allowing `completed` status:
1. **Pending queries check**: `StageAssignment::hasPendingQueries()` -- queries with status `pending` or `responded` block completion
2. **Stage-specific data checks** (in controller):
   - `document_collection`: All required documents must be resolved (`LoanDocumentService::allRequiredResolved`)
   - `technical_valuation` / `property_valuation`: Valuation record with `final_valuation` must exist
   - Other stages: `isStageDataComplete()` checks notes data per stage

### Auto-Completion via `isStageDataComplete()`

Some stages auto-complete when required notes data is filled (via `saveNotes`):

| Stage Key | Completion Condition |
|-----------|---------------------|
| `app_number` | `application_number` + `docket_days_offset` both filled |
| `bsm_osv` | Always true (completes on any save) |
| `legal_verification` | `legal_phase === '3'` |
| `technical_valuation` | Never (completed by ValuationController) |
| `rate_pf` | `rate_pf_phase === '3'` |
| `sanction` | `sanction_phase === '3'` + `sanction_date` + `sanctioned_amount` filled |
| `docket` | `docket_phase` is `'2'` or `'3'` |
| `kfs` | Always true |
| `sanction_decision` | Never (completed by `sanctionDecisionAction`) |
| `esign` | `esign_phase === '4'` |
| `otc_clearance` | `handover_date` filled |

## Parallel Processing Implementation

### Initialization

When the workflow reaches `parallel_processing`:
1. Parent assignment is set to `in_progress`
2. `autoAssignParallelSubStages()` is called -- only starts `app_number`; all others remain `pending`

### Sequential-then-Parallel Unlock

```
app_number (4a) -> bsm_osv (4b) -> [legal_verification (4c), technical_valuation (4d), sanction_decision (4e)] in parallel
```

- `app_number` completes -> `startSingleParallelSubStage('bsm_osv')` starts 4b only
- `bsm_osv` completes -> `startRemainingParallelSubStages()` starts all remaining pending sub-stages (4c, 4d, 4e)

### Completion Check

`checkParallelCompletion(LoanDetail $loan): bool`

Called after every sub-stage status change:
1. Gets all sub-stages with `parent_stage_key = 'parallel_processing'`
2. Checks if ALL are `completed` or `skipped`
3. If yes: marks parent as `completed`, advances to next main stage, recalculates progress
4. Returns true/false

### Rate & PF Pre-condition

`rate_pf` has a special `canStartStage` check:
- `$loan->is_sanctioned` must be `true` (set by `sanction_decision` approve action)
- At least one parallel sub-stage must be completed

## Multi-Phase Stage Flows

Several stages use a phase system stored in `StageAssignment.notes` (JSON). Each phase typically transfers the stage to a different role.

### E-Sign (4 phases)
| Phase | Actor | Action |
|-------|-------|--------|
| 1 | Loan Advisor | `send_for_esign` -> transfers to bank employee |
| 2 | Bank Employee | `esign_generated` -> transfers back to advisor |
| 3 | Loan Advisor | `esign_customer_done` -> transfers to bank employee |
| 4 | Bank Employee | `esign_complete` -> completes the stage |

### Legal Verification (3 phases)
| Phase | Actor | Action |
|-------|-------|--------|
| 1 | Loan Advisor | `send_to_bank` -> transfers to bank employee |
| 2 | Bank Employee | `initiate_legal` -> transfers back to advisor |
| 3 | Loan Advisor | Completes stage (auto-complete on phase 3) |

### Sanction (3 phases)
| Phase | Actor | Action |
|-------|-------|--------|
| 1 | Loan Advisor | `send_for_sanction` -> transfers to bank employee |
| 2 | Bank Employee | `sanction_generated` -> transfers back to advisor |
| 3 | Loan Advisor | Fills sanction details -> auto-completes |

### Rate & PF (3 phases)
| Phase | Actor | Action |
|-------|-------|--------|
| 1 | Loan Advisor | Fills rate/fee details |
| 2 | Bank Employee | `send_to_bank` -> reviews |
| 3 | Task Owner | `return_to_owner` -> auto-completes |

### Docket (3 phases)
| Phase | Actor | Action |
|-------|-------|--------|
| 1 | Loan Advisor | Fills login date, `send_to_office` |
| 2 | Office Employee | Performs docket login, "completes" (triggers phase 3, not real completion) |
| 3 | Loan Advisor | Gets stage back, completes for real |

### Sanction Decision (escalation)
| Action | Who Can | Effect |
|--------|---------|--------|
| `approve` | Any assignee | Sets `is_sanctioned = true`, completes stage |
| `escalate_to_bm` | Any assignee | Transfers to Branch Manager, records escalation history |
| `escalate_to_bdo` | Any assignee | Transfers to BDO, records escalation history |
| `reject` | BM/BDO/Admin only | Rejects entire loan, rejects all pending stages |

## Transfer Mechanics

### Manual Transfer

`transferStage(LoanDetail $loan, string $stageKey, int $toUserId, ?string $reason): StageAssignment`

1. Updates `StageAssignment.assigned_to`
2. Creates `StageTransfer` record with `transfer_type = 'manual'`
3. Reassigns open queries on this stage to the new assignment
4. Logs activity, touches loan `updated_at`

### Auto Transfer

Created during `autoAssignStage()`:
- Same `StageTransfer` record but with `transfer_type = 'auto'`
- Reason: `'Auto-assigned on stage advance'`

### StageTransfer Model

```
stage_assignment_id  FK -> stage_assignments
loan_id              FK -> loan_details
stage_key            string
transferred_from     FK -> users
transferred_to       FK -> users
reason               string (nullable)
transfer_type        'manual' | 'auto'
created_at           datetime
```

No `updated_at` (timestamps disabled).

## Query Blocking Logic

### StageQueryService

**Raise:** `raiseQuery(StageAssignment, queryText, userId): StageQuery`
- Creates query with status `pending`
- Notifies stage assignee via `NotificationService`

**Respond:** `respondToQuery(StageQuery, responseText, userId): QueryResponse`
- Creates `QueryResponse` record
- Updates query status to `responded`
- Notifies the original raiser

**Resolve:** `resolveQuery(StageQuery, userId): StageQuery`
- Sets status to `resolved`, records `resolved_at` and `resolved_by`
- **Only the user who raised the query can resolve it** (enforced in controller)

### Blocking Mechanism

`StageAssignment::hasPendingQueries()` returns true if any queries have status `pending` or `responded`.

Checked in two places:
1. `LoanStageService::updateStageStatus()` -- throws `RuntimeException`
2. `LoanStageController::updateStatus()` -- returns 422 JSON with count

### StageQuery Model

```
stage_assignment_id  FK -> stage_assignments
loan_id              FK -> loan_details
stage_key            string
query_text           string
raised_by            FK -> users
status               'pending' | 'responded' | 'resolved'
resolved_at          datetime (nullable)
resolved_by          FK -> users (nullable)
```

Scopes: `pending()`, `active()` (pending or responded), `resolved()`

### QueryResponse Model

```
stage_query_id   FK -> stage_queries
response_text    string
responded_by     FK -> users
created_at       datetime
```

No `updated_at` (timestamps disabled).

## Progress Calculation

`recalculateProgress(LoanDetail $loan): LoanProgress`

1. Gets all **main stage** assignments (non-parallel, no parent)
2. `total` = count of main assignments
3. `completed` = count where status is `completed` or `skipped`
4. `percentage` = `(completed / total) * 100`, rounded to 2 decimal places
5. Stores a `workflow_snapshot` (array of all assignments with stage_key, status, assigned_to)

Called after every status change and at the end of parallel completion checks.

## Product-Aware Stage Configuration

### ProductStage Model

Per-product configuration for each stage:

```
product_id              FK -> products
stage_id                FK -> stages
is_enabled              bool
default_assignee_role   string (nullable)
default_user_id         FK -> users (nullable)
auto_skip               bool
allow_skip              bool
sort_order              int
sub_actions_override    array (nullable)
```

Key methods:
- `getUserForBranch(?int $branchId): ?int` -- simple branch lookup with product default fallback
- `getUserForLocation(?int $branchId, ?int $cityId, ?int $stateId): ?int` -- hierarchical: branch -> city -> state -> product default

### ProductStageUser Model

Location-specific user assignments within a product-stage:

```
product_stage_id   FK -> product_stages
branch_id          FK -> branches (nullable, null = location-level)
location_id        FK -> locations (nullable, for city/state level)
user_id            FK -> users
is_default         bool
```

The `findBestAssignee` algorithm uses `getUserForLocation` to resolve the location hierarchy before falling back to role-based matching.

### Skip Configuration

- `ProductStage.allow_skip` -- whether the UI shows the skip button
- `ProductStage.auto_skip` -- whether the stage should be automatically skipped (not currently auto-triggered in `initializeStages`, used for product configuration UI)
- Controller checks `auth()->user()->hasPermission('skip_loan_stages')` before allowing skip action

## Permission Requirements

### Controller Actions

| Action | Route | Permission / Auth Check |
|--------|-------|------------------------|
| View stages | `index` | `view_all_loans` OR loan creator/advisor OR stage assignee OR branch-level BM/BDO |
| Update status | `updateStatus` | `skip_loan_stages` required for skip action |
| Assign user | `assign` | No explicit permission check (implicit via route middleware) |
| Skip stage | `skip` | No explicit permission check (delegated to `updateStatus`) |
| Transfer | `transfer` | No explicit permission check |
| Reject | `reject` | No explicit permission check (controller level) |
| Raise query | `raiseQuery` | No explicit permission check |
| Respond to query | `respondToQuery` | No explicit permission check |
| Resolve query | `resolveQuery` | Must be `raised_by` user |
| Sanction decision reject | `sanctionDecisionAction` | Must have role: `super_admin`, `admin`, `branch_manager`, or `bdo` |

### Stage Role Eligibility

Each stage's `default_role` (JSON array in `stages` table) defines which roles can be auto-assigned. Retrieved via:
- `LoanStageService::getStageRoleEligibility(string $stageKey): array`
- `LoanStageService::getAllStageRoleEligibility(): array` (bulk, keyed by stage_key)

## Special Completion Behaviors

### Disbursement -> OTC Skip

When `disbursement` completes:
- If `disbursement_type === 'fund_transfer'`: OTC stage is skipped, loan status set to `STATUS_COMPLETED`
- If cheque: normal flow continues to `otc_clearance`

### OTC -> Loan Completion

When `otc_clearance` completes:
- Loan status set to `STATUS_COMPLETED`
- `NotificationService::notifyLoanCompleted()` is called

### Sanction -> Docket Date Calculation

When `sanction` completes, reads `app_number` notes:
- If `custom_docket_date` is set, uses that
- Else if `docket_days_offset` is set, calculates `now() + offset days`
- Stores result in `loan_details.expected_docket_date`

### Application Number -> Loan Sync

When `app_number` notes are saved with `application_number`, the value is synced to `loan_details.application_number`.

### Sanction Notes -> Due Date

When sanction notes are saved with `sanction_date`, the docket timeline from `app_number` notes is used to calculate and set `loan_details.due_date`.

## StageAssignment Model Reference

```
loan_id              FK -> loan_details
stage_key            string (FK -> stages.stage_key)
assigned_to          FK -> users (nullable)
status               'pending' | 'in_progress' | 'completed' | 'rejected' | 'skipped'
priority             'low' | 'normal' | 'high' | 'urgent'
started_at           datetime (nullable)
completed_at         datetime (nullable)
completed_by         FK -> users (nullable)
is_parallel_stage    bool
parent_stage_key     string (nullable)
notes                text (JSON-encoded data, stage-specific)
```

Key methods:
- `canTransitionTo(string $newStatus): bool`
- `hasPendingQueries(): bool`
- `getNotesData(): array` -- decodes JSON notes
- `mergeNotesData(array $data): void` -- merges into existing notes
- `isActionable(): bool` -- true if pending or in_progress

Scopes: `pending()`, `inProgress()`, `completed()`, `forUser($id)`, `mainStages()`, `subStagesOf($parentKey)`

Relationships: `loan`, `assignee`, `completedByUser`, `stage`, `transfers`, `queries`, `activeQueries`
