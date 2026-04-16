# Database Schema Reference

Complete schema for all tables in the SHF application (SQLite).

## System Tables

### users
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| name | string | |
| email | string | unique |
| email_verified_at | timestamp | nullable |
| password | string | |
| is_active | boolean | default true |
| created_by | bigint | FK users, nullable, nullOnDelete |
| phone | string(20) | nullable |
| employee_id | string | nullable |
| default_branch_id | bigint | FK branches, nullable, nullOnDelete |
| task_bank_id | bigint | FK banks, nullable, nullOnDelete |
| remember_token | text | nullable |
| created_at, updated_at | timestamps | |

### sessions
| Column | Type | Constraints |
|--------|------|-------------|
| id | string | PK |
| user_id | bigint | FK, nullable, index |
| ip_address | string(45) | nullable |
| user_agent | text | nullable |
| payload | longText | |
| last_activity | integer | index |

### password_reset_tokens
| Column | Type | Constraints |
|--------|------|-------------|
| email | string | PK |
| token | string | |
| created_at | timestamp | nullable |

### cache / cache_locks
Standard Laravel cache tables.

### jobs / job_batches / failed_jobs
Standard Laravel queue tables.

---

## Permissions & Roles

### permissions
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| name | string | |
| slug | string | unique |
| group | string | |
| description | string | nullable |
| created_at, updated_at | timestamps | |

### roles
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| name | string | |
| slug | string | unique |
| description | string | nullable |
| can_be_advisor | boolean | default false |
| is_system | boolean | default false |
| created_at, updated_at | timestamps | |

**Seeded roles:** super_admin (system), admin (system), branch_manager (advisor), bdh (advisor), loan_advisor (advisor), bank_employee, office_employee

### role_user (pivot)
| Column | Type | Constraints |
|--------|------|-------------|
| user_id | bigint | FK users, cascadeOnDelete |
| role_id | bigint | FK roles, cascadeOnDelete |
| **PK** | (user_id, role_id) | |

### role_permission (pivot)
| Column | Type | Constraints |
|--------|------|-------------|
| role_id | bigint | FK roles, cascadeOnDelete |
| permission_id | bigint | FK permissions, cascadeOnDelete |
| **PK** | (role_id, permission_id) | |

### user_permissions
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| user_id | bigint | FK users, cascadeOnDelete |
| permission_id | bigint | FK permissions, cascadeOnDelete |
| type | enum | 'grant' or 'deny' |
| created_at, updated_at | timestamps | |
| **Unique** | (user_id, permission_id) | |

### task_role_permissions
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| task_role | string | |
| permission_id | bigint | FK permissions, cascadeOnDelete |
| created_at, updated_at | timestamps | |
| **Unique** | (task_role, permission_id) | |

---

## Activity & Audit

### activity_logs
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| user_id | bigint | FK users, nullable, nullOnDelete |
| action | string | |
| subject_type | string | nullable |
| subject_id | unsignedBigInteger | nullable |
| properties | json | nullable |
| ip_address | string(45) | nullable |
| user_agent | text | nullable |
| created_at, updated_at | timestamps | |

**Indexes:** (subject_type, subject_id), user_id, created_at

---

## Configuration

### app_config
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| config_key | string | unique |
| config_json | longText | nullable |
| created_at, updated_at | timestamps | |

### app_settings
| Column | Type | Constraints |
|--------|------|-------------|
| setting_key | string | PK |
| setting_value | text | nullable |
| updated_at | timestamp | nullable |

### bank_charges
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
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
| created_at, updated_at | timestamps | |

---

## Quotation Tables

### quotations
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| user_id | bigint | FK users, cascadeOnDelete |
| loan_id | bigint | FK loan_details, nullable, nullOnDelete |
| customer_name | string | |
| customer_type | string | proprietor/partnership_llp/pvt_ltd/salaried/all |
| loan_amount | unsignedBigInteger | |
| pdf_filename | string | nullable |
| pdf_path | string | nullable |
| additional_notes | text | nullable |
| prepared_by_name | string | nullable |
| prepared_by_mobile | string | nullable |
| selected_tenures | json | nullable |
| location_id | bigint | FK locations, nullable, nullOnDelete |
| branch_id | bigint | FK branches, nullable, nullOnDelete |
| deleted_at | timestamp | nullable (soft delete) |
| created_by | bigint | FK users, nullable (audit) |
| updated_by | bigint | FK users, nullable (audit) |
| deleted_by | bigint | FK users, nullable (audit) |
| created_at, updated_at | timestamps | |

### quotation_banks
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| quotation_id | bigint | FK quotations, cascadeOnDelete |
| bank_name | string | |
| roi_min | decimal(5,2) | default 0 |
| roi_max | decimal(5,2) | default 0 |
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
| created_at, updated_at | timestamps | |

### quotation_emi
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| quotation_bank_id | bigint | FK quotation_banks, cascadeOnDelete |
| tenure_years | integer | |
| monthly_emi | unsignedBigInteger | default 0 |
| total_interest | unsignedBigInteger | default 0 |
| total_payment | unsignedBigInteger | default 0 |
| created_at, updated_at | timestamps | |

### quotation_documents
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| quotation_id | bigint | FK quotations, cascadeOnDelete |
| document_name_en | string | |
| document_name_gu | string | nullable |
| created_at, updated_at | timestamps | |

---

## Location & Geography

### locations
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| parent_id | bigint | FK locations, nullable, nullOnDelete |
| name | string | |
| type | enum | 'state' or 'city', default 'city' |
| code | string(20) | nullable |
| is_active | boolean | default true |
| created_at, updated_at | timestamps | |
| **Unique** | (name, parent_id) | |

### location_user (pivot)
- location_id FK locations, user_id FK users. Unique (location_id, user_id).

### location_product (pivot)
- location_id FK locations, product_id FK products. Unique (location_id, product_id).

---

## Banks & Branches

### banks
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| name | string | unique |
| code | string | nullable |
| is_active | boolean | default true |
| deleted_at | timestamp | nullable (soft delete) |
| created_by, updated_by, deleted_by | bigint | FK users, nullable (audit) |
| created_at, updated_at | timestamps | |

### bank_employees (pivot)
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| bank_id | bigint | FK banks, cascadeOnDelete |
| user_id | bigint | FK users, cascadeOnDelete |
| is_default | boolean | default false |
| location_id | bigint | FK locations, nullable, nullOnDelete |
| created_at, updated_at | timestamps | |
| **Unique** | (bank_id, user_id, location_id) | |

### bank_location (pivot)
- bank_id FK banks, location_id FK locations. Unique (bank_id, location_id).

### branches
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| name | string | |
| code | string | nullable, unique |
| address | text | nullable |
| city | string | nullable |
| phone | string(20) | nullable |
| is_active | boolean | default true |
| manager_id | bigint | FK users, nullable, nullOnDelete |
| location_id | bigint | FK locations, nullable, nullOnDelete |
| deleted_at | timestamp | nullable (soft delete) |
| created_by, updated_by, deleted_by | bigint | FK users, nullable (audit) |
| created_at, updated_at | timestamps | |

### user_branches (pivot)
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| user_id | bigint | FK users, cascadeOnDelete |
| branch_id | bigint | FK branches, cascadeOnDelete |
| is_default_office_employee | boolean | default false |
| created_at, updated_at | timestamps | |
| **Unique** | (user_id, branch_id) | |

---

## Products & Stages

### products
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| bank_id | bigint | FK banks, cascadeOnDelete |
| name | string | |
| code | string | nullable |
| is_active | boolean | default true |
| deleted_at | timestamp | nullable (soft delete) |
| created_by, updated_by, deleted_by | bigint | FK users, nullable (audit) |
| created_at, updated_at | timestamps | |
| **Unique** | (bank_id, name) | |

### stages
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| stage_key | string | unique |
| is_enabled | boolean | default true |
| stage_name_en | string | |
| stage_name_gu | string | nullable |
| sequence_order | integer | index |
| is_parallel | boolean | default false |
| parent_stage_key | string | nullable, index |
| stage_type | string | default 'sequential' (sequential/parallel/decision) |
| description_en | text | nullable |
| description_gu | text | nullable |
| default_role | json | nullable (array of role slugs) |
| sub_actions | json | nullable (array of action objects) |
| created_at, updated_at | timestamps | |

**Stage keys:** inquiry, document_selection, document_collection, parallel_processing (parent), app_number (sub), bsm_osv (sub), legal_verification (sub), technical_valuation (sub), sanction_decision, rate_pf, sanction, docket, kfs, esign, disbursement, otc_clearance

### product_stages
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| product_id | bigint | FK products, cascadeOnDelete |
| stage_id | bigint | FK stages, cascadeOnDelete |
| is_enabled | boolean | default true |
| default_assignee_role | string | nullable |
| default_user_id | bigint | FK users, nullable, nullOnDelete |
| auto_skip | boolean | default false |
| allow_skip | boolean | default false |
| sort_order | integer | nullable |
| sub_actions_override | json | nullable |
| created_by, updated_by | bigint | FK users, nullable (audit) |
| created_at, updated_at | timestamps | |
| **Unique** | (product_id, stage_id) | |

### product_stage_users
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| product_stage_id | bigint | FK product_stages, cascadeOnDelete |
| branch_id | bigint | FK branches, nullable, cascadeOnDelete |
| location_id | bigint | FK locations, nullable, nullOnDelete |
| user_id | bigint | FK users, cascadeOnDelete |
| is_default | boolean | default false |
| created_at, updated_at | timestamps | |

---

## Loan Tables

### loan_details
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| loan_number | string | unique (SHF-YYYYMM-XXXX) |
| quotation_id | bigint | FK quotations, nullable, nullOnDelete |
| customer_id | bigint | FK customers, nullable, nullOnDelete |
| branch_id | bigint | FK branches, nullable, nullOnDelete |
| bank_id | bigint | FK banks, nullable, nullOnDelete |
| product_id | bigint | FK products, nullable, nullOnDelete |
| location_id | bigint | FK locations, nullable, nullOnDelete |
| customer_name | string | |
| customer_type | string | proprietor/partnership_llp/pvt_ltd/salaried |
| customer_phone | string(20) | nullable |
| customer_email | string | nullable |
| date_of_birth | date | nullable |
| pan_number | string(10) | nullable |
| loan_amount | unsignedBigInteger | |
| status | string | default 'active' (active/completed/rejected/cancelled/on_hold) |
| is_sanctioned | boolean | default false |
| current_stage | string | default 'inquiry' |
| bank_name | string | nullable |
| roi_min | decimal(5,2) | nullable |
| roi_max | decimal(5,2) | nullable |
| total_charges | string | nullable |
| application_number | string | nullable |
| assigned_bank_employee | bigint | FK users, nullable, nullOnDelete |
| due_date | date | nullable |
| expected_docket_date | date | nullable |
| rejected_at | timestamp | nullable |
| rejected_by | bigint | FK users, nullable, nullOnDelete |
| rejected_stage | string | nullable |
| rejection_reason | text | nullable |
| status_reason | text | nullable |
| status_changed_at | timestamp | nullable |
| status_changed_by | bigint | FK users, nullable, nullOnDelete |
| assigned_advisor | bigint | FK users, nullable, nullOnDelete |
| notes | text | nullable |
| deleted_at | timestamp | nullable (soft delete) |
| created_by | bigint | FK users, cascadeOnDelete |
| updated_by, deleted_by | bigint | FK users, nullable (audit) |
| created_at, updated_at | timestamps | |

**Indexes:** status, current_stage, customer_type

### customers
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| customer_name | string | |
| mobile | string(20) | nullable |
| email | string | nullable |
| date_of_birth | date | nullable |
| pan_number | string(10) | nullable |
| created_by, updated_by, deleted_by | bigint | FK users, nullable (audit) |
| deleted_at | timestamp | nullable (soft delete) |
| created_at, updated_at | timestamps | |

---

## Loan Workflow Tables

### stage_assignments
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| loan_id | bigint | FK loan_details, cascadeOnDelete |
| stage_key | string | |
| assigned_to | bigint | FK users, nullable, nullOnDelete |
| status | string | default 'pending' (pending/in_progress/completed/rejected/skipped) |
| previous_status | string | nullable |
| priority | string | default 'normal' (low/normal/high/urgent) |
| started_at | timestamp | nullable |
| completed_at | timestamp | nullable |
| completed_by | bigint | FK users, nullable, nullOnDelete |
| is_parallel_stage | boolean | default false |
| parent_stage_key | string | nullable |
| notes | text | nullable (JSON) |
| created_by, updated_by | bigint | FK users, nullable (audit) |
| created_at, updated_at | timestamps | |
| **Unique** | (loan_id, stage_key) | |

### stage_transfers
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| stage_assignment_id | bigint | FK stage_assignments, cascadeOnDelete |
| loan_id | bigint | FK loan_details, cascadeOnDelete |
| stage_key | string | |
| transferred_from | bigint | FK users, cascadeOnDelete |
| transferred_to | bigint | FK users, cascadeOnDelete |
| reason | text | nullable |
| transfer_type | string | default 'manual' |
| created_at | timestamp | useCurrent |

### stage_queries
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| stage_assignment_id | bigint | FK stage_assignments, cascadeOnDelete |
| loan_id | bigint | FK loan_details, cascadeOnDelete |
| stage_key | string | |
| query_text | text | |
| raised_by | bigint | FK users, cascadeOnDelete |
| status | string | default 'pending' (pending/responded/resolved) |
| resolved_at | timestamp | nullable |
| resolved_by | bigint | FK users, nullable, nullOnDelete |
| created_at, updated_at | timestamps | |

### query_responses
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| stage_query_id | bigint | FK stage_queries, cascadeOnDelete |
| response_text | text | |
| responded_by | bigint | FK users, cascadeOnDelete |
| created_at | timestamp | useCurrent |

### loan_progress
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| loan_id | bigint | unique, FK loan_details, cascadeOnDelete |
| total_stages | integer | default 10 |
| completed_stages | integer | default 0 |
| overall_percentage | decimal(5,2) | default 0 |
| estimated_completion | date | nullable |
| workflow_snapshot | text | nullable (JSON array) |
| created_at, updated_at | timestamps | |

---

## Loan Documents & Details

### loan_documents
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| loan_id | bigint | FK loan_details, cascadeOnDelete |
| document_name_en | string | |
| document_name_gu | string | nullable |
| is_required | boolean | default true |
| status | string | default 'pending' (pending/received/rejected/waived) |
| received_date | date | nullable |
| received_by | bigint | FK users, nullable, nullOnDelete |
| rejected_reason | text | nullable |
| notes | text | nullable |
| sort_order | integer | default 0 |
| file_path | string | nullable |
| file_name | string | nullable |
| file_size | unsignedBigInteger | nullable |
| file_mime | string(100) | nullable |
| uploaded_by | bigint | FK users, nullable, nullOnDelete |
| uploaded_at | timestamp | nullable |
| created_by, updated_by | bigint | FK users, nullable (audit) |
| created_at, updated_at | timestamps | |

### valuation_details
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| loan_id | bigint | FK loan_details, cascadeOnDelete |
| valuation_type | string | default 'property' (property/vehicle/business) |
| property_address | text | nullable |
| landmark | string(255) | nullable |
| property_type | string | nullable (residential_bunglow/residential_flat/commercial/industrial/land/mixed) |
| latitude | string(50) | nullable |
| longitude | string(50) | nullable |
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
| created_by, updated_by | bigint | FK users, nullable (audit) |
| created_at, updated_at | timestamps | |

### remarks
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| loan_id | bigint | FK loan_details, cascadeOnDelete |
| stage_key | string | nullable |
| user_id | bigint | FK users, cascadeOnDelete |
| remark | text | |
| created_at, updated_at | timestamps | |

### disbursement_details
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| loan_id | bigint | unique, FK loan_details, cascadeOnDelete |
| disbursement_type | string | fund_transfer/cheque |
| disbursement_date | date | nullable |
| amount_disbursed | unsignedBigInteger | nullable |
| bank_account_number | string | nullable |
| ifsc_code | string | nullable |
| cheque_number | string | nullable |
| cheque_date | date | nullable |
| cheques | json | nullable (array of cheque objects) |
| dd_number | string | nullable |
| dd_date | date | nullable |
| is_otc | boolean | default false |
| otc_branch | string | nullable |
| otc_cleared | boolean | default false |
| otc_cleared_date | date | nullable |
| otc_cleared_by | bigint | FK users, nullable, nullOnDelete |
| reference_number | string | nullable |
| notes | text | nullable |
| created_by, updated_by | bigint | FK users, nullable (audit) |
| created_at, updated_at | timestamps | |

---

## Notifications

### shf_notifications
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| user_id | bigint | FK users, cascadeOnDelete |
| title | string | |
| message | text | |
| type | string | default 'info' (info/success/warning/error/stage_update/assignment) |
| is_read | boolean | default false |
| loan_id | bigint | FK loan_details, nullable, nullOnDelete |
| stage_key | string | nullable |
| link | string | nullable |
| created_at, updated_at | timestamps | |

**Indexes:** (user_id, is_read), loan_id

---

## General Tasks

### general_tasks
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| title | string | |
| description | text | nullable |
| created_by | bigint | FK users, cascadeOnDelete |
| assigned_to | bigint | FK users, nullable, nullOnDelete |
| loan_detail_id | bigint | FK loan_details, nullable, nullOnDelete |
| status | string | default 'pending' (pending/in_progress/completed/cancelled) |
| priority | string | default 'normal' (low/normal/high/urgent) |
| due_date | date | nullable |
| completed_at | timestamp | nullable |
| created_at, updated_at | timestamps | |

### general_task_comments
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| general_task_id | bigint | FK general_tasks, cascadeOnDelete |
| user_id | bigint | FK users, cascadeOnDelete |
| body | text | |
| created_at, updated_at | timestamps | |

---

## Daily Visit Reports

### daily_visit_reports
| Column | Type | Constraints |
|--------|------|-------------|
| id | bigint | PK |
| user_id | bigint | FK users, cascadeOnDelete |
| visit_date | date | |
| contact_name | string | |
| contact_phone | string(20) | nullable |
| contact_type | string | |
| purpose | string | |
| notes | text | nullable |
| outcome | text | nullable |
| follow_up_needed | boolean | default false |
| follow_up_date | date | nullable |
| follow_up_notes | text | nullable |
| is_follow_up_done | boolean | default false |
| parent_visit_id | bigint | FK daily_visit_reports, nullable, nullOnDelete |
| follow_up_visit_id | bigint | FK daily_visit_reports, nullable, nullOnDelete |
| quotation_id | bigint | FK quotations, nullable, nullOnDelete |
| loan_id | bigint | FK loan_details, nullable, nullOnDelete |
| branch_id | bigint | FK branches, nullable, nullOnDelete |
| created_at, updated_at | timestamps | |

**Indexes:** (user_id, visit_date), follow_up_date, (follow_up_needed, is_follow_up_done)

---

## Permission Slugs (48 total, 8 groups)

### Settings (8)
view_settings, edit_company_info, edit_banks, edit_documents, edit_tenures, edit_charges, edit_services, edit_gst

### Quotations (8)
create_quotation, generate_pdf, view_own_quotations, view_all_quotations, delete_quotations, download_pdf, download_pdf_branded, download_pdf_plain

### Users (5)
view_users, create_users, edit_users, delete_users, assign_roles

### Loans (14)
convert_to_loan, view_loans, view_all_loans, create_loan, edit_loan, delete_loan, manage_loan_documents, upload_loan_documents, download_loan_documents, delete_loan_files, manage_loan_stages, skip_loan_stages, add_remarks, manage_workflow_config

### Tasks (1)
view_all_tasks

### DVR (5)
view_dvr, create_dvr, edit_dvr, delete_dvr, view_all_dvr

### System (4)
change_own_password, manage_permissions, view_activity_log, view_reports

### Transfer (1)
transfer_loan_stages
