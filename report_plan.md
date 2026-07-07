# Reports — Implementation Plans

> Historical: the Loan Report (Parts 0–1 below) is **IMPLEMENTED** (2026-07-07,
> `LoanReportTest` 8 green). The Turnaround fixes of Part 0 were implemented but the TAT
> report is now **scheduled for REMOVAL** — superseded by Phase 2 below (the corrected
> TAT math survives as the Completed-view TAT column in the Pipeline report).

---

# Phase 2 (IMPLEMENTED 2026-07-07): Remove TAT report → Loan Pipeline + Management Summary

> Status: **IMPLEMENTED** same day. Tests: `PipelineReportTest` (11) +
> `ManagementReportTest` (6) + `LoanReportTest` (8) green (32/32 with listing regressions).
> TAT report deleted (blade + css/js + routes + nav + `TurnaroundReportTest`). All report
> pages share the 4-role gate. Implementation notes: all date math is PHP/Carbon-side
> (driver-portable — the SQL diff helpers were removed as dead code); the parallel
> container stage is excluded from pipeline stage lines; management exceptions ignore the
> period filter (current-state).

## Final report suite

| Page | Route | Purpose |
|---|---|---|
| Loan Pipeline (new) | `/reports/pipeline` | Current status of every loan: stage, owner, aging, blockers |
| Loan Report (exists) | `/reports/loans` | Sanctioned/disbursed milestone listing — unchanged |
| Management Summary (new) | `/reports/management` | Funnel, trends, scoreboard, exceptions |

## 2A — Loan Pipeline

- **Status summary strip**: clickable chips All/Active/On Hold/Completed/Rejected/Cancelled
  with count + ₹ total; Active chip shows "N stages queued in parallel" sub-count.
- **Main table** (columns adapt to status; default Active; loan # links to stages page;
  most-stuck-first sort; aging colors ≤7 green / 8–14 amber / >14 red):
  - **Active**: stage lines per loan —
    (a) in-progress: stage — owner — days in stage — days with owner — ⚠ open-query flag.
        Days-with-owner = now − GREATEST(sa.started_at, last `stage_transfers` handoff to
        the current assignee). Driver-aware diff helpers already exist.
    (b) **pending sub-stages inside an active parallel block**: "⏳ pending (unassigned or
        pre-assigned name) — queued Nd" where N = days since the parent block's started_at.
        Pending future MAIN stages are excluded (noise). Rule keys off "pending + parent
        assignment in_progress", so `OPEN_RATE_PF_PARALLEL` layouts work automatically.
  - **On Hold**: status_reason + status_changed_at. **Completed**: sanctioned/disbursed ₹ +
    corrected end-to-end TAT (MAX completed stage). **Rejected**: rejected_stage/reason/by/at.
    **Cancelled**: reason/date.
- **Workload tab**: in-progress stages of active loans grouped by current holder — held
  count, oldest days, avg days, stuck>7d count, per-stage breakdown.
- **Filters**: status, bank, product, branch, user (COALESCE advisor/creator), period on
  created_at, stage, "stuck ≥ N days".

## 2B — Management Summary (one page, four sections; branch + period filters)

1. **Funnel**: Quotations → Converted → Sanctioned → Disbursed (count + ₹ + step
   conversion % + avg days between milestones). Sources: `quotations.is_converted`,
   loan created_at, completed `sanction`/`disbursement` stage assignments.
2. **Monthly trend (12 months)**: per month created / sanctioned / disbursed (count + ₹),
   CSS mini-bars (no chart lib — no-build-step stack).
3. **Branch & advisor scoreboard**: created, active, completed, rejection %, avg TAT,
   disbursed ₹, currently-stuck count; branch rows expandable to advisors.
4. **Exceptions digest**: stages in-progress >14d; queries unresolved >7d; on-hold >30d —
   each row linking to the loan.

## Backend

- `ReportController`: DELETE `turnaround()`, `turnaroundData()`, `overallTatData()`,
  `stageTatData()`, `getUserScope()`, `applyRoleScope()`. ADD `pipeline()/pipelineData()`,
  `management()/managementData()`. Rename `authorizeLoanReport()` → `authorizeReports()`
  (same gate); reuse `loanReportScope()` (rename → `reportScope()`), `applyFilters()`,
  `dayDiffSql()/hourDiffSql()`. Aggregates = single grouped queries, no N+1.
- Routes: remove turnaround pair; add pipeline + management pairs (names
  `reports.pipeline[.data]`, `reports.management[.data]`).

## Frontend

- NEW: `newtheme/reports/pipeline.blade.php` + `public/newtheme/pages/pipeline.{css,js}`;
  `newtheme/reports/management.blade.php` + `public/newtheme/pages/management.{css,js}`.
  Clone loan-report structure: fetch + inline `Loading…` (NOT the global SHF.loader).
- DELETE: `newtheme/reports/turnaround.blade.php`, `public/newtheme/pages/turnaround.{css,js}`.
- Nav: header Reports dropdown = Loan Pipeline / Loan Report / Management Summary (all
  gated to the 4 roles — dropdown hidden entirely for other roles); bottom-nav same three
  entries replacing the `view_reports`-gated Turnaround link.

## Tests

- DELETE `tests/Feature/TurnaroundReportTest.php` (user-approved via TAT removal).
- NEW `PipelineReportTest`: access matrix (both endpoints), per-status column payloads,
  owner + days-with-owner after a transfer, pending-sub-stage-in-parallel appears with
  queued days, pending MAIN stage does not appear, open-query flag, workload grouping,
  BM forged-branch scoping, summary chip counts, re-homed Part-0 tests (stage-based TAT
  on Completed view, COALESCE advisor attribution).
- NEW `ManagementReportTest`: funnel math on a seeded quotation→loan→sanction→disbursement
  chain, monthly bucketing, scoreboard rejection %, exception thresholds, BM scope.
- Re-run `LoanReportTest` (+ listing suites) for regressions. SQLite ext flags per lessons.

## Docs / deploy

- `.claude/routes-reference.md` (swap turnaround→pipeline/management), `.docs/loans.md`
  report section, `.docs/README.md` if it indexes reports, `tasks/todo.md`, lessons.
- No migration. No version bump (old public assets deleted, new URLs added).
- Server deploy: changed/new files + DELETE the three turnaround files +
  `view:clear` + `route:clear`. Prerequisite unchanged: migration `2026_06_23_185610`
  must exist on prod.

---

## Part 0 — Fix the existing Turnaround Time report FIRST (bugs confirmed 2026-07-07)

Audited `ReportController` + live data. Confirmed wrong output:

### Bug 1 (critical): Overall TAT uses `ld.updated_at` as the completion date
`overallTatData()` computes `DATEDIFF(ld.updated_at, ld.created_at)` — but `updated_at`
bumps on ANY later touch: bulk backfills (many completed loans share
`updated_at = 2026-05-30 19:45:25`), `$loan->touch()` in `transferStage()`, edits, model
hooks. Verified: SHF-202604-0006 reports **35 days**, real TAT **8 days**;
SHF-202604-0001 38 vs 11; SHF-202604-0004 38 vs 21. `status_changed_at` is NULL on all
completed loans, so the fix is stage-based:

- Join a derived table `(SELECT loan_id, MAX(completed_at) AS done_at FROM
  stage_assignments WHERE status='completed' GROUP BY loan_id) fin ON fin.loan_id = ld.id`
- TAT = `DATEDIFF(fin.done_at, ld.created_at)` (keep `WHERE ld.status='completed'`).

### Bug 2: Stage-filter dropdown is missing all sub-stages
`turnaround()` passes `Stage::enabled()->mainStages()` but the stage-tab DATA contains 6
sub-stage keys (app_number, bsm_osv, sanction_decision, legal_verification,
technical_valuation, original_document_verification). Users cannot filter by exactly the
stages they care about. Fix: pass all enabled stages ordered by sequence; render sub-stages
indented under their parent (or `<optgroup>`).

### Bug 3 (hardening): Overall tab inner-joins users on `assigned_advisor`
A completed loan with NULL advisor silently disappears (0 rows locally today, but the
app treats creator as fallback advisor everywhere else). Fix: attribute to
`COALESCE(ld.assigned_advisor, ld.created_by)` in the join.

### Known behaviors to document, NOT fix now
- Stage tab attributes the whole `started_at → completed_at` duration to the FINAL
  assignee (transfers/escalations don't split time per holder). Splitting requires
  `stage_transfers` segmentation — out of scope; note it under the table in the UI docs.
- `DATEDIFF` counts midnight crossings, not 24h periods (23:59→00:01 = "1 day"). The
  stage tab already mitigates with hour columns. Acceptable.
- Parallel sub-stages all get `started_at` when parallel processing opens — wait time
  counts toward stage TAT by design.

### Turnaround fix tests — extend into `tests/Feature/TurnaroundReportTest.php` (new)
- Completed loan whose `updated_at` is bumped AFTER completion still reports the
  stage-based TAT (the Bug-1 regression test).
- Loan with NULL `assigned_advisor` appears, attributed to creator.
- Stage filter accepts a sub-stage key and the page passes sub-stages to the dropdown.
- Role scoping unchanged: BM stays branch-scoped, self roles see own rows only.

## Goal

A new standalone report page listing loans, filterable by **product, user, bank, branch,
date range**, plus a **status select with exactly two options: Sanctioned / Disbursed**.
Role-gated (NOT permission-gated) to: **super_admin, admin, bdh, branch_manager**.

## Access & scoping rules

| Role | Access | Branch/User filter options + data scope |
|------|--------|------------------------------------------|
| super_admin | yes | all branches, all users |
| admin | yes | all branches, all users |
| **bdh** | yes | **all branches, all users** (explicit requirement — differs from turnaround report where BDH is branch-limited) |
| branch_manager | yes | only their `user_branches` branches + users of those branches |
| all other roles | **403** | — |

- No permission slug. Gate via `abort_unless($user->hasAnyRole(['super_admin','admin','bdh','branch_manager']), 403)`
  in BOTH the view and data endpoints (shared private helper in `ReportController`).
- Branch scope for branch_manager must be re-applied server-side in the data endpoint
  (never trust the dropdown value). Canonical pattern: `$user->branches()->pluck('branches.id')`
  — never `default_branch_id` (see tasks/lessons.md "Branch-scoped visibility").

## Backend — extend `app/Http/Controllers/ReportController.php`

Mirror the existing turnaround pair (`turnaround()` / `turnaroundData()`):

1. `loanReport()` — renders the page. Passes scoped filter options:
   - `$banks` = `Bank::active()` (all), `$products` = `Product::active()->with('bank')` (all)
   - `$branches` / `$users` per the table above (BM: users via `whereHas('branches', whereIn branch_ids)`)
2. `loanReportData(Request): JsonResponse` — one row per loan from `loan_details` (Eloquent or DB::table, whichever matches the query joins best):
   - Filters (same shape as existing `applyFilters()`): `bank_id`, `product_id`, `branch_id`,
     `date_from`/`date_to` on `loan_details.created_at`
   - `user_id` filter semantics: loan matches if user is `created_by` **OR** `assigned_advisor`
   - **`status` filter (required select, default `sanctioned`)**:
     - `sanctioned` → `sanctioned_amount IS NOT NULL`
     - `disbursed`  → `disbursed_amount IS NOT NULL`
     - (real columns on `loan_details`, added 2026-06-23 — see tasks/lessons.md)
   - Left-join `stage_assignments` for `sanction` and `disbursement` stage `completed_at`
     → "Sanctioned on" / "Disbursed on" columns
   - Response: rows + totals (`count`, `SUM(sanctioned_amount)`, `SUM(disbursed_amount)`)
     for the filtered set, Indian ₹ format via `NumberToWordsService::formatCurrency`
3. Columns: Loan #, Customer, Bank / Product, Branch, Advisor, Loan Amount,
   Sanctioned ₹, Disbursed ₹, Sanctioned on, Disbursed on, Status badge.

## Routes (`routes/web.php`, next to the turnaround pair, ~line 216)

```php
Route::get('/reports/loans', [ReportController::class, 'loanReport'])->name('reports.loans');
Route::get('/reports/loans/data', [ReportController::class, 'loanReportData'])->name('reports.loans.data');
```

No `permission:` middleware — role check lives in the controller.

## Frontend

- `resources/views/newtheme/reports/loan-report.blade.php` — clone the structure of
  `newtheme/reports/turnaround.blade.php` (filter panel, table, mobile cards, empty state,
  totals strip above the table).
- `resources/views/newtheme/reports/loan-report.css` + `loan-report.js` — same location
  pattern as `turnaround.css` / `turnaround.js` (note: these live in the VIEWS reports dir,
  not public/newtheme/pages — check how turnaround.blade.php includes them and match it).
- **Nav**: convert the plain "Reports" item in `newtheme/partials/header.blade.php` (~line 74)
  into a dropdown (`nav-dd-wrap` pattern, copy the Settings dropdown just below it):
  - "Turnaround Time" → `reports.turnaround` (everyone, unchanged)
  - "Loan Report" → `reports.loans` (only when `$u->hasAnyRole([...the 4 roles])`)
  - Same addition in `newtheme/partials/bottom-nav.blade.php` (~line 77).
- No `SHF_VERSION`/`SHF_SW_VERSION` bump needed IF all assets are brand-new URLs.
  If ANY existing public file is touched (e.g. shared CSS), bump both per the SW deploy rule.

## Agreed defaults (change only if the user says so)

1. Date range filters on **loan `created_at`** (consistent with turnaround) — not on
   sanction/disbursement event dates.
2. Status select has **only the two options**, no "All"; default **Sanctioned** on page load.

## Tests — `tests/Feature/LoanReportTest.php`

Copy setup patterns from `tests/Feature/LoanProductFilterTest.php` (admin() helper,
makeLoan() helper, `RefreshDatabase`, seed roles via `Role::firstOrCreate`).

- Access: 200 for super_admin, admin, bdh, branch_manager; **403** for loan_advisor,
  bank_employee, office_employee (both endpoints).
- BDH: sees loans + branch/user options from ALL branches.
- BM: options limited to own branches; data stays branch-scoped even when a foreign
  `branch_id` is forged in the request.
- Status filter: `sanctioned` returns loans with sanctioned_amount only; `disbursed`
  returns loans with disbursed_amount only.
- Product / bank / user / date filters narrow correctly; totals are correct.
- Reminder: run via `php -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll
  vendor/phpunit/phpunit/phpunit --filter=LoanReportTest` (CLI PHP lacks pdo_sqlite —
  see tasks/lessons.md).

## Docs sync (same change, not deferred)

- `.claude/routes-reference.md` — the 2 new routes (Reports section, ~line 161)
- `.docs/loans.md` — new "Loan Report" section (filters, scope table, status semantics)
- `tasks/todo.md` — checklist progress
- `tasks/lessons.md` — only if something new is learned

---

## Verified facts for the implementing session (checked 2026-07-07)

- Turnaround assets live in `public/newtheme/pages/turnaround.{css,js}` loaded with
  `?v={{ config('app.shf_version') }}` — loan-report assets follow the same pattern
  (the "views reports dir" note above is wrong).
- `getUserScope()` puts **BDH in branch scope** — the loan report needs its own
  `loanReportScope()` (super_admin/admin/**bdh** → all; branch_manager → branches; rest 403).
- Header "Reports" nav item: `partials/header.blade.php:74` (ungated); Settings
  `nav-dd-wrap` dropdown pattern begins line 80. Bottom-nav Reports: line 77, gated by
  `view_reports` permission — keep that gate for the parent, role-gate the Loan Report entry.
- `sanctioned_amount`/`disbursed_amount` migration `2026_06_23_185610` has run locally;
  **verify it has run on the live server before deploy** (was pending per lessons.md).
- Per the 2026-07-07 AJAX-loader convention: `loan-report.js` data loads use fetch() +
  inline `Loading…` placeholder (like turnaround's `tat-loader`) — NOT the global
  `SHF.loader` overlay.
- No `SHF_VERSION`/`SHF_SW_VERSION` bump needed for brand-new asset URLs — but Part 0
  edits `turnaround.blade.php` (dropdown) and possibly `turnaround.js`; if `turnaround.js`
  or any existing public asset changes, bump BOTH versions.

## Instructions for the implementing session

1. **Pre-read gate** (mandatory): `tasks/lessons.md`, `tasks/todo.md`,
   `.claude/routes-reference.md`, `.docs/loans.md`, `.docs/roles.md` + `.docs/permissions.md`,
   and read `ReportController.php` + `newtheme/reports/turnaround.blade.php` + its js/css in full.
2. Copy this plan's checklist into `tasks/todo.md` as an IN PROGRESS block before coding;
   tick items as you go.
3. Implement **Part 0 (turnaround fixes) first**, run its tests, then the new report:
   controller methods → routes → blade → css/js → nav → tests → docs.
4. Run `vendor/bin/pint --dirty --format agent` and the test suite filter above; also run
   `LoanProductFilterTest` + `LoanListingTaskOwnerTest` to catch listing regressions.
5. Verify the page renders per role (at minimum via the feature tests; ideally via
   chrome-devtools MCP against `php artisan serve` with seeded users).
6. Deploy notes for the user: new blades + controller + routes → plain deploy;
   `php artisan view:clear` on server; no migration needed; version bump only if a shared
   public asset was touched.
