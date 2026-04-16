# Roles

## Overview

7 unified roles managed via `roles` table + `role_user` pivot. Users can have multiple roles.

## Role Definitions

| Slug | Name | System | Can Be Advisor | Description |
|------|------|--------|----------------|-------------|
| super_admin | Super Admin | Yes | No | Full system access, bypasses all permissions |
| admin | Admin | Yes | No | System administration, settings, user management |
| branch_manager | Branch Manager | No | Yes | Branch-level management, quotations, loan stages |
| bdh | Business Development Head | No | Yes | Same access as Branch Manager |
| loan_advisor | Loan Advisor | No | Yes | Quotation creation, loan processing |
| bank_employee | Bank Employee | No | No | Bank-side loan processing only |
| office_employee | Office Employee | No | No | Office operations, valuations, docket review, OTC |

## Key Properties

### `is_system`
System roles (super_admin, admin) cannot have their slug changed. Managed at code level.

### `can_be_advisor`
Determines if role holders can be assigned as loan advisors. Cached for 5 minutes via `Role::advisorEligibleSlugs()`.

Advisor-eligible roles: branch_manager, bdh, loan_advisor.

## Gujarati Labels

```php
Role::gujaratiLabels() = [
    'super_admin' => 'સુપર એડમિન',
    'admin' => 'એડમિન',
    'branch_manager' => 'બ્રાન્ચ મેનેજર',
    'bdh' => 'બિઝનેસ ડેવલપમેન્ટ હેડ',
    'loan_advisor' => 'લોન સલાહકાર',
    'bank_employee' => 'બેંક કર્મચારી',
    'office_employee' => 'ઓફિસ કર્મચારી',
]
```

## Role Model

### Scopes
- `advisorEligible` — where can_be_advisor = true
- `workflow` — where is_system = false

### Methods
- `advisorEligibleSlugs()` — static, cached 5 min, returns slug array
- `clearAdvisorCache()` — clears the advisor cache

## User Model Role Helpers

- `hasRole(slug)` — check if user has role
- `hasAnyRole(slugs)` — check if user has any of given roles
- `getRoleSlugsAttribute()` — array of user's role slugs
- `isSuperAdmin()`, `isAdmin()`, `isBankEmployee()`, `isLoanAdvisor()`
- `hasWorkflowRole()` — has any workflow-capable role
- `canCreateLoans()` — has create_loan permission + advisor-eligible role

## Role Management

### Controller: `RoleManagementController`
- Permission: `manage_permissions`
- CRUD for non-system roles
- Can copy permissions and stage eligibility from existing roles
- Syncs permissions and stage sub_action roles on update

### View: `roles/index.blade.php`
- Lists all roles with user counts
- Edit/create forms with permission checkboxes and stage eligibility toggles

## Branch-Based Visibility

Roles interact with branches for visibility:
- **branch_manager/bdh** — see loans in their branches via `user_branches` pivot
- **bank_employee/office_employee** — see loans they appear in stage_transfers
