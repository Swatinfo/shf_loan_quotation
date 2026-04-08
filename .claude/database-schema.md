# Database Schema Reference

## Tables

### users
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| name | string | |
| email | string | unique |
| email_verified_at | timestamp | nullable |
| password | string | bcrypt hashed |
| role | enum | super_admin, admin, staff (default: staff) |
| is_active | boolean | default: true |
| created_by | FK -> users | nullable, set null on delete |
| phone | varchar(20) | nullable |
| task_role | string | nullable -- branch_manager, loan_advisor, bank_employee, office_employee |
| employee_id | string | nullable |
| default_branch_id | FK -> branches | nullable, set null on delete |
| task_bank_id | FK -> banks | nullable, set null on delete -- only for bank_employee |
| remember_token | varchar(100) | nullable |
| timestamps | | |

### permissions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| name | string | display name |
| slug | string | unique, used in code |
| group | string | Settings, Quotations, Users, Loans, System |
| description | string | nullable |
| timestamps | | |

### role_permissions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| role | enum | super_admin, admin, staff |
| permission_id | FK -> permissions | cascade delete |
| timestamps | | |
| | | unique(role, permission_id) |

### user_permissions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | FK -> users | cascade delete |
| permission_id | FK -> permissions | cascade delete |
| type | enum | grant, deny |
| timestamps | | |
| | | unique(user_id, permission_id) |

### task_role_permissions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| task_role | string | branch_manager, loan_advisor, bank_employee, office_employee |
| permission_id | FK -> permissions | cascade delete |
| timestamps | | |
| | | unique(task_role, permission_id) |

### activity_logs
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | FK -> users | nullable, set null on delete |
| action | string | e.g., created_quotation, deleted_user |
| subject_type | string | nullable, polymorphic |
| subject_id | bigint | nullable, polymorphic |
| properties | JSON (longtext) | nullable, extra context |
| ip_address | varchar(45) | nullable |
| user_agent | text | nullable |
| timestamps | | |

**Indexes**: index(user_id), index(subject_type, subject_id), index(created_at)

### locations
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| parent_id | FK -> locations | nullable, set null on delete (self-referencing) |
| name | string | |
| type | enum | state, city (default: city) |
| code | varchar(20) | nullable |
| is_active | boolean | default: true |
| timestamps | | |

**Indexes**: unique(name, parent_id), index(parent_id)

### bank_location (pivot)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| bank_id | FK -> banks | cascade delete |
| location_id | FK -> locations | cascade delete |
| timestamps | | |

**Indexes**: unique(bank_id, location_id)

### location_user (pivot)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| location_id | FK -> locations | cascade delete |
| user_id | FK -> users | cascade delete |
| timestamps | | |

**Indexes**: unique(location_id, user_id)

### location_product (pivot)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| location_id | FK -> locations | cascade delete |
| product_id | FK -> products | cascade delete |
| timestamps | | |

**Indexes**: unique(location_id, product_id)

### quotations
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| loan_id | FK -> loan_details | nullable, set null on delete -- back-reference |
| location_id | FK -> locations | nullable, set null on delete |
| user_id | FK -> users | cascade delete |
| customer_name | string | |
| customer_type | string | proprietor, partnership_llp, pvt_ltd, salaried, all |
| loan_amount | unsigned bigint | max 1,000,000,000 |
| pdf_filename | string | nullable |
| pdf_path | string | nullable |
| additional_notes | text | nullable |
| prepared_by_name | string | nullable |
| prepared_by_mobile | string | nullable |
| selected_tenures | JSON (longtext) | array of integers (years) |
| updated_by | FK -> users | nullable, set null on delete |
| deleted_by | FK -> users | nullable, set null on delete |
| timestamps | | |
| deleted_at | timestamp | nullable (soft delete) |

**Indexes**: index(user_id), index(created_at), index(loan_id), index(location_id)

### quotation_banks
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| quotation_id | FK -> quotations | cascade delete |
| bank_name | string | |
| roi_min | decimal(5,2) | default 0 |
| roi_max | decimal(5,2) | default 0, must be >= roi_min |
| pf_charge | decimal(5,2) | default 0 |
| admin_charge | unsignedBigInteger | default 0 |
| stamp_notary | unsignedBigInteger | default 0 |
| registration_fee | unsignedBigInteger | default 0 |
| advocate_fees | unsignedBigInteger | default 0 |
| iom_charge | unsignedBigInteger | default 0 |
| tc_report | unsignedBigInteger | default 0 |
| extra1_name | string | nullable |
| extra1_amount | unsignedBigInteger | default 0 |
| extra2_name | string | nullable |
| extra2_amount | unsignedBigInteger | default 0 |
| total_charges | unsignedBigInteger | default 0 |
| timestamps | | |

### quotation_emi (NOTE: not quotation_emis)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| quotation_bank_id | FK -> quotation_banks | cascade delete |
| tenure_years | integer | |
| monthly_emi | unsignedBigInteger | default 0 |
| total_interest | unsignedBigInteger | default 0 |
| total_payment | unsignedBigInteger | default 0 |
| timestamps | | |

### quotation_documents
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| quotation_id | FK -> quotations | cascade delete |
| document_name_en | string | English name |
| document_name_gu | string | nullable, Gujarati name |
| timestamps | | |

### bank_charges (reference data)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| bank_name | string | |
| pf | decimal(5,2) | default 0 |
| admin | unsignedBigInteger | default 0 |
| stamp_notary | unsignedBigInteger | default 0 |
| registration_fee | unsignedBigInteger | default 0 |
| advocate | unsignedBigInteger | default 0 |
| tc | unsignedBigInteger | default 0 |
| extra1_name | string | nullable |
| extra1_amt | unsignedBigInteger | default 0 |
| extra2_name | string | nullable |
| extra2_amt | unsignedBigInteger | default 0 |
| timestamps | | |

### app_config
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| config_key | string | unique |
| config_json | JSON (longtext) | nullable, structured config data |
| timestamps | | |

### app_settings
| Column | Type | Notes |
|--------|------|-------|
| setting_key | varchar(255) PK | primary key |
| setting_value | text | nullable |
| updated_at | timestamp | nullable |

---

## Loan Task System Tables

### banks
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| name | string | unique |
| code | string | nullable |
| is_active | boolean | default: true |
| default_employee_id | FK -> users | nullable, set null on delete |
| updated_by | FK -> users | nullable, set null on delete |
| deleted_by | FK -> users | nullable, set null on delete |
| timestamps | | |
| deleted_at | timestamp | nullable (soft delete) |

### branches
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| name | string | |
| code | string | nullable, unique |
| address | text | nullable |
| city | string | nullable |
| phone | varchar(20) | nullable |
| is_active | boolean | default: true |
| manager_id | FK -> users | nullable, set null on delete |
| location_id | FK -> locations | nullable, set null on delete |
| updated_by | FK -> users | nullable, set null on delete |
| deleted_by | FK -> users | nullable, set null on delete |
| timestamps | | |
| deleted_at | timestamp | nullable (soft delete) |

### products
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| bank_id | FK -> banks | cascade delete |
| name | string | unique(bank_id, name) |
| code | string | nullable |
| is_active | boolean | default: true |
| updated_by | FK -> users | nullable, set null on delete |
| deleted_by | FK -> users | nullable, set null on delete |
| timestamps | | |
| deleted_at | timestamp | nullable (soft delete) |

### stages
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| stage_key | string | unique |
| is_enabled | boolean | default: true |
| stage_name_en | string | |
| stage_name_gu | string | nullable |
| sequence_order | integer | |
| is_parallel | boolean | default: false |
| parent_stage_key | string | nullable, refs stages.stage_key |
| stage_type | string | default: 'sequential' (sequential/parallel/decision) |
| description_en | text | nullable |
| description_gu | text | nullable |
| default_role | string | nullable, JSON array of task roles |
| sub_actions | JSON (longtext) | nullable, JSON-validated |
| timestamps | | |

**Indexes**: unique(stage_key), index(sequence_order), index(parent_stage_key)

**Seeded stages (16 total)**:

| stage_key | Name | Seq | Type | Parent |
|-----------|------|-----|------|--------|
| inquiry | Loan Inquiry | 1 | sequential | -- |
| document_selection | Document Selection | 2 | sequential | -- |
| document_collection | Document Collection | 3 | sequential | -- |
| parallel_processing | Parallel Processing | 4 | parallel | -- |
| app_number | Application Number | 4 | sequential | parallel_processing |
| bsm_osv | BSM/OSV Approval | 4 | sequential | parallel_processing |
| legal_verification | Legal Verification | 4 | sequential | parallel_processing |
| technical_valuation | Technical Valuation | 4 | sequential | parallel_processing |
| property_valuation | Property Valuation | 4 | sequential | parallel_processing |
| rate_pf | Rate & PF Request | 5 | sequential | -- |
| sanction | Sanction Letter | 6 | sequential | -- |
| docket | Docket Login | 7 | sequential | -- |
| kfs | KFS Generation | 8 | sequential | -- |
| esign | E-Sign & eNACH | 9 | sequential | -- |
| disbursement | Disbursement | 10 | decision | -- |
| otc_clearance | OTC Clearance | 11 | sequential | -- |

### user_branches
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | FK -> users | cascade delete |
| branch_id | FK -> branches | cascade delete |
| is_default_office_employee | boolean | default: false |
| timestamps | | |

**Indexes**: unique(user_id, branch_id)

### loan_details
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| loan_number | string | unique, format: SHF-YYYYMM-XXXX |
| quotation_id | FK -> quotations | nullable, set null on delete |
| branch_id | FK -> branches | nullable, set null on delete |
| location_id | FK -> locations | nullable, set null on delete |
| bank_id | FK -> banks | nullable, set null on delete |
| product_id | FK -> products | nullable, set null on delete |
| customer_name | string | |
| customer_type | string | proprietor, partnership_llp, pvt_ltd, salaried |
| customer_phone | varchar(20) | nullable |
| customer_email | string | nullable |
| loan_amount | unsignedBigInteger | |
| status | string | default: 'active' (active/completed/rejected/cancelled/on_hold) |
| current_stage | string | default: 'inquiry', refs stages.stage_key |
| bank_name | string | nullable, denormalized |
| roi_min | decimal(5,2) | nullable |
| roi_max | decimal(5,2) | nullable |
| total_charges | string | nullable |
| application_number | string | nullable |
| assigned_bank_employee | FK -> users | nullable, set null on delete |
| due_date | date | nullable, default: 7 days from creation |
| rejected_at | timestamp | nullable |
| rejected_by | FK -> users | nullable, set null on delete |
| rejected_stage | string | nullable |
| rejection_reason | text | nullable |
| created_by | FK -> users | cascade delete |
| assigned_advisor | FK -> users | nullable, set null on delete |
| notes | text | nullable |
| updated_by | FK -> users | nullable, set null on delete |
| deleted_by | FK -> users | nullable, set null on delete |
| timestamps | | |
| deleted_at | timestamp | nullable (soft delete) |

**Indexes**: unique(loan_number), index(status), index(current_stage), index(customer_type), index(location_id), index(quotation_id), index(branch_id), index(bank_id), index(product_id)

### loan_documents
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| loan_id | FK -> loan_details | cascade delete |
| document_name_en | string | |
| document_name_gu | string | nullable |
| is_required | boolean | default: true |
| status | string | default: 'pending' (pending/received/rejected/waived) |
| received_date | date | nullable |
| received_by | FK -> users | nullable, set null on delete |
| rejected_reason | text | nullable |
| notes | text | nullable |
| file_path | string | nullable, storage path of uploaded file |
| file_name | string | nullable, original file name |
| file_size | unsignedBigInteger | nullable, size in bytes |
| file_mime | varchar(100) | nullable, MIME type |
| uploaded_by | FK -> users | nullable, set null on delete |
| uploaded_at | timestamp | nullable |
| sort_order | integer | default: 0 |
| updated_by | FK -> users | nullable, set null on delete |
| timestamps | | |

**Indexes**: index(loan_id), index(loan_id, status)

### stage_assignments
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| loan_id | FK -> loan_details | cascade delete |
| stage_key | string | refs stages.stage_key |
| assigned_to | FK -> users | nullable, set null on delete |
| status | string | default: 'pending' (pending/in_progress/completed/rejected/skipped) |
| priority | string | default: 'normal' (low/normal/high/urgent) |
| started_at | timestamp | nullable |
| completed_at | timestamp | nullable |
| completed_by | FK -> users | nullable, set null on delete |
| is_parallel_stage | boolean | default: false |
| parent_stage_key | string | nullable |
| notes | text | nullable, JSON for stage-specific data |
| updated_by | FK -> users | nullable, set null on delete |
| timestamps | | |

**Indexes**: unique(loan_id, stage_key), index(stage_key), index(assigned_to), index(status), index(parent_stage_key)

### loan_progress
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| loan_id | FK -> loan_details | unique, cascade delete |
| total_stages | integer | default: 10 |
| completed_stages | integer | default: 0 |
| overall_percentage | decimal(5,2) | default: 0 |
| estimated_completion | date | nullable |
| workflow_snapshot | text | nullable, JSON |
| timestamps | | |

### stage_transfers
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| stage_assignment_id | FK -> stage_assignments | cascade delete |
| loan_id | FK -> loan_details | cascade delete |
| stage_key | string | |
| transferred_from | FK -> users | cascade delete |
| transferred_to | FK -> users | cascade delete |
| reason | text | nullable |
| transfer_type | string | default: 'manual' (manual/auto) |
| created_at | timestamp | default: current_timestamp() |

### stage_queries
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| stage_assignment_id | FK -> stage_assignments | cascade delete |
| loan_id | FK -> loan_details | cascade delete |
| stage_key | string | |
| query_text | text | |
| raised_by | FK -> users | cascade delete |
| status | string | default: 'pending' (pending/responded/resolved) |
| resolved_at | timestamp | nullable |
| resolved_by | FK -> users | nullable, set null on delete |
| timestamps | | |

**Indexes**: index(stage_assignment_id, status), index(loan_id)

### query_responses
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| stage_query_id | FK -> stage_queries | cascade delete |
| response_text | text | |
| responded_by | FK -> users | cascade delete |
| created_at | timestamp | default: current_timestamp() |

### valuation_details
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| loan_id | FK -> loan_details | cascade delete |
| valuation_type | string | default: 'property' (property/vehicle/business) |
| property_address | text | nullable |
| latitude | varchar(50) | nullable |
| longitude | varchar(50) | nullable |
| property_type | string | nullable |
| land_area | string | nullable |
| land_rate | decimal(12,2) | nullable |
| land_valuation | unsignedBigInteger | nullable |
| construction_area | string | nullable |
| construction_rate | decimal(12,2) | nullable |
| construction_valuation | unsignedBigInteger | nullable |
| final_valuation | unsignedBigInteger | nullable |
| market_value | unsignedBigInteger | nullable |
| government_value | unsignedBigInteger | nullable |
| valuation_date | date | nullable |
| valuator_name | string | nullable |
| valuator_report_number | string | nullable |
| notes | text | nullable |
| updated_by | FK -> users | nullable, set null on delete |
| timestamps | | |

**Indexes**: index(loan_id)

### remarks
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| loan_id | FK -> loan_details | cascade delete |
| stage_key | string | nullable (null = general remark) |
| user_id | FK -> users | cascade delete |
| remark | text | |
| timestamps | | |

**Indexes**: index(loan_id), index(stage_key)

### shf_notifications
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| user_id | FK -> users | cascade delete |
| title | string | |
| message | text | |
| type | string | default: 'info' (info/success/warning/error/stage_update/assignment) |
| is_read | boolean | default: false |
| loan_id | FK -> loan_details | nullable, set null on delete |
| stage_key | string | nullable |
| link | string | nullable |
| timestamps | | |

**Indexes**: index(user_id, is_read), index(loan_id)

### disbursement_details
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| loan_id | FK -> loan_details | unique, cascade delete |
| disbursement_type | string | fund_transfer/cheque |
| disbursement_date | date | nullable |
| amount_disbursed | unsignedBigInteger | nullable |
| bank_account_number | string | nullable (fund_transfer) |
| ifsc_code | string | nullable (fund_transfer) |
| cheque_number | string | nullable (single cheque) |
| cheque_date | date | nullable (single cheque) |
| cheques | JSON (longtext) | nullable, JSON-validated, array of cheque objects |
| dd_number | string | nullable (legacy) |
| dd_date | date | nullable (legacy) |
| is_otc | boolean | default: false |
| otc_branch | string | nullable |
| otc_cleared | boolean | default: false |
| otc_cleared_date | date | nullable |
| otc_cleared_by | FK -> users | nullable, set null on delete |
| reference_number | string | nullable |
| notes | text | nullable |
| updated_by | FK -> users | nullable, set null on delete |
| timestamps | | |

### product_stages
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| product_id | FK -> products | cascade delete |
| stage_id | FK -> stages | cascade delete |
| is_enabled | boolean | default: true |
| default_assignee_role | string | nullable |
| default_user_id | FK -> users | nullable, set null on delete |
| auto_skip | boolean | default: false |
| allow_skip | boolean | default: true |
| sub_actions_override | JSON (longtext) | nullable, JSON-validated |
| sort_order | integer | nullable |
| updated_by | FK -> users | nullable, set null on delete |
| timestamps | | |

**Indexes**: unique(product_id, stage_id)

### product_stage_users
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| product_stage_id | FK -> product_stages | cascade delete |
| branch_id | FK -> branches | nullable, cascade delete |
| location_id | FK -> locations | nullable, set null on delete |
| user_id | FK -> users | cascade delete |
| is_default | boolean | default: false |
| timestamps | | |

**Indexes**: unique(product_stage_id, branch_id)

### bank_employees
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| bank_id | FK -> banks | cascade delete |
| user_id | FK -> users | cascade delete |
| is_default | boolean | default: false |
| timestamps | | |

**Indexes**: unique(bank_id, user_id)

---

## Laravel Framework Tables

### sessions
| Column | Type | Notes |
|--------|------|-------|
| id | varchar(255) PK | |
| user_id | bigint unsigned | nullable |
| ip_address | varchar(45) | nullable |
| user_agent | text | nullable |
| payload | longtext | |
| last_activity | integer | |

### cache / cache_locks
| Column | Type | Notes |
|--------|------|-------|
| key | varchar(255) PK | |
| value | mediumtext | |
| expiration | integer | |
| owner | varchar(255) | cache_locks only |

### password_reset_tokens
| Column | Type | Notes |
|--------|------|-------|
| email | varchar(255) PK | |
| token | string | |
| created_at | timestamp | nullable |

### jobs
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| queue | string | |
| payload | longtext | |
| attempts | tinyint unsigned | |
| reserved_at | int unsigned | nullable |
| available_at | int unsigned | |
| created_at | int unsigned | |

### job_batches
| Column | Type | Notes |
|--------|------|-------|
| id | varchar(255) PK | |
| name | string | |
| total_jobs | integer | |
| pending_jobs | integer | |
| failed_jobs | integer | |
| failed_job_ids | longtext | |
| options | mediumtext | nullable |
| cancelled_at | integer | nullable |
| created_at | integer | |
| finished_at | integer | nullable |

### failed_jobs
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | auto-increment |
| uuid | string | unique |
| connection | text | |
| queue | text | |
| payload | longtext | |
| exception | longtext | |
| failed_at | timestamp | default: current_timestamp() |

### migrations
| Column | Type | Notes |
|--------|------|-------|
| id | int unsigned PK | auto-increment |
| migration | string | |
| batch | integer | |

---

## All 36 Permissions

| Slug | Group | Super Admin | Admin | Staff |
|------|-------|-------------|-------|-------|
| view_settings | Settings | yes | yes | no |
| edit_company_info | Settings | yes | yes | no |
| edit_banks | Settings | yes | yes | no |
| edit_tenures | Settings | yes | yes | no |
| edit_documents | Settings | yes | yes | no |
| edit_charges | Settings | yes | yes | no |
| edit_services | Settings | yes | yes | no |
| edit_gst | Settings | yes | yes | no |
| create_quotation | Quotations | yes | yes | yes |
| generate_pdf | Quotations | yes | yes | yes |
| view_all_quotations | Quotations | yes | yes | no |
| view_own_quotations | Quotations | yes | yes | yes |
| download_pdf | Quotations | yes | yes | yes |
| delete_quotations | Quotations | yes | yes | no |
| view_users | Users | yes | yes | no |
| create_users | Users | yes | yes | no |
| edit_users | Users | yes | yes | no |
| delete_users | Users | yes | no | no |
| assign_roles | Users | yes | yes | no |
| convert_to_loan | Loans | yes | yes | yes |
| view_loans | Loans | yes | yes | yes |
| view_all_loans | Loans | yes | yes | no |
| create_loan | Loans | yes | yes | yes |
| edit_loan | Loans | yes | yes | yes |
| delete_loan | Loans | yes | yes | no |
| manage_loan_documents | Loans | yes | yes | yes |
| manage_loan_stages | Loans | yes | yes | yes |
| skip_loan_stages | Loans | yes | yes | yes |
| add_remarks | Loans | yes | yes | yes |
| manage_workflow_config | Loans | yes | yes | no |
| upload_loan_documents | Loans | yes | yes | yes |
| download_loan_documents | Loans | yes | yes | yes |
| delete_loan_files | Loans | yes | yes | yes |
| manage_permissions | System | yes | no | no |
| view_activity_log | System | yes | yes | no |
| change_own_password | System | yes | yes | yes |

*super_admin always has all permissions (bypass in PermissionService)*

*Permission groups: Settings, Quotations, Users, Loans, System (5 groups)*

---

## Task Roles (4 roles)

- `branch_manager` -- manages branch operations
- `loan_advisor` -- handles loan advisory
- `bank_employee` -- bank-side processing (linked to specific bank via task_bank_id)
- `office_employee` -- internal office operations

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
