# Plan: Gross disbursement totals everywhere + charge attribution & locking

## Context

A loan's disbursement has two totals: **NET** transfers (`DisbursementDetail::entryTotal()` = Σ entry
`amount`) and **GROSS** (`grossTotal()` = net + PF + admin, insurance excluded). Loan completion is
gross-based (`DisbursementService::syncDisbursementState` line 181). The user reported the completed
Disbursement stage block showing only the NET total. During the audit we confirmed:

- All **per-loan** displays are now GROSS and correct (fixes already applied — see "Already done").
- The **reporting layer** still computes "Disbursed" as NET per-tranche in 4 places.
- The **OTC Clearance** stage shows individual entries but **no total**.
- PF/admin/insurance are one-time header-level charges that should always attach to the **first**
  disbursement, never be re-added on a later partial save, and become **immutable once OTC has been
  cleared once**.

The user chose: make all 4 reporting aggregates GROSS, attributing the undated one-time PF+admin to
the **first tranche's date** (confirmed). This plan covers the remaining work as one coherent change.

---

## Already done (committed to working tree, tests green)

Pure consistency fixes applied before planning — listed for the record:

- `resources/views/newtheme/loans/_stage-readonly-detail.blade.php:264` — Total → `grossTotal()`,
  insurance on its own muted line.
- `resources/views/newtheme/loans/_stages-body.blade.php` — completed disbursement Total → `grossTotal()`
  + insurance line; in-progress "Disbursed so far" → `grossTotal()`.
- `app/Http/Controllers/LoanDisbursementController.php:149` — flash "remaining" → `grossTotal()`.
- `app/Services/DisbursementService.php:132` — `mark_fully_disbursed` log `amount` → `grossTotal()`.

Pint clean; 64 Disbursement/StageBreakdown tests pass.

---

## Part A — Reporting aggregates go GROSS (first-tranche attribution)

**Rule:** a loan's full PF+admin counts as disbursed in the period/month of its **first tranche**
(earliest `disbursement_date`, tie-break earliest `id`). Insurance stays excluded from "disbursed".
Net tranches continue to bucket by their own `disbursement_date`. Grand total per loan then equals
`disbursed_amount` (gross column), and no month double-counts.

Four sites (all currently NET):

1. `app/Http/Controllers/ReportController.php:336-389` — funnel `disbursed` query.
2. `app/Http/Controllers/ReportController.php:424-432` — 12-month trend `disbursedBuckets`.
3. `app/Http/Controllers/ReportController.php:640-672` — `loanReportTotals` strip + Xlsx footer.
4. `app/Services/LoanPipelineBreakdownService.php:619-662` — `windowedTrancheAmount` / `bucketAmount`.

**Uniform implementation** — attach each loan's `pf_amount + admin_charges` to its single **first
tranche** (earliest `disbursement_date`, tie-break `id`). Because the charge rides one specific entry,
every downstream grouping (period / month / OTC bucket) picks it up exactly once and all existing
reconciliation invariants hold at gross.

- **SQL sites (funnel `:336`, trend `:424`, `loanReportTotals` `:658`)** — these already window
  `de.disbursement_date`. Add to each summed amount:
  ```sql
  de.amount + CASE WHEN de.id = (
      SELECT de2.id FROM disbursement_entries de2
      WHERE de2.loan_id = de.loan_id AND de2.deleted_at IS NULL
      ORDER BY de2.disbursement_date ASC, de2.id ASC LIMIT 1
  ) THEN COALESCE(dd.pf_amount,0) + COALESCE(dd.admin_charges,0) ELSE 0 END
  ```
  joining `disbursement_details dd` on `dd.loan_id = de.loan_id`. The first-tranche subquery is global
  (un-windowed), so a loan whose first tranche is outside the window simply never has its row present →
  fees correctly land only in the first-tranche period. `settled_amount` (OTC) stays net — charges
  aren't OTC-gated. Verified to work on both MySQL and SQLite (tests).
- **Pipeline service `windowedTrancheAmount` (`:619`)** — operates on the loaded
  `$loan->disbursementEntries` collection. Determine the loan's first entry once
  (`sortBy([date,id])->first()`); inside the sum, add `pf_amount + admin_charges` when the passing
  entry **is** that first entry. The charge travels with its entry through the same settled/unsettled
  filter, so the Entry + Settled + OTC bucket arithmetic still reconciles (now at gross).

> NOTE: `ReportController` per-loan **rows** already use the gross `ld.disbursed_amount` column — only
> the period/windowed aggregates change. Consider a small helper `DisbursementDetail::chargeAddon(): int`
> (= `pfTotal() + adminTotal()`) for the PHP side; the SQL sites inline the expression.

---

## Part B — OTC Clearance stage: add totals/breakdown

**Current state:** `resources/views/newtheme/loans/_stages-body.blade.php` `@case('otc_clearance')`
(lines 3161-3282) renders a **cheque-only** per-entry card list (`shf-otc-list`) with **no total** —
only a "N pending" count in the header. The completed branch (3272-3279) shows just a success alert.
There is **no otc_clearance case** in `_stage-readonly-detail.blade.php`. `$disbursementData =
$loan->disbursement` is already in scope (line ~3164) and exposes `entryTotal()/pfTotal()/adminTotal()/
insuranceTotal()/grossTotal()`.

**Change:** add a compact summary block in the otc_clearance case, rendered whenever
`$disbursementData` exists (so it shows in both the pending-list state and the completed-alert state):

- Net Transferred — `entryTotal()`
- Processing Fee (PF) — `pfTotal()` (show only if > 0)
- Admin Charges — `adminTotal()` (show only if > 0)
- Insurance (excluded) — `insuranceTotal()` (muted, show only if > 0)
- **Gross Total** — `grossTotal()` (bold)

Mirror the existing `ld-summary`/`shf-section` presentation used in `disbursement.blade.php:115-133`.
Place it right after the stage header label, before the cascading `@if ($otcPending > 0)` branches so
it is visible in both active and completed OTC states. No model/controller change needed.

## Part C — Lock PF/admin/insurance once OTC is cleared; never re-add on later saves

**Current state:** charges are read from the form and **overwritten on every save** via
`DisbursementService::processDisbursement()` `updateOrCreate` (lines 32-34, 47-50). The only guard is
status-based `$isLocked` (`LoanDisbursementController:40`, `EDITABLE_STATUSES` 20-24), which does NOT
fire for a `partial_disbursed` loan whose first OTC already cleared — so the charges stay editable and
a later partial save re-writes (or zeroes) them. They are header-level (one row per loan), so they are
never *duplicated* per tranche today — the risk is **re-editing**, which this part closes.

**Lock trigger:** a loan's charges become immutable once **any disbursement entry's OTC is settled**
(`DisbursementEntry::isOtcSettled()` — a cleared cheque, or any fund-transfer which is auto-`skipped`
at save). Add a helper `DisbursementDetail::chargesLocked(): bool` =
`entryRows()->whereIn('otc_status', DisbursementEntry::OTC_SETTLED)->exists()`. (Matches the user's
"after the OTC is cleared once"; also freezes NEFT-only loans after their first disbursement.)

**Three layers (super-admin correction tool exempt via `$allowReopen`):**

1. **Service (authoritative) — `processDisbursement()`**: before `updateOrCreate`, when
   `$allowReopen === false` and the existing `$loan->disbursement?->chargesLocked()` is true, **retain
   the stored** `pf_amount`/`admin_charges`/`insurance_amount` and ignore the posted values (recompute
   `$total` from stored charges). This is the safety net that also covers disabled inputs not POSTing.
   The correction tool (`DisbursementDataService::apply()` → `allowReopen: true`) bypasses the lock —
   unchanged.
2. **Controller — `show()`**: compute `$chargesLocked = $disbursement?->chargesLocked() ?? false` and
   pass to the view (alongside `$isLocked` at line 40).
3. **Blade — `disbursement.blade.php:206-231`**: when `$chargesLocked`, render the three charge
   display inputs as **readonly** (keep the hidden raw inputs posting their stored values so totals
   stay correct) with a small lock note ("Locked — OTC cleared"). Independent of the whole-form
   `$isLocked` fieldset.

**"Counted against the first disbursement only":** satisfied by Part A (reporting attributes to the
first tranche) + this lock (charges set at first disbursement, frozen after) + the existing
single-header-row model (never added twice). No per-entry charge columns are introduced.

---

## Part D — Docs & tests

- **Tests** (`php -c .scratch/php-test.ini vendor/phpunit/phpunit/phpunit`):
  - New `tests/Feature` coverage: first-tranche gross attribution produces gross totals in funnel,
    trend, loan-report totals, and pipeline "Total Disbursed"; a loan with tranches split across two
    months puts PF+admin only in the first month.
  - OTC stage renders a gross total + insurance line.
  - Charge-lock: after an entry's OTC is settled, a normal re-save cannot change PF/admin/insurance
    (service retains), but the correction tool (`allowReopen`) still can.
  - Re-run existing `Disbursement|StageBreakdown|Report|Management|Payout` suites — must stay green.
- **Docs to update** (per workflow.md sync checklist): `.claude/services-reference.md`
  (DisbursementService charge-lock + reporting basis), `.docs/models.md` (`chargesLocked()`/
  `chargeAddon()`), `.docs/dashboard.md` + `.docs/workflow-developer.md` (gross reporting basis),
  `tasks/lessons.md` (first-tranche attribution rule), `tasks/todo.md`.

## Files touched (summary)

- `app/Http/Controllers/ReportController.php` — funnel/trend/loanReportTotals gross attribution.
- `app/Services/LoanPipelineBreakdownService.php` — `windowedTrancheAmount` gross attribution.
- `app/Services/DisbursementService.php` — charge-lock retention in `processDisbursement`.
- `app/Http/Controllers/LoanDisbursementController.php` — pass `$chargesLocked` to view.
- `app/Models/DisbursementDetail.php` — `chargesLocked()`, `chargeAddon()` helpers.
- `resources/views/newtheme/loans/disbursement.blade.php` — readonly charge fields when locked.
- `resources/views/newtheme/loans/_stages-body.blade.php` — OTC stage summary/total block.

## Verification

- Targeted suite: `php -c .scratch/php-test.ini vendor/phpunit/phpunit/phpunit --filter='Disbursement|StageBreakdown|Report|Management|Pipeline|Payout'`
- `vendor/bin/pint --dirty --format agent`
- Manual (Chrome MCP / app): the screenshot loan — Reports "Disbursed" now includes PF+admin; OTC
  stage shows a Gross Total + Insurance line; after clearing an OTC, the PF/admin/insurance fields are
  readonly on the disbursement form; super-admin correction tool can still edit them.
