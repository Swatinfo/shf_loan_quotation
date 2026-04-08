# Stage B: Quotation-to-Loan Conversion Bridge

## Overview

Creates the `loan_details` table and the conversion mechanism that turns a quotation into a trackable loan task. This is the critical bridge between the existing quotation system and the new workflow system.

## Dependencies

- Stage A (banks, branches, products, stages, user modifications)

---

## Migrations

### Migration 1: `create_loan_details_table`

**File**: `database/migrations/xxxx_xx_xx_create_loan_details_table.php`

**Table**: `loan_details`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_number | string | no | | unique, format: `SHF-YYYYMM-XXXX` |
| quotation_id | FK → quotations | yes | null | null if created directly (not from quotation) |
| branch_id | FK → branches | yes | null | set null on delete |
| bank_id | FK → banks | yes | null | set null on delete |
| product_id | FK → products | yes | null | set null on delete |
| customer_name | string | no | | from quotation or manual entry |
| customer_type | string | no | | proprietor, partnership_llp, pvt_ltd, salaried |
| customer_phone | string(20) | yes | null | |
| customer_email | string | yes | null | |
| loan_amount | unsignedBigInteger | no | | in INR |
| status | string | no | 'active' | values: active, completed, rejected, cancelled, on_hold |
| current_stage | string | no | 'inquiry' | refs stages.stage_key |
| bank_name | string | yes | null | denormalized from quotation for display (bank_id may be null for legacy) |
| roi_min | decimal(5,2) | yes | null | from selected quotation bank |
| roi_max | decimal(5,2) | yes | null | from selected quotation bank |
| total_charges | string | yes | null | from selected quotation bank |
| application_number | string | yes | null | bank application number (entered at Stage 4.1) |
| assigned_bank_employee | FK → users | yes | null | set null on delete — bank employee assigned at application number entry |
| due_date | date | yes | null | default: 7 days from creation |
| rejected_at | timestamp | yes | null | when loan was rejected |
| rejected_by | FK → users | yes | null | set null on delete |
| rejected_stage | string | yes | null | which stage_key caused rejection |
| rejection_reason | text | yes | null | required when rejecting |
| created_by | FK → users | no | | cascade on delete |
| assigned_advisor | FK → users | yes | null | set null on delete — primary loan advisor |
| notes | text | yes | null | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**:
- unique on `loan_number`
- index on `quotation_id`
- index on `branch_id`
- index on `bank_id`
- index on `status`
- index on `current_stage`
- index on `created_by`
- index on `assigned_advisor`
- index on `customer_type`

**Validation constraints** (enforced in service, not DB):
- `status` must be one of: `active`, `completed`, `rejected`, `cancelled`, `on_hold`
- `customer_type` must be one of: `proprietor`, `partnership_llp`, `pvt_ltd`, `salaried`
- `current_stage` must reference a valid `stages.stage_key`
- `loan_amount` > 0 and <= 1,000,000,000,000

---

### Migration 2: `add_loan_id_to_quotations_table`

**File**: `database/migrations/xxxx_xx_xx_add_loan_id_to_quotations_table.php`

**Adds to `quotations` table**:

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| loan_id | FK → loan_details | yes | null | set null on delete — back-reference to created loan |

**Purpose**: Allows quotation to know it has been converted. Bidirectional link:
- `loan_details.quotation_id` → which quotation was the source
- `quotations.loan_id` → which loan was created from this quotation

---

## Models

### LoanDetail

**File**: `app/Models/LoanDetail.php`

**Table**: `loan_details`

**Fillable**: `loan_number`, `quotation_id`, `branch_id`, `bank_id`, `product_id`, `customer_name`, `customer_type`, `customer_phone`, `customer_email`, `loan_amount`, `status`, `current_stage`, `bank_name`, `roi_min`, `roi_max`, `total_charges`, `application_number`, `due_date`, `rejected_at`, `rejected_by`, `rejected_stage`, `rejection_reason`, `created_by`, `assigned_advisor`, `notes`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `loan_amount` | integer |
| `roi_min` | decimal:2 |
| `roi_max` | decimal:2 |
| `due_date` | date |
| `rejected_at` | datetime |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `quotation()` | BelongsTo | Quotation | `quotation_id` |
| `branch()` | BelongsTo | Branch | `branch_id` |
| `bank()` | BelongsTo | Bank | `bank_id` |
| `product()` | BelongsTo | Product | `product_id` |
| `creator()` | BelongsTo | User | `created_by` |
| `advisor()` | BelongsTo | User | `assigned_advisor` |
| `stageAssignments()` | HasMany | StageAssignment | `loan_id` |
| `documents()` | HasMany | LoanDocument | `loan_id` |
| `progress()` | HasOne | LoanProgress | `loan_id` |
| `remarks()` | HasMany | Remark | `loan_id` |
| `valuationDetails()` | HasMany | ValuationDetail | `loan_id` |
| `disbursement()` | HasOne | DisbursementDetail | `loan_id` |

*Note: Relationships to models from later stages (StageAssignment, LoanDocument, etc.) are added when those stages are implemented.*

**Accessors**:
| Accessor | Returns | Description |
|----------|---------|-------------|
| `getFormattedAmountAttribute()` | string | `₹ X,XX,XXX` formatted (Indian comma system) |
| `getStatusLabelAttribute()` | string | Capitalized status with color mapping |
| `getCurrentStageNameAttribute()` | string | Looks up stage_name_en from stages table |

**Static Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `generateLoanNumber()` | string | Auto-generates `SHF-YYYYMM-XXXX` |

**Loan Number Generation Logic**:
```php
public static function generateLoanNumber(): string
{
    $prefix = 'SHF-' . now()->format('Ym') . '-';
    $lastLoan = static::where('loan_number', 'like', $prefix . '%')
        ->orderByDesc('loan_number')
        ->first();

    if ($lastLoan) {
        $lastNum = (int) substr($lastLoan->loan_number, -4);
        $nextNum = $lastNum + 1;
    } else {
        $nextNum = 1;
    }

    return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    // Result: SHF-202604-0001, SHF-202604-0002, etc.
}
```

**Scopes**:
| Scope | Query | Description |
|-------|-------|-------------|
| `scopeActive($q)` | `$q->where('status', 'active')` | Only active loans |
| `scopeVisibleTo($q, User $user)` | See below | Permission-based visibility |

**Visibility Scope** (full implementation in Stage J, stub here):
```php
public function scopeVisibleTo($query, User $user): void
{
    if ($user->hasPermission('view_all_loans')) {
        return; // No restriction
    }

    $query->where(function ($q) use ($user) {
        $q->where('created_by', $user->id)
          ->orWhere('assigned_advisor', $user->id);
        // Extended in Stage E to include stage assignments
    });
}
```

**Status Constants**:
```php
const STATUS_ACTIVE = 'active';
const STATUS_COMPLETED = 'completed';
const STATUS_REJECTED = 'rejected';
const STATUS_CANCELLED = 'cancelled';
const STATUS_ON_HOLD = 'on_hold';

const STATUSES = [
    self::STATUS_ACTIVE,
    self::STATUS_COMPLETED,
    self::STATUS_REJECTED,
    self::STATUS_CANCELLED,
    self::STATUS_ON_HOLD,
];

const STATUS_LABELS = [
    'active' => ['label' => 'Active', 'color' => 'primary'],
    'completed' => ['label' => 'Completed', 'color' => 'success'],
    'rejected' => ['label' => 'Rejected', 'color' => 'danger'],
    'cancelled' => ['label' => 'Cancelled', 'color' => 'secondary'],
    'on_hold' => ['label' => 'On Hold', 'color' => 'warning'],
];
```

---

### Quotation Model Modifications

**File**: `app/Models/Quotation.php` (existing — modify)

**Add to fillable**: `loan_id`

**New Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |

**New Accessor**:
| Accessor | Returns | Description |
|----------|---------|-------------|
| `getIsConvertedAttribute()` | bool | `return $this->loan_id !== null` |

---

## Service: LoanConversionService

**File**: `app/Services/LoanConversionService.php`

### Constructor

```php
public function __construct(
    private LoanStageService $stageService,
) {}
```

### Methods

#### `convertFromQuotation(Quotation $quotation, int $bankIndex, array $extra = []): LoanDetail`

**Purpose**: Converts a quotation into a loan task, pre-filling data from the quotation.

**Parameters**:
| Param | Type | Description |
|-------|------|-------------|
| `$quotation` | Quotation | Must be loaded with `banks` and `documents` relations |
| `$bankIndex` | int | Index of the selected bank in `$quotation->banks` collection (0-based) |
| `$extra` | array | Optional overrides: `branch_id`, `product_id`, `customer_phone`, `customer_email`, `assigned_advisor`, `notes` |

**Flow** (wrapped in DB::transaction):

1. **Validate** quotation is not already converted:
   ```php
   if ($quotation->loan_id !== null) {
       throw new \RuntimeException('Quotation already converted to loan #' . $quotation->loan->loan_number);
   }
   ```

2. **Get selected bank** from quotation:
   ```php
   $quotationBank = $quotation->banks[$bankIndex]
       ?? throw new \RuntimeException('Invalid bank index');
   ```

3. **Try to match bank_name to banks table**:
   ```php
   $bank = Bank::where('name', $quotationBank->bank_name)->first();
   ```

4. **Create LoanDetail**:
   ```php
   $loan = LoanDetail::create([
       'loan_number' => LoanDetail::generateLoanNumber(),
       'quotation_id' => $quotation->id,
       'branch_id' => $extra['branch_id'] ?? null,
       'bank_id' => $bank?->id,
       'product_id' => $extra['product_id'] ?? null,
       'customer_name' => $quotation->customer_name,
       'customer_type' => $quotation->customer_type,
       'customer_phone' => $extra['customer_phone'] ?? null,
       'customer_email' => $extra['customer_email'] ?? null,
       'loan_amount' => $quotation->loan_amount,
       'status' => LoanDetail::STATUS_ACTIVE,
       'current_stage' => 'document_collection', // Skip stages 1-2
       'bank_name' => $quotationBank->bank_name,
       'roi_min' => $quotationBank->roi_min,
       'roi_max' => $quotationBank->roi_max,
       'total_charges' => $quotationBank->total_charges,
       'created_by' => auth()->id(),
       'assigned_advisor' => $extra['assigned_advisor'] ?? auth()->id(),
       'notes' => $extra['notes'] ?? $quotation->additional_notes,
   ]);
   ```

5. **Copy documents** from quotation to loan (uses LoanDocumentService from Stage D):
   ```php
   // This is deferred to Stage D implementation.
   // In Stage B, just creates the loan without documents.
   // Stage D adds: $this->documentService->populateFromQuotation($loan, $quotation);
   ```

6. **Set back-reference on quotation**:
   ```php
   $quotation->update(['loan_id' => $loan->id]);
   ```

7. **Initialize stages** (deferred to Stage E):
   ```php
   // Stage E adds: $this->stageService->initializeStages($loan);
   // And auto-completes stages 1-2: inquiry + document_selection
   ```

8. **Log activity**:
   ```php
   ActivityLog::log('convert_quotation_to_loan', $loan, [
       'quotation_id' => $quotation->id,
       'loan_number' => $loan->loan_number,
       'customer_name' => $loan->customer_name,
       'loan_amount' => $loan->loan_amount,
       'bank_name' => $loan->bank_name,
   ]);
   ```

9. **Return** the created `LoanDetail`.

**Returns**: `LoanDetail` (the newly created loan)

**Throws**: `\RuntimeException` if quotation already converted or invalid bank index

---

#### `createDirectLoan(array $data): LoanDetail`

**Purpose**: Creates a loan directly without a quotation.

**Parameters**:
| Key | Type | Required | Description |
|-----|------|----------|-------------|
| `customer_name` | string | yes | |
| `customer_type` | string | yes | proprietor, partnership_llp, pvt_ltd, salaried |
| `loan_amount` | int | yes | > 0 |
| `bank_id` | int | no | FK to banks table |
| `product_id` | int | no | FK to products table |
| `branch_id` | int | no | FK to branches table |
| `customer_phone` | string | no | |
| `customer_email` | string | no | |
| `assigned_advisor` | int | no | FK to users table |
| `notes` | string | no | |

**Flow** (wrapped in DB::transaction):

1. **Validate** required fields (customer_name, customer_type, loan_amount)
2. **Look up bank name** if bank_id provided:
   ```php
   $bankName = $data['bank_id'] ? Bank::find($data['bank_id'])?->name : null;
   ```
3. **Create LoanDetail** with `current_stage = 'inquiry'` (starts from beginning)
4. **Populate documents** from defaults (deferred to Stage D)
5. **Initialize stages** (deferred to Stage E)
6. **Log activity**: `create_loan`
7. **Return** `LoanDetail`

---

## Validation Rules (Inline in Controller)

### Convert Form (`POST /quotations/{quotation}/convert`)

```php
$validated = $request->validate([
    'bank_index' => 'required|integer|min:0',
    'branch_id' => 'nullable|exists:branches,id',
    'product_id' => 'nullable|exists:products,id',
    'customer_phone' => 'nullable|string|max:20',
    'customer_email' => 'nullable|email|max:255',
    'assigned_advisor' => 'nullable|exists:users,id',
    'notes' => 'nullable|string|max:5000',
]);
```

---

## Controller: LoanConversionController

**File**: `app/Http/Controllers/LoanConversionController.php`

### Constructor

```php
public function __construct(
    private LoanConversionService $conversionService,
) {}
```

### Actions

#### `showConvertForm(Quotation $quotation)` — `GET /quotations/{quotation}/convert`

**Permission**: `convert_to_loan`

**Authorization**: User must own quotation OR have `view_all_quotations`

**Logic**:
1. Check quotation not already converted → redirect back with error if so
2. Load quotation with `banks` and `documents` relations
3. Load branches (active) for dropdown
4. Load products (active, grouped by bank) for dropdown
5. Load users with task_role for advisor dropdown
6. Return view `quotations.convert`

```php
public function showConvertForm(Quotation $quotation)
{
    if ($quotation->is_converted) {
        return redirect()->route('loans.show', $quotation->loan_id)
            ->with('info', 'This quotation has already been converted to Loan #' . $quotation->loan->loan_number);
    }

    $quotation->load(['banks', 'documents']);
    $branches = Branch::active()->orderBy('name')->get();
    $products = Product::active()->with('bank')->orderBy('name')->get()->groupBy('bank_id');
    $advisors = User::whereNotNull('task_role')->where('is_active', true)->orderBy('name')->get();

    return view('quotations.convert', compact('quotation', 'branches', 'products', 'advisors'));
}
```

#### `convert(Request $request, Quotation $quotation)` — `POST /quotations/{quotation}/convert`

**Permission**: `convert_to_loan`

**Logic**:
1. Validate input (inline)
2. Call `$this->conversionService->convertFromQuotation()`
3. On success: redirect to `loans.show` with success message
4. On failure: redirect back with error

```php
public function convert(Request $request, Quotation $quotation)
{
    $validated = $request->validate([
        'bank_index' => 'required|integer|min:0',
        'branch_id' => 'nullable|exists:branches,id',
        'product_id' => 'nullable|exists:products,id',
        'customer_phone' => 'nullable|string|max:20',
        'customer_email' => 'nullable|email|max:255',
        'assigned_advisor' => 'nullable|exists:users,id',
        'notes' => 'nullable|string|max:5000',
    ]);

    try {
        $quotation->load(['banks', 'documents']);
        $loan = $this->conversionService->convertFromQuotation(
            $quotation,
            (int) $validated['bank_index'],
            $validated,
        );

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Quotation converted to Loan #' . $loan->loan_number);
    } catch (\RuntimeException $e) {
        return redirect()->back()->withInput()->with('error', $e->getMessage());
    }
}
```

---

## Routes

**File**: `routes/web.php` (add to existing)

```php
// Quotation to Loan conversion
Route::middleware(['auth', 'active', 'permission:convert_to_loan'])->group(function () {
    Route::get('/quotations/{quotation}/convert', [LoanConversionController::class, 'showConvertForm'])
        ->name('quotations.convert');
    Route::post('/quotations/{quotation}/convert', [LoanConversionController::class, 'convert'])
        ->name('quotations.convert.store');
});
```

**Note**: Uses existing `auth`, `active`, and `permission:` middleware stack pattern.

---

## Views

### `resources/views/quotations/convert.blade.php`

**Extends**: `layouts.app`

**Sections**: `title`, `content`

**Layout**:
```
┌─────────────────────────────────────────────┐
│ Convert Quotation to Loan Task              │
│ Breadcrumb: Dashboard > Quotation #X > Convert
├─────────────────────────────────────────────┤
│ Section 1: Quotation Summary (read-only)    │
│   Customer: {name} ({type label})           │
│   Loan Amount: ₹ X,XX,XXX                  │
│   Documents: {count} documents              │
│   Banks: {bank names}                       │
├─────────────────────────────────────────────┤
│ Section 2: Select Bank                      │
│   Radio buttons for each quotation bank:    │
│     ○ HDFC Bank (ROI: 8.50% - 9.00%)      │
│     ○ ICICI Bank (ROI: 8.75% - 9.25%)     │
│   Each shows: bank name, ROI range, charges │
├─────────────────────────────────────────────┤
│ Section 3: Additional Details               │
│   Branch: [dropdown - optional]             │
│   Product: [dependent dropdown - optional]  │
│   Customer Phone: [input]                   │
│   Customer Email: [input]                   │
│   Assigned Advisor: [dropdown - optional]   │
│   Notes: [textarea - pre-filled]            │
├─────────────────────────────────────────────┤
│ [Convert to Loan Task] button               │
└─────────────────────────────────────────────┘
```

**CSS classes used**: `shf-section`, `shf-section-header`, `shf-section-body`, `shf-btn-accent`, form-control, form-select (Bootstrap)

**JS behavior**:
- Bank radio selection highlights the selected card
- Product dropdown filters based on selected bank (if bank matches banks table)
- Form submits as standard POST (not AJAX)

---

### Modify `resources/views/quotations/show.blade.php`

**Add to header actions area** (next to Download/Delete buttons):

```blade
@if(!$quotation->is_converted && auth()->user()->hasPermission('convert_to_loan'))
    <a href="{{ route('quotations.convert', $quotation) }}" class="btn shf-btn-accent">
        <i class="bi bi-arrow-right-circle"></i> Convert to Loan
    </a>
@elseif($quotation->is_converted)
    <a href="{{ route('loans.show', $quotation->loan_id) }}" class="btn btn-outline-primary">
        <i class="bi bi-box-arrow-up-right"></i> View Loan #{{ $quotation->loan->loan_number }}
    </a>
@endif
```

---

## Permissions

**Add to `config/permissions.php`** under new group `'Loans'`:

```php
'Loans' => [
    [
        'slug' => 'convert_to_loan',
        'name' => 'Convert to Loan',
        'description' => 'Convert quotation to loan task',
    ],
],
```

**Role defaults**:
| Permission | Admin | Staff |
|------------|-------|-------|
| `convert_to_loan` | yes | yes |

**Run `PermissionSeeder`** after adding to config to create the permission and role_permission rows.

---

## Data Flow: Quotation → Loan Mapping

| Quotation Field | Loan Field | Notes |
|-----------------|------------|-------|
| `quotation.id` | `loan.quotation_id` | Source reference |
| `quotation.customer_name` | `loan.customer_name` | Direct copy |
| `quotation.customer_type` | `loan.customer_type` | Direct copy |
| `quotation.loan_amount` | `loan.loan_amount` | Direct copy |
| `quotation.additional_notes` | `loan.notes` | Overridable in form |
| `quotation_banks[N].bank_name` | `loan.bank_name` | Selected bank |
| `quotation_banks[N].roi_min` | `loan.roi_min` | Selected bank |
| `quotation_banks[N].roi_max` | `loan.roi_max` | Selected bank |
| `quotation_banks[N].total_charges` | `loan.total_charges` | Selected bank |
| *matched* `banks.name` | `loan.bank_id` | FK if bank name matches banks table |
| form input | `loan.branch_id` | User-selected |
| form input | `loan.product_id` | User-selected |
| form input | `loan.customer_phone` | User-entered |
| form input | `loan.customer_email` | User-entered |
| form input | `loan.assigned_advisor` | User-selected |
| `auth()->id()` | `loan.created_by` | Auto from auth |
| - | `loan.current_stage` | Set to `document_collection` (stages 1-2 auto-completed) |
| - | `loan.status` | Set to `active` |
| `quotation_documents[*]` | `loan_documents[*]` | Copied in Stage D |

---

## Verification

```bash
php artisan migrate                              # loan_details table + quotations.loan_id column
php artisan db:seed --class=PermissionSeeder     # convert_to_loan permission
php artisan serve

# Test flow:
# 1. Create a quotation
# 2. View quotation → see "Convert to Loan" button
# 3. Click → see conversion form
# 4. Select bank, fill optional fields, submit
# 5. Redirected to loan show page (loans.show)
# 6. View original quotation → see "View Loan #SHF-..." link
# 7. Attempting to convert again → redirected with info message
```

---

## Files Created/Modified

| Action | File |
|--------|------|
| Create | `database/migrations/xxxx_create_loan_details_table.php` |
| Create | `database/migrations/xxxx_add_loan_id_to_quotations_table.php` |
| Create | `app/Models/LoanDetail.php` |
| Create | `app/Services/LoanConversionService.php` |
| Create | `app/Http/Controllers/LoanConversionController.php` |
| Create | `resources/views/quotations/convert.blade.php` |
| Modify | `app/Models/Quotation.php` (add loan_id, loan(), is_converted) |
| Modify | `resources/views/quotations/show.blade.php` (add Convert/View Loan button) |
| Modify | `config/permissions.php` (add Loans group with convert_to_loan) |
| Modify | `routes/web.php` (add conversion routes) |
