# Eloquent Models

## Overview

The application uses 28 Eloquent models. All models are in `app/Models/`.

**Original (11)**: User, Quotation, QuotationBank, QuotationEmi, QuotationDocument, Permission, RolePermission, UserPermission, ActivityLog, AppConfig, BankCharge

**Loan Task System (17)**: Bank, Branch, Product, Stage, ProductStage, ProductStageUser, LoanDetail, LoanDocument, LoanProgress, StageAssignment, StageTransfer, StageQuery, QueryResponse, Remark, ShfNotification, ValuationDetail, DisbursementDetail

## Relationship Diagram

```
User ──┬── hasMany → UserPermission
       ├── hasMany → Quotation
       ├── hasMany → createdUsers (self-referential)
       ├── belongsTo → creator (self-referential via created_by)
       ├── hasMany → ActivityLog
       ├── belongsToMany → Branch (via user_branches, withPivot is_default_office_employee)
       ├── belongsTo → Branch (default_branch_id)
       ├── belongsTo → Bank (task_bank_id)
       └── belongsToMany → Bank (via bank_employees, withPivot is_default)

Quotation ──┬── belongsTo → User
            ├── hasMany → QuotationBank
            ├── hasMany → QuotationDocument
            └── belongsTo → LoanDetail (loan_id)

QuotationBank ──┬── belongsTo → Quotation
                └── hasMany → QuotationEmi

QuotationEmi ── belongsTo → QuotationBank

QuotationDocument ── belongsTo → Quotation

Permission ──┬── hasMany → RolePermission
             └── hasMany → UserPermission

RolePermission ── belongsTo → Permission
UserPermission ── belongsTo → User, Permission

ActivityLog ── belongsTo → User

AppConfig   ── standalone (key-value config store)
BankCharge  ── standalone (reference data)

Bank ──┬── hasMany → Product
       ├── belongsTo → User (default_employee_id)
       └── belongsToMany → User (via bank_employees, withPivot is_default)

Branch ──┬── belongsToMany → User (via user_branches)
         └── belongsTo → User (manager_id)

Product ──┬── belongsTo → Bank
          ├── belongsToMany → Stage (via product_stages, withPivot)
          └── hasMany → ProductStage

ProductStage ──┬── belongsTo → Product
               ├── belongsTo → Stage
               ├── belongsTo → User (default_user_id)
               └── hasMany → ProductStageUser

ProductStageUser ── belongsTo → ProductStage, Branch, User

Stage ──┬── hasMany → Stage (children via parent_stage_key)
        └── belongsTo → Stage (parent via parent_stage_key)

LoanDetail ──┬── belongsTo → Quotation, Branch, Bank, Product
             ├── belongsTo → User (creator, advisor, bankEmployee)
             ├── hasMany → LoanDocument
             ├── hasMany → StageAssignment
             ├── hasOne → LoanProgress
             ├── hasMany → StageTransfer
             ├── hasMany → StageQuery
             ├── hasMany → Remark
             ├── hasMany → ValuationDetail
             └── hasOne → DisbursementDetail

LoanDocument ── belongsTo → LoanDetail, User (received_by)

LoanProgress ── belongsTo → LoanDetail

StageAssignment ──┬── belongsTo → LoanDetail, User (assignee/completedBy), Stage (via stage_key)
                  ├── hasMany → StageTransfer
                  └── hasMany → StageQuery

StageTransfer ── belongsTo → StageAssignment, LoanDetail, User (from/to)

StageQuery ──┬── belongsTo → StageAssignment, LoanDetail, User (raised_by/resolved_by)
             └── hasMany → QueryResponse

QueryResponse ── belongsTo → StageQuery, User (responded_by)

Remark ── belongsTo → LoanDetail, User

ShfNotification ── belongsTo → User, LoanDetail

ValuationDetail ── belongsTo → LoanDetail

DisbursementDetail ── belongsTo → LoanDetail, User (otc_cleared_by)
```

---

## User

**File**: `app/Models/User.php`

**Table**: `users`

**Fillable**: `name`, `email`, `password`, `role`, `is_active`, `created_by`, `phone`, `task_role`, `employee_id`, `default_branch_id`, `task_bank_id`

**Hidden**: `password`, `remember_token`

**Traits**: HasFactory, Notifiable, Impersonate

**Casts**:
| Attribute | Cast |
|-----------|------|
| `email_verified_at` | datetime |
| `password` | hashed |
| `is_active` | boolean |

**Relationships**:
| Method | Type | Related | FK / Notes |
|--------|------|---------|------------|
| `creator()` | BelongsTo | User | `created_by` |
| `createdUsers()` | HasMany | User | `created_by` |
| `userPermissions()` | HasMany | UserPermission | `user_id` |
| `branches()` | BelongsToMany | Branch | via `user_branches` (withPivot `is_default_office_employee`) |
| `defaultBranch()` | BelongsTo | Branch | `default_branch_id` |
| `taskBank()` | BelongsTo | Bank | `task_bank_id` |
| `employerBanks()` | BelongsToMany | Bank | via `bank_employees` (withPivot `is_default`) |

**Custom Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `isSuperAdmin()` | bool | `role === 'super_admin'` |
| `isAdmin()` | bool | `role === 'admin'` |
| `isStaff()` | bool | `role === 'staff'` |
| `hasPermission(string $slug)` | bool | Delegates to `PermissionService::userHasPermission()` |
| `hasTaskRole()` | bool | Checks if user has a task_role assigned |
| `isTaskRole(string $role)` | bool | Checks if user's task_role matches given role |
| `isBankEmployee()` | bool | Checks if task_role is `bank_employee` |
| `isLoanAdvisor()` | bool | Checks if task_role is `loan_advisor` |
| `isLegalAdvisor()` | bool | Checks if task_role is `legal_advisor` |
| `canImpersonate()` | bool | Impersonate trait method |
| `canBeImpersonate()` | bool | Impersonate trait method |

**Accessors**:
| Accessor | Returns | Description |
|----------|---------|-------------|
| `getRoleLabelAttribute()` | string | Human-readable role: "Super Admin", "Admin", "Staff" |
| `getTaskRoleLabelAttribute()` | string | Human-readable task role label (English) |
| `getTaskRoleLabelGuAttribute()` | string | Human-readable task role label (Gujarati) |

**Constants**:
| Constant | Value |
|----------|-------|
| `TASK_ROLES` | `['branch_manager', 'loan_advisor', 'bank_employee', 'office_employee', 'legal_advisor']` |
| `TASK_ROLE_LABELS` | English labels for each task role |
| `TASK_ROLE_LABELS_GU` | Gujarati labels for each task role |

**Roles**: `super_admin`, `admin`, `staff`

---

## Quotation

**File**: `app/Models/Quotation.php`

**Table**: `quotations`

**Fillable**: `user_id`, `loan_id`, `customer_name`, `customer_type`, `loan_amount`, `pdf_filename`, `pdf_path`, `additional_notes`, `prepared_by_name`, `prepared_by_mobile`, `selected_tenures`

**Traits**: HasAuditColumns, SoftDeletes

**Casts**:
| Attribute | Cast |
|-----------|------|
| `loan_amount` | integer |
| `selected_tenures` | array |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `user()` | BelongsTo | User | `user_id` |
| `banks()` | HasMany | QuotationBank | `quotation_id` |
| `documents()` | HasMany | QuotationDocument | `quotation_id` |
| `loan()` | BelongsTo | LoanDetail | `loan_id` |

**Accessors**:
| Accessor | Returns | Description |
|----------|---------|-------------|
| `getIsConvertedAttribute()` | bool | Checks `loan_id` is not null |
| `getFormattedAmountAttribute()` | string | `₹ X,XX,XXX` formatted loan amount |

**Custom Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `getTypeLabel()` | string | Bilingual type label (e.g., "Proprietor / માલિકી") |
| `formatIndianNumber($num)` | string | Private helper for Indian comma formatting |

**Customer Types**: `proprietor`, `partnership_llp`, `pvt_ltd`, `salaried`, `all`

---

## QuotationBank

**File**: `app/Models/QuotationBank.php`

**Table**: `quotation_banks`

**Fillable**: `quotation_id`, `bank_name`, `roi_min`, `roi_max`, `pf_charge`, `admin_charge`, `stamp_notary`, `registration_fee`, `advocate_fees`, `iom_charge`, `tc_report`, `extra1_name`, `extra1_amount`, `extra2_name`, `extra2_amount`, `total_charges`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `roi_min` | decimal:2 |
| `roi_max` | decimal:2 |
| `pf_charge` | decimal:2 |
| `admin_charge` | integer |
| `stamp_notary` | integer |
| `registration_fee` | integer |
| `advocate_fees` | integer |
| `iom_charge` | integer |
| `tc_report` | integer |
| `extra1_amount` | integer |
| `extra2_amount` | integer |
| `total_charges` | integer |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `quotation()` | BelongsTo | Quotation | `quotation_id` |
| `emiEntries()` | HasMany | QuotationEmi | `quotation_bank_id` |

---

## QuotationEmi

**File**: `app/Models/QuotationEmi.php`

**Table**: `quotation_emi` (NOT `quotation_emis` — custom table name)

**Fillable**: `quotation_bank_id`, `tenure_years`, `monthly_emi`, `total_interest`, `total_payment`

**Casts**: `tenure_years`, `monthly_emi`, `total_interest`, `total_payment` → all `integer`

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `quotationBank()` | BelongsTo | QuotationBank | `quotation_bank_id` |

> **Note**: The relationship method is `quotationBank()`, NOT `bank()`.

---

## QuotationDocument

**File**: `app/Models/QuotationDocument.php`

**Table**: `quotation_documents`

**Fillable**: `quotation_id`, `document_name_en`, `document_name_gu`

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `quotation()` | BelongsTo | Quotation | `quotation_id` |

---

## Permission

**File**: `app/Models/Permission.php`

**Table**: `permissions`

**Fillable**: `name`, `slug`, `group`, `description`

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `rolePermissions()` | HasMany | RolePermission | `permission_id` |
| `userPermissions()` | HasMany | UserPermission | `permission_id` |

---

## RolePermission

**File**: `app/Models/RolePermission.php`

**Table**: `role_permissions`

**Fillable**: `role`, `permission_id`

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `permission()` | BelongsTo | Permission | `permission_id` |

---

## UserPermission

**File**: `app/Models/UserPermission.php`

**Table**: `user_permissions`

**Fillable**: `user_id`, `permission_id`, `type`

**Types**: `grant`, `deny`

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `user()` | BelongsTo | User | `user_id` |
| `permission()` | BelongsTo | Permission | `permission_id` |

---

## ActivityLog

**File**: `app/Models/ActivityLog.php`

**Table**: `activity_logs`

**Fillable**: `user_id`, `action`, `subject_type`, `subject_id`, `properties`, `ip_address`, `user_agent`

**Casts**: `properties` → `array`

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `user()` | BelongsTo | User | `user_id` |

**Static Methods**:
```php
ActivityLog::log(string $action, ?Model $subject = null, array $properties = [])
```
Auto-captures: `auth()->id()`, `request()->ip()`, `request()->userAgent()`. Polymorphic subject via `subject_type` + `subject_id`.

---

## AppConfig

**File**: `app/Models/AppConfig.php`

**Table**: `app_config`

**Fillable**: `config_key`, `config_json`

**Casts**: `config_json` → `array`

Standalone model — no relationships. Stores application configuration as key-value pairs where value is a JSON object.

---

## BankCharge

**File**: `app/Models/BankCharge.php`

**Table**: `bank_charges`

**Fillable**: `bank_name`, `pf`, `admin`, `stamp_notary`, `registration_fee`, `advocate`, `tc`, `extra1_name`, `extra1_amt`, `extra2_name`, `extra2_amt`

**Casts**: `pf` → `decimal:2`

Standalone reference data model — stores per-bank default charges for auto-fill in the quotation creation form.

---

## Bank

**File**: `app/Models/Bank.php`

**Table**: `banks`

**Fillable**: `name`, `code`, `is_active`, `default_employee_id`

**Traits**: HasAuditColumns, SoftDeletes

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_active` | boolean |

**Relationships**:
| Method | Type | Related | FK / Notes |
|--------|------|---------|------------|
| `products()` | HasMany | Product | `bank_id` |
| `defaultEmployee()` | BelongsTo | User | `default_employee_id` |
| `employees()` | BelongsToMany | User | via `bank_employees` (withPivot `is_default`) |

**Scopes**:
| Scope | Description |
|-------|-------------|
| `active()` | Filters to `is_active = true` |

---

## Branch

**File**: `app/Models/Branch.php`

**Table**: `branches`

**Fillable**: `name`, `code`, `address`, `city`, `phone`, `is_active`, `manager_id`

**Traits**: HasAuditColumns, SoftDeletes

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_active` | boolean |

**Relationships**:
| Method | Type | Related | FK / Notes |
|--------|------|---------|------------|
| `users()` | BelongsToMany | User | via `user_branches` |
| `manager()` | BelongsTo | User | `manager_id` |

**Scopes**:
| Scope | Description |
|-------|-------------|
| `active()` | Filters to `is_active = true` |

---

## Product

**File**: `app/Models/Product.php`

**Table**: `products`

**Fillable**: `bank_id`, `name`, `code`, `is_active`

**Traits**: HasAuditColumns, SoftDeletes

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_active` | boolean |

**Relationships**:
| Method | Type | Related | FK / Notes |
|--------|------|---------|------------|
| `bank()` | BelongsTo | Bank | `bank_id` |
| `stages()` | BelongsToMany | Stage | via `product_stages` (withPivot `is_enabled`, `default_assignee_role`, `auto_skip`, `sort_order`) |
| `productStages()` | HasMany | ProductStage | `product_id` |

**Scopes**:
| Scope | Description |
|-------|-------------|
| `active()` | Filters to `is_active = true` |

---

## ProductStage

**File**: `app/Models/ProductStage.php`

**Table**: `product_stages`

**Fillable**: `product_id`, `stage_id`, `is_enabled`, `default_assignee_role`, `default_user_id`, `auto_skip`, `sort_order`

**Traits**: HasAuditColumns

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_enabled` | boolean |
| `auto_skip` | boolean |
| `sort_order` | integer |

**Relationships**:
| Method | Type | Related | FK / Notes |
|--------|------|---------|------------|
| `product()` | BelongsTo | Product | `product_id` |
| `stage()` | BelongsTo | Stage | `stage_id` |
| `defaultUser()` | BelongsTo | User | `default_user_id` |
| `branchUsers()` | HasMany | ProductStageUser | `product_stage_id` |

**Custom Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `getUserForBranch(?int $branchId)` | ?int | Returns user_id assigned for a specific branch |

---

## ProductStageUser

**File**: `app/Models/ProductStageUser.php`

**Table**: `product_stage_users`

**Fillable**: `product_stage_id`, `branch_id`, `user_id`

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `productStage()` | BelongsTo | ProductStage | `product_stage_id` |
| `branch()` | BelongsTo | Branch | `branch_id` |
| `user()` | BelongsTo | User | `user_id` |

---

## Stage

**File**: `app/Models/Stage.php`

**Table**: `stages`

**Fillable**: `stage_key`, `stage_name_en`, `stage_name_gu`, `sequence_order`, `is_parallel`, `parent_stage_key`, `stage_type`, `description_en`, `description_gu`, `default_role`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_parallel` | boolean |
| `sequence_order` | integer |
| `default_role` | array |

**Relationships**:
| Method | Type | Related | FK / Notes |
|--------|------|---------|------------|
| `children()` | HasMany | Stage | `parent_stage_key` |
| `parent()` | BelongsTo | Stage | `parent_stage_key` |

**Scopes**:
| Scope | Description |
|-------|-------------|
| `mainStages()` | Filters to stages with no parent |
| `subStagesOf(string $parentKey)` | Filters to children of a given parent stage key |

**Custom Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `isSubStage()` | bool | Checks if stage has a parent |
| `isParent()` | bool | Checks if stage has children |
| `isDecision()` | bool | Checks if stage_type is decision |

---

## LoanDetail

**File**: `app/Models/LoanDetail.php`

**Table**: `loan_details`

**Fillable**: `loan_number`, `quotation_id`, `branch_id`, `bank_id`, `product_id`, `customer_name`, `customer_type`, `customer_phone`, `customer_email`, `loan_amount`, `status`, `current_stage`, `bank_name`, `roi_min`, `roi_max`, `total_charges`, `application_number`, `assigned_bank_employee`, `due_date`, `rejected_at`, `rejected_by`, `rejected_stage`, `rejection_reason`, `created_by`, `assigned_advisor`, `notes`

**Traits**: HasAuditColumns, SoftDeletes

**Casts**:
| Attribute | Cast |
|-----------|------|
| `loan_amount` | integer |
| `roi_min` | decimal:2 |
| `roi_max` | decimal:2 |
| `due_date` | date |
| `rejected_at` | datetime |

**Relationships**:
| Method | Type | Related | FK / Notes |
|--------|------|---------|------------|
| `quotation()` | BelongsTo | Quotation | `quotation_id` |
| `branch()` | BelongsTo | Branch | `branch_id` |
| `bank()` | BelongsTo | Bank | `bank_id` |
| `product()` | BelongsTo | Product | `product_id` |
| `creator()` | BelongsTo | User | `created_by` |
| `advisor()` | BelongsTo | User | `assigned_advisor` |
| `bankEmployee()` | BelongsTo | User | `assigned_bank_employee` |
| `documents()` | HasMany | LoanDocument | `loan_id` |
| `stageAssignments()` | HasMany | StageAssignment | `loan_id` |
| `progress()` | HasOne | LoanProgress | `loan_id` |
| `stageTransfers()` | HasMany | StageTransfer | `loan_id` |
| `stageQueries()` | HasMany | StageQuery | `loan_id` |
| `remarks()` | HasMany | Remark | `loan_id` |
| `valuationDetails()` | HasMany | ValuationDetail | `loan_id` |
| `disbursement()` | HasOne | DisbursementDetail | `loan_id` |

**Scopes**:
| Scope | Description |
|-------|-------------|
| `active()` | Filters to active status loans |
| `visibleTo(User $user)` | Filters loans visible to a given user based on role/permissions |

**Accessors**:
| Accessor | Returns | Description |
|----------|---------|-------------|
| `getFormattedAmountAttribute()` | string | `₹ X,XX,XXX` formatted loan amount |
| `getStatusLabelAttribute()` | string | Human-readable status label |
| `getStatusColorAttribute()` | string | Bootstrap color class for status |
| `getCustomerTypeLabelAttribute()` | string | Bilingual customer type label |
| `getCurrentStageNameAttribute()` | string | Name of the current stage |

**Dynamic Accessors**:
| Accessor | Returns | Description |
|----------|---------|-------------|
| `getCurrentOwnerAttribute()` | mixed | Current owner of the loan based on stage assignment |
| `getTimeWithCurrentOwnerAttribute()` | mixed | Duration loan has been with current owner |
| `getTotalLoanTimeAttribute()` | mixed | Total elapsed time since loan creation |

**Static Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `generateLoanNumber()` | string | Generates unique loan number |

**Constants**:
| Constant | Description |
|----------|-------------|
| `STATUS_ACTIVE` | Active status value |
| `STATUS_COMPLETED` | Completed status value |
| `STATUS_REJECTED` | Rejected status value |
| `STATUS_CANCELLED` | Cancelled status value |
| `STATUS_ON_HOLD` | On-hold status value |
| `STATUSES` | Array of all status values |
| `STATUS_LABELS` | Human-readable labels for statuses |
| `CUSTOMER_TYPE_LABELS` | Bilingual customer type labels |

---

## LoanDocument

**File**: `app/Models/LoanDocument.php`

**Table**: `loan_documents`

**Fillable**: `loan_id`, `document_name_en`, `document_name_gu`, `is_required`, `status`, `received_date`, `received_by`, `rejected_reason`, `notes`, `sort_order`

**Traits**: HasAuditColumns

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_required` | boolean |
| `received_date` | date |
| `sort_order` | integer |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `receivedByUser()` | BelongsTo | User | `received_by` |

**Scopes**:
| Scope | Description |
|-------|-------------|
| `required()` | Filters to required documents |
| `received()` | Filters to received documents |
| `pending()` | Filters to pending documents |
| `rejected()` | Filters to rejected documents |
| `resolved()` | Filters to resolved documents |
| `unresolved()` | Filters to unresolved documents |

**Custom Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `isReceived()` | bool | Checks if document status is received |
| `isPending()` | bool | Checks if document status is pending |
| `isResolved()` | bool | Checks if document is resolved |

**Constants**:
| Constant | Value |
|----------|-------|
| `STATUS_PENDING` | Pending status |
| `STATUS_RECEIVED` | Received status |
| `STATUS_REJECTED` | Rejected status |
| `STATUS_WAIVED` | Waived status |
| `STATUSES` | Array of all status values |
| `STATUS_LABELS` | Human-readable labels for statuses |

---

## LoanProgress

**File**: `app/Models/LoanProgress.php`

**Table**: `loan_progress`

**Fillable**: `loan_id`, `total_stages`, `completed_stages`, `overall_percentage`, `estimated_completion`, `workflow_snapshot`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `total_stages` | integer |
| `completed_stages` | integer |
| `overall_percentage` | decimal:2 |
| `estimated_completion` | date |
| `workflow_snapshot` | array |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |

---

## StageAssignment

**File**: `app/Models/StageAssignment.php`

**Table**: `stage_assignments`

**Fillable**: `loan_id`, `stage_key`, `assigned_to`, `status`, `priority`, `started_at`, `completed_at`, `completed_by`, `is_parallel_stage`, `parent_stage_key`, `notes`

**Traits**: HasAuditColumns

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_parallel_stage` | boolean |
| `started_at` | datetime |
| `completed_at` | datetime |

**Relationships**:
| Method | Type | Related | FK / Notes |
|--------|------|---------|------------|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `assignee()` | BelongsTo | User | `assigned_to` |
| `completedByUser()` | BelongsTo | User | `completed_by` |
| `stage()` | BelongsTo | Stage | via `stage_key` to `stage_key` |
| `transfers()` | HasMany | StageTransfer | `stage_assignment_id` |
| `queries()` | HasMany | StageQuery | `stage_assignment_id` |
| `activeQueries()` | HasMany | StageQuery | filtered: pending/responded |

**Scopes**:
| Scope | Description |
|-------|-------------|
| `pending()` | Filters to pending assignments |
| `inProgress()` | Filters to in-progress assignments |
| `completed()` | Filters to completed assignments |
| `forUser(int $userId)` | Filters to assignments for a specific user |
| `mainStages()` | Filters to main stage assignments (no parent) |
| `subStagesOf(string $parentKey)` | Filters to sub-stage assignments of a given parent |

**Custom Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `isActionable()` | bool | Checks if assignment can be acted upon |
| `canTransitionTo(string $newStatus)` | bool | Validates status transition |
| `hasPendingQueries()` | bool | Checks for unresolved queries |
| `getNotesData()` | array | Retrieves structured notes data |
| `mergeNotesData(array $data)` | void | Merges data into notes |

**Constants**:
| Constant | Description |
|----------|-------------|
| `STATUS_PENDING` | Pending status |
| `STATUS_IN_PROGRESS` | In-progress status |
| `STATUS_COMPLETED` | Completed status |
| `STATUS_REJECTED` | Rejected status |
| `STATUS_SKIPPED` | Skipped status |
| `STATUSES` | Array of all status values |
| `STATUS_LABELS` | Human-readable labels |
| `PRIORITY_LABELS` | Human-readable priority labels |

---

## StageTransfer

**File**: `app/Models/StageTransfer.php`

**Table**: `stage_transfers`

**Timestamps**: `false` (`$timestamps = false`, `created_at` added manually)

**Fillable**: `stage_assignment_id`, `loan_id`, `stage_key`, `transferred_from`, `transferred_to`, `reason`, `transfer_type`

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `stageAssignment()` | BelongsTo | StageAssignment | `stage_assignment_id` |
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `fromUser()` | BelongsTo | User | `transferred_from` |
| `toUser()` | BelongsTo | User | `transferred_to` |

---

## StageQuery

**File**: `app/Models/StageQuery.php`

**Table**: `stage_queries`

**Fillable**: `stage_assignment_id`, `loan_id`, `stage_key`, `query_text`, `raised_by`, `status`, `resolved_at`, `resolved_by`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `resolved_at` | datetime |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `stageAssignment()` | BelongsTo | StageAssignment | `stage_assignment_id` |
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `raisedByUser()` | BelongsTo | User | `raised_by` |
| `resolvedByUser()` | BelongsTo | User | `resolved_by` |
| `responses()` | HasMany | QueryResponse | `stage_query_id` |

**Scopes**:
| Scope | Description |
|-------|-------------|
| `pending()` | Filters to pending queries |
| `active()` | Filters to pending + responded queries |
| `resolved()` | Filters to resolved queries |

**Constants**:
| Constant | Value |
|----------|-------|
| `STATUS_PENDING` | Pending status |
| `STATUS_RESPONDED` | Responded status |
| `STATUS_RESOLVED` | Resolved status |

---

## QueryResponse

**File**: `app/Models/QueryResponse.php`

**Table**: `query_responses`

**Timestamps**: `false` (`$timestamps = false`, `created_at` manually cast)

**Fillable**: `stage_query_id`, `response_text`, `responded_by`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `created_at` | datetime |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `stageQuery()` | BelongsTo | StageQuery | `stage_query_id` |
| `respondedByUser()` | BelongsTo | User | `responded_by` |

---

## Remark

**File**: `app/Models/Remark.php`

**Table**: `remarks`

**Fillable**: `loan_id`, `stage_key`, `user_id`, `remark`

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `user()` | BelongsTo | User | `user_id` |

**Scopes**:
| Scope | Description |
|-------|-------------|
| `forStage(string $key)` | Filters remarks for a specific stage key |
| `general()` | Filters to remarks with null stage_key |

---

## ShfNotification

**File**: `app/Models/ShfNotification.php`

**Table**: `shf_notifications`

**Fillable**: `user_id`, `title`, `message`, `type`, `is_read`, `loan_id`, `stage_key`, `link`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_read` | boolean |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `user()` | BelongsTo | User | `user_id` |
| `loan()` | BelongsTo | LoanDetail | `loan_id` |

**Scopes**:
| Scope | Description |
|-------|-------------|
| `unread()` | Filters to unread notifications |
| `forUser(int $userId)` | Filters to notifications for a specific user |
| `recent(int $limit = 50)` | Limits to most recent notifications |

**Constants**:
| Constant | Description |
|----------|-------------|
| `TYPE_INFO` | Info notification type |
| `TYPE_SUCCESS` | Success notification type |
| `TYPE_WARNING` | Warning notification type |
| `TYPE_ERROR` | Error notification type |
| `TYPE_STAGE_UPDATE` | Stage update notification type |
| `TYPE_ASSIGNMENT` | Assignment notification type |

---

## ValuationDetail

**File**: `app/Models/ValuationDetail.php`

**Table**: `valuation_details`

**Fillable**: `loan_id`, `valuation_type`, `property_address`, `property_type`, `property_area`, `market_value`, `government_value`, `valuation_date`, `valuator_name`, `valuator_report_number`, `notes`

**Traits**: HasAuditColumns

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

**Constants**:
| Constant | Description |
|----------|-------------|
| `TYPE_PROPERTY` | Property valuation type |
| `TYPE_VEHICLE` | Vehicle valuation type |
| `TYPE_BUSINESS` | Business valuation type |
| `TYPES` | Array of all valuation types |
| `PROPERTY_TYPES` | Array of property type options |

---

## DisbursementDetail

**File**: `app/Models/DisbursementDetail.php`

**Table**: `disbursement_details`

**Fillable**: `loan_id`, `disbursement_type`, `disbursement_date`, `amount_disbursed`, `bank_account_number`, `ifsc_code`, `cheque_number`, `cheque_date`, `dd_number`, `dd_date`, `is_otc`, `otc_branch`, `otc_cleared`, `otc_cleared_date`, `otc_cleared_by`, `reference_number`, `notes`

**Traits**: HasAuditColumns

**Casts**:
| Attribute | Cast |
|-----------|------|
| `amount_disbursed` | integer |
| `disbursement_date` | date |
| `cheque_date` | date |
| `dd_date` | date |
| `is_otc` | boolean |
| `otc_cleared` | boolean |
| `otc_cleared_date` | date |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `otcClearedByUser()` | BelongsTo | User | `otc_cleared_by` |

**Custom Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `isComplete()` | bool | Checks if disbursement is complete |
| `needsOtcClearance()` | bool | Checks if OTC clearance is needed |

**Constants**:
| Constant | Description |
|----------|-------------|
| `TYPE_FUND_TRANSFER` | Fund transfer disbursement type |
| `TYPE_CHEQUE` | Cheque disbursement type |
| `TYPE_DEMAND_DRAFT` | Demand draft disbursement type |
| `TYPES` | Array of all disbursement types |
