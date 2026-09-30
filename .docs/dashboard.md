# Dashboard

Single-page dashboard for the authenticated user at `GET /dashboard`. Controller: `DashboardController`.

## Structure

A KPI-styled tab bar in the header + a tabbed panel with several DataTables fed by AJAX endpoints. No fixed sidebar layout — the navbar is the only chrome.

## Header KPI strip (disabled) → tab bar carries the chip look

The header used to show a **KPI chip strip** (`#kpiStrip`) — a white card of chips, each an
icon + big number + uppercase label, separated by vertical dividers, rendered client-side by
`dashboard.js` from `D.kpi` (`DashboardController::newthemeKpi()`).

That strip is now **disabled** — the markup is commented out in
`resources/views/newtheme/dashboard.blade.php` and the matching render block is commented out in
`public/newtheme/pages/dashboard.js`. **Both must stay commented together**: with the `#kpiStrip`
element gone, the live render block would do `$("kpiStrip").innerHTML = …` on `null` and throw,
aborting the rest of dashboard init (tab counts, tab switching). The `newthemeKpi()` method and its
`'kpi'` payload are intentionally **left in place** (unused) so the strip can be restored quickly.

The **tab bar took over the chip look** (see "Tab bar visual style" below), so the header still reads
as a KPI strip — just interactive.

## Tabs

All tabs are permission-gated for visibility. Default selection is **data-driven** — pick the tab that has the most actionable items, not merely the first visible one.

### Tab bar visual style (KPI-chip look)

The tab row (`.page-header .tabs`) is styled to mirror the retired KPI strip, so the two read as one
system. Styling is a **page-scoped override in `public/newtheme/pages/dashboard.css`** (shared
`shf.css` / `shf-workflow.css` are untouched):

- Container: white rounded card — `#fff`, `1px solid var(--line)`, `border-radius:10px`,
  `box-shadow:var(--sh-1)`, `padding:8px 14px`, `margin-top:14px` — copied from `.kpi-strip`.
- Each tab is a chip in **icon → number → label** order (matching a KPI chip's icon → value → label):
  - a tone-coloured leading icon (`.tab-ic`, 16px box / 14px svg), tone set per tab via a
    `tone-*` class (`accent`/`blue`/`amber`/`green`/`violet`) driven by a `$tabMeta` map in the blade;
  - the **count** (`.count`) rendered like `.kpi-val` — 18px / 700, `var(--ink)` (accent when active),
    **not** the small pill from base `shf.css`; the number sits **before** the label;
  - the **label** uppercase with `letter-spacing:0.05em`, 13px / 600 (like `.kpi-lbl`).
- A 1px × 22px `var(--line)` divider (`.tab + .tab::before`) sits between adjacent tabs — the
  `.kpi-sep` equivalent; container `gap:16px` leaves room for it.
- Stage Breakdown has **no count** (still shows only icon + label) and keeps the active orange
  underline; the active tab also colours its number/label with the accent.
- **Responsive**: the bar shows **all** tabs at every width — no horizontal swipe. On desktop it's a
  single row (with dividers); at `≤899px` it `flex-wrap: wrap`s onto multiple rows and turns
  `overflow-x: visible` (the dividers are hidden on wrap, since a divider at a row-start floats); at
  `≤480px` the label/count type is trimmed one step so two chips fit per row on phones.

Editing `dashboard.css`/`dashboard.js`/the blade is a public-asset change → bump `SHF_VERSION`
(`.env`) + `SHF_SW_VERSION` (`public/sw.js`) + `php artisan config:clear` (per the asset-versioning
rule).

### Default tab priority (in order)

1. Overdue personal tasks (due date past, not completed)
2. Loan tasks currently assigned to user (pending stages)
3. Pending personal tasks (not overdue)
4. Active loans the user owns or is working on
5. Unconverted quotations
6. Personal tasks (fallback)

### Tab list

- **Stage Breakdown** — the stage-wise status funnel (see "Stage status breakdown block" below); first tab, default-selected, no count badge
- **Personal Tasks** — general tasks created by / assigned to the user (see `general-tasks.md`)
- **Loan Tasks** — current stage assignments for loans the user is working on. A loan in
  `parallel_processing` collapses into a **single entry** (not one row per sub-stage): the entry
  (`type: 'parallel'`) carries a `subStages[]` array of every active parallel sub-stage + its owner,
  rendered as stacked badge+owner lines in one row. Non-parallel assignments are `type: 'single'`.
  Built in `DashboardController::newthemeMyLoanTasks()` (groups parallel assignments by loan); rendered
  by `public/newtheme/pages/dashboard.js` (`myTaskStageCell`).
- **Active Loans** — loans the user created / advises / is currently assigned to a stage of. Like
  My Loan Tasks, a loan in `parallel_processing` renders as one row combining its active sub-stages +
  owners (`type: 'parallel'` + `subStages[]`, built via `DashboardController::parallelSubStages()`);
  the Stage cell reuses the shared `myTaskStageCell()` JS helper (the separate Owner column was folded
  in). Both tabs also show the loan's **application number** and **loan account number** as their own
  columns (**App #** = `applicationNumber`; **Loan Acct #** = `loanAccountNumbers`, distinct
  active-tranche numbers, `—` pre-disbursement), each with a copy button (as does the loan number).
- **Quotations** — user's recent quotations, with conversion status
- **DVR** — user's recent visits + pending follow-ups
- **Activity Log** — (admin / `view_activity_log` only) recent audit events

## AJAX endpoints

DataTable rows come from:

- `GET /dashboard/quotation-data`
- `GET /dashboard/task-data`
- `GET /dashboard/loan-data`
- `GET /dashboard/dvr-data`

All are session-auth, apply user-scoped filters (visibility rules from the relevant model scopes), return standard DataTables JSON.

## Create actions

Primary create CTAs (New Quotation / New Task / New Visit) are **no longer in the dashboard header or tab toolbars**. They live in the mobile FAB (`newtheme/partials/fab.blade.php`, visible < xl) and on their respective listing page headers (`/quotations`, `/general-tasks`, `/dvr`).

The inline modals `#dashCreateTaskModal` and `#dashCreateDvrModal` still exist in the dashboard view for the empty-state CTA inside the Personal Tasks tab. They are not triggered from the main header any more. Modal markup is **not** shared across pages — each host view (dashboard, general-tasks index, dvr index) carries its own instance, kept simple because controllers inject page-specific variables.

The "View All" pill stays on each dashboard tab and links to the matching full listing page.

## DVR create modal

Pre-fills:
- Visit date = today
- User = current user
- Branch = user's default branch

Uses the same validation logic as `DailyVisitReportController@store`. On success, the DVR page's data reloads.

## Task create modal

Pre-fills due date (today + 7 days), normal priority. Optional loan link via autocomplete (`/general-tasks/search-loans`).

## Activity Log page

Separate from the dashboard: `GET /activity-log` (permission: `view_activity_log`). DataTable with filters on user, action, subject type, date range. Data endpoint: `GET /activity-log/data`.

## Responsive patterns

- Stat cards: 4-up on desktop, 2-up on tablet, 1-up on mobile
- **Tab bar wraps to show every tab on smaller screens** — it does **not** horizontally scroll/swipe.
  Desktop = one row with dividers; `≤899px` the row `flex-wrap: wrap`s onto multiple rows
  (`overflow-x: visible`, dividers hidden, `row-gap` 8px); `≤480px` trims the type slightly so two
  chips fit per row. Verified at 1440 (1 row), 768 (2 rows), 414/360 (3 rows). See "Tab bar visual
  style" above.
- DataTables use the **mobile card pattern** (`.shf-table-mobile`) on narrow screens — `thead` hides, `tbody` rows become flex-card blocks with `data-label` pseudo-elements

## Open Queries widget

`DashboardController::newthemeOpenQueries(User $user)` feeds the right-rail "Open Queries" list (rendered into `#openQueriesList`). It is **user-scoped**, not global:

- `view_all_loans` (admin / super_admin) → see every active query (oversight).
- Everyone else → only active (`pending`/`responded`) queries that are **assigned to them** (`assigned_to_user_id`) **OR** on a loan visible to them (`whereHas('loan', visibleTo)` — owner/advisor, stage assignee, branch, or transfer history). Latest 6.

Covered by `tests/Feature/DashboardOpenQueriesTest.php`.

## Stage status breakdown block

The **first dashboard tab** (before Personal Tasks) and the **default selected tab**
(`newthemeDefaultTab` returns `stage-breakdown` when visible; tab-persist can still
restore a returning user's last choice). It gives a stage-wise **status funnel**: per
stage section (Sanction / Technical / Legal / Disbursement) a row of status-bucket
tiles, each showing a **count + ₹ amount** (rendered compact — L / Cr). Fed lazily by
`GET /dashboard/stage-breakdown` (kept off the initial page load); `newthemePayload`
only ships `stageBreakdownMeta` (allowed scopes, user options, period list). The tab
carries no count badge.

- **Collapsible**: the whole block is collapsible via the shared card pattern —
  `data-collapsible` on `#dash-panel-stage-breakdown` + a `.card-caret` in the header;
  clicking the header toggles `.card-collapsed` (`.card-bd` hidden, caret rotates). Because
  this header uniquely carries **filter controls** (scope/branch/bank/product/period selects
  + Apply), the delegated toggle handler in `dashboard.js` ignores clicks on
  `a, button, select, input, textarea, label, option` so the filters keep working without
  collapsing the card. This is independent of the per-**section** collapse inside the block
  (`[data-sb-toggle]` → `.sb-collapsed`).
- **Service**: `LoanPipelineBreakdownService` (see `services-reference.md`) does a
  single-fetch-per-scope PHP classification — each cohort loan lands in **exactly one
  bucket per section** by precedence, so buckets never overlap within a section.
  Cross-section overlap is intended (a loan is "Technical: completed" *and* "Legal:
  under process"). **Reached-stage gate**: `initializeStages()` pre-creates a `pending`
  row for every stage, so the classifier counts a loan in a section only once it has
  actually reached it — Disbursement "Spill" = `docket` `in_progress` (not the `pending`
  placeholder), and Technical/Legal "Not Initiated" / Sanction "SIP"-pending only count
  while `current_stage = parallel_processing`. A `parallel_processing` loan thus shows in
  Sanction/Technical/Legal (concurrent sub-stages) but **not** in Disbursement.
  Amounts: `loan_amount` everywhere except Disbursement
  (Spill/Logged-in = `sanctioned_amount`; Cheque/Transfer + OTC = summed active
  `disbursement_entries`). Spill/Logged-in fall back to `loan_amount` when a loan has
  no `sanctioned_amount` yet (so the tile never shows ₹0 for a real docket-phase loan).
  OTC Clearance also absorbs **every completed loan** (`status = completed`, or
  `otc_clearance` completed/skipped).
- **Scope blocks by role**: `view_all_loans` → one **All** block; branch_manager/bdh →
  **My data** + **My Branch**; everyone else → **My data**. A user dropdown (all users
  for `view_all_loans`, branch users for BM/BDH) narrows to one user's own data. Scope
  and selected-user are re-authorised server-side — a forged `scope`/`user_id` is
  silently downgraded, never leaked.
- **Filters**: date window — **Current Month** (default) / Last Month / Current Quarter /
  Current Half Year / All time / **Custom** (start + end date via the shared datepicker,
  applied by an **Apply** button). Calendar periods (not rolling days), matching the
  Management report. The active range is shown in the card header.
- The Disbursement section carries a derived **Total Disbursed** tile = Cheque/Transfer
  Entry + OTC Clearance (disjoint, so no double count); it's excluded from the section
  subtotal and reconciles with the Management report's "Disbursed."
- **Bank / Product / Branch filters** (AND-combined with scope/user/date): Bank →
  Product cascade (Product disabled until a Bank is picked, then limited to that bank's
  products); Branch options are scoped (all for `view_all_loans`, the user's own branches
  otherwise). These reuse the loans-list's native `bank_id`/`product_id`/`branch_id`
  params, so the tile click-through carries them and the list shows them selected. The window is applied **per bucket by that
  bucket's own stage-event date** (not one created-at cohort): sanction/technical/legal
  `completed_at` or `started_at`, tranche `disbursement_date` (Entry + OTC), query raised
  date, hold/withdrawn `status_changed_at` — with a **loan `created_at` fallback** for
  pending placeholders that have no event yet. This mirrors the Management funnel, so the
  Disbursement Entry + OTC amount **reconciles exactly with the report's "Disbursed"**
  (Σ tranches in window).
- **Click-through**: every tile links to `/loans` with `brk_section` + `brk_bucket`
  (+ `brk_scope`/`brk_user`/`brk_period`, or `brk_from`/`brk_to` for custom). The loans
  list re-runs the *same* classifier (`loanIdsFor`) to `whereIn` the exact IDs, so the
  list count matches the tile. The loans page opens its filter panel, sets Status →
  "All" (so nothing narrows the exact set), and shows a labelled "Filtered from
  dashboard — Section · Bucket · date-range · scope … Clear" banner. Adding any list
  filter narrows *within* the bucket; Clear drops the deep-link.
- Covered by `tests/Feature/StageBreakdownTest.php`.

## Implementation notes

- **Permissions decide visibility** — don't hide data behind `hasRole()` checks; use `hasPermission('slug')`
- **Data-driven defaults** — read actual counts before deciding which tab is active; do not assume the first tab is always correct
- DataTable initialization follows the project's standard `{ dom: 'rt<"shf-dt-bottom"ip>' }` layout — no separate search box; inline filters above the table

## See also

- `general-tasks.md` — personal/delegated tasks
- `dvr.md` — daily visit reports
- `loans.md` — loan visibility rules
- `quotations.md` — quotation creation path
- `frontend.md` — stat card / DataTable styling
