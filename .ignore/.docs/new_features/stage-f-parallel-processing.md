# Stage F: Parallel Processing (Stage 4)

## Overview

Implements Stage 4's parallel processing — 4 independent sub-stages that run simultaneously. All 4 must complete before the loan advances to Stage 5. Also adds the valuation details table for the Technical Valuation sub-stage.

## Dependencies

- Stage E (stage_assignments, LoanStageService workflow engine)

---

## Parallel Processing Logic

### How It Works

When a loan reaches `current_stage = 'parallel_processing'`:

1. The parent stage `parallel_processing` is set to `in_progress`
2. Its 4 sub-stages become independently actionable:
   - `app_number` — Application Number
   - `bsm_osv` — BSM/OSV Approval
   - `legal_verification` — Legal Verification
   - `technical_valuation` — Technical Valuation
3. Each sub-stage can be started, assigned, completed, or skipped independently
4. No ordering between sub-stages — they are truly parallel
5. When ALL 4 sub-stages reach a terminal state (completed or skipped), the parent `parallel_processing` auto-completes and `current_stage` advances to `rate_pf`

### Sub-Stage Details

| Sub-Stage Key | Name | Typical Assignee Role | Description |
|---------------|------|----------------------|-------------|
| `app_number` | Application Number | loan_advisor | Enter bank application number for the loan |
| `bsm_osv` | BSM/OSV Approval | bank_employee | Bank site and office verification/approval |
| `legal_verification` | Legal Verification | bank_employee | Legal document verification by bank |
| `technical_valuation` | Technical Valuation | office_employee | Property/asset valuation with detailed data entry |

---

## Migration: `create_valuation_details_table`

**File**: `database/migrations/xxxx_xx_xx_create_valuation_details_table.php`

**Table**: `valuation_details`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_id | FK → loan_details | no | | cascade on delete |
| valuation_type | string | no | 'property' | values: property, vehicle, business |
| property_address | text | yes | null | full address of property/asset |
| property_type | string | yes | null | e.g., "Residential", "Commercial", "Industrial", "Land" |
| property_area | string | yes | null | e.g., "1200 sq ft", "500 sq yards" |
| market_value | unsignedBigInteger | yes | null | estimated market value in INR |
| government_value | unsignedBigInteger | yes | null | government/circle rate value in INR |
| valuation_date | date | yes | null | date of valuation |
| valuator_name | string | yes | null | name of the valuator/surveyor |
| valuator_report_number | string | yes | null | report reference number |
| notes | text | yes | null | additional valuation notes |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: index on `loan_id`

**Note**: No unique constraint on loan_id — a loan may have multiple valuations (e.g., one for property, one for vehicle).

---

## Model: ValuationDetail

**File**: `app/Models/ValuationDetail.php`

**Table**: `valuation_details`

**Fillable**: `loan_id`, `valuation_type`, `property_address`, `property_type`, `property_area`, `market_value`, `government_value`, `valuation_date`, `valuator_name`, `valuator_report_number`, `notes`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `market_value` | integer |
| `government_value` | integer |
| `valuation_date` | date |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |

**Accessors**:
| Accessor | Returns | Description |
|----------|---------|-------------|
| `getFormattedMarketValueAttribute()` | string | `₹ X,XX,XXX` format |
| `getFormattedGovernmentValueAttribute()` | string | `₹ X,XX,XXX` format |

**Valuation Type Constants**:
```php
const TYPE_PROPERTY = 'property';
const TYPE_VEHICLE = 'vehicle';
const TYPE_BUSINESS = 'business';

const TYPES = [
    self::TYPE_PROPERTY => 'Property / મિલકત',
    self::TYPE_VEHICLE => 'Vehicle / વાહન',
    self::TYPE_BUSINESS => 'Business / વ્યવસાય',
];

const PROPERTY_TYPES = [
    'residential' => 'Residential / રહેણાંક',
    'commercial' => 'Commercial / વ્યાપારી',
    'industrial' => 'Industrial / ઔદ્યોગિક',
    'land' => 'Land / જમીન',
    'mixed' => 'Mixed Use / મિશ્ર ઉપયોગ',
];
```

---

### LoanDetail Model Additions

**Add Relationship**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `valuationDetails()` | HasMany | ValuationDetail | `loan_id` |

---

## Service: LoanStageService Extensions

### `checkParallelCompletion(LoanDetail $loan): bool`

**Purpose**: Called after any sub-stage status change. Checks if all 4 parallel sub-stages are done.

```php
public function checkParallelCompletion(LoanDetail $loan): bool
{
    $subStages = $loan->stageAssignments()
        ->subStagesOf('parallel_processing')
        ->get();

    $allDone = $subStages->every(fn($sa) => in_array($sa->status, ['completed', 'skipped']));

    if ($allDone) {
        // Auto-complete the parent parallel_processing stage
        $parent = $loan->stageAssignments()
            ->where('stage_key', 'parallel_processing')
            ->first();

        if ($parent && $parent->status !== 'completed') {
            $parent->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => auth()->id(),
            ]);

            // Advance to next stage
            $nextKey = $this->getNextStage('parallel_processing'); // → 'rate_pf'
            $loan->update(['current_stage' => $nextKey]);

            ActivityLog::log('parallel_stages_completed', $loan, [
                'loan_number' => $loan->loan_number,
                'advanced_to' => $nextKey,
            ]);
        }

        $this->recalculateProgress($loan);
        return true;
    }

    return false;
}
```

### `getParallelSubStages(LoanDetail $loan): Collection`

```php
public function getParallelSubStages(LoanDetail $loan): Collection
{
    return $loan->stageAssignments()
        ->subStagesOf('parallel_processing')
        ->with(['stage', 'assignee'])
        ->get();
}
```

### Update `handleStageCompletion` (from Stage E)

The existing method already handles this:
```php
protected function handleStageCompletion(LoanDetail $loan, string $completedStageKey): void
{
    $assignment = $loan->stageAssignments()->where('stage_key', $completedStageKey)->first();

    // If this is a parallel sub-stage, check parallel completion
    if ($assignment && ($assignment->is_parallel_stage || $assignment->parent_stage_key !== null)) {
        $this->checkParallelCompletion($loan);
        return;
    }

    // For sequential stages, advance to next
    $nextKey = $this->getNextStage($completedStageKey);
    if ($nextKey) {
        $loan->update(['current_stage' => $nextKey]);
    }

    if ($completedStageKey === 'disbursement') {
        $loan->update(['status' => LoanDetail::STATUS_COMPLETED]);
    }
}
```

### Update `canStartStage` — sub-stages

Sub-stages of `parallel_processing` can start whenever `current_stage` is `parallel_processing`:
```php
// In canStartStage(), the existing logic handles this:
if ($stage->parent_stage_key) {
    return $loan->current_stage === $stage->parent_stage_key
        || $loan->current_stage === 'parallel_processing';
}
```

---

## Controller: LoanValuationController

**File**: `app/Http/Controllers/LoanValuationController.php`

### Constructor

```php
public function __construct() {}
```

### Actions

#### `show(LoanDetail $loan)` — `GET /loans/{loan}/valuation`

**Permission**: `manage_loan_stages`

```php
public function show(LoanDetail $loan)
{
    $valuations = $loan->valuationDetails;

    return view('loans.valuation', compact('loan', 'valuations'));
}
```

---

#### `store(Request $request, LoanDetail $loan)` — `POST /loans/{loan}/valuation`

**Permission**: `manage_loan_stages`

**Validation**:
```php
$validated = $request->validate([
    'valuation_type' => 'required|in:property,vehicle,business',
    'property_address' => 'nullable|string|max:1000',
    'property_type' => 'nullable|string|max:100',
    'property_area' => 'nullable|string|max:100',
    'market_value' => 'nullable|numeric|min:0|max:100000000000',
    'government_value' => 'nullable|numeric|min:0|max:100000000000',
    'valuation_date' => 'nullable|date',
    'valuator_name' => 'nullable|string|max:255',
    'valuator_report_number' => 'nullable|string|max:100',
    'notes' => 'nullable|string|max:5000',
]);
```

**Logic**:
```php
public function store(Request $request, LoanDetail $loan)
{
    $validated = $request->validate([...]);

    $valuation = $loan->valuationDetails()->updateOrCreate(
        ['loan_id' => $loan->id, 'valuation_type' => $validated['valuation_type']],
        $validated,
    );

    ActivityLog::log('save_valuation', $valuation, [
        'loan_number' => $loan->loan_number,
        'valuation_type' => $validated['valuation_type'],
    ]);

    return redirect()->route('loans.stages', $loan)
        ->with('success', 'Valuation details saved');
}
```

---

## Routes

```php
// Valuation
Route::middleware(['auth', 'active', 'permission:manage_loan_stages'])->group(function () {
    Route::get('/loans/{loan}/valuation', [LoanValuationController::class, 'show'])
        ->name('loans.valuation');
    Route::post('/loans/{loan}/valuation', [LoanValuationController::class, 'store'])
        ->name('loans.valuation.store');
});
```

---

## Views

### Parallel Stage 4 Rendering in `loans/stages.blade.php`

When rendering Stage 4, display the 4 sub-stages in a 2x2 grid:

```blade
@if($assignment->stage_key === 'parallel_processing')
    <div class="card-body">
        <p class="text-muted mb-3">All 4 tracks must complete before advancing to Stage 5.</p>
        <div class="row">
            @foreach($subStages->where('parent_stage_key', 'parallel_processing') as $sub)
                <div class="col-md-6 mb-3">
                    @include('loans.partials.stage-card', [
                        'assignment' => $sub,
                        'loan' => $loan,
                        'assignableUsers' => $assignableUsers,
                        'isSubStage' => true,
                    ])
                </div>
            @endforeach
        </div>

        {{-- Technical Valuation link --}}
        @if($loan->current_stage === 'parallel_processing')
            <a href="{{ route('loans.valuation', $loan) }}" class="btn btn-sm btn-outline-primary mt-2">
                <i class="bi bi-building"></i> Valuation Details
            </a>
        @endif
    </div>
@endif
```

### `resources/views/loans/valuation.blade.php`

**Extends**: `layouts.app`

**Layout**:
```
┌──────────────────────────────────────────────────┐
│ Valuation Details — Loan #SHF-202604-0001        │
│ ← Back to Stages                                  │
├──────────────────────────────────────────────────┤
│ Valuation Type: [Property ▼ | Vehicle | Business]│
├──────────────────────────────────────────────────┤
│ Property Details (shown when type = property):    │
│   Address: [textarea]                             │
│   Property Type: [Residential ▼]                 │
│   Area: [input] (e.g., 1200 sq ft)              │
│   Market Value: [₹ input]                        │
│   Government Value: [₹ input]                    │
├──────────────────────────────────────────────────┤
│ Valuator Details:                                 │
│   Date: [datepicker]                              │
│   Valuator Name: [input]                          │
│   Report Number: [input]                          │
│   Notes: [textarea]                               │
├──────────────────────────────────────────────────┤
│ [Save Valuation]                                  │
└──────────────────────────────────────────────────┘
```

**JS behavior**: Valuation type radio changes which fields are shown (property shows address/type/area, vehicle shows different fields, etc.)

---

## No New Permissions

Uses existing `manage_loan_stages` permission.

---

## Verification

```bash
php artisan migrate    # valuation_details table
php artisan serve

# Test flow:
# 1. Advance a loan to stage 4 (complete stages 1-3)
# 2. View stages → see 4 sub-stage cards in 2x2 grid
# 3. Start sub-stage "app_number" → status changes to in_progress
# 4. Complete "app_number" → marked done, but parent still in progress
# 5. Complete all 4 sub-stages → parent auto-completes, current_stage advances to "rate_pf"
# 6. Skip one sub-stage → counts as done for parallel completion
# 7. Fill valuation details → saved to valuation_details table
# 8. Progress bar reflects parallel completion correctly
```

---

## Files Created/Modified

| Action | File |
|--------|------|
| Create | `database/migrations/xxxx_create_valuation_details_table.php` |
| Create | `app/Models/ValuationDetail.php` |
| Create | `app/Http/Controllers/LoanValuationController.php` |
| Create | `resources/views/loans/valuation.blade.php` |
| Modify | `app/Models/LoanDetail.php` (add valuationDetails relationship) |
| Modify | `app/Services/LoanStageService.php` (add parallel methods) |
| Modify | `resources/views/loans/stages.blade.php` (parallel grid rendering) |
| Modify | `routes/web.php` (add valuation routes) |
