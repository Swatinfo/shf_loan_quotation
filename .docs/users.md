# User Management

## Overview

User management handles CRUD operations for system users. Only admins and super_admins can manage users. Registration is disabled — all users are created through this module.

## Roles

| Role | Level | Description |
|------|-------|-------------|
| `super_admin` | Highest | Full access to everything, cannot be edited/deleted by non-super_admins |
| `admin` | Middle | Manages settings, users, quotations; can create staff and admin users |
| `staff` | Lowest | Creates quotations, views own quotations only |

## Task Roles (Loan Workflow)

In addition to the system role, users can be assigned a **task role** for the loan workflow system:

| Task Role | Description |
|-----------|-------------|
| `branch_manager` | Manages branch operations, oversees loan processing |
| `loan_advisor` | Assigned as advisor on loans, handles client relationships |
| `bank_employee` | Works for a specific bank, handles bank-side processing |
| `office_employee` | Office staff handling internal processing |
| `legal_advisor` | Handles legal verification, e-sign, documentation |

Task role fields on the User model:
- `task_role` — the user's workflow role (nullable, can have none)
- `employee_id` — internal employee identifier
- `default_branch_id` — user's primary branch (FK → branches)
- `task_bank_id` — for bank_employee: which bank they work for (FK → banks)
- `branches()` — BelongsToMany via user_branches pivot (users can be assigned to multiple branches)
- `employerBanks()` — BelongsToMany via bank_employees pivot (with is_default flag)

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

- Fetches all users with `creator` relationship eager-loaded
- Ordered by: role priority (super_admin first, then admin, then staff), then name alphabetically
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
| `role` | required, in:{allowed_roles} |
| `phone` | nullable, string, max:20 |
| `is_active` | boolean |

**Allowed Roles** (based on current user):
- `super_admin` can create: super_admin, admin, staff
- `admin` can create: admin, staff
- `staff` cannot create users

**On Create**:
1. Creates user with hashed password
2. Sets `created_by` to current auth user ID
3. Logs activity: `ActivityLog::log('user_created', $user, ['name' => ..., 'role' => ...])`
4. Redirects to `/users` with success message

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
| `role` | required, in:{allowed_roles} |
| `phone` | nullable, string, max:20 |
| `is_active` | boolean |

**On Update**:
1. Updates basic fields (name, email, role, phone, is_active)
2. If password provided: updates hashed password
3. Syncs user-specific permission overrides
4. Clears user's permission cache
5. Logs activity: `ActivityLog::log('user_updated', $user, [...])`
6. Redirects with success message

### Delete User (`DELETE /users/{user}`)

**Permission**: `delete_users`

**Guards**:
- Cannot delete yourself
- Cannot delete a `super_admin` unless you are a `super_admin`

**On Delete**:
1. Deletes user (cascades to user_permissions, quotations, etc.)
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
- Filters: Search, role dropdown, status dropdown, task_role dropdown
- Columns: Name (+ phone), Email, Role (badge), Task Role, Status (active/inactive badge), Created date, Actions
- Role badges: orange = super_admin, blue = admin, gray = staff
- Action buttons: Edit, Toggle Active, Delete (with confirmation)

### users/create.blade.php
- Form with: Name, Email, Phone, Password, Confirm Password, Role dropdown, Active checkbox
- Max-width 42rem container

### users/edit.blade.php
- Same fields as create (password optional)
- **Task Role section**: Task role dropdown, employee ID, default branch, task bank (for bank_employee)
- **Permission Overrides section**:
  - Shows all permissions grouped by category
  - Each permission has 3 options: Default (role), Grant, Deny
  - Super_admin permissions shown as always-granted (disabled)

## Activity Logging

| Action | When | Properties |
|--------|------|-----------|
| `user_created` | New user created | name, role |
| `user_updated` | User details changed | name, role, changes |
| `user_deleted` | User removed | name, email |
| `user_activated` | User reactivated | name |
| `user_deactivated` | User deactivated | name |
