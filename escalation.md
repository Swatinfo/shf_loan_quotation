# Escalation Feature — Files to Upload to Server

Makes **Loan Sanction Decision** + **Technical Valuation** escalation **level-driven**
(base → BM → BDH), with admin/super_admin able to act at any level. Base role stays
dynamic (from product-stage config). Technical Valuation has **no Approve** (filling the
valuation form completes it) but **does** have escalate/reject; the escalated-to BM/BDH
also gets the valuation form. Reject = reject the whole loan.

Built/verified: 2026-05-31. Tests: `tests/Feature/StageEscalationTest.php` (6) + 26-test
regression green. Pint clean. Real DB is MySQL (tests run on SQLite).

## Files to upload (code + views)

| File | Change | Purpose |
|------|--------|---------|
| `app/Http/Controllers/LoanStageController.php` | modified | New generic `decisionAction(Request,LoanDetail,string $stageKey)` (level model from `escalation_history` + role guards + admin bypass; approve only for sanction_decision). `sanctionDecisionAction` is now a thin wrapper. |
| `routes/web.php` | modified | Added `POST /loans/{loan}/stages/technical_valuation/decision` → `decisionAction` (name `loans.stages.technical-valuation-decision`). |
| `resources/views/newtheme/loans/_stages-body.blade.php` | modified | sanction_decision buttons now level-driven (+ admin-all); technical_valuation phase 2 adds the escalate/reject decision UI (no Approve) beside the Fill Valuation Form link; uses generic `.shf-decision-*` classes + `data-stage`. |
| `resources/views/newtheme/loans/_stages-scripts.blade.php` | modified | Generalized `.shf-sd-action`/`.shf-sd-remarks` → `.shf-decision-action`/`.shf-decision-remarks`; routes the POST per `data-stage` (sanction `/action`, technical `/decision`). |
| `config/app.php` | modified | `shf_version` → `20260531120000` (busts cached blade JS/CSS). |

## Files NOT changed (intentionally)
- `app/Http/Controllers/LoanValuationController.php` — **unchanged**; valuation save still auto-completes technical_valuation (= the approval). Escalated BM/BDH can fill it because the form is shown to the current holder.
- No DB migration. No role/permission/config-seed changes (base assignment stays driven by product-stage config).

## Docs / tests (optional on server, part of the change)
- `tests/Feature/StageEscalationTest.php` (new) — escalation guard tests.
- `.docs/workflow-developer.md`, `.claude/routes-reference.md` — doc updates.

## After uploading on the server
1. `php artisan config:clear`
2. `php artisan view:clear`
3. (route cache, if used) `php artisan route:cache`

No migrations to run.
