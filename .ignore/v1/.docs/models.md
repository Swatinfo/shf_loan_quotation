# Models Reference

All Eloquent models in `app/Models/`. Organized by domain group.

---

## 1. Core

### User

| Property | Value |
|----------|-------|
| Table | `users` |
| Traits | `HasFactory`, `Impersonate`, `Notifiable` |
| Fillable | `name`, `email`, `password`, `is_active`, `created_by`, `phone`, `employee_id`, `default_branch_id`, `task_bank_id` |
| Hidden | `password`, `remember_token` |
| Casts | `email_verified_at` (datetime), `password` (hashed), `is_active` (boolean) |

**Relationships:**

| Method | Type | Related Model | FK/Pivot |
|--------|------|---------------|----------|
| `creator()` | BelongsTo | User | `created_by` |
| `createdUsers()` | HasMany | User | `created_by` |
| `roles()` | BelongsToMany | Role | `role_user` |
| `userPermissions()` | HasMany | UserPermission | |
| `branches()` | BelongsToMany | Branch | `user_branches` (pivot: `is_default_office_employee`) |
| `defaultBranch()` | BelongsTo | Branch | `default_branch_id` |
| `taskBank()` | BelongsTo | Bank | `task_bank_id` |
| `employerBanks()` | BelongsToMany | Bank | `bank_employees` (pivot: `is_default`, timestamps) |
| `locations()` | BelongsToMany | Location | `location_user` (timestamps) |

**Scopes:** `advisorEligible` -- roles with `can_be_advisor=true` and `is_active=true`

**Accessors:** `role_slugs` (array of role slugs), `role_label` (comma-joined role names), `workflow_role_label` (first non-admin role name), `workflow_role_label_gu` (Gujarati)

**Notable Methods:**
- `hasRole(slug)`, `hasAnyRole(slugs)` -- role checks via loaded relationship
- `isSuperAdmin()`, `isAdmin()`, `isBankEmployee()`, `isLoanAdvisor()` -- convenience role checks
- `hasWorkflowRole()` -- has advisor-eligible, bank_employee, or office_employee role
- `canCreateLoans()` -- super_admin/admin or advisor-eligible
- `hasPermission(slug)` -- delegates to `PermissionService`
- `canImpersonate()` / `canBeImpersonated()` -- impersonation gates
- `advisorEligibleRoles()` -- static, delegates to `Role::advisorEligibleSlugs()`

---

### Role

| Property | Value |
|----------|-------|
| Table | `roles` |
| Fillable | `name`, `slug`, `description`, `can_be_advisor`, `is_system` |
| Casts | `can_be_advisor` (boolean), `is_system` (boolean) |

**Relationships:**

| Method | Type | Related Model | FK/Pivot |
|--------|------|---------------|----------|
| `users()` | BelongsToMany | User | `role_user` |
| `permissions()` | BelongsToMany | Permission | `role_permission` |

**Scopes:** `advisorEligible` (where `can_be_advisor=true`), `workflow` (where `is_system=false`)

**Notable Methods:**
- `advisorEligibleSlugs()` -- static, cached 5 min, returns slug array
- `clearAdvisorCache()` -- busts the cache
- `gujaratiLabels()` -- static array mapping slug to Gujarati display names

---

### Permission

| Property | Value |
|----------|-------|
| Table | `permissions` |
| Fillable | `name`, `slug`, `group`, `description` |

**Relationships:**

| Method | Type | Related Model | FK/Pivot |
|--------|------|---------------|----------|
| `roles()` | BelongsToMany | Role | `role_permission` |
| `userPermissions()` | HasMany | UserPermission | |

---

### UserPermission

| Property | Value |
|----------|-------|
| Table | `user_permissions` |
| Fillable | `user_id`, `permission_id`, `type` |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `user()` | BelongsTo | User | |
| `permission()` | BelongsTo | Permission | |

---

## 2. Organization

### Bank

| Property | Value |
|----------|-------|
| Table | `banks` |
| Traits | `HasAuditColumns`, `SoftDeletes` |
| Fillable | `name`, `code`, `is_active`, `default_employee_id` |
| Casts | `is_active` (boolean) |

**Relationships:**

| Method | Type | Related Model | FK/Pivot |
|--------|------|---------------|----------|
| `products()` | HasMany | Product | |
| `defaultEmployee()` | BelongsTo | User | `default_employee_id` |
| `employees()` | BelongsToMany | User | `bank_employees` (pivot: `is_default`, timestamps) |
| `locations()` | BelongsToMany | Location | `bank_location` (timestamps) |

**Scopes:** `active`

---

### Branch

| Property | Value |
|----------|-------|
| Table | `branches` |
| Traits | `HasAuditColumns`, `SoftDeletes` |
| Fillable | `name`, `code`, `address`, `city`, `phone`, `is_active`, `manager_id`, `location_id` |
| Casts | `is_active` (boolean) |

**Relationships:**

| Method | Type | Related Model | FK/Pivot |
|--------|------|---------------|----------|
| `location()` | BelongsTo | Location | |
| `users()` | BelongsToMany | User | `user_branches` |
| `manager()` | BelongsTo | User | `manager_id` |

**Scopes:** `active`

---

### Product

| Property | Value |
|----------|-------|
| Table | `products` |
| Traits | `HasAuditColumns`, `SoftDeletes` |
| Fillable | `bank_id`, `name`, `code`, `is_active` |
| Casts | `is_active` (boolean) |

**Relationships:**

| Method | Type | Related Model | FK/Pivot |
|--------|------|---------------|----------|
| `bank()` | BelongsTo | Bank | |
| `stages()` | BelongsToMany | Stage | `product_stages` (pivot: `is_enabled`, `default_assignee_role`, `auto_skip`, `sort_order`) |
| `productStages()` | HasMany | ProductStage | |
| `locations()` | BelongsToMany | Location | `location_product` (timestamps) |

**Scopes:** `active`

---

### Location

| Property | Value |
|----------|-------|
| Table | `locations` |
| Fillable | `parent_id`, `name`, `type`, `code`, `is_active` |
| Casts | `is_active` (boolean) |

**Relationships:**

| Method | Type | Related Model | FK/Pivot |
|--------|------|---------------|----------|
| `parent()` | BelongsTo | Location (self) | `parent_id` |
| `children()` | HasMany | Location (self) | `parent_id` |
| `users()` | BelongsToMany | User | `location_user` (timestamps) |
| `products()` | BelongsToMany | Product | `location_product` (timestamps) |
| `branches()` | HasMany | Branch | |

**Scopes:** `active`, `states` (type=state, no parent), `cities` (type=city, has parent)

**Helpers:** `isState()`, `isCity()`

---

### Customer

| Property | Value |
|----------|-------|
| Table | `customers` |
| Traits | `HasAuditColumns`, `SoftDeletes` |
| Fillable | `customer_name`, `mobile`, `email`, `date_of_birth`, `pan_number`, `created_by`, `updated_by`, `deleted_by` |
| Casts | `date_of_birth` (date) |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `loans()` | HasMany | LoanDetail | `customer_id` |

---

## 3. Quotation

### Quotation

| Property | Value |
|----------|-------|
| Table | `quotations` |
| Traits | `HasAuditColumns`, `SoftDeletes` |
| Fillable | `user_id`, `loan_id`, `customer_name`, `customer_type`, `loan_amount`, `pdf_filename`, `pdf_path`, `additional_notes`, `prepared_by_name`, `prepared_by_mobile`, `selected_tenures`, `location_id` |
| Casts | `loan_amount` (integer), `selected_tenures` (array) |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `user()` | BelongsTo | User | |
| `banks()` | HasMany | QuotationBank | |
| `documents()` | HasMany | QuotationDocument | |
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `location()` | BelongsTo | Location | |

**Accessors:** `is_converted` (has loan_id), `formatted_amount` (Indian number format)

**Notable Methods:** `getTypeLabel()` -- bilingual customer type label, `formatIndianNumber()` -- private helper

---

### QuotationBank

| Property | Value |
|----------|-------|
| Table | `quotation_banks` |
| Fillable | `quotation_id`, `bank_name`, `roi_min`, `roi_max`, `pf_charge`, `admin_charge`, `stamp_notary`, `registration_fee`, `advocate_fees`, `iom_charge`, `tc_report`, `extra1_name`, `extra1_amount`, `extra2_name`, `extra2_amount`, `total_charges` |
| Casts | `roi_min` (decimal:2), `roi_max` (decimal:2), `pf_charge` (decimal:2), `admin_charge` (integer), `stamp_notary` (integer), `registration_fee` (integer), `advocate_fees` (integer), `iom_charge` (integer), `tc_report` (integer), `extra1_amount` (integer), `extra2_amount` (integer), `total_charges` (integer) |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `quotation()` | BelongsTo | Quotation | |
| `emiEntries()` | HasMany | QuotationEmi | |

---

### QuotationEmi

| Property | Value |
|----------|-------|
| Table | `quotation_emi` (explicit) |
| Fillable | `quotation_bank_id`, `tenure_years`, `monthly_emi`, `total_interest`, `total_payment` |
| Casts | `tenure_years` (integer), `monthly_emi` (integer), `total_interest` (integer), `total_payment` (integer) |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `quotationBank()` | BelongsTo | QuotationBank | |

---

### QuotationDocument

| Property | Value |
|----------|-------|
| Table | `quotation_documents` |
| Fillable | `quotation_id`, `document_name_en`, `document_name_gu` |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `quotation()` | BelongsTo | Quotation | |

---

### BankCharge

| Property | Value |
|----------|-------|
| Table | `bank_charges` |
| Fillable | `bank_name`, `pf`, `admin`, `stamp_notary`, `registration_fee`, `advocate`, `tc`, `extra1_name`, `extra1_amt`, `extra2_name`, `extra2_amt` |
| Casts | `pf` (decimal:2) |

No relationships. Used as a charge template for populating quotation bank charges.

---

## 4. Loan

### LoanDetail

| Property | Value |
|----------|-------|
| Table | `loan_details` |
| Traits | `HasAuditColumns`, `SoftDeletes` |
| Fillable | `loan_number`, `quotation_id`, `customer_id`, `branch_id`, `bank_id`, `product_id`, `location_id`, `customer_name`, `customer_type`, `customer_phone`, `customer_email`, `date_of_birth`, `pan_number`, `loan_amount`, `status`, `is_sanctioned`, `current_stage`, `bank_name`, `roi_min`, `roi_max`, `total_charges`, `application_number`, `assigned_bank_employee`, `due_date`, `expected_docket_date`, `rejected_at`, `rejected_by`, `rejected_stage`, `rejection_reason`, `status_reason`, `status_changed_at`, `status_changed_by`, `created_by`, `assigned_advisor`, `notes` |
| Casts | `loan_amount` (integer), `is_sanctioned` (boolean), `roi_min` (decimal:2), `roi_max` (decimal:2), `due_date` (date), `date_of_birth` (date), `expected_docket_date` (date), `rejected_at` (datetime), `status_changed_at` (datetime) |

**Constants:**

```php
STATUS_ACTIVE = 'active'
STATUS_COMPLETED = 'completed'
STATUS_REJECTED = 'rejected'
STATUS_CANCELLED = 'cancelled'
STATUS_ON_HOLD = 'on_hold'

STATUS_LABELS = [status => [label, color]] // Bootstrap badge colors
CUSTOMER_TYPE_LABELS = [type => bilingual_label]
```

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `quotation()` | BelongsTo | Quotation | |
| `branch()` | BelongsTo | Branch | |
| `bank()` | BelongsTo | Bank | |
| `product()` | BelongsTo | Product | |
| `location()` | BelongsTo | Location | |
| `customer()` | BelongsTo | Customer | |
| `creator()` | BelongsTo | User | `created_by` |
| `advisor()` | BelongsTo | User | `assigned_advisor` |
| `bankEmployee()` | BelongsTo | User | `assigned_bank_employee` |
| `statusChangedByUser()` | BelongsTo | User | `status_changed_by` |
| `documents()` | HasMany | LoanDocument | `loan_id` |
| `stageAssignments()` | HasMany | StageAssignment | `loan_id` |
| `progress()` | HasOne | LoanProgress | `loan_id` |
| `stageTransfers()` | HasMany | StageTransfer | `loan_id` |
| `stageQueries()` | HasMany | StageQuery | `loan_id` |
| `remarks()` | HasMany | Remark | `loan_id` |
| `valuationDetails()` | HasMany | ValuationDetail | `loan_id` |
| `disbursement()` | HasOne | DisbursementDetail | `loan_id` |

**Scopes:** `active`, `visibleTo(User)` -- filters by ownership/assignment/branch/transfer history

**Accessors:** `current_owner` (User from current stage assignment), `time_with_current_owner` (duration string), `total_loan_time` (duration string), `formatted_amount`, `status_label`, `status_color`, `customer_type_label`, `current_stage_name` (with parallel sub-stage details), `stage_badge_html` (HTML badges)

**Notable Methods:**
- `getStageAssignment(key)` -- find assignment by stage_key
- `isBasicEditLocked()` -- locked after app_number completed
- `getEditableStageKey()` -- previous stage that can be edited
- `canEditStage(key)` -- check if a specific stage is editable
- `generateLoanNumber()` -- static, format `SHF-YYYYMM-NNNN`
- `stageBadgeClass(key)` -- static, CSS class for stage badge
- `roleSuffix(slug)` -- static, display suffix for role
- `userRoleSlug(User)` -- static, primary workflow role slug

---

### LoanDocument

| Property | Value |
|----------|-------|
| Table | `loan_documents` |
| Traits | `HasAuditColumns` |
| Fillable | `loan_id`, `document_name_en`, `document_name_gu`, `is_required`, `status`, `received_date`, `received_by`, `rejected_reason`, `notes`, `sort_order`, `file_path`, `file_name`, `file_size`, `file_mime`, `uploaded_by`, `uploaded_at` |
| Casts | `is_required` (boolean), `received_date` (date), `sort_order` (integer), `file_size` (integer), `uploaded_at` (datetime) |

**Constants:**

```php
STATUS_PENDING = 'pending'
STATUS_RECEIVED = 'received'
STATUS_REJECTED = 'rejected'
STATUS_WAIVED = 'waived'
STATUS_LABELS = [status => [label, color]]
```

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `receivedByUser()` | BelongsTo | User | `received_by` |
| `uploadedByUser()` | BelongsTo | User | `uploaded_by` |

**Scopes:** `required`, `received`, `pending`, `rejected`, `resolved` (received or waived), `unresolved` (pending or rejected)

**Helpers:** `hasFile()`, `formattedFileSize()`, `isReceived()`, `isPending()`, `isResolved()`

---

### LoanProgress

| Property | Value |
|----------|-------|
| Table | `loan_progress` (explicit) |
| Fillable | `loan_id`, `total_stages`, `completed_stages`, `overall_percentage`, `estimated_completion`, `workflow_snapshot` |
| Casts | `total_stages` (integer), `completed_stages` (integer), `overall_percentage` (decimal:2), `estimated_completion` (date), `workflow_snapshot` (array) |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |

---

## 5. Workflow

### Stage

| Property | Value |
|----------|-------|
| Table | `stages` |
| Fillable | `stage_key`, `is_enabled`, `stage_name_en`, `stage_name_gu`, `sequence_order`, `is_parallel`, `parent_stage_key`, `stage_type`, `description_en`, `description_gu`, `default_role`, `sub_actions` |
| Casts | `is_enabled` (boolean), `is_parallel` (boolean), `sequence_order` (integer), `default_role` (array), `sub_actions` (array) |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `children()` | HasMany | Stage (self) | `parent_stage_key` -> `stage_key` |
| `parent()` | BelongsTo | Stage (self) | `parent_stage_key` -> `stage_key` |

**Scopes:** `enabled`, `mainStages` (no parent, ordered by sequence), `subStagesOf(parentKey)`

**Helpers:** `isSubStage()`, `isParent()`, `isDecision()`

---

### StageAssignment

| Property | Value |
|----------|-------|
| Table | `stage_assignments` |
| Traits | `HasAuditColumns` |
| Fillable | `loan_id`, `stage_key`, `assigned_to`, `status`, `priority`, `started_at`, `completed_at`, `completed_by`, `is_parallel_stage`, `parent_stage_key`, `notes` |
| Casts | `is_parallel_stage` (boolean), `started_at` (datetime), `completed_at` (datetime) |

**Constants:**

```php
STATUS_PENDING = 'pending'
STATUS_IN_PROGRESS = 'in_progress'
STATUS_COMPLETED = 'completed'
STATUS_REJECTED = 'rejected'
STATUS_SKIPPED = 'skipped'

STATUS_LABELS = [status => [label, color]]
PRIORITY_LABELS = [priority => [label, color]]  // low, normal, high, urgent
```

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `assignee()` | BelongsTo | User | `assigned_to` |
| `completedByUser()` | BelongsTo | User | `completed_by` |
| `stage()` | BelongsTo | Stage | `stage_key` -> `stage_key` |
| `transfers()` | HasMany | StageTransfer | `stage_assignment_id` |
| `queries()` | HasMany | StageQuery | `stage_assignment_id` |
| `activeQueries()` | HasMany | StageQuery | filtered: status in [pending, responded] |

**Scopes:** `pending`, `inProgress`, `completed`, `forUser(userId)`, `mainStages`, `subStagesOf(parentKey)`

**Notable Methods:**
- `isActionable()` -- pending or in_progress
- `canTransitionTo(status)` -- validates state transitions
- `hasPendingQueries()` -- checks for blocking queries
- `getNotesData()` / `mergeNotesData(data)` -- JSON notes management

---

### StageTransfer

| Property | Value |
|----------|-------|
| Table | `stage_transfers` |
| Timestamps | **disabled** (`$timestamps = false`; only `created_at` via cast) |
| Fillable | `stage_assignment_id`, `loan_id`, `stage_key`, `transferred_from`, `transferred_to`, `reason`, `transfer_type` |
| Casts | `created_at` (datetime) |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `stageAssignment()` | BelongsTo | StageAssignment | |
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `fromUser()` | BelongsTo | User | `transferred_from` |
| `toUser()` | BelongsTo | User | `transferred_to` |

---

### StageQuery

| Property | Value |
|----------|-------|
| Table | `stage_queries` |
| Fillable | `stage_assignment_id`, `loan_id`, `stage_key`, `query_text`, `raised_by`, `status`, `resolved_at`, `resolved_by` |
| Casts | `resolved_at` (datetime) |

**Constants:**

```php
STATUS_PENDING = 'pending'
STATUS_RESPONDED = 'responded'
STATUS_RESOLVED = 'resolved'
```

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `stageAssignment()` | BelongsTo | StageAssignment | |
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `raisedByUser()` | BelongsTo | User | `raised_by` |
| `resolvedByUser()` | BelongsTo | User | `resolved_by` |
| `responses()` | HasMany | QueryResponse | `stage_query_id` |

**Scopes:** `pending`, `active` (pending or responded), `resolved`

---

### QueryResponse

| Property | Value |
|----------|-------|
| Table | `query_responses` |
| Timestamps | **disabled** (`$timestamps = false`; only `created_at` via cast) |
| Fillable | `stage_query_id`, `response_text`, `responded_by` |
| Casts | `created_at` (datetime) |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `stageQuery()` | BelongsTo | StageQuery | `stage_query_id` |
| `respondedByUser()` | BelongsTo | User | `responded_by` |

---

### ProductStage

| Property | Value |
|----------|-------|
| Table | `product_stages` |
| Traits | `HasAuditColumns` |
| Fillable | `product_id`, `stage_id`, `is_enabled`, `default_assignee_role`, `default_user_id`, `auto_skip`, `allow_skip`, `sort_order`, `sub_actions_override` |
| Casts | `is_enabled` (boolean), `auto_skip` (boolean), `allow_skip` (boolean), `sort_order` (integer), `sub_actions_override` (array) |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `product()` | BelongsTo | Product | |
| `stage()` | BelongsTo | Stage | |
| `defaultUser()` | BelongsTo | User | `default_user_id` |
| `branchUsers()` | HasMany | ProductStageUser | |

**Notable Methods:**
- `getUserForBranch(branchId)` -- branch-specific or default user
- `getUserForLocation(branchId, cityId, stateId)` -- priority: branch -> city -> state -> default

---

### ProductStageUser

| Property | Value |
|----------|-------|
| Table | `product_stage_users` |
| Fillable | `product_stage_id`, `branch_id`, `location_id`, `user_id`, `is_default` |
| Casts | `is_default` (boolean) |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `productStage()` | BelongsTo | ProductStage | |
| `branch()` | BelongsTo | Branch | |
| `user()` | BelongsTo | User | |

---

## 6. Specialized

### ValuationDetail

| Property | Value |
|----------|-------|
| Table | `valuation_details` |
| Traits | `HasAuditColumns` |
| Fillable | `loan_id`, `valuation_type`, `property_address`, `property_type`, `latitude`, `longitude`, `land_area`, `land_rate`, `land_valuation`, `construction_area`, `construction_rate`, `construction_valuation`, `final_valuation`, `market_value`, `government_value`, `valuation_date`, `valuator_name`, `valuator_report_number`, `notes` |
| Casts | `land_rate` (decimal:2), `land_valuation` (integer), `construction_rate` (decimal:2), `construction_valuation` (integer), `final_valuation` (integer), `market_value` (integer), `government_value` (integer), `valuation_date` (date) |

**Constants:**

```php
TYPE_PROPERTY = 'property'
TYPE_VEHICLE = 'vehicle'
TYPE_BUSINESS = 'business'
TYPES = [type => label]
PROPERTY_TYPES = [key => label]  // residential_bunglow, residential_flat, commercial, industrial, land, mixed
```

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |

---

### DisbursementDetail

| Property | Value |
|----------|-------|
| Table | `disbursement_details` |
| Traits | `HasAuditColumns` |
| Fillable | `loan_id`, `disbursement_type`, `disbursement_date`, `amount_disbursed`, `bank_account_number`, `cheques`, `notes` |
| Casts | `amount_disbursed` (integer), `disbursement_date` (date), `cheques` (array) |

**Constants:**

```php
TYPE_FUND_TRANSFER = 'fund_transfer'
TYPE_CHEQUE = 'cheque'
TYPES = [type => label]  // 'Fund Transfer (NEFT/RTGS)', 'Cheque'
```

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `otcClearedByUser()` | BelongsTo | User | `otc_cleared_by` |

**Helpers:** `isComplete()` -- always returns true

---

### Remark

| Property | Value |
|----------|-------|
| Table | `remarks` |
| Fillable | `loan_id`, `stage_key`, `user_id`, `remark` |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `user()` | BelongsTo | User | |

**Scopes:** `forStage(key)`, `general` (null stage_key)

---

### ShfNotification

| Property | Value |
|----------|-------|
| Table | `shf_notifications` (explicit) |
| Fillable | `user_id`, `title`, `message`, `type`, `is_read`, `loan_id`, `stage_key`, `link` |
| Casts | `is_read` (boolean) |

**Constants:**

```php
TYPE_INFO = 'info'
TYPE_SUCCESS = 'success'
TYPE_WARNING = 'warning'
TYPE_ERROR = 'error'
TYPE_STAGE_UPDATE = 'stage_update'
TYPE_ASSIGNMENT = 'assignment'
```

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `user()` | BelongsTo | User | |
| `loan()` | BelongsTo | LoanDetail | `loan_id` |

**Scopes:** `unread`, `forUser(userId)`, `recent(limit=50)`

---

## 7. Config

### AppConfig

| Property | Value |
|----------|-------|
| Table | `app_config` (explicit) |
| Fillable | `config_key`, `config_json` |
| Casts | `config_json` (array) |

No relationships. Key-value config store read via `ConfigService`.

---

### ActivityLog

| Property | Value |
|----------|-------|
| Table | `activity_logs` |
| Fillable | `user_id`, `action`, `subject_type`, `subject_id`, `properties`, `ip_address`, `user_agent` |
| Casts | `properties` (array) |

**Relationships:**

| Method | Type | Related Model | FK |
|--------|------|---------------|-----|
| `user()` | BelongsTo | User | |

**Notable Methods:**
- `log(action, subject?, properties?)` -- static factory; auto-fills `user_id`, `ip_address`, `user_agent` from request context. Uses polymorphic `subject_type`/`subject_id`.

---

## Shared Trait: HasAuditColumns

Located at `app/Traits/HasAuditColumns.php`. Auto-fills:
- `updated_by` on creating and updating (if column exists)
- `deleted_by` on soft deleting (if column exists)

Used by: Bank, Branch, Product, Customer, Quotation, LoanDetail, LoanDocument, StageAssignment, ProductStage, ValuationDetail, DisbursementDetail

---

## Quick Reference: Model Count by Group

| Group | Count |
|-------|-------|
| Core | 4 |
| Organization | 5 |
| Quotation | 5 |
| Loan | 3 |
| Workflow | 7 |
| Specialized | 4 |
| Config | 2 |
| **Total** | **30** |
