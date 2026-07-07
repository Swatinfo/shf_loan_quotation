# Database Schema Reference

Auto-generated from migration files. Source of truth for all table structures.

---

## Core Tables

### `users`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| name | string | | | |
| email | string | | | unique |
| email_verified_at | timestamp | yes | null | |
| password | string | | | |
| role | enum | | 'staff' | Values: `super_admin`, `admin`, `staff`, `bank_employee` (legacy column, superseded by roles table) |
| is_active | boolean | | true | |
| created_by | FK(users) | yes | null | nullOnDelete |
| phone | string(20) | yes | null | |
| task_role | string | yes | null | Legacy: `branch_manager`, `bdo`, `loan_advisor`, `bank_employee`, `office_employee` (superseded by roles table) |
| employee_id | string | yes | null | |
| default_branch_id | FK(branches) | yes | null | nullOnDelete |
| task_bank_id | FK(banks) | yes | null | nullOnDelete |
| remember_token | string | yes | null | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

### `roles`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| name | string | | | Display name |
| slug | string | | | unique; Values: `super_admin`, `admin`, `branch_manager`, `bdo`, `loan_advisor`, `bank_employee`, `office_employee` |
| description | string | yes | null | |
| can_be_advisor | boolean | | false | Whether role holders can be assigned as loan advisor |
| is_system | boolean | | false | System roles cannot be deleted/edited via UI |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

### `role_user`

Pivot table: User to Role assignment.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| user_id | FK(users) | | | cascadeOnDelete; composite PK with role_id |
| role_id | FK(roles) | | | cascadeOnDelete; composite PK with user_id |

### `role_permission`

Pivot table: Role to Permission assignment (new unified system).

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| role_id | FK(roles) | | | cascadeOnDelete; composite PK with permission_id |
| permission_id | FK(permissions) | | | cascadeOnDelete; composite PK with role_id |

### `permissions`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| name | string | | | Display name |
| slug | string | | | unique |
| group | string | | | Groups: Settings, Quotations, Users, Loans, Customers, System |
| description | string | yes | null | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

### `role_permissions`

Legacy role-permission table (enum-based roles).

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| role | enum | | | Values: `super_admin`, `admin`, `staff`, `bank_employee` |
| permission_id | FK(permissions) | | | cascadeOnDelete |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

Unique: `[role, permission_id]`

### `user_permissions`

Per-user permission overrides (grant or deny).

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| user_id | FK(users) | | | cascadeOnDelete |
| permission_id | FK(permissions) | | | cascadeOnDelete |
| type | enum | | | Values: `grant`, `deny` |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

Unique: `[user_id, permission_id]`

### `task_role_permissions`

Legacy task-role permission mapping.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| task_role | string | | | Role slug |
| permission_id | FK(permissions) | | | cascadeOnDelete |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

Unique: `[task_role, permission_id]`

### `password_reset_tokens`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| email | string (PK) | | | |
| token | string | | | |
| created_at | timestamp | yes | null | |

### `sessions`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | string (PK) | | | |
| user_id | FK(users) | yes | null | indexed |
| ip_address | string(45) | yes | null | |
| user_agent | text | yes | null | |
| payload | longText | | | |
| last_activity | integer | | | indexed |

### `cache`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| key | string (PK) | | | |
| value | mediumText | | | |
| expiration | integer | | | indexed |

### `cache_locks`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| key | string (PK) | | | |
| owner | string | | | |
| expiration | integer | | | indexed |

### `jobs`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| queue | string | | | indexed |
| payload | longText | | | |
| attempts | unsignedTinyInteger | | | |
| reserved_at | unsignedInteger | yes | null | |
| available_at | unsignedInteger | | | |
| created_at | unsignedInteger | | | |

### `job_batches`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | string (PK) | | | |
| name | string | | | |
| total_jobs | integer | | | |
| pending_jobs | integer | | | |
| failed_jobs | integer | | | |
| failed_job_ids | longText | | | |
| options | mediumText | yes | null | |
| cancelled_at | integer | yes | null | |
| created_at | integer | | | |
| finished_at | integer | yes | null | |

### `failed_jobs`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| uuid | string | | | unique |
| connection | text | | | |
| queue | text | | | |
| payload | longText | | | |
| exception | longText | | | |
| failed_at | timestamp | | CURRENT_TIMESTAMP | |

---

## Config & Logging Tables

### `app_config`

Key-value config store. Read via `ConfigService` with `config/app-defaults.php` fallback.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| config_key | string | | | unique; e.g. `main` |
| config_json | longText | yes | null | JSON blob |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

### `app_settings`

Simple key-value settings.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| setting_key | string (PK) | | | |
| setting_value | text | yes | null | |
| updated_at | timestamp | yes | null | |

### `activity_logs`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| user_id | FK(users) | yes | null | nullOnDelete; indexed |
| action | string | | | |
| subject_type | string | yes | null | Polymorphic type |
| subject_id | unsignedBigInteger | yes | null | Polymorphic ID |
| properties | json | yes | null | |
| ip_address | string(45) | yes | null | |
| user_agent | text | yes | null | |
| created_at | timestamp | yes | null | indexed |
| updated_at | timestamp | yes | null | |

Index: `[subject_type, subject_id]`

---

## Quotation Tables

### `quotations`

Uses **SoftDeletes**.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| loan_id | FK(loan_details) | yes | null | nullOnDelete |
| location_id | FK(locations) | yes | null | nullOnDelete |
| user_id | FK(users) | | | cascadeOnDelete; indexed |
| customer_name | string | | | |
| customer_type | string | | | Values: `proprietor`, `partnership_llp`, `pvt_ltd`, `salaried`, `all` |
| loan_amount | unsignedBigInteger | | | |
| pdf_filename | string | yes | null | |
| pdf_path | string | yes | null | |
| additional_notes | text | yes | null | |
| prepared_by_name | string | yes | null | |
| prepared_by_mobile | string | yes | null | |
| selected_tenures | json | yes | null | |
| created_at | timestamp | yes | null | indexed |
| updated_at | timestamp | yes | null | |
| updated_by | FK(users) | yes | null | nullOnDelete |
| deleted_at | timestamp | yes | null | SoftDeletes |
| deleted_by | FK(users) | yes | null | nullOnDelete |

### `quotation_banks`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| quotation_id | FK(quotations) | | | cascadeOnDelete |
| bank_name | string | | | |
| roi_min | decimal(5,2) | | 0 | |
| roi_max | decimal(5,2) | | 0 | |
| pf_charge | decimal(5,2) | | 0 | |
| admin_charge | unsignedBigInteger | | 0 | |
| stamp_notary | unsignedBigInteger | | 0 | Renamed from `stamp_duty` |
| registration_fee | unsignedBigInteger | | 0 | Renamed from `notary_charge` |
| advocate_fees | unsignedBigInteger | | 0 | |
| iom_charge | unsignedBigInteger | | 0 | |
| tc_report | unsignedBigInteger | | 0 | |
| extra1_name | string | yes | null | |
| extra1_amount | unsignedBigInteger | | 0 | |
| extra2_name | string | yes | null | |
| extra2_amount | unsignedBigInteger | | 0 | |
| total_charges | unsignedBigInteger | | 0 | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

### `quotation_emi`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| quotation_bank_id | FK(quotation_banks) | | | cascadeOnDelete |
| tenure_years | integer | | | |
| monthly_emi | unsignedBigInteger | | 0 | |
| total_interest | unsignedBigInteger | | 0 | |
| total_payment | unsignedBigInteger | | 0 | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

### `quotation_documents`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| quotation_id | FK(quotations) | | | cascadeOnDelete |
| document_name_en | string | | | |
| document_name_gu | string | yes | null | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

### `bank_charges`

Default charge templates for banks.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| bank_name | string | | | |
| pf | decimal(5,2) | | 0 | |
| admin | unsignedBigInteger | | 0 | |
| stamp_notary | unsignedBigInteger | | 0 | Renamed from `stamp` |
| registration_fee | unsignedBigInteger | | 0 | Renamed from `notary` |
| advocate | unsignedBigInteger | | 0 | |
| tc | unsignedBigInteger | | 0 | |
| extra1_name | string | yes | null | |
| extra1_amt | unsignedBigInteger | | 0 | |
| extra2_name | string | yes | null | |
| extra2_amt | unsignedBigInteger | | 0 | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

---

## Loan Foundation Tables

### `banks`

Uses **SoftDeletes**.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| name | string | | | unique |
| code | string | yes | null | |
| is_active | boolean | | true | |
| default_employee_id | FK(users) | yes | null | nullOnDelete |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |
| updated_by | FK(users) | yes | null | nullOnDelete |
| deleted_at | timestamp | yes | null | SoftDeletes |
| deleted_by | FK(users) | yes | null | nullOnDelete |

### `branches`

Uses **SoftDeletes**.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| name | string | | | |
| code | string | yes | null | unique |
| address | text | yes | null | |
| city | string | yes | null | |
| phone | string(20) | yes | null | |
| is_active | boolean | | true | |
| manager_id | FK(users) | yes | null | nullOnDelete |
| location_id | FK(locations) | yes | null | nullOnDelete |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |
| updated_by | FK(users) | yes | null | nullOnDelete |
| deleted_at | timestamp | yes | null | SoftDeletes |
| deleted_by | FK(users) | yes | null | nullOnDelete |

### `products`

Uses **SoftDeletes**.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| bank_id | FK(banks) | | | cascadeOnDelete |
| name | string | | | |
| code | string | yes | null | |
| is_active | boolean | | true | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |
| updated_by | FK(users) | yes | null | nullOnDelete |
| deleted_at | timestamp | yes | null | SoftDeletes |
| deleted_by | FK(users) | yes | null | nullOnDelete |

Unique: `[bank_id, name]`

### `stages`

Workflow stage definitions.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| stage_key | string | | | unique |
| is_enabled | boolean | | true | |
| stage_name_en | string | | | |
| stage_name_gu | string | yes | null | |
| sequence_order | integer | | | indexed |
| is_parallel | boolean | | false | |
| parent_stage_key | string | yes | null | indexed; FK-like to stages.stage_key |
| stage_type | string | | 'sequential' | Values: `sequential`, `parallel`, `decision` |
| description_en | text | yes | null | |
| description_gu | text | yes | null | |
| default_role | string (JSON) | yes | null | JSON array of role slugs eligible for this stage |
| sub_actions | json | yes | null | JSON array of sub-action definitions |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

### `locations`

Hierarchical: states and cities.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| parent_id | FK(locations) | yes | null | nullOnDelete; self-referential |
| name | string | | | |
| type | enum | | 'city' | Values: `state`, `city` |
| code | string(20) | yes | null | |
| is_active | boolean | | true | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

Unique: `[name, parent_id]`

### `customers`

Uses **SoftDeletes**. Has audit columns.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| customer_name | string | | | |
| mobile | string(20) | yes | null | |
| email | string | yes | null | |
| date_of_birth | date | yes | null | |
| pan_number | string(10) | yes | null | |
| created_by | FK(users) | yes | null | nullOnDelete |
| updated_by | FK(users) | yes | null | nullOnDelete |
| deleted_by | FK(users) | yes | null | nullOnDelete |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |
| deleted_at | timestamp | yes | null | SoftDeletes |

---

## Loan Core Tables

### `loan_details`

Uses **SoftDeletes**. Has audit columns (updated_by, deleted_by).

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| loan_number | string | | | unique; format: `SHF-YYYYMM-NNNN` |
| quotation_id | FK(quotations) | yes | null | nullOnDelete |
| customer_id | FK(customers) | yes | null | nullOnDelete |
| branch_id | FK(branches) | yes | null | nullOnDelete |
| location_id | FK(locations) | yes | null | nullOnDelete |
| bank_id | FK(banks) | yes | null | nullOnDelete |
| product_id | FK(products) | yes | null | nullOnDelete |
| customer_name | string | | | |
| customer_type | string | | | Values: `proprietor`, `partnership_llp`, `pvt_ltd`, `salaried` |
| customer_phone | string(20) | yes | null | |
| customer_email | string | yes | null | |
| date_of_birth | date | yes | null | |
| pan_number | string(10) | yes | null | |
| loan_amount | unsignedBigInteger | | | |
| status | string | | 'active' | Values: `active`, `completed`, `rejected`, `cancelled`, `on_hold` (see LoanDetail model constants) |
| is_sanctioned | boolean | | false | Whether loan has received sanction |
| current_stage | string | | 'inquiry' | Current workflow stage key |
| bank_name | string | yes | null | |
| roi_min | decimal(5,2) | yes | null | |
| roi_max | decimal(5,2) | yes | null | |
| total_charges | string | yes | null | |
| application_number | string | yes | null | |
| assigned_bank_employee | FK(users) | yes | null | nullOnDelete |
| due_date | date | yes | null | |
| expected_docket_date | date | yes | null | |
| rejected_at | timestamp | yes | null | |
| rejected_by | FK(users) | yes | null | nullOnDelete |
| rejected_stage | string | yes | null | Stage key where rejection occurred |
| rejection_reason | text | yes | null | |
| status_reason | text | yes | null | Reason for status change (hold/cancel) |
| status_changed_at | timestamp | yes | null | |
| status_changed_by | FK(users) | yes | null | nullOnDelete |
| created_by | FK(users) | | | cascadeOnDelete |
| assigned_advisor | FK(users) | yes | null | nullOnDelete |
| notes | text | yes | null | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |
| updated_by | FK(users) | yes | null | nullOnDelete |
| deleted_at | timestamp | yes | null | SoftDeletes |
| deleted_by | FK(users) | yes | null | nullOnDelete |

Indexes: `status`, `current_stage`, `customer_type`

### `loan_documents`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| loan_id | FK(loan_details) | | | cascadeOnDelete; indexed |
| document_name_en | string | | | |
| document_name_gu | string | yes | null | |
| is_required | boolean | | true | |
| status | string | | 'pending' | Values: `pending`, `received`, `rejected`, `waived` (see LoanDocument model constants) |
| received_date | date | yes | null | |
| received_by | FK(users) | yes | null | nullOnDelete |
| rejected_reason | text | yes | null | |
| notes | text | yes | null | |
| file_path | string | yes | null | Storage path for uploaded file |
| file_name | string | yes | null | Original filename |
| file_size | unsignedBigInteger | yes | null | Bytes |
| file_mime | string(100) | yes | null | MIME type |
| uploaded_by | FK(users) | yes | null | nullOnDelete |
| uploaded_at | timestamp | yes | null | |
| sort_order | integer | | 0 | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |
| updated_by | FK(users) | yes | null | nullOnDelete |

Index: `[loan_id, status]`

---

## Workflow Tables

### `stage_assignments`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| loan_id | FK(loan_details) | | | cascadeOnDelete |
| stage_key | string | | | indexed |
| assigned_to | FK(users) | yes | null | nullOnDelete; indexed |
| status | string | | 'pending' | Values: `pending`, `in_progress`, `completed`, `rejected`, `skipped` (see StageAssignment model constants) |
| previous_status | string | yes | null | Stores status before a soft-revert (e.g. `completed` when reverted to `in_progress`) |
| priority | string | | 'normal' | Values: `low`, `normal`, `high`, `urgent` |
| started_at | timestamp | yes | null | |
| completed_at | timestamp | yes | null | |
| completed_by | FK(users) | yes | null | nullOnDelete |
| is_parallel_stage | boolean | | false | |
| parent_stage_key | string | yes | null | indexed |
| notes | text | yes | null | Can store JSON data |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |
| updated_by | FK(users) | yes | null | nullOnDelete |

Unique: `[loan_id, stage_key]`
Indexes: `stage_key`, `assigned_to`, `status`, `parent_stage_key`

### `loan_progress`

One record per loan tracking overall progress.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| loan_id | FK(loan_details) | | | cascadeOnDelete; unique |
| total_stages | integer | | 10 | |
| completed_stages | integer | | 0 | |
| overall_percentage | decimal(5,2) | | 0 | |
| estimated_completion | date | yes | null | |
| workflow_snapshot | text | yes | null | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

### `stage_transfers`

History of stage assignment transfers between users.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| stage_assignment_id | FK(stage_assignments) | | | cascadeOnDelete |
| loan_id | FK(loan_details) | | | cascadeOnDelete |
| stage_key | string | | | |
| transferred_from | FK(users) | | | cascadeOnDelete |
| transferred_to | FK(users) | | | cascadeOnDelete |
| reason | text | yes | null | |
| transfer_type | string | | 'manual' | |
| created_at | timestamp | | CURRENT_TIMESTAMP | No updated_at |

### `stage_queries`

Two-way query system that blocks stage completion until resolved.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| stage_assignment_id | FK(stage_assignments) | | | cascadeOnDelete |
| loan_id | FK(loan_details) | | | cascadeOnDelete |
| stage_key | string | | | |
| query_text | text | | | |
| raised_by | FK(users) | | | cascadeOnDelete |
| status | string | | 'pending' | Values: `pending`, `responded`, `resolved` (see StageQuery model constants) |
| resolved_at | timestamp | yes | null | |
| resolved_by | FK(users) | yes | null | nullOnDelete |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

Indexes: `[stage_assignment_id, status]`, `loan_id`

### `query_responses`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| stage_query_id | FK(stage_queries) | | | cascadeOnDelete |
| response_text | text | | | |
| responded_by | FK(users) | | | cascadeOnDelete |
| created_at | timestamp | | CURRENT_TIMESTAMP | No updated_at |

### `product_stages`

Per-product stage configuration.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| product_id | FK(products) | | | cascadeOnDelete |
| stage_id | FK(stages) | | | cascadeOnDelete |
| is_enabled | boolean | | true | |
| default_assignee_role | string | yes | null | |
| default_user_id | FK(users) | yes | null | nullOnDelete |
| auto_skip | boolean | | false | |
| allow_skip | boolean | | true | |
| sub_actions_override | json | yes | null | Overrides stage-level sub_actions |
| sort_order | integer | yes | null | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |
| updated_by | FK(users) | yes | null | nullOnDelete |

Unique: `[product_id, stage_id]`

### `product_stage_users`

Per-product + per-branch/location user assignment for stages.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| product_stage_id | FK(product_stages) | | | cascadeOnDelete |
| branch_id | FK(branches) | yes | null | cascadeOnDelete; nullable (either branch_id or location_id) |
| location_id | FK(locations) | yes | null | nullOnDelete |
| user_id | FK(users) | | | cascadeOnDelete |
| is_default | boolean | | false | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

Unique: `[product_stage_id, branch_id]`

---

## Specialized Tables

### `valuation_details`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| loan_id | FK(loan_details) | | | cascadeOnDelete; indexed |
| valuation_type | string | | 'property' | Values: `property`, `vehicle`, `business` |
| property_address | text | yes | null | |
| latitude | string(50) | yes | null | |
| longitude | string(50) | yes | null | |
| property_type | string | yes | null | |
| land_area | string | yes | null | Renamed from `property_area` |
| land_rate | decimal(12,2) | yes | null | |
| land_valuation | unsignedBigInteger | yes | null | |
| construction_area | string | yes | null | |
| construction_rate | decimal(12,2) | yes | null | |
| construction_valuation | unsignedBigInteger | yes | null | |
| final_valuation | unsignedBigInteger | yes | null | |
| market_value | unsignedBigInteger | yes | null | |
| government_value | unsignedBigInteger | yes | null | |
| valuation_date | date | yes | null | |
| valuator_name | string | yes | null | |
| valuator_report_number | string | yes | null | |
| notes | text | yes | null | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |
| updated_by | FK(users) | yes | null | nullOnDelete |

### `disbursement_details`

One record per loan.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| loan_id | FK(loan_details) | | | cascadeOnDelete; unique |
| disbursement_type | string | | | Values: `fund_transfer`, `cheque`, `demand_draft` |
| disbursement_date | date | yes | null | |
| amount_disbursed | unsignedBigInteger | yes | null | |
| bank_account_number | string | yes | null | |
| ifsc_code | string | yes | null | |
| cheque_number | string | yes | null | |
| cheque_date | date | yes | null | |
| cheques | json | yes | null | Multiple cheque entries |
| dd_number | string | yes | null | |
| dd_date | date | yes | null | |
| is_otc | boolean | | false | |
| otc_branch | string | yes | null | |
| otc_cleared | boolean | | false | |
| otc_cleared_date | date | yes | null | |
| otc_cleared_by | FK(users) | yes | null | nullOnDelete |
| reference_number | string | yes | null | |
| notes | text | yes | null | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |
| updated_by | FK(users) | yes | null | nullOnDelete |

### `remarks`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| loan_id | FK(loan_details) | | | cascadeOnDelete; indexed |
| stage_key | string | yes | null | indexed |
| user_id | FK(users) | | | cascadeOnDelete |
| remark | text | | | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

### `shf_notifications`

In-app notification system with 60s polling.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| user_id | FK(users) | | | cascadeOnDelete |
| title | string | | | |
| message | text | | | |
| type | string | | 'info' | |
| is_read | boolean | | false | |
| loan_id | FK(loan_details) | yes | null | nullOnDelete |
| stage_key | string | yes | null | |
| link | string | yes | null | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

Indexes: `[user_id, is_read]`, `loan_id`

---

## Pivot Tables

### `bank_employees`

Bank to User assignment.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| bank_id | FK(banks) | | | cascadeOnDelete |
| user_id | FK(users) | | | cascadeOnDelete |
| is_default | boolean | | false | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

Unique: `[bank_id, user_id]`

### `user_branches`

User to Branch assignment.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| user_id | FK(users) | | | cascadeOnDelete |
| branch_id | FK(branches) | | | cascadeOnDelete |
| is_default_office_employee | boolean | | false | |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

Unique: `[user_id, branch_id]`

### `location_user`

User to Location assignment.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| location_id | FK(locations) | | | cascadeOnDelete |
| user_id | FK(users) | | | cascadeOnDelete |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

Unique: `[location_id, user_id]`

### `location_product`

Product to Location assignment.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| location_id | FK(locations) | | | cascadeOnDelete |
| product_id | FK(products) | | | cascadeOnDelete |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

Unique: `[location_id, product_id]`

### `bank_location`

Bank to Location assignment.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint (PK) | | auto | |
| bank_id | FK(banks) | | | cascadeOnDelete |
| location_id | FK(locations) | | | cascadeOnDelete |
| created_at | timestamp | yes | null | |
| updated_at | timestamp | yes | null | |

Unique: `[bank_id, location_id]`

---

## Summary of SoftDeletes Tables

- `loan_details`
- `banks`
- `branches`
- `products`
- `quotations`
- `customers`

## Summary of Audit Columns

**updated_by** (FK users, nullable): `loan_details`, `quotations`, `banks`, `branches`, `products`, `stage_assignments`, `loan_documents`, `valuation_details`, `disbursement_details`, `product_stages`

**deleted_by** (FK users, nullable): `loan_details`, `quotations`, `banks`, `branches`, `products`

**created_by** (FK users): `users` (nullable), `loan_details` (required), `customers` (nullable)

## Model Status Constants

### LoanDetail

- `STATUS_ACTIVE = 'active'`
- `STATUS_COMPLETED = 'completed'`
- `STATUS_REJECTED = 'rejected'`
- `STATUS_CANCELLED = 'cancelled'`
- `STATUS_ON_HOLD = 'on_hold'`

Customer types: `proprietor`, `partnership_llp`, `pvt_ltd`, `salaried`

### StageAssignment

- `STATUS_PENDING = 'pending'`
- `STATUS_IN_PROGRESS = 'in_progress'`
- `STATUS_COMPLETED = 'completed'`
- `STATUS_REJECTED = 'rejected'`
- `STATUS_SKIPPED = 'skipped'`

Priority values: `low`, `normal`, `high`, `urgent`

### LoanDocument

- `STATUS_PENDING = 'pending'`
- `STATUS_RECEIVED = 'received'`
- `STATUS_REJECTED = 'rejected'`
- `STATUS_WAIVED = 'waived'`

### StageQuery

- `STATUS_PENDING = 'pending'`
- `STATUS_RESPONDED = 'responded'`
- `STATUS_RESOLVED = 'resolved'`
