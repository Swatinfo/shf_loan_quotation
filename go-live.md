# Go-Live / Deployment Guide — SHF World

Checklist and file manifest for transferring the current local build to the **live** server.
Generated 2026-10-07. Nothing in this build has been committed to git yet — the manifest below
is the full set of **uncommitted** changes (what the live server does **not** yet have).

> **Golden rules**
> - **Back up the live database AND the live code** before touching anything.
> - **Never overwrite the live `.env`** — it holds the live DB credentials. Edit it in place for the one new key (`SHF_VERSION`).
> - Deploy is **forward-only**: `php artisan migrate --force`. Never `migrate:fresh` / `migrate:refresh` / `rollback` on live.
> - There is **no npm/Vite build** — frontend is local vendor files. Do **not** run `npm run build`.

---

## 1. Pre-flight — things to take care of (in order)

1. **Backup (mandatory).**
   - DB: `mysqldump -u <user> -p shf_all_operations > backup_pre_golive_$(date +%F).sql`
   - Code: tar/zip the current live app directory.

2. **Put the app in maintenance mode** (optional but recommended during migration):
   `php artisan down --render="errors::503"` … and `php artisan up` at the end.

3. **Transfer the files** in the manifest (Section 3). Keep live `.env`, live `storage/`, and
   live `vendor/` as they are unless a dependency changed (none were added — see §4).

4. **`.env` — edit in place (do NOT replace):**
   - Add/update **`SHF_VERSION=20261007161436`** (or any newer unique value). This busts the
     browser cache for all changed `.css`/`.js` — **without it, users keep the old assets** and
     won't see the new disbursement/report/dashboard UI.
   - Confirm `APP_ENV=production`, and the live DB/`LIVE_DB_*` credentials are untouched.

5. **Autoload** — `composer.json` now autoloads `app/helpers.php` (the `inr()`/`inrc()` helpers):
   `composer dump-autoload -o`  (run `composer install --no-dev -o` only if vendor is being rebuilt).

6. **Run migrations + backfills (forward-only):**
   `php artisan migrate --force`
   - This applies 16 migrations (Section 5), including **two data backfills** that mutate live rows
     (`backfill_payout_user_from_creator`, `backfill_otc_handover_date_for_settled_entries`) and a
     per-product/rate version seed. They are idempotent and safe, but this is why the backup matters.

7. **Seed permission rows (recommended):**
   `php artisan db:seed --class=PermissionSeeder`
   - Ensures every permission slug in `config/permissions.php` exists (connector / payout / import
     perms). Role→permission grants come from the migrations; if `loan_advisor` needs
     `convert_to_loan` and it isn't granted on live, set it via the Permissions settings page (it was
     a runtime grant on the old live DB, not a migration).

8. **Clear + rebuild caches:**
   `php artisan optimize:clear` then (if you cache in prod) `php artisan config:cache route:cache view:cache`.
   Always at least `php artisan config:clear` so the new `SHF_VERSION` is read.

9. **Permissions cache:** the 5-min permission cache self-expires; to be safe:
   `php artisan cache:clear`.

10. **File permissions:** ensure `storage/` and `bootstrap/cache/` are writable by the web user.

11. **Security — the seeded connector user:** migration `2026_10_06_150941_add_vipul_connector_user`
    creates **`vipulconnector@shfworld.com`** with password **`password`**. **Change it or delete the
    account** on live if it isn't wanted.

12. **PDF engine:** Chrome headless / PDF microservice must be available on live (unchanged, but verify).

13. **Bring the app back up:** `php artisan up`.

---

## 2. Data-model behaviour changes to be aware of (business impact)

- **Payout is now aggregate + settlement-dated.** Payouts are computed product-wide over a date range
  on the **Payout Runs** screen, keyed on `otc_handover_date` (settlement date). A disbursed-but-not-
  cleared cheque is **excluded from payout until its OTC is cleared**.
- **Reports now show Disbursed (gross, money-out) + Settled (OTC-cleared) side by side**; the gap =
  money awaiting OTC. Dashboard breakdown relabelled: *Awaiting OTC Clearance / Partially Completed /
  OTC Cleared*.
- **NEFT tranches** carry a **Transfer Date** (= settlement date); **cheque** settles on its cleared
  handover date. The backfill sets these for historical rows so nothing drops out of payout/reports.
- **Disbursement charges (PF/Admin/Insurance)** are one-time per disbursement (header-level), GST
  back-calculated. Per-tranche charge columns were **dropped** from `disbursement_entries` (migration
  `make_disbursement_charges_one_time`) — the backup covers you, but note it is a destructive column drop
  (the live rows had 0 there, so no data loss).
- **Payout user eligibility:** cannot be super_admin / admin / bank_employee / office_employee.

---

## 3. Files to transfer — folder-wise, ascending

Legend: **[N]** = new file, **[M]** = modified. (Nothing deleted.)

### (root)
- [M] .gitignore
- [M] composer.json                      ← re-run `composer dump-autoload -o`
- [N] end_to_end_test_errors.md          *(notes only — optional)*
- [N] go-live.md                         *(this file — optional)*

### .claude/   *(reference docs — optional to deploy)*
- [M] .claude/database-schema.md
- [M] .claude/routes-reference.md
- [M] .claude/services-reference.md

### .docs/   *(reference docs — optional to deploy)*
- [M] .docs/dashboard.md
- [M] .docs/models.md
- [M] .docs/permissions.md
- [M] .docs/quotations.md
- [M] .docs/roles.md
- [M] .docs/settings.md
- [M] .docs/workflow-developer.md

### app/
- [N] app/helpers.php                     ← autoloaded via composer.json

### app/Http/Controllers/
- [M] app/Http/Controllers/DailyVisitReportController.php
- [M] app/Http/Controllers/DashboardController.php
- [N] app/Http/Controllers/DisbursementDataController.php
- [M] app/Http/Controllers/GeneralTaskController.php
- [M] app/Http/Controllers/ImpersonateController.php
- [M] app/Http/Controllers/LoanController.php
- [M] app/Http/Controllers/LoanConversionController.php
- [M] app/Http/Controllers/LoanDisbursementController.php
- [M] app/Http/Controllers/LoanRemarkController.php
- [M] app/Http/Controllers/LoanSettingsController.php
- [M] app/Http/Controllers/LoanStageController.php
- [N] app/Http/Controllers/PayoutController.php
- [N] app/Http/Controllers/PayoutRunController.php
- [M] app/Http/Controllers/QuotationController.php
- [M] app/Http/Controllers/ReportController.php
- [M] app/Http/Controllers/WorkflowConfigController.php

> **Removed this session:** `app/Http/Controllers/LoanPayoutController.php` was deleted locally (the
> old per-loan finalize path). If it exists on live, **delete it** after deploy (its route is gone).

### app/Http/Middleware/
- [M] app/Http/Middleware/CheckPermission.php

### app/Models/
- [M] app/Models/DisbursementDetail.php
- [M] app/Models/DisbursementEntry.php
- [M] app/Models/LoanDetail.php
- [N] app/Models/LoanPayout.php
- [N] app/Models/PayoutRateVersion.php
- [N] app/Models/PayoutRun.php
- [N] app/Models/PayoutRunLine.php
- [N] app/Models/PayoutRunProduct.php
- [N] app/Models/PayoutRunUser.php
- [M] app/Models/Product.php
- [M] app/Models/ProductPayoutSlab.php
- [N] app/Models/ProductPayoutVersion.php
- [M] app/Models/Role.php
- [M] app/Models/ShfNotification.php
- [M] app/Models/User.php

### app/Services/
- [N] app/Services/DisbursementDataService.php
- [M] app/Services/DisbursementService.php
- [M] app/Services/LoanConversionService.php
- [M] app/Services/LoanPipelineBreakdownService.php
- [M] app/Services/LoanStageService.php
- [N] app/Services/PayoutConfigService.php
- [N] app/Services/PayoutRunService.php
- [N] app/Services/PayoutService.php
- [M] app/Services/XlsxExportService.php
- [N] app/Services/XlsxImportService.php

### config/
- [M] config/app-defaults.php
- [M] config/permissions.php

### database/migrations/   *(see Section 5 for run order)*
- [N] database/migrations/2026_10_05_160000_add_connector_role.php
- [N] database/migrations/2026_10_05_160100_add_payout_user_to_loans.php
- [N] database/migrations/2026_10_05_170000_add_payout_engine.php
- [N] database/migrations/2026_10_06_104758_add_disbursement_charges.php
- [N] database/migrations/2026_10_06_130813_add_payout_breakdown_to_loan_payouts.php
- [N] database/migrations/2026_10_06_134433_create_payout_rate_versions.php
- [N] database/migrations/2026_10_06_134434_create_product_payout_versions.php
- [N] database/migrations/2026_10_06_141011_backfill_payout_user_from_creator.php
- [N] database/migrations/2026_10_06_145707_add_view_connector_loans_permission.php
- [N] database/migrations/2026_10_06_150941_add_vipul_connector_user.php
- [N] database/migrations/2026_10_06_174114_add_import_disbursement_data_permission.php
- [N] database/migrations/2026_10_06_184750_make_disbursement_charges_one_time.php
- [N] database/migrations/2026_10_07_100000_create_payout_run_tables.php
- [N] database/migrations/2026_10_07_100100_add_payout_run_coverage_columns.php
- [N] database/migrations/2026_10_07_134330_add_transfer_date_to_disbursement_entries_table.php
- [N] database/migrations/2026_10_07_143752_backfill_otc_handover_date_for_settled_entries.php

### public/
- [M] public/sw.js                        ← PWA service worker (bump also handled by SHF_VERSION)

### public/newtheme/pages/
- [M] public/newtheme/pages/dashboard.js
- [M] public/newtheme/pages/loan-disbursement.css
- [M] public/newtheme/pages/loan-report.js
- [M] public/newtheme/pages/management.js
- [N] public/newtheme/pages/payouts.css
- [M] public/newtheme/pages/pipeline.js

### resources/views/newtheme/  (blades)
- [M] resources/views/newtheme/activity-log.blade.php
- [M] resources/views/newtheme/dvr/index.blade.php
- [M] resources/views/newtheme/general-tasks/show.blade.php
- [N] resources/views/newtheme/loans/disbursement-data.blade.php
- [M] resources/views/newtheme/loans/_stage-readonly-detail.blade.php
- [M] resources/views/newtheme/loans/_stages-body.blade.php
- [M] resources/views/newtheme/loans/_stages-scripts.blade.php
- [M] resources/views/newtheme/loans/_valuation-body.blade.php
- [M] resources/views/newtheme/loans/disbursement.blade.php
- [M] resources/views/newtheme/loans/index.blade.php
- [M] resources/views/newtheme/loans/show.blade.php
- [M] resources/views/newtheme/loans/stages.blade.php
- [M] resources/views/newtheme/loans/valuation.blade.php
- [M] resources/views/newtheme/loans/valuation-map.blade.php
- [M] resources/views/newtheme/loan-settings/_panes.blade.php
- [M] resources/views/newtheme/loan-settings/_scripts.blade.php
- [M] resources/views/newtheme/loan-settings/index.blade.php
- [M] resources/views/newtheme/loan-settings/product-stages.blade.php
- [M] resources/views/newtheme/partials/bottom-nav.blade.php
- [M] resources/views/newtheme/partials/create-task-modal.blade.php
- [M] resources/views/newtheme/partials/header.blade.php
- [N] resources/views/newtheme/payouts/reconcile.blade.php
- [N] resources/views/newtheme/payouts/report.blade.php
- [N] resources/views/newtheme/payouts/run-show.blade.php
- [N] resources/views/newtheme/payouts/runs.blade.php
- [M] resources/views/newtheme/quotations/_convert-body.blade.php
- [M] resources/views/newtheme/quotations/create.blade.php
- [M] resources/views/newtheme/quotations/index.blade.php
- [M] resources/views/newtheme/reports/loan-report.blade.php
- [M] resources/views/newtheme/reports/pipeline.blade.php
- [M] resources/views/newtheme/roles/create.blade.php
- [M] resources/views/newtheme/roles/edit.blade.php
- [M] resources/views/newtheme/roles/index.blade.php
- [M] resources/views/newtheme/settings/_workflow-product-stages-body.blade.php

### routes/
- [M] routes/web.php

### tasks/   *(working notes — optional)*
- [M] tasks/lessons.md
- [M] tasks/todo.md

### tests/Feature/   *(optional on live; required if you run the suite on a staging mirror)*
- [N] tests/Feature/ConnectorLoanVisibilityTest.php
- [N] tests/Feature/ConnectorRoleTest.php
- [N] tests/Feature/DisbursementDataToolTest.php
- [M] tests/Feature/DisbursementMultiEntryTest.php
- [N] tests/Feature/LoanConversionDefaultsTest.php
- [N] tests/Feature/LoanPayoutUserTest.php
- [M] tests/Feature/LoanReportTest.php
- [M] tests/Feature/ManagementReportTest.php
- [N] tests/Feature/PayoutConfigSettingTest.php
- [N] tests/Feature/PayoutReconcileTest.php
- [N] tests/Feature/PayoutRunControllerTest.php
- [N] tests/Feature/PayoutRunServiceTest.php
- [N] tests/Feature/PayoutServiceTest.php
- [M] tests/Feature/ProductPayoutConfigTest.php
- [M] tests/Feature/ReportExportTest.php
- [M] tests/Feature/StageBreakdownTest.php
- [M] tests/Feature/StageQueryGatingTest.php
- [M] tests/Feature/StageQueryResolveTest.php
- [N] tests/Feature/UserSelectableScopeTest.php

### tests/Unit/
- [N] tests/Unit/IndianNumberHelperTest.php

> **Not transferred:** `.env` (edit in place), `storage/*` (live runtime), `vendor/*` (no new deps),
> `node_modules/*` (none), and the SQLite test config under `.scratch/` (local only).

---

## 4. Dependencies

- **No new composer packages** were added. `composer.json` only added `app/helpers.php` to the
  `autoload.files` array → **`composer dump-autoload -o`** is required; a full `composer install` is not.
- PHP 8.4 / Laravel 12 (unchanged). No front-end build step.

---

## 5. Migrations to run (ascending order = run order)

`php artisan migrate --force` runs these in filename order:

| # | Migration | What it does | Data backfill? |
|---|-----------|--------------|----------------|
| 1 | 2026_10_05_160000_add_connector_role | Connector role + 9 permissions | seeds |
| 2 | 2026_10_05_160100_add_payout_user_to_loans | `loan_details.payout_user_id` + `change_payout_user` perm | — |
| 3 | 2026_10_05_170000_add_payout_engine | `loan_payouts` table + product payout slab/cycle cols | — |
| 4 | 2026_10_06_104758_add_disbursement_charges | PF/Admin/Insurance cols | — |
| 5 | 2026_10_06_130813_add_payout_breakdown_to_loan_payouts | payout breakdown cols | — |
| 6 | 2026_10_06_134433_create_payout_rate_versions | rate version table | **seeds (effective 2026-04-01)** |
| 7 | 2026_10_06_134434_create_product_payout_versions | product version table + pointer | **backfills 1 version/product** |
| 8 | 2026_10_06_141011_backfill_payout_user_from_creator | payout_user = creator where null | **backfill** |
| 9 | 2026_10_06_145707_add_view_connector_loans_permission | `view_connector_loans` perm | seeds |
| 10 | 2026_10_06_150941_add_vipul_connector_user | connector user (⚠ default pw `password`) | seeds |
| 11 | 2026_10_06_174114_add_import_disbursement_data_permission | `import_disbursement_data` perm | seeds |
| 12 | 2026_10_06_184750_make_disbursement_charges_one_time | consolidate charges to header, **drop per-entry charge cols** | consolidates |
| 13 | 2026_10_07_100000_create_payout_run_tables | 4 `payout_run_*` tables | — |
| 14 | 2026_10_07_100100_add_payout_run_coverage_columns | coverage cols on entries/details | — |
| 15 | 2026_10_07_134330_add_transfer_date_to_disbursement_entries_table | `transfer_date` col | — |
| 16 | 2026_10_07_143752_backfill_otc_handover_date_for_settled_entries | NEFT/skipped settlement dates | **backfill** |

After migrating, confirm: `php artisan migrate:status` shows **0 pending**.

---

## 6. Post-deploy verification

- [ ] `php artisan migrate:status` → no pending.
- [ ] Product payout versions: 1 per active product; rate versions = 4; payout_user set on all loans.
- [ ] Disbursement screen: add/collapse tranches, NEFT Transfer Date + OTC Skip, cheque OTC Pending/Cleared.
- [ ] **Payout Runs** screen computes a preview and finalises.
- [ ] Loan Report shows **Disbursed + Settled + Pending OTC**; totals reconcile.
- [ ] Dashboard breakdown shows **Awaiting OTC Clearance / Partially Completed / OTC Cleared**.
- [ ] Settings → correct top nav highlights (Disbursement Data / Loan Settings / Roles / Activity Log under **Settings**).
- [ ] Hard-refresh a page and confirm new CSS/JS load (SHF_VERSION busted the cache).
- [ ] Connector login works; `vipulconnector@shfworld.com` password changed/removed.
- [ ] (Optional) On a staging mirror: `php artisan test` → full suite green (last local run: 397 passing).

---

## 7. Rollback

- Restore the DB from the pre-go-live dump and restore the code archive. (The migrations' `down()`
  methods are not relied on for rollback — restore from backup instead, especially because migration
  #12 drops columns and the backfills are one-way.)
