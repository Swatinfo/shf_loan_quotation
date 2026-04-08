# Database Schema Reference

## Tables

### users
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | |
| email | string | unique |
| email_verified_at | timestamp | nullable |
| password | string | bcrypt hashed |
| role | enum | super_admin, admin, staff (default: staff) |
| is_active | boolean | default: true |
| created_by | FK → users | nullable, who created this user |
| phone | string | nullable |
| task_role | string | nullable — branch_manager, loan_advisor, bank_employee, office_employee, legal_advisor |
| employee_id | string | nullable |
| default_branch_id | FK → branches | nullable, null on delete |
| task_bank_id | FK → banks | nullable, null on delete — only for bank_employee |
| updated_by | FK → users | nullable |
| remember_token | string | |
| timestamps | | |

### permissions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | display name |
| slug | string | unique, used in code |
| group | string | Settings, Quotations, Users, Loans, System |
| description | string | nullable |
| timestamps | | |

### role_permissions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| role | enum | super_admin, admin, staff |
| permission_id | FK → permissions | cascade delete |
| timestamps | | |
| | | unique(role, permission_id) |

### user_permissions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | FK → users | cascade delete |
| permission_id | FK → permissions | cascade delete |
| type | enum | grant, deny |
| timestamps | | |
| | | unique(user_id, permission_id) |

### activity_logs
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | FK → users | nullable, set null on delete |
| action | string | e.g., created_quotation, deleted_user |
| subject_type | string | nullable, polymorphic |
| subject_id | bigint | nullable, polymorphic |
| properties | JSON | nullable, extra context |
| ip_address | string | nullable |
| user_agent | string | nullable |
| timestamps | | |

### quotations
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | FK → users | cascade delete |
| customer_name | string | |
| customer_type | string | proprietor, partnership_llp, pvt_ltd, salaried, all |
| loan_amount | unsigned bigint | max 1,000,000,000 |
| pdf_filename | string | nullable |
| pdf_path | string | nullable |
| additional_notes | text | nullable |
| prepared_by_name | string | nullable |
| prepared_by_mobile | string | nullable |
| selected_tenures | JSON | array of integers (years) |
| loan_id | FK → loan_details | nullable, null on delete — back-reference |
| updated_by | FK → users | nullable |
| deleted_by | FK → users | nullable |
| timestamps | | |
| deleted_at | timestamp | nullable (soft delete) |

### quotation_banks
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| quotation_id | FK → quotations | cascade delete |
| bank_name | string | |
| roi_min | decimal(5,2) | 0-30% |
| roi_max | decimal(5,2) | 0-30%, must be >= roi_min |
| pf_charge | decimal(5,2) | default 0 |
| admin_charge | unsignedBigInteger | default 0 |
| stamp_notary | unsignedBigInteger | default 0 |
| registration_fee | unsignedBigInteger | default 0 |
| advocate_fees | unsignedBigInteger | default 0 |
| iom_charge | unsignedBigInteger | default 0 |
| tc_report | unsignedBigInteger | default 0 |
| extra1_name | string | nullable |
| extra1_amount | unsignedBigInteger | nullable |
| extra2_name | string | nullable |
| extra2_amount | unsignedBigInteger | nullable |
| total_charges | unsignedBigInteger | default 0 |
| timestamps | | |

### quotation_emi (NOTE: not quotation_emis)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| quotation_bank_id | FK → quotation_banks | cascade delete |
| tenure_years | integer | |
| monthly_emi | unsignedBigInteger | default 0 |
| total_interest | unsignedBigInteger | default 0 |
| total_payment | unsignedBigInteger | default 0 |
| timestamps | | |

### quotation_documents
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| quotation_id | FK → quotations | cascade delete |
| document_name_en | string | English name |
| document_name_gu | string | Gujarati name |
| timestamps | | |

### bank_charges (reference data)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| bank_name | string | |
| pf | decimal | |
| admin, stamp_notary, registration_fee, advocate, tc | unsignedBigInteger | |
| extra1_name, extra1_amt | string | nullable |
| extra2_name, extra2_amt | string | nullable |
| timestamps | | |

### app_config
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| config_key | string | |
| config_json | JSON | structured config data |
| timestamps | | |

## All 34 Permissions

| Slug | Group | Admin | Staff |
|------|-------|-------|-------|
| view_settings | Settings | yes | no |
| edit_company_info | Settings | yes | no |
| edit_banks | Settings | yes | no |
| edit_tenures | Settings | yes | no |
| edit_documents | Settings | yes | no |
| edit_charges | Settings | yes | no |
| edit_services | Settings | yes | no |
| edit_gst | Settings | yes | no |
| create_quotation | Quotations | yes | yes |
| generate_pdf | Quotations | yes | yes |
| view_all_quotations | Quotations | yes | no |
| view_own_quotations | Quotations | yes | yes |
| download_pdf | Quotations | yes | yes |
| delete_quotations | Quotations | yes | no |
| view_users | Users | yes | no |
| create_users | Users | yes | no |
| edit_users | Users | yes | no |
| delete_users | Users | no | no |
| assign_roles | Users | yes | no |
| convert_to_loan | Loans | yes | yes |
| view_loans | Loans | yes | yes |
| view_all_loans | Loans | yes | no |
| create_loan | Loans | yes | yes |
| edit_loan | Loans | yes | no |
| delete_loan | Loans | yes | no |
| manage_loan_documents | Loans | yes | yes |
| manage_loan_stages | Loans | yes | yes |
| skip_loan_stages | Loans | yes | no |
| add_remarks | Loans | yes | yes |
| manage_workflow_config | Loans | yes | no |
| manage_permissions | System | no | no |
| view_activity_log | System | yes | no |
| change_own_password | System | yes | yes |

*super_admin always has all permissions (bypass in PermissionService)*

*Permission groups: Settings, Quotations, Users, Loans, System (5 groups)*

---

## Loan Task System Tables (Phase 1: Foundation)

### banks
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | unique |
| code | string | nullable |
| is_active | boolean | default: true |
| default_employee_id | FK → users | nullable, null on delete |
| updated_by | FK → users | nullable |
| deleted_by | FK → users | nullable |
| timestamps | | |
| deleted_at | timestamp | nullable (soft delete) |

### branches
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | |
| code | string | nullable, unique |
| address | text | nullable |
| city | string | nullable |
| phone | string(20) | nullable |
| is_active | boolean | default: true |
| manager_id | FK → users | nullable, null on delete |
| updated_by | FK → users | nullable |
| deleted_by | FK → users | nullable |
| timestamps | | |
| deleted_at | timestamp | nullable (soft delete) |

### products
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| bank_id | FK → banks | cascade delete |
| name | string | unique(bank_id, name) |
| code | string | nullable |
| is_active | boolean | default: true |
| updated_by | FK → users | nullable |
| deleted_by | FK → users | nullable |
| timestamps | | |
| deleted_at | timestamp | nullable (soft delete) |

### stages
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| stage_key | string | unique |
| stage_name_en | string | |
| stage_name_gu | string | nullable |
| sequence_order | integer | |
| is_parallel | boolean | default: false |
| parent_stage_key | string | nullable, refs stages.stage_key |
| stage_type | string | default: 'sequential' (sequential/parallel/decision) |
| default_role | string | nullable |
| description_en | text | nullable |
| description_gu | text | nullable |
| timestamps | | |

**Seeded**: 25 stages (14 base + 11 optional bank-specific)

### user_branches
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | FK → users | cascade delete |
| branch_id | FK → branches | cascade delete |
| is_default_office_employee | boolean | default: false |
| timestamps | | |

**Indexes**: unique(user_id, branch_id)

### loan_details
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| loan_number | string | unique, format: SHF-YYYYMM-XXXX |
| quotation_id | FK → quotations | nullable, null on delete |
| branch_id | FK → branches | nullable, null on delete |
| bank_id | FK → banks | nullable, null on delete |
| product_id | FK → products | nullable, null on delete |
| customer_name | string | |
| customer_type | string | proprietor, partnership_llp, pvt_ltd, salaried |
| customer_phone | string(20) | nullable |
| customer_email | string | nullable |
| loan_amount | unsignedBigInteger | |
| status | string | default: 'active' (active/completed/rejected/cancelled/on_hold) |
| current_stage | string | default: 'inquiry', refs stages.stage_key |
| bank_name | string | nullable, denormalized |
| roi_min | decimal(5,2) | nullable |
| roi_max | decimal(5,2) | nullable |
| total_charges | string | nullable |
| application_number | string | nullable |
| assigned_bank_employee | FK → users | nullable, null on delete |
| due_date | date | nullable, default: 7 days from creation |
| rejected_at | timestamp | nullable |
| rejected_by | FK → users | nullable, null on delete |
| rejected_stage | string | nullable |
| rejection_reason | text | nullable |
| created_by | FK → users | cascade delete |
| assigned_advisor | FK → users | nullable, null on delete |
| notes | text | nullable |
| updated_by | FK → users | nullable |
| deleted_by | FK → users | nullable |
| timestamps | | |
| deleted_at | timestamp | nullable (soft delete) |

**Indexes**: unique(loan_number), index(status), index(current_stage), index(customer_type)

### loan_documents (Phase 4)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| loan_id | FK → loan_details | cascade delete |
| document_name_en | string | |
| document_name_gu | string | nullable |
| is_required | boolean | default: true |
| status | string | default: 'pending' (pending/received/rejected/waived) |
| received_date | date | nullable |
| received_by | FK → users | nullable, null on delete |
| rejected_reason | text | nullable |
| notes | text | nullable |
| sort_order | integer | default: 0 |
| updated_by | FK → users | nullable |
| timestamps | | |

**Indexes**: index(loan_id), index(loan_id, status)

### stage_assignments (Phase 5)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| loan_id | FK → loan_details | cascade delete |
| stage_key | string | refs stages.stage_key |
| assigned_to | FK → users | nullable, null on delete |
| status | string | default: 'pending' (pending/in_progress/completed/rejected/skipped) |
| priority | string | default: 'normal' (low/normal/high/urgent) |
| started_at | timestamp | nullable |
| completed_at | timestamp | nullable |
| completed_by | FK → users | nullable, null on delete |
| is_parallel_stage | boolean | default: false |
| parent_stage_key | string | nullable |
| notes | text | nullable, JSON for stage-specific data |
| timestamps | | |

**Indexes**: unique(loan_id, stage_key), index(stage_key), index(assigned_to), index(status), index(parent_stage_key)

### loan_progress (Phase 5)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| loan_id | FK → loan_details | unique, cascade delete |
| total_stages | integer | default: 10 |
| completed_stages | integer | default: 0 |
| overall_percentage | decimal(5,2) | default: 0 |
| estimated_completion | date | nullable |
| workflow_snapshot | text | nullable, JSON |
| timestamps | | |

### stage_transfers (Phase 5)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| stage_assignment_id | FK → stage_assignments | cascade delete |
| loan_id | FK → loan_details | cascade delete |
| stage_key | string | |
| transferred_from | FK → users | cascade delete |
| transferred_to | FK → users | cascade delete |
| reason | text | nullable |
| transfer_type | string | default: 'manual' (manual/auto) |
| created_at | timestamp | |

### stage_queries (Phase 5)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| stage_assignment_id | FK → stage_assignments | cascade delete |
| loan_id | FK → loan_details | cascade delete |
| stage_key | string | |
| query_text | text | |
| raised_by | FK → users | cascade delete |
| status | string | default: 'pending' (pending/responded/resolved) |
| resolved_at | timestamp | nullable |
| resolved_by | FK → users | nullable, null on delete |
| timestamps | | |

**Indexes**: index(stage_assignment_id, status), index(loan_id)

### query_responses (Phase 5)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| stage_query_id | FK → stage_queries | cascade delete |
| response_text | text | |
| responded_by | FK → users | cascade delete |
| created_at | timestamp | |

### valuation_details (Phase 6a)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| loan_id | FK → loan_details | cascade delete |
| valuation_type | string | default: 'property' (property/vehicle/business) |
| property_address | text | nullable |
| property_type | string | nullable |
| property_area | string | nullable |
| market_value | unsignedBigInteger | nullable |
| government_value | unsignedBigInteger | nullable |
| valuation_date | date | nullable |
| valuator_name | string | nullable |
| valuator_report_number | string | nullable |
| notes | text | nullable |
| updated_by | FK → users | nullable |
| timestamps | | |

**Indexes**: index(loan_id)

### remarks (Phase 6b)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| loan_id | FK → loan_details | cascade delete |
| stage_key | string | nullable (null = general remark) |
| user_id | FK → users | cascade delete |
| remark | text | |
| timestamps | | |

### shf_notifications (Phase 6b)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | FK → users | cascade delete |
| title | string | |
| message | text | |
| type | string | default: 'info' (info/success/warning/error/stage_update/assignment) |
| is_read | boolean | default: false |
| loan_id | FK → loan_details | nullable, null on delete |
| stage_key | string | nullable |
| link | string | nullable |
| timestamps | | |

**Indexes**: index(user_id, is_read), index(loan_id)

### disbursement_details (Phase 7a)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| loan_id | FK → loan_details | unique, cascade delete |
| disbursement_type | string | fund_transfer/cheque/demand_draft |
| disbursement_date | date | nullable |
| amount_disbursed | unsignedBigInteger | nullable |
| bank_account_number | string | nullable (fund_transfer) |
| ifsc_code | string | nullable (fund_transfer) |
| cheque_number | string | nullable (cheque) |
| cheque_date | date | nullable (cheque) |
| dd_number | string | nullable (demand_draft) |
| dd_date | date | nullable (demand_draft) |
| is_otc | boolean | default: false |
| otc_branch | string | nullable |
| otc_cleared | boolean | default: false |
| otc_cleared_date | date | nullable |
| otc_cleared_by | FK → users | nullable, null on delete |
| reference_number | string | nullable |
| notes | text | nullable |
| updated_by | FK → users | nullable |
| timestamps | | |

### product_stages (Phase 7b)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| product_id | FK → products | cascade delete |
| stage_id | FK → stages | cascade delete |
| is_enabled | boolean | default: true |
| default_assignee_role | string | nullable |
| default_user_id | FK → users | nullable, null on delete |
| auto_skip | boolean | default: false |
| sort_order | integer | nullable |
| updated_by | FK → users | nullable |
| timestamps | | |

**Indexes**: unique(product_id, stage_id)

### product_stage_users
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| product_stage_id | FK → product_stages | cascade delete |
| branch_id | FK → branches | cascade delete |
| user_id | FK → users | cascade delete |
| timestamps | | |

**Indexes**: unique(product_stage_id, branch_id)

### bank_employees
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| bank_id | FK → banks | cascade delete |
| user_id | FK → users | cascade delete |
| is_default | boolean | default: false |
| timestamps | | |

**Indexes**: unique(bank_id, user_id)

## Soft Deletes (Phase 9)

Tables with `deleted_at`: loan_details, banks, branches, products, quotations

Dependency protection:
- Bank: blocked if has products or active loans
- Branch: blocked if has assigned users or active loans
- Product: blocked if has active loans
- Quotation: blocked if converted to loan

## Audit Columns (Phase 10)

Tables with `updated_by`: loan_details, quotations, banks, branches, products, stage_assignments, loan_documents, valuation_details, disbursement_details, product_stages

Tables with `deleted_by`: loan_details, quotations, banks, branches, products

Auto-filled by `App\Traits\HasAuditColumns` trait on save/soft-delete.
