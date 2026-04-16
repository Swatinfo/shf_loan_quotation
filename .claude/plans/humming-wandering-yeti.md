# Fix: Stage Form Auto-Fill & Validation Issues

## Context
Multiple issues with stage forms across the workflow:
1. app_number: Custom date not validated when "Custom Date" selected
2. app_number: Docket Timeline select doesn't auto-fill saved value on edit
3. rate_pf / sanction: Datepickers don't work in edit forms (hidden then revealed)
4. All edit forms: Datepicker not re-initialized after slideDown reveal

## Issue 1: Select field doesn't auto-fill (app_number docket_days_offset)

**File:** `resources/views/loans/partials/stage-notes-form.blade.php` line 25

**Root cause:** PHP numeric string array keys are coerced to integers. Options defined as:
```php
['' => 'Select...', '1' => 'S+1', '2' => 'S+2', '3' => 'S+3', '0' => 'Custom Date']
```
PHP converts `'1'` key → `int 1`. But `$fieldValue()` returns `string "1"` from DB. Strict comparison `"1" === 1` → `false`. No option selected.

**Fix:** Cast both sides to string:
```blade
{{ (string) $fieldValue($field) === (string) $val ? 'selected' : '' }}
```

## Issue 2: Custom date not validated

**File:** `app/Http/Controllers/LoanStageController.php`

**`getFieldErrors()` (line 613):** Only validates `application_number` + `docket_days_offset`. Missing conditional validation for `custom_docket_date` when `docket_days_offset === '0'`.

**Fix:**
```php
'app_number' => array_merge(
    ['application_number' => 'Application Number', 'docket_days_offset' => 'Docket Timeline'],
    ($data['docket_days_offset'] ?? '') === '0' ? ['custom_docket_date' => 'Custom Docket Date'] : []
),
```

**`isStageDataComplete()` (line 666):** Doesn't check custom_docket_date when offset is '0'. Stage auto-completes with empty custom date.

**Fix:**
```php
'app_number' => !empty($notesData['application_number'])
    && isset($notesData['docket_days_offset']) && $notesData['docket_days_offset'] !== ''
    && ($notesData['docket_days_offset'] !== '0' || !empty($notesData['custom_docket_date'])),
```

## Issue 3: Hidden edit forms — datepickers, currency fields, and toggles don't initialize

**File:** `resources/views/loans/stages.blade.php` line 1597-1601

When clicking "Edit" on a completed stage, the hidden form (`.shf-collapse-hidden`) slides down. But all interactive widgets were initialized at page load — hidden elements are skipped or don't format properly.

**3a: Datepickers not initialized** (Bootstrap Datepicker skips hidden inputs)
- app_number edit: `custom_docket_date`
- rate_pf edit: `rate_offered_date`, `rate_valid_until`
- sanction edit: `sanction_date` (readonly, but still needs picker if readonly removed)

**3b: Currency fields not formatted** (`SHF.initAmountFields()` runs at page load, line 372 in shf-app.js)
- The `shf-amount-input` display fields have raw values but no Indian formatting
- Amount-to-words text not shown
- `SHF.initAmountFields()` checks `data('shf-amount-bound')` to skip already-bound fields, so calling it again is safe for new fields

**Affected currency fields in edit forms:**
- rate_pf: `admin_charges`, `processing_fee_gst`, `total_pf`
- sanction: `sanctioned_amount`, `emi_amount`

**3c: Custom docket date toggle** (app_number edit)
- Toggle runs at page load but select is hidden → toggle state incorrect on reveal

**Fix: Update the edit toggle handler** (line 1597-1601) to re-initialize ALL widgets:
```javascript
$(document).on('click', '.shf-edit-saved', function() {
    var target = $(this).data('target');
    $(this).closest('.shf-stage-saved-data').hide();
    $(target).slideDown(200, function() {
        // Re-initialize datepickers in revealed form
        $(target).find('.shf-datepicker').datepicker({
            format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true
        });
        // Re-initialize currency formatting
        SHF.initAmountFields();
        // Re-evaluate docket date toggle if present
        $(target).find('[name="docket_days_offset"]').each(function() {
            toggleCustomDocketDate($(this));
        });
    });
});
```

## All stage form includes audit

| Line | Stage | Context | Passes `$assignment`? | Issue? |
|------|-------|---------|-----------------------|--------|
| 319 | app_number (sub) | Completed edit | YES (`$sub`) | None |
| 349 | app_number (sub) | In-progress | YES (`$sub`) | Select auto-fill (Issue 1) |
| 709 | rate_pf | Completed edit | Inherited from scope | Datepicker init (Issue 3) |
| 730 | rate_pf phase 1 | In-progress | Inherited from scope | None (fresh form) |
| 760 | rate_pf phase 2 | In-progress | Inherited from scope | None |
| 789 | rate_pf phase 3 | In-progress | Inherited from scope | None |
| 850 | sanction | Completed edit | Inherited from scope | Datepicker init (Issue 3) |
| 894 | sanction phase 3 | In-progress | Inherited from scope | None |
| 953 | docket phase 1 | In-progress | Inherited from scope | None |
| 1085 | otc_clearance | In-progress | Inherited from scope | None |

Note: All forms inherit `$assignment` and `$loan` from parent Blade scope. The explicit `['assignment' => $sub]` on lines 319/349 is needed because sub-stages use `$sub` variable, not `$assignment`.

## Key Files to Modify
1. `resources/views/loans/partials/stage-notes-form.blade.php` — line 25 (select comparison)
2. `app/Http/Controllers/LoanStageController.php` — lines 613, 666 (validation + completion)
3. `resources/views/loans/stages.blade.php` — lines 1597-1601 (edit toggle JS)

## Verification
1. app_number: Select "S+1" → save → edit → should show "S+1" selected
2. app_number: Select "Custom Date" → save empty → should show validation error
3. app_number: Select "Custom Date" → enter date → save → edit → both fields filled
4. rate_pf completed → click Edit → date fields should have datepicker working
5. sanction completed → click Edit → date field should show saved date with datepicker
