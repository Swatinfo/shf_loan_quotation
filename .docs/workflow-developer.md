# Loan Workflow — Developer Guide

## Architecture

### Key Models
- **Stage** — master stage definitions (stage_key, sequence_order, default_role, sub_actions, stage_type)
- **StageAssignment** — per-loan stage instances (status, assigned_to, notes JSON)
- **ProductStage** — product-specific stage config (is_enabled, allow_skip, default_assignee_role)
- **ProductStageUser** — branch/location-specific user assignments
- **StageTransfer** — transfer history records
- **StageQuery** / **QueryResponse** — two-way query system

### Key Service: `LoanStageService`

Central workflow engine. See `services-reference.md` for full method list.

## Stage Initialization

`LoanStageService::initializeStages(loan)` creates StageAssignment records for 14+ base stages:

```
inquiry, document_selection, document_collection,
parallel_processing (parent),
  app_number (sub), bsm_osv (sub), legal_verification (sub), technical_valuation (sub),
sanction_decision, rate_pf, sanction, docket, kfs, esign,
disbursement, otc_clearance
```

Also creates `LoanProgress` record.

## Stage Status Transitions

Valid transitions enforced by `StageAssignment::canTransitionTo()`:
- pending → in_progress, skipped
- in_progress → completed, rejected, pending (revert)
- completed → in_progress (revert only via `revertStageIfIncomplete`)
- skipped → (terminal)
- rejected → (terminal)

## Parallel Processing

### Sub-stage sequencing (in `handleStageCompletion`)
1. `parallel_processing` starts → only `app_number` is auto-assigned
2. `app_number` completes → `bsm_osv` auto-assigned
3. `bsm_osv` completes → `legal_verification` + `technical_valuation` auto-assigned simultaneously
4. All subs complete → `checkParallelCompletion()` marks parent complete, advances to `rate_pf` via `assignNextStage()`

### Sanction Decision Gate
- `sanction_decision` is stage_type = 'decision'
- Actions: approve (sets `is_sanctioned = true`), escalate, reject
- `rate_pf` requires `is_sanctioned = true` via `canStartStage()`

## Multi-Phase Stages

Stages with `sub_actions` JSON define role-based phases:

```json
[
    {"key": "phase_1", "label_en": "Submit", "label_gu": "...", "role": "loan_advisor"},
    {"key": "phase_2", "label_en": "Process", "label_gu": "...", "role": "bank_employee"},
    {"key": "phase_3", "label_en": "Confirm", "label_gu": "...", "role": "loan_advisor"}
]
```

Phase transitions handled by dedicated controller actions:
- `legalAction()` — Legal Verification (3-phase)
- `ratePfAction()` — Rate & PF (3-phase, Phase 3 has `complete` action)
- `sanctionAction()` — Sanction Letter (3-phase, Phase 3 requires `tenure_months`, EMI validated against sanctioned amount)
- `docketAction()` — Docket Login (3-phase)
- `esignAction()` — E-Sign & eNACH (4-phase)
- `technicalValuationAction()` — Technical Valuation
- `sanctionDecisionAction()` — Sanction Decision

Phase state stored in `stage_assignments.notes` JSON field.

### hideSubmit Flag
Multi-phase stages (Rate & PF, Docket Login, OTC Clearance) pass `hideSubmit => true` to the `stage-notes-form` partial. Each phase's action button saves form data before transitioning, so the generic Save button is hidden.

## Auto-Assignment

`LoanStageService::findBestAssignee()` priority:
1. **ProductStageUser config** — branch/location-specific user from `product_stage_users`
2. **Loan advisor** — if stage role matches advisor
3. **Bank default employee** — `Bank::getDefaultEmployeeForCity()`
4. **Role + branch match** — user with matching role in same branch
5. **Any matching role** — fallback to any active user with the role

`ProductStage::getUserForLocation()` hierarchy:
- Exact branch match → city match → state match → default user

### `assignNextStage()` (private)
Extracted from inline logic in `handleStageCompletion()`. Sets next stage to `in_progress` and auto-assigns. Called from both `handleStageCompletion()` (sequential advancement) and `checkParallelCompletion()` (parallel → rate_pf). Ensures stages like rate_pf get properly assigned to the loan advisor after parallel completion.

## Stage Completion Side Effects (`handleStageCompletion`)

1. **Parallel sub-stage sequencing** — triggers next sub-stage
2. **Document collection** — auto-starts parallel processing if all docs resolved
3. **Fund transfer disbursement** — skips OTC, may complete loan
4. **Sequential advancement** — advances `current_stage` to next main stage
5. **Auto-assignment** — next stage auto-assigned
6. **Docket date calculation** — from app_number notes (custom_docket_date or docket_days_offset)
7. **Progress recalculation** — updates LoanProgress percentage

## Stage Reversion

`revertStageIfIncomplete()` — soft-reverts completed stages when data no longer meets criteria (e.g., document un-received after doc_collection was completed). Also reverts dependent next stages if needed.

## Query System

- `StageQueryService::raiseQuery()` — creates query, blocks stage completion
- `StageQueryService::respondToQuery()` — adds response, marks 'responded'
- `StageQueryService::resolveQuery()` — marks 'resolved', stage can proceed
- Completion blocked by `StageAssignment::hasPendingQueries()`

## Transfer System

- `LoanStageService::transferStage()` — reassigns stage, creates StageTransfer record
- Reassigns open queries to new assignee
- Tracks: from_user, to_user, reason, transfer_type

## Rejection

- Only BM, BDH, Admin can reject
- Sets loan status = 'rejected', marks stage as 'rejected'
- Records: rejected_at, rejected_by, rejected_stage, rejection_reason

## Expected Docket Date

Calculated in `handleStageCompletion` when app_number completes:
- From notes: `custom_docket_date` (explicit date) or `docket_days_offset` (days from now)
- Stored on `loan_details.expected_docket_date`

## Views

| View | Purpose |
|------|---------|
| `loans/stages.blade.php` | Visual workflow pipeline with stage blocks |
| `loans/timeline.blade.php` | Chronological event list |
| `loans/transfers.blade.php` | Transfer history |
| `loans/partials/stage-notes-form.blade.php` | Stage notes modal |
