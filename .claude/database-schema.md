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
| remember_token | string | |
| timestamps | | |

### permissions
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| name | string | display name |
| slug | string | unique, used in code |
| group | string | Settings, Quotations, Users, System |
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
| customer_type | string | proprietor, partnership_llp, pvt_ltd, all |
| loan_amount | unsigned bigint | max 1,000,000,000 |
| pdf_filename | string | nullable |
| pdf_path | string | nullable |
| additional_notes | text | nullable |
| prepared_by_name | string | nullable |
| prepared_by_mobile | string | nullable |
| selected_tenures | JSON | array of integers (years) |
| timestamps | | |

### quotation_banks
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| quotation_id | FK → quotations | cascade delete |
| bank_name | string | |
| roi_min | decimal(5,2) | 0-30% |
| roi_max | decimal(5,2) | 0-30%, must be >= roi_min |
| pf_charge | decimal(5,2) | default 0 |
| admin_charge | string | default '0' |
| stamp_notary | string | default '0' |
| registration_fee | string | default '0' |
| advocate_fees | string | default '0' |
| iom_charge | string | default '0' |
| tc_report | string | default '0' |
| extra1_name | string | nullable |
| extra1_amount | string | nullable |
| extra2_name | string | nullable |
| extra2_amount | string | nullable |
| total_charges | string | default '0' |
| timestamps | | |

### quotation_emi (NOTE: not quotation_emis)
| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| quotation_bank_id | FK → quotation_banks | cascade delete |
| tenure_years | integer | |
| monthly_emi | string | default '0' |
| total_interest | string | default '0' |
| total_payment | string | default '0' |
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
| admin, stamp_notary, registration_fee, advocate, tc | string | |
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

## All 18 Permissions

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
| download_pdf | Quotations | yes | yes |
| delete_quotations | Quotations | yes | no |
| view_users | Users | yes | no |
| create_users | Users | yes | no |
| edit_users | Users | yes | no |
| delete_users | Users | yes | no |
| manage_permissions | System | no | no |
| view_activity_log | System | yes | no |
| change_password | System | yes | yes |

*super_admin always has all permissions (bypass in PermissionService)*
