# Permissions

## Overview

The permission system controls access to all features across the platform. It uses a 3-tier resolution model with 48 permissions organized into 5 groups, managed via database tables and cached for performance.

## Database Tables

| Table | Purpose |
|-------|---------|
| `permissions` | All permission definitions (`id`, `name`, `slug`, `group`, `description`) |
| `role_permission` | Pivot: which permissions each role has |
| `user_permissions` | Per-user overrides (`user_id`, `permission_id`, `type`: grant/deny) |
| `roles` | Role definitions (`id`, `name`, `slug`, `description`, `can_be_advisor`, `is_system`) |
| `role_user` | Pivot: which roles each user has |

## Permission Groups

### Settings (8 permissions)

| Slug | Name | Description |
|------|------|-------------|
| `view_settings` | View Settings | View the settings page |
| `edit_company_info` | Edit Company Info | Edit company information |
| `edit_banks` | Edit Banks | Add/edit/remove banks |
| `edit_documents` | Edit Documents | Add/edit/remove required documents |
| `edit_tenures` | Edit Tenures | Add/edit/remove loan tenures |
| `edit_charges` | Edit Charges | Edit bank charges |
| `edit_services` | Edit Services | Edit service charges |
| `edit_gst` | Edit GST | Edit GST percentage |

### Quotations (6 permissions)

| Slug | Name | Description |
|------|------|-------------|
| `create_quotation` | Create Quotation | Create new loan quotations |
| `generate_pdf` | Generate PDF | Generate PDF for quotations |
| `view_own_quotations` | View Own Quotations | View quotations created by self |
| `view_all_quotations` | View All Quotations | View all quotations across users |
| `delete_quotations` | Delete Quotations | Delete quotations |
| `download_pdf` | Download PDF | Download generated PDFs |

### Users (5 permissions)

| Slug | Name | Description |
|------|------|-------------|
| `view_users` | View Users | View the users list |
| `create_users` | Create Users | Create new user accounts |
| `edit_users` | Edit Users | Edit existing user accounts |
| `delete_users` | Delete Users | Delete user accounts |
| `assign_roles` | Assign Roles | Assign roles to users |

### Loans (14 permissions)

| Slug | Name | Description |
|------|------|-------------|
| `convert_to_loan` | Convert to Loan | Convert quotation to loan task |
| `view_loans` | View Loans | View loan task list |
| `view_all_loans` | View All Loans | View all loans across users/branches |
| `create_loan` | Create Loan | Create loan tasks directly |
| `edit_loan` | Edit Loan | Edit loan details |
| `delete_loan` | Delete Loan | Delete loan tasks |
| `manage_loan_documents` | Manage Loan Documents | Mark documents as received/pending, add/remove documents |
| `upload_loan_documents` | Upload Loan Documents | Upload document files to loan documents |
| `download_loan_documents` | Download Loan Documents | Download/preview uploaded document files |
| `delete_loan_files` | Delete Loan Files | Remove uploaded document files |
| `manage_loan_stages` | Manage Loan Stages | Update stage status and assignments |
| `skip_loan_stages` | Skip Loan Stages | Skip stages in loan workflow |
| `add_remarks` | Add Remarks | Add remarks to loan stages |
| `manage_workflow_config` | Manage Workflow Config | Configure banks, products, branches, stage workflows |

### System (3 permissions)

| Slug | Name | Description |
|------|------|-------------|
| `change_own_password` | Change Own Password | Change own password |
| `manage_permissions` | Manage Permissions | Manage role and user permissions |
| `view_activity_log` | View Activity Log | View system activity log |

## 3-Tier Resolution

Permission checks follow this order in `PermissionService::userHasPermission()`:

1. **Super Admin bypass** -- If the user has the `super_admin` role, return `true` immediately. Super admins always have all permissions.

2. **User-specific override** -- Check the `user_permissions` table for a row matching the user + permission. If found, the `type` column determines the result:
   - `grant` → `true` (user explicitly granted this permission)
   - `deny` → `false` (user explicitly denied this permission)

3. **Role-based permission** -- Check if ANY of the user's roles has this permission via the `role_permission` pivot table. If any role grants it, the user has it.

If none of the three tiers match, the permission defaults to `false`.

### Code Path

```
User::hasPermission($slug)
  → PermissionService::userHasPermission($user, $slug)
    → 1. $user->hasRole('super_admin') → true
    → 2. getUserOverride($user, $slug) → true/false/null
    → 3. userRolesHavePermission($user, $slug) → true/false
```

## CheckPermission Middleware

**Class:** `App\Http\Middleware\CheckPermission`
**Alias:** `permission`

Usage in routes:

```php
Route::get('/settings', ...)->middleware('permission:view_settings');
Route::post('/users', ...)->middleware('permission:create_users');
```

The middleware:
1. Checks that a user is authenticated (aborts 403 if not)
2. Calls `$user->hasPermission($permission)` which delegates to `PermissionService`
3. Aborts 403 if the user lacks the permission

## Caching

All permission lookups are cached with a 5-minute (300-second) TTL:

| Cache Key | Contents |
|-----------|----------|
| `user_perms:{userId}` | User-specific overrides (slug → type map) |
| `user_role_ids:{userId}` | Array of role IDs for the user |
| `role_perms:{roleId}` or `role_perms:{id1,id2,...}` | Permission slugs for a set of role IDs |
| `advisor_eligible_roles` | Slugs of roles with `can_be_advisor = true` |

### Cache Invalidation

- `clearUserCache($user)` -- Clears `user_perms` and `user_role_ids` for one user
- `clearRoleCache()` -- Clears all `role_perms` entries
- `clearAllCaches()` -- Clears role cache + all user caches

Cache is cleared automatically when:
- Permissions are updated via `PermissionController::update()`
- A user is updated via `UserController::update()`
- Roles are created/updated via `RoleManagementController`

## Permission Management UI

### Role-Permission Matrix (Settings > Permissions)

**Route:** `GET /permissions` → `PermissionController@index`
**Permission required:** `manage_permissions`

Displays a matrix table with:
- Rows: permissions grouped by group (Settings, Quotations, Users, System)
- Columns: all roles except `super_admin`
- Checkboxes at each intersection

**Important:** Loans group permissions are excluded from this page. They are managed separately in **Loan Settings > Role Permissions**.

On save (`PUT /permissions` → `PermissionController@update()`):
- Syncs non-Loans permissions for each role
- Preserves existing Loans-group permissions (not affected by this form)
- Super admin always gets all permissions re-synced
- Clears all permission caches
- Logs `permissions_updated` activity

### User-Specific Overrides (User Edit Page)

On the user edit form, if the current user has `manage_permissions` and the target user is not a super admin, a "Permission Overrides" section appears.

Each permission shows a dropdown with three options:
- **Default (role)** -- No override, uses the role-based permission
- **Grant** -- Explicitly grant this permission to the user
- **Deny** -- Explicitly deny this permission for the user

Overrides are stored in the `user_permissions` table and take precedence over role-based permissions (tier 2 in the resolution order).

## Models

### Permission

**File:** `app/Models/Permission.php`

| Field | Type |
|-------|------|
| `name` | string |
| `slug` | string (unique identifier used in code) |
| `group` | string (Settings, Quotations, Users, Loans, System) |
| `description` | string |

**Relationships:**
- `roles()` -- BelongsToMany via `role_permission`
- `userPermissions()` -- HasMany `UserPermission`

### UserPermission

**File:** `app/Models/UserPermission.php`

| Field | Type |
|-------|------|
| `user_id` | foreign key |
| `permission_id` | foreign key |
| `type` | string (`grant` or `deny`) |

**Relationships:**
- `user()` -- BelongsTo `User`
- `permission()` -- BelongsTo `Permission`

## Source Files

| File | Purpose |
|------|---------|
| `config/permissions.php` | Permission definitions (groups + slugs) |
| `app/Services/PermissionService.php` | 3-tier resolution logic + caching |
| `app/Http/Middleware/CheckPermission.php` | Route middleware |
| `app/Models/Permission.php` | Permission model |
| `app/Models/UserPermission.php` | User override model |
| `app/Http/Controllers/PermissionController.php` | Role-permission matrix CRUD |
| `resources/views/permissions/index.blade.php` | Matrix UI |
