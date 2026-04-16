# Stage G: Advanced Stages (5-10) and Disbursement Decision Tree

## Overview

Adds stage-specific forms for stages 5-9 and implements the Stage 10 disbursement decision tree (Fund Transfer vs Cheque with OTC handling). Stage-specific data for 5-9 is stored in `stage_assignments.notes` as JSON — no new tables needed except for disbursement.

## Dependencies

- Stage E (stage workflow engine)
- Stage F (parallel processing — stages 1-4 must work)

---

## Stages 5-9: Sequential with Stage-Specific Data

### Data Storage Strategy

Each of stages 5-9 has a small set of specific fields. Rather than creating 5 separate tables, we store this data as JSON in the existing `stage_assignments.notes` column.

**Reading/writing JSON notes** (helper on StageAssignment model):
```php
// In StageAssignment model
public function getNotesData(): array
{
    if (!$this->notes) return [];
    $decoded = json_decode($this->notes, true);
    return is_array($decoded) ? $decoded : [];
}

public function setNotesData(array $data): void
{
    $this->update(['notes' => json_encode($data)]);
}

public function mergeNotesData(array $data): void
{
    $existing = $this->getNotesData();
    $this->update(['notes' => json_encode(array_merge($existing, $data))]);
}
```

---

### Stage 5: Rate & PF Request

**Stage Key**: `rate_pf`

**Fields** (stored in notes JSON):
| Key | Type | Description |
|-----|------|-------------|
| `interest_rate` | string | Final interest rate offered by bank (e.g., "8.75%") |
| `processing_fee` | string | Processing fee amount or percentage |
| `processing_fee_gst` | string | GST on processing fee |
| `total_pf` | string | Total PF including GST |
| `rate_offered_date` | string | Date rate was offered (YYYY-MM-DD) |
| `rate_valid_until` | string | Rate validity expiry date |
| `bank_reference` | string | Bank's internal reference number |
| `special_conditions` | string | Any special conditions from bank |

**Flow**: Mortgage Advisor requests → Bank Employee responds with rate and PF details → Advisor completes stage.

---

### Stage 6: Sanction Letter

**Stage Key**: `sanction`

**Fields** (stored in notes JSON):
| Key | Type | Description |
|-----|------|-------------|
| `sanction_letter_number` | string | Sanction letter reference |
| `sanction_date` | string | Date of sanction (YYYY-MM-DD) |
| `sanctioned_amount` | string | Amount sanctioned by bank |
| `sanctioned_rate` | string | Interest rate in sanction |
| `sanction_validity` | string | Validity period of sanction |
| `conditions` | string | Conditions mentioned in sanction letter |

---

### Stage 7: Docket Login

**Stage Key**: `docket`

**Fields** (stored in notes JSON):
| Key | Type | Description |
|-----|------|-------------|
| `docket_number` | string | Docket/file number |
| `login_date` | string | Date docket was logged in (YYYY-MM-DD) |
| `documents_submitted` | string | List of physical documents submitted |
| `submitted_to` | string | Person/department docket submitted to |
| `acknowledgement_number` | string | Receipt/acknowledgement reference |

---

### Stage 8: KFS Generation

**Stage Key**: `kfs`

**Fields** (stored in notes JSON):
| Key | Type | Description |
|-----|------|-------------|
| `kfs_generated` | boolean | Whether KFS has been generated |
| `kfs_date` | string | Date of KFS generation (YYYY-MM-DD) |
| `kfs_reference` | string | KFS reference number |
| `customer_acknowledged` | boolean | Whether customer has acknowledged KFS |
| `acknowledgement_date` | string | Date customer acknowledged |

---

### Stage 9: E-Sign & eNACH

**Stage Key**: `esign`

**Fields** (stored in notes JSON):
| Key | Type | Description |
|-----|------|-------------|
| `esign_completed` | boolean | Whether e-sign is done |
| `esign_date` | string | Date of e-sign (YYYY-MM-DD) |
| `esign_reference` | string | E-sign transaction reference |
| `enach_registered` | boolean | Whether eNACH mandate is registered |
| `enach_date` | string | eNACH registration date |
| `enach_bank_account` | string | Account number for eNACH |
| `enach_amount` | string | EMI amount for mandate |

---

## Stage 10: Disbursement Decision Tree

### Decision Flow

```
Stage 10: Disbursement
├── Method: Fund Transfer
│   └── Enter: account number, IFSC, amount, date, reference
│       └── LOAN COMPLETED
│
└── Method: Cheque
    ├── Direct: cheque number, date, amount
    │   └── LOAN COMPLETED
    │
    └── OTC (Over The Counter)
        ├── Enter: cheque details + OTC branch
        └── OTC Clearance
            ├── OTC cleared → date, reference
            │   └── LOAN COMPLETED
            └── Pending (wait for clearance)
```

---

## Migration: `create_disbursement_details_table`

**File**: `database/migrations/xxxx_xx_xx_create_disbursement_details_table.php`

**Table**: `disbursement_details`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_id | FK → loan_details | no | | cascade on delete, unique |
| disbursement_type | string | no | | values: fund_transfer, cheque, demand_draft |
| disbursement_date | date | yes | null | |
| amount_disbursed | unsignedBigInteger | yes | null | in INR |
| bank_account_number | string | yes | null | for fund_transfer |
| ifsc_code | string | yes | null | for fund_transfer |
| cheque_number | string | yes | null | for cheque |
| cheque_date | date | yes | null | for cheque |
| dd_number | string | yes | null | for demand_draft |
| dd_date | date | yes | null | for demand_draft |
| is_otc | boolean | no | false | Over The Counter — only for cheque |
| otc_branch | string | yes | null | OTC collection branch |
| otc_cleared | boolean | no | false | has OTC been cleared |
| otc_cleared_date | date | yes | null | |
| otc_cleared_by | FK → users | yes | null | set null on delete |
| reference_number | string | yes | null | bank reference/UTR number |
| notes | text | yes | null | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: unique on `loan_id`

---

## Model: DisbursementDetail

**File**: `app/Models/DisbursementDetail.php`

**Table**: `disbursement_details`

**Fillable**: `loan_id`, `disbursement_type`, `disbursement_date`, `amount_disbursed`, `bank_account_number`, `ifsc_code`, `cheque_number`, `cheque_date`, `is_otc`, `otc_branch`, `otc_cleared`, `otc_cleared_date`, `otc_cleared_by`, `reference_number`, `notes`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `amount_disbursed` | integer |
| `disbursement_date` | date |
| `cheque_date` | date |
| `is_otc` | boolean |
| `otc_cleared` | boolean |
| `otc_cleared_date` | date |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `otcClearedByUser()` | BelongsTo | User | `otc_cleared_by` |

**Accessors**:
| Accessor | Returns | Description |
|----------|---------|-------------|
| `getFormattedAmountAttribute()` | string | `₹ X,XX,XXX` format |
| `getTypeLabel()` | string | "Fund Transfer" or "Cheque" |

**Constants**:
```php
const TYPE_FUND_TRANSFER = 'fund_transfer';
const TYPE_CHEQUE = 'cheque';
const TYPE_DEMAND_DRAFT = 'demand_draft';

const TYPES = [
    self::TYPE_FUND_TRANSFER => 'Fund Transfer (NEFT/RTGS) / ફંડ ટ્રાન્સફર',
    self::TYPE_CHEQUE => 'Cheque / ચેક',
    self::TYPE_DEMAND_DRAFT => 'Demand Draft / ડિમાન્ડ ડ્રાફ્ટ',
];
```

**Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `isComplete()` | bool | Fund transfer always complete; cheque: complete if !OTC or OTC cleared |
| `needsOtcClearance()` | bool | `$this->is_otc && !$this->otc_cleared` |

```php
public function isComplete(): bool
{
    if ($this->disbursement_type === self::TYPE_FUND_TRANSFER) {
        return true;
    }
    // Cheque: complete if not OTC, or if OTC is cleared
    return !$this->is_otc || $this->otc_cleared;
}
```

---

### LoanDetail Model Additions

**Add Relationship**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `disbursement()` | HasOne | DisbursementDetail | `loan_id` |

---

## Service: DisbursementService

**File**: `app/Services/DisbursementService.php`

### Constructor

```php
public function __construct(
    private LoanStageService $stageService,
) {}
```

### Methods

#### `processDisbursement(LoanDetail $loan, array $data): DisbursementDetail`

```php
public function processDisbursement(LoanDetail $loan, array $data): DisbursementDetail
{
    return DB::transaction(function () use ($loan, $data) {
        $disbursement = DisbursementDetail::updateOrCreate(
            ['loan_id' => $loan->id],
            $data,
        );

        // If disbursement is complete (fund transfer or cheque without pending OTC)
        if ($disbursement->isComplete()) {
            // Complete the disbursement stage
            $this->stageService->updateStageStatus($loan, 'disbursement', 'completed', auth()->id());

            // Loan is now completed
            $loan->update(['status' => LoanDetail::STATUS_COMPLETED]);

            ActivityLog::log('process_disbursement', $disbursement, [
                'loan_number' => $loan->loan_number,
                'type' => $disbursement->disbursement_type,
                'amount' => $disbursement->amount_disbursed,
            ]);
        } else {
            // OTC pending — stage stays in_progress
            ActivityLog::log('save_disbursement_pending_otc', $disbursement, [
                'loan_number' => $loan->loan_number,
                'otc_branch' => $disbursement->otc_branch,
            ]);
        }

        return $disbursement;
    });
}
```

---

#### `clearOtc(DisbursementDetail $disbursement): DisbursementDetail`

```php
public function clearOtc(DisbursementDetail $disbursement): DisbursementDetail
{
    return DB::transaction(function () use ($disbursement) {
        $disbursement->update([
            'otc_cleared' => true,
            'otc_cleared_date' => now()->toDateString(),
            'otc_cleared_by' => auth()->id(),
        ]);

        $loan = $disbursement->loan;

        // Now complete the disbursement stage and loan
        $this->stageService->updateStageStatus($loan, 'disbursement', 'completed', auth()->id());
        $loan->update(['status' => LoanDetail::STATUS_COMPLETED]);

        ActivityLog::log('otc_cleared', $disbursement, [
            'loan_number' => $loan->loan_number,
        ]);

        return $disbursement->fresh();
    });
}
```

---

## Controller: LoanDisbursementController

**File**: `app/Http/Controllers/LoanDisbursementController.php`

### Actions

#### `show(LoanDetail $loan)` — `GET /loans/{loan}/disbursement`

**Permission**: `manage_loan_stages`

```php
public function show(LoanDetail $loan)
{
    $disbursement = $loan->disbursement;
    return view('loans.disbursement', compact('loan', 'disbursement'));
}
```

---

#### `store(Request $request, LoanDetail $loan)` — `POST /loans/{loan}/disbursement`

**Permission**: `manage_loan_stages`

**Validation**:
```php
$validated = $request->validate([
    'disbursement_type' => 'required|in:fund_transfer,cheque',
    'disbursement_date' => 'nullable|date',
    'amount_disbursed' => 'nullable|numeric|min:0|max:100000000000',
    'bank_account_number' => 'nullable|string|max:50',
    'ifsc_code' => 'nullable|string|max:20',
    'cheque_number' => 'nullable|string|max:50',
    'cheque_date' => 'nullable|date',
    'is_otc' => 'boolean',
    'otc_branch' => 'nullable|string|max:255',
    'reference_number' => 'nullable|string|max:100',
    'notes' => 'nullable|string|max:5000',
]);
```

```php
public function store(Request $request, LoanDetail $loan)
{
    $validated = $request->validate([...]);
    $validated['is_otc'] = $request->boolean('is_otc');

    $disbursement = app(DisbursementService::class)->processDisbursement($loan, $validated);

    if ($disbursement->needsOtcClearance()) {
        return redirect()->route('loans.disbursement', $loan)
            ->with('info', 'Disbursement saved. OTC clearance pending.');
    }

    return redirect()->route('loans.show', $loan)
        ->with('success', 'Loan disbursed and completed successfully!');
}
```

---

#### `clearOtc(LoanDetail $loan)` — `POST /loans/{loan}/disbursement/clear-otc`

**Permission**: `manage_loan_stages`

```php
public function clearOtc(LoanDetail $loan)
{
    $disbursement = $loan->disbursement;
    abort_unless($disbursement && $disbursement->needsOtcClearance(), 404);

    app(DisbursementService::class)->clearOtc($disbursement);

    return redirect()->route('loans.show', $loan)
        ->with('success', 'OTC cleared. Loan completed!');
}
```

---

## Controller: LoanStageController — Stage Notes Endpoint

Add to `LoanStageController`:

#### `saveNotes(Request $request, LoanDetail $loan, string $stageKey)` — `POST /loans/{loan}/stages/{stageKey}/notes`

**Permission**: `manage_loan_stages`

```php
public function saveNotes(Request $request, LoanDetail $loan, string $stageKey): JsonResponse
{
    $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->firstOrFail();

    $validated = $request->validate([
        'notes_data' => 'required|array',
    ]);

    $assignment->mergeNotesData($validated['notes_data']);

    return response()->json(['success' => true]);
}
```

---

## Routes

```php
// Disbursement
Route::middleware(['auth', 'active', 'permission:manage_loan_stages'])->group(function () {
    Route::get('/loans/{loan}/disbursement', [LoanDisbursementController::class, 'show'])
        ->name('loans.disbursement');
    Route::post('/loans/{loan}/disbursement', [LoanDisbursementController::class, 'store'])
        ->name('loans.disbursement.store');
    Route::post('/loans/{loan}/disbursement/clear-otc', [LoanDisbursementController::class, 'clearOtc'])
        ->name('loans.disbursement.clear-otc');
});

// Stage notes (for stages 5-9 forms)
Route::middleware(['auth', 'active', 'permission:manage_loan_stages'])->group(function () {
    Route::post('/loans/{loan}/stages/{stageKey}/notes', [LoanStageController::class, 'saveNotes'])
        ->name('loans.stages.notes');
});
```

---

## Views

### Stage-Specific Partials (stages 5-9)

Each stage has a partial included within the stage card in `loans/stages.blade.php`:

```blade
{{-- In stage-card.blade.php, after the actions section: --}}
@if(in_array($assignment->stage_key, ['rate_pf', 'sanction', 'docket', 'kfs', 'esign']))
    @include('loans.partials.stage-' . str_replace('_', '-', $assignment->stage_key), [
        'assignment' => $assignment,
        'loan' => $loan,
    ])
@endif
```

#### `resources/views/loans/partials/stage-cibil-check.blade.php`

**Optional stage** — only shown if enabled for the product via product_stages.

```
CIBIL Score: [number input, 300-900, required]
Remarks: [textarea]
[Save Details]
```

**Validation**: Score must be integer between 300 and 900.
**Stage notes JSON keys**: `cibil_score`, `stageRemarks`

---

#### `resources/views/loans/partials/stage-rate-pf.blade.php`

```
Interest Rate: [decimal input] %  (required)
Processing Fee: [decimal input] %  (required)
Admin Charges: [₹ currency input]  (required)
Remarks: [textarea]
[Save Details]
```

**Stage notes JSON keys**: `interest_rate`, `processing_fee`, `admin_charges`, `stageRemarks`

#### `resources/views/loans/partials/stage-sanction.blade.php`

```
Sanction Letter #: [input]
Sanction Date: [datepicker]
Sanctioned Amount: [₹ input]
Sanctioned Rate: [input] %
Validity: [input]
Conditions: [textarea]
[Save Details]
```

#### `resources/views/loans/partials/stage-docket.blade.php`

```
Docket Number: [input]
Login Date: [datepicker]
Documents Submitted: [textarea]
Submitted To: [input]
Acknowledgement #: [input]
[Save Details]
```

#### `resources/views/loans/partials/stage-kfs.blade.php`

```
KFS Generated: [checkbox]
KFS Date: [datepicker]
KFS Reference: [input]
Customer Acknowledged: [checkbox]
Acknowledgement Date: [datepicker]
[Save Details]
```

#### `resources/views/loans/partials/stage-esign.blade.php`

```
ECS Reference: [input, required]
E-Sign Status: [dropdown, required]
  - Completed
  - Pending Signature
  - Partially Signed
Remarks: [textarea]
[Save Details]
```

**Stage notes JSON keys**: `ecs_reference`, `esign_status`, `stageRemarks`

**Completion rule**: Stage can only be completed when `esign_status = 'completed'`. If status is 'pending' or 'partial', saving notes is allowed but the Complete button is disabled with tooltip "E-Sign must be completed before this stage can finish".

**JS pattern**: Each partial submits its form data via AJAX POST to `/loans/{id}/stages/{key}/notes`:
```javascript
$('.shf-stage-notes-form').on('submit', function(e) {
    e.preventDefault();
    const $form = $(this);
    const url = $form.data('notes-url');
    const formData = {};
    $form.serializeArray().forEach(item => { formData[item.name] = item.value; });

    $.post(url, { _token: csrfToken, notes_data: formData })
        .done(function(response) {
            showToast('Details saved', 'success');
        });
});
```

---

### `resources/views/loans/disbursement.blade.php`

**Extends**: `layouts.app`

**Layout**:
```
┌──────────────────────────────────────────────────────┐
│ Disbursement — Loan #SHF-202604-0001                 │
│ ← Back to Stages                                      │
├──────────────────────────────────────────────────────┤
│ Step 1: Disbursement Method                           │
│   ○ Fund Transfer / ફંડ ટ્રાન્સફર                    │
│   ○ Cheque / ચેક                                     │
├──────────────────────────────────────────────────────┤
│ [If Fund Transfer]:                                   │
│   Account Number: [input]                             │
│   IFSC Code: [input]                                  │
│   Amount: [₹ input]                                   │
│   Date: [datepicker]                                  │
│   Reference/UTR: [input]                              │
│   Notes: [textarea]                                   │
│   [Process Disbursement] → LOAN COMPLETED             │
├──────────────────────────────────────────────────────┤
│ [If Cheque]:                                          │
│   Cheque Number: [input]                              │
│   Cheque Date: [datepicker]                           │
│   Amount: [₹ input]                                   │
│                                                        │
│   ☐ OTC (Over The Counter)                           │
│   [If OTC checked]:                                   │
│     OTC Branch: [input]                               │
│                                                        │
│   Reference: [input]                                  │
│   Notes: [textarea]                                   │
│   [Process Disbursement]                              │
│     → If no OTC → LOAN COMPLETED                     │
│     → If OTC → Saved, pending OTC clearance          │
├──────────────────────────────────────────────────────┤
│ [If OTC Pending]:                                     │
│   OTC Status: Pending clearance at {branch}           │
│   [Clear OTC] button → LOAN COMPLETED                │
└──────────────────────────────────────────────────────┘
```

**JS behavior**:
```javascript
// Show/hide fields based on disbursement type
$('input[name="disbursement_type"]').on('change', function() {
    const type = $(this).val();
    $('.shf-fund-transfer-fields').toggle(type === 'fund_transfer');
    $('.shf-cheque-fields').toggle(type === 'cheque');
});

// Show/hide OTC fields
$('input[name="is_otc"]').on('change', function() {
    $('.shf-otc-fields').toggle($(this).is(':checked'));
});
```

---

## No New Permissions

Uses existing `manage_loan_stages` permission.

---

## Verification

```bash
php artisan migrate    # disbursement_details table
php artisan serve

# Test flow:
# 1. Advance loan through stages 1-4
# 2. At each stage 5-9: fill stage-specific form, save notes, complete stage
# 3. Verify notes JSON stored correctly in stage_assignments.notes
# 4. At stage 10: choose Fund Transfer → fill details → loan completed
# 5. Create another loan → At stage 10: choose Cheque (no OTC) → loan completed
# 6. Create another loan → Choose Cheque + OTC → saved as pending
# 7. Click "Clear OTC" → loan completed
# 8. Verify loan status changes to 'completed' after disbursement
```

---

## Files Created/Modified

| Action | File |
|--------|------|
| Create | `database/migrations/xxxx_create_disbursement_details_table.php` |
| Create | `app/Models/DisbursementDetail.php` |
| Create | `app/Services/DisbursementService.php` |
| Create | `app/Http/Controllers/LoanDisbursementController.php` |
| Create | `resources/views/loans/disbursement.blade.php` |
| Create | `resources/views/loans/partials/stage-rate-pf.blade.php` |
| Create | `resources/views/loans/partials/stage-sanction.blade.php` |
| Create | `resources/views/loans/partials/stage-docket.blade.php` |
| Create | `resources/views/loans/partials/stage-kfs.blade.php` |
| Create | `resources/views/loans/partials/stage-esign.blade.php` |
| Modify | `app/Models/LoanDetail.php` (add disbursement relationship) |
| Modify | `app/Models/StageAssignment.php` (add notes JSON helpers) |
| Modify | `app/Http/Controllers/LoanStageController.php` (add saveNotes action) |
| Modify | `resources/views/loans/stages.blade.php` (include stage partials) |
| Modify | `routes/web.php` (add disbursement + notes routes) |
