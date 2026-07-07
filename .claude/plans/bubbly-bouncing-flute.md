# Plan: Workflow Stage Flow Changes

## Context

The user wants to change the flow of several loan stages to better match their business process. Currently some stages auto-assign immediately or have incomplete field logic. The changes span 6 areas: Technical Valuation assignment flow, Rate & PF field calculations, Docket Login date restrictions + new completion flow, KFS completion flow, E-Sign disbursement assignment, and a global no-future-dates rule.

---

## Changes Summary

### 1. Technical Valuation — "Send" Button Before Office Employee Assignment

**Current:** When `bsm_osv` completes, `startRemainingParallelSubStages()` auto-assigns technical_valuation to the best assignee (often office_employee) and starts it immediately.

**New:** Task owner (loan advisor) gets the stage first. They fill valuation details, then press "Send for Technical Valuation" to transfer to office employee.

**Files to modify:**
- `app/Services/LoanStageService.php` — `startRemainingParallelSubStages()` (line 564): For `technical_valuation`, assign to task owner (advisor or loan creator) instead of `findBestAssignee`
- `resources/views/loans/stages.blade.php` — technical_valuation case (line 431): Add a "Send for Technical Valuation" button with office_employee select dropdown (similar to docket/legal patterns)
- `app/Http/Controllers/LoanStageController.php` — New `technicalValuationAction()` method to handle the send action, transfers to office_employee
- `routes/web.php` — Add route for the new action

**Phase flow:**
| Phase | Role | Action |
|-------|------|--------|
| 1 | Task Owner (Advisor) | Click "Send for Technical Valuation" (just transfers, doesn't fill form) |
| 2 | Office Employee | Fill valuation form, mark complete |

**Notes JSON additions:** `tv_phase` ('1' or '2'), `tv_original_assignee` (user ID)

---

### 2. Rate & PF — Processing Fee % or Amount + Admin Charges GST

**Current:** `processing_fee` is always a percentage field. `processing_fee_gst` is a manual currency field labeled "PF GST". `total_pf` is editable. No admin charges GST.

**New flow:**
- Add `processing_fee_type` toggle: "%" or "Amount"
- If "%" → calculate: `(loan_amount * processing_fee / 100)` = PF amount
- If "Amount" → use `processing_fee` value directly = PF amount
- Rename "PF GST" → "GST (%)" with default value `18`
- PF GST amount auto-calculated: `pf_amount * gst_percent / 100`
- **Total PF** = PF amount + PF GST amount (readonly, auto-calculated)
- Add `admin_charges_gst_percent` field (default 18%)
- Admin GST amount auto-calculated: `admin_charges * admin_charges_gst_percent / 100`
- **Total Admin Charges** = admin_charges + admin GST amount (readonly, auto-calculated)
- Two separate totals: Total PF and Total Admin Charges

**Files to modify:**
- `resources/views/loans/stages.blade.php` — Rate & PF section (lines 661-828): Restructure fields for all 3 phases + completed view
- `public/js/shf-loans.js` — Add JS for PF type toggle, auto-calculation of Total PF and Total Admin Charges
- `app/Http/Controllers/LoanStageController.php` — `ratePfAction()` (line 459): Update required fields list, add new fields to original_values snapshot
- `app/Http/Controllers/LoanStageController.php` — `getFieldErrors()`: Update rate_pf field list

**New notes fields:**
- `processing_fee_type`: `'percent'` | `'amount'`
- `processing_fee_amount`: calculated PF in rupees
- `gst_percent`: GST percentage (default 18)
- `pf_gst_amount`: calculated GST on PF
- `total_pf`: PF amount + PF GST amount (readonly)
- `admin_charges_gst_percent`: GST % on admin charges (default 18)
- `admin_charges_gst_amount`: calculated GST on admin charges
- `total_admin_charges`: admin_charges + admin_charges_gst_amount (readonly)

---

### 3. Docket Login — Past Dates Only

**Current:** No date restriction on `login_date` field.

**New:** Only allow today or past dates (no future dates). Apply `endDate: '+0d'` to the docket datepicker.

**Files to modify:**
- `resources/views/loans/stages.blade.php` — Docket login_date field (line 972): Add a data attribute or class for past-only
- JS init for datepicker: Add `endDate: '+0d'` for docket login_date

---

### 4. Docket Login — New Completion Flow (Generate KFS)

**Current flow:**
- Phase 1: Task owner fills login_date → sends to office employee
- Phase 2: Office employee clicks "Complete" → transfers to phase 3 (back to task owner)
- Phase 3: Task owner clicks "Complete" → docket stage completes → KFS auto-assigned via `handleStageCompletion`

**New flow:**
- Phase 1: Task owner fills login_date → sends to office employee (no change)
- Phase 2: Office employee clicks **"Generate KFS"** → docket stage **completes** + KFS stage assigned to **task owner** (not auto-assigned via findBestAssignee)
- Phase 3: **Removed** (no longer needed)

**Files to modify:**
- `app/Http/Controllers/LoanStageController.php` — `updateStatus()` docket phase 2 handler (lines 62-82): Instead of advancing to phase 3, complete the docket stage directly
- `app/Services/LoanStageService.php` — `handleStageCompletion()` (line 282): For `docket` → `kfs` transition, assign to task owner (like docket/otc_clearance pattern) instead of `autoAssignStage`
- `resources/views/loans/stages.blade.php` — Docket phase 2 button (line 991): Rename "Complete" → "Generate KFS"
- Remove phase 3 UI (lines 998-1010)
- `isStageDataComplete()` — Update docket completion check: phase `'2'` only (remove `'3'`)

---

### 5. KFS — Complete Assigns E-Sign to Task Owner

**Current:** KFS completes → `handleStageCompletion` → `autoAssignStage('esign')` via `findBestAssignee`.

**New:** KFS completes → assign E-Sign to **task owner** (assigned_advisor or created_by).

**Files to modify:**
- `app/Services/LoanStageService.php` — `handleStageCompletion()`: Add `kfs` to the list alongside `docket` and `otc_clearance` that assigns to task owner instead of `findBestAssignee`
- `resources/views/loans/stages.blade.php` — KFS view (line 1015): Rename button from "Complete" → "KFS Complete"

---

### 6. E-Sign Completion — Assign Disbursement to Office Employee

**Current:** E-Sign completes → `handleStageCompletion` → `autoAssignStage('disbursement')` via `findBestAssignee`.

**New:** E-Sign completes → assign Disbursement to **office employee** (not task owner, not auto-assign).

**Files to modify:**
- `app/Services/LoanStageService.php` — `handleStageCompletion()`: Add special case for `disbursement` stage: find office employee (default for branch, or any active office_employee) and assign directly

---

### 7. Global — No Future Dates in Loan Stages

**Current:** All datepickers allow any date.

**New:** All datepickers in loan stage forms should have `endDate: '+0d'` (today max).

**Files to modify:**
- `resources/views/loans/stages.blade.php` — JS datepicker init (line 1323 and 1701): Add `endDate: '+0d'` to all `.shf-datepicker` instances within stages
- Note: `rate_valid_until` field (Rate & PF) should KEEP allowing future dates since it's a "valid until" date. Same for `custom_docket_date` in app_number (expected future date). Need a `shf-datepicker-past` class for past-only fields vs keeping `shf-datepicker` for unrestricted.

**Approach:** Use `shf-datepicker-past` class for fields that must be past-only. Change JS init to:
- `.shf-datepicker` → no endDate restriction (for future-allowed fields like rate_valid_until, custom_docket_date)
- `.shf-datepicker-past` → `endDate: '+0d'` (most date fields)

Fields that should allow future dates: `rate_valid_until`, `custom_docket_date`
All other date fields: past-only (`login_date`, `rate_offered_date`, `sanction_date`, `handover_date`, etc.)

---

## Implementation Order

1. **Phase A**: Global date restriction (change 7) — foundation change
2. **Phase B**: Rate & PF fields (change 2) — most complex UI change
3. **Phase C**: Docket flow (changes 3 + 4) — date restriction + flow change
4. **Phase D**: KFS flow (change 5) — simple
5. **Phase E**: E-Sign → Disbursement assignment (change 6) — simple
6. **Phase F**: Technical Valuation send button (change 1) — new action + UI
7. **Phase G**: Update docs + run Pint

## Product Stage / User Configuration Changes

**No product_stages table changes needed.** All changes are in code logic:
- Technical Valuation: Override initial assignment in `startRemainingParallelSubStages()` to use task owner
- KFS → E-Sign: Override in `handleStageCompletion()` to use task owner
- E-Sign → Disbursement: Override in `handleStageCompletion()` to find office employee
- Docket phase 2 → KFS: Override in `handleStageCompletion()` to use task owner

**Stages table (`default_role`):** No changes needed — role eligibility stays the same; we're just changing who gets initially assigned.

## Verification

1. Create a test loan, advance to parallel processing
2. Verify technical_valuation is assigned to task owner, not office employee
3. Verify task owner sees "Send for Technical Valuation" button (no valuation form in phase 1)
4. Verify "Send for Technical Valuation" transfers to office employee who then fills the form
5. Advance to Rate & PF, test % vs amount toggle, verify PF auto-calculations
6. Verify Total PF = PF amount + PF GST (readonly)
7. Verify Total Admin Charges = Admin + Admin GST (readonly)
8. Advance to Docket, verify login_date disallows future dates
9. Verify office employee sees "Generate KFS" button (not "Complete")
10. Verify clicking "Generate KFS" completes docket AND assigns KFS to task owner
11. Verify KFS "KFS Complete" assigns E-Sign to task owner
12. Advance through E-Sign, verify disbursement assigned to office employee
13. Verify all date fields across all stages reject future dates (except rate_valid_until and custom_docket_date)
