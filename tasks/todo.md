# Task Tracker

Current and completed tasks. Updated as work progresses.

---

## DONE: Email-gated loan stage reset — web + CLI (2026-07-24)

Rewind a loan to an earlier stage. Engine extracted from `LoanSetStageCommand` into `LoanStageService::resetToStage()` so CLI + web share one implementation. Access gated to specific email accounts (NOT role/permission).

- [x] `LoanStageService::resetToStage($loan,$stageKey,?$phase,?$variant)` + `resolveResetUsers()` + ported reset helpers (target→in_progress, later stages→pending, re-open parallel parent, clear disbursement/valuation/app_number/expected_docket_date/is_sanctioned, recalc progress). Phased stages default to entry phase 1.
- [x] `LoanSetStageCommand` → thin wrapper: keeps interactive menu + prior-stage validation; delegates mutation to the service. Added non-interactive args `loan:set-stage {loan} {stage} [--phase=] [--variant=] [--force]`.
- [x] Gate: `config('app.stage_reset_emails')` (default superadmin@shfworld.com,admin@shfworld.com; `STAGE_RESET_EMAILS` env override) + `User::canResetLoanStages()`. Even super_admin denied unless email listed.
- [x] Web: `POST /loans/{loan}/stages/reset` (`loans.stages.reset`, outside permission group) → `LoanStageController@resetStage` (email abort_unless + stage_key validation + `reset_loan_stage` activity log). Red "Reset Stage" button + SweetAlert stage picker in `newtheme/loans/stages.blade.php` (gated, uses global Swal + SHF.loader). No 4400-line body touched.
- [x] Tests: `LoanStageResetTest` 5/5 (allowed reset, clears application_number, super_admin-role-wrong-email 403, unknown stage 422, CLI non-interactive). Regression: StageTransition/Escalation/QueryResolve/DateFilter 32/32. Pint clean; blades compile.
- [x] Docs: `.docs/workflow-developer.md` (reset section), `.claude/services-reference.md`, `.claude/routes-reference.md`, `.env.example`. No asset version bump (server-rendered blade only).
- [ ] **PENDING (server)**: deploy `LoanStageService.php`, `LoanSetStageCommand.php`, `LoanStageController.php`, `User.php`, `config/app.php`, `routes/web.php`, `loans/stages.blade.php`, tests. Optionally set `STAGE_RESET_EMAILS` in server `.env`; `php artisan config:clear && route:clear && view:clear`.

## DONE: Loan-list date filter → stage-completion activity (2026-07-24)

Loan-list date range filtered on `loan_details.created_at` → loan 96 (SHF-202606-0023, created 17 Jun, OTC completed 23 Jul) was hidden from a July window. Now the date range keys on stage completion.

- [x] `LoanController::loanData()` — replaced `created_at` date blocks + old "currently at stage" match. **Stage selected** → `whereHas('stageAssignments', stage_key AND status='completed')` with date bounds on that stage's `completed_at` (one closure; works for parent + sub-stage keys). **Date only** → correlated subquery on `MAX(completed_at)` (latest stage completion), `DATE()`-wrapped, MySQL+SQLite portable.
- [x] `tests/Feature/LoanStageDateFilterTest.php` — 4 tests (loan-96 stage+date match, out-of-range exclusion, stage-only completed vs in-progress, date-only latest-completion). Green. Regression: LoanProductFilterTest + LoanListingTaskOwnerTest 7/7.
- [x] Pint clean; MySQL sanity: both new queries return loan 96 for July window. Docs synced (`.docs/loans.md` `stage` + `date_from/date_to` filter notes).
- [x] No new dropdown added (user confirmed: drop the active/completed control). Stage filter meaning changed from "currently at stage" → "stage completed".

## DONE: Entry-based "disbursed" in management + loan reports (2026-07-14)

Partial disbursements (entries saved, stage not completed) now count as disbursed in BOTH reports. Rule: disbursed count = distinct loans with ≥1 active `disbursement_entries` row dated in period; disbursed amount = SUM of in-window tranche amounts (each tranche in its own month). Sanctioned stays stage-based. Coverage verified: all 63 stage-completed loans have mirror entries, 0 gaps, 0 NULL entry dates.

- [x] 1. Management funnel: `$disbursed` → per-loan entry aggregate (count / period tranche sum / avg days = sanction → first tranche). Date bounds via `toDateString()` (SQLite string-compare safe).
- [x] 2. Management trend: disbursed buckets from entries (distinct loans + tranche sums per month).
- [x] 3. `loanReportTotals()` disbursed side → entry aggregate (COUNT DISTINCT loan + SUM amount, whereDate window).
- [x] 4. `loanReportRows()`: disbursed view = INNER joinSub on windowed entry aggregate; sanctioned view LEFT joins the unwindowed aggregate; `disbursed_on` = MAX tranche date in both views (dsa join removed); `applyFilters` accepts `false` to skip dates.
- [x] 5. Tests: shared `addDisbursementEntry()` helper ×3 files; ManagementReportTest funnel on midnight-aligned dates + 2-tranche partial; trend asserts tranche bucket; LoanReportTest new cross-period partial test (July window: count 1 / ₹2L; June: ₹3L). 47/47 green.
- [x] 6. Pint pass, docs synced (.docs/loans.md). MySQL July: both reports now 10 disbursed / ₹3,45,03,551 — partial SHF-202606-0008 (₹24L tranche 6 Jul, stage open) now included; SHF-202605-0043 + SHF-202606-0026 (stages completed 14 Jul, tranches dated 29-30 Jun) correctly moved to June.
- [ ] **PENDING (server)**: deploy `ReportController.php` + `tests/Feature/{LoanReportTest,ReportExportTest,ManagementReportTest}.php` (same batch as earlier today; no new asset changes beyond the existing `SHF_VERSION=20260714100000` bump).

## DONE: Loan report = management figures + period totals (2026-07-14)

Row inclusion switched from `whereNotNull(amount)` to completed-milestone-stage (matches management funnel counts; amount renders "—" until docket P2 fills it). Totals strip/export footer show PERIOD totals (both milestones, milestone-dated, filter+scope aware) — independent of the status toggle and of which rows are listed.

- [x] 1. `loanReportRows()` — rows included by `whereNotNull(milestone completed_at)` instead of amount.
- [x] 2. New `loanReportTotals()` — two milestone aggregates (count + ₹ sum), same filters/scope/date semantics as rows.
- [x] 3. `loanReportData()` totals payload gains `sanctioned_count`/`disbursed_count`; export footer = `Period totals — sanctioned N / disbursed M loans` + period sums.
- [x] 4. Blade label ids + `loan-report.js` renders milestone counts in the totals card labels.
- [x] 5. Tests: LoanReportTest reworked (all loans get completed milestone stages; totals test now proves disbursed total includes non-listed loans; new NULL-amount test) + ReportExportTest footer/status/bulk tests updated. 46/46 green across 4 report suites.
- [x] 6. Pint pass, `node --check` clean, docs synced (.docs/loans.md). MySQL sanity: July = 11/11 both reports; totals ₹2,83,50,000 / ₹4,20,68,799 identical to management funnel.
- [ ] **PENDING (server)**: deploy `ReportController.php`, `reports/loan-report.blade.php`, `pages/loan-report.js`, `tests/Feature/{LoanReportTest,ReportExportTest}.php` (same `SHF_VERSION=20260714100000` batch; `config:clear && view:clear`).

## DONE: Loan report date filter → milestone date (2026-07-14)

Loan report filtered `date_from`/`date_to` on `ld.created_at` while the management funnel counts by `stage_assignments.completed_at` → 11 vs 1 mismatch. Switched loan report to filter by the milestone date (sanction completion for status=sanctioned, disbursement completion for status=disbursed).

- [x] 1. `applyFilters()` — optional `$dateColumn` param (default `{alias}.created_at`; pipeline/loan-list callers unchanged).
- [x] 2. `loanReportRows()` — passes `ssa.completed_at` / `dsa.completed_at` per status; rows ordered by milestone date desc (created_at tiebreak). Applies to data + export endpoints (shared core).
- [x] 3. Blade: Period label now shows "(sanction date)" / "(disbursement date)", synced to the status toggle in `loan-report.js`.
- [x] 4. Tests: +2 in `LoanReportTest` (May-created/July-sanctioned appears in July window; July-created/June-sanctioned excluded; disbursed view uses disbursement date). All 4 report suites: 45/45 green.
- [x] 5. Pint pass; `node --check` clean; docs synced (.docs/loans.md). MySQL sanity: July window now returns 10 sanctioned / 11 disbursed vs management's 11/11 — the 1-loan gap is loan 92 `SHF-202606-0019` (sanction completed 2026-07-14, `sanctioned_amount` NULL — amount never entered; data-entry gap, not report logic).
- [ ] **PENDING (server)**: deploy `ReportController.php`, `reports/loan-report.blade.php`, `pages/loan-report.js`, `tests/Feature/LoanReportTest.php` (same `SHF_VERSION=20260714100000` bump batch as the user-form fix; `config:clear && view:clear`).

## DONE: Fix false "City/Bank is required" on user create for bank_employee (2026-07-14)

Root cause: `assigned_locations[]`/`assigned_banks[]` each rendered twice in `users/_form.blade.php` (role-conditional select + checkbox variants); `SHF.validateForm`'s mixed-set `is(':checkbox')` read the hidden unchecked group → required always failed.

- [x] `user-form.js`: disable inactive location variants on role change + initial pass (banks already were).
- [x] `validateForm` in `shf-app.js` + `shf-newtheme.js` (identical copies): skip `:disabled` fields.
- [x] Bumped `SHF_VERSION` (.env/.env.example) + `SHF_SW_VERSION` (sw.js) → `20260714100000`; `config:clear` run.
- [x] Verified via headless-Chrome harness (real jQuery + real JS + form replica): 12/12 assertions pass post-fix; same harness fails on git-HEAD pre-fix code. `node --check` clean on all three files.
- [x] Docs: `.docs/frontend.md` validateForm note; `tasks/lessons.md` entry.
- [ ] **PENDING (server)**: deploy `public/newtheme/pages/user-form.js`, `public/newtheme/js/shf-app.js`, `public/newtheme/assets/shf-newtheme.js`, `public/sw.js`; set `SHF_VERSION=20260714100000` in server `.env`; `php artisan config:clear`.

## DONE: Export to Excel — Pipeline + Loan Report (2026-07-08)

Plan: `.claude/plans/zippy-coalescing-wreath.md`. True .xlsx via in-house ZipArchive writer (no new composer deps); exports honor applied filters + full unpaginated record set; pipeline exports active tab (loans w/ flattened stage lines, or workload).

- [x] 1. `app/Services/XlsxExportService.php` — minimal OOXML writer (inline strings, numeric `#,##0` + decimal + date-serial `dd/mm/yyyy` cells, bold header, wrap style, footer rows, ENT_XML1 escaping + control-char strip).
- [x] 2. `ReportController` behavior-neutral refactor: `pipelineRawRows()` / `workloadRows()` / `loanReportRows()` privates return raw values; JSON methods format on top. Pipeline/LoanReport/Management tests 31/31 green post-refactor.
- [x] 3. `pipelineExport()` + `loanReportExport()` + `flattenStageLines()`; routes `reports.pipeline.export` + `reports.loans.export` (same in-controller `view_reports` gate + scope).
- [x] 4. Export buttons in both report blades (`plExport`/`lrExport`, filters card) + `window.location = exportUrl + getFilters()` handlers in `pages/pipeline.js` / `loan-report.js`; bumped SHF_VERSION + SHF_SW_VERSION → `20260708120000`; `config:clear` run.
- [x] 5. `tests/Feature/ReportExportTest.php` — 12 tests / 208 assertions (permission gate + guest redirect, forged-branch scope, raw numeric cells + no ₹, date serials, totals footer, status/stuck/workload-tab filters, empty-valid xlsx, 60-loan all-records).
- [x] 6. Pint pass; full suite 233 passed / 1 pre-existing unrelated fail (FcmServiceTest, fails on clean tree too); docs synced (routes-reference, services-reference, .docs/loans.md incl. stale Loan Report access note fixed).
- [ ] **PENDING (server)**: deploy `app/Services/XlsxExportService.php`, `ReportController.php`, `routes/web.php`, both report blades, `pages/{pipeline,loan-report}.js`, `public/sw.js`, `tests/Feature/ReportExportTest.php`; set `SHF_VERSION=20260708120000` in server `.env`; `php artisan config:clear && view:clear && route:clear`.

## DONE: Phase 2 — remove TAT, add Pipeline + Management reports (2026-07-07)

- [x] 1. `ReportController`: TAT methods + getUserScope/applyRoleScope deleted; authorizeReports()/reportScope() shared; pipeline()/pipelineData() (summary chips, status-adaptive rows, stage lines incl. pending-in-parallel + queued days, container excluded, workload tab) + management()/managementData() (funnel, 12-mo trend, scoreboard, exceptions). All date math PHP/Carbon (driver-portable); SQL diff helpers removed as dead code.
- [x] 2. Routes: turnaround pair removed; reports.pipeline[.data] + reports.management[.data] added (6 report routes total).
- [x] 3. Blades: pipeline.blade.php + management.blade.php created; turnaround.blade.php DELETED.
- [x] 4. Assets: pages/pipeline.{css,js} + pages/management.{css,js} created; pages/turnaround.{css,js} DELETED.
- [x] 5. Nav: header Reports nav-dd = Pipeline/Loan Report/Management (4 roles, hidden otherwise); bottom-nav same 3 entries.
- [x] 6. Tests: TurnaroundReportTest DELETED (user-approved; salvageable tests re-homed); PipelineReportTest (11) + ManagementReportTest (6) + LoanReportTest (8) — 32/32 with listing regressions. MySQL sanity: loan 104 stage lines correct (legal/technical/rate_pf in-progress + ODV pending-queued; container excluded).
- [x] 7. Pint pass; `node --check` both JS; compiled blades lint clean; docs synced (routes-reference, .docs/loans.md, .docs/api.md, .docs/views.md, report_plan.md).
- [ ] **PENDING (server)**: deploy `ReportController.php`, `routes/web.php`, `reports/{pipeline,management,loan-report}.blade.php`, `partials/{header,bottom-nav}.blade.php`, `pages/{pipeline,management,loan-report}.{css,js}`, tests; DELETE `reports/turnaround.blade.php` + `pages/turnaround.{css,js}` on the server; `php artisan view:clear && php artisan route:clear`. No version bump (deleted + brand-new assets only). Prerequisite: migration `2026_06_23_185610` on prod.

## DONE: report_plan.md — Turnaround fixes + Loan Report (2026-07-07)

- [x] P0.1 `ReportController::overallTatData()` — TAT from MAX(stage completed_at) join, not `ld.updated_at`; advisor via COALESCE(assigned_advisor, created_by); driver-aware day/hour diff (SQLite julianday vs MySQL DATEDIFF). MySQL sanity: 38→11, 35→8, 38→21 days.
- [x] P0.2 `turnaround()` + blade — stage dropdown includes enabled sub-stages (indented `— ` under parent).
- [x] P0.3 Stage-tab user filter matches `sa.assigned_to` (was loan advisor — wrong rows); overall/loan-report user filter uses COALESCE(advisor, creator) **cast to int** (SQLite affinity).
- [x] P0.4 `tests/Feature/TurnaroundReportTest.php` — 5 tests green.
- [x] P1.1 `loanReport()` + `loanReportData()` + `loanReportScope()` (BDH=all; BM=branches; others 403 both endpoints).
- [x] P1.2 Routes `reports.loans` + `reports.loans.data` beside turnaround pair.
- [x] P1.3 `newtheme/reports/loan-report.blade.php` + `public/newtheme/pages/loan-report.{css,js}` (fetch + inline loader; totals strip; status select Sanctioned|Disbursed default Sanctioned).
- [x] P1.4 Nav: header Reports → nav-dd dropdown (Turnaround everyone; Loan Report 4 roles); bottom-nav entry.
- [x] P1.5 `tests/Feature/LoanReportTest.php` — 7 tests green (access matrix, BDH all-branch, BM forged-branch, status/filters/totals, sanctioned_on).
- [x] P1.6 Pint pass; 19/19 with LoanProductFilterTest + LoanListingTaskOwnerTest; compiled blades lint clean; `node --check` loan-report.js; docs synced (routes-reference, .docs/loans.md, report_plan.md status).
- [ ] **PENDING (server)**: deploy `ReportController.php`, `routes/web.php`, `reports/{turnaround,loan-report}.blade.php`, `partials/{header,bottom-nav}.blade.php`, `public/newtheme/pages/loan-report.{css,js}`, tests; `php artisan view:clear` + `route:clear`. **No version bump needed** (only new public assets). **Prerequisite: confirm migration `2026_06_23_185610` (sanctioned/disbursed columns) has run on the live DB.**

## DONE: Global AJAX loader (2026-07-07)

User-action AJAX gives no feedback → users think the task finished. jQuery ajax = user actions
(~48 sites); badge poll + DataTables use fetch() → auto-excluded from jQuery hooks.

- [x] 1. `shf-extras.css`: `.shf-ajax-loader` overlay + spinner (shf- prefix, CSS vars, z-index 20000).
- [x] 2. `shf-newtheme.js`: `SHF.loader` (ref-counted, 250ms delay, 300ms min-visible, 30s watchdog, on-demand DOM, pageshow reset) + `ajaxSend`/`ajaxComplete` hooks; `__shfLoaderInstalled` guard.
- [x] 3. Wrapped fetch() user actions: quotation `_create-script.blade.php` submit (try/finally), `loans/show.blade.php` DME update (.finally), `pages/users.js` `postJson` (+ delete refactored to reuse postJson). List-data fetches keep their inline `Loading…` by design.
- [x] 4. Bumped `SHF_VERSION` (.env/.env.example) + `SHF_SW_VERSION` (sw.js) → `20260707150000`; `config:clear` run.
- [x] 5. Node harness: 15/15 assertions (never-flash, delay, min-visible, ref-count, hooks, stray end, reset); `node --check` clean on shf-newtheme.js + users.js; compiled blades `php -l` clean.
- [x] 6. Docs: coding-feedback.md JS patterns + frontend.md new section + lessons.
- [x] 7. Coverage audit (all $.ajax/fetch/XHR sites): jQuery = auto-covered; list loads keep inline `Loading…`; poll/typeahead/push/offline silent by design. Gap found + fixed: `notifications.js` `post()` (mark-read / mark-all-read) wrapped with SHF.loader. Version re-bump → `20260707160000`.
- [ ] **PENDING (server)**: deploy `shf-newtheme.js`, `shf-extras.css`, `users.js`, `notifications.js`, `sw.js`, `_create-script.blade.php`, `loans/show.blade.php`; set `SHF_VERSION=20260707160000` in server `.env` + `php artisan config:clear` + `view:clear`.

## DONE: Query resolve — allow current stage assignee + admin, any active status (2026-07-07)

Root cause (loan 104): resolve was raiser-only (`raised_by === auth()->id()`), UI button only at status
`responded`. After escalation (raiser → BM → BDH) the raiser leaves the stage and nobody can close the
query; `hasPendingQueries()` (pending OR responded) blocks stage completion forever. Also the
"reassign open queries" block in `LoanStageService::transferStage()` was a no-op (set
`stage_assignment_id` to its existing value).

New rule: a non-resolved query (`pending` or `responded`) can be resolved by raiser OR current
`stageAssignment->assigned_to` OR admin/super_admin.

- [x] 1. `LoanStageController::resolveQuery()` — three-way authorization + 422 when already resolved.
- [x] 2. `StageQueryService::resolveQuery()` — ActivityLog `resolve_query` + notify raiser when someone else resolves (try/catch).
- [x] 3. `_stages-body.blade.php` — both Resolve buttons (sub ~1521, main ~3223): status pending|responded + raiser/assignee/admin.
- [x] 4. `LoanStageService::transferStage()` — fix no-op: open queries whose `assigned_to_user_id` = outgoing assignee follow to new assignee.
- [x] 5. Tests: `tests/Feature/StageQueryResolveTest.php` — 8 tests green; related suites (StageQueryRouting, StageTransition, StageEscalation, DashboardOpenQueries, CancelledRecordListingExclusion, LegalSkipBankAndOdv) 40/40 green. Compiled blade `php -l` clean.
- [x] 6. Pint pass + docs sync (`.claude/services-reference.md`, `.docs/workflow-developer.md`).
- [x] 7. Follow-up (blank Swal on blocked Approve): `decisionAction` approve now pre-checks `hasPendingQueries()` + `canTransitionTo('completed')` → 422 `{error}` before mutating (no more half-approve); decision-handler Swals fall back to `responseJSON.message`. Test added (9th) — approve blocked 422, nothing half-applied, succeeds after resolve.
- [ ] **PENDING (server)**: deploy `LoanStageController.php`, `StageQueryService.php`, `LoanStageService.php`, `_stages-body.blade.php`, `_stages-scripts.blade.php` + `php artisan view:clear`; then loan 104 → Denish (current assignee) resolves query #31 → re-click Approve on sanction_decision (idempotent — `is_sanctioned` already set).

## PLANNED (not started): Loan Report — sanctioned/disbursed (2026-07-04)

Full approved plan + execution instructions in **`report_plan.md`** (project root).
Summary: standalone report page, filters product/user/bank/branch/dates + status select
(Sanctioned | Disbursed via the real `sanctioned_amount`/`disbursed_amount` columns).
Role-gated (no permission slug) to super_admin/admin/bdh/branch_manager; BDH sees ALL
branches+users, BM only own branches. To execute: "implement report_plan.md".

## DONE: Product filter on loan listing (2026-07-04)

- [x] 1. `LoanController::loanData()`: `product_id` filter branch (plain `where`, like bank_id).
- [x] 2. `loans/index.blade.php`: `lxProduct` select (all roles, label "Product — Bank", `data-bank-id` per option). `LoanController::index` now passes `$products` (active, with bank; bank employees with `task_bank_id` get only their bank's products).
- [x] 3. `loans.js`: `product_id` in `readFilters()` + `allFilterIds` + filter-count defaults; bank→product cascade via `syncProductOptions()` (hides/disables non-matching options, clears stale selection, re-run on Clear).
- [x] 4. Bump `SHF_VERSION` (.env/.env.example) + `SHF_SW_VERSION` (sw.js) → `20260704160000`.
- [x] 5. Tests: `LoanProductFilterTest` (4 tests — filter narrows, no-filter returns all, null-product excluded, index renders lxProduct options). 12/12 green with related listing tests; `node --check` clean.
- [x] 6. Docs sync: `.docs/loans.md` filter list.
- [ ] **PENDING (server)**: deploy `loans.js`, `sw.js`, `index.blade.php`, `LoanController.php`; set `SHF_VERSION=20260704160000` in server `.env` + `php artisan config:clear`.

## DONE: Product listing slab range + big-number words fix (2026-07-04)

1. Products & Stages tab: slab badge shows min–max payout slab range (₹ low of first slab – ₹ high of last slab, Indian format).
2. Fix JS `numberToWordsEn/Gu`: 20000000000 → "undefined Hundred Crore Rupees / વીસ સો કરોડ રૂપિયા". PHP `NumberToWordsService` already recurses the crore segment (`innerDigitsEn/Gu`); the JS copies still passed the crore part through the 3-digit helper (`ones[20]` = undefined). Ported the recursive segment logic to `shf-app.js` + `shf-newtheme.js`.

- [x] 1. `_panes.blade.php`: slab badge → "N payout slabs · ₹ X – ₹ Y" via `NumberToWordsService::formatCurrency`.
- [x] 2. `shf-app.js`: recursive `seg()` in `numberToWordsEn` + `numberToWordsGu`.
- [x] 3. `shf-newtheme.js`: same fix in the guarded copies.
- [x] 4. Bump `SHF_VERSION` (.env/.env.example) + `SHF_SW_VERSION` (sw.js) → `20260704150000` — public JS changed.
- [x] 5. Tests: `NumberToWordsServiceTest` (unit, 10 tests incl. 20000000000) + `ProductPayoutConfigTest` badge-range assertion — 19/19 green; JS verified via node harness (14 EN+GU assertions pass, `node --check` clean).
- [x] 6. Docs sync (`.docs/settings.md`) + lesson (three words-helper copies must stay in sync).
- [ ] **PENDING (server)**: deploy `shf-app.js`, `shf-newtheme.js`, `sw.js`, `_panes.blade.php`; set `SHF_VERSION=20260704150000` in server `.env` + `php artisan config:clear`.

## IN PROGRESS: Stale service-worker cache — payout slabs only work after hard refresh (2026-07-04)

Root cause: `sw.js` serves `.css`/`.js` cache-first keyed by exact URL; `?v=` (`SHF_VERSION`) and `SHF_SW_VERSION` were both last bumped 2026-06-23, before the payout-slab deploy (2026-07-03). Clients keep the pre-deploy `loan-settings.css` (654 new lines missing) until a hard refresh bypasses the SW.

- [x] 1. `sw.js`: switch static assets from cache-first to **stale-while-revalidate** (serve cached instantly, refetch in background, update cache) so a missed version bump self-heals one reload later.
- [x] 2. Bump `SHF_SW_VERSION` in `sw.js` to `20260704103000` (purges all old caches on activate).
- [x] 3. Bump `SHF_VERSION` in `.env` + `.env.example` to the same timestamp (busts browser HTTP cache via `?v=`).
- [x] 4. Docs sync: `.docs/offline-pwa.md` strategy table + version-bump deploy rule.
- [x] 5. Lesson in `tasks/lessons.md`.
- [ ] 6. **PENDING (server)**: deploy new `public/sw.js`, set `SHF_VERSION=20260704150000` in server `.env` (superseded 103000/120000 — bumped again for the words-helpers crore fix), run `php artisan config:clear` (+ `config:cache` if used). Users then need ONE normal reload — no hard refresh.

## DONE: Amount-in-words hints on payout inputs (2026-07-04)

Live bilingual (EN/GU) amount-in-words hint under Max Payout Amount + slab Low/High inputs on `/loan-settings` → Products.

- [x] 1. Ported `SHF.numberToWordsEn`/`numberToWordsGu`/`bilingualAmountWords` from `shf-app.js` into `shf-newtheme.js` (global layout bundle) with `|| function` guards so shf-app.js stays authoritative where both load.
- [x] 2. `_scripts.blade.php`: delegated `input` handler on `#productMaxPayoutInput, .slab-low, .slab-high` creates/updates an on-demand `.shf-amount-words-hint` div (on-demand per the stripped-static-div lesson); guarded against stale-cached shf-newtheme.js missing the helpers. Edit-product prefill + reset trigger `input` so hints sync on programmatic `.val()`.
- [x] 3. Bumped `SHF_VERSION` (.env/.env.example) + `SHF_SW_VERSION` (sw.js) → `20260704120000` (public asset changed).
- [x] 4. Verified: `node --check` on shf-newtheme.js; blade compiled + `php -l` clean. Docs: layout comment + `.claude/rules/coding-feedback.md`.
- [ ] **PENDING (server)**: deploy `shf-newtheme.js`, `sw.js`, blades; set `SHF_VERSION=20260704120000` in server `.env` + `php artisan config:clear`.

## DONE: `products.max_payout_amount` → decimal(14,2), 2 decimal places (2026-07-04)

- [x] 1. Migration `2026_07_04_111341` — change column to `decimal(14,2)` nullable (plain signed decimal — MySQL 8 deprecates UNSIGNED DECIMAL; `min:0` validation enforces non-negative). `down()` restores unsignedBigInteger.
- [x] 2. `Product` cast `max_payout_amount` → `decimal:2` (reads back as string, e.g. `'50000.75'`).
- [x] 3. `WorkflowConfigController::storeProduct` rule → `nullable|numeric|decimal:0,2|min:0|max:100000000000`.
- [x] 4. Blade `_panes.blade.php`: input `step="0.01"`; payout-cap badge shows 2 decimals.
- [x] 5. Tests: decimal value persists (`50000.75`); >2 decimals rejected (`50000.759`). 8/8 pass.
- [x] 6. Docs sync: `.claude/database-schema.md`, `.docs/models.md`, `.docs/settings.md`.
- [ ] **PENDING (server)**: run `php artisan migrate --force` on live MySQL for `2026_07_04_111341_change_max_payout_amount_to_decimal_on_products_table`.

## DONE: `disbursement_entries` — normalized tranche rows with soft-delete audit (2026-07-03)

Goal: keep `disbursement_details.entries` JSON exactly as-is (still the form's source), but mirror every tranche into a new normalized `disbursement_entries` table (one row per entry). Rows are UPDATED (not recreated) on edit and SOFT-DELETED with `deleted_by` + `deleted_at` when an entry is removed from the form. Gives the future payout calculation clean queryable rows (joins to products/payout slabs) plus a deletion audit trail the JSON can't provide.

- [x] 1. Migration: create `disbursement_entries` — `id`, `loan_id` (FK loan_details CASCADE, INDEX), `disbursement_detail_id` (FK disbursement_details CASCADE, INDEX), `disbursement_date` (date), `method` (varchar 20), `product_id` (FK products nullOnDelete, nullable), `product_name` (snapshot), `loan_account_number` (varchar 50), `amount` (unsignedBigInt), `cheque_name`/`cheque_number`/`cheque_date` (nullable), **`is_active` (boolean default 1)**, `updated_by` (FK users nullable), `deleted_by` (FK users nullable), softDeletes, timestamps. **Backfill**: one row per existing JSON entry (`is_active` = loan not cancelled); write the new `row_id` back into each JSON entry.
- [x] 1b. **`is_active` lifecycle hook**: `LoanDetail::booted()` `updated` event — when `wasChanged('status')`: `is_active = !in_array(status, ['cancelled', 'rejected', 'on_hold'])` bulk-applied to the loan's entry rows (`active`/`completed` → 1; `cancelled`/`rejected`/`on_hold` → 0). Model-event hook covers `LoanController::updateStatus`, stage-flow rejection, and any other Eloquent status writer. Backfill uses the same rule.
- [x] 2. Model `DisbursementEntry`: `HasAuditColumns` + `SoftDeletes` (trait auto-fills updated_by/deleted_by); relations `loan`, `disbursement` (DisbursementDetail), `product`, `deletedByUser`.
- [x] 3. Relations: `DisbursementDetail::entryRows()` HasMany (named to avoid colliding with the `entries` JSON attribute); `LoanDetail::disbursementEntries()` HasMany.
- [x] 4. **Row identity (update-vs-delete semantics)**: each JSON entry carries `row_id` (= disbursement_entries PK). Blade posts it back via hidden `entries[N][row_id]`. `DisbursementService::processDisbursement()` sync inside the existing transaction: posted row_id (validated as belonging to THIS disbursement) → update row; no row_id → create row; existing live rows missing from the payload → `->delete()` (soft; trait stamps deleted_by). New row ids written back into the JSON entries before saving `entries`.
- [x] 5. Controller `store()`: accept `entries.*.row_id` nullable integer (ownership enforced in service — foreign/stale ids treated as new rows, not hijacked).
- [x] 6. Blade `disbursement.blade.php`: hidden `row_id` input in the JS row template + hydration from saved entries / `old()`.
- [x] 7. No behavior change to stage completion, OTC, totals, or the JSON read sites — `entryList()` remains the display source.
- [x] 8. Tests (`DisbursementEntrySyncTest`): initial save creates N rows (+ row_ids persisted in JSON, is_active=1); edit modifies one row in place (same PK, updated values, updated_by), removes one (soft-deleted with deleted_by + deleted_at), adds one (new row); cheque fields stored; foreign row_id not hijacked; **cancel loan → rows is_active=0; reactivate → is_active=1** (soft-deleted rows untouched).
- [x] 9. Pint + docs sync: `.claude/database-schema.md`, `.docs/models.md`, `.claude/services-reference.md`, `.docs/loans.md`, lessons/todo.

Notes: `notes` stays on the parent `disbursement_details` (disbursement-level, not per-tranche). Loan hard-delete cascades rows; loan soft-delete leaves them (consistent with other children).
- [ ] **PENDING**: run migration `2026_07_03_173425_create_disbursement_entries_table` on live MySQL DB (deploy `php artisan migrate --force`, together with the other two 2026_07_03 migrations).

## DONE: Product payout config — flag + cap + slab table (2026-07-03)

Products carry payout config for the future "payout to loan creator" calculation (STORAGE ONLY). `is_pf_based` flag, optional `max_payout_amount` cap, payout slabs in dedicated `product_payout_slabs` table (low/high range + ₹-or-% payout per row). Slabs entered the same way for PF and non-PF products (no pf_percent field). Surfaces: `/loan-settings` → Products & Stages tab.

- [x] 1. Migration `2026_07_03_154343` — `products.is_pf_based` + `max_payout_amount`.
- [x] 2. Migration `2026_07_03_154344` — `product_payout_slabs` table (product_id FK CASCADE, low/high unsignedBigInt, payout_type varchar(10), payout_value decimal 12,2).
- [x] 3. Models: `ProductPayoutSlab` (TYPE constants, casts, `product()`); `Product::payoutSlabs()` ordered by low_amount + new fillable/casts.
- [x] 4. `storeProduct()`: validation (high `gt` low, type in amount|percent, percent ≤100, no-overlap after sort) + transactional full-replace slab sync; `is_pf_based` via `$request->boolean()`.
- [x] 5. Add Product form: code field added, PF checkbox, max payout input, slab repeater (JS-built rows) + slab client validation in `#productForm` submit.
- [x] 6. Edit Product: `.shf-edit-product` button populates the same collapse form (banks/branches pattern — page has no Bootstrap CSS so no modal); slabs via `data-slabs` JSON; `resetProductForm()` wired into `.shf-form-cancel`.
- [x] 7. Row badges: "PF Based" (orange), "Payout cap ₹X" (blue), "N payout slabs" (purple).
- [x] 8. Soft-delete keeps slabs (Product uses SoftDeletes → FK cascade only on hard delete) — intentional, covered by test.
- [x] 9. Tests: `ProductPayoutConfigTest` — 7 green (store, edit-replace, overlap, high≤low, percent>100, plain product, soft-delete). Full suite 156/157 (same pre-existing FcmServiceTest failure).
- [x] 10. Pint + docs: database-schema.md (products + new table), models.md, settings.md, CLAUDE.md model count, lessons.
- [ ] **PENDING**: run both migrations on live MySQL DB (with the disbursement `entries` migration) via deploy `php artisan migrate --force`.

## DONE: Multi-entry tranche disbursement (2026-07-03)

Disbursement is a list of **tranches** saved over time. Each entry: `{disbursement_date, method (fund_transfer|cheque), product_id, product_name, loan_account_number, amount, cheque_name?, cheque_number?, cheque_date?}`. Method per entry (mixed NEFT + cheque allowed). Total auto-computed. Stage auto-completes when total ≥ sanctioned (fallback loan_amount); manual "Mark as Fully Disbursed" for under-disbursement.

- [x] 1. Migration `2026_07_03_151044_add_entries_to_disbursement_details_table` — `entries` json + backfill from legacy columns.
- [x] 2. `DisbursementDetail`: `entries` fillable + cast; `entryList()` (legacy fallback), `entryTotal()`, `hasChequeEntries()`.
- [x] 3. `LoanDisbursementController::show()`: bank products (bank_id, name fallback), disbursed-so-far/target; locked when loan closed OR stage completed.
- [x] 4. `LoanDisbursementController::store()`: per-entry validation incl. product-belongs-to-bank + cheque-field requirement; `product_name` snapshotted.
- [x] 5. `DisbursementService`: derived legacy columns, total mirrored every save, auto-complete only when total ≥ `disbursementTarget()` AND stage in_progress; `markFullyDisbursed()`.
- [x] 6. Route `loans.disbursement.complete` + "Mark as Fully Disbursed" Swal-confirmed button.
- [x] 7. Blade + JS rebuilt: entry rows with per-row method toggle, Add/Remove, running total + bilingual words, Sanctioned/Disbursed/Remaining strip, old()+saved hydration.
- [x] 8. Read sites: `_stages-body` disbursement summary → entries table + "disbursed so far" while in-progress; OTC list filters cheque entries (with product column).
- [x] 9. Tests: `DisbursementMultiEntryTest` (8 green) + updated `LoanSanctionedDisbursedColumnsTest` to new payload. Full suite: 149/150 (1 pre-existing `FcmServiceTest` failure in web-push skip, unrelated).
- [x] 10. Pint + docs sync: database-schema, routes-reference, services-reference, models.md, loans.md, workflow-developer.md, lessons.
- [ ] **PENDING**: run migration on live MySQL DB (production env → `php artisan migrate --force` via deploy; backfill runs inside the migration).

---

## DONE: sanctioned_amount & disbursed_amount as loan_details columns + per-loan listing columns (2026-06-23)

Each loan row in listings shows that loan's own sanctioned + disbursed amount, read from new real columns on `loan_details` (not parsed from stage_notes JSON). Surfaces: main loans list + dashboard "Loans" widget. Display: **separate columns** "Sanctioned" + "Disbursed"; `—` when no value.

- [x] Migration: `sanctioned_amount` + `disbursed_amount` (unsignedBigInteger nullable) on `loan_details` + backfill (sanctioned ← docket/sanction notes; disbursed ← `disbursement_details.amount_disbursed`)
- [x] `LoanDetail`: fillable + integer casts + `formatted_sanctioned_amount` / `formatted_disbursed_amount` accessors (null when empty)
- [x] Write sync: `LoanStageController::saveNotes()` (docket wins, sanction fills only when null) + `DisbursementService::processDisbursement()`
- [x] `LoanController::loanData()` returns `sanctioned_info` / `disbursed_info`; `loans.js` adds desktop columns + mobile rows
- [x] `DashboardController::newthemeLoans()` + `dashboard.js` add the two columns
- [x] Test `tests/Feature/LoanSanctionedDisbursedColumnsTest.php` (5 tests green on sqlite)
- [x] Docs: database-schema.md, models.md, services-reference.md, loans.md, lessons.md
- [ ] **PENDING**: run the migration on the live DB (env reports APPLICATION IN PRODUCTION → `php artisan migrate` was declined). Backfill runs automatically inside the migration.

---

## Completed: Move sanction financials to docket login (2026-04-20)

Goal: at the sanction stage, task owner captures only the sanction date. The loan financials (sanctioned amount, sanctioned rate, tenure in months, EMI) are now captured by the office employee at docket login (Phase 2) alongside the login date.

- [x] `LoanStageController::getSanctionRequiredFields` — returns only `sanction_date`.
- [x] `LoanStageController::getDocketRequiredFields` (new) — requires login_date + 4 financial fields at Phase 2.
- [x] `isStageDataComplete` — sanction done on date alone; docket done only when all 5 fields present.
- [x] EMI ≤ sanctioned_amount sanity check moved from sanction branch to docket branch.
- [x] Blade: removed 4 fields from sanction Phase 3 form + edit modal + saved-data display. Added them to docket Phase 2 form + saved-data display. Default `sanctioned_rate` now hydrates from `rate_pf.interest_rate` in docket (previously sanction).
- [x] `LoanController::loans` list HTML — reads sanctioned_amount preferring docket, falls back to sanction notes for legacy loans.
- [x] `LoanDisbursementController::show` — same fallback pattern for disbursement form.
- [x] `LoanSetStageCommand` — reset/menu/completion metadata aligned with new fields.
- [x] `SeedScreenshotLoans` — seeds financials under docket notes.
- [x] `tests/Feature/SanctionDocketFieldMoveTest.php` — 8 tests (required fields + data-complete predicates). All 55 suite tests pass.
- [x] `.docs/workflow-developer.md` — required-fields list updated.
- [x] Pint run.

Notes: no DB migration required (notes JSON is schemaless). Legacy loans with sanction-stage financials render fine because consumer sites fall back to sanction notes when docket notes are absent.

---

## Completed: Quotation hold/cancel + daily reminders (2026-04-18)

- [x] Migration: `quotations` table — add `status` (active/on_hold/cancelled), hold + cancel columns, indexes on `status` and `hold_follow_up_date`.
- [x] Migration: `general_tasks` table — add nullable `quotation_id` FK.
- [x] Migration: seed `hold_quotation`, `cancel_quotation`, `resume_quotation` perms + grants.
- [x] `config/app-defaults.php`: `quotationHoldReasons` + `quotationCancelReasons` vocab.
- [x] `config/permissions.php`: append new slugs to Quotations group.
- [x] `Quotation` model: constants, casts, scopes, `heldBy`/`cancelledBy` relations, label accessors.
- [x] `GeneralTask` model: add `quotation_id` fillable + relation.
- [x] `QuotationController`: `hold()`, `cancel()`, `resume()` actions. Hold auto-creates DVR. Notifies creator.
- [x] Routes: 3 quotation routes + 2 settings routes.
- [x] `LoanConversionController`: block conversion of cancelled quotations.
- [x] Settings UI: Quotation Reasons tab with two sub-sections.
- [x] Dashboard quotation tab: status filter + status column + action buttons (desktop + mobile).
- [x] Quotation show page: status banner + modals + auto-open via `?action=`.
- [x] `SendDailyReminders` command + 08:00 / 20:00 schedule.
- [x] Tests: `QuotationHoldCancelTest` (5 cases, all passing).
- [x] Pint + full test suite (47 passing).
- [x] Docs: quotations, permissions, settings, dvr, general-tasks, database-schema, routes-reference, services-reference, lessons.

---

## In Progress: Newtheme HTML uplift (2026-04-20)

**Goal**: Update every HTML page in `newtheme/` to match the forms, filters, stats, modals, multi-phase stage UI, and permission-gated sections found in the existing Blade views — while preserving the newtheme visual language (classes `card`, `tbl`, `badge`, `pill`, `stage-card`, `kv-stack`, tokens `--accent`/`--ink-*`, fonts Jost/Archivo/JetBrains Mono). HTML-only in this pass — no Blade conversion.

**Rules agreed**:
- Preserve newtheme CSS classes — **do NOT** pull in `shf-*` Bootstrap classes from `public/css/shf.css`.
- Bilingual (English / Gujarati) labels in-place on mockups (matches real UX).
- OK to add `newtheme/assets/shf-workflow.css` for stage/phase-specific additions (don't edit `shf.css`/`shf-extras.css`).
- Keep demo data in `<script>` blocks where newtheme already does so; only structure/markup needs to match real workflow.
- Batch phase-by-phase; pause for user sign-off between phases.

### Phase 1 — Conventions (agreed with user) ✓
- [x] 1.1 HTML-only in newtheme/. No Blade wiring.
- [x] 1.2 Class vocabulary cheat sheet from `shf.css` + `shf-extras.css` captured.
- [x] 1.3 Multi-phase stage rendering convention: extend `.stage-card` with phase strip — `<div class="phase-strip">` containing `<span class="phase-pill phase-done/active/pending">`.

### Phase 2 — Shared shell ✓
- [x] 2.1 Verified `newtheme/assets/menu.js` NAV_ITEMS matches real navigation. Bilingual `SHF_STAGES` present.
- [x] 2.2 No changes needed to menu.js.
- [x] 2.3 Created `newtheme/assets/shf-workflow.css` — phase pills, role chips, stat cards, filter panel, decision grid, doc grid, query items, chips, tag input, amount input, mobile m-cards, status banners, timeline role dots.
- [x] 2.4 Interactivity parity with live code: every newtheme HTML page now loads the same vendor libs as production (`../public/vendor/jquery/jquery-3.7.1.min.js`, `bootstrap-datepicker.min.js`, `Sortable.min.js`, `sweetalert2.all.min.js`, plus datepicker3 + SweetAlert2 CSS). Batch-injected via one-shot script with `<!-- SHF-VENDOR-START/END -->` markers for idempotent re-runs.
- [x] 2.5 Created `newtheme/assets/shf-forms.js` — mirrors `public/js/shf-app.js` exactly. Auto-wires: `.shf-datepicker` (Bootstrap Datepicker, dd/mm/yyyy), `.shf-amount-input` (Indian-comma live formatting + bilingual EN/GU words), `.shf-confirm-delete` (SweetAlert2), `.shf-password-toggle`, `.shf-collapsible[data-target]` (slideToggle filters), `[data-sortable]` (SortableJS), `.tag-input-wrap` (Enter/backspace tag add/remove with hidden-input sync + auto-add pending value on form submit), `.chip-toggle` (single-element toggle), auto-expand textareas, form novalidate. Exposes `SHF.validateForm`, `SHF.formatIndianNumber`, `SHF.numberToWordsEn/Gu`, `SHF.bilingualAmountWords`, `SHF.rescan`.
- [x] 2.6 Added Noto Sans Gujarati font link on every page for bilingual labels.
- [x] 2.7 Moved vendor tree into `newtheme/vendor/` (copy of `public/vendor/`) so demo is self-contained and path `vendor/…` works under any host. Rewrote `../public/vendor/` → `vendor/` in all 47 HTML pages.
- [x] 2.8 Created `newtheme/assets/shf-data.js` — single source of truth for demo content: company, banks (8, real names), branches, products, customer types, loan/quotation statuses, 7 roles (with GU labels + can_be_advisor + chip slug), 17 stages (with phases[] for legal/technical/rate_pf/sanction/docket/esign), tenures, IOM/GST, documents EN+GU by customer type, DVR contact types + purposes, task priorities/statuses, 44 permissions × 7 groups. Injected via `<!-- SHF-DATA-START/END -->` marker in every HTML page.
- [x] 2.9 Fixed datepicker init timing: rewrote `shf-forms.js` so `SHF.initDatepickers / initAmountFields / initTagInputs / initSortables / rescan` are defined EAGERLY at top-level IIFE (callable before doc-ready). Only the initial sweep + global handlers live inside doc-ready. Added MutationObserver to auto-init dynamically inserted markup.
- [x] 2.10 Added CSS overrides in `shf-workflow.css`: `.datepicker-dropdown { z-index: 10050 }` + accent-branded active/today cells; SweetAlert2 popup styling; extra responsive breakpoint at 599px collapsing stat-row to 1 col, filter-grid to 1 col, and stacking page-header actions.
- [x] 2.11 Rewrote `loans.html` to consume SHF_DATA: filter grid built dynamically from `loanStatuses`/`customerTypes`/`banks`/`branches`/`stages`/`roles`, 48 realistic rows generated from the Indian name pool + real banks + real products + real stages + bilingual stage labels. Filter datepickers init explicitly after DOM insertion.

### Phase 3 — Core listing pages (stats + filters + table + mobile card)
- [x] 3.1 `loans.html` — 4 stat cards; full 9-filter panel (status/type/bank/branch/stage/owner role/docket/date-from/date-to) + per-page selector; real column set (Loan #, Customer, Bank/Product, Amount, Stage, Owner, Status, Date, Actions); mobile m-card layout; bilingual EN/GU throughout; role-chip on owner cell; status pill; empty state.
- [ ] 3.2 `quotations.html` — stats + status/type/bank/created-by/date filters; hold/cancel action menu.
- [ ] 3.3 `customers.html` — stats + type/branch/created-by filters; linked loans count.
- [ ] 3.4 `dvr.html` — stats + contact type/purpose/outcome/follow-up status/visit-chain/branch/user/date filters.
- [ ] 3.5 `general-tasks.html` — stats + status/priority/assigned/created/loan-link/date filters; "+ new task" modal.
- [ ] 3.6 `users.html` — role/branch/active filters; permission-gated create.
- [ ] 3.7 `roles.html` — can_be_advisor column + permissions count.
- [ ] 3.8 `notifications.html` — read/unread + type filters + mark-all-read.
- [ ] 3.9 `activity-log.html` — action/subject/user/date filters.

### Phase 4 — Loan workflow (biggest)
- [ ] 4.1 `loan-show.html` — summary card, stage pipeline dots, quick links, remarks/queries pane.
- [ ] 4.2 `loan-stages.html` — real per-stage forms for S1–S11 + multi-phase role handoffs (legal 3, rate-pf 3, sanction 3, docket 3, esign 4), sanction decision gate, OPEN_RATE_PF_PARALLEL indicator, stage notes/query/transfer modals.
- [ ] 4.3 `loan-create.html` — customer linkage, branch, bank, product, amount, tenure chips, assigned advisor.
- [ ] 4.4 `loan-edit.html` — mirror + status/assignment controls.
- [ ] 4.5 `loan-documents.html` — sortable doc grid, upload slots, received/rejected marking, bilingual labels.
- [ ] 4.6 `loan-disbursement.html` — mode (cheque/NEFT), tranches, dates, cheque details.
- [ ] 4.7 `loan-valuation.html` + `loan-valuation-map.html` — valuation fields + Leaflet map shell.
- [ ] 4.8 `loan-timeline.html` — event feed with role-colored dots.
- [ ] 4.9 `loan-transfers.html` — transfer history + new-transfer modal.

### Phase 5 — Quotation workflow (read `.docs/quotations.md`, `.docs/pdf-generation.md`, `.claude/services-reference.md` first)
- [ ] 5.1 `quotation-new.html` — customer type/lookup, amount (Indian format), tenures chips, selected banks/products, per-bank rate+PF, IOM, GST, doc checklist EN+GU by customer type.
- [ ] 5.2 `quotation-show.html` — comparison cards, PDF actions, share.
- [ ] 5.3 `quotation-convert.html` — pre-fill loan-create.

### Phase 6 — DVR, tasks, customers
- [ ] 6.1 `dvr-create.html` / `dvr-show.html` — full fields + visit-chain + link-to-loan/quotation.
- [ ] 6.2 `task-create.html` / `task-show.html` — priority, due, assignee, status, loan link, comments.
- [ ] 6.3 `customer-create.html` / `customer-edit.html` / `customer-show.html` — type-specific fields + linked loans/quotations.

### Phase 7 — Admin & settings (read `.docs/settings.md`, `.docs/permissions.md`, `.claude/database-schema.md` first)
- [ ] 7.1 `settings.html` — tabs: Company, Banks, IOM Charges, Tenures, GST, Services, Documents (EN+GU × customer type), DVR Contact Types, DVR Purposes, Quotation Hold/Cancel Reasons.
- [ ] 7.2 `settings-hub.html` — tile index.
- [ ] 7.3 `loan-settings.html` — workflow toggles, per-product stage mapping, feature flags, auto-assignment rules.
- [ ] 7.4 `permissions.html` — 44 perms × 7 groups grid + role toggles + user override.
- [ ] 7.5 `roles.html` + `role-edit.html` — slug, can_be_advisor, perms matrix.
- [ ] 7.6 `users.html` + `user-edit.html` — branches pivot, roles multi-select, active, impersonate.
- [ ] 7.7 `reports.html` — turnaround filters + chart/table + export.
- [ ] 7.8 `profile.html` — password, profile, linked branches.
- [ ] 7.9 Auth pages — Breeze flow inputs verified.

### Phase 8 — Verification
- [ ] 8.1 Cross-check pages against screenshots in `screenshots/`.
- [ ] 8.2 Link audit + menu alignment.
- [ ] 8.3 Gap report — done/partial/untouched.

---

## Archived: Comprehensive Improvement Plan (2026-04-17)

Full plan approved across iterations. Scope confirmations:
- DB: MariaDB already in place. No engine migration.
- Permission tables (roles/permissions/role_permission/role_user/user_permissions) exist. No `spatie/laravel-permission` swap.
- PWA: online-only gate. No offline data, no offline writes.
- Out of scope: PDF path, DataTables server-side, frontend framework swap, error tracking.

### Phase 0 — Baseline ✓

- [x] **0.1** `CONTRIBUTING.md` with branch protection + conventional commits
- [x] **0.2** MariaDB sanity audit — all JSON columns use native `json` (except `app_config.config_json` `longText` by design); collation `utf8mb4_unicode_ci`
- [x] **0.3** Queue driver confirmed (`database`), supervisor/systemd/NSSM docs in `.docs/ops.md`

### Phase 1 — Quick wins ✓

- [x] **1.1** Permission cache invalidation + `Gate::before` + `@can` integration + tier-matrix tests (10 tests)
- [x] **1.2** `FileUploadService` with MIME + mimetype whitelist (jpeg/png/webp/pdf), hashed filename, private storage (`storage/app/private/`), 7 unit tests
- [x] **1.3** XSS audit — 25 `{!! !!}` uses classified: 18 dead ternaries, 7 static-controlled HTML, 0 risky. PDF uses `$e = htmlspecialchars(...)` for all dynamic fields
- [x] **1.4** Impersonation audit — `TakeImpersonation`/`LeaveImpersonation` logged with `original_user_id`; `ActivityLog::log()` captures `impersonator_id` in properties for every action during impersonation (4 tests)

### Phase 2 — Code quality ✓

- [x] **2.1** `app/Validation/LoanValidationRules` + `DvrValidationRules` extracted; `LoanController` and `DailyVisitReportController` updated. Quotation has no inline validation to extract. Stage-transition rules are small per-method — kept inline.
- [x] **2.2** Service audit written to `.docs/service-audit.md`. `LoanStageService` flagged as god service (27 methods, 6 responsibilities); split deferred until Phase 5.2 tests exist. All other services cohesive.
- [x] **2.3** Found `LoanDetail::getCurrentOwnerAttribute` doing `User::find()` per row — now reuses `advisor` relation. Added `advisor` to dashboard eager load. Enabled `Model::preventLazyLoading()` in non-prod.

### Phase 3 — Library replacements

- [x] **3.1** `spatie/laravel-activitylog` installed. `activity_log` table created with custom `ip_address`/`user_agent` cols. Backfill migration copies legacy `activity_logs` rows (kept in place for historical read). `App\Models\ActivityLog` extends Spatie's `Activity`; legacy `::log()` helper preserved; `action`/`user_id`/`user` accessors kept for backward compat. `DashboardController::activityLog*` updated to new cols. 5 compat tests added.
- [x] **3.2** Declined after audit. Config shape (4 customer types × bilingual doc lists, nested iom/DVR structs) fits `spatie/laravel-settings` poorly. Rationale in `.docs/settings-package-decision.md`. `ConfigService` stays.

### Phase 4 — Notifications + Real-time + Web Push

- [x] **4.1** `NotificationBroadcast` event + `ShfNotification::created` hook + `routes/channels.php` with private-user auth. Broadcast defaults to `log` driver (no-op) until Reverb is flipped on. 3 tests.
- [~] **4.2/4.3** Reverb + Web Push setup written to `.docs/realtime-setup.md` (packages, env, supervisor, Echo frontend, VAPID key generation, SW push handler). Implementation deferred — requires hosting + VAPID key decisions.

### Phase 5 — Testing

- [x] **5.1** No-op — no pre-existing Breeze test files on disk to delete.
- [~] **5.2** Starter workflow test suite shipped (13 tests: state machine, illegal-transition rejection, query-blocks-completion, initialization, next-stage, role resolution). Full ~60-test matrix across role handoffs/multi-phase stages deferred — this is the base the split in Phase 2.2 would build on.

### Phase 6 — PWA online-only gate ✓

- [x] **6.1** `public/offline.html` shell with bilingual messaging + retry. `sw.js` rewritten to cache static assets + offline shell only; pages go network-first with offline shell fallback; XHR/fetch returns 503 JSON; non-GET passthrough. `offline-manager.js` neutered — legacy methods no-op/shim, `setupNetworkListeners` disables nav links + submit buttons while offline. `.docs/offline-pwa.md` fully rewritten with rationale, strategy table, testing recipe, and migration notes for call sites.

---

## Recently Completed

- [x] Complete Documentation Regeneration from codebase scan (2026-04-15)
- [x] Turnaround Time Report + Loan Duration (2026-04-15)
- [x] SHF Operational Manual v3 → v4 Update (2026-04-15)
- [x] Bank-Wise Dynamic Stage Role Configuration (2026-04-16)
- [x] Docket Login + OTC Clearance + Stage Tooling (2026-04-14)
- [x] DVR (Daily Visit Report) Module (2026-04-14)
- [x] Workflow Stage Flow Changes (2026-04-14)

---

## Completed

(historical tasks archived to .ignore/tasks/todo.md)
