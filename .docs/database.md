# Database Schema

## Overview

The application uses **SQLite** as its database. Session, cache, and queue are also database-driven. All migrations use the `0001_01_01_*` prefix for core tables.

## Tables

### users
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| name | string | no | | |
| email | string | no | | unique |
| email_verified_at | timestamp | yes | null | |
| password | string | no | | bcrypt hashed |
| role | enum | no | 'staff' | super_admin, admin, staff |
| is_active | boolean | no | true | |
| created_by | FK → users | yes | null | who created this user |
| phone | string | yes | null | |
| task_role | string | yes | null | branch_manager, loan_advisor, bank_employee, office_employee, legal_advisor |
| employee_id | string | yes | null | |
| default_branch_id | FK → branches | yes | null | null on delete |
| task_bank_id | FK → banks | yes | null | only for bank_employee, null on delete |
| updated_by | FK → users | yes | null | audit column |
| remember_token | string(100) | yes | | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: unique on `email`

---

### permissions
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | bigint PK | no | |
| name | string | no | Display name |
| slug | string | no | unique, used in code |
| group | string | no | Settings, Quotations, Users, Loans, System |
| description | string | yes | |
| created_at | timestamp | yes | |
| updated_at | timestamp | yes | |

**Indexes**: unique on `slug`

**Seeded by**: `PermissionSeeder` (from `config/permissions.php`)

---

### role_permissions
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | bigint PK | no | |
| role | enum | no | super_admin, admin, staff |
| permission_id | FK → permissions | no | cascade delete |
| created_at | timestamp | yes | |
| updated_at | timestamp | yes | |

**Indexes**: unique composite on `(role, permission_id)`

---

### user_permissions
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | bigint PK | no | |
| user_id | FK → users | no | cascade delete |
| permission_id | FK → permissions | no | cascade delete |
| type | enum | no | grant, deny |
| created_at | timestamp | yes | |
| updated_at | timestamp | yes | |

**Indexes**: unique composite on `(user_id, permission_id)`

---

### activity_logs
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | bigint PK | no | |
| user_id | FK → users | yes | set null on delete |
| action | string | no | e.g., created_quotation |
| subject_type | string | yes | polymorphic model class |
| subject_id | bigint | yes | polymorphic model ID |
| properties | JSON | yes | extra context data |
| ip_address | string(45) | yes | |
| user_agent | string | yes | |
| created_at | timestamp | yes | |
| updated_at | timestamp | yes | |

---

### quotations
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| user_id | FK → users | no | | cascade delete |
| customer_name | string | no | | |
| customer_type | string | no | | proprietor, partnership_llp, pvt_ltd, salaried, all |
| loan_amount | unsigned bigint | no | | max 1,000,000,000,000 |
| pdf_filename | string | yes | null | |
| pdf_path | string | yes | null | |
| additional_notes | text | yes | null | |
| prepared_by_name | string | yes | null | |
| prepared_by_mobile | string | yes | null | |
| selected_tenures | JSON | no | | array of integers (years) |
| loan_id | FK → loan_details | yes | null | back-reference when converted, null on delete |
| updated_by | FK → users | yes | null | audit |
| deleted_by | FK → users | yes | null | audit |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |
| deleted_at | timestamp | yes | null | soft delete |

---

### quotation_banks
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| quotation_id | FK → quotations | no | | cascade delete |
| bank_name | string | no | | |
| roi_min | decimal(5,2) | no | | 0-30% |
| roi_max | decimal(5,2) | no | | >= roi_min, 0-30% |
| pf_charge | decimal(5,2) | no | 0 | Processing fee % |
| admin_charge | unsigned bigint | no | 0 | |
| stamp_notary | unsigned bigint | no | 0 | |
| registration_fee | unsigned bigint | no | 0 | |
| advocate_fees | unsigned bigint | no | 0 | |
| iom_charge | unsigned bigint | no | 0 | |
| tc_report | unsigned bigint | no | 0 | |
| extra1_name | string | yes | null | Custom charge 1 label |
| extra1_amount | unsigned bigint | no | 0 | Custom charge 1 amount |
| extra2_name | string | yes | null | Custom charge 2 label |
| extra2_amount | unsigned bigint | no | 0 | Custom charge 2 amount |
| total_charges | unsigned bigint | no | 0 | Sum of all charges |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

---

### quotation_emi

**IMPORTANT**: Table name is `quotation_emi` (NOT `quotation_emis`). The model uses `protected $table = 'quotation_emi'`.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| quotation_bank_id | FK → quotation_banks | no | | cascade delete |
| tenure_years | integer | no | | |
| monthly_emi | unsigned bigint | no | 0 | |
| total_interest | unsigned bigint | no | 0 | |
| total_payment | unsigned bigint | no | 0 | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

---

### quotation_documents
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | bigint PK | no | |
| quotation_id | FK → quotations | no | cascade delete |
| document_name_en | string | no | English name |
| document_name_gu | string | no | Gujarati name |
| created_at | timestamp | yes | |
| updated_at | timestamp | yes | |

---

### app_config
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| id | bigint PK | no | |
| config_key | string | no | unique |
| config_json | longText (JSON) | no | Structured config data |
| created_at | timestamp | yes | |
| updated_at | timestamp | yes | |

**Primary record**: `config_key = 'main'` stores the full app configuration.

---

### app_settings
| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| setting_key | string PK | no | Primary key |
| setting_value | text | yes | |

Simple key-value store. Currently used for `additional_notes`.

---

### bank_charges
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| bank_name | string | no | | |
| pf | decimal | no | | Processing fee % |
| admin | unsigned bigint | no | | Admin charge |
| stamp_notary | unsigned bigint | no | | Stamp & notary |
| registration_fee | unsigned bigint | no | | Registration fee |
| advocate | unsigned bigint | no | | Advocate fees |
| tc | unsigned bigint | no | | TC report |
| extra1_name | string | yes | null | |
| extra1_amt | string | yes | null | |
| extra2_name | string | yes | null | |
| extra2_amt | string | yes | null | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

Reference data — auto-updated when quotations are generated. Also editable via Settings > Bank Charges.

---

### Laravel System Tables

| Table | Purpose |
|-------|---------|
| `sessions` | Session storage (database driver) |
| `cache` | Cache storage (database driver) |
| `cache_locks` | Cache lock management |
| `jobs` | Queue jobs |
| `job_batches` | Job batch tracking |
| `failed_jobs` | Failed queue jobs |
| `password_reset_tokens` | Password reset tokens |

## Loan Task System Tables

### banks
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| name | string | no | | unique |
| code | string | yes | null | |
| is_active | boolean | no | true | |
| default_employee_id | FK → users | yes | null | null on delete |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |
| deleted_at | timestamp | yes | null | soft delete |
| updated_by | FK → users | yes | null | audit |
| deleted_by | FK → users | yes | null | audit |

---

### branches
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| name | string | no | | |
| code | string | yes | null | unique |
| address | text | yes | null | |
| city | string | yes | null | |
| phone | string(20) | yes | null | |
| is_active | boolean | no | true | |
| manager_id | FK → users | yes | null | null on delete |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |
| deleted_at | timestamp | yes | null | soft delete |
| updated_by | FK → users | yes | null | audit |
| deleted_by | FK → users | yes | null | audit |

---

### products
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| bank_id | FK → banks | no | | cascade delete |
| name | string | no | | unique(bank_id, name) |
| code | string | yes | null | |
| is_active | boolean | no | true | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |
| deleted_at | timestamp | yes | null | soft delete |
| updated_by | FK → users | yes | null | audit |
| deleted_by | FK → users | yes | null | audit |

---

### stages
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| stage_key | string | no | | unique |
| stage_name_en | string | no | | |
| stage_name_gu | string | yes | null | |
| sequence_order | integer | no | | |
| is_parallel | boolean | no | false | |
| parent_stage_key | string | yes | null | refs stages.stage_key |
| stage_type | string | no | 'sequential' | sequential/parallel/decision |
| default_role | string | yes | null | |
| description_en | text | yes | null | |
| description_gu | text | yes | null | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Seeded**: 25 stages (14 base + 11 optional bank-specific)

---

### user_branches
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| user_id | FK → users | no | | cascade delete |
| branch_id | FK → branches | no | | cascade delete |
| is_default_office_employee | boolean | no | false | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: unique(user_id, branch_id)

---

### loan_details
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_number | string | no | | unique, format: SHF-YYYYMM-XXXX |
| quotation_id | FK → quotations | yes | null | null on delete |
| branch_id | FK → branches | yes | null | null on delete |
| bank_id | FK → banks | yes | null | null on delete |
| product_id | FK → products | yes | null | null on delete |
| customer_name | string | no | | |
| customer_type | string | no | | proprietor, partnership_llp, pvt_ltd, salaried |
| customer_phone | string(20) | yes | null | |
| customer_email | string | yes | null | |
| loan_amount | unsigned bigint | no | | |
| status | string | no | 'active' | active/completed/rejected/cancelled/on_hold |
| current_stage | string | no | 'inquiry' | refs stages.stage_key |
| bank_name | string | yes | null | denormalized |
| roi_min | decimal(5,2) | yes | null | |
| roi_max | decimal(5,2) | yes | null | |
| total_charges | string | yes | null | |
| application_number | string | yes | null | |
| assigned_bank_employee | FK → users | yes | null | null on delete |
| due_date | date | yes | null | default: 7 days from creation |
| rejected_at | timestamp | yes | null | |
| rejected_by | FK → users | yes | null | null on delete |
| rejected_stage | string | yes | null | |
| rejection_reason | text | yes | null | |
| created_by | FK → users | no | | cascade delete |
| assigned_advisor | FK → users | yes | null | null on delete |
| notes | text | yes | null | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |
| deleted_at | timestamp | yes | null | soft delete |
| updated_by | FK → users | yes | null | audit |
| deleted_by | FK → users | yes | null | audit |

**Indexes**: unique(loan_number), index(status), index(current_stage), index(customer_type)

---

### loan_documents
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_id | FK → loan_details | no | | cascade delete |
| document_name_en | string | no | | |
| document_name_gu | string | yes | null | |
| is_required | boolean | no | true | |
| status | string | no | 'pending' | pending/received/rejected/waived |
| received_date | date | yes | null | |
| received_by | FK → users | yes | null | null on delete |
| rejected_reason | text | yes | null | |
| notes | text | yes | null | |
| sort_order | integer | no | 0 | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |
| updated_by | FK → users | yes | null | audit |

**Indexes**: index(loan_id), index(loan_id, status)

---

### stage_assignments
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_id | FK → loan_details | no | | cascade delete |
| stage_key | string | no | | refs stages.stage_key |
| assigned_to | FK → users | yes | null | null on delete |
| status | string | no | 'pending' | pending/in_progress/completed/rejected/skipped |
| priority | string | no | 'normal' | low/normal/high/urgent |
| started_at | timestamp | yes | null | |
| completed_at | timestamp | yes | null | |
| completed_by | FK → users | yes | null | null on delete |
| is_parallel_stage | boolean | no | false | |
| parent_stage_key | string | yes | null | |
| notes | text | yes | null | JSON for stage-specific data |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |
| updated_by | FK → users | yes | null | audit |

**Indexes**: unique(loan_id, stage_key), index(stage_key), index(assigned_to), index(status), index(parent_stage_key)

---

### loan_progress
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_id | FK → loan_details | no | | unique, cascade delete |
| total_stages | integer | no | 10 | |
| completed_stages | integer | no | 0 | |
| overall_percentage | decimal(5,2) | no | 0 | |
| estimated_completion | date | yes | null | |
| workflow_snapshot | text | yes | null | JSON |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

---

### stage_transfers
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| stage_assignment_id | FK → stage_assignments | no | | cascade delete |
| loan_id | FK → loan_details | no | | cascade delete |
| stage_key | string | no | | |
| transferred_from | FK → users | no | | cascade delete |
| transferred_to | FK → users | no | | cascade delete |
| reason | text | yes | null | |
| transfer_type | string | no | 'manual' | manual/auto |
| created_at | timestamp | yes | | |

**Note**: No `updated_at` — transfers are immutable records.

---

### stage_queries
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| stage_assignment_id | FK → stage_assignments | no | | cascade delete |
| loan_id | FK → loan_details | no | | cascade delete |
| stage_key | string | no | | |
| query_text | text | no | | |
| raised_by | FK → users | no | | cascade delete |
| status | string | no | 'pending' | pending/responded/resolved |
| resolved_at | timestamp | yes | null | |
| resolved_by | FK → users | yes | null | null on delete |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: index(stage_assignment_id, status), index(loan_id)

---

### query_responses
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| stage_query_id | FK → stage_queries | no | | cascade delete |
| response_text | text | no | | |
| responded_by | FK → users | no | | cascade delete |
| created_at | timestamp | yes | | |

**Note**: No `updated_at` — responses are immutable records.

---

### valuation_details
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_id | FK → loan_details | no | | cascade delete |
| valuation_type | string | no | 'property' | property/vehicle/business |
| property_address | text | yes | null | |
| property_type | string | yes | null | |
| property_area | string | yes | null | |
| market_value | unsigned bigint | yes | null | |
| government_value | unsigned bigint | yes | null | |
| valuation_date | date | yes | null | |
| valuator_name | string | yes | null | |
| valuator_report_number | string | yes | null | |
| notes | text | yes | null | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |
| updated_by | FK → users | yes | null | audit |

---

### remarks
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_id | FK → loan_details | no | | cascade delete |
| stage_key | string | yes | null | null = general remark |
| user_id | FK → users | no | | cascade delete |
| remark | text | no | | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

---

### shf_notifications
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| user_id | FK → users | no | | cascade delete |
| title | string | no | | |
| message | text | no | | |
| type | string | no | 'info' | info/success/warning/error/stage_update/assignment |
| is_read | boolean | no | false | |
| loan_id | FK → loan_details | yes | null | null on delete |
| stage_key | string | yes | null | |
| link | string | yes | null | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: index(user_id, is_read), index(loan_id)

---

### disbursement_details
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_id | FK → loan_details | no | | unique, cascade delete |
| disbursement_type | string | no | | fund_transfer/cheque/demand_draft |
| disbursement_date | date | yes | null | |
| amount_disbursed | unsigned bigint | yes | null | |
| bank_account_number | string | yes | null | for fund_transfer |
| ifsc_code | string | yes | null | for fund_transfer |
| cheque_number | string | yes | null | for cheque |
| cheque_date | date | yes | null | for cheque |
| dd_number | string | yes | null | for demand_draft |
| dd_date | date | yes | null | for demand_draft |
| is_otc | boolean | no | false | |
| otc_branch | string | yes | null | |
| otc_cleared | boolean | no | false | |
| otc_cleared_date | date | yes | null | |
| otc_cleared_by | FK → users | yes | null | null on delete |
| reference_number | string | yes | null | |
| notes | text | yes | null | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |
| updated_by | FK → users | yes | null | audit |

---

### product_stages
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| product_id | FK → products | no | | cascade delete |
| stage_id | FK → stages | no | | cascade delete |
| is_enabled | boolean | no | true | |
| default_assignee_role | string | yes | null | |
| default_user_id | FK → users | yes | null | null on delete |
| auto_skip | boolean | no | false | |
| sort_order | integer | yes | null | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |
| updated_by | FK → users | yes | null | audit |

**Indexes**: unique(product_id, stage_id)

---

### product_stage_users
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| product_stage_id | FK → product_stages | no | | cascade delete |
| branch_id | FK → branches | no | | cascade delete |
| user_id | FK → users | no | | cascade delete |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: unique(product_stage_id, branch_id)

---

### bank_employees
| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| bank_id | FK → banks | no | | cascade delete |
| user_id | FK → users | no | | cascade delete |
| is_default | boolean | no | false | |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: unique(bank_id, user_id)

---

## Soft Deletes

Tables with `deleted_at`: loan_details, banks, branches, products, quotations

Dependency protection:
- Bank: blocked if has products or active loans
- Branch: blocked if has assigned users or active loans
- Product: blocked if has active loans
- Quotation: blocked if converted to loan

## Audit Columns

Tables with `updated_by`: loan_details, quotations, banks, branches, products, stage_assignments, loan_documents, valuation_details, disbursement_details, product_stages

Tables with `deleted_by`: loan_details, quotations, banks, branches, products

Auto-filled by `App\Traits\HasAuditColumns` trait on save/soft-delete.

## Cascade Behavior

| When Deleted | Cascades To |
|-------------|-------------|
| User | user_permissions, quotations (→ banks → EMIs, documents) |
| Quotation | quotation_banks (→ quotation_emi), quotation_documents |
| QuotationBank | quotation_emi |
| Permission | role_permissions, user_permissions |
| LoanDetail | loan_documents, stage_assignments, loan_progress, stage_transfers, stage_queries, remarks, valuation_details, disbursement_details, shf_notifications |
| StageAssignment | stage_transfers, stage_queries |
| StageQuery | query_responses |
| Bank | products |
| Product | product_stages |
| ProductStage | product_stage_users |

**Note**: `activity_logs.user_id` is set to NULL on user delete (not cascaded).

## Migrations

| File | Tables Created/Modified |
|------|------------------------|
| `0001_01_01_000000_create_users_table.php` | users, password_reset_tokens, sessions |
| `0001_01_01_000001_create_cache_table.php` | cache, cache_locks |
| `0001_01_01_000002_create_jobs_table.php` | jobs, job_batches, failed_jobs |
| `0001_01_01_000003_create_permissions_tables.php` | permissions, role_permissions, user_permissions |
| `0001_01_01_000004_create_activity_logs_table.php` | activity_logs |
| `0001_01_01_000005_create_app_config_tables.php` | app_config, app_settings, bank_charges |
| `0001_01_01_000006_create_quotation_tables.php` | quotations, quotation_banks, quotation_emi, quotation_documents |
| `2026_02_26_140000_add_missing_columns...` | Added missing columns and timestamps |
| `2026_02_27_153509_rename_stamp_notary...` | Renamed stamp→stamp_notary, notary→registration_fee |
| `2026_04_06_200000_create_banks_table.php` | banks |
| `2026_04_06_200001_create_branches_table.php` | branches |
| `2026_04_06_200002_create_products_table.php` | products |
| `2026_04_06_200003_create_stages_table.php` | stages |
| `2026_04_06_200004_create_user_branches_table.php` | user_branches |
| `2026_04_06_200005_add_task_fields_to_users_table.php` | users (task_role, employee_id, default_branch_id, task_bank_id) |
| `2026_04_06_210000_create_loan_details_table.php` | loan_details |
| `2026_04_06_210001_add_loan_id_to_quotations_table.php` | quotations (loan_id) |
| `2026_04_06_220000_create_loan_documents_table.php` | loan_documents |
| `2026_04_07_084256_add_is_default_office_employee...` | user_branches (is_default_office_employee) |
| `2026_04_07_100000_create_stage_assignments_table.php` | stage_assignments |
| `2026_04_07_100001_create_loan_progress_table.php` | loan_progress |
| `2026_04_07_100002_create_stage_transfers_table.php` | stage_transfers |
| `2026_04_07_100003_create_stage_queries_table.php` | stage_queries |
| `2026_04_07_100004_create_query_responses_table.php` | query_responses |
| `2026_04_07_110000_create_valuation_details_table.php` | valuation_details |
| `2026_04_07_110001_create_remarks_table.php` | remarks |
| `2026_04_07_110002_create_notifications_table.php` | shf_notifications |
| `2026_04_07_120000_create_disbursement_details_table.php` | disbursement_details |
| `2026_04_07_120001_create_product_stages_table.php` | product_stages |
| `2026_04_07_130000_add_soft_deletes_to_tables.php` | Adds deleted_at to loan_details, banks, branches, products, quotations |
| `2026_04_07_140000_add_audit_columns_to_tables.php` | Adds updated_by/deleted_by to multiple tables |
| `2026_04_07_150000_add_default_employee_to_banks...` | banks (default_employee_id) |
| `2026_04_07_160000_add_default_role_to_stages...` | stages (default_role) |
| `2026_04_07_160001_add_manager_to_branches...` | branches (manager_id) |
| `2026_04_07_170000_change_default_role_to_json...` | stages (default_role type change) |
| `2026_04_07_180000_create_product_stage_users...` | product_stage_users |
| `2026_04_07_190000_create_bank_employees...` | bank_employees |
