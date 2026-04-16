# CLAUDE.md

## Project Overview

Shreenathji Home Finance (SHF) — a bilingual (English/Gujarati) loan management platform for Indian financial services. Generates comparison PDFs across banks with EMI calculations, charges, and required documents. Full loan lifecycle: 12-stage workflow (including 5 parallel sub-stages with multi-phase role handoffs), document collection, stage assignments/transfers, two-way queries, notifications, disbursement tracking, and timeline. General task management for personal/delegated tasks. Daily visit report (DVR) system for field activity tracking. 33 models, 13 services, 24 controllers.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.4, SQLite |
| Frontend | Blade + Bootstrap 5.3 + jQuery 3.7 (local vendor, no build step) |
| Auth | Laravel Breeze (session-based), registration disabled |
| PDF | Chrome headless (any OS) / PDF microservice fallback |
| Testing | PHPUnit 11, formatting: Laravel Pint |
| Offline | IndexedDB + Service Worker (PWA) |

## Development Commands

```bash
php artisan serve                           # Start dev server
php artisan test --compact                  # Run all tests
php artisan test --compact --filter=Name    # Run specific test
vendor/bin/pint --dirty --format agent      # Format changed PHP files
php artisan migrate                         # Run migrations
```

## Key Conventions

- **Bilingual**: All user-facing content in English + Gujarati
- **Indian currency**: `₹ X,XX,XXX` format (Indian comma system via `NumberToWordsService`)
- **Activity logging**: `ActivityLog::log($action, $subject, $properties)`
- **Config**: Use `ConfigService` (reads `app_config` table with `config/app-defaults.php` fallback)
- **Views**: `@extends`/`@section` pattern only — never Blade component wrappers
- **CSS prefix**: `shf-` for all custom classes in `public/css/shf.css`
- **Customer types**: proprietor, partnership_llp, pvt_ltd, salaried
- **Inline validation**: Controllers use inline validation (not Form Requests)
- **7 unified roles**: super_admin, admin, branch_manager, bdh, loan_advisor, bank_employee, office_employee

## Loan Workflow Stages (12 stages)

1. **Inquiry** → 2. **Document Selection** → 3. **Document Collection** → 4. **Parallel Processing** (parent):
   - 4a. Application Number → 4b. BSM/OSV → 4c. Legal Verification (3-phase) + 4d. Technical Valuation + 4e. Sanction Decision (approve/escalate/reject)
5. **Rate & PF** (3-phase) → 6. **Sanction Letter** (3-phase) → 7. **Docket Login** (3-phase) → 8. **KFS** → 9. **E-Sign & eNACH** (4-phase) → 10. **Disbursement** → 11. **OTC Clearance** (cheque only)

Multi-phase stages involve role handoffs: loan_advisor ↔ bank_employee ↔ office_employee.

## General Tasks & DVR

- **General Tasks**: Personal/delegated tasks. Any user creates, assigns, comments. BDH sees branch users' tasks. Admin view-all (read-only). Optional loan link.
- **DVR**: Daily Visit Reports for field tracking. Contact types, purposes, follow-up tracking, visit chains. BDH/BM see branch DVRs.

## Mandatory Pre-Read Gate

Before writing ANY code, read the relevant docs for your task:

| Task | Read FIRST |
|------|-----------|
| Quotation creation/editing | `.docs/quotations.md` + `.claude/services-reference.md` |
| PDF generation/template | `.docs/pdf-generation.md` + `.claude/services-reference.md` |
| Permission changes | `.docs/permissions.md` + `.claude/database-schema.md` |
| Settings/Config tabs | `.docs/settings.md` |
| Frontend (CSS/JS) | `.docs/frontend.md` + `.docs/views.md` |
| Dashboard/DataTables | `.docs/dashboard.md` |
| API endpoints | `.docs/api.md` |
| DB/Models/Migrations | `.claude/database-schema.md` + `.docs/models.md` |
| Routes/Controllers | `.claude/routes-reference.md` |
| Services/Validation | `.claude/services-reference.md` |
| Offline/PWA | `.docs/offline-pwa.md` |
| User management | `.docs/users.md` + `.docs/permissions.md` |
| Authentication/Login | `.docs/authentication.md` |
| Any Blade view edit | `.docs/views.md` + `.docs/frontend.md` |
| Loan CRUD/management | `.docs/loans.md` + `.claude/services-reference.md` |
| Loan stages/workflow | `.docs/workflow-developer.md` + `.claude/services-reference.md` |
| Loan documents | `.claude/services-reference.md` + `.docs/models.md` |
| Loan settings/config | `.docs/settings.md` + `.docs/workflow-developer.md` |
| Notifications | `.claude/services-reference.md` + `.docs/models.md` |
| Disbursement | `.claude/services-reference.md` + `.docs/models.md` |
| Loan remarks/queries | `.claude/services-reference.md` + `.docs/models.md` |
| DVR (Daily Visit Reports) | `.docs/dvr.md` + `.claude/services-reference.md` |
| Workflow stage config | `.docs/settings.md` + `.docs/workflow-developer.md` |
| Impersonation | `.docs/users.md` |
| User assignment / auto-assign | `.docs/user-assignment.md` + `.docs/users.md` |
| General tasks | `.docs/general-tasks.md` + `.claude/routes-reference.md` |
| Dashboard tabs/stats | `.docs/dashboard.md` |
| Roles / role system | `.docs/roles.md` + `.docs/permissions.md` |
| Activity logging | `.docs/activity-log.md` + `.claude/services-reference.md` |
| Loan valuation | `.docs/loans.md` + `.claude/services-reference.md` |

**Always read** (every task): `tasks/lessons.md` + `tasks/todo.md`

## Reference Documentation

| Area | File(s) |
|------|---------|
| All docs index | `.docs/README.md` |
| Database schema | `.claude/database-schema.md` + `.docs/database.md` |
| Routes & middleware | `.claude/routes-reference.md` |
| Services & validation | `.claude/services-reference.md` |
| Models & relationships | `.docs/models.md` |
| Permissions & roles | `.docs/permissions.md` + `.docs/roles.md` |
| Quotation workflow | `.docs/quotations.md` |
| Loan management | `.docs/loans.md` |
| Loan workflow (user) | `.docs/workflow-guide.md` |
| Loan workflow (dev) | `.docs/workflow-developer.md` |
| PDF generation | `.docs/pdf-generation.md` |
| Frontend & CSS | `.docs/frontend.md` + `.docs/views.md` |
| Settings config | `.docs/settings.md` |
| API endpoints | `.docs/api.md` |
| Offline/PWA | `.docs/offline-pwa.md` |
| Users & impersonation | `.docs/users.md` |
| User assignment | `.docs/user-assignment.md` |
| Authentication | `.docs/authentication.md` |
| Dashboard | `.docs/dashboard.md` |
| General tasks | `.docs/general-tasks.md` |
| Daily visit reports | `.docs/dvr.md` |
| Activity logging | `.docs/activity-log.md` |
| Loan lifecycle detail | `Loan_Lifecycle_Roles_Actions.md` |
| Past lessons | `tasks/lessons.md` |
| Current tasks | `tasks/todo.md` |

## Source of Truth Files

- `public/css/shf.css` — ALL custom CSS classes (`shf-*` prefix)
- `public/js/shf-app.js` — Core custom JS (SHF.* namespace)
- `public/js/shf-loans.js` — Loan module JS (SHFLoans.* namespace)
- `config/app-defaults.php` — Default config values
- `config/permissions.php` — Permission definitions and role defaults

Rules auto-loaded from `.claude/rules/`: `pre-read-gate.md`, `coding-feedback.md`, `project-context.md`, `workflow.md`, `laravel-boost.md`
