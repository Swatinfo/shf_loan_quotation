# Stage I: Workflow Configuration (Admin)

## Overview

Allows admins to configure the workflow per bank/product — which stages are enabled, default assignee roles, auto-skip settings. Also provides CRUD for banks, products, and branches through a settings interface.

## Dependencies

- Stage A (banks, branches, products, stages)
- Stage E (stage_assignments, LoanStageService)

---

## Migration: `create_product_stages_table`

**File**: `database/migrations/xxxx_xx_xx_create_product_stages_table.php`

**Table**: `product_stages`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| product_id | FK → products | no | | cascade on delete |
| stage_id | FK → stages | no | | cascade on delete |
| is_enabled | boolean | no | true | whether this stage applies to this product |
| default_assignee_role | string | yes | null | task_role value: branch_manager, loan_advisor, bank_employee, office_employee, legal_advisor |
| auto_skip | boolean | no | false | auto-skip this stage when initializing loan |
| sort_order | integer | yes | null | custom ordering (null = use stage's default order) |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: unique composite on `(product_id, stage_id)`; index on `product_id`; index on `stage_id`

**Purpose**: When a loan is created with a specific `product_id`, the `initializeStages` method consults this table to determine which stages to create, which to auto-skip, and who to pre-assign. If no `product_stages` rows exist for the product, all stages are created with defaults.

---

## Model: ProductStage

**File**: `app/Models/ProductStage.php`

**Table**: `product_stages`

**Fillable**: `product_id`, `stage_id`, `is_enabled`, `default_assignee_role`, `auto_skip`, `sort_order`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_enabled` | boolean |
| `auto_skip` | boolean |
| `sort_order` | integer |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `product()` | BelongsTo | Product | `product_id` |
| `stage()` | BelongsTo | Stage | `stage_id` |

---

### Product Model Additions

**Add Relationship**:
| Method | Type | Related | FK/Pivot |
|--------|------|---------|----------|
| `stages()` | BelongsToMany | Stage | pivot: `product_stages`, withPivot: `is_enabled`, `default_assignee_role`, `auto_skip`, `sort_order` |
| `productStages()` | HasMany | ProductStage | `product_id` |

---

## Service: LoanStageService Enhancement

### Update `initializeStages(LoanDetail $loan): void`

Modified to consult `product_stages` when a product is set:

```php
public function initializeStages(LoanDetail $loan): void
{
    $stages = Stage::orderBy('sequence_order')->get();
    $productConfig = $this->getProductStageConfig($loan->product_id);
    $mainCount = 0;

    foreach ($stages as $stage) {
        $config = $productConfig[$stage->id] ?? null;

        // If product has stage config and this stage is disabled, skip creating it
        if ($config !== null && !$config['is_enabled']) {
            continue;
        }

        // If this is a sub-stage and its parent was disabled, skip it too
        if ($stage->parent_stage_key) {
            $parentStage = Stage::where('stage_key', $stage->parent_stage_key)->first();
            $parentConfig = $parentStage ? ($productConfig[$parentStage->id] ?? null) : null;
            if ($parentConfig !== null && !$parentConfig['is_enabled']) {
                continue;
            }
        }

        $isParallel = $stage->parent_stage_key !== null;
        $status = ($config && $config['auto_skip']) ? 'skipped' : 'pending';
        $assignedTo = null;

        // Pre-assign based on default role
        if ($config && $config['default_assignee_role'] && $loan->branch_id) {
            $assignedTo = $this->findUserByRoleInBranch(
                $config['default_assignee_role'],
                $loan->branch_id
            );
        }

        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => $stage->stage_key,
            'status' => $status,
            'priority' => 'normal',
            'is_parallel_stage' => $isParallel,
            'parent_stage_key' => $stage->parent_stage_key,
            'assigned_to' => $assignedTo,
            'completed_at' => $status === 'skipped' ? now() : null,
        ]);

        if (!$isParallel && $stage->parent_stage_key === null) {
            $mainCount++;
        }
    }

    LoanProgress::create([
        'loan_id' => $loan->id,
        'total_stages' => $mainCount,
        'completed_stages' => 0,
        'overall_percentage' => 0,
    ]);

    $this->recalculateProgress($loan);
}
```

### New Helper Methods

#### `getProductStageConfig(?int $productId): array`

```php
protected function getProductStageConfig(?int $productId): array
{
    if (!$productId) return [];

    return ProductStage::where('product_id', $productId)
        ->get()
        ->keyBy('stage_id')
        ->map(fn($ps) => [
            'is_enabled' => $ps->is_enabled,
            'default_assignee_role' => $ps->default_assignee_role,
            'auto_skip' => $ps->auto_skip,
        ])
        ->toArray();
}
```

#### `findUserByRoleInBranch(string $taskRole, int $branchId): ?int`

```php
protected function findUserByRoleInBranch(string $taskRole, int $branchId): ?int
{
    return User::where('task_role', $taskRole)
        ->where('is_active', true)
        ->whereHas('branches', fn($q) => $q->where('branches.id', $branchId))
        ->value('id');
}
```

---

## Controller: WorkflowConfigController

**File**: `app/Http/Controllers/WorkflowConfigController.php`

### Constructor

```php
public function __construct(
    private ConfigService $configService,
) {}
```

### Actions

#### `index()` — `GET /settings/workflow`

**Permission**: `manage_workflow_config`

```php
public function index()
{
    $banks = Bank::with(['products'])->orderBy('name')->get();
    $branches = Branch::orderBy('name')->get();
    $stages = Stage::orderBy('sequence_order')->get();

    return view('settings.workflow', compact('banks', 'branches', 'stages'));
}
```

---

#### `storeBank(Request $request)` — `POST /settings/workflow/banks`

**Permission**: `manage_workflow_config`

```php
public function storeBank(Request $request)
{
    $validated = $request->validate([
        'id' => 'nullable|exists:banks,id',
        'name' => 'required|string|max:255',
        'code' => 'nullable|string|max:20',
    ]);

    if ($validated['id'] ?? null) {
        $bank = Bank::findOrFail($validated['id']);
        $bank->update($validated);
        $message = 'Bank updated';
    } else {
        Bank::create($validated);
        $message = 'Bank created';
    }

    return redirect()->route('settings.workflow')->with('success', $message);
}
```

---

#### `destroyBank(Bank $bank)` — `DELETE /settings/workflow/banks/{bank}`

**Permission**: `manage_workflow_config`

```php
public function destroyBank(Bank $bank): JsonResponse
{
    // Check if bank has active loans
    if ($bank->loans()->where('status', 'active')->exists()) {
        return response()->json(['error' => 'Cannot delete bank with active loans'], 422);
    }

    $bank->delete(); // Cascades to products and product_stages

    return response()->json(['success' => true]);
}
```

---

#### `storeProduct(Request $request)` — `POST /settings/workflow/products`

**Permission**: `manage_workflow_config`

```php
public function storeProduct(Request $request)
{
    $validated = $request->validate([
        'id' => 'nullable|exists:products,id',
        'bank_id' => 'required|exists:banks,id',
        'name' => 'required|string|max:255',
        'code' => 'nullable|string|max:20',
    ]);

    if ($validated['id'] ?? null) {
        $product = Product::findOrFail($validated['id']);
        $product->update($validated);
    } else {
        Product::create($validated);
    }

    return redirect()->route('settings.workflow')->with('success', 'Product saved');
}
```

---

#### `productStages(Product $product)` — `GET /settings/workflow/products/{product}/stages`

**Permission**: `manage_workflow_config`

```php
public function productStages(Product $product)
{
    $stages = Stage::orderBy('sequence_order')->get();
    $productStages = $product->productStages()->get()->keyBy('stage_id');

    return view('settings.workflow-product-stages', compact('product', 'stages', 'productStages'));
}
```

---

#### `saveProductStages(Request $request, Product $product)` — `POST /settings/workflow/products/{product}/stages`

**Permission**: `manage_workflow_config`

```php
public function saveProductStages(Request $request, Product $product)
{
    $validated = $request->validate([
        'stages' => 'required|array',
        'stages.*.stage_id' => 'required|exists:stages,id',
        'stages.*.is_enabled' => 'boolean',
        'stages.*.default_assignee_role' => 'nullable|in:branch_manager,loan_advisor,bank_employee,office_employee,legal_advisor',
        'stages.*.auto_skip' => 'boolean',
        'stages.*.sort_order' => 'nullable|integer',
    ]);

    foreach ($validated['stages'] as $stageData) {
        ProductStage::updateOrCreate(
            [
                'product_id' => $product->id,
                'stage_id' => $stageData['stage_id'],
            ],
            [
                'is_enabled' => $stageData['is_enabled'] ?? true,
                'default_assignee_role' => $stageData['default_assignee_role'] ?? null,
                'auto_skip' => $stageData['auto_skip'] ?? false,
                'sort_order' => $stageData['sort_order'] ?? null,
            ],
        );
    }

    ActivityLog::log('save_product_stages', $product, [
        'product_name' => $product->name,
        'bank_name' => $product->bank->name,
    ]);

    return redirect()->route('settings.workflow.product-stages', $product)
        ->with('success', 'Stage configuration saved');
}
```

---

#### `storeBranch(Request $request)` — `POST /settings/workflow/branches`

**Permission**: `manage_workflow_config`

```php
public function storeBranch(Request $request)
{
    $validated = $request->validate([
        'id' => 'nullable|exists:branches,id',
        'name' => 'required|string|max:255',
        'code' => 'nullable|string|max:20',
        'address' => 'nullable|string|max:500',
        'city' => 'nullable|string|max:100',
        'phone' => 'nullable|string|max:20',
    ]);

    if ($validated['id'] ?? null) {
        Branch::findOrFail($validated['id'])->update($validated);
    } else {
        Branch::create($validated);
    }

    return redirect()->route('settings.workflow')->with('success', 'Branch saved');
}
```

---

## Routes

```php
// Workflow configuration
Route::middleware(['auth', 'active', 'permission:manage_workflow_config'])->group(function () {
    Route::get('/settings/workflow', [WorkflowConfigController::class, 'index'])
        ->name('settings.workflow');

    // Banks
    Route::post('/settings/workflow/banks', [WorkflowConfigController::class, 'storeBank'])
        ->name('settings.workflow.banks.store');
    Route::delete('/settings/workflow/banks/{bank}', [WorkflowConfigController::class, 'destroyBank'])
        ->name('settings.workflow.banks.destroy');

    // Products
    Route::post('/settings/workflow/products', [WorkflowConfigController::class, 'storeProduct'])
        ->name('settings.workflow.products.store');

    // Product stages
    Route::get('/settings/workflow/products/{product}/stages', [WorkflowConfigController::class, 'productStages'])
        ->name('settings.workflow.product-stages');
    Route::post('/settings/workflow/products/{product}/stages', [WorkflowConfigController::class, 'saveProductStages'])
        ->name('settings.workflow.product-stages.save');

    // Branches
    Route::post('/settings/workflow/branches', [WorkflowConfigController::class, 'storeBranch'])
        ->name('settings.workflow.branches.store');
    Route::delete('/settings/workflow/branches/{branch}', [WorkflowConfigController::class, 'destroyBranch'])
        ->name('settings.workflow.branches.destroy');
});
```

---

## Views

### `resources/views/settings/workflow.blade.php`

**Extends**: `layouts.app`

**Layout**: Tabbed interface (same pattern as settings/index.blade.php):

```
┌──────────────────────────────────────────────────────┐
│ Workflow Configuration                                │
├──────────────────────────────────────────────────────┤
│ [Banks] [Products] [Branches]  ← tab navigation     │
├──────────────────────────────────────────────────────┤
│ Banks Tab:                                            │
│ ┌──────────────────────────────────────────────┐     │
│ │ HDFC Bank (HDFC)           [Edit] [Delete]   │     │
│ │   Products: Home Loan, Mortgage Loan, OD     │     │
│ │   [Configure Stages →]                        │     │
│ ├──────────────────────────────────────────────┤     │
│ │ ICICI Bank (ICICI)         [Edit] [Delete]   │     │
│ │   Products: Home Loan, Personal Loan         │     │
│ │   [Configure Stages →]                        │     │
│ └──────────────────────────────────────────────┘     │
│                                                       │
│ Add Bank:                                             │
│ Name: [___________] Code: [____] [Save]              │
│                                                       │
│ Add Product:                                          │
│ Bank: [dropdown] Name: [_______] Code: [__] [Save]   │
├──────────────────────────────────────────────────────┤
│ Branches Tab:                                         │
│ ┌──────────────────────────────────────────────┐     │
│ │ Rajkot Main Office (RJK-MAIN)                │     │
│ │ Rajkot, Gujarat · +91 99747 89089           │     │
│ │ [Edit] [Delete]                               │     │
│ └──────────────────────────────────────────────┘     │
│                                                       │
│ Add Branch:                                           │
│ Name: [___] Code: [___] City: [___]                  │
│ Address: [___________] Phone: [___] [Save]           │
└──────────────────────────────────────────────────────┘
```

---

### `resources/views/settings/workflow-product-stages.blade.php`

**Extends**: `layouts.app`

```
┌──────────────────────────────────────────────────────────┐
│ Stage Configuration — HDFC Bank / Home Loan              │
│ ← Back to Workflow Settings                               │
├──────────────────────────────────────────────────────────┤
│ Stage Name               | Enabled | Auto-Skip | Role   │
│ ─────────────────────────┼─────────┼───────────┼────────│
│ 1. Loan Inquiry          │   [✓]   │    [ ]    │ [LA ▼] │
│ 2. Document Selection    │   [✓]   │    [ ]    │ [LA ▼] │
│ 3. Document Collection   │   [✓]   │    [ ]    │ [LA ▼] │
│ 4. Parallel Processing   │   [✓]   │    [ ]    │ [-- ▼] │
│   4a. Application Number │   [✓]   │    [ ]    │ [LA ▼] │
│   4b. BSM/OSV Approval   │   [✓]   │    [ ]    │ [BE ▼] │
│   4c. Legal Verification │   [✓]   │    [ ]    │ [LG ▼] │
│   4d. Tech Valuation     │   [✓]   │    [ ]    │ [OE ▼] │
│ 5. Rate & PF Request     │   [✓]   │    [ ]    │ [LA ▼] │
│ 6. Sanction Letter       │   [✓]   │    [ ]    │ [BE ▼] │
│ 7. Docket Login          │   [✓]   │    [ ]    │ [OE ▼] │
│ 8. KFS Generation        │   [✓]   │    [✓]   │ [-- ▼] │
│ 9. E-Sign & eNACH        │   [✓]   │    [ ]    │ [BE ▼] │
│ 10. Disbursement         │   [✓]   │    [ ]    │ [OE ▼] │
├──────────────────────────────────────────────────────────┤
│ [Save Configuration]                                      │
└──────────────────────────────────────────────────────────┘

Role dropdown values:
  -- (none)
  LA = Loan Advisor
  BE = Bank Employee
  BM = Branch Manager
  OE = Office Employee
  LG = Legal Advisor
```

---

### Settings Navigation Link

Add "Workflow" link to existing settings page or as a sub-nav:

```blade
@if(auth()->user()->hasPermission('manage_workflow_config'))
    <a href="{{ route('settings.workflow') }}" class="list-group-item">
        <i class="bi bi-diagram-3"></i> Workflow Configuration
    </a>
@endif
```

---

## Document Templates per Loan Type

The shf_task defines document templates per loan type (15 loan types, 7-9 docs each). These are used when creating direct loans (not from quotation).

**Storage**: Add `loan_type_documents` key to `config/app-defaults.php` or `app_config` table.

**15 loan types**: home-loan, lap, od, asha, pratham, personal-loan, business-loan, vehicle-loan, education-loan, cc, bl, construction-finance, bill-discounting, pl, micro-loan

**LoanDocumentService**: When populating documents for a direct loan, check product → loan type → use loan_type_documents. Fall back to customer_type documents.

**Admin UI**: Add "Document Templates" tab to workflow settings for managing per-loan-type document lists.

See `F:\G Drive\Projects\shf_task\js\stage-config.js` lines 5-157 for complete document template data.

---

## Permissions

**Add to `config/permissions.php`** under `'Loans'` group:

```php
['slug' => 'manage_workflow_config', 'name' => 'Manage Workflow Config', 'description' => 'Configure banks, products, branches, and stage workflows'],
```

**Role defaults**:
| Permission | Admin | Staff |
|------------|-------|-------|
| `manage_workflow_config` | yes | no |

---

## Verification

```bash
php artisan migrate    # product_stages table
php artisan db:seed --class=PermissionSeeder
php artisan serve

# Test flow:
# 1. Go to /settings/workflow → see banks, products, branches tabs
# 2. Add a new bank → appears in list
# 3. Add a product to the bank → appears under bank
# 4. Click "Configure Stages" for a product → see stage config form
# 5. Disable "KFS" stage, set auto-skip for "Docket" → save
# 6. Create a loan with that product → KFS stage not created, Docket auto-skipped
# 7. Add a branch → appears in branch list
# 8. Assign a user to the branch → user available for stage assignment
```

---

## Files Created/Modified

| Action | File |
|--------|------|
| Create | `database/migrations/xxxx_create_product_stages_table.php` |
| Create | `app/Models/ProductStage.php` |
| Create | `app/Http/Controllers/WorkflowConfigController.php` |
| Create | `resources/views/settings/workflow.blade.php` |
| Create | `resources/views/settings/workflow-product-stages.blade.php` |
| Modify | `app/Models/Product.php` (add stages, productStages relationships) |
| Modify | `app/Services/LoanStageService.php` (product-aware initializeStages) |
| Modify | `config/permissions.php` (add manage_workflow_config) |
| Modify | `routes/web.php` (add workflow config routes) |
