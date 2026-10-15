# Plan — Disbursement completion / Rate&PF charges / Stage-reset data integrity

Status: **CODE BUILT + TESTED (2026-10-15), NOT COMMITTED.** Full suite 404/407 (only 3 pre-existing
date-dependent DisbursementMultiEntryTest failures — proven pre-existing via `git stash`). Pint clean,
`view:clear` done. **Data corrections NOT yet run** — live SQL in `loan-disburse-data-fix.md`, to be
run on production manually.

## Problems (all verified against live code + DB)

1. **Loan won't close.** Completion is owned solely by `DisbursementService::syncDisbursementState()`,
   but `LoanStageService::handleStageCompletion()` opens `otc_clearance` on disbursement completion
   without calling it. If all tranches are already OTC-settled at that moment (generic "complete
   stage" button, or reset→re-advance), nothing re-triggers completion → loan stuck open.
   Only stuck loan today: **289**.

2. **Rate & PF wrong Admin GST/Total.** `_stages-scripts.blade.php:855` parses the comma-formatted
   visible input (`parseFloat("4,250") = 4`). Only `admin_charges_gst_amount` + `total_admin_charges`
   corrupted (admin_charges itself is correct). 6 loans affected: 306, 318, 323, 335, 341, 347.

3. **Stage reset leaves forward data.** `resetClearRelatedData()` hard-deletes `disbursement_details`
   but does NOT cascade to `disbursement_entries`; `syncEntryRows` only reconciles the current
   detail, so a redo can leave orphaned/duplicate entries. Also `sanctioned_amount` column and open
   forward-stage queries are not cleared. (DB currently clean of leftovers; latent bug.)

## Code changes

### A — Disbursement completion trigger
- `app/Services/LoanStageService.php` — in `handleStageCompletion()`, after the sequential-advance
  block (~line 659): when `$completedStageKey === 'disbursement'`, call
  `app(\App\Services\DisbursementService::class)->syncDisbursementState($loan->refresh())`.
- `app/Services/DisbursementService.php` — add a per-loan re-entry guard around
  `syncDisbursementState()` (`private static array $syncing = []`; return if set; `try/finally`) so the
  form path (which already calls sync → completes the disbursement stage → handleStageCompletion →
  sync) doesn't double-run / double-notify.

### B — Rate & PF comma parse
- `resources/views/newtheme/loans/_stages-scripts.blade.php:855` →
  `var adminCharges = parseFloat(($card.find('input[name="admin_charges_input"]').val() || '').toString().replace(/,/g, '')) || 0;`
  Remove the stray `console.log` (line 857). No `SHF_VERSION` bump (inline blade); run `view:clear`.

### C — Stage reset data integrity
- `app/Models/LoanDetail.php` — add `hasFinalizedPayout(): bool` = any active `disbursementEntries`
  with `loan_payout_id` OR `payout_run_id` not null. (Shared by C2a + C3.)
- `app/Services/LoanStageService.php`:
  - **C1:** in `resetClearRelatedData()`, when `targetIdx <= disbursement` index, also
    `$loan->disbursementEntries()->forceDelete()` (match the hard-deleted parent; clean redo).
  - **C2a:** early in `resetToStage()` (before mutations), if `targetIdx <= disbursement` and
    `$loan->hasFinalizedPayout()` → `throw new \RuntimeException('Cannot reset past disbursement — N
    tranche(s) already have a finalized payout. Reverse the payout first.')`.
  - **C2b:** in the `$loanUpdate` block, clear `sanctioned_amount = null` when `targetIdx <= docket`
    index; after resetting forward assignments, close open `stage_queries` (status pending/responded
    → resolved) on the target + forward stage_keys.
- `app/Http/Controllers/LoanStageController.php` — wrap the `resetToStage()` call in `resetStage()`
  (line 91) in `try { } catch (\RuntimeException $e) { return response()->json(['error' => $e->getMessage()], 422); }`
  (stage-scripts JS already reads `responseJSON.error`).
- **C3:** `resources/views/newtheme/loans/stages.blade.php:123` →
  `$canResetStages = auth()->user()->canResetLoanStages() && ! $loan->hasFinalizedPayout();`
  (hides the Reset Stage button, the group build, and the reset JS — all gate on `$canResetStages`).

## Tests (SQLite via `php -c .scratch/php-test.ini vendor/phpunit/phpunit/phpunit`)
- `tests/Feature/DisbursementMultiEntryTest.php` — complete disbursement stage (generic
  `updateStageStatus`) with all entries pre-settled + intent full ⇒ loan `completed`, otc completed.
- `tests/Feature/LoanStageResetTest.php` — reset to `rate_pf`: (a) disbursement entries removed;
  (b) reset blocked (422/RuntimeException) when a payout exists; (c) `sanctioned_amount` cleared;
  (d) open forward-stage query closed.
- Run full suite + `vendor/bin/pint --dirty --format agent`.

## Docs sync
- `.claude/services-reference.md` — disbursement completion now also triggered from
  `handleStageCompletion`; reset cascade + payout guard + hasFinalizedPayout.
- `tasks/lessons.md` — OTC completion trigger gap; Rate&PF comma parse; reset not cascading entries.
- `tasks/todo.md` — progress.

## Data corrections — `loan-disburse-data-fix.md` (run on production, SHOW → review → UPDATE → VERIFY)
- **Section 1** — Rate & PF Admin GST/Total (6 loans).
- **Section 2** — Disbursement/OTC state mismatch (diagnosis SHOW; resolver via tinker; raw-SQL +
  mirror fallbacks). Loan 289 today.
- **Section 3** — Reset-leftover / duplicate entries audit + soft-delete cleanup. (Clean here; check prod.)

## Execution order
1. Code A + B + C → tests → suite + Pint → `php artisan view:clear`.
2. Deploy.
3. Data: runbook Section 1, then Section 2 (loan 289), then Section 3 audit on production.
4. Verify in browser: 289 completed; loan 347 Rate&PF = ₹765 / ₹5,015; payout loans show no Reset Stage.
