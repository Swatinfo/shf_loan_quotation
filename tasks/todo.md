# Task Tracker

Current and completed tasks. Updated as work progresses.

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
