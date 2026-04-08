# Stage A: Foundation (Database, Models, Core Services)

## Overview

Creates the foundational database tables, models, and services for the loan task system. No routes, views, or permissions — purely backend scaffolding.

## Dependencies

None. This is the first stage.

---

## Migrations

### Migration 1: `create_banks_table`

**File**: `database/migrations/xxxx_xx_xx_create_banks_table.php`

**Table**: `banks`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| name | string | no | | e.g., "HDFC Bank", "ICICI Bank" |
| code | string | yes | null | e.g., "HDFC", "ICICI" — short identifier |
| is_active | boolean | no | true | soft toggle |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: unique on `name`

**Seed data** (from existing `config/app-defaults.php` banks array):
```
HDFC Bank, ICICI Bank, Axis Bank, Kotak Mahindra Bank
```

---

### Migration 2: `create_branches_table`

**File**: `database/migrations/xxxx_xx_xx_create_branches_table.php`

**Table**: `branches`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| name | string | no | | e.g., "Rajkot Main Office" |
| code | string | yes | null | unique short code, e.g., "RJK-MAIN" |
| address | text | yes | null | full address |
| city | string | yes | null | simple text, no FK to cities table |
| phone | string(20) | yes | null | |
| is_active | boolean | no | true | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: unique on `code` (where not null)

**Seed data**: One default branch from company info:
```
name: "Rajkot Main Office"
address: (from config companyAddress)
city: "Rajkot"
phone: (from config companyPhone)
```

---

### Migration 3: `create_products_table`

**File**: `database/migrations/xxxx_xx_xx_create_products_table.php`

**Table**: `products`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| bank_id | FK → banks | no | | cascade on delete |
| name | string | no | | e.g., "Home Loan", "Mortgage Loan" |
| code | string | yes | null | e.g., "HL", "ML" |
| is_active | boolean | no | true | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: index on `bank_id`; unique composite on `(bank_id, name)`

**Seed data** (from `config/app-defaults.php` ourServices):
```
For each bank: Home Loan, Mortgage Loan, Commercial Loan, Industrial Loan, Land Loan, Over Draft (OD)
```

---

### Migration 4: `create_stages_table`

**File**: `database/migrations/xxxx_xx_xx_create_stages_table.php`

**Table**: `stages`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| stage_key | string | no | | unique identifier, e.g., "inquiry", "document_collection" |
| stage_name_en | string | no | | English name |
| stage_name_gu | string | yes | null | Gujarati name |
| sequence_order | integer | no | | display/processing order |
| is_parallel | boolean | no | false | true only for parent "parallel_processing" stage |
| parent_stage_key | string | yes | null | refs stages.stage_key — for sub-stages of stage 4 |
| stage_type | string | no | 'sequential' | values: sequential, parallel, decision |
| description_en | text | yes | null | |
| description_gu | text | yes | null | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: unique on `stage_key`; index on `sequence_order`; index on `parent_stage_key`

**Validation constraints** (enforced in service/seeder, not DB):
- `stage_type` must be one of: `sequential`, `parallel`, `decision`
- `parent_stage_key` must reference an existing `stage_key` if set

---

### Migration 5: `create_user_branches_table`

**File**: `database/migrations/xxxx_xx_xx_create_user_branches_table.php`

**Table**: `user_branches`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| user_id | FK → users | no | | cascade on delete |
| branch_id | FK → branches | no | | cascade on delete |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: unique composite on `(user_id, branch_id)`; index on `user_id`; index on `branch_id`

---

### Migration 6: `add_task_fields_to_users_table`

**File**: `database/migrations/xxxx_xx_xx_add_task_fields_to_users_table.php`

**Adds to `users` table**:

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| task_role | string | yes | null | values: branch_manager, loan_advisor, bank_employee, office_employee, legal_advisor |
| employee_id | string | yes | null | internal employee ID or bank employee ID |
| default_branch_id | FK → branches | yes | null | set null on delete |
| task_bank_id | FK → banks | yes | null | set null on delete — **only for bank_employee role** |

**Validation constraints** (enforced in controller/service, not DB):
- `task_role` must be one of: `branch_manager`, `loan_advisor`, `bank_employee`, `office_employee`, `legal_advisor`, or null
- `task_bank_id` is required when `task_role = 'bank_employee'`, null otherwise

**Two-role design** (see `role-integration.md` for full details):
- `role` (super_admin/admin/staff) = system-level access control (who can access what features)
- `task_role` = workflow-level role (who can be assigned to which loan stages)
- `task_bank_id` = which bank a bank_employee works for (filters stage assignment)
- Both can coexist: A `staff` user with `task_role = 'loan_advisor'` is a quotation staff member who also processes loans
- A `super_admin` with `task_role = null` can still manage everything but won't appear in stage assignment dropdowns
- A `staff` with `task_role = 'bank_employee'` and `task_bank_id = (HDFC id)` can only be assigned to stages for HDFC loans

---

## Seeder: `StageSeeder`

**File**: `database/seeders/StageSeeder.php`

Seeds 14 stage rows. Run via `php artisan db:seed --class=StageSeeder`.

```php
$stages = [
    // Main sequential stages
    ['stage_key' => 'inquiry', 'stage_name_en' => 'Loan Inquiry', 'stage_name_gu' => 'લોન પૂછપરછ', 'sequence_order' => 1, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Initial customer and loan details entry'],
    ['stage_key' => 'document_selection', 'stage_name_en' => 'Document Selection', 'stage_name_gu' => 'દસ્તાવેજ પસંદગી', 'sequence_order' => 2, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Select required documents for the loan'],
    ['stage_key' => 'document_collection', 'stage_name_en' => 'Document Collection', 'stage_name_gu' => 'દસ્તાવેજ સંગ્રહ', 'sequence_order' => 3, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Collect and verify all required documents'],

    // Stage 4: Parallel parent
    ['stage_key' => 'parallel_processing', 'stage_name_en' => 'Parallel Processing', 'stage_name_gu' => 'સમાંતર પ્રક્રિયા', 'sequence_order' => 4, 'is_parallel' => true, 'parent_stage_key' => null, 'stage_type' => 'parallel', 'description_en' => 'Four parallel tracks processed simultaneously'],

    // Stage 4 sub-stages (all sequence_order = 4, differentiated by parent_stage_key)
    ['stage_key' => 'app_number', 'stage_name_en' => 'Application Number', 'stage_name_gu' => 'અરજી નંબર', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Enter bank application number'],
    ['stage_key' => 'bsm_osv', 'stage_name_en' => 'BSM/OSV Approval', 'stage_name_gu' => 'BSM/OSV મંજૂરી', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Bank site and office verification'],
    ['stage_key' => 'legal_verification', 'stage_name_en' => 'Legal Verification', 'stage_name_gu' => 'કાનૂની ચકાસણી', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Legal document verification'],
    ['stage_key' => 'technical_valuation', 'stage_name_en' => 'Technical Valuation', 'stage_name_gu' => 'ટેકનિકલ મૂલ્યાંકન', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Property/asset technical valuation'],

    // Stages 5-9: Sequential
    ['stage_key' => 'rate_pf', 'stage_name_en' => 'Rate & PF Request', 'stage_name_gu' => 'દર અને PF વિનંતી', 'sequence_order' => 5, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Request interest rate and processing fee from bank'],
    ['stage_key' => 'sanction', 'stage_name_en' => 'Sanction Letter', 'stage_name_gu' => 'મંજૂરી પત્ર', 'sequence_order' => 6, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Bank issues sanction letter'],
    ['stage_key' => 'docket', 'stage_name_en' => 'Docket Login', 'stage_name_gu' => 'ડોકેટ લોગિન', 'sequence_order' => 7, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Physical document processing and docket creation'],
    ['stage_key' => 'kfs', 'stage_name_en' => 'KFS Generation', 'stage_name_gu' => 'KFS જનરેશન', 'sequence_order' => 8, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Key Fact Statement generation'],
    ['stage_key' => 'esign', 'stage_name_en' => 'E-Sign & eNACH', 'stage_name_gu' => 'ઈ-સાઇન અને eNACH', 'sequence_order' => 9, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Digital signature and eNACH mandate'],

    // Stage 10: Decision tree
    ['stage_key' => 'disbursement', 'stage_name_en' => 'Disbursement', 'stage_name_gu' => 'વિતરણ', 'sequence_order' => 10, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'decision', 'description_en' => 'Fund disbursement — transfer or cheque with OTC handling'],

    // ── Bank/product-specific optional stages (enabled via product_stages in Stage I) ──
    // These are seeded but NOT active by default. Enabled per product via product_stages table.
    ['stage_key' => 'cibil_check', 'stage_name_en' => 'CIBIL Score Check', 'stage_name_gu' => 'CIBIL સ્કોર તપાસ', 'sequence_order' => 5, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Credit score verification (optional, between parallel and rate_pf)'],
    ['stage_key' => 'property_valuation', 'stage_name_en' => 'Property Valuation', 'stage_name_gu' => 'મિલકત મૂલ્યાંકન', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Dedicated property valuation (replaces technical_valuation for LAP)'],
    ['stage_key' => 'vehicle_valuation', 'stage_name_en' => 'Vehicle Valuation', 'stage_name_gu' => 'વાહન મૂલ્યાંકન', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Vehicle valuation for car/vehicle loans'],
    ['stage_key' => 'business_valuation', 'stage_name_en' => 'Business Valuation', 'stage_name_gu' => 'વ્યવસાય મૂલ્યાંકન', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Business valuation for business loans'],
    ['stage_key' => 'title_search', 'stage_name_en' => 'Title Search', 'stage_name_gu' => 'ટાઇટલ સર્ચ', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Property title verification (for LAP)'],
    ['stage_key' => 'financial_analysis', 'stage_name_en' => 'Financial Analysis', 'stage_name_gu' => 'નાણાકીય વિશ્લેષણ', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Financial analysis for business loans'],
    ['stage_key' => 'site_visit', 'stage_name_en' => 'Site Visit Report', 'stage_name_gu' => 'સાઇટ મુલાકાત રિપોર્ટ', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Physical site visit for business loans'],
    ['stage_key' => 'approval_committee', 'stage_name_en' => 'Approval Committee', 'stage_name_gu' => 'મંજૂરી સમિતિ', 'sequence_order' => 5, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Committee approval (ICICI specific)'],
    ['stage_key' => 'credit_committee', 'stage_name_en' => 'Credit Committee', 'stage_name_gu' => 'ક્રેડિટ સમિતિ', 'sequence_order' => 5, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Credit committee review (Kotak specific)'],
    ['stage_key' => 'insurance', 'stage_name_en' => 'Insurance', 'stage_name_gu' => 'વીમો', 'sequence_order' => 9, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Insurance requirement (for vehicle loans)'],
    ['stage_key' => 'mortgage', 'stage_name_en' => 'Mortgage Registration', 'stage_name_gu' => 'મોર્ટગેજ નોંધણી', 'sequence_order' => 9, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Mortgage registration (for LAP)'],
];

// Total: 14 base stages + 11 optional stages = 25 stage rows
// Base stages are always created for all loans
// Optional stages are only created when product_stages has them enabled
```

---

## Models

### Bank

**File**: `app/Models/Bank.php`

**Table**: `banks`

**Fillable**: `name`, `code`, `is_active`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_active` | boolean |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `products()` | HasMany | Product | `bank_id` |
| `loans()` | HasMany | LoanDetail | `bank_id` |

**Scopes**:
| Scope | Query |
|-------|-------|
| `scopeActive($q)` | `$q->where('is_active', true)` |

---

### Branch

**File**: `app/Models/Branch.php`

**Table**: `branches`

**Fillable**: `name`, `code`, `address`, `city`, `phone`, `is_active`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_active` | boolean |

**Relationships**:
| Method | Type | Related | FK/Pivot |
|--------|------|---------|----------|
| `users()` | BelongsToMany | User | pivot: `user_branches` |
| `loans()` | HasMany | LoanDetail | `branch_id` |

**Scopes**:
| Scope | Query |
|-------|-------|
| `scopeActive($q)` | `$q->where('is_active', true)` |

---

### Product

**File**: `app/Models/Product.php`

**Table**: `products`

**Fillable**: `bank_id`, `name`, `code`, `is_active`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_active` | boolean |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `bank()` | BelongsTo | Bank | `bank_id` |
| `loans()` | HasMany | LoanDetail | `product_id` |

**Scopes**:
| Scope | Query |
|-------|-------|
| `scopeActive($q)` | `$q->where('is_active', true)` |

---

### Stage

**File**: `app/Models/Stage.php`

**Table**: `stages`

**Fillable**: `stage_key`, `stage_name_en`, `stage_name_gu`, `sequence_order`, `is_parallel`, `parent_stage_key`, `stage_type`, `description_en`, `description_gu`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_parallel` | boolean |
| `sequence_order` | integer |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `children()` | HasMany | Stage | `parent_stage_key` (local: `stage_key`) |
| `parent()` | BelongsTo | Stage | `parent_stage_key` (owner: `stage_key`) |

**Custom Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `isSubStage()` | bool | `return $this->parent_stage_key !== null` |
| `isParent()` | bool | `return $this->is_parallel && $this->children()->exists()` |
| `isDecision()` | bool | `return $this->stage_type === 'decision'` |

**Scopes**:
| Scope | Query |
|-------|-------|
| `scopeMainStages($q)` | `$q->whereNull('parent_stage_key')->orderBy('sequence_order')` |
| `scopeSubStagesOf($q, $parentKey)` | `$q->where('parent_stage_key', $parentKey)` |

---

### User Model Modifications

**File**: `app/Models/User.php` (existing — modify)

**Add to fillable**: `task_role`, `employee_id`, `default_branch_id`, `task_bank_id`

**New Relationships**:
| Method | Type | Related | FK/Pivot |
|--------|------|---------|----------|
| `branches()` | BelongsToMany | Branch | pivot: `user_branches` |
| `defaultBranch()` | BelongsTo | Branch | `default_branch_id` |
| `taskBank()` | BelongsTo | Bank | `task_bank_id` |

**New Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `hasTaskRole()` | bool | `return $this->task_role !== null` |
| `isTaskRole(string $role)` | bool | `return $this->task_role === $role` |
| `isBankEmployee()` | bool | `return $this->task_role === 'bank_employee'` |
| `isLoanAdvisor()` | bool | `return $this->task_role === 'loan_advisor'` |
| `isLegalAdvisor()` | bool | `return $this->task_role === 'legal_advisor'` |

**New Accessors**:
| Accessor | Returns | Description |
|----------|---------|-------------|
| `getTaskRoleLabelAttribute()` | string | English: "Loan Advisor", "Bank Employee", etc. |
| `getTaskRoleLabelGuAttribute()` | string | Gujarati label |

**New Scopes**:
| Scope | Description |
|-------|-------------|
| `scopeEligibleForStage($q, $stageKey, $loanBankId)` | Filters to users eligible for a stage; bank employees matched by bank |

**Task Role Constants** (see `role-integration.md` for full mapping):
```php
const TASK_ROLES = ['branch_manager', 'loan_advisor', 'bank_employee', 'office_employee', 'legal_advisor'];

const TASK_ROLE_LABELS = [
    'branch_manager' => 'Branch Manager',
    'loan_advisor' => 'Loan Advisor',
    'bank_employee' => 'Bank Employee',
    'office_employee' => 'Office Employee',
    'legal_advisor' => 'Legal Advisor',
];

const TASK_ROLE_LABELS_GU = [
    'branch_manager' => 'બ્રાન્ચ મેનેજર',
    'loan_advisor' => 'લોન સલાહકાર',
    'bank_employee' => 'બેંક કર્મચારી',
    'office_employee' => 'ઓફિસ કર્મચારી',
    'legal_advisor' => 'કાનૂની સલાહકાર',
];
```

---

## Service: LoanStageService

**File**: `app/Services/LoanStageService.php`

### Constructor

```php
public function __construct()
```

No dependencies in Stage A. Extended in later stages.

### Methods (Stage A)

| Method | Signature | Returns | Description |
|--------|-----------|---------|-------------|
| `getOrderedStages` | `(): Collection` | `Collection<Stage>` | All main stages (no sub-stages) ordered by sequence |
| `getStageByKey` | `(string $key): ?Stage` | `Stage\|null` | Find stage by stage_key |
| `getSubStages` | `(string $parentKey): Collection` | `Collection<Stage>` | Get child stages of a parallel parent |
| `isParallelStage` | `(string $key): bool` | `bool` | Check if stage_key is the parallel parent or a sub-stage |
| `getMainStageKeys` | `(): array` | `string[]` | Ordered array of main stage keys (no sub-stages) |

### Implementation Notes

```php
public function getOrderedStages(): Collection
{
    return Stage::mainStages()->get();
}

public function getSubStages(string $parentKey): Collection
{
    return Stage::subStagesOf($parentKey)->get();
}

public function getMainStageKeys(): array
{
    return Stage::mainStages()->pluck('stage_key')->toArray();
    // Returns: ['inquiry', 'document_selection', 'document_collection', 'parallel_processing', 'rate_pf', 'sanction', 'docket', 'kfs', 'esign', 'disbursement']
}
```

---

## Verification

After implementing Stage A:

```bash
php artisan migrate                              # All 6 migrations run successfully
php artisan db:seed --class=StageSeeder          # 14 stage rows seeded
php artisan tinker
> Stage::count()                                 # 14
> Stage::mainStages()->count()                   # 10
> Stage::subStagesOf('parallel_processing')->count()  # 4
> Bank::count()                                  # 4 (if BankSeeder ran)
> Branch::count()                                # 1 (if BranchSeeder ran)
> User::first()->task_role                       # null (column exists)
```

---

## Files Created/Modified

| Action | File |
|--------|------|
| Create | `database/migrations/xxxx_create_banks_table.php` |
| Create | `database/migrations/xxxx_create_branches_table.php` |
| Create | `database/migrations/xxxx_create_products_table.php` |
| Create | `database/migrations/xxxx_create_stages_table.php` |
| Create | `database/migrations/xxxx_create_user_branches_table.php` |
| Create | `database/migrations/xxxx_add_task_fields_to_users_table.php` |
| Create | `database/seeders/StageSeeder.php` |
| Create | `database/seeders/BankSeeder.php` (optional) |
| Create | `app/Models/Bank.php` |
| Create | `app/Models/Branch.php` |
| Create | `app/Models/Product.php` |
| Create | `app/Models/Stage.php` |
| Create | `app/Services/LoanStageService.php` |
| Modify | `app/Models/User.php` |
