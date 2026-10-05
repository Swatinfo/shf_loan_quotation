# TODO — Per-entry OTC + `partial_disbursed` status (multi-round disbursement)

## STATUS: IN PROGRESS — core (schema + status + service/state-machine + controller) DONE & tested (95 tests green). Remaining: UI blades, filter-everywhere wiring, back-compat migration run on prod, docs.

## Goal
Support disbursement happening in multiple tranches over time, with **per-entry OTC
handover** (cheque AND NEFT/RTGS), a new monotonic loan status **`partial_disbursed`**
filterable everywhere, over-disbursement allowed (with a warning label), and the loan
completing only when fully disbursed AND every entry is OTC-settled. Must migrate every
loan currently in the Disbursement or OTC Clearance stage.

## Locked decisions
- `partial_disbursed` = **monotonic**: disbursement started but loan not completed
  (covers money-partial AND fully-disbursed-but-OTC-pending).
- **Over-disbursement allowed** (cumulative may exceed sanctioned; keep the absolute
  1e11 sanity cap). Show a warning label/badge when cumulative > sanctioned.
- **OTC per entry, all methods**, default `pending`; user records handover or skips
  per entry. No automatic method-based skip.
- **OTC stage auto-completes** when all active entries are settled.
- **No reopen** after `completed`.

## Model
- Entry `otc_status`: pending | cleared | skipped. "Settled" = cleared|skipped.
- Loan: active → partial_disbursed (first entry) → completed (moneyDone && allSettled).
- moneyDone = cumulative ≥ target OR completion_intent=full.
- Central resolver `syncDisbursementState(loan)` called after every mutation.

## Steps
- [x] Schema: 3 migrations (2026_10_05_1000xx) — per-entry OTC cols on disbursement_entries;
      completion_intent on disbursement_details; backfill. loan status = varchar (no enum).
- [x] LoanDetail: STATUS_PARTIAL_DISBURSED + STATUSES/LABELS (info); IN_FLIGHT_STATUSES;
      scopeActive→IN_FLIGHT; scopeOpen auto-includes partial (not CLOSED); isOverDisbursed().
- [x] DisbursementEntry/DisbursementDetail: OTC constants + fillable/casts + isOtcSettled();
      INTENT_OPEN/FULL + completion_intent fillable. (is_active hook already correct —
      partial_disbursed not in the deactivate list.)
- [x] DisbursementService: processDisbursement()→syncEntryRows(+otc)+syncDisbursementState()
      (dropped auto-complete-at-target); recordEntryOtc(); markFullyDisbursed()→intent=full;
      syncDisbursementState() = single authority (completed=terminal guard).
- [x] LoanStageService::handleStageCompletion — removed disbursement→terminal & otc→completed
      branches. resetToStage nulls disbursed_amount (disbursement row+entries cascade-deleted,
      status→active). BONUS: recalculateProgress now firstOrCreate (kills loan_progress dup bug).
- [x] Controller + endpoint: unlock while in [active,partial_disbursed,on_hold]; store() takes
      per-entry otc; entryOtc() endpoint + route `loans.disbursement.entry.otc`; status-aware
      redirects. Tests: DisbursementMultiEntryTest rewritten to new model (13) + adjacent
      suites green (95 total).
- [x] UI — disbursement.blade: ONE form takes a mix of cheque + NEFT entry rows; EACH row
      (any method) has an inline OTC control: Pending (default) / Cleared (handover date +
      remarks) / Skipped. Saved together in one submit. over-disbursed warning on total;
      Mark Full Disbursement. processDisbursement accepts per-entry otc_status/
      otc_handover_date/otc_remarks (validate: cleared ⇒ date required). OTC stage panel
      (_stages-body:3125): per-entry table with quick Clear/Skip via shared endpoint;
      auto-complete (no manual Complete button); keep Transfer-to-OE.
      Bump SHF_VERSION + SHF_SW_VERSION + config:clear.
- [x] Filter everywhere: loan list default + "Active" filter = in-flight (active+partial);
      "Partially Disbursed" dropdown option (auto from STATUS_LABELS) isolates; stats/stages-link
      include partial; reactivation re-resolves partial via syncDisbursementState. Dashboard:
      all loan "active" filters → ->active() (IN_FLIGHT); loans-tab default in-flight; tiles/
      tab counts/pipeline/bank-mix/loans-data include partial; stages-link includes partial.
      Reports: status chips + default/"active" filter + stage-line gathering + workload/stale/
      stuck/advisor "active" → in-flight. GeneralTask::scopeWithActiveLinks keeps partial.
      LoanPipelineBreakdownService: no change needed (status-agnostic load + entries-based
      classification already covers partial; completed→otc, partial-with-entries→entry).
      status-change `in:` stays active/on_hold/cancelled (partial is system-managed).
      Tests: LoanListingActiveDefaultTest +2 (default includes partial; active-filter includes,
      partial-filter isolates). Full suite 324+ green (2 pre-existing unrelated failures only).
- [ ] syncDisbursementState: HARD GUARD — if loan.status==completed → return immediately
      (completed is terminal, never downgraded/reopened). Protects all 192 completed loans.
- [ ] Back-compat data migration (VALIDATED against live DB 2026-10-05):
      * completed/cheque (141): entries→cleared (otc_handover_date = otc-stage notes
        handover_date; otc_cleared_by = otc completed_by; otc_cleared_at = otc completed_at);
        completion_intent=full. STATUS UNCHANGED (completed).
      * completed/NEFT (51): entries→skipped; completion_intent=full. STATUS UNCHANGED.
      * active + disb=completed + otc=in_progress (10): entries→pending; completion_intent=full
        → status=partial_disbursed.
      * active + disb=in_progress + >=1 entry (18): entries→pending; completion_intent=open
        → status=partial_disbursed.
      * everything else (on_hold 1-with-0-entries, pending-disb 91, rejected 19, cancelled 15)
        → UNTOUCHED.
      Status flip ONLY for loan_status='active' AND (disb completed OR >=1 live entry).
      Completed-loan entry backfill = NEW COLUMNS ONLY (no status/stage change).
      Total: 28 status flips + 192 completed entry-backfills.
- [ ] Tests (php -c .scratch/php-test.ini): partial→partial_disbursed; handover-while-
      partial doesn't complete; target-with-pending-entry stays partial until settled;
      all-settled→completed; over-disbursed allowed + warning; mark-full+settle→completed;
      migration per case; report/list filter includes partial_disbursed; reset clears OTC.
- [x] Docs: database-schema, models, services-reference, loans, workflow-developer,
      dashboard, lessons + todo. (No new permission added.)

## REMAINING (hand-off)
- [ ] Run migrations on prod: `php artisan migrate` (3 files 2026_10_05_1000xx) — USER action.
- [ ] After deploy, verify assets busted (SHF_VERSION 20261005130000) + one reload for SW.

## Confirmed
- OTC handover applies to EVERY entry regardless of method, default `pending`; skip when
  not needed; NEFT must be explicitly cleared or skipped (no auto-skip shortcut).
- Disbursement form enters cheque + NEFT entries together, each with inline OTC control.
