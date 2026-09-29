# TODO — Dashboard "Stage status breakdown" block

## STATUS: DONE — implemented + tested (StageBreakdownTest 9/9; loan-list regression 12/12 green).

## Goal
New card below "Pipeline by stage" (#7): stage-wise status funnel. Per stage section
(Sanction / Technical / Legal / Disbursement) status-bucket tiles with Count + ₹ Amount
(compact L/Cr), each tile deep-linking to a pre-filtered loan list. Scope + date + user filters.

## Decisions (locked by user)
- Scope blocks: view_all_loans → All; BM/BDH → My data + My Branch; others → My data.
- User dropdown: view_all_loans (all users) + BM/BDH (branch users) → pick a user = that user's own data.
- Date: loans created within window. 30 (default)/60/90/180/All time/**Custom (start+end)**. Show the range.
- Amounts: loan_amount, except Disbursement Spill/Logged-in = sanctioned_amount; Cheque/Transfer + OTC = summed active disbursement_entries.
- Loan-level Withdrawn/Rejected/Hold stay in operational sections too.
- **OTC Clearance = every completed loan** (status completed, or otc_clearance completed/skipped).
- Sections individually **collapsible**.

## Steps — Phase A (board)
- [x] `LoanPipelineBreakdownService` — allowedScopes/userOptions/build/loanIdsFor, precedence classifier, custom-range window, 60s cache, server-side scope+user auth.
- [x] `DashboardController@stageBreakdown` (JSON) + route `GET /dashboard/stage-breakdown`.
- [x] `stageBreakdownMeta` in `newthemePayload` (scopes, user options, periods incl. Custom).
- [x] Dashboard block markup + collapsible sections + custom date inputs (`dashboard.blade.php`).
- [x] Render + filter wiring (`dashboard.js`): AJAX, compact L/Cr amounts, collapse toggle, custom range.
- [x] CSS (`dashboard.css`): tiles, collapsible section headers/caret, date inputs.
- [x] Bump `SHF_VERSION` + `SHF_SW_VERSION` → 20260929120000, `config:clear`.
- [x] Tests: classifier exclusivity, query rule, per-bucket amounts, OTC=completed, custom range, date cohort, scope/user auth.

## Steps — Phase B (click-through)
- [x] `loanIdsFor(...)` on the service (exact bucket IDs, incl. custom from/to).
- [x] `LoanController@loanData` honours `brk_section`/`brk_bucket` (+scope/user/period/from/to) via service IDs; skips Active-status default when active.
- [x] `loans.js` reads `brk_*` from URL, forwards them, forces status=all, shows Clear banner (`loans.css`).
- [x] Test: list count == tile count (`test_loan_list_deeplink_filters_to_the_exact_bucket`).

## Docs
- [x] `.docs/dashboard.md`, `.claude/routes-reference.md`, `.claude/services-reference.md`, `tasks/lessons.md`.

## Follow-ups (not requested / deferred)
- CSV export of the breakdown; trend vs previous period.
- Remember last-used filter in localStorage.

## Test runner (this machine)
`php -c .scratch/php-test.ini vendor/phpunit/phpunit/phpunit --filter=StageBreakdownTest`
