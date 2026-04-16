# Users

## Overview

User management with role assignments, branch associations, bank employee assignments, and impersonation.

## Model: `User`

### Key Fields
- name, email, password, phone, employee_id
- is_active (boolean, default true)
- default_branch_id (FK branches)
- task_bank_id (FK banks — for bank employees)
- created_by (FK users — who created this user)

### Relationships
- `roles` — BelongsToMany(Role) via role_user
- `branches` — BelongsToMany(Branch) via user_branches (with is_default_office_employee pivot)
- `employerBanks` — BelongsToMany(Bank) via bank_employees (with is_default, location_id pivots)
- `locations` — BelongsToMany(Location) via location_user
- `userPermissions` — HasMany(UserPermission)

## CRUD

### Controller: `UserController`

**Create/Store:**
- Validates: name, email (unique), password (min 8, confirmed), phone, roles (required), branch
- Syncs: roles, branches (with OE defaults), bank assignments, product stages, locations
- Handles permission overrides
- Logs activity, clears permission cache

**Edit/Update:**
- Same validation (password optional on update)
- Prevents editing super_admin unless user is super_admin
- Syncs all associations

**Destroy:**
- Prevents self-deletion
- Prevents deleting super_admin unless user is super_admin
- Prevents deleting users with loans

**Toggle Active:**
- Prevents self-deactivation
- Prevents changing super_admin unless user is super_admin
- Logged to activity log

### DataTable
`UserController@userData` — server-side with filters: role, status, search.

## Bank Employee Assignments

Bank employees and office employees can be assigned to banks:
- Via `bank_employees` pivot table
- `is_default` flag marks default employee for a bank
- `location_id` associates employee with specific city
- `Bank::getDefaultEmployeeForCity(cityId)` — finds default by city, falls back to global default

## Branch Associations

- Users assigned to branches via `user_branches` pivot
- `is_default_office_employee` — marks user as default OE for that branch
- BDH/BM see data from users in their branches
- `default_branch_id` — user's primary branch

## Product Stage Assignments

Users can be assigned to specific product stages:
- Via `product_stage_users` table
- Linked to specific branch and/or location
- `is_default` flag for default assignment
- Managed in user create/edit forms and loan settings

## Impersonation

Uses `lab404/laravel-impersonate` package.

### Controller: `ImpersonateController`
- `users()` — search users for impersonation (active, non-super_admin)
- `take(id)` — start impersonating, stores referrer path
- `leave()` — stop impersonating, returns to referrer

### Access Control
- `canImpersonate()` — super_admin OR env `app.allow_impersonate_all` users
- `canBeImpersonated()` — non-super_admin users only

### Activity Logging
- `TakeImpersonation` event → logs "impersonate_start"
- `LeaveImpersonation` event → logs "impersonate_end"

### UI
- Desktop/mobile search dropdown with 300ms debounce
- SweetAlert confirmation before impersonating
- Yellow impersonation banner when active
- Smart redirect: returns to referrer if impersonated/original user has access

## Permissions

| Slug | Description |
|------|-------------|
| view_users | View users list |
| create_users | Create users |
| edit_users | Edit users |
| delete_users | Delete users |
| assign_roles | Assign roles |

## Views

| View | Purpose |
|------|---------|
| users/index.blade.php | DataTable list with role/status filters |
| users/create.blade.php | Create form with role multi-select, branches, banks |
| users/edit.blade.php | Edit form + password change section |
