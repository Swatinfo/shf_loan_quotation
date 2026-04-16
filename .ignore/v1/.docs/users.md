# Users

## Overview

User management covers CRUD operations, role assignment, branch/bank/location assignment, permission overrides, activation control, and impersonation. Users are managed by admins and super admins via the user management interface.

## User Model Fields

**File:** `app/Models/User.php`

| Field | Type | Description |
|-------|------|-------------|
| `name` | string | Full name |
| `email` | string | Unique email, used for login |
| `password` | string (hashed) | Auto-hashed via cast |
| `phone` | string, nullable | Phone number |
| `employee_id` | string, nullable | Employee identifier |
| `is_active` | boolean | Whether the user can log in |
| `default_branch_id` | foreign key, nullable | Default branch assignment |
| `task_bank_id` | foreign key, nullable | Bank associated with task work |
| `created_by` | foreign key, nullable | User who created this account |
| `email_verified_at` | datetime, nullable | Email verification timestamp |

## Relationships

| Relationship | Type | Pivot Table | Notes |
|-------------|------|-------------|-------|
| `roles()` | BelongsToMany | `role_user` | Multiple roles per user |
| `branches()` | BelongsToMany | `user_branches` | Pivot has `is_default_office_employee` |
| `defaultBranch()` | BelongsTo | — | Via `default_branch_id` |
| `employerBanks()` | BelongsToMany | `bank_employees` | Pivot has `is_default`, timestamps |
| `locations()` | BelongsToMany | `location_user` | With timestamps |
| `taskBank()` | BelongsTo | — | Via `task_bank_id` |
| `creator()` | BelongsTo | — | Via `created_by` |
| `createdUsers()` | HasMany | — | Users created by this user |
| `userPermissions()` | HasMany | — | Permission overrides |

## User CRUD

### List Users

**Route:** `GET /users` → `UserController@index`
**Permission:** `view_users`

Server-side DataTable via `GET /users/data` → `UserController@userData`. Supports:
- Search by name, email, or phone
- Filter by role (slug) and status (active/inactive)
- Sorting by name, email, status, or created date
- Pagination (10/25/50/100)
- Dual layout: desktop table + mobile cards

### Create User

**Route:** `GET /users/create` → `UserController@create`
**Permission:** `create_users`

**Route:** `POST /users` → `UserController@store`

Fields:
- Name (required)
- Email (required, unique)
- Phone (optional)
- Password + confirmation (required, min 8)
- Role (required, single select from dropdown)
- Default branch (optional)
- Status toggle (active by default)

**Copy feature:** Passing `?copy={userId}` pre-fills the form with an existing user's data (name, phone, role, branch, bank/location assignments). Email and password must be changed.

**Role-dependent fields shown dynamically:**
- `bank_employee` / `office_employee` → City select (single location from `location_user`)
- `branch_manager` / `bdo` / `loan_advisor` → Multiple location checkboxes
- `bank_employee` → Single bank select
- `office_employee` → Multiple bank checkboxes

On store:
1. Creates the user record
2. Syncs roles via `role_user` pivot
3. Syncs branch assignment via `user_branches`
4. Syncs bank assignments via `bank_employees` (for bank_employee/office_employee roles)
5. Syncs location assignments via `location_user`
6. Logs `user_created` activity

### Edit User

**Route:** `GET /users/{user}/edit` → `UserController@edit`
**Permission:** `edit_users`

**Route:** `PUT /users/{user}` → `UserController@update`

Same fields as create, plus:
- Password is optional (leave blank to keep current)
- Permission overrides section (if current user has `manage_permissions` and target is not super_admin)

**Protection:** Cannot edit a super_admin unless you are a super_admin.

On update:
1. Updates user fields
2. Syncs roles
3. Syncs branch (using `syncWithoutDetaching`)
4. Syncs bank assignments (detaches all if user no longer has bank/office role)
5. Syncs location assignments
6. Syncs permission overrides (if submitted)
7. Clears user permission cache
8. Logs `user_updated` activity

### Delete User

**Route:** `DELETE /users/{user}` → `UserController@destroy`
**Permission:** `delete_users`

**Protections:**
- Cannot delete yourself
- Cannot delete a super_admin unless you are a super_admin
- Cannot delete users who have associated loans (returns 422 with message to deactivate instead)

Returns JSON response. Uses SweetAlert2 confirmation dialog on the frontend.

## Activation Toggle

**Route:** `POST /users/{user}/toggle-active` → `UserController@toggleActive`
**Permission:** `edit_users`

Toggles the `is_active` boolean. Cannot deactivate yourself. Inactive users are blocked from logging in by the `EnsureUserIsActive` middleware.

Logs `user_activated` or `user_deactivated` activity.

## Role Assignment

Roles are assigned via a single `<select>` dropdown on the create/edit forms (despite the `roles[]` array name, the UI currently shows a single-select dropdown). The backend validates and syncs via the `role_user` pivot table.

### Role Hierarchy for User Management

The `getAllowedRoles()` method controls which roles can be assigned:
- **super_admin** can assign all roles including `super_admin`
- **admin** can assign all roles except `super_admin`
- **Other roles** cannot manage roles (empty list)

## User Permission Overrides

On the edit user page, admins with `manage_permissions` can set per-user permission overrides. Each permission can be:
- **Default** -- Uses the role-based permission (no override stored)
- **Grant** -- Explicitly grants the permission regardless of role
- **Deny** -- Explicitly denies the permission regardless of role

Stored in `user_permissions` table. See [permissions.md](permissions.md) for the 3-tier resolution logic.

## Branch Assignment

- `default_branch_id` on the user record sets the primary branch
- `user_branches` pivot table tracks all branch assignments with an `is_default_office_employee` flag
- On create, the default branch is synced to the pivot table
- On update, `syncWithoutDetaching` is used to preserve existing branch associations

## Bank Employee Assignment

- Stored in the `bank_employees` pivot table
- Pivot includes `is_default` flag and timestamps
- **bank_employee** role: single bank select (one bank assignment)
- **office_employee** role: multiple bank checkboxes (multiple bank assignments)
- When a user's role changes away from bank/office employee, all bank assignments are detached

## Location Assignment

- Stored in the `location_user` pivot table with timestamps
- **bank_employee** / **office_employee**: single city select (grouped by state)
- **branch_manager** / **bdo** / **loan_advisor**: multiple location checkboxes (states + cities in a scrollable list)
- Location fields toggle dynamically based on the selected role via JavaScript

## Impersonation

**Package:** `lab404/laravel-impersonate`

The User model uses the `Impersonate` trait and defines two methods:

### canImpersonate()

Returns `true` if the user is allowed to impersonate others:
- If `config('app.allow_impersonate_all')` is `true`, all authenticated users can impersonate (dev/testing)
- Otherwise, only `super_admin` users can impersonate

### canBeImpersonated()

Returns `true` unless the user has the `super_admin` role. Super admins cannot be impersonated.

### Impersonation User Search

**Route:** `GET /impersonate/users` → `ImpersonateController@users`

Returns JSON list of impersonatable users:
- Excludes the current user
- Excludes users with `super_admin` role
- Only includes active users
- Supports search by name or email
- Limited to 20 results
- Requires `canImpersonate()` check (aborts 403 if not)

## Role Management

**Controller:** `RoleManagementController`
**Permission:** Managed via `super_admin` access (stage CRUD is super_admin only)

### List Roles

**Route:** `GET /roles` → `RoleManagementController@index`

Displays a table with columns: Role name, Slug, Description, Advisor Eligible, User count, Type (System/Workflow). Includes desktop table and mobile card layouts.

### Create Role

**Route:** `GET /roles/create` → `RoleManagementController@create`
**Route:** `POST /roles` → `RoleManagementController@store`

Fields:
- Name (required, unique)
- Slug (required, unique, alpha_dash -- auto-converted to underscore format)
- Description (optional, max 500)
- Advisor eligible (`can_be_advisor` boolean)
- Copy from existing role (optional):
  - Copy permissions (syncs `role_permission` from source)
  - Copy stage eligibility (copies `default_role` entries from stages + sub_action role entries)

New roles are always created with `is_system = false`.

### Edit Role

**Route:** `GET /roles/{role}/edit` → `RoleManagementController@edit`
**Route:** `PUT /roles/{role}` → `RoleManagementController@update`

Edit page includes:
- Basic role info (name, description, advisor eligible)
- Slug is read-only for system roles (`is_system = true`)
- Full permission matrix (all groups, checkboxes per permission)
- Stage eligibility checkboxes (which workflow stages this role can be assigned to)

On update:
1. Updates role fields (slug change blocked for system roles)
2. Syncs permissions if submitted
3. Syncs stage eligibility (adds/removes role slug from each stage's `default_role` array)
4. Clears advisor cache and all permission caches
5. Logs `role_updated` activity

### Role Flags

| Flag | Purpose |
|------|---------|
| `can_be_advisor` | Role is eligible for loan advisor assignment (used by `advisorEligibleSlugs()`) |
| `is_system` | Built-in role; slug cannot be changed. System roles: super_admin, admin, branch_manager, bdo, loan_advisor, bank_employee, office_employee |

## Role Convenience Methods on User

| Method | Returns |
|--------|---------|
| `hasRole($slug)` | `bool` -- checks if user has a specific role |
| `hasAnyRole($slugs)` | `bool` -- checks if user has any of the given roles |
| `isSuperAdmin()` | `bool` -- shortcut for `hasRole('super_admin')` |
| `isAdmin()` | `bool` -- shortcut for `hasRole('admin')` |
| `isBankEmployee()` | `bool` -- shortcut for `hasRole('bank_employee')` |
| `isLoanAdvisor()` | `bool` -- shortcut for `hasRole('loan_advisor')` |
| `hasWorkflowRole()` | `bool` -- has advisor-eligible role, bank_employee, or office_employee |
| `canCreateLoans()` | `bool` -- super_admin, admin, or advisor-eligible role |
| `hasPermission($slug)` | `bool` -- delegates to `PermissionService` |

## Display Helpers

| Accessor | Returns |
|----------|---------|
| `role_label` | Comma-separated role names, or "--" |
| `workflow_role_label` | First non-admin role name |
| `workflow_role_label_gu` | Gujarati labels for workflow roles |
| `role_slugs` | Array of role slugs |

## Scopes

| Scope | Purpose |
|-------|---------|
| `advisorEligible` | Users with advisor-eligible roles who are active |

## Source Files

| File | Purpose |
|------|---------|
| `app/Models/User.php` | User model with relationships, role helpers, impersonation |
| `app/Models/Role.php` | Role model with advisor caching, Gujarati labels |
| `app/Http/Controllers/UserController.php` | User CRUD, activation toggle, permission overrides |
| `app/Http/Controllers/ImpersonateController.php` | Impersonation user search |
| `app/Http/Controllers/RoleManagementController.php` | Role CRUD with permission/stage sync |
| `resources/views/users/index.blade.php` | User list with DataTable + mobile cards |
| `resources/views/users/create.blade.php` | Create user form with dynamic role fields |
| `resources/views/users/edit.blade.php` | Edit user form with permission overrides |
| `resources/views/roles/index.blade.php` | Role list with desktop/mobile layouts |
