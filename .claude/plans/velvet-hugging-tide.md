# Plan: Update DefaultDataSeeder from Current Database

## Context
The `DefaultDataSeeder` was created on 2026-04-07. Since then, significant changes have been made in the live DB: new users, updated task_roles, new permissions, locations system, bank employees, stage default_roles/sub_actions, etc. The seeder needs to match the current DB so a fresh install reproduces production data.

**User constraint:** Do NOT seed `products` table (or product-dependent tables).

## File to Modify
- `database/seeders/DefaultDataSeeder.php`

## Detailed Diff: Current Code vs Current DB

### 1. `permissions` — Add 3 new rows
**Code:** 33 permissions (ids 1-33)
**DB:** 36 permissions — add:
- `id=34, slug=upload_loan_documents, group=Loans`
- `id=35, slug=download_loan_documents, group=Loans`
- `id=36, slug=delete_loan_files, group=Loans`

### 2. `branches` — Update fields
**Code:** `manager_id => null, location_id` not set
**DB:** `manager_id => 2, location_id => 2`

### 3. `stages` — Many default_role/is_enabled/sub_actions changes
| Stage | Field | Code value | DB value |
|-------|-------|-----------|----------|
| 6 bsm_osv | default_role | `null` | `["bank_employee"]` |
| 8 technical_valuation | default_role | `["bank_employee"]` | `["branch_manager","office_employee"]` |
| 9 rate_pf | default_role | `["bank_employee"]` | `null` |
| 9 rate_pf | sub_actions | `null` | JSON with 2 sub-actions |
| 10 sanction | default_role | `["bank_employee"]` | `null` |
| 10 sanction | sub_actions | `null` | JSON with 3 sub-actions |
| 11 docket | default_role | `["bank_employee"]` | `["branch_manager","office_employee"]` |
| 12 kfs | default_role | `...bank_employee"]` | `...office_employee"]` |
| 14 disbursement | default_role | 3 roles | 2 roles (no office_employee) |
| 15 cibil_check | is_enabled | `true` | `false` |
| 15 cibil_check | default_role | 3 roles | `["bank_employee"]` |
| 16 property_valuation | default_role | `["bank_employee"]` | `["branch_manager","office_employee"]` |
| 17 vehicle_valuation | is_enabled | `true` | `false` |
| 17 vehicle_valuation | default_role | `["bank_employee"]` | `null` |
| 18 business_valuation | is_enabled | `true` | `false` |
| 18 business_valuation | default_role | `["bank_employee"]` | `["branch_manager","office_employee"]` |
| 19 title_search | is_enabled | `true` | `false` |
| 20 financial_analysis | is_enabled | `true` | `false` |
| 21 site_visit | is_enabled | `true` | `false` |
| 21 site_visit | default_role | `["bank_employee"]` | `["branch_manager"]` |
| 22 approval_committee | is_enabled | `true` | `false` |
| 22 approval_committee | default_role | `["bank_employee"]` | `["branch_manager"]` |
| 23 credit_committee | is_enabled | `true` | `false` |
| 23 credit_committee | default_role | `["bank_employee"]` | `["branch_manager"]` |
| 24 insurance | default_role | `null` | `["loan_advisor","office_employee"]` |
| 25 mortgage | default_role | 3 roles | `["office_employee","legal_advisor"]` |

### 4. `users` — Update task_roles + add 3 new users
**Changes to existing:**
- User 2: `task_role => null` → `'branch_manager'`
- Users 3-13: `task_role => null` → `'loan_advisor'`
- User 22: `task_bank_id => null` → `1`

**New users to add:**
- id=23: Office Employee2, email=officeemployee2@shfworld.com, task_role=office_employee, default_branch_id=1, task_bank_id=1
- id=24: Legal Advisor 1, email=legal@shfworld.com, task_role=legal_advisor, task_bank_id=4

### 5. `role_permissions` — Update permission sets
**admin:** Add 34, 35, 36 to existing list
**staff:** Add 27, 31, 34, 35, 36 to existing list → `[9, 10, 11, 14, 20, 23, 24, 26, 27, 29, 30, 31, 32, 34, 35, 36]`
**super_admin:** Already uses "all permissions" query — auto-includes new ones

### 6. `user_branches` — Add user 23
**Code:** `[1-13, 22]`
**DB:** `[1-13, 22, 23]`

### 7. `bank_employees` — Add 6 new entries
**Code:** 8 entries (bank employees only)
**DB:** 14 entries — add:
- bank_id=3, user_id=22 (office employee → Axis)
- bank_id=1, user_id=22 (office employee → HDFC)
- bank_id=3, user_id=23 (office employee 2 → Axis)
- bank_id=1, user_id=23 (office employee 2 → HDFC)
- bank_id=4, user_id=24 (legal advisor → Kotak)
- bank_id=3, user_id=24 (legal advisor → Axis)

### 8. `bank_charges` — Reorder IDs to match DB
**Code:** IDs 1-4 (Axis=1, HDFC=2, ICICI=3, Kotak=4)
**DB:** IDs 2-5 (Axis=2, HDFC=3, ICICI=4, Kotak=5); ID 1 is deleted bank ABCD — skip it
Update to use DB IDs and match on `bank_name` instead of `id`.

### 9. `quotations` — Add `location_id` column
**Code:** Missing `location_id` field
**DB:** All quotations have `location_id => 2`
Add `'location_id' => 2` to all quotation records.

### 10. NEW: `locations` table (4 rows)
```
id=1: Gujarat (state, code=GJ)
id=2: Rajkot (city, parent_id=1, code=RJT)
id=3: Jamnagar (city, parent_id=1, code=JAM)
id=4: Ahmedabad (city, parent_id=1, code=AMD)
```

### 11. NEW: `bank_location` table (7 rows)
```
bank_id=3 → location_id=2 (Axis → Rajkot)
bank_id=1 → location_id=2 (HDFC → Rajkot)
bank_id=2 → location_id=3 (ICICI → Jamnagar)
bank_id=2 → location_id=2 (ICICI → Rajkot)
bank_id=4 → location_id=3 (Kotak → Jamnagar)
bank_id=4 → location_id=2 (Kotak → Rajkot)
bank_id=3 → location_id=3 (Axis → Jamnagar)
```

### 12. NEW: `location_user` table (6 rows)
```
location_id=1, user_id=17 (Gujarat → Kotak Emp 2)
location_id=2, user_id=17 (Rajkot → Kotak Emp 2)
location_id=2, user_id=24 (Rajkot → Legal Advisor)
location_id=3, user_id=24 (Jamnagar → Legal Advisor)
location_id=2, user_id=23 (Rajkot → Office Employee2)
location_id=2, user_id=22 (Rajkot → Office Employee1)
```

### 13. No changes needed
- `app_config` — identical
- `app_settings` — identical
- `quotation_banks` — identical
- `quotation_emi` — identical
- `quotation_documents` — identical (but quotations 41/42 now have docs in DB — these were created dynamically, seeder already has note they had no docs)

### 14. Remove
- `seedProducts()` method — user requested exclusion

### 15. Bug fix in existing code
Line 213: `$defaultPassword = "Admin@123"` — missing semicolon. Fix syntax.

## Tables explicitly SKIPPED
- `products` (user request)
- `product_stages` (depends on products)
- `location_product` (depends on products)
- `product_stage_users` (empty, depends on products)
- `user_permissions` (empty)

## Implementation Order in `run()`
```
seedPermissions()
seedLocations()       // NEW
seedBranches()
seedBanks()
// seedProducts() — REMOVED
seedStages()
seedUsers()
seedRolePermissions()
seedUserBranches()
seedBankEmployees()
seedBankLocations()   // NEW
seedLocationUsers()   // NEW
seedBankCharges()
seedAppConfig()
seedAppSettings()
seedQuotations()
seedQuotationBanks()
seedQuotationEmi()
seedQuotationDocuments()
```

## Verification
1. Run `php artisan migrate:fresh` then `php artisan db:seed --class=DefaultDataSeeder`
2. Verify no FK constraint errors
3. Spot-check: `SELECT COUNT(*) FROM users` = 24, `SELECT COUNT(*) FROM permissions` = 36, `SELECT COUNT(*) FROM locations` = 4, `SELECT COUNT(*) FROM bank_employees` = 14
