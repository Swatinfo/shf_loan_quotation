# Plan: Complete Documentation Regeneration from Codebase Scan

## Context
Regenerate ALL documentation purely from code scanning. Existing docs are stale from recent code changes. CLAUDE.md must be under 200 lines and map each task type to only the relevant docs. All content must be clear and precise.

---

## Phase 1: Backup Everything

Copy to `.backups/2026-04-15/` preserving full hierarchy:

```
.backups/2026-04-15/
├── CLAUDE.md
├── Loan Lifecycle - Roles & Actions.txt
├── .docs/
│   ├── README.md              ├── api.md
│   ├── authentication.md      ├── dashboard.md
│   ├── database.md            ├── frontend.md
│   ├── general-tasks.md       ├── models.md
│   ├── offline-pwa.md         ├── pdf-generation.md
│   ├── permissions.md         ├── quotations.md
│   ├── settings.md            ├── user-assignment.md
│   ├── users.md               ├── views.md
│   ├── workflow-developer.md  └── workflow-guide.md
├── .claude/
│   ├── database-schema.md
│   ├── routes-reference.md
│   ├── services-reference.md
│   ├── file-suggestions.sh
│   ├── launch.json
│   └── rules/
│       ├── coding-feedback.md
│       ├── laravel-boost.md
│       ├── pre-read-gate.md
│       ├── project-context.md
│       └── workflow.md
└── tasks/
    ├── lessons.md
    └── todo.md
```

**Excluded from backup:** `.claude/plans/`, `.claude/settings.json`, `.claude/settings.local.json`

---

## Phase 2: Rename Lifecycle File

`Loan Lifecycle - Roles & Actions.txt` → `Loan_Lifecycle_Roles_Actions.md`

---

## Phase 3: Regenerate `.claude/` Reference Files

### 3a. `.claude/database-schema.md` — from 71 migrations + 33 models
Source: All migration files in `database/migrations/`, all model files in `app/Models/`

Tables to document (grouped):
- **Auth:** users, roles, permissions, role_user, role_permission, user_permissions
- **Organization:** banks, branches, locations, products, bank_location, location_user, location_product, user_branches, bank_employees, customers
- **Quotation:** quotations, quotation_banks, quotation_emi, quotation_documents, bank_charges
- **Loan:** loan_details, loan_documents, loan_progress, valuation_details, disbursement_details, remarks
- **Workflow:** stages, stage_assignments, stage_transfers, stage_queries, query_responses, product_stages, product_stage_users, task_role_permissions
- **Tasks:** general_tasks, general_task_comments
- **DVR:** daily_visit_reports
- **System:** activity_logs, shf_notifications, app_config, cache, sessions, jobs

For each table: columns, types, nullable, defaults, FKs, unique constraints, indexes

### 3b. `.claude/routes-reference.md` — from routes/web.php (306 lines), api.php, auth.php
Source: Route files + bootstrap/app.php middleware registration

Document every route:
- Method (GET/POST/PUT/PATCH/DELETE)
- URI pattern
- Controller@method
- Middleware chain (auth, permission:slug)
- Route name
- Group prefix

Groups found in code: Dashboard, Profile, Users, Permissions, Settings, Loans, Loan Stages, Loan Settings, Workflow Config, Disbursement, Valuation, Remarks, Documents, Roles, Quotations, Conversion, General Tasks, DVR, Notifications, Activity Log, Impersonation, API (session), API (public)

### 3c. `.claude/services-reference.md` — from 13 services + 22 controllers
Source: All files in `app/Services/`, `app/Http/Controllers/` (skip `__` prefixed)

**13 Services to document:**
1. PermissionService — 3-tier permission resolution, 5min cache
2. ConfigService — app_config table + app-defaults.php fallback
3. NotificationService — in-app notifications lifecycle
4. QuotationService — quotation generation + PDF + validation
5. LoanDocumentService — document CRUD + file upload + progress tracking
6. LoanStageService — workflow engine, 14 base stages, phase transitions, progress calc
7. RemarkService — stage/general remarks
8. StageQueryService — raise/respond/resolve queries
9. DisbursementService — fund_transfer or cheque processing
10. LoanConversionService — quotation→loan + direct loan creation
11. LoanTimelineService — chronological event timeline
12. PdfGenerationService — HTML→PDF via Chrome/microservice/DomPDF
13. NumberToWordsService — Indian numbering (English + Gujarati)

**22 Controllers to document** (with inline validation rules from code):
DashboardController, UserController, LoanController, LoanStageController, QuotationController, LoanDocumentController, LoanConversionController, PermissionController, SettingsController, RoleManagementController, WorkflowConfigController, LoanSettingsController, GeneralTaskController, DailyVisitReportController, NotificationController, ProfileController, LoanRemarkController, LoanValuationController, LoanDisbursementController, ImpersonateController, Api/ConfigApiController, Api/NotesApiController, Api/SyncApiController

### 3d. `.claude/file-suggestions.sh` — from current file tree
Regenerate entry-point suggestions based on actual directory structure

---

## Phase 4: Regenerate `.claude/rules/` Files

### 4a. `rules/pre-read-gate.md`
Source: New doc filenames from Phase 5

Rebuild task→doc mapping table. Each task type maps to exactly 1-3 relevant docs (not all docs).

### 4b. `rules/coding-feedback.md`
Source: `shf.css` (130+ shf-* classes, CSS variables), `shf-app.js` (SHF.* functions), `shf-loans.js`, layout views, controller patterns

Document:
- Frontend stack (Bootstrap 5.3 + jQuery 3.7, local vendor, no build step)
- CSS prefix `shf-*`, custom properties, font scale
- Responsive patterns (navbar-expand-xl, col-6 col-md-auto, dual layout tables)
- DataTable conventions (shf-section shf-dt-section, dom config, drawCallback)
- Button classes (btn-accent, btn-accent-sm, btn-accent-outline)
- Form inputs (shf-input, shf-form-label, shf-validation-error)
- Date handling (Bootstrap Datepicker from vendor/datepicker/)
- Config/JSON cast rules
- View patterns (@extends/@section only)

### 4c. `rules/project-context.md`
Source: Models, config/permissions.php, config/app-defaults.php, LoanStageService, visibility scopes

Document:
- Domain: SHF bilingual loan management
- 33 models, 13 services, 22 controllers
- 7 roles: super_admin, admin, branch_manager, bdh, loan_advisor, bank_employee, office_employee
- 48+ permissions in 7 groups
- Customer types: proprietor, partnership_llp, pvt_ltd, salaried
- Loan workflow: 11 stages (17 with sub-stages), parallel processing, multi-phase
- Visibility scopes: LoanDetail::visibleTo, GeneralTask::visibleTo, DailyVisitReport::visibleTo
- Config system: ConfigService → app_config table → app-defaults.php fallback

### 4d. `rules/workflow.md`
Source: Current workflow rules (task management, planning, docs sync)

Keep existing task management rules, update doc sync checklist to match new file names.

### 4e. `rules/laravel-boost.md`
Source: composer.json (PHP 8.4, Laravel 12, Breeze 2, Pint 1, PHPUnit 11)

Keep project-specific overrides (inline validation, no build step, ConfigService). Update package versions from composer.json.

---

## Phase 5: Regenerate `.docs/` Files

**22 files total (20 existing + 2 new)**

| # | File | Generated From (code files) |
|---|------|---------------------------|
| 1 | `README.md` | Index of all doc files |
| 2 | `api.md` | routes/api.php, Api/*Controller.php, offline-manager.js |
| 3 | `authentication.md` | Auth controllers, EnsureUserIsActive, bootstrap/app.php, login.blade.php |
| 4 | `dashboard.md` | DashboardController (index, quotationData, taskData, loanData, dvrData), dashboard.blade.php |
| 5 | `database.md` | All 71 migrations — full schema reference with types and constraints |
| 6 | `frontend.md` | shf.css (130+ classes, CSS vars), shf-app.js, shf-loans.js, vendor/ libs, layout views |
| 7 | `general-tasks.md` | GeneralTask model, GeneralTaskComment, GeneralTaskController, general-tasks/*.blade.php |
| 8 | `models.md` | All 33 models — fillable, casts, relationships, scopes, constants, key methods |
| 9 | `offline-pwa.md` | offline-manager.js, sw.js, manifest.json, SyncApiController |
| 10 | `pdf-generation.md` | PdfGenerationService (3-tier: microservice→Chrome→fallback), template structure |
| 11 | `permissions.md` | config/permissions.php (48+ perms, 7 groups), PermissionService, CheckPermission middleware, User::hasPermission |
| 12 | `quotations.md` | QuotationService, QuotationController, Quotation/QuotationBank/QuotationEmi/QuotationDocument models |
| 13 | `settings.md` | ConfigService, SettingsController (11 update methods), config/app-defaults.php structure |
| 14 | `user-assignment.md` | ProductStage::getUserForLocation, ProductStageUser, LoanStageService auto-assignment |
| 15 | `users.md` | User model, UserController (CRUD + syncBranches + syncBanks + syncPermissions), ImpersonateController |
| 16 | `views.md` | All 47 blade files listed by directory, layout structure (app/guest/navigation) |
| 17 | `workflow-developer.md` | LoanStageService (initializeStages, updateStageStatus, phase methods), StageAssignment notes JSON |
| 18 | `workflow-guide.md` | User-facing 11-stage workflow from LoanStageController actions + Loan Lifecycle txt |
| 19 | `dvr.md` | **NEW** — DailyVisitReport model, DailyVisitReportController, dvr/*.blade.php, visibility scope |
| 20 | `loans.md` | **NEW** — LoanDetail model, LoanController (CRUD + status + loanData), create/edit/show/index views |
| 21 | `roles.md` | **NEW** — Role model, RoleManagementController, roles/*.blade.php, gujaratiLabels |
| 22 | `activity-log.md` | **NEW** — ActivityLog model, DashboardController (activityLog/activityLogData), activity-log.blade.php |

---

## Phase 6: Regenerate `CLAUDE.md` (under 200 lines)

Structure (~180 lines):

```
# CLAUDE.md

## Project Overview (5 lines)
## Tech Stack (table, 8 lines)
## Dev Commands (6 lines)
## Key Conventions (12 lines)
  - Bilingual, currency format, ActivityLog, ConfigService
  - CSS prefix shf-*, @extends/@section views, inline validation
## Roles (5 lines)
## Loan Workflow (10 lines)
## General Tasks & DVR (4 lines)

## Pre-Read Gate (table, ~35 lines)
  Maps each task type → exactly the 1-3 docs needed
  Example: "Quotation work" → quotations.md + pdf-generation.md
  Example: "Permission changes" → permissions.md
  Example: "Frontend/CSS" → frontend.md + views.md

## Reference Docs Index (table, 22 lines)
## Source of Truth Files (6 lines)
## Always Read (3 lines) — tasks/lessons.md + tasks/todo.md
```

Key principle: The pre-read gate table tells Claude which docs to read per task. It should NOT list all docs for every task — only the relevant ones.

---

## Phase 7: Update `SeedScreenshotLoans.php`

Source: Current stage keys from Stage model + LoanStageService phase handling

Current $loans array has 31 entries covering:
- Sequential: document_collection
- Parallel: app_number, bsm_osv, legal_phase1-3, parallel_valuation, sanction_decision (oe/bm/bdh/only)
- Post-parallel: rate_pf_phase1-3, sanction_phase1-3, docket_phase1-3, kfs, esign_phase1-4
- Terminal: disbursement, otc_clearance
- Closed: completed_fund, completed_cheque, rejected_at_sanction_decision, on_hold, cancelled

**Updates needed:**
- Verify stage keys match current `stages` table (check for property_valuation vs technical_valuation)
- Verify phase note keys match current LoanStageController action methods
- Verify role assignments match current stage default_role JSON values
- Update advance methods if any stage transition logic changed
- Ensure customer data variety (names, amounts, types) is realistic

**Will NOT run the command.**

---

## Phase 8: Rewrite `Loan_Lifecycle_Roles_Actions.md`

Source: LoanStageService methods, LoanStageController action handlers, Stage model sub_actions, SeedScreenshotLoans advance logic

Complete 11-stage lifecycle with:
- Stage key, name (EN/GU), sequence order
- Stage type (sequential/parallel/decision)
- Phases within stage (role handoffs, form fields, actions)
- Who can act at each phase
- What data is captured in notes JSON
- Transition rules (what triggers next stage)
- Cross-stage rules: queries, transfers, rejection, auto-assignment

---

## Complete File Change List

### Files BACKED UP (copied to .backups/2026-04-15/)
| Source | Backup Location |
|--------|----------------|
| `CLAUDE.md` | `.backups/2026-04-15/CLAUDE.md` |
| `Loan Lifecycle - Roles & Actions.txt` | `.backups/2026-04-15/Loan Lifecycle - Roles & Actions.txt` |
| `.docs/README.md` | `.backups/2026-04-15/.docs/README.md` |
| `.docs/api.md` | `.backups/2026-04-15/.docs/api.md` |
| `.docs/authentication.md` | `.backups/2026-04-15/.docs/authentication.md` |
| `.docs/dashboard.md` | `.backups/2026-04-15/.docs/dashboard.md` |
| `.docs/database.md` | `.backups/2026-04-15/.docs/database.md` |
| `.docs/frontend.md` | `.backups/2026-04-15/.docs/frontend.md` |
| `.docs/general-tasks.md` | `.backups/2026-04-15/.docs/general-tasks.md` |
| `.docs/models.md` | `.backups/2026-04-15/.docs/models.md` |
| `.docs/offline-pwa.md` | `.backups/2026-04-15/.docs/offline-pwa.md` |
| `.docs/pdf-generation.md` | `.backups/2026-04-15/.docs/pdf-generation.md` |
| `.docs/permissions.md` | `.backups/2026-04-15/.docs/permissions.md` |
| `.docs/quotations.md` | `.backups/2026-04-15/.docs/quotations.md` |
| `.docs/settings.md` | `.backups/2026-04-15/.docs/settings.md` |
| `.docs/user-assignment.md` | `.backups/2026-04-15/.docs/user-assignment.md` |
| `.docs/users.md` | `.backups/2026-04-15/.docs/users.md` |
| `.docs/views.md` | `.backups/2026-04-15/.docs/views.md` |
| `.docs/workflow-developer.md` | `.backups/2026-04-15/.docs/workflow-developer.md` |
| `.docs/workflow-guide.md` | `.backups/2026-04-15/.docs/workflow-guide.md` |
| `.claude/database-schema.md` | `.backups/2026-04-15/.claude/database-schema.md` |
| `.claude/routes-reference.md` | `.backups/2026-04-15/.claude/routes-reference.md` |
| `.claude/services-reference.md` | `.backups/2026-04-15/.claude/services-reference.md` |
| `.claude/file-suggestions.sh` | `.backups/2026-04-15/.claude/file-suggestions.sh` |
| `.claude/launch.json` | `.backups/2026-04-15/.claude/launch.json` |
| `.claude/rules/coding-feedback.md` | `.backups/2026-04-15/.claude/rules/coding-feedback.md` |
| `.claude/rules/laravel-boost.md` | `.backups/2026-04-15/.claude/rules/laravel-boost.md` |
| `.claude/rules/pre-read-gate.md` | `.backups/2026-04-15/.claude/rules/pre-read-gate.md` |
| `.claude/rules/project-context.md` | `.backups/2026-04-15/.claude/rules/project-context.md` |
| `.claude/rules/workflow.md` | `.backups/2026-04-15/.claude/rules/workflow.md` |
| `tasks/lessons.md` | `.backups/2026-04-15/tasks/lessons.md` |
| `tasks/todo.md` | `.backups/2026-04-15/tasks/todo.md` |

### Files REGENERATED (rewritten from code scan)
| # | File | Action |
|---|------|--------|
| 1 | `CLAUDE.md` | Rewrite (under 200 lines) |
| 2 | `.claude/database-schema.md` | Rewrite from migrations + models |
| 3 | `.claude/routes-reference.md` | Rewrite from route files |
| 4 | `.claude/services-reference.md` | Rewrite from services + controllers |
| 5 | `.claude/file-suggestions.sh` | Rewrite from file tree |
| 6 | `.claude/rules/coding-feedback.md` | Rewrite from CSS/JS/view patterns |
| 7 | `.claude/rules/laravel-boost.md` | Update package versions + overrides |
| 8 | `.claude/rules/pre-read-gate.md` | Rewrite with new doc mappings |
| 9 | `.claude/rules/project-context.md` | Rewrite from models/config/services |
| 10 | `.claude/rules/workflow.md` | Update doc sync checklist |
| 11 | `.docs/README.md` | Rewrite as doc index |
| 12 | `.docs/api.md` | Rewrite from API routes/controllers |
| 13 | `.docs/authentication.md` | Rewrite from auth code |
| 14 | `.docs/dashboard.md` | Rewrite from DashboardController + view |
| 15 | `.docs/database.md` | Rewrite from all migrations |
| 16 | `.docs/frontend.md` | Rewrite from CSS/JS/vendor files |
| 17 | `.docs/general-tasks.md` | Rewrite from task model/controller |
| 18 | `.docs/models.md` | Rewrite from all 33 models |
| 19 | `.docs/offline-pwa.md` | Rewrite from JS/SW/manifest |
| 20 | `.docs/pdf-generation.md` | Rewrite from PdfGenerationService |
| 21 | `.docs/permissions.md` | Rewrite from permissions config/service |
| 22 | `.docs/quotations.md` | Rewrite from quotation code |
| 23 | `.docs/settings.md` | Rewrite from ConfigService/SettingsController |
| 24 | `.docs/user-assignment.md` | Rewrite from auto-assignment code |
| 25 | `.docs/users.md` | Rewrite from User model/controller |
| 26 | `.docs/views.md` | Rewrite from blade file listing |
| 27 | `.docs/workflow-developer.md` | Rewrite from LoanStageService code |
| 28 | `.docs/workflow-guide.md` | Rewrite from stage workflow code |
| 29 | `.docs/dvr.md` | **NEW** — Daily Visit Reports |
| 30 | `.docs/loans.md` | **NEW** — Loan CRUD & management |
| 31 | `.docs/roles.md` | **NEW** — Role management |
| 32 | `.docs/activity-log.md` | **NEW** — Activity logging |

### Files RENAMED
| Old | New |
|-----|-----|
| `Loan Lifecycle - Roles & Actions.txt` | `Loan_Lifecycle_Roles_Actions.md` |

### Files UPDATED (code change)
| File | Change |
|------|--------|
| `app/Console/Commands/SeedScreenshotLoans.php` | Update $loans data + advance methods to match current workflow |
| `Loan_Lifecycle_Roles_Actions.md` | Complete rewrite with current 11-stage workflow |

### Files NOT TOUCHED
- `.claude/plans/` (all plan files)
- `.claude/settings.json`
- `.claude/settings.local.json`
- `.claude/launch.json` (backed up but not regenerated)
- `tasks/lessons.md` (backed up but not regenerated)
- `tasks/todo.md` (backed up but not regenerated)
- All source code files (except SeedScreenshotLoans.php)

---

## Execution Order

1. **Phase 1** — Backup all files to `.backups/2026-04-15/`
2. **Phase 2** — Rename lifecycle file
3. **Phase 3** — Regenerate `.claude/` reference files (database-schema, routes, services, file-suggestions)
4. **Phase 4** — Regenerate `.claude/rules/` (5 files)
5. **Phase 5** — Regenerate `.docs/` (22 files)
6. **Phase 6** — Regenerate `CLAUDE.md` (under 200 lines)
7. **Phase 7** — Update SeedScreenshotLoans.php data
8. **Phase 8** — Rewrite Loan_Lifecycle_Roles_Actions.md

---

## Verification Checklist

- [ ] All 32 source files backed up in `.backups/2026-04-15/`
- [ ] `CLAUDE.md` line count < 200
- [ ] Pre-read gate maps each task to 1-3 docs (not all)
- [ ] Stage keys in SeedScreenshotLoans match `stages` table
- [ ] All 22 .docs/ files exist and are populated
- [ ] All 5 .claude/rules/ files regenerated
- [ ] All 3 .claude/ reference files regenerated
- [ ] `Loan_Lifecycle_Roles_Actions.md` exists (old .txt removed)
- [ ] No references to removed/renamed files in any doc
- [ ] Zero negative statements in documentation
- [ ] Run `vendor/bin/pint --dirty --format agent` after SeedScreenshotLoans.php update

---

## Total Impact

- **32 files backed up** → `.backups/2026-04-15/`
- **32 files regenerated/created** (1 CLAUDE.md + 3 .claude refs + 1 .claude script + 5 rules + 22 .docs)
- **1 file renamed** (lifecycle txt → md)
- **1 PHP file updated** (SeedScreenshotLoans)
- **Grand total: 34 files modified/created**
