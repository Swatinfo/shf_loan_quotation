# Live vs local — file differences

Generated 29-10-2026 10:24 by comparing this codebase with `live_code/` (the code downloaded from the live server).

- **Modified** — the file exists in both but its content differs
- **Modified (formatting only)** — differs only in spacing / quote style (code is the same)
- **Only in local** — the file is in our codebase but not on live (new, not deployed yet)
- **Only in live** — the file is on live but not in our codebase

Line-ending-only differences (CRLF vs LF) are ignored. Not compared: `.backups/`, `.git/`, `.scratch/`, `bootstrap/cache/`, `live_code/`, `node_modules/`, `old_calculation_engine/`, `public/website/`, `screenshots/`, `storage/`, `vendor/`, and backup copies (file names with `BKP` / `backup`, e.g. `*BKP.php`, `backup_*.zip`).

**Total: 104 files** — Modified 60 · Modified (formatting only) 3 · Only in local 38 · Only in live 3 · across 24 directories.

## ⚠ Needs attention before the next deploy

- **`deploy/deploy.sh` — keep the LIVE version.** Live calls `php8.4 artisan …` and has the `if [ -f "artisan" ]` guard commented out (a server-side fix). Our copy calls plain `php`; deploying it would undo that fix.
- **`sw.js` — live is ahead in content.** Live pre-caches the newtheme stylesheets (`/newtheme/assets/*.css`, `/newtheme/css/shf.css`); our copy still lists the old `/css/shf.css` path (only its version stamp is newer). Bring the live content into our codebase before deploying `sw.js`.
- **`.env` / `.env.example` — environment-specific.** Never overwrite the live `.env`; only add new keys (e.g. a newer `SHF_VERSION`).
- **Only on live (not in our codebase):** `.claude/scheduled_tasks.lock`, and `archive_name.zip`.
- **Code not yet on live:** everything marked *Modified* / *Only in local* under `app/`, `database/`, `resources/`, `routes/`, `public/newtheme/`, `tests/` — the partial-disbursement / payout / reconcile work plus `LoanStageController.php` (reset error handling) and `DefaultDataSeeder.php`. Migrations `2026_10_28_*` are only in local → run `php artisan migrate` after deploying.
- **Bulk non-code folder** (listed in full below): `sop/` (only in local).


## (root)/

| File | Status |
|---|---|
| .env | Modified |
| .env.example | Modified |
| .gitignore | Modified |
| .mcp.json | Modified |
| .phpunit.result.cache | Modified |
| 1.md | Only in local |
| _ide_helper.php | Modified |
| _ide_helper_models.php | Only in local |
| archive_name.zip | Only in live |
| disbursement-data-server-update-final.xlsx | Modified |
| end_to_end_test_errors.md | Only in local |
| escalation.md | Only in local |
| gitrestore.md | Only in local |
| go-live.md | Only in local |
| golive-changes-20261007_164318.zip | Only in local |
| loan-disburse-data-fix.md | Modified |
| notification_setup.md | Modified |
| refresh-laravel | Only in local |
| report_plan.md | Only in local |
| sw.js | Modified |

## .claude/

| File | Status |
|---|---|
| database-schema.md | Modified |
| routes-reference.md | Modified |
| scheduled_tasks.lock | Only in live |
| services-reference.md | Modified |

## .claude/rules/

| File | Status |
|---|---|
| project-context.md | Modified |

## .docs/

| File | Status |
|---|---|
| dashboard.md | Modified |
| loans.md | Modified |
| models.md | Modified |
| ops.md | Modified |
| settings.md | Modified |
| workflow-developer.md | Modified |
| workflow-guide.md | Modified |

## .ignore/

| File | Status |
|---|---|
| final_working_code_before_bank_change.zip | Only in local |

## .vscode/

| File | Status |
|---|---|
| settings.json | Only in local |

## app/Console/Commands/

| File | Status |
|---|---|
| LoanSetStageCommand.php | Modified (formatting only) |
| PurgePayoutRunsCommand.php | Only in local |

## app/Http/Controllers/

| File | Status |
|---|---|
| DashboardController.php | Modified |
| LoanController.php | Modified |
| LoanDisbursementController.php | Modified |
| LoanStageController.php | Modified |
| PayoutController.php | Modified |
| PayoutRunController.php | Modified |
| ReportController.php | Modified |
| WorkflowConfigController.php | Modified |

## app/Models/

| File | Status |
|---|---|
| DisbursementDetail.php | Modified |
| LoanDetail.php | Modified |
| Product.php | Modified |

## app/Services/

| File | Status |
|---|---|
| DatabaseBackupService.php | Only in local |
| DisbursementDataService.php | Modified |
| DisbursementService.php | Modified |
| LoanStageService.php | Modified |
| PayoutRunService.php | Modified |
| PayoutService.php | Modified |

## bootstrap/

| File | Status |
|---|---|
| app.php | Modified (formatting only) |

## database/migrations/

| File | Status |
|---|---|
| 2026_10_28_184008_backfill_partial_completed_loan_status.php | Only in local |
| 2026_10_28_184553_add_charges_payout_run_id_to_disbursement_details.php | Only in local |
| 2026_10_28_193918_recalculate_progress_for_partial_loans.php | Only in local |
| 2026_10_28_210902_add_bank_aliases_to_products_table.php | Only in local |

## database/seeders/

| File | Status |
|---|---|
| DefaultDataSeeder.php | Modified |

## deploy/

| File | Status |
|---|---|
| deploy.sh | Modified |
| shf-payout-changes-191026200052.zip | Only in local |

## public/images/

| File | Status |
|---|---|
| brand_page_1 - Copy.png | Only in local |
| Shree-2 - Copy.png | Only in local |

## public/newtheme/assets/

| File | Status |
|---|---|
| shf.css | Modified (formatting only) |

## public/newtheme/pages/

| File | Status |
|---|---|
| payouts.css | Modified |

## resources/views/newtheme/loan-settings/

| File | Status |
|---|---|
| _panes.blade.php | Modified |
| _scripts.blade.php | Modified |

## resources/views/newtheme/loans/

| File | Status |
|---|---|
| _stage-readonly-detail.blade.php | Modified |
| _stages-body.blade.php | Modified |
| disbursement.blade.php | Modified |
| show.blade.php | Modified |

## resources/views/newtheme/payouts/

| File | Status |
|---|---|
| _acc-link.blade.php | Only in local |
| _product-check.blade.php | Only in local |
| reconcile.blade.php | Modified |
| run-show.blade.php | Modified |
| runs.blade.php | Modified |

## routes/

| File | Status |
|---|---|
| web.php | Modified |

## sop/

| File | Status |
|---|---|
| Factory_Joining_Work_Profile_Index 1.docx | Only in local |
| INTERVIEW QUE.docx | Only in local |
| PMS_1_Yearly.docx | Only in local |
| PMS_2_Monthly.docx | Only in local |
| PMS_Guidelines.docx | Only in local |
| Promotion Criteria.docx | Only in local |
| SHF_Operational_Manual_2026.docx | Only in local |
| SHF_Operational_Manual_2026_v3.docx | Only in local |
| SHF_Operational_Manual_2026_v4.docx | Only in local |
| SHF_Operational_Manual_2026_v5.docx | Only in local |
| SHF_Operational_Manual_2026_v6.docx | Only in local |
| SOP.docx | Only in local |
| Unifrom_Dress _Code_Rules_2025_V2.O.docx | Only in local |

## tasks/

| File | Status |
|---|---|
| lessons.md | Modified |
| todo.md | Modified |

## tests/Feature/

| File | Status |
|---|---|
| BackfillPartialCompletedStatusTest.php | Only in local |
| DisbursementDataToolTest.php | Modified |
| DisbursementMultiEntryTest.php | Modified |
| KfsLoanAmountTest.php | Modified |
| PayoutReconcileProductTest.php | Only in local |
| PayoutReconcileTest.php | Modified |
| PayoutRunControllerTest.php | Modified |
| PayoutRunServiceTest.php | Modified |
| PayoutServiceTest.php | Modified |
| PurgePayoutRunsCommandTest.php | Only in local |
| StageReadonlyDetailTest.php | Modified |
| TurnaroundReportTest.php | Only in live |
