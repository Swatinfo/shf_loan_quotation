# Task Tracker

Current and completed tasks. Updated as work progresses.

---

## In Progress

### Turnaround Time Report + Loan Duration (2026-04-15)

- [x] **1. Loan listing** — `total_loan_time` already exists and shows on listing page
- [x] **2. Report controller** — `ReportController@turnaround` + `turnaroundData` with filters
- [x] **3. Report view** — Two tabs: Overall TAT + Stage-wise TAT with DataTables, color-coded
- [x] **4. Route + permission** — `GET /reports/turnaround`, `view_reports` permission seeded
- [x] **5. Update docs** — routes, permissions, views, loans

### SHF Operational Manual v3 → v4 Update (2026-04-15)

**Context:** DVR and quotation-to-loan-sanction are now in CRM. SHF expanding to new cities.

- [x] **1. DVR Updates** — Manual DVR template → CRM DVR module reference with fields
- [x] **2. Loan Journey Updates** — Added Quotation to inquiry stage, CRM flow description in Gujarati
- [x] **3. Branch Expansion** — "Rajkot / Jamnagar" → dynamic "[Branch Name]" throughout
- [x] **4. CRM Portal Guide** — Updated DVR, Quotation, Loans, Tasks module descriptions
- [x] **5. Gujarati Grammar** — 42 fixes: English→Gujarati replacements, grammar corrections, typo fixes
- [x] **6. Table Page-Breaks** — cantSplit + keepLines + keepNext on all 190 tables
- [x] **7. New Sections** — Section 22: Branch Onboarding (14-item checklist), Section 23: Multi-City Expansion
- [x] **8. Quick Reference Card** — Dynamic branch contacts, CRM DVR references
- [x] **9. Version Control** — Updated to v4.0 with changelog

---

## Just Completed

### Complete Documentation Regeneration (2026-04-15)

All docs regenerated from code scan only. Plan: `.claude/plans/rustling-yawning-hennessy.md`

**Phase 1: Backup**
- [x] Create `.backups/2026-04-15/` directory structure
- [x] Copy CLAUDE.md + Loan Lifecycle file
- [x] Copy all .docs/ files (20)
- [x] Copy .claude/ reference + rules files (8)
- [x] Copy tasks/ files (2)

**Phase 2: Rename**
- [x] Rename `Loan Lifecycle - Roles & Actions.txt` → `Loan_Lifecycle_Roles_Actions.md`

**Phase 3: Regenerate .claude/ reference files**
- [x] `.claude/database-schema.md` — from migrations + models
- [x] `.claude/routes-reference.md` — from route files
- [x] `.claude/services-reference.md` — from services + controllers
- [x] `.claude/file-suggestions.sh` — from file tree

**Phase 4: Regenerate .claude/rules/**
- [x] `rules/pre-read-gate.md`
- [x] `rules/coding-feedback.md`
- [x] `rules/project-context.md`
- [x] `rules/workflow.md`
- [x] `rules/laravel-boost.md`

**Phase 5: Regenerate .docs/ (22 files)**
- [x] README.md, api.md, authentication.md, dashboard.md
- [x] database.md, frontend.md, general-tasks.md, models.md
- [x] offline-pwa.md, pdf-generation.md, permissions.md, quotations.md
- [x] settings.md, user-assignment.md, users.md, views.md
- [x] workflow-developer.md, workflow-guide.md
- [x] dvr.md (NEW), loans.md (NEW), roles.md (NEW), activity-log.md (NEW)

**Phase 6: Regenerate CLAUDE.md**
- [x] Write CLAUDE.md (130 lines, under 200 limit)
- [x] Verify line count < 200

**Phase 7: Update SeedScreenshotLoans.php**
- [x] Verify stage keys match stages table (all 16 keys aligned)
- [x] Verified $loans data + advance methods already match current workflow (no changes needed)
- [x] No PHP changes, pint not needed

**Phase 8: Rewrite Loan_Lifecycle_Roles_Actions.md**
- [x] Complete 12-stage lifecycle with phases, roles, actions (16 stages total)

---

## Recently Completed

- [x] Complete Documentation Regeneration from codebase scan (2026-04-15)
- [x] Docket Login + OTC Clearance + Stage Tooling (2026-04-14)
- [x] DVR (Daily Visit Report) Module (2026-04-14)
- [x] Workflow Stage Flow Changes (2026-04-14)
- [x] Branch Flow + User Assignment + Product Stage Replace (2026-04-14)
- [x] jQuery Form Validation + Cleanup (2026-04-15)
- [x] Documentation reorganization and regeneration (2026-04-13)

---

## Completed

(historical tasks archived to .ignore/tasks/todo.md)
