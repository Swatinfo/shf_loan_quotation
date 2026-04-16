# Loan Lifecycle — Roles & Actions

Complete reference for the 12-stage loan workflow (16 including sub-stages), with multi-phase role handoffs, notes JSON keys, and cross-stage rules. Generated from source code.

---

## Stage Overview

| # | Stage Key | Name | Type | Seq |
|---|-----------|------|------|-----|
| 1 | `inquiry` | Inquiry | sequential | 1 |
| 2 | `document_selection` | Document Selection | sequential | 2 |
| 3 | `document_collection` | Document Collection | sequential | 3 |
| 4 | `parallel_processing` | Parallel Processing | parallel (container) | 4 |
| 4a | `app_number` | Application Number | parallel sub-stage | — |
| 4b | `bsm_osv` | BSM / OSV | parallel sub-stage | — |
| 4c | `legal_verification` | Legal Verification | parallel sub-stage, 3-phase | — |
| 4d | `technical_valuation` | Technical Valuation | parallel sub-stage | — |
| 4e | `sanction_decision` | Sanction Decision | parallel sub-stage, decision | — |
| 5 | `rate_pf` | Rate & PF | sequential, 3-phase | 5 |
| 6 | `sanction` | Sanction Letter | sequential, 3-phase | 6 |
| 7 | `docket` | Docket Login | sequential, 3-phase | 7 |
| 8 | `kfs` | KFS | sequential | 8 |
| 9 | `esign` | E-Sign & eNACH | sequential, 4-phase | 9 |
| 10 | `disbursement` | Disbursement | sequential | 10 |
| 11 | `otc_clearance` | OTC Clearance | sequential (cheque only) | 11 |

Base stage keys initialized for every loan (16 total):
```
inquiry, document_selection, document_collection,
parallel_processing, app_number, bsm_osv, legal_verification, technical_valuation, sanction_decision,
rate_pf, sanction, docket, kfs, esign, disbursement, otc_clearance
```

---

## 1. Inquiry

- **Stage key**: `inquiry`
- **Type**: Sequential
- **Default roles**: Defined in `stages.default_role` column (typically `loan_advisor`, `branch_manager`, `bdh`)
- **Phases**: Single phase
- **Notes JSON keys**: None (stage-specific)
- **Completion trigger**: Manual status update to `completed`
- **Auto-complete criteria**: None (returns `false` in `isStageDataComplete`)
- **Next stage**: `document_selection`

When a loan is created via quotation conversion, `inquiry` and `document_selection` are auto-completed by `LoanStageService::autoCompleteStages()`.

---

## 2. Document Selection

- **Stage key**: `document_selection`
- **Type**: Sequential
- **Default roles**: Same as inquiry
- **Phases**: Single phase
- **Notes JSON keys**: None
- **Completion trigger**: Manual status update to `completed`, or auto-completed on quotation conversion
- **Next stage**: `document_collection`

---

## 3. Document Collection

- **Stage key**: `document_collection`
- **Type**: Sequential
- **Default roles**: Advisor-eligible roles
- **Phases**: Single phase
- **Notes JSON keys**: None (data stored in `loan_documents` table)
- **Completion trigger**: Manual status update, with validation that all required documents have been collected (`LoanDocumentService::allRequiredResolved()`)
- **Completion gate**: System blocks completion if required documents are pending
- **Next stage**: `parallel_processing`

---

## 4. Parallel Processing (Container)

- **Stage key**: `parallel_processing`
- **Type**: Parallel container
- **Default roles**: None (container stage, never directly assigned)
- **Phases**: N/A — contains 5 sub-stages
- **Completion trigger**: Automatic — completes when all child sub-stages are `completed` or `skipped` (checked by `checkParallelCompletion()`)
- **Next stage**: `rate_pf`

When `parallel_processing` starts:
1. Parent assignment set to `in_progress`
2. Only `app_number` is auto-started (others remain `pending`)
3. After `app_number` completes, only `bsm_osv` starts
4. After `bsm_osv` completes, remaining sub-stages (`legal_verification`, `technical_valuation`, `sanction_decision`) start in parallel

### 4a. Application Number

- **Stage key**: `app_number`
- **Type**: Parallel sub-stage (parent: `parallel_processing`)
- **Default roles**: From `stages.default_role`
- **Phases**: Single phase
- **Notes JSON keys**:
  - `application_number` — Bank application number (synced to `loan_details.application_number`)
  - `docket_days_offset` — Number of days from sanction to expected docket date (e.g. `"2"`; `"0"` means custom date)
  - `custom_docket_date` — Required when `docket_days_offset` is `"0"`, format `d/m/Y`
  - `stageRemarks` — Free-text remarks
- **Required fields for completion**: `application_number`, `docket_days_offset`, and `custom_docket_date` if offset is `"0"`
- **Auto-complete**: Saves notes via `saveNotes` endpoint; auto-completes when all required fields present
- **Completion trigger**: Auto-completes on save when data is complete
- **Next sub-stage**: `bsm_osv` (started via `startSingleParallelSubStage`)

### 4b. BSM / OSV

- **Stage key**: `bsm_osv`
- **Type**: Parallel sub-stage (parent: `parallel_processing`)
- **Default roles**: From `stages.default_role`
- **Phases**: Single phase
- **Notes JSON keys**: None required
- **Auto-complete criteria**: Always `true` — completes as soon as status is set to completed
- **Completion trigger**: Manual status update
- **Next**: After completion, starts remaining parallel sub-stages (`legal_verification`, `technical_valuation`, `sanction_decision`) via `startRemainingParallelSubStages()`

### 4c. Legal Verification (3-Phase)

- **Stage key**: `legal_verification`
- **Type**: Parallel sub-stage, 3-phase with role handoffs
- **Default roles**: From `stages.default_role`

#### Phase 1 — Loan Advisor suggests legal advisor
- **Who acts**: Loan advisor / task owner
- **Action**: `legalAction` with `action=send_to_bank`
- **Form fields**: `suggested_legal_advisor` (text, optional)
- **Transfer target**: `transfer_to` (optional, defaults to loan's `assigned_bank_employee` or auto-found bank employee)
- **Button**: "Send to Bank"

#### Phase 2 — Bank employee initiates legal
- **Who acts**: Bank employee
- **Action**: `legalAction` with `action=initiate_legal`
- **Form fields**: `suggested_legal_advisor` (can confirm or change the suggested advisor name)
- **Transfer target**: `transfer_to` (optional, defaults to `legal_original_assignee` or loan creator)
- **Button**: "Initiate Legal"

#### Phase 3 — Loan advisor completes
- **Who acts**: Loan advisor / original assignee
- **Completion trigger**: Auto-completes when `legal_phase` is `"3"` (via `isStageDataComplete`)
- **Action**: Complete stage via standard status update

**Notes JSON keys**:
- `legal_phase` — Current phase: `"1"`, `"2"`, or `"3"`
- `legal_original_assignee` — User ID of the advisor before bank handoff
- `suggested_legal_advisor` — Name suggested in phase 1
- `confirmed_legal_advisor` — Name confirmed in phase 2

### 4d. Technical Valuation

- **Stage key**: `technical_valuation`
- **Type**: Parallel sub-stage with office employee handoff
- **Default roles**: From `stages.default_role`
- **Initial assignment**: Auto-assigned to task owner (advisor) when `bsm_osv` completes

#### Phase 1 — Advisor sends to office employee
- **Who acts**: Loan advisor
- **Action**: `technicalValuationAction` with `action=send_to_office`
- **Transfer target**: `transfer_to` (optional, auto-finds best office employee via `findBestAssignee`)
- **Button**: "Send for Technical Valuation"

#### Phase 2 — Office employee fills valuation form
- **Who acts**: Office employee
- **Form**: `LoanValuationController::store` — separate form (not saveNotes)
- **Valuation form fields**:
  - `valuation_type` — Always `"property"`
  - `property_type` — e.g. `"residential_bunglow"`
  - `property_address` — Full address text
  - `landmark` — Required, max 255 chars
  - `latitude`, `longitude` — GPS coordinates (from map picker)
  - `land_area` — e.g. `"200"` (sq. units)
  - `land_rate` — Rate per unit (numeric)
  - `construction_area` — Optional
  - `construction_rate` — Optional (numeric)
  - `valuation_date` — Format `d/m/Y`
  - `valuator_name` — Required
  - `valuator_report_number` — Optional
  - `notes` — Optional, max 5000 chars
- **Calculated fields** (server-side):
  - `land_valuation` = `land_area * land_rate`
  - `construction_valuation` = `construction_area * construction_rate`
  - `final_valuation` = `land_valuation + construction_valuation`
  - `market_value` = `final_valuation`
- **Data stored in**: `valuation_details` table (not `stage_assignments.notes`)
- **Completion trigger**: Auto-completes after valuation form is saved (`ValuationController::store` calls `updateStageStatus` to `completed`)
- **Completion gate**: Requires `valuation_details` record with `valuation_type=property` and `final_valuation` present

**Notes JSON keys**:
- `tv_phase` — Current phase: `"2"` (after send to office)
- `tv_original_assignee` — User ID of the advisor before office handoff

### 4e. Sanction Decision

- **Stage key**: `sanction_decision`
- **Type**: Parallel sub-stage, decision gate
- **Default roles**: From `stages.default_role` (typically `office_employee`)
- **Phases**: Escalation chain (office_employee -> branch_manager -> bdh)

#### Actions (via `sanctionDecisionAction`):

**Approve** (`action=approve`):
- Any assignee can approve
- Sets `loan_details.is_sanctioned = true`
- Notes: `decision_action=approved`, `decided_by=<user_id>`
- Completes the stage immediately

**Escalate to Branch Manager** (`action=escalate_to_bm`):
- Requires `decision_remarks` (mandatory)
- Transfers stage to a branch_manager user
- `transfer_to` optional (auto-finds branch_manager in same branch)
- Appends to `escalation_history` array

**Escalate to BDH** (`action=escalate_to_bdh`):
- Requires `decision_remarks` (mandatory)
- Transfers stage to a bdh user
- `transfer_to` optional (auto-finds bdh in same branch)
- Appends to `escalation_history` array

**Reject** (`action=reject`):
- Only `super_admin`, `admin`, `branch_manager`, `bdh` can reject
- Requires `rejection_reason` (minimum 10 characters)
- Sets `loan_details.status=rejected`, `rejected_at`, `rejected_by`, `rejected_stage`, `rejection_reason`
- Rejects all pending/in_progress stages (saves `previous_status` for potential reactivation)
- Notes: `decision_action=rejected`, `rejection_reason`, `decided_by`

**Notes JSON keys**:
- `decision_action` — `"approved"` or `"rejected"`
- `decided_by` — User ID who made the decision
- `decision_remarks` — Remarks for escalation
- `rejection_reason` — Reason for rejection
- `escalation_history` — Array of escalation records:
  ```json
  [{
    "from_user_id": 5,
    "from_user_name": "John Doe",
    "to_role": "branch_manager",
    "remarks": "High value loan",
    "date": "2026-04-15 10:30:00"
  }]
  ```

**Completion trigger**: Only via `sanctionDecisionAction` approve action (returns `false` in `isStageDataComplete` to prevent saveNotes auto-complete)

---

## 5. Rate & PF (3-Phase)

- **Stage key**: `rate_pf`
- **Type**: Sequential, 3-phase with role handoffs
- **Default roles**: From `stages.default_role`
- **Pre-condition**: `loan.is_sanctioned = true` (set by sanction_decision approve)
- **Auto-assigned to**: Task owner (advisor) on stage advance

#### Phase 1 — Loan advisor fills rate and PF details
- **Who acts**: Loan advisor / task owner
- **Form fields** (saved via `saveNotes`):
  - `interest_rate` — e.g. `"8.50"`
  - `repo_rate` — e.g. `"6.50"`
  - `bank_rate` — Bank margin, e.g. `"2.00"`
  - `rate_offered_date` — Format `d/m/Y`
  - `rate_valid_until` — Format `d/m/Y`
  - `processing_fee_type` — Type of PF
  - `processing_fee` — e.g. `"0.50"` (percentage or amount)
  - `gst_percent` — GST percentage on PF
  - `admin_charges` — Admin charges amount
  - `admin_charges_gst_percent` — GST percentage on admin charges
  - `bank_reference` — Bank reference number
  - `special_conditions` — Free text
- **Calculated/display fields** (stored in notes):
  - `processing_fee_amount`, `pf_gst_amount`, `total_pf`
  - `admin_charges_gst_amount`, `total_admin_charges`
- **Required fields for phase action**: `interest_rate`, `repo_rate`, `bank_rate`, `rate_offered_date`, `rate_valid_until`, `processing_fee_type`, `processing_fee`, `gst_percent`, `admin_charges`, `admin_charges_gst_percent`
- **Action**: `ratePfAction` with `action=send_to_bank`
- **Button**: "Send to Bank for Review"

#### Phase 2 — Bank employee reviews
- **Who acts**: Bank employee
- **Transfer**: Auto-transfers to `assigned_bank_employee` or auto-found bank employee
- **Snapshot**: `original_values` stored in notes (all rate/PF fields from phase 1)
- **Action**: `ratePfAction` with `action=return_to_owner`
- **Button**: "Return to Task Owner"

#### Phase 3 — Loan advisor finalizes
- **Who acts**: Loan advisor / original assignee
- **Transfer**: Returns to `rate_pf_original_assignee` or loan creator
- **Completion trigger**: Auto-completes when `rate_pf_phase` is `"3"` (via `isStageDataComplete`)

**Notes JSON keys**:
- `rate_pf_phase` — Current phase: `"1"`, `"2"`, or `"3"`
- `rate_pf_original_assignee` — User ID of the advisor before bank handoff
- `original_values` — Snapshot object of all rate/PF fields at time of send_to_bank
- `interest_rate`, `repo_rate`, `bank_rate` — Rate fields
- `rate_offered_date`, `rate_valid_until` — Date fields (format `d/m/Y`)
- `processing_fee_type`, `processing_fee`, `gst_percent` — PF fields
- `admin_charges`, `admin_charges_gst_percent` — Admin charge fields
- `processing_fee_amount`, `pf_gst_amount`, `total_pf` — Calculated PF fields
- `admin_charges_gst_amount`, `total_admin_charges` — Calculated admin fields
- `bank_reference`, `special_conditions` — Reference fields

**Next stage**: `sanction`

---

## 6. Sanction Letter (3-Phase)

- **Stage key**: `sanction`
- **Type**: Sequential, 3-phase with role handoffs
- **Default roles**: From `stages.default_role`
- **Auto-assigned to**: Via `autoAssignStage`

#### Phase 1 — Loan advisor fills sanction details
- **Who acts**: Loan advisor / task owner
- **Form fields** (saved via `saveNotes`):
  - `sanction_date` — Format `d/m/Y`
  - `sanctioned_amount` — Sanctioned loan amount
  - `sanctioned_rate` — Sanctioned interest rate
  - `emi_amount` — EMI amount
- **Required fields for completion**: `sanction_date`, `sanctioned_amount`, `emi_amount`
- **Action**: `sanctionAction` with `action=send_for_sanction`
- **Button**: "Send for Sanction Letter"

#### Phase 2 — Bank employee generates sanction letter
- **Who acts**: Bank employee
- **Transfer**: To `assigned_bank_employee` or auto-found bank employee
- **Action**: `sanctionAction` with `action=sanction_generated`
- **Button**: "Sanction Letter Generated"

#### Phase 3 — Returns to loan advisor
- **Who acts**: Loan advisor / original assignee
- **Transfer**: To `sanction_original_assignee` (skips if original assignee is a bank_employee) or loan creator
- **Completion trigger**: Auto-completes when `sanction_phase` is `"3"` AND `sanction_date` is present AND `sanctioned_amount` is present (via `isStageDataComplete`)

**Side effect on completion**: Calculates `expected_docket_date` from `app_number` notes:
- If `custom_docket_date` is set: uses that date
- If `docket_days_offset` is set: adds that many days from now

**Side effect on saving sanction_date**: If `docket_days_offset` exists in app_number notes, calculates `loan_details.due_date`:
- If offset is non-zero: `sanction_date + offset days`
- If offset is `"0"` and `custom_docket_date` exists: uses custom date

**Notes JSON keys**:
- `sanction_phase` — Current phase: `"1"`, `"2"`, or `"3"`
- `sanction_original_assignee` — User ID of the advisor before bank handoff
- `sanction_date` — Format `d/m/Y`
- `sanctioned_amount` — Amount
- `sanctioned_rate` — Rate
- `emi_amount` — EMI amount

**Next stage**: `docket`

---

## 7. Docket Login (3-Phase)

- **Stage key**: `docket`
- **Type**: Sequential, 3-phase with role handoffs
- **Default roles**: From `stages.default_role`
- **Auto-assigned to**: Task owner (advisor or loan creator) — explicit logic in `handleStageCompletion`

#### Phase 1 — Loan advisor prepares docket
- **Who acts**: Loan advisor / task owner
- **Action**: `docketAction` with `action=send_to_office`
- **Transfer target**: `transfer_to` (optional, auto-finds first active office employee)
- **Button**: "Send to Office for Docket Login"

#### Phase 2 — Office employee logs the docket
- **Who acts**: Office employee
- **Form fields** (saved via `saveNotes`):
  - `login_date` — Format `d/m/Y` (required for completion)
- **Completion trigger**: Auto-completes when `docket_phase` is `"2"` AND `login_date` is present (via `isStageDataComplete`)
- **Action**: Complete stage via standard status update (or "Generate KFS" button)

#### Phase 3 — (Post-completion state)
- **Notes reference**: `docket_phase=3` is set in seed data; in practice, phase 2 completion auto-advances to KFS

**Notes JSON keys**:
- `docket_phase` — Current phase: `"1"`, `"2"`, or `"3"`
- `docket_original_assignee` — User ID of the advisor before office handoff
- `login_date` — Docket login date, format `d/m/Y`

**Next stage**: `kfs`

---

## 8. KFS

- **Stage key**: `kfs`
- **Type**: Sequential, single phase
- **Default roles**: From `stages.default_role`
- **Auto-assigned to**: Task owner (advisor or loan creator) — explicit logic in `handleStageCompletion`
- **Notes JSON keys**: None required
- **Auto-complete criteria**: Always `true` — completes as soon as status is set
- **Completion trigger**: Manual status update
- **Next stage**: `esign`

---

## 9. E-Sign & eNACH (4-Phase)

- **Stage key**: `esign`
- **Type**: Sequential, 4-phase with role handoffs
- **Default roles**: From `stages.default_role`
- **Auto-assigned to**: Task owner (advisor or loan creator) — explicit logic in `handleStageCompletion`

#### Phase 1 — Loan advisor sends to bank
- **Who acts**: Loan advisor / task owner
- **Action**: `esignAction` with `action=send_for_esign`
- **Transfer target**: `transfer_to` (optional, defaults to `assigned_bank_employee` or auto-found bank employee)
- **Button**: "Send for E-Sign & eNACH"

#### Phase 2 — Bank employee generates E-Sign docs
- **Who acts**: Bank employee
- **Action**: `esignAction` with `action=esign_generated`
- **Transfer**: Returns to `esign_original_assignee` or loan creator
- **Button**: "E-Sign Generated"

#### Phase 3 — Loan advisor completes with customer
- **Who acts**: Loan advisor / original assignee
- **Action**: `esignAction` with `action=esign_customer_done`
- **Transfer**: To `esign_bank_employee` or `assigned_bank_employee`
- **Button**: "Customer E-Sign Done"

#### Phase 4 — Bank employee confirms completion
- **Who acts**: Bank employee
- **Action**: `esignAction` with `action=esign_complete`
- **Completes the stage** directly via `updateStageStatus` to `completed`

**Notes JSON keys**:
- `esign_phase` — Current phase: `"1"`, `"2"`, `"3"`, or `"4"`
- `esign_original_assignee` — User ID of the advisor (set in phase 1)
- `esign_bank_employee` — User ID of the bank employee (set in phase 2)

**Completion trigger**: `esign_complete` action in phase 4, or auto-complete when `esign_phase` is `"4"` (via `isStageDataComplete`)

**Next stage**: `disbursement`

---

## 10. Disbursement

- **Stage key**: `disbursement`
- **Type**: Sequential, single phase
- **Default roles**: From `stages.default_role`
- **Auto-assigned to**: Office employee (via `findOfficeEmployeeForLoan`, priority: product stage config -> branch default OE -> branch OE -> any OE)
- **Form**: `LoanDisbursementController::store` — separate form (Blade view `loans.disbursement`)

### Form Fields

- `disbursement_type` — `"fund_transfer"` or `"cheque"` (required)
- `disbursement_date` — Format `d/m/Y` (required)
- `amount_disbursed` — Numeric, min 1, max 100,000,000,000 (required)
- `bank_account_number` — For fund transfers (optional)
- `notes` — Free text, max 5000 (optional)

### Cheque-specific fields (when `disbursement_type=cheque`):
- `cheques` — Array of cheque objects:
  - `cheque_name` — Payee name (required)
  - `cheque_number` — Cheque number (required)
  - `cheque_date` — Cheque date (required)
  - `cheque_amount` — Numeric, min 0.01 (required)

### Validations:
- `amount_disbursed` must not exceed `sanctioned_amount` (from sanction stage notes)
- Total cheque amounts must not exceed `amount_disbursed`
- Total cheque amounts must not exceed `sanctioned_amount`

### Data stored in: `disbursement_details` table (via `DisbursementService::processDisbursement`)

### Post-disbursement behavior:
- **Fund transfer**: OTC stage is auto-skipped, loan status set to `completed`
- **Cheque**: Normal flow continues to `otc_clearance`

**Lock behavior**: Disbursement form is read-only after loan completion, unless user has `super_admin`, `admin`, `bdh`, or `branch_manager` role (override access)

**Next stage**: `otc_clearance` (cheque) or loan completed (fund_transfer)

---

## 11. OTC Clearance

- **Stage key**: `otc_clearance`
- **Type**: Sequential, single phase (cheque disbursements only)
- **Default roles**: From `stages.default_role`
- **Auto-assigned to**: Task owner (advisor or loan creator) — explicit logic in `handleStageCompletion`

### Notes JSON keys:
- `handover_date` — Date of cheque handover, format `d/m/Y` (required for completion)

### Required fields for completion: `handover_date`

### Auto-complete criteria: `handover_date` is present

### Transfer behavior: On transfer, `handover_date` is cleared from notes so the new assignee must fill it

### Completion effect: Sets `loan_details.status = completed`, triggers `NotificationService::notifyLoanCompleted()`

### Fund transfer path: If disbursement type is `fund_transfer`, this stage is auto-skipped with `status=skipped`

---

## Cross-Stage Rules

### Status Transitions

Valid transitions per status (defined in `StageAssignment::canTransitionTo`):

| Current Status | Allowed Next Status |
|---------------|-------------------|
| `pending` | `in_progress`, `skipped` |
| `in_progress` | `completed`, `rejected` |
| `rejected` | `in_progress` |
| `skipped` | (none) |
| `completed` | `in_progress` (for revert) |

Status constants: `pending`, `in_progress`, `completed`, `rejected`, `skipped`

Priority levels: `low`, `normal`, `high`, `urgent`

### Query System

- Queries are raised via `raiseQuery` on a stage assignment
- Fields: `query_text` (max 5000 chars)
- Response: `response_text` (max 5000 chars)
- Only the user who raised a query can resolve it
- **Completion block**: A stage with pending queries (`status=pending` or `status=responded`) cannot be completed. The system checks `hasPendingQueries()` and returns the count of unresolved queries.

### Auto-Assignment Priority Chain

`findBestAssignee()` resolves the best user in this order:

1. **Product stage config** — Location-specific user from `product_stages` table (checks branch -> city -> state -> product default)
2. **Assigned advisor** — For advisor-eligible stages, uses `loan.assigned_advisor` if they have an eligible role
3. **Bank default employee** — For bank_employee stages: bank's default employee for the branch's city
4. **Bank employee + branch** — Bank employee from `bank_employees` pivot in same branch
5. **Bank employee (any branch)** — Bank employee from pivot (any branch)
6. **Other eligible roles in branch** — Non-bank roles matching stage eligibility + branch
7. **Default office employee** — For office_employee stages: user with `is_default_office_employee=true` on `user_branches` pivot
8. **Loan creator** — For advisor/BM/BDH roles: falls back to loan creator
9. **Branch match** — Any user with eligible role in same branch
10. **Any match** — Any active user with eligible role

Special assignment rules:
- `docket`, `otc_clearance`, `kfs`, `esign` — Always assigned to task owner (advisor or loan creator) first
- `disbursement` — Assigned to office employee via `findOfficeEmployeeForLoan` (separate priority: product stage config -> branch default OE -> branch OE -> any active OE)
- `parallel_processing` — Never assigned (container only)
- `technical_valuation` — Initially assigned to task owner (who then manually sends to office employee)

### Stage Transfer Mechanism

- Transfers tracked in `stage_transfers` table with fields: `stage_assignment_id`, `loan_id`, `stage_key`, `transferred_from`, `transferred_to`, `reason`, `transfer_type` (`manual` or `auto`)
- Open queries on the stage are reassigned to the new assignee
- `OTC clearance` special: `handover_date` is cleared on transfer
- Activity logged as `transfer_stage`

### Rejection Flow

Two rejection paths:

**1. Stage-level rejection** (via `reject` action on `LoanStageController`):
- Allowed by: `super_admin`, `admin`, `branch_manager`, `bdh`
- Requires: `reason` (max 2000 chars)
- Sets `loan_details.status=rejected`, `rejected_at`, `rejected_by`, `rejected_stage`, `rejection_reason`
- Saves `previous_status` on the rejected stage assignment

**2. Sanction decision rejection** (via `sanctionDecisionAction`):
- Allowed by: `super_admin`, `admin`, `branch_manager`, `bdh`
- Requires: `rejection_reason` (minimum 10 characters)
- Rejects ALL pending/in_progress stages (saves `previous_status` for each)
- Sets same loan-level rejection fields

Both paths:
- Lock all non-completed stages to `rejected` status
- Preserve `previous_status` for potential future reactivation

### Skip Stages

- Requires `skip_loan_stages` permission
- Transition: `pending` -> `skipped`
- Skipped stages count as "done" for progress and unlock the next stage
- Product-level `allow_skip` flag per stage (from `product_stages` table)

### Soft Revert

`revertStageIfIncomplete()` handles cases where editing a completed stage's data makes it incomplete:
- Only reverts `completed` -> `in_progress`
- Preserves all data (notes, assigned_to, timestamps)
- Also reverts the next stage back to `pending` if it was `in_progress` or `pending`
- For parallel_processing: also reverts sub-stages to `pending`
- Updates `loan.current_stage` back to the reverted stage

### Progress Calculation

`recalculateProgress()`:
- Counts only **main stages** (not parallel sub-stages)
- `completed_stages` = count of main stages with `status=completed` or `status=skipped`
- `overall_percentage` = `(completed_stages / total_stages) * 100`
- Stores a `workflow_snapshot` array of all stage statuses
- Saved to `loan_progress` table

### Expected Docket Date Calculation

Triggered when `sanction` stage completes:
1. Reads `app_number` notes for `docket_days_offset` and `custom_docket_date`
2. If `custom_docket_date` is set: uses that as `expected_docket_date`
3. If `docket_days_offset` is set: `now() + offset days` as `expected_docket_date`
4. Saved to `loan_details.expected_docket_date`

Also triggered when saving sanction notes with `sanction_date`:
- If offset is non-zero: `sanction_date + offset days` -> `loan_details.due_date`
- If offset is `"0"` and `custom_docket_date` exists: uses custom date -> `loan_details.due_date`

### Loan Completion

Two paths to loan completion:

1. **Fund transfer**: `disbursement` completion -> auto-skips `otc_clearance` -> sets `loan.status=completed`
2. **Cheque**: `disbursement` completion -> `otc_clearance` starts -> `otc_clearance` completion -> sets `loan.status=completed` + sends notification via `NotificationService::notifyLoanCompleted()`

### Loan Status Values

| Status | Description |
|--------|-------------|
| `active` | Loan in progress |
| `on_hold` | Paused by user, with `status_reason` |
| `cancelled` | Withdrawn, with `status_reason` |
| `completed` | All stages done, disbursement complete |
| `rejected` | Rejected at any stage, with `rejection_reason` |
