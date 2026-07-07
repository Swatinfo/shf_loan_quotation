# Plan: Audit — Loan Forms Missing Client-Side Validation

## Context

User noticed the Sanction Decision form submits without jQuery validation. Investigation revealed a **global issue**: `shf-app.js` line 7-8 sets `$('form').attr('novalidate', 'novalidate')` on ALL forms, disabling HTML5 browser validation entirely. Combined with no jQuery validation library, most forms have **zero client-side validation** — they rely solely on server-side Laravel validation.

This is an audit/inventory only — **no code changes**.

---

## Global Root Cause

**File:** `public/js/shf-app.js` (line 7-8)
```js
$('form').attr('novalidate', 'novalidate');
```
This disables HTML5 `required`, `min`, `maxlength`, `pattern` etc. on every form in the app.

---

## Complete Form Inventory

### Forms with NO client-side validation (submit directly)

| # | Form | File | Type | Impact |
|---|------|------|------|--------|
| 1 | Create Loan | `resources/views/loans/create.blade.php` | HTML POST | High — loan creation with customer data |
| 2 | Edit Loan | `resources/views/loans/edit.blade.php` | HTML PUT | High — loan modification |
| 3 | Valuation | `resources/views/loans/valuation.blade.php` | HTML POST | Medium — property valuation details |
| 4 | Disbursement | `resources/views/loans/disbursement.blade.php` | HTML POST | High — financial transaction, no cheque total vs amount check |
| 5 | Query Response | `resources/views/loans/stages.blade.php` (~line 515) | AJAX POST | Medium — can submit empty response |
| 6 | Add Document | `resources/views/loans/documents.blade.php` (~line 135) | AJAX POST | Low — document name |
| 7 | Stage Notes | `resources/views/loans/partials/stage-notes-form.blade.php` | AJAX POST | Medium — stage-specific data |
| 8 | Sanction Decision — Approve | `resources/views/loans/stages.blade.php` (~line 456) | AJAX POST | High — approves loan with no confirmation |
| 9 | Rate & PF actions | `stages.blade.php` (~line 1412-1488) | AJAX POST | Medium |
| 10 | Docket actions | `stages.blade.php` (~line 1413-1424) | AJAX POST | Medium |
| 11 | E-Sign actions | `stages.blade.php` (~line 1391-1396) | AJAX POST | Medium |
| 12 | Legal Verification actions | `stages.blade.php` (~line 1502-1524) | AJAX POST | Medium |
| 13 | Sanction actions | `stages.blade.php` (~line 1525-1556) | AJAX POST | Medium |
| 14 | Stage Status/Assign | `stages.blade.php` (~line 1260-1373) | AJAX POST | Medium |
| 15 | Add Bank | `resources/views/settings/workflow.blade.php` (~line 53) | HTML POST | Low |
| 16 | Add Product | `resources/views/settings/workflow.blade.php` (~line 68) | HTML POST | Low |
| 17 | Add Branch | `resources/views/settings/workflow.blade.php` (~line 103) | HTML POST | Low |
| 18 | Product Stages Config | `resources/views/settings/workflow-product-stages.blade.php` | HTML POST | Low |

### Forms with PARTIAL validation

| # | Form | File | What's validated | What's missing |
|---|------|------|-----------------|----------------|
| 19 | Sanction Decision — Reject | `stages.blade.php` (~line 1656) | 10-char min for rejection reason | — |
| 20 | Sanction Decision — Escalate | `stages.blade.php` (~line 1656) | Remarks required | — |
| 21 | Remark | `resources/views/loans/show.blade.php` (~line 508) | Empty check only | Min length, character validation |
| 22 | OTC Transfer | `stages.blade.php` (~line 1718) | User selection check | — |

### Forms with confirmation dialogs (NOT validation)

- Delete branch — Swal confirmation before delete
- Delete document actions — Swal confirmation

---

## Summary

- **18 forms** with zero client-side validation
- **4 forms** with partial/minimal validation
- **Root cause**: Global `novalidate` attribute + no validation library
- All forms rely entirely on server-side Laravel validation
- Server-side validation IS present and working — this is a UX issue, not a security issue
