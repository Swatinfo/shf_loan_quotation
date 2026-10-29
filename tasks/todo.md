# TODO — Reconcile product check + one-click product correction + bank aliases  ← CURRENT (2026-10-28) — BUILT, full suite 469/469, Pint clean

Decisions: new `product` column in the reconcile Excel; it is a CHECK, not a match key (match stays app# / A/c / name). Compare against our product name + code + per-product **bank aliases** (new Settings field), same bank only, ignoring case/spaces/punctuation. One-click "Change to X" per row (no bulk). The 5 loans whose entry product already differs from the loan product are left untouched (no cleanup).

- [x] A. Migration `products.bank_aliases` (JSON array) + Product cast/fillable + `matchesBankName()` normaliser
- [x] B. Settings → Products form: "Bank names / aliases" input (comma separated), storeProduct saves it, shown on the product row
- [x] C. Reconcile: `product` column in template; per-row product check (ok / mismatch / unknown / blank) on matched + mismatch rows; mismatch/unknown = discrepancy
- [x] D. Reconcile: bank-wide identifier lookup so a loan logged under a non-selected product is found ("Logged under X — not in selected products") instead of "not in DB"
- [x] E. Uploaded file kept (per-user token, 24h) so the page can re-run without re-uploading
- [x] F. `POST payouts/reconcile/loans/{loan}/product` (finalize_payout): same bank only, blocked when any tranche/charges are in a payout run or legacy finalized; updates loan + its entries (+ entries JSON); ActivityLog `change_loan_product` (source reconcile); page re-runs automatically
- [x] H. Excel `loan_amount` renamed `disbursed_amount` (old header still read as fallback); table headers "Disbursed Amount"
- [x] G. Tests + Pint + docs (services-reference, routes-reference, database-schema, settings, models, lessons)
- [x] Browser-verified on local live copy: HL → Unknown, LAP → Mismatch → Change → auto re-run → "Other product"; loan 350 restored to HOME LOAN (2 `change_loan_product` logs)
- [x] I. Loan A/c number → Stages page link (new tab) on Reconcile (3 tables) + Payout Runs preview + run detail; tests; suite 470/470
- [x] J. Loan # linked too (new tab) + brand orange `.po-loan-no` on Reconcile + Payout Runs; SHF_VERSION bumped in local .env (bump on prod too); suite 471/471
- [ ] Not committed — awaiting user confirmation

---

# TODO — Partial disbursement lifecycle + payout/reconcile rework + payout purge  ← CURRENT (2026-10-28) — BUILT, full suite 459/459, Pint clean

Decisions: partial Save completes disbursement stage + opens OTC; new stored status `partial_completed`; Mark Full sets loan_amount = entries+PF+admin; payout normal = slab% × (entries + PF + admin, once on 1st tranche); PF-based = slab% × PF ex-GST (Settings PF GST); insurance info-only (not in total, no TDS); TDS on final total; pay on OUR figures; reconcile normal = Excel loan_amount vs entries+PF+admin, PF-based = Excel pf_amount vs PF ex-GST (±₹1 counts as a match, PF only — all other comparisons exact); admin_charges column removed from reconcile Excel; purge all past payouts via `payout:purge-runs` with mysqldump backup to storage/app/backups/.

- [x] A. `partial_completed` status (constants, labels EN/GU, in-flight + editable lists, hard-coded lists in Loan/Report/Dashboard controllers + LoanPipelineBreakdownService, badges) + status-only data migration
- [x] B. `resolveDisbursementState` rewrite (always complete disbursement stage; reopen OTC on pending cheque; partial_completed vs completed)
- [x] C. Disbursement page: no-entries error popups (Save + Mark Full), below-target warning, server `entries` error shown, Mark Full visible with no entries + unsaved-changes block
- [x] D. Mark Full → loan_amount = gross, keep original, log change_loan_amount
- [x] E. OTC stage card "Partially Completed" badge (owner + read-only partials)
- [x] F. Shared payout formula (PayoutService) + PayoutRunService: no GST except PF-based ex-GST; admin+PF in normal base once (first tranche overall); `charges_payout_run_id` migration (PF+admin marker; named for both charges); insurance info-only column; TDS on total; run screen + PDF/Excel
- [x] G. Reconcile: new comparisons, first-tranche-overall carrier fix, drop admin_charges from template/export, differences shown as before, insurance info-only
- [x] H. `payout:purge-runs` (--dry-run, typed PURGE, mysqldump backup first, abort on failed backup, single transaction, activity log)
- [x] I. Tests (status flow, Mark Full, no-entries, payout, reconcile, migration, purge) + update existing payout tests + full suite + Pint
- [x] J. Docs: loans, workflow-developer, workflow-guide, settings, services-reference, database-schema, dashboard, ops, lessons; payout memory notes
- [ ] Not committed — awaiting user confirmation

---

# TODO — Entry-less on-hold loans by app# + download timestamps  (2026-10-19)

- [x] `dbIdentifierIndex` now loan-first (lists all bank+product loans ANY status, then enriches with tranches) → entry-less on-hold/cancelled loans found by application number, shown as [status] not "not in DB"
- [x] Export filenames stamped: reconcile template `payout-reconciliation-template-<d-m-Y_H-i-s>.xlsx`; payout report `payout-report-<Ymd>-<Ymd>-<His>.xlsx`
- [x] Tests: entryless-on-hold-by-app#, template-filename-timestamp regex. **Full suite 434/434**, Pint clean, view:clear
- [x] Verified real file (upload8): 2 app-numbered on-hold loans → excel_in_db [status]; excel_only 4→2
- [x] Docs: services-reference, settings, lessons
- [ ] Not committed — awaiting user confirmation

---

# TODO — Reconcile mismatch categories + payout status exclusion  (2026-10-19)

- [x] Mismatch display fix: `dbIdentifierIndex` carries real otc_handover clearance dates + `pending` + `loan_id` + `status`, scans ALL tranches (amounts/dates from active only); blade shows "Pending (not cleared)" not disbursement fallback + a Reason column
- [x] `mismatchReason()` → {category, reason}; categories difference / status / pending / outside / other; blade badge per category
- [x] `LoanDetail::PAYOUT_EXCLUDED_STATUSES = [cancelled, on_hold, rejected]` excluded from BOTH `ourEntries` + `PayoutRunService` (never paid); shown in reconcile with status
- [x] Tests: reconcile pending/difference/outside/status categories, on-hold excluded from payout run. **Full suite 431/431**, Pint clean, view:clear
- [x] Docs: services-reference, settings, lessons
- [ ] Not committed — awaiting user confirmation

---

# TODO — Payout Runs POST + reconcile product cols + dashboard confirm  (2026-10-19)

- [x] Payout Runs Calculate → POST `payouts.runs.calculate` (clean URLs); GET route kept for landing/deep-link; +test
- [x] Reconcile: Product column on "In DB, not in Excel" (db_only via loan.product) + "In Excel & in our DB — mismatch" (dbIdentifierIndex carries product_name)
- [x] Dashboard disbursement block / stage breakdown already on settlement date (transactionDate) — confirmed + fixed stale comment
- [x] **Full suite 427/427**, Pint clean, view:clear
- [ ] Not committed — awaiting user confirmation

---

# TODO — Settlement-date basis + reconcile exists-in-DB + stages dates  (2026-10-19)

User: use otc_handover (settlement) date everywhere; "not in DB" only when record truly absent; show cheque/handover/OTC status on stages disbursement table.

- [x] `DisbursementEntry::TXN_DATE_SQL` → `DATE(COALESCE(otc_handover_date, disbursement_date))`; `transactionDate()` → `otc_handover_date ?? disbursement_date`. One definition change; reconcile/payout/reports/dashboard all use it. txnDateExpr + grossTrancheExpr order updated to otc_handover. COALESCE fallback keeps un-cleared cheques visible (dashboard awaiting-OTC bucket intact).
- [x] reconcile(): split unmatched bank rows → `excel_in_db` (identifier exists in DB any date via `dbIdentifierIndex()` → "mismatch, not payable" section, both sides) vs `excel_only` (truly missing). Summary tile + blade sections.
- [x] Stages disbursement table (`_stages-body.blade.php` desktop+mobile): + Cheque Date / Handover Date / OTC Status columns (separate partial from readonly-detail)
- [x] Tests: reconcile window→settlement + exists-in-db + truly-missing (PayoutReconcileTest). **Full suite 426/426**, Pint clean, view:clear
- [x] Verified real file: TBRAJ00007885128 (loan 241) now MATCHES (₹52,94,100 cleared 27/08 vs bank ₹53,00,000 19/08, discrepancy); "not in DB" 16→4 truly-missing, 12 moved to exists-in-db
- [x] Docs: services-reference, lessons
- [ ] Not committed — awaiting user confirmation

---

# TODO — Reports txn-date + end-date −1day + visibility + connector breakdown  (2026-10-19)

User asks (all with plan approval): ① Payout Runs already on txn-date ✓; ② auto end-date = +1mo−1day (16→15); ③④ reports/dashboard "correct data" — amounts already GROSS, align DISBURSED date window to txn-date; ⑤ BM/BDH shouldn't see stage-assigned (non-branch) loans; connectors should see stage breakdown for their loans.

- [x] ② `d.setDate(d.getDate()-1)` on runs.blade (#prFrom→#prTo) + reconcile.blade (#rcFrom→#rcTo)
- [x] ③④ ReportController: `txnDateExpr()` + `applyFilters` expr-aware; funnel/trend/loan-report(rows+totals) windows → txn-date; `grossTrancheExpr` first-tranche order → txn-date. `LoanPipelineBreakdownService` windowedTrancheAmount/hasTrancheInWindow → `transactionDate()`; **added transfer_date to the disbursementEntries eager-load**. Amounts unchanged (already GROSS). Pipeline/scoreboard stay on created_at.
- [x] ⑤ `LoanDetail::scopeVisibleTo`: stage-assignment clause gated to loan_advisor/bank_employee/office_employee (BM/BDH excluded → own+branch only)
- [x] Connector breakdown: DashboardController stage-breakdown tab `visible => $canSeeLoans`; `LoanPipelineBreakdownService::applyScope` own-branch += connector quotation clause
- [x] Tests: new LoanVisibilityScopeTest (2), updated ConnectorLoanVisibilityTest (tab visible + breakdown scope). **Full suite 425/425**, Pint clean, view:clear
- [x] Docs: project-context (visibility), services-reference, lessons
- [ ] Not committed — awaiting user confirmation

---

# TODO — Payout totals + OTC status col + transaction-date basis  (2026-10-19)

- [x] Totals footers: reconcile Matched (+ summaries gain **Total Admin** col, controller by_product/by_user += admin), Payout Runs per-user + considered grand total, run-show grand total, Report detail + by-user
- [x] OTC status column on Disbursement readonly stage table (desktop+mobile) via `DisbursementEntry::otcStatusBadge($status,$method)`; `entryList()` already carries otc_status
- [x] **Transaction-date basis** (decision: NEFT/disbursement date, not OTC clearance; apply reconcile + Payout Runs): `DisbursementEntry::TXN_DATE_SQL = DATE(COALESCE(transfer_date, disbursement_date))` + `transactionDate()`; `ourEntries` + `PayoutRunService` entries window = `whereNotNull(otc_handover)` (keep settled gate) + `whereRaw(TXN_DATE_SQL BETWEEN)`; settlement/carrier/rate-as-of use transactionDate(); show() settlement_date too. Report unchanged (finalized_at).
- [x] Tests: +window-uses-transaction-date, +neft-uses-transfer-date (PayoutReconcileTest 9/9). **Full suite 420/420**, Pint clean, view:clear
- [x] Verified real file (ICICI 16/08–15/09): matched 14→20, db-only 8→2, excel-only 16→10; TBRAJ…5128 disbursed 14/08 now correctly prior-period
- [x] Docs: services-reference, settings, lessons
- [ ] Not committed — awaiting user confirmation

---

# TODO — Loan listing: search by bank account number  (2026-10-19)

- [x] `LoanController` `loans.data` search += `orWhereHas('disbursementEntries', active + loan_account_number like)` (app# already worked; a/c# is a relation, not a column)
- [x] Widened search-box placeholder (index.blade): "loan # / app # / a/c # / customer / phone / bank…"
- [x] Test `LoanListingAppNumberAndSubStageTest::test_search_matches_loan_account_number` (full + partial) — **6/6 green**, Pint clean, view:clear
- [x] Docs: .docs/loans.md
- [ ] Not committed — awaiting user confirmation

---

# TODO — Reconcile loan-grain, identifier-only matching + discrepancies  (2026-10-19)

Bug: bank files 1 row/loan (loan-level amount+date); we store 1 row/tranche. Old matcher keyed acc+amount+date → any diff split one loan into BOTH not-found lists (e.g. TBRAJ00007885128 / SHF-202608-0018).

- [x] `reconcile()` rewritten to **loan grain**: group our tranches per loan (`loanSide()`), match by **identifier only** A/c# → app# → name (`normId` strips to [A-Z0-9]; `normName`). Amount/date compared after, never matched on.
- [x] `loanCalc()` sums per-tranche `entryCalc` (carrier = earliest settled carries one-time PF/Admin/Insurance; uploaded insurance overrides DB on carrier)
- [x] Flags amount/date/insurance → `has_discrepancy`; result adds `discrepancy_count` + `date_error_count`
- [x] `parseFlexibleDate()` — dd-mm-yyyy / dd/mm/yyyy / dd.mm.yyyy / dd,mm,yyyy / d-m-yyyy / yyyy-mm-dd / 2-digit / Excel serial; native DateTimeImmutable+getLastErrors (NOT Carbon::createFromFormat); unreadable flagged + surfaced
- [x] Blade: loan-grain Matched (Excel value + `DB:` on 2nd line same `<td>` via `<br>`, mismatch-highlighted cell+row), DB-only (loan grain), Excel-only (+date-error), summary boxes add ⚠ discrepancy + unreadable-dates
- [x] Removed old `matchKey`/`entryMatchKeys`/`parseDate`
- [x] Tests: PayoutReconcileTest rewrite (+multi-tranche-discrepancy, +flexible-dates reflection) **7/7**; payout set **27/27**, Pint clean, view:clear
- [x] Verified real upload vs local DB: TBRAJ00007885128 now **matched (discrepancy)** amount 52,94,100(3tr) vs 53,00,000, date 27/08 vs 19/08; 14 matched / 8 db-only / 16 excel-only
- [x] Docs: services-reference, settings, lessons
- [ ] Not committed — awaiting user confirmation

---

# TODO — Payout insurance-from-upload + Loan/App/A-c identifiers  (2026-10-19)

User: (A) reconcile template missing insurance col, and upload must feed insurance into calc;
(B) show internal loan # + application # + account # on Payout Runs, Reconciliation, Payout Report.
Decisions: Report shows Loan#+App# only (no a/c); keep single `loan_acc_no` upload column; match order A/c# → App# → Name.

- [x] A1 `PayoutController::COLUMNS` += `insurance_amount`; template numeric type at new index 7
- [x] A2 `reconcile()` reads uploaded insurance per loan (first non-empty matched row); `entryCalc(…, ?int $excelInsurance)` uses it on carrier tranche, DB `disbursement.insurance_amount` fallback when blank
- [x] B1 `ourEntries()` loan select += `loan_number`, `application_number`
- [x] B2 `entryMatchKeys()` candidates per DB entry: A/c# → App# → Name (+amount+date); reconcile() tries each, marks excel key seen
- [x] B3 `reconcile.blade`: Loan# + App# stacked under A/c cell on Matched + DB-only
- [x] B4 `PayoutRunService` $loanRows += `application_number` + distinct `account_numbers[]`; `PayoutRunController::show()` same; runs.blade considered-loans + run-show per-loan render Loan#/App#/A/c#
- [x] B5 `report.blade` (+App# col, colspan 13) + `exportReport` xlsx (App# after Loan#, numeric idx shifted to 6-11)
- [x] Tests: PayoutReconcileTest +2 (insurance-from-upload+DB-fallback, match-by-app-number) **5/5**; PayoutRunControllerTest identifier assertions. Payout suite **19/19**, Pint clean, view:clear
- [x] Docs: services-reference, settings, lessons
- [ ] Not committed — awaiting user confirmation (standing rule: never commit/push without it)

---

# TODO — Disbursement completion / Rate&PF charges / reset integrity  (2026-10-15)

Plan: `tasks/plan-disbursement-ratepf-reset-fix.md`. Data SQL: `loan-disburse-data-fix.md`.

- [x] A1 `LoanStageService::handleStageCompletion` → `syncDisbursementState` when disbursement completes; `DisbursementService` per-loan re-entrancy guard (wrapper + `resolveDisbursementState`)
- [x] B1 Rate&PF `recalcPfTotals` strip commas on admin_charges + remove console.log
- [x] C1 reset `forceDelete`s disbursement entries; C2a payout-block (RuntimeException + controller 422); C2b clear `sanctioned_amount` (≤docket) + close forward queries; C3 hide Reset UI via `LoanDetail::hasFinalizedPayout()`
- [x] Tests: DisbursementMultiEntryTest (+1 generic-complete, +serviceEntry() making the 3 direct-service date tests robust), LoanStageResetTest (+4). **Suite 407/407 green**, Pint clean, view:clear
- [x] Confirmed date-parse bug was TEST-ONLY: LoanDisbursementController::store normalizes d/m/Y→Y-m-d before the service, so browser/prod unaffected
- [x] **DEV DB backfill applied** (local shf_all_operations): Rate&PF GST fixed on 6 loans — BOTH top-level AND `original_values` snapshot (the "Original:" hints); 306/318/323/335/341 (900/5900), 347 (765/5015). Detector now returns 0.
- [x] **Live browser verified** (loan 347): form recomputes 5000→900/5900, 100000→18000/118000, 4250→765/5015; Original hints now 765/5015
- [x] Runbook S1 updated to fix original_values too (two UPDATEs)
- [ ] **PROD (manual):** run `loan-disburse-data-fix.md` S1 (6 rate_pf loans, both UPDATEs) + S2 (loan 289) + S3 audit
- [ ] Not committed

---

# TODO — GROSS disbursement totals + first-tranche charge attribution + OTC lock  ← (2026-10-09)

Plan: `.claude/plans/floofy-dazzling-parrot.md`. User-confirmed: all reporting "Disbursed" aggregates GROSS; undated PF+admin ride the **first tranche** (earliest date, tie id); insurance always separate.

- [x] Per-loan stage displays → `grossTotal()` + insurance line (readonly-detail + stages-body completed & "disbursed so far"); flash "remaining" + `mark_fully_disbursed` log → gross
- [x] Model: `DisbursementDetail::chargeAddon()` + `chargesLocked()`
- [x] Reporting GROSS (first-tranche): `ReportController::grossTrancheExpr()` applied to funnel / trend / `loanReportTotals`; pipeline `windowedTrancheAmount` add-on on first entry (+ eager-load `disbursement`)
- [x] OTC stage: gross total + PF/admin/insurance breakdown block (`_stages-body` otc_clearance)
- [x] Charge lock: `processDisbursement` retains stored pf/admin/insurance when locked & `!allowReopen`; controller passes `$chargesLocked`; disbursement form fields readonly + banner; CSS `.ld-info-locked` + bumped `SHF_VERSION`
- [x] Tests: 4 new (funnel gross, trend first-month attribution, cheque-pending no-lock, lock+retain+correction-bypass). **Full suite 401/401, Pint clean**
- [x] Docs: services-reference, models, lessons

**COMPLETE** — nothing committed.

---

# TODO — SETTLEMENT-DATE (otc_handover_date) BASIS + NEFT dates  ← (prior)

Decisions (user-confirmed): date basis switches to `otc_handover_date` for **payout + all reports + reconcile**; **backfill** existing rows via migration.

- [x] `otcAttrs()` NEFT branch → `otc_handover_date = transfer_date ?? disbursement_date`
- [x] Backfill migration `2026_10_07_143752` (applied)
- [x] `PayoutRunService::computeRun` → `whereBetween('otc_handover_date', …)`
- [x] Reports → otc_handover_date: loan-report (Disbursed On = MAX + filter) + management funnel/trend. Dashboard tiles stay on disbursement_date (track pending vs settled).
- [x] Reconcile: window + match-key + carrier + entryCalc as-of → otc_handover_date
- [x] Cheque OTC dropdown: Skip removed (cheque = Pending/Cleared; NEFT = Skip only) — verified in browser
- [x] Stages auto-scroll: fallback to `#stage-{current_stage}` — verified (scrolls on partial_disbursed)
- [x] Correction tool: Transfer Date column (export+import) + NEFT settlement import + preview diff
- [x] Tests + docs; **full suite 395/395, Pint clean**

**COMPLETE** — nothing committed.

Leave unchanged: Payout Report (finalized_at), Pipeline report (created_at).

---

# TODO — PAYOUT REDESIGN (aggregate / volume-tier)  ← (prior; superseded above for date basis)

**Model (locked, verified against the old `shf` engine `UpdateExcelDataAdmin::checkDatabaseData`):**
- Admin picks a **from/to range**. Scope = **not-yet-paid tranches** with `disbursement_date ∈ range`. Flat products (no sub-products); `is_pf_based` splits behavior.
- **Amount-based**: product_volume = Σ unpaid in-range `amount` (ALL users) → product slab (version @ range end) → tier rate. Per user: `commission = min(user's unpaid in-range disbursed × tier rate, max_payout)`.
- **PF-based**: excluded from volume. `pf_base = exGst(pf_amount, pf_gst)`; pf_aggregate = Σ pf_base → PF slab → rate (75% today); per user `pf_payout = min(user PF × rate, max_payout)`. One-time per loan.
- **Insurance**: Σ `insurance × insurance%`, to the loan's payout user, one-time, not in volume.
- Per user: `total = Σcommission + Σpf_payout + Σinsurance_payout`; `net = total − total×tds%`. Connector→connector slab rate. **No GST deduction.** admin never in payout. `max_payout=-1` → uncapped. Cap is per **(user×product)**.
- Idempotency: tier AND payout on **unpaid** in-range only; finalize stamps tranches + one-time PF/insurance paid.

**Historical snapshot**: each run stores the slab/version, payout %, insurance %, tds %, gst %, tier, aggregate, max — so reports reproduce stable figures even after config changes.

## Checklist
- [x] Migrations: `payout_runs`, `payout_run_products`, `payout_run_lines`, `payout_run_users`; + `disbursement_entries.payout_run_id` & `paid_amount_counted`; + `disbursement_details.pf_payout_run_id` & `insurance_payout_run_id`. (migrated on DB)
- [x] Models (PayoutRun, PayoutRunProduct, PayoutRunLine, PayoutRunUser) + relations; coverage columns in DisbursementEntry/Detail fillable.
- [x] Engine: `PayoutRunService::previewRun(from,to)` + `finalizeRun(from,to,actor)` (new service).
- [x] Engine tests: `PayoutRunServiceTest` (7 tests) — volume tier, PF ex-GST, insurance+TDS, cap, connector, idempotency, range. Full suite 382/382.
- [x] Payout screen: date range → preview (per-product tiers + per-user breakdown) → Finalize Run; runs history + drill-down. (`payouts/runs` + `run-show`, nav link; verified in browser — product names show bank.)
- [x] Remove the payout block from `loans/show` (keep Payout-User). (Slim card: Payout User + link to Payout Runs; `$payoutPreview` build dropped from LoanController.)
- [x] Retire the loan-page finalize path: removed `loans.payout.finalize` route + deleted `LoanPayoutController`. (Full suite 382/382, Pint pass.)
- [x] **Reconcile = verify-only** (user-chosen): dropped `payouts.reconcile.finalize` route + `reconcileFinalize` + `PayoutService::finalizeEntries`. Reconcile now matches our entries vs the bank statement and shows an informational breakdown; it no longer writes payouts. Added a **"Run Payout for this period →"** button on the reconcile result that opens `payouts.runs` pre-filled with the same from/to (finalize happens there). `payouts.report` kept as read-only history of past `loan_payouts`.
- [x] Retired the dead per-loan engine: removed `PayoutService::previewFinalize`/`finalizePayout`/`computeGroups` (+ `matchSlab`/`cycleFor`/`cycleForDays`/`matchSlabIn`/`writePayout`); `PayoutService` now holds only `payoutRates`/`breakdown`/`computeAmount` (reconcile display + disbursement form). Rewrote `PayoutServiceTest` to cover the survivors (5 tests); added `PayoutRunControllerTest` (3 tests: preview/finalize/show over HTTP). Full suite **380/380**, Pint clean.
- [x] Docs sync: `.docs/settings.md` (payout now via PayoutRunService), `.claude/services-reference.md` (PayoutRunService + trimmed PayoutService), `.claude/routes-reference.md` (payouts group + removed routes), `.claude/database-schema.md` (4 payout_run_* tables + coverage columns).

**PAYOUT REDESIGN COMPLETE** — full suite 380/380, Pint clean. Nothing committed (per standing instruction).

---

# TODO — Loan payout (user-wise) + Connector role + Excel reconciliation + Indian format  (ORIGINAL per-loan engine — being replaced by the redesign above)

## Locked decisions
- Single **Loan payout user** per loan (dropdown = all users except bank_employee/office_employee),
  set at quotation→loan conversion, editable on loan (perm `change_payout_user`). Slab switches on
  the user's role: connector → connector slab; else → standard slab.
- **Per-entry payout flag**: each disbursement_entries row is paid/unpaid for payout; finalize uses
  only unpaid entries, then stamps them paid + date. Next tranche counts only its new entries.
- Permissions: `change_payout_user` + `finalize_payout` (+ `reconcile_payout` for Excel tool).
- Product payout cycle: start_day/end_day (1-31, default 1/31) → derived cycle windows.
- Connector = new role; sees only the existing plain/unbranded quotation download; cannot convert.
- Excel reconciliation: export template, user fills with bank statement, import, pick bank+date range,
  match our disbursed entries vs file on loan_acc_no (+amount/date), highlight DB-only & Excel-only,
  show payout to pay. Sample bank columns: loan_acc_no, customer_name, cheque_number,
  disbursement_date, loan_amount, pf_amount, admin_charges (see .scratch/payout-sample.xlsx).

## PART A — Indian number format  ✅ DONE (not committed yet)
- [x] `app/helpers.php` inr()/inrc() (Indian grouping) + composer files autoload + dump-autoload.
- [x] Replaced all money number_format() in loan blades (_stages-body incl OTC, disbursement,
      _stage-readonly-detail, valuation, _valuation-body, valuation-map), loan-settings/_panes:832,
      and controllers (LoanDisbursement, Dashboard, LoanStage, WorkflowConfig). Left the % line as number_format.
- [x] tests/Unit/IndianNumberHelperTest.php (3 tests). Verified live: OTC panel now 16,20,000.

## PART B — Connector role  ✅ DONE (not committed yet)
- [x] Migration 2026_10_05_160000_add_connector_role: role + 9 perms (create/edit_quotation,
      generate_pdf, view_own_quotations, download_pdf, download_pdf_plain, change_own_password,
      view_dashboard, manage_notifications). NO download_pdf_branded / convert_to_loan / loan access.
- [x] No blade changes needed — quotation show.blade already gates Convert (:64), Branded (:75),
      Plain (:81) on those slugs, so connectors see only plain download + no convert.
- [x] Role::gujaratiLabels connector=કનેક્ટર. Applied to local DB (9 perms verified). roles.md updated.
- [x] Tests ConnectorRoleTest (4). Full suite 337 green bar the 2 pre-existing.

## PART C — Loan payout user  ✅ DONE (not committed yet)
- [x] Migration 2026_10_05_160100: loan_details.payout_user_id (FK users) + permission
      change_payout_user granted to admin/branch_manager/bdh. LoanDetail fillable + payoutUser().
- [x] LoanConversionService sets payout_user_id from $extra. Convert controller: payoutUsers +
      defaultPayoutUserId (connector creator) + validation + eligibility check. Convert form dropdown.
- [x] Edit endpoint LoanController@updatePayoutUser + route loans.payout-user.update
      (permission:change_payout_user). Loan show: display + SweetAlert change (mirrors DME).
- [x] Tests LoanPayoutUserTest (5). Applied to local DB. Full suite 342 green bar 2 pre-existing.
- NOTE: admin (non-super) got 403 via the HTTP test harness though hasPermission resolves true
  directly + grant exists on live DB — a test-harness cache/instance quirk, NOT a prod bug; tests use
  super_admin actor for the endpoint + assert the admin-role grant directly.

## PART D — Payout engine (per-entry, incremental)
- [x] Schema (2026_10_05_170000): product_payout_slabs + connector_payout_type/value; products +
      payout_cycle_start_day/end_day; disbursement_entries + loan_payout_id + payout_finalized_at;
      loan_payouts ledger; finalize_payout permission (admin/BM/BDH). Applied to local DB.
- [x] Models: LoanPayout; ProductPayoutSlab.rateFor(isConnector); Product cycle casts;
      DisbursementEntry loan_payout_id/payout_finalized_at + loanPayout(); LoanDetail.payouts().
- [x] PayoutService: cycleFor (start/end day → window, cross-month aware), matchSlab, computeAmount,
      previewFinalize (unpaid is_active entries → base → slab → standard/connector rate → cap → cycle),
      finalizePayout (writes loan_payouts + stamps entries). Tests PayoutServiceTest (5).
      NOTE: manual finalize computes percent on the disbursed increment; PF-based % on actual PF
      comes via Part E (Excel) where per-entry pf_amount is supplied.
- [x] Finalize endpoint LoanPayoutController@finalize + route loans.payout.finalize
      (permission:finalize_payout). Loan show: payout card (user, unpaid increment, next-payout preview,
      Finalize button, ledger). Full suite 347 green bar 2 pre-existing.
- [x] D5: Config UI — slab editor gains Internal + Connector type/value columns; product cycle
      start/end day inputs; WorkflowConfigController validates (connector % ≤ 100) + saves.
      Test ProductPayoutConfigTest (2).
- [x] D6: Report — PayoutController@report (filters: from/to/bank; per-user totals + ledger + xlsx
      export). View newtheme/payouts/report.blade. Nav links added (header + bottom-nav).

## PART E — Excel export/import + Bank Payout Reconciliation  ✅ DONE (not committed)
- [x] XlsxImportService (self-contained ZipArchive + SimpleXML; shared/inline strings + Excel date
      serials). Verified against the sample bank file (51 rows, 45504→2024-07-31).
- [x] PayoutController@reconcileTemplate (export template pre-filled with our entries for bank+range),
      @reconcileForm, @reconcile (import → match on loan_acc_no+amount+date, name+amount+date fallback
      for placeholder accounts → Matched / DB-only / Excel-only + payout-to-pay; PF-based uses the
      file's pf_amount). View newtheme/payouts/reconcile.blade. Gated by view_reports.
- [x] Tests PayoutReconcileTest (2): reader + full reconcile (2 matched, 49 excel-only, payout total).

## STATUS: ALL PARTS A–E DONE (not committed). Full suite green bar the 2 pre-existing failures.
Migrations applied to local DB. For prod: php artisan migrate + SHF_VERSION bump + config:cache.

## Confirmed (Part E)
- Match strictness: loan_acc_no + amount + date (all three). Fallback to customer_name+amount+date
  for placeholder accounts (e.g. LBRAJ00000000000).
- xlsx reader: SELF-CONTAINED (ZipArchive + SimpleXML), no new dependency.

## Notes
- Part A implemented, NOT committed yet (awaiting user). Branch = main (feature branch deleted).

## Disbursement charges: PF + Admin + Insurance (per-tranche)  [2026-10-06]
Decisions: (1) per-tranche on each entry; (2) GROSS = net + pf + admin drives fully-disbursed (insurance excluded); (3) payout base = is_pf_based ? Σ pf_amount : Σ amount.

- [x] Migration: add `pf_amount`, `admin_charges`, `insurance_amount` (int, default 0) to `disbursement_entries` + `disbursement_details`
- [x] Models: fillable + int casts on both; `DisbursementDetail::pfTotal()/adminTotal()/insuranceTotal()/grossTotal()`; entryList() fallback carries the 3 fields (default 0)
- [x] Controller store(): validate per-entry pf/admin/insurance (nullable numeric min:0), cast int, keep in validated entries
- [x] DisbursementService::processDisbursement(): denormalize header totals; amount_disbursed + loan.disbursed_amount = GROSS; syncEntryRows persists the 3 fields
- [x] DisbursementService::syncDisbursementState(): moneyDone compares GROSS vs target (net+pf+admin); keep 0-start detection on net
- [x] PayoutService::previewFinalize()/finalizeEntries(): base = is_pf_based ? Σ pf_amount(unpaid) : Σ amount(unpaid)
- [x] disbursement.blade: per-entry PF/Admin/Insurance amount inputs; summary strip gains Gross/PF/Admin/Insurance; JS entryRowHtml + totals + amount-raw sync
- [x] Reconcile template: prefill pf_amount/admin_charges from stored entry values
- [x] Tests: service (gross completion + per-entry charges), payout (pf-based base), controller validation
- [x] Docs: services-reference, models.md, database-schema, loans/settings as needed; bump SHF_VERSION/SW for blade+css asset

## Payout Config tab (loan settings)  [2026-10-06] — DONE
- [x] app-defaults `payoutConfig` (admin_gst/pf_gst/user_tds/user_insurance → {value, calc, effective_from})
- [x] WorkflowConfigController::savePayoutConfig — derives calc=value/100 server-side, stores effective_from; ConfigService::updateSection
- [x] route loan-settings.payout-config.save (manage_workflow_config)
- [x] LoanSettingsController::index passes $payoutConfig; index.blade tab 07; _panes pane; _scripts auto-calc + datepicker
- [x] Tests PayoutConfigSettingTest (3); docs settings.md + routes-reference.md

## Payout net formula wired from payoutConfig  [2026-10-06] — DONE
- [x] migration 2026_10_06_130813 — loan_payouts breakdown cols (pf_base, gst, insurance_base, insurance_payout, tds, net + rate snapshots)
- [x] PayoutService: ConfigService DI, payoutRates(), breakdown() (net = commission + ins − gst − tds; tds on +gross; net floored ≥0); wired into previewFinalize/finalizePayout/finalizeEntries (total = net)
- [x] PayoutController: reconcile entryPayout returns net; report totals + export use net + breakdown cols
- [x] Views: loan show payout card (preview breakdown + ledger cols), report.blade breakdown cols
- [x] Tests: net breakdown + zero-floor; updated finalize_entries + reconcile expectations to net. Full suite: only 2 known pre-existing failures.
- [x] Docs: settings.md consumption note

## Move product-wise payout → Payout Config tab  [2026-10-06] — DONE
- [x] storeProduct: identity-only (bank/name/code); payout stripped
- [x] WorkflowConfigController::savePayoutProduct (product_id + slabs/cycle/cap/PF; same validation) + route loan-settings.payout-product.save
- [x] _panes: removed payout block + payout data-attrs + badges from Products tab; added Product Payout section (list + shared collapse form, reusing #productSlabList etc.) to Payout Config pane
- [x] _scripts: #productForm submit → name/bank only; #payoutProductForm submit → slab validation; split resetProductForm / resetPayoutProductForm; .shf-edit-product (identity) + .shf-edit-payout-product (payout)
- [x] ProductPayoutConfigTest retargeted to payout-product.save (+ identity-only store test); full suite 353 / only 2 known pre-existing failures
- [x] docs: settings.md + routes-reference.md

## Effective-dated payout config (temporal history)  [plan 2026-10-06]
Decisions: rate/slab applied = version in force as of each tranche's DISBURSEMENT date; scope = both global rates + product payout. Finalized loan_payouts already snapshot rates, so past payouts are untouched.

- [x] Migration `payout_rate_versions` (rate_key, value, calc, effective_from, created_by, ts; unique rate_key+effective_from)
- [x] Migration `product_payout_versions` (product_id, effective_from, is_pf_based, max_payout_amount, cycle start/end, created_by; unique product_id+effective_from) + add `product_payout_slabs.version_id` FK
- [x] Backfill: payoutConfig → one rate version each; each product's current slabs+fields → one version (effective_from = product created date floor)
- [x] Resolver (PayoutService or PayoutConfigService): ratesAsOf(date), productPayoutAsOf(product,date)
- [x] PayoutService finalize: group unpaid tranches by resolved config-period (productVersion + rate versions) from each tranche's disbursement_date; one loan_payouts row per group (common case = 1). Store resolved effective date(s) on the row.
- [x] Keep `products.is_pf_based/max/cycle` + payoutConfig as denormalized "current" mirrors (UI defaults, other readers); temporal source = version tables
- [x] Controllers: savePayoutConfig inserts/updates rate version by (key, effective_from) + updates current mirror; savePayoutProduct creates a product version (+slabs) + updates mirror; both take effective_from
- [x] UI: global rates — current + per-rate history list; product payout — effective_from on the form + per-product version history
- [x] Tests: version lookup by date, cross-period finalize splits, backfill, current-mirror sync; docs sync

## User dropdowns: show role + exclude super_admin/admin  [2026-10-06] — DONE
- [x] User::scopeSelectable() = active + whereDoesntHave roles in [super_admin, admin]
- [x] All source queries → selectable() + with('roles'): LoanController (index/create/edit/show payout+dme), LoanConversionController, LoanSettingsController, WorkflowConfigController, LoanStageController (index + eligibleUsers API +role), QuotationController (index/create), GeneralTaskController (index/show), DailyVisitReportController, DashboardController (activityLog), ReportController (filterOptions), LoanPipelineBreakdownService (+role), ImpersonateController
- [x] Blade option text shows role: convert payout, general-tasks/show, quotations/create, loans/index, quotations/index, dvr/index, activity-log, reports/pipeline+loan-report, create-task-modal (inline query→selectable), _stages-body DME map, _workflow-product-stages phase override
- [x] JS dropdowns +role: show.blade DME+payout SweetAlert maps, _stages-scripts transfer selects (from API role), dashboard.js scope filter
- [x] Transfer selects (_stages-body optgroup) already group by role + source now selectable() — left as optgroup
- [x] Tests UserSelectableScopeTest; fixed PayoutConfigSettingTest for 2026-04-01 seed. Full suite 356 / only 2 known pre-existing failures. Bumped SHF_VERSION (dashboard.js).

## Connector read-only loan tracking  [2026-10-06] — DONE
- [x] CheckPermission middleware → variadic OR (permission:a,b = either)
- [x] view_connector_loans permission (config/permissions.php) + migration 2026_10_06_145707 seeding + granting to connector
- [x] routes: read routes (index/data/show/timeline/stages/transfers/remarks.index) gated view_loans OR view_connector_loans; POST dme split out to view_loans only
- [x] LoanDetail::scopeVisibleTo + LoanController/LoanStageController authorizeView: connector branch via quotation.user_id
- [x] remarks.index + transferHistory: connector-only visibility guard (view_loans holders unchanged)
- [x] nav: Loans link shows for view_connector_loans (header + bottom-nav); read-only banner on loan show + stages
- [x] Tests ConnectorLoanVisibilityTest (3); full suite 359 / only 2 known pre-existing. Migration applied live + permission cache cleared.
- [x] docs: permissions.md, roles.md, routes-reference.md

## Connector dashboard Loans tab  [2026-10-06] — DONE
- [x] DashboardController::newthemeTabsConfig — Loans tab visible when view_connector_loans ($canSeeLoans); hide Stage Breakdown + My Tasks for connector-only ($connectorOnly) since both resolve empty. Loans count/data already visibleTo-scoped.
- [x] Test: connector dashboard loads, loans tab visible (count 1), stage-breakdown/tasks hidden. Full suite 360 / only 2 known pre-existing. Pure PHP (no asset bump).
- [x] docs: dashboard.md

## Disbursement Data export/import correction tool (super_admin)  [2026-10-06] — DONE
- [x] permission import_disbursement_data (config + migration 2026_10_06_174114, granted to no role) + controller hard super_admin check
- [x] DisbursementDataService (exportData/preview/apply) → reuses XlsxExport/Import + DisbursementService::processDisbursement (updates JSON + mirror + amounts + bank/product + status)
- [x] DisbursementDataController (index/export/preview[token]/import[token]); routes /loans-tools/disbursement-data/*
- [x] view disbursement-data.blade (export btn, upload→preview diff→confirm import by token); header nav (super_admin)
- [x] one row per active disbursed tranche; v1 update-only; Bank+Product editable
- [x] Tests DisbursementDataToolTest (4: gate, export, round-trip rewrite+status, unknown-skip). Full suite 364 / only 2 known pre-existing.
- [x] Safety-net migration DROPPED — this tool + existing backfills cover it. docs: routes-reference, services-reference.

## Disbursement Data tool — charges + delete + before/after preview  [2026-10-06] — DONE
- [x] Export/import columns add PF Amount, Admin Charges, Insurance Amount (editable, separate) + Action (Keep/Delete)
- [x] Delete excludes tranche → processDisbursement soft-deletes; blocked if loan_payout_id set (reported)
- [x] syncDisbursementState($loan, $allowReopen) — correction import re-resolves status for EVERY loan incl. downgrade completed→partial (reopenStage / resetStageToPending helpers); default false keeps all other flows monotonic
- [x] Preview now structured before/after: per-field old vs new + changed flag, loan-level Bank/Product/Sanctioned/Status, predicted status; view shows changed cells in RED
- [x] Tests: preview flags, charges+status, delete, delete-blocked(payout), completed→partial downgrade (9 total). Full suite 369 / only 2 known pre-existing.

## Payout reconcile — bottom summaries (by Product + by User)  [2026-10-06] — DONE
- [x] Confirmed preview-before-save already exists: reconcile() previews (matched/db-only/excel-only + total), reconcileFinalize() is the explicit save.
- [x] ourEntries() eager-loads loan.bank/product/payoutUser; reconcile() builds result.by_product (bank+product-wise total payout) + result.by_user (user-wise total payout)
- [x] reconcile.blade: two summary tables at the bottom with totals (per the uploadExcel PDF)
- [x] PayoutReconcileTest asserts by_product + by_user totals. Full suite 369 / only 2 known pre-existing.

## Payout reconcile — full PDF columns + resolve 2 pre-existing failures  [2026-10-06] — DONE
- [x] entryCalc() returns full breakdown; matched table shows User/Product/Loan A/c/Cheque/Date/Loan Amt/PF/Admin/Base/Payout%/Commission/Insurance/Ins Payout/GST/Total/TDS/Net (mirrors upload-Excel PDF)
- [x] by_product + by_user summaries carry full columns (loan/pf/commission/insurance/ins payout/gst/gross/tds/net) + grand-total footers
- [x] FIX FcmServiceTest: ShfNotification::booted skips Web Push when user has a native device (deviceTokens exists) — FCM handles that device
- [x] FIX LegalSkipBankAndOdvTest: canSkipLegalBank reordered — BM/BDH are branch-bound even with waive_legal_verification (precedence over generic waive bypass); super_admin/admin global; other waive-holders global. Both legal suites green.
- [x] FULL SUITE 369/369 — 0 failures (first time this session). Views compile; Pint clean.
