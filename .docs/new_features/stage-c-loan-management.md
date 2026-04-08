# Stage C: Loan Management (CRUD, List, Detail Views)

## Overview

Provides full CRUD for loan tasks — listing with DataTables, direct creation (without quotation), show/detail view, edit, status changes, and deletion. Adds "Loans" to the main navigation.

## Dependencies

- Stage A (banks, branches, products, stages)
- Stage B (loan_details table, LoanDetail model, LoanConversionService)

---

## No New Migrations

Uses `loan_details` table from Stage B.

---

## Controller: LoanController

**File**: `app/Http/Controllers/LoanController.php`

### Constructor

```php
public function __construct(
    private LoanConversionService $conversionService,
    private ConfigService $configService,
) {}
```

### Actions

#### `index(Request $request)` — `GET /loans`

**Permission**: `view_loans`

**Logic**:
1. Calculate stats for dashboard cards:
   ```php
   $baseQuery = LoanDetail::visibleTo(auth()->user());
   $stats = [
       'total' => (clone $baseQuery)->count(),
       'active' => (clone $baseQuery)->where('status', 'active')->count(),
       'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
       'this_month' => (clone $baseQuery)->whereMonth('created_at', now()->month)
                            ->whereYear('created_at', now()->year)->count(),
   ];
   ```
2. Load filter options: banks, branches, customer types, statuses
3. Return view `loans.index` with stats and filter options

---

#### `loanData(Request $request): JsonResponse` — `GET /loans/data`

**Permission**: `view_loans`

**Purpose**: Server-side DataTables AJAX endpoint. Follows exact same pattern as `DashboardController@quotationData`.

**Query Parameters** (from DataTables + custom filters):
| Param | Type | Description |
|-------|------|-------------|
| `draw` | int | DataTables draw counter |
| `start` | int | Offset |
| `length` | int | Limit |
| `search[value]` | string | Global search |
| `order[0][column]` | int | Sort column index |
| `order[0][dir]` | string | asc/desc |
| `status` | string | Filter by status |
| `customer_type` | string | Filter by customer type |
| `bank_id` | int | Filter by bank |
| `branch_id` | int | Filter by branch |
| `date_from` | date | Filter by created_at >= |
| `date_to` | date | Filter by created_at <= |

**Response** (DataTables format):
```json
{
    "draw": 1,
    "recordsTotal": 100,
    "recordsFiltered": 25,
    "data": [
        {
            "id": 1,
            "loan_number": "SHF-202604-0001",
            "customer_name": "Ramesh Patel",
            "customer_type": "proprietor",
            "customer_type_label": "Proprietor / માલિકી",
            "bank_name": "HDFC Bank",
            "loan_amount": 5000000,
            "formatted_amount": "₹ 50,00,000",
            "current_stage": "document_collection",
            "current_stage_name": "Document Collection",
            "status": "active",
            "status_label": "Active",
            "status_color": "primary",
            "created_at": "2026-04-06",
            "created_by_name": "Admin User",
            "advisor_name": "Staff User",
            "actions_html": "<a href='...' class='btn btn-sm'>...</a>"
        }
    ]
}
```

**Search logic**: Searches across `loan_number`, `customer_name`, `bank_name`, `customer_phone`, `customer_email`.

**Visibility**: Uses `LoanDetail::visibleTo(auth()->user())` scope.

---

#### `create()` — `GET /loans/create`

**Permission**: `create_loan`

**Logic**: Load dropdowns (banks, branches, products, customer types, advisors) and return view.

```php
public function create()
{
    $banks = Bank::active()->orderBy('name')->get();
    $branches = Branch::active()->orderBy('name')->get();
    $products = Product::active()->with('bank')->orderBy('name')->get();
    $advisors = User::whereNotNull('task_role')->where('is_active', true)->orderBy('name')->get();

    return view('loans.create', compact('banks', 'branches', 'products', 'advisors'));
}
```

---

#### `store(Request $request)` — `POST /loans`

**Permission**: `create_loan`

**Validation** (inline):
```php
$validated = $request->validate([
    'customer_name' => 'required|string|max:255',
    'customer_type' => 'required|in:proprietor,partnership_llp,pvt_ltd,salaried',
    'loan_amount' => 'required|numeric|min:1|max:1000000000000',
    'bank_id' => 'nullable|exists:banks,id',
    'product_id' => 'nullable|exists:products,id',
    'branch_id' => 'nullable|exists:branches,id',
    'customer_phone' => 'nullable|string|max:20',
    'customer_email' => 'nullable|email|max:255',
    'assigned_advisor' => 'nullable|exists:users,id',
    'notes' => 'nullable|string|max:5000',
]);
```

**Logic**:
1. Call `$this->conversionService->createDirectLoan($validated)`
2. Redirect to `loans.show` with success message

---

#### `show(LoanDetail $loan)` — `GET /loans/{loan}`

**Permission**: `view_loans`

**Authorization**: Must be visible to user (own OR `view_all_loans`)

**Logic**:
```php
public function show(LoanDetail $loan)
{
    $this->authorizeView($loan);

    $loan->load([
        'quotation',
        'branch',
        'bank',
        'product',
        'creator',
        'advisor',
        // These are added in later stages:
        // 'documents', 'stageAssignments', 'progress', 'remarks'
    ]);

    $stages = app(LoanStageService::class)->getOrderedStages();

    return view('loans.show', compact('loan', 'stages'));
}
```

**Authorization helper** (private method):
```php
private function authorizeView(LoanDetail $loan): void
{
    $user = auth()->user();
    if ($user->hasPermission('view_all_loans')) return;
    if ($loan->created_by === $user->id) return;
    if ($loan->assigned_advisor === $user->id) return;
    abort(403);
}
```

---

#### `edit(LoanDetail $loan)` — `GET /loans/{loan}/edit`

**Permission**: `edit_loan`

Same authorization as show. Loads dropdowns + loan data.

---

#### `update(Request $request, LoanDetail $loan)` — `PUT /loans/{loan}`

**Permission**: `edit_loan`

**Validation**: Same as store but all optional.

**Logic**:
1. Update loan with validated data
2. Log activity: `edit_loan` with changed fields
3. Redirect to `loans.show`

---

#### `updateStatus(Request $request, LoanDetail $loan)` — `POST /loans/{loan}/status`

**Permission**: `edit_loan`

**Validation**:
```php
$validated = $request->validate([
    'status' => 'required|in:active,on_hold,cancelled,rejected',
]);
```

**Logic**:
1. Store old status
2. Update loan status
3. Log activity: `change_loan_status` with old_status and new_status
4. Return JSON response (called via AJAX)

**Note**: `completed` status is NOT settable via this endpoint. It's only set by the disbursement process (Stage G).

---

#### `destroy(LoanDetail $loan)` — `DELETE /loans/{loan}`

**Permission**: `delete_loan`

**Logic**:
1. Log activity: `delete_loan` with loan_number, customer_name
2. Delete loan (cascades to all related tables)
3. Return JSON (AJAX) or redirect

---

## Routes

**File**: `routes/web.php` (add to existing)

```php
// Loan management
Route::middleware(['auth', 'active'])->group(function () {
    Route::middleware('permission:view_loans')->group(function () {
        Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
        Route::get('/loans/data', [LoanController::class, 'loanData'])->name('loans.data');
    });

    Route::middleware('permission:create_loan')->group(function () {
        Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
        Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
    });

    Route::middleware('permission:view_loans')->group(function () {
        Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
    });

    Route::middleware('permission:edit_loan')->group(function () {
        Route::get('/loans/{loan}/edit', [LoanController::class, 'edit'])->name('loans.edit');
        Route::put('/loans/{loan}', [LoanController::class, 'update'])->name('loans.update');
        Route::post('/loans/{loan}/status', [LoanController::class, 'updateStatus'])->name('loans.update-status');
    });

    Route::middleware('permission:delete_loan')->group(function () {
        Route::delete('/loans/{loan}', [LoanController::class, 'destroy'])->name('loans.destroy');
    });
});
```

**Route model binding**: `LoanDetail` is bound as `{loan}` in the route model binding (add to `RouteServiceProvider` or use `Route::model`).

---

## Views

### `resources/views/loans/index.blade.php`

**Extends**: `layouts.app`

**Layout**:
```
┌─────────────────────────────────────────────────────┐
│ Loans                            [+ New Loan] button │
├─────────────────────────────────────────────────────┤
│ Stats Cards (row of 4):                             │
│   Total Loans | Active | Completed | This Month    │
├─────────────────────────────────────────────────────┤
│ Filter Bar:                                          │
│   [Status ▼] [Type ▼] [Bank ▼] [Branch ▼]          │
│   [Date From] [Date To] [Search...]                  │
├─────────────────────────────────────────────────────┤
│ DataTable:                                           │
│   Loan # | Customer | Type | Bank | Amount |         │
│   Stage | Status | Created | Actions                 │
│                                                      │
│   Desktop: Full table (d-none d-md-block)           │
│   Mobile: Card layout (d-md-none)                   │
└─────────────────────────────────────────────────────┘
```

**DataTables config** (jQuery):
```javascript
$('#loansTable').DataTable({
    processing: true,
    serverSide: true,
    ajax: {
        url: '{{ route("loans.data") }}',
        data: function(d) {
            d.status = $('#filterStatus').val();
            d.customer_type = $('#filterType').val();
            d.bank_id = $('#filterBank').val();
            d.branch_id = $('#filterBranch').val();
            d.date_from = $('#filterDateFrom').val();
            d.date_to = $('#filterDateTo').val();
        }
    },
    columns: [
        { data: 'loan_number' },
        { data: 'customer_name' },
        { data: 'customer_type_label' },
        { data: 'bank_name' },
        { data: 'formatted_amount', className: 'text-end' },
        { data: 'current_stage_name' },
        { data: 'status_label' },
        { data: 'created_at' },
        { data: 'actions_html', orderable: false, searchable: false },
    ],
    order: [[0, 'desc']],
    pageLength: 25,
});
```

**Mobile card layout**: Same dual-layout pattern as dashboard (desktop table + mobile cards).

---

### `resources/views/loans/create.blade.php`

**Extends**: `layouts.app`

**Layout**:
```
┌──────────────────────────────────────────┐
│ Create New Loan                          │
├──────────────────────────────────────────┤
│ Section 1: Customer Information          │
│   Name: [input - required]              │
│   Type: [dropdown - required]            │
│   Phone: [input]                         │
│   Email: [input]                         │
├──────────────────────────────────────────┤
│ Section 2: Loan Details                  │
│   Amount: [input with ₹ prefix]         │
│   Bank: [dropdown → loads products]     │
│   Product: [dependent dropdown]          │
│   Branch: [dropdown]                     │
├──────────────────────────────────────────┤
│ Section 3: Assignment                    │
│   Advisor: [dropdown of task_role users] │
│   Notes: [textarea]                      │
├──────────────────────────────────────────┤
│ [Create Loan] button                     │
└──────────────────────────────────────────┘
```

**JS behavior**:
- Amount input: Shows Indian formatted preview on blur (same pattern as quotation create)
- Bank dropdown change: Filters product dropdown to show only products of selected bank
- Customer type labels: Bilingual (English / Gujarati) in dropdown options

---

### `resources/views/loans/show.blade.php`

**Extends**: `layouts.app`

**Layout**:
```
┌──────────────────────────────────────────────┐
│ Loan #SHF-202604-0001                        │
│ Status: [Active badge]                        │
│ Actions: [Edit] [Status ▼] [Delete]          │
├──────────────────────────────────────────────┤
│ Section 1: Customer & Loan Info               │
│   Customer: Ramesh Patel                      │
│   Type: Proprietor / માલિકી                  │
│   Phone: +91 99999 99999                      │
│   Amount: ₹ 50,00,000                        │
│   Bank: HDFC Bank (ROI: 8.50% - 9.00%)      │
│   Product: Home Loan                          │
│   Branch: Rajkot Main Office                  │
│   Advisor: Staff User                         │
│   Notes: ...                                  │
├──────────────────────────────────────────────┤
│ Section 2: Stage Progress (placeholder)       │
│   [10-step progress bar - implemented in E]  │
│   Current: Document Collection (Stage 3)      │
├──────────────────────────────────────────────┤
│ Section 3: Quick Links                        │
│   [Documents (X/Y)] [Stages] [Remarks]       │
│   (links implemented in later stages)         │
├──────────────────────────────────────────────┤
│ Section 4: Source Quotation (if converted)    │
│   Quotation #5 — Created 2026-04-05         │
│   [View Quotation] [Download PDF]            │
├──────────────────────────────────────────────┤
│ Section 5: Activity Timeline                  │
│   (Recent activity log entries for this loan) │
└──────────────────────────────────────────────┘
```

**CSS classes**: `shf-section`, `shf-section-header`, `shf-section-body`, Bootstrap cards and badges

**Status dropdown** (for changing status via AJAX):
```html
<div class="dropdown">
    <button class="btn btn-outline-secondary dropdown-toggle btn-sm" data-bs-toggle="dropdown">
        Status
    </button>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="#" data-status="on_hold">Put On Hold</a></li>
        <li><a class="dropdown-item" href="#" data-status="cancelled">Cancel Loan</a></li>
        <li><a class="dropdown-item" href="#" data-status="active">Reactivate</a></li>
    </ul>
</div>
```

---

### `resources/views/loans/edit.blade.php`

Same form layout as `create.blade.php` but pre-filled with loan data. Form submits via `PUT /loans/{loan}`.

---

## Navigation Update

**File**: `resources/views/layouts/navigation.blade.php` (modify)

Add "Loans" nav item between "New Quotation" and "Users":

```blade
@if(auth()->user()->hasPermission('view_loans'))
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('loans.*') ? 'active' : '' }}"
           href="{{ route('loans.index') }}">
            <i class="bi bi-list-task d-lg-none"></i>
            <span>Loans</span>
        </a>
    </li>
@endif
```

**Mobile nav**: Same item appears in off-canvas/collapse menu.

---

## Permissions

**Add to `config/permissions.php`** under `'Loans'` group:

```php
'Loans' => [
    ['slug' => 'convert_to_loan', 'name' => 'Convert to Loan', 'description' => 'Convert quotation to loan task'],
    ['slug' => 'view_loans', 'name' => 'View Loans', 'description' => 'View loan task list'],
    ['slug' => 'view_all_loans', 'name' => 'View All Loans', 'description' => 'View all loans across users/branches'],
    ['slug' => 'create_loan', 'name' => 'Create Loan', 'description' => 'Create loan tasks directly'],
    ['slug' => 'edit_loan', 'name' => 'Edit Loan', 'description' => 'Edit loan details'],
    ['slug' => 'delete_loan', 'name' => 'Delete Loan', 'description' => 'Delete loan tasks'],
],
```

**Role defaults**:
| Permission | Admin | Staff |
|------------|-------|-------|
| `convert_to_loan` | yes | yes |
| `view_loans` | yes | yes |
| `view_all_loans` | yes | no |
| `create_loan` | yes | yes |
| `edit_loan` | yes | no |
| `delete_loan` | yes | no |

---

## Customer Type Labels (Reuse from Quotation)

Bilingual labels used in dropdowns and display:

```php
// In LoanDetail model or shared helper
const CUSTOMER_TYPE_LABELS = [
    'proprietor' => 'Proprietor / માલિકી',
    'partnership_llp' => 'Partnership / LLP / ભાગીદારી',
    'pvt_ltd' => 'Pvt. Ltd. / પ્રા. લિ.',
    'salaried' => 'Salaried / પગારદાર',
];
```

---

## Verification

```bash
php artisan db:seed --class=PermissionSeeder  # New Loans permissions
php artisan serve

# Test flow:
# 1. Navigate to /loans → see empty list with stats
# 2. Click "New Loan" → create form
# 3. Fill form, submit → redirected to loan show page
# 4. Back to list → see loan in table
# 5. Edit loan → update fields
# 6. Change status → AJAX status update
# 7. Delete loan → removed from list
# 8. Non-admin user → sees only own loans
# 9. Admin with view_all_loans → sees all loans
```

---

## Files Created/Modified

| Action | File |
|--------|------|
| Create | `app/Http/Controllers/LoanController.php` |
| Create | `resources/views/loans/index.blade.php` |
| Create | `resources/views/loans/create.blade.php` |
| Create | `resources/views/loans/show.blade.php` |
| Create | `resources/views/loans/edit.blade.php` |
| Modify | `resources/views/layouts/navigation.blade.php` (add Loans nav item) |
| Modify | `config/permissions.php` (add full Loans group) |
| Modify | `routes/web.php` (add loan routes) |
