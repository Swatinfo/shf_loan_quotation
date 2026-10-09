# End-to-End Browser Test — Error Log

**Run date:** 2026-10-06
**Environment:** local Herd — https://loanproposal.test (DB `shf_all_operations`, APP_ENV=production)
**Driver:** Chrome DevTools MCP (real browser, not CLI)

## Executive summary

Both end-to-end scenarios ran **in a real browser (Chrome DevTools)** against the live app and **both reached `status = completed`.** The platform is largely error-free across the full quotation→loan→disbursement→payout lifecycle, including the recently-refactored **one-time GST-inclusive PF/Admin/Insurance charges** (verified live: GST back-calc displayed, header-level storage, correct gross) and the **connector read-only** access model.

**One real functional blocker found:**
- 🔴 **#5 — a connector with no branch/location cannot create a quotation** (empty required Location dropdown). Root cause: the `vipul connector` account was seeded without a branch/location. Worked around by assigning the branch; the seeding migration should assign one.

**Worth confirming (config/behavioral, not code bugs):** connector payout rates are 0 for HDFC HOME LOAN (#8), HDFC product skips KFS/E-Sign stages (#7), connector sees only Plain PDF (#9). Plus minor a11y gaps (#1). Full detail below.

## Accounts used
- super_admin — superadmin@shfworld.com (impersonation hub)
- connector — vipulconnector@shfworld.com

## Scenarios
1. **A — Multi-bank quotation → loan → full 12-stage lifecycle** (super_admin + impersonation for role handoffs), multi-entry disbursement with one-time PF/Admin/Insurance.
2. **B — Connector quotation → loan → full lifecycle**, connector read-only verification.

---

## Scenario A — RESULT: ✅ PASS (full lifecycle completed)

Quotation **#396** (RAJESH M PATEL, Salaried, ₹50,00,000, banks ICICI+HDFC+Axis, ROIs + charges)
→ branded PDF generated (570 KB, valid `%PDF-`)
→ converted to loan **#338 / SHF-202610-0011** (ICICI · HOME LOAN, advisor+payout = JAYDEEP #6)
→ all 12 stages completed → loan **status = completed**.

Stages exercised (impersonation used for the role handoffs, as requested):
- document_collection — 8 docs marked received (auto-completed) — super_admin
- app_number — **impersonated JAYDEEP #6** (app no, DME, docket S+3)
- bsm_osv — **impersonated MANTHAN #23** (office)
- technical_valuation — JAYDEEP (valuation ₹63,00,000)
- legal_verification (3-phase) — JAYDEEP → sent to office → MANTHAN initiated → JAYDEEP completed
- sanction_decision — **MANTHAN approved** (is_sanctioned=true)
- rate_pf (3-phase, flag ON) — JAYDEEP filled → MANTHAN returned → JAYDEEP completed
- original_document_verification — JAYDEEP completed → parallel_processing done
- sanction (3-phase) — sent to office → MANTHAN generated → completed (super_admin)
- docket (3-phase) — sent to office → financials captured (₹50,00,000 / 8.5% / 240m / EMI 43,391) → KFS generated
- kfs — completed
- esign (4-phase) — all 4 phases driven
- **disbursement — MULTI-ENTRY**: NEFT ₹30,00,000 (OTC skip) + Cheque ₹19,80,825 (OTC pending); one-time charges PF ₹14,750 / Admin ₹4,425 (GST-inclusive) / Insurance ₹25,000 (excluded). Gross = ₹50,00,000. **GST back-calc shown live**: PF "incl. GST @18% = ₹2,250 · base ₹12,500", Admin "₹675 · base ₹3,750". Header stored pf/admin/insurance at loan level (one-time). ✅ the refactor works in the live UI.
- otc_clearance — cheque OTC cleared → loan **completed**.

**No functional errors in Scenario A.**

## Scenario B — RESULT: ✅ PASS (connector flow + read-only verified; 1 real blocker found & worked around)

Connector quotation **#397** (PRIYA R SHAH, Salaried, ₹40,00,000, banks HDFC+Kotak) created **as the connector** (after the branch fix below) → PDF generated.
- Connector **cannot convert** — no "Convert to Loan" button; `/quotations/397/convert` returns **403**. ✅ correct.
→ converted by super_admin to loan **#339 / SHF-202610-0012** (HDFC · HOME LOAN). **Payout user defaulted to the connector** (vipul connector) automatically. ✅
→ full lifecycle completed (docs → app_number → bsm_osv → parallel wave [legal **waived** → OSV auto-completed, technical, sanction **approved**, rate_pf 3-phase] → sanction 3-phase → docket → **disbursement** single NEFT ₹39,80,250 + one-time PF ₹14,750 / Admin ₹5,000 / Insurance ₹20,000, gross ₹40,00,000, OTC skipped) → loan **status = completed**.
→ **Payout finalized**: 1 `loan_payouts` row, **role_context = connector**, basis 3,980,250, net 0 (see finding #8). ✅ engine correctly detected the connector and applied the connector slab.

**Connector read-only access — all verified (impersonated #34):**
- `/loans/data` returns exactly **1 loan** (only #339, their own). ✅ scoped correctly.
- `/loans/339` + `/loans/339/stages` load (200) but render **0 action buttons**. ✅
- Stage-mutation POST (`/loans/339/stages/document_collection/notes`) → **403**. ✅
- `/loans/339/disbursement` → **403**. ✅
- Connector dashboard shows **Loans + Quotations** tabs, no admin nav. ✅

## Created test records (for cleanup if desired)
- Quotations: **#396** (RAJESH M PATEL), **#397** (PRIYA R SHAH)
- Loans: **#338 / SHF-202610-0011** (completed), **#339 / SHF-202610-0012** (completed)
- 1 `loan_payouts` row for #339.
- Data change made for the test: assigned `vipul connector` (#34) to Rajkot branch (user_branches) + Rajkot location (location_user) + default_branch_id=1 — see finding #5.

## Scenario C — Query system (two-way queries) + stage blocking — RESULT: ✅ PASS

Test loan **#340 / SHF-202610-0013** (ICICI · HOME LOAN). All query actions driven **through the browser** (in-page `fetch` with session + CSRF, same as the UI).

**Completion is blocked while a query is open — verified on every stage reached (clean 422, no 500):**
- app_number → `422 "Cannot complete — 1 unresolved query/queries"`; UI card shows *"Action blocked by an open query… Stage cannot be completed until resolved"* and hides the form.
- bsm_osv → 422; legal_verification (`complete_skip_bank`) → 422; sanction_decision (`approve`) → `422 "Cannot approve — unresolved query"`; original_document_verification → 422.
- All 4 parallel stages (legal, technical, sanction_decision, rate_pf) showed the UI block simultaneously (stage-wise).
- Resolving the query unblocks completion every time.

**Central guard:** `LoanStageService::updateStageStatus()` (line ~475) throws on `hasPendingQueries()` for `completed`; every completion routes through it, so the block is system-wide (applies to super_admin too). Controller pre-checks return clean 422 JSON.

### Change requested & made during this test — block TRANSFERS while a query is open
Originally, transfers/handoffs were **allowed** while a query was open (`send_to_office`/`send_to_bank` returned 200, and open queries *followed* the handoff). Per request, this was changed so a stage **cannot be transferred to another user until its open query is resolved**.

**Code changed:**
- `app/Services/LoanStageService.php::transferStage()` — added a guard that throws `"Cannot transfer stage '{key}' — there are unresolved queries…"` when `hasPendingQueries()`; this is the single chokepoint for ALL transfers (generic transfer, every multi-phase send, escalation). Removed the now-dead "open query follows the handoff" logic.
- `app/Http/Controllers/LoanStageController.php` — added `queryBlock()` pre-checks at the top of `sanctionAction`, `legalAction`, `technicalValuationAction`, `esignAction`, `docketAction`, `ratePfAction` (rate_pf's check runs **before** field validation now), and a pending-query guard in the `sanction_decision` **escalate** branch. Generic `transfer()` already caught the exception → clean 422. `queryBlock()` message made action-agnostic ("Resolve it before continuing.").

**Tests:** updated `StageQueryResolveTest` (the two "query follows transfer" tests replaced with transfer-blocked assertions), added 3 transfer-block HTTP tests to `StageQueryGatingTest`, fixed 1 message assertion. **Full suite: 372/372 green.**

**Browser verification (loan #340):**
- `technical_valuation send_to_office` with open query → now **422** (was 200 before the fix) ✅
- `rate_pf` generic transfer with open query → **422 "Cannot transfer stage 'rate_pf'…"** ✅
- `rate_pf send_to_bank` with open query → **422** (query block now fires before field validation) ✅
- After resolving the queries: technical send → **200**, rate_pf transfer → **200** (reassigned) ✅

No 500s / raw error pages anywhere in the query/transfer flows.

### Linear-stage walk-through (loan #340, every remaining stage, browser) — ✅ PASS

Walked #340 through all remaining stages. At each, raised a query → confirmed both the **completion action** AND a **transfer** return 422 while open → resolved → completed to advance:

| Stage | Completion blocked | Transfer blocked | Result |
|-------|--------------------|------------------|--------|
| sanction (3-phase) | 422 | 422 | ✅ completed after resolve |
| docket (3-phase) | 422 | 422 | ✅ |
| kfs | 422 | 422 | ✅ |
| esign (4-phase) | 422 | 422 | ✅ |
| disbursement | blocked (see bug #11) | 422 | ✅ completed after resolve |
| otc_clearance | blocked ✅ | 422 ✅ | ✅ completed after resolve (loan #341) |

Loan #340 reached **completed** end-to-end.

**otc_clearance — explicit cheque-based test (loan #341):** spun a loan straight to the disbursement stage, posted a **full cheque disbursement** (OTC pending → `otc_clearance` opens `in_progress`), then raised a query on otc_clearance. Results:
- Generic transfer of otc_clearance → **422** "Cannot transfer stage 'otc_clearance'…" ✅
- Clearing the cheque's per-entry OTC with the query open → the handover is **recorded** (`otc=cleared`), but the loan correctly **stays `partial_disbursed`** and the stage `in_progress` (loan does NOT complete) ✅ — and, importantly, **no bug #11-style inconsistency** here: the loan-completion path (`syncDisbursementState` line ~226, "complete loan only if otc is `completed`") already guarded this case.
- After resolving the query + re-recording the clear → otc_clearance + loan → **completed** ✅.

### 🔴 Bug #11 — FOUND & FIXED during the disbursement walk-through

**What:** With an open query on the **disbursement** stage, saving a *full* disbursement (money done + all OTC settled) left the **disbursement stage correctly `in_progress`** (query blocked it) — but `DisbursementService::syncDisbursementState()` **continued** past it and completed `otc_clearance` + marked the **loan `completed`** and advanced `current_stage` to `otc_clearance`. Result: an inconsistent loan — `status=completed` while its `disbursement` stage is still `in_progress` with an unresolved query.

**Why:** `completeStageIfInProgress('disbursement')` skips completion when the stage `hasPendingQueries()` (no exception, silently no-op), but the method kept going to the OTC-complete + loan-complete block.

**Fix:** `app/Services/DisbursementService.php::syncDisbursementState()` — after attempting to close the disbursement stage, if it is not actually `completed`, re-assert `in_progress` and **return early** (stay `partial_disbursed`; don't touch OTC or loan status).

**Tests:** added `DisbursementMultiEntryTest::test_open_query_on_disbursement_blocks_loan_completion` (full suite **373/373** green).

**Browser re-verification (loan #340):** with the query open → `status=partial_disbursed`, disbursement `in_progress`, otc `pending` (entry saved, nothing completed). After resolving the query + re-sync → disbursement + otc completed, loan `completed`. ✅

## 🔴 Bug #12 — FOUND & FIXED: fully-disbursed loan stays open when a NEFT entry has OTC pending

**What (the "all OTC cleared but loan stays open" case the user asked about):** On a fully-disbursed loan, a **fund-transfer (NEFT) entry** was given an OTC handover requirement (the disbursement form defaults every method's OTC to `pending`). But a NEFT has no physical instrument to hand over, so it never gets "cleared". Result: after clearing all the **cheques**, the loan stayed `partial_disbursed` / `otc_clearance in_progress` forever, waiting on a NEFT handover that can't happen. Reproduced on loan #345 (cheque cleared, NEFT `pending` → loan stuck partial).

**Verified first that the pure multi-cheque path is fine:** 2 cheques cleared via the per-entry endpoint (#342), both cheques cleared in the form (#343), and mark-fully-disbursed + cheque (#344) all completed the loan correctly. The bug was specifically NEFT-in-the-OTC-list.

**Fix (OTC applies to cheques only):**
- `DisbursementService::otcAttrs()` — a non-cheque entry is saved with `otc_status = skipped` (ignoring any posted value); NEFT never enters OTC.
- `DisbursementEntry::isOtcSettled()` — returns `true` for any non-cheque method (defensive, also fixes legacy NEFT rows already stored as `pending`).

**UI (ask #2 — "only show the OTC block when pending"):** `resources/views/newtheme/loans/_stages-body.blade.php` otc_clearance section now lists **only cheque** entries, shows the handover block + the "transfer to office" option **only while a cheque OTC is still pending** (`$otcPending > 0`), and the pending count is cheque-only. (The disbursement entry form is unchanged — per the user, OTC entries stay as they are.)

**Tests:** added `DisbursementMultiEntryTest::test_neft_entry_does_not_require_otc_and_does_not_block_completion`; updated 3 tests that assumed NEFT awaited OTC to use cheques. **Full suite 374/374 green.**

**Browser verification:**
- Repro #345 → after the fix, re-sync completes the loan (`completed`, NEFT shows `settled`). ✅
- Fresh cheque+NEFT loan #346: NEFT posted as `pending` → **saved as `skipped`**; the stages OTC block shows **only the cheque** ("Cheque OTC handover — 1 pending"); clearing the cheque alone → loan **completed**. ✅

## Errors / observations found

Severity legend: 🔴 bug · 🟡 minor/UX · ⚪ test-harness artifact (not an app bug)

1. 🟡 **Accessibility — unlabeled form fields.** Console issues on the quotation create page ("No label associated with a form field", count ~90; "A form field element should have an id or name attribute"). The per-bank charge inputs and some stage inputs lack `id`/`for`/`name` label associations. Functional impact: none; screen-reader/a11y only.
   - Where: `quotations/create` bank cards; several stage note forms.
   - Why: inputs rendered without associated `<label for>` / `id`.

2. ⚪ **Convert submit needed a retry (not a bug).** On the convert page the first click on "Convert to Loan Task" didn't submit because a PAN-lookup AJAX (`/customers/lookup?pan=…`) re-rendered the form between my fill and click; a second click succeeded. Real users won't hit this (they don't click in the same millisecond as the lookup). No app defect.

3. ⚪ **Impersonation navigation race (test-harness only).** Rapidly chaining `/impersonate/leave` immediately after a stage action's page reload occasionally returned `net::ERR_ABORTED` and briefly showed the login page; re-navigating confirmed the session was intact (super_admin restored). This is the automation firing navigations faster than a human; impersonation itself worked correctly every time.

5. 🔴 **A connector with no branch/location cannot create a quotation (blocks the connector quotation flow).**
   - **What:** Logged in as the connector (`vipul connector`, #34), the quotation create page renders the **Location** dropdown with **zero options** (only the "-- Select Location --" placeholder) and the **Branch** dropdown empty. Location is required by client validation, so "Generate PDF Proposal" silently fails ("Please select location") and no `/quotations/generate` POST is sent — the connector cannot create a quotation at all.
   - **Where:** `QuotationController@create` (app/Http/Controllers/QuotationController.php:78-101). For non-admin/super_admin users it populates `locations = $user->locations()` and `branches = $user->branches()`.
   - **Why:** The `vipul connector` account has `default_branch_id = NULL`, no `user_branches` rows, and no `location_user` rows. `User::locations()` (location_user pivot) and `User::branches()` (user_branches) therefore return empty, so the dropdowns are empty. The connector has `create_quotation` + `generate_pdf` permissions but no branch/location to create under.
   - **Root cause origin:** the migration `2026_10_06_150941_add_vipul_connector_user.php` seeds the connector **without** assigning a branch or location.
   - **Fix options:** (a) assign every connector a branch + location on creation (update the seeding migration / user-create flow to require it); and/or (b) have the create form fall back to the branch's location or show a clear "no branch assigned — contact admin" message instead of an unusable empty required dropdown.
   - **Severity:** 🔴 functional blocker for the connector-quotation path (worked around in this test by assigning the connector to Rajkot branch + location).

6. ℹ️ **Auto-assignment note (behavioral, not an error).** `bsm_osv`, legal office phase, sanction/docket/esign "office" phases all auto-assigned to the single office employee MANTHAN #23, and `app_number` (a bank-employee stage per docs) auto-assigned to the advisor #6. This is the configured fallback when no dedicated bank-employee auto-assign rule matches the loan's bank; worth confirming it matches intended routing for ICICI.

7. ℹ️ **Per-product stage set differs (behavioral, confirm intended).** Loan #338 (ICICI · HOME LOAN) included **KFS** and **E-Sign & eNACH** stages; loan #339 (HDFC · HOME LOAN) had **neither** — after docket it advanced straight to disbursement. So the enabled-stage set varies by bank/product. Confirm this is the intended workflow config for HDFC (no KFS / no E-Sign).

8. 🟡 **Connector payout rates are 0 for HDFC HOME LOAN → connector earns ₹0 (data/config, not a code bug).** The payout engine correctly detected the connector (`is_connector=1`, `role_context=connector`) and applied the connector slab, but every connector slab rate on product HOME LOAN (id 8) is `0%`, so commission=0; combined with PF/Admin GST deductions exceeding the small insurance payout, net floored to **₹0**. The math is correct — but a connector-owned loan producing ₹0 payout is likely unintended. **Confirm connector payout rates are configured** in Payout Config → product payout for the products connectors actually use.

9. 🟡 **Connector sees only "Plain PDF" (not "Branded PDF") on the quotation show page.** Super_admin saw both Branded + Plain PDF download; the connector saw only Plain PDF. Likely intentional gating, but flagging in case connectors should be able to hand customers the branded proposal.

10. ⚪ **"Save Details" / stage-complete buttons sometimes complete without a visible SweetAlert in automation.** A few stage saves (legal complete, docket Generate KFS, KFS Complete, OTC Clear) completed server-side even when my script reported no swal confirm — they either had no confirm or the confirm auto-resolved. Verified by DB state each time; no functional issue, noted only because the automation's swal-detection was inconsistent.
