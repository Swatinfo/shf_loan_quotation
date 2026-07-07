# User Management

## Overview

User management handles CRUD operations for system users. Only admins and super_admins can manage users. Registration is disabled — all users are created through this module.

## Unified Role System (7 Roles)

The system uses a single unified role model. Users are assigned one or more roles via the `role_user` pivot table. There is no separate "system role" vs "task role" — all roles live in the `roles` table.

| Role | Slug | Description |
|------|------|-------------|
| **Super Admin** | `super_admin` | Full access to everything, cannot be edited/deleted by non-super_admins |
| **Admin** | `admin` | Manages settings, users, quotations; full loan management |
| **Branch Manager** | `branch_manager` | Branch-level management, quotations, loan oversight |
| **BDO** | `bdo` | Business Development Officer — same access as Branch Manager |
| **Loan Advisor** | `loan_advisor` | Quotation creation, primary loan handler, client relationships |
| **Bank Employee** | `bank_employee` | Bank-side loan processing (BSM/OSV, sanction, e-sign) |
| **Office Employee** | `office_employee` | Office operations, valuations, docket review, OTC clearance |

### Bilingual Role Labels (Gujarati)

| Slug | Gujarati |
|------|----------|
| `super_admin` | સુપર એડમિન |
| `admin` | એડમિન |
| `branch_manager` | બ્રાન્ચ મેનેજર |
| `bdo` | બિઝનેસ ડેવલપમેન્ટ ઓફિસર |
| `loan_advisor` | લોન સલાહકાર |
| `bank_employee` | બેંક કર્મચારી |
| `office_employee` | ઓફિસ કર્મચારી |

### Loan-Eligible Roles

Advisor eligibility is database-driven via the `can_be_advisor` flag on the `roles` table, managed through Role Management (super_admin only). Currently: branch_manager, bdo, loan_advisor.

### Role Fields on User

- `roles()` — BelongsToMany via `role_user` pivot (primary role assignment)
- `employee_id` — internal employee identifier
- `default_branch_id` — user's primary branch (FK → branches)
- `task_bank_id` — for bank_employee: which bank they work for (FK → banks)
- `branches()` — BelongsToMany via `user_branches` pivot (users can be assigned to multiple branches)
- `employerBanks()` — BelongsToMany via `bank_employees` pivot (with is_default flag)
- `locations()` — BelongsToMany via `location_user` pivot

**Note**: The legacy `role` and `task_role` columns remain in the database but are deprecated. The `roles()` BelongsToMany relationship is the sole source of truth for user roles.

## Impersonation

Super admins (or all admins when `ALLOW_IMPERSONATE_ALL=1` in `.env`) can impersonate other users:
- **Cannot impersonate**: super_admin users (protected)
- **UI**: Search dropdown in navbar, amber banner when impersonating, leave button
- **Package**: `lab404/laravel-impersonate`
- **Activity logging**: `impersonate_start` and `impersonate_end` events logged
- **Controller**: `ImpersonateController@users` — debounced search endpoint

## Controller: UserController

**File**: `app/Http/Controllers/UserController.php`

### List Users (`GET /users`)

**Permission**: `view_users`

- Fetches all users with `creator` and `roles` relationships eager-loaded
- Ordered by name alphabetically
- Paginated: 20 per page
- Renders `users.index` view

### Create User (`GET /users/create`, `POST /users`)

**Permission**: `create_users`

**Validation Rules**:
| Field | Rules |
|-------|-------|
| `name` | required, string, max:255 |
| `email` | required, string, email, max:255, unique:users |
| `password` | required, string, min:8, confirmed |
| `roles` | required, array of role IDs |
| `phone` | nullable, string, max:20 |
| `is_active` | boolean |

**On Create**:
1. Creates user with hashed password
2. Syncs roles via `role_user` pivot
3. Sets `created_by` to current auth user ID
4. Logs activity: `ActivityLog::log('user_created', $user, ['name' => ..., 'roles' => ...])`
5. Redirects to `/users` with success message

### Edit User (`GET /users/{user}/edit`, `PUT /users/{user}`)

**Permission**: `edit_users`

**Authorization**:
- Cannot edit a `super_admin` unless you are also a `super_admin`

**Validation Rules**:
| Field | Rules |
|-------|-------|
| `name` | required, string, max:255 |
| `email` | required, string, email, max:255, unique:users,{id} |
| `password` | nullable, string, min:8, confirmed |
| `roles` | required, array of role IDs |
| `phone` | nullable, string, max:20 |
| `is_active` | boolean |

**On Update**:
1. Updates basic fields (name, email, phone, is_active)
2. Syncs roles via `role_user` pivot
3. If password provided: updates hashed password
4. Syncs user-specific permission overrides
5. Clears user's permission cache
6. Logs activity: `ActivityLog::log('user_updated', $user, [...])`
7. Redirects with success message

### Delete User (`DELETE /users/{user}`)

**Permission**: `delete_users`

**Guards**:
- Cannot delete yourself
- Cannot delete a `super_admin` unless you are a `super_admin`

**On Delete**:
1. Deletes user (cascades to user_permissions, role_user, quotations, etc.)
2. Logs activity: `ActivityLog::log('user_deleted', null, ['name' => ..., 'email' => ...])`
3. Redirects with success message

### Toggle Active (`POST /users/{user}/toggle-active`)

**Permission**: `edit_users`

**Guards**:
- Cannot deactivate yourself

**On Toggle**:
1. Flips `is_active` boolean
2. Saves user
3. Logs: `user_activated` or `user_deactivated`
4. Returns redirect with success message

**Effect of Deactivation**: The `EnsureUserIsActive` middleware immediately blocks the user on their next request, logs them out, and redirects to login with an error message.

## Permission Override Sync

When editing a user, the form sends permission overrides as:
```php
$permissions = [
    'permission_id_1' => 'grant',   // Explicitly granted
    'permission_id_2' => 'deny',    // Explicitly denied
    'permission_id_3' => 'default', // Use role default (no record)
];
```

The `syncUserPermissions()` method:
1. Deletes all existing `user_permissions` for the user
2. Creates new records only for `grant` and `deny` overrides
3. Ignores `default` entries (no record = fall through to role)

## Views

### users/index.blade.php
- Responsive table with mobile card layout
- Filters: Search, role dropdown, status dropdown
- Columns: Name (+ phone), Email, Roles (badges), Status (active/inactive badge), Created date, Actions
- Role badges: orange = super_admin, blue = admin, green = branch_manager/bdo, teal = loan_advisor, purple = bank_employee, gray = office_employee
- Action buttons: Edit, Toggle Active, Delete (with confirmation)

### users/create.blade.php
- Form with: Name, Email, Phone, Password, Confirm Password, Roles (multi-select), Active checkbox
- Max-width 42rem container

### users/edit.blade.php
- Same fields as create (password optional)
- **Bank/Branch assignment section**: employee ID, default branch, task bank (for bank_employee), branch assignments
- **Permission Overrides section**:
  - Shows all permissions grouped by category
  - Each permission has 3 options: Default (role), Grant, Deny
  - Super_admin permissions shown as always-granted (disabled)

## Activity Logging

| Action | When | Properties |
|--------|------|-----------|
| `user_created` | New user created | name, roles |
| `user_updated` | User details changed | name, roles, changes |
| `user_deleted` | User removed | name, email |
| `user_activated` | User reactivated | name |
| `user_deactivated` | User deactivated | name |
