# Task Tracker

Current and completed tasks. Updated as work progresses.

---

## In Progress

### Loan Task System Integration (from shf_task)

Spec docs: `.docs/new_features/` | Plan: `.claude/plans/calm-swinging-shamir.md`

```
Phase 0: IMP (independent, highest priority)
Phase 1: A   (foundation, no deps)
Phase 2: B   (depends on A)
Phase 3: C   (depends on B)
Phase 4: D   (depends on C)
Phase 5: E   (depends on D)
Phase 6: F + H (both depend on E, can run in parallel)
Phase 7: G + I (G depends on F, I depends on E, can run in parallel)
Phase 8: J   (depends on all)
```

---

#### Phase 0: Impersonation [HIGHEST PRIORITY — independent, no deps]

Spec: `stage-j-polish.md` §7

- [x] `composer require lab404/laravel-impersonate` (v1.7.8)
- [x] Add `ALLOW_IMPERSONATE_ALL=1` to `.env` + `ALLOW_IMPERSONATE_ALL=0` to `.env.example`
- [x] Add `'allow_impersonate_all' => env('ALLOW_IMPERSONATE_ALL', false)` to `config/app.php`
- [x] User model: add `Impersonate` trait, `canImpersonate()` (env flag), `canBeImpersonated()` (never super_admin)
- [x] Controller: `ImpersonateController` (search endpoint, canImpersonate check)
- [x] Navigation: `@canImpersonate` button + `@impersonating` amber banner + search dropdown + leave (desktop + mobile)
- [x] JS: debounced search (300ms), confirmation dialog, auto-focus on dropdown open
- [x] Event listeners: ActivityLog for impersonate_start/impersonate_end (AppServiceProvider)
- [x] Config: published + redirects to /dashboard
- [x] `Route::impersonate()` registered in web.php (take/leave routes)
- [ ] Verify: test as super_admin, test with ALLOW_IMPERSONATE_ALL=1

---

#### Phase 1: Stage A — Foundation [depends on: nothing]

Spec: `stage-a-foundation.md` | Roles: `role-integration.md`

- [x] Migration: `create_banks_table`
- [x] Migration: `create_branches_table`
- [x] Migration: `create_products_table`
- [x] Migration: `create_stages_table`
- [x] Migration: `create_user_branches_table`
- [x] Migration: `add_task_fields_to_users_table` (task_role, employee_id, default_branch_id, task_bank_id)
- [x] Model: `Bank`
- [x] Model: `Branch`
- [x] Model: `Product`
- [x] Model: `Stage`
- [x] Modify: `User` model (task_role, task_bank_id, branches, taskBank, constants, helpers, accessors)
- [x] Seeder: `StageSeeder` (14 base + 11 optional = 25 stages)
- [x] Seeder: `BankSeeder` (4 banks, 24 products, 1 branch)
- [x] Service: `LoanStageService` (getOrderedStages, getStageByKey, getSubStages, isParallelStage, getMainStageKeys)
- [x] Run migrations + seeders, verify
- [x] Update `.claude/database-schema.md`

---

#### Phase 2: Stage B — Quotation-to-Loan Bridge [depends on: A]

Spec: `stage-b-quotation-to-loan.md`

- [x] Migration: `create_loan_details_table` (incl. due_date, rejection fields, application_number, assigned_bank_employee)
- [x] Migration: `add_loan_id_to_quotations_table`
- [x] Model: `LoanDetail` (generateLoanNumber, scopeVisibleTo, status/customer_type constants, formatIndianNumber)
- [x] Modify: `Quotation` model (loan_id, loan(), is_converted)
- [x] Service: `LoanConversionService` (convertFromQuotation, createDirectLoan)
- [x] Controller: `LoanConversionController` (showConvertForm, convert)
- [x] View: `quotations/convert.blade.php` (bank radio cards, branch/product/advisor dropdowns)
- [x] Modify: `quotations/show.blade.php` (Convert to Loan / View Loan button)
- [x] Routes: conversion routes + temporary loans.show
- [x] Permission: `convert_to_loan` (admin + workflow roles)
- [x] Temporary: `loans/show-temp.blade.php` (placeholder until Phase 3)
- [x] Update reference docs

---

#### Phase 3: Stage C — Loan CRUD [depends on: B]

Spec: `stage-c-loan-management.md`

- [x] Controller: `LoanController` (index, loanData, create, store, show, edit, update, updateStatus, destroy)
- [x] View: `loans/index.blade.php` (DataTables + stats + filters + mobile cards)
- [x] View: `loans/create.blade.php` (bank→product dependent dropdown)
- [x] View: `loans/show.blade.php` (hub: info, stage, quotation link, notes, rejection, SweetAlert actions)
- [x] View: `loans/edit.blade.php` (pre-filled + dependent dropdown)
- [x] Routes: full loan CRUD routes (replaced temp route)
- [x] Navigation: "Loans" nav item (desktop + mobile, between Quotation and Users)
- [x] Permissions: view_loans, view_all_loans, create_loan, edit_loan, delete_loan (seeded)
- [x] Update reference docs

---

#### Phase 4: Stage D — Document Collection [depends on: C]

Spec: `stage-d-document-collection.md`

- [x] Migration: `create_loan_documents_table` (status: pending/received/rejected/waived + rejected_reason)
- [x] Model: `LoanDocument` (4 statuses, scopes: resolved/unresolved, helpers)
- [x] Service: `LoanDocumentService` (populateFromQuotation, populateFromDefaults, updateStatus, getProgress, allRequiredResolved, addDocument, removeDocument)
- [x] Controller: `LoanDocumentController` (index, updateStatus, store, destroy)
- [x] View: `loans/documents.blade.php` (progress bar, bilingual list, status dropdowns, SweetAlert reject reason, add form)
- [x] Integrate with LoanConversionService (populate docs on convert + create)
- [x] LoanDetail model: added documents() relationship
- [x] Loan show view: documents progress section with link
- [x] Routes: document routes
- [x] Permission: `manage_loan_documents` (admin + workflow roles)
- [x] Update reference docs

---

#### Phase 5: Stage E — Workflow Engine [depends on: D] (largest phase)

Spec: `stage-e-stage-workflow.md` + `stage-transfer-assignment.md`

- [x] Migration: `create_stage_assignments_table`
- [x] Migration: `create_loan_progress_table`
- [x] Migration: `create_stage_transfers_table` (transfer audit log)
- [x] Migration: `create_stage_queries_table` (two-way query system)
- [x] Migration: `create_query_responses_table` (query conversation thread)
- [x] Model: `StageAssignment` (status transitions, transfers, queries relationships, hasPendingQueries)
- [x] Model: `LoanProgress`
- [x] Model: `StageTransfer` (fromUser, toUser, reason, transfer_type)
- [x] Model: `StageQuery` (pending→responded→resolved, blocks completion)
- [x] Model: `QueryResponse` (conversation thread per query)
- [x] Service: `LoanStageService` — core (initializeStages, updateStageStatus, assignStage, skipStage, recalculateProgress, canStartStage, getNextStage)
- [x] Service: `LoanStageService` — auto-assignment (autoAssignStage, findBestAssignee, STAGE_ROLE_ELIGIBILITY)
- [x] Service: `LoanStageService` — transfer (transferStage with validation + history + notifications)
- [x] Service: `LoanStageService` — rejection (rejectLoan: rejects entire loan, terminal state)
- [x] Service: `StageQueryService` (raiseQuery, respondToQuery, resolveQuery, hasPendingQueries)
- [x] Controller: `LoanStageController` (index, updateStatus, assign, skip, transfer, transferHistory, reject, raiseQuery, respondToQuery, resolveQuery)
- [x] View: `loans/stages.blade.php` (with transfer modal + query banners)
- [x] Partial: `loans/partials/progress-bar.blade.php`
- [x] Partial: `loans/partials/stage-card.blade.php` (transfer button, transfer history, query UI)
- [x] View: `loans/transfers.blade.php` (full transfer timeline)
- [x] Integrate with LoanConversionService (initializeStages + autoComplete 1-2 + autoAssign stage 3)
- [x] Routes: stage + transfer + query routes
- [x] Permissions: manage_loan_stages, skip_loan_stages
- [x] Update reference docs

---

#### Phase 6a: Stage F — Parallel Processing [depends on: E]

Spec: `stage-f-parallel-processing.md`

- [x] Migration: `create_valuation_details_table`
- [x] Model: `ValuationDetail`
- [x] Service: `LoanStageService` — parallel (checkParallelCompletion, getParallelSubStages)
- [x] Service: `LoanStageService` — `autoAssignParallelSubStages()` (auto-assign all sub-stages on parallel start)
- [x] Controller: `LoanValuationController`
- [x] View: `loans/valuation.blade.php`
- [x] Update stages view — 2x2 parallel grid for stage 4
- [x] Routes: valuation routes
- [x] Update reference docs

#### Phase 6b: Stage H — Dashboard, Notifications, Remarks [depends on: E] (parallel with F)

Spec: `stage-h-dashboard-notifications.md`

- [x] Migration: `create_remarks_table`
- [x] Migration: `create_notifications_table`
- [x] Model: `Remark`
- [x] Model: `Notification` (types: info, success, warning, error, stage_update, assignment)
- [x] Service: `NotificationService` (notify, notifyStageAssignment, notifyStageCompleted, notifyLoanCompleted, markRead, markAllRead, getUnreadCount)
- [x] Service: `RemarkService` (addRemark, getRemarks)
- [x] Controller: `LoanRemarkController`
- [x] Controller: `NotificationController`
- [x] View: `loans/partials/remarks.blade.php`
- [x] View: `notifications/index.blade.php`
- [x] Navigation: notification bell with badge + 60s polling
- [x] Dashboard: loan stats cards + "My Pending Stages" list
- [x] Integrate notifications into LoanStageService (assign, complete, transfer, reject)
- [x] Routes: remarks + notification routes
- [x] Permission: `add_remarks`
- [x] Update reference docs

---

#### Phase 7a: Stage G — Advanced Stages 5-10 + Disbursement [depends on: F]

Spec: `stage-g-advanced-stages.md`

- [x] Migration: `create_disbursement_details_table` (fund_transfer/cheque/demand_draft + OTC)
- [x] Model: `DisbursementDetail` (3 disbursement types, OTC handling)
- [x] Service: `DisbursementService` (processDisbursement, clearOtc)
- [x] Controller: `LoanDisbursementController`
- [x] StageAssignment: notes JSON helpers (getNotesData, mergeNotesData)
- [x] LoanStageController: `saveNotes` action
- [x] View: `loans/disbursement.blade.php` (decision tree UI)
- [x] Partial: `stage-cibil-check.blade.php` (score 300-900)
- [x] Partial: `stage-rate-pf.blade.php` (interest rate, processing fee, admin charges)
- [x] Partial: `stage-sanction.blade.php` (reference, date)
- [x] Partial: `stage-docket.blade.php` (docket number, login date)
- [x] Partial: `stage-kfs.blade.php` (reference)
- [x] Partial: `stage-esign.blade.php` (status enforcement: blocks if not 'completed')
- [x] Routes: disbursement + notes routes
- [x] Update reference docs

#### Phase 7b: Stage I — Workflow Configuration [depends on: E] (parallel with G)

Spec: `stage-i-workflow-config.md`

- [x] Migration: `create_product_stages_table`
- [x] Model: `ProductStage`
- [x] Controller: `WorkflowConfigController` (banks, products, branches, product stage config)
- [x] View: `settings/workflow.blade.php` (tabbed: Banks, Products, Branches)
- [x] View: `settings/workflow-product-stages.blade.php` (toggle/config per stage)
- [x] Loan type document templates (15 loan types in config)
- [x] Enhance LoanStageService.initializeStages() — product-aware (enabled/disabled/auto-skip/pre-assign)
- [x] Routes: workflow config routes
- [x] Permission: `manage_workflow_config`
- [x] Update reference docs

---

#### Phase 8: Stage J — Settings Restructure + Polish [depends on: ALL above]

Spec: `stage-j-polish.md` + plan file Phase 8 section

**8a. Settings Restructure**
- [x] Rename "Settings" → "Quotation Settings" in navigation (desktop + mobile)
- [x] Rename "Settings" → "Quotation Settings" in `settings/index.blade.php` page title
- [x] Create `LoanSettingsController` (index + reuse WorkflowConfigController actions)
- [x] Create `loan-settings/index.blade.php` with 5 tabs (Banks, Branches, Products, Stage Config, User Roles)
- [x] Banks tab: shared `banks` table CRUD (add/edit/delete + link to quotation charges)
- [x] Branches tab: branch CRUD (move from workflow.blade.php)
- [x] Products tab: per-bank product CRUD with "Configure Stages" links
- [x] Stage Config tab: product stage toggle overview with links to per-product config
- [x] User Roles tab: manage task_role, task_bank_id, employee_id, branch assignments per user
- [x] Add "Loan Settings" nav item (between Users and Permissions, permission: view_loans)
- [x] Routes: `/loan-settings` + sub-routes (move workflow routes under loan-settings)
- [x] Remove standalone `/settings/workflow` route (redirect or remove)
- [x] Update `workflow-product-stages.blade.php` breadcrumb to Loan Settings
- [x] "Sync from Banks Table" button in Quotation Settings → Banks tab

**8b. Polish**
- [x] Complete permission audit (all 11 new permissions seeded + role defaults verified)
- [x] CSS additions to `public/css/shf.css` (~80 lines stage/workflow styles)
- [x] JS: `public/js/shf-loans.js` (consolidated loan interactions)
- [x] Tests: LoanConversionTest (9), LoanStageWorkflowTest (22), LoanDocumentTest (11), LoanPermissionTest (19), DisbursementTest (12)
- [x] Full end-to-end test: LoanEndToEndTest (4) — quotation → convert → all stages → disbursement → completed + rejection flow + OTC flow
- [x] Update all reference docs (database-schema, routes, services, models, permissions, views, frontend)

---

#### Phase 9: Soft Delete + Dependency Protection + Theme Fix

**9a. Soft Delete**
- [x] Migration: `add_soft_deletes_to_tables` (deleted_at on: loan_details, banks, branches, products, quotations)
- [x] Model: Add `SoftDeletes` trait to LoanDetail, Bank, Branch, Product, Quotation

**9b. Dependency Protection**
- [x] Bank: block delete if has products or active loans
- [x] Branch: block delete if has assigned users or active loans
- [x] Product: block delete if has active loans
- [x] Quotation: block delete if converted to loan (loan_id not null)
- [x] LoanDetail: soft delete always allowed (recoverable)

**9c. Theme Consistency (replace Bootstrap defaults with shf-* classes in 13 loan views)**
- [x] Bulk replace: `form-label` → `shf-form-label` in all loan views
- [x] Bulk replace: `form-control` → `shf-input` in all loan views
- [x] Bulk replace: `form-select` → `shf-input` in all loan views
- [x] Bulk replace: `btn btn-sm btn-outline-primary` → `btn-accent-sm` for action buttons
- [x] Update reference docs

---

#### Phase 10: Task Ownership + Timeline + Audit Columns

**10a. Audit Columns (updated_by, deleted_by)**
- [x] Migration: `add_audit_columns_to_tables` — add updated_by (9 tables) + deleted_by (5 soft-delete tables)
- [x] Create: `App\Traits\HasAuditColumns` trait (auto-fill updated_by on save, deleted_by on soft delete)
- [x] Add trait to models: LoanDetail, Bank, Branch, Product, Quotation, StageAssignment, LoanDocument, ValuationDetail, DisbursementDetail, ProductStage
- [x] Add updated_by/deleted_by to fillable on affected models

**10b. Task Ownership Display**
- [x] LoanDetail: `getCurrentOwnerAttribute()` — user assigned to current stage
- [x] LoanDetail: `getTimeWithCurrentOwnerAttribute()` — "2d 5h 30m" format
- [x] Loan show view: display current owner + time with them
- [x] Loan index DataTable: add "Owner" column
- [x] Stage cards: show time since assignment per stage

**10c. Complete Lifecycle Timeline**
- [x] Service: `LoanTimelineService` — merge stages, transfers, queries, remarks into timeline
- [x] Controller: `LoanController@timeline` action
- [x] Route: `GET /loans/{loan}/timeline`
- [x] View: `loans/timeline.blade.php` — vertical timeline with color-coded entries
- [x] Loan show: "View Timeline" button
- [x] Update reference docs

---

## Recently Completed

- [x] Create comprehensive `.docs/` documentation folder
    - [x] `.docs/README.md` — Index of all documentation
    - [x] `.docs/overview.md` — Project overview, tech stack, architecture
    - [x] `.docs/authentication.md` — Auth system, login/logout, password reset
    - [x] `.docs/permissions.md` — Permission system, roles, middleware, caching
    - [x] `.docs/users.md` — User CRUD, role hierarchy, activation
    - [x] `.docs/quotations.md` — Quotation creation, validation, EMI, DB persistence
    - [x] `.docs/pdf-generation.md` — PDF rendering pipeline, Chrome/microservice
    - [x] `.docs/dashboard.md` — Dashboard stats, DataTables AJAX, activity log
    - [x] `.docs/settings.md` — All 8 settings sections, config service, defaults
    - [x] `.docs/api.md` — Public config API, notes API, sync API
    - [x] `.docs/models.md` — All 11 Eloquent models, relationships, casts, methods
    - [x] `.docs/database.md` — Full schema, migrations, indexes, constraints
    - [x] `.docs/frontend.md` — Design system, CSS classes, JS modules, Bootstrap
    - [x] `.docs/offline-pwa.md` — PWA, service worker, IndexedDB, sync queue
    - [x] `.docs/views.md` — All Blade views, layouts, sections, partials
    - [x] Updated `CLAUDE.md` to reference `.docs/` with lookup table

---

## Completed

- [x] Add "Salaried" Customer Type
    - [x] Add salaried docs to `config/app-defaults.php` (EN + GU)
    - [x] Add salaried docs to `public/js/config-defaults.js` (EN + GU)
    - [x] Update `Quotation` model `getTypeLabel()`
    - [x] Update `DashboardController` type labels + badge class
    - [x] Update create quotation view (dropdown + JS doc logic)
    - [x] Update show quotation view (badge class)
    - [x] Update dashboard view (filter dropdown)
    - [x] Update settings view (tabs + JS loops)
    - [x] Update `public/js/pdf-renderer.js` type labels
    - [x] Update `public/js/config-translations.js` type labels + types array
    - [x] Add `.shf-badge-purple` CSS class
    - [x] Update `.claude/database-schema.md`
    - [x] Tests pass (15/15 project tests), Pint clean

- [x] Fix `array_replace_recursive` ghost documents bug
- [x] Fix DataTables offline — show cached data instead of blocking "offline" screen
- [x] Phase 3: Remove x-app-layout / x-guest-layout — switch to @extends/@section
- [x] Fix mobile responsiveness across all pages
- [x] Remove auto-download PDFs on offline sync
- [x] Bootstrap Tables + Datepicker Migration
- [x] Dashboard DataTables AJAX Implementation
