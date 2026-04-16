# Permission System

## Overview

The app uses a 3-tier permission system with 48 permissions across 6 groups (Settings, Quotations, Users, Loans, Customers, System). Permissions are defined in the `permissions` table (seeded via migration), assigned to roles via the `role_permission` pivot table, and resolved by `PermissionService`.

## Architecture

```
roles table                     → 7 roles with slugs
role_permission pivot           → Maps roles to permissions
permissions table               → 48 permission definitions
app/Services/PermissionService  → Resolution logic + caching
app/Http/Middleware/CheckPermission → Route-level enforcement
app/Models/User::hasPermission() → Model-level check (delegates to PermissionService)
```

## Unified Role System (7 Roles)

The system uses a single unified role model — no separate "system role" vs "task role". Users are assigned one or more roles via the `role_user` pivot table.

| Role | Slug | Description |
|------|------|-------------|
| **Super Admin** | `super_admin` | Full system access, bypasses all permissions |
| **Admin** | `admin` | System administration, settings, user management |
| **Branch Manager** | `branch_manager` | Branch-level management, quotations, loan stages |
| **BDO** | `bdo` | Business Development Officer (same access as Branch Manager) |
| **Loan Advisor** | `loan_advisor` | Quotation creation, loan processing stages |
| **Bank Employee** | `bank_employee` | Bank-side loan processing only |
| **Office Employee** | `office_employee` | Office operations, loan stages, document handling |

Users can hold multiple roles (e.g., `admin` + `branch_manager`). Permissions are the **union** of all assigned roles.

## Resolution Order

When checking if a user has a permission (3-tier):

1. **Super Admin Bypass**: If user has `super_admin` role → always `true`
2. **User Override**: Check `user_permissions` table for explicit `grant` or `deny` for this user + permission
3. **Role Permissions**: Check if ANY of the user's roles (via `role_permission` pivot) grants the permission

```php
// PermissionService::userHasPermission()
public function userHasPermission(User $user, string $slug): bool
{
    if ($user->hasRole('super_admin')) return true;

    $override = $this->getUserOverride($user, $slug);
    if ($override !== null) return $override;

    return $this->userRolesHavePermission($user, $slug);
}
```

## All Permissions (48)

### Settings Group
| Slug | Description | Admin | Branch Mgr | BDO | Loan Advisor | Bank Emp | Office Emp |
|------|-------------|-------|------------|-----|-------------|---------|------------|
| `view_settings` | View settings page | yes | no | no | no | no | no |
| `edit_company_info` | Edit company details | yes | no | no | no | no | no |
| `edit_banks` | Edit bank list | yes | no | no | no | no | no |
| `edit_tenures` | Edit tenure options | yes | no | no | no | no | no |
| `edit_documents` | Edit document lists | yes | no | no | no | no | no |
| `edit_charges` | Edit IOM/bank charges | yes | no | no | no | no | no |
| `edit_services` | Edit services list | yes | no | no | no | no | no |
| `edit_gst` | Edit GST percentage | yes | no | no | no | no | no |

### Quotations Group
| Slug | Description | Admin | Branch Mgr | BDO | Loan Advisor | Bank Emp | Office Emp |
|------|-------------|-------|------------|-----|-------------|---------|------------|
| `create_quotation` | Create new quotations | yes | yes | yes | yes | no | no |
| `generate_pdf` | Generate PDF files | yes | yes | yes | yes | no | no |
| `view_own_quotations` | View own quotations | yes | yes | yes | yes | no | no |
| `view_all_quotations` | View all users' quotations | yes | yes | yes | no | no | no |
| `download_pdf` | Download PDF files | yes | yes | yes | yes | no | no |
| `delete_quotations` | Delete quotations | yes | no | no | no | no | no |

### Users Group
| Slug | Description | Admin | Branch Mgr | BDO | Loan Advisor | Bank Emp | Office Emp |
|------|-------------|-------|------------|-----|-------------|---------|------------|
| `view_users` | View user list | yes | yes | yes | no | no | no |
| `create_users` | Create new users | yes | no | no | no | no | no |
| `edit_users` | Edit existing users | yes | no | no | no | no | no |
| `delete_users` | Delete users | no | no | no | no | no | no |
| `assign_roles` | Assign user roles | yes | no | no | no | no | no |

### Loans Group
| Slug | Description | Admin | Branch Mgr | BDO | Loan Advisor | Bank Emp | Office Emp |
|------|-------------|-------|------------|-----|-------------|---------|------------|
| `convert_to_loan` | Convert quotation to loan | yes | yes | yes | yes | no | no |
| `view_loans` | View loan task list | yes | yes | yes | yes | yes | yes |
| `view_all_loans` | View all loans across users | yes | yes | yes | no | no | no |
| `create_loan` | Create loan tasks directly | yes | yes | yes | yes | no | no |
| `edit_loan` | Edit loan details | yes | yes | yes | yes | no | yes |
| `delete_loan` | Delete loan tasks | yes | no | no | no | no | no |
| `manage_loan_documents` | Mark documents received/pending | yes | yes | yes | yes | no | yes |
| `upload_loan_documents` | Upload document files | yes | yes | yes | yes | no | yes |
| `download_loan_documents` | Download/preview document files | yes | yes | yes | yes | yes | yes |
| `delete_loan_files` | Remove uploaded document files | yes | yes | yes | no | no | no |
| `manage_loan_stages` | Update stage status and assignments | yes | yes | yes | yes | no | yes |
| `add_remarks` | Add remarks to loan stages | yes | yes | yes | yes | yes | yes |
| `manage_workflow_config` | Configure workflow settings | yes | no | no | no | no | no |
| `transfer_loan_stages` | Transfer stage to another user | yes | yes | yes | yes | no | yes |
| `reject_loan` | Reject a loan application | yes | yes | yes | yes | no | yes |
| `change_loan_status` | Put loan on hold or cancel | yes | yes | yes | yes | no | yes |
| `view_loan_timeline` | View loan stage timeline | yes | yes | yes | yes | yes | yes |
| `manage_disbursement` | Process loan disbursement | yes | yes | yes | yes | no | no |
| `manage_valuation` | Fill and edit valuation details | yes | yes | yes | no | no | yes |
| `raise_query` | Raise queries on loan stages | yes | yes | yes | yes | yes | yes |
| `resolve_query` | Resolve raised queries | yes | yes | yes | yes | no | yes |

### Customers Group
| Slug | Description | Admin | Branch Mgr | BDO | Loan Advisor | Bank Emp | Office Emp |
|------|-------------|-------|------------|-----|-------------|---------|------------|
| `manage_customers` | Create and edit customer records | yes | yes | yes | yes | no | yes |
| `view_customers` | View customer list and details | yes | yes | yes | yes | yes | yes |

### System Group
| Slug | Description | Admin | Branch Mgr | BDO | Loan Advisor | Bank Emp | Office Emp |
|------|-------------|-------|------------|-----|-------------|---------|------------|
| `change_own_password` | Change own password | yes | yes | yes | yes | yes | yes |
| `manage_permissions` | Manage permission matrix | no | no | no | no | no | no |
| `view_activity_log` | View activity log | yes | yes | yes | no | no | no |
| `impersonate_users` | Log in as another user | no | no | no | no | no | no |
| `view_dashboard` | Access the dashboard | yes | yes | yes | yes | yes | yes |
| `manage_notifications` | View and manage notifications | yes | yes | yes | yes | yes | yes |

**Note**: `super_admin` always has ALL permissions (hardcoded bypass in PermissionService). `manage_permissions` and `impersonate_users` are super_admin-only by default.

**Note**: Role management (create, edit, advisor eligibility) is available to super_admin via `/roles`.

**Total**: 48 permissions across 6 groups.

## Middleware Usage

### CheckPermission Middleware

Registered as alias `permission` in `bootstrap/app.php`.

**Usage in routes**:
```php
Route::get('/users', [UserController::class, 'index'])
    ->middleware('permission:view_users');
```

**Implementation**:
```php
public function handle(Request $request, Closure $next, string $permission): Response
{
    if (!$request->user()) abort(403);
    if (!$request->user()->hasPermission($permission)) abort(403);
    return $next($request);
}
```

### Checking in Controllers

For fine-grained checks within controller methods:
```php
if (!auth()->user()->hasPermission('view_all_quotations')) {
    // User can only see own quotations
    $query->where('user_id', auth()->id());
}
```

### Checking in Views

```blade
@if(auth()->user()->hasPermission('create_quotation'))
    <a href="{{ route('quotations.create') }}" class="btn btn-accent">New Quotation</a>
@endif
```

## User Permission Overrides

Admins can set per-user overrides via the user edit page (`/users/{user}/edit`):

| Override Type | Effect |
|--------------|--------|
| `grant` | User gets permission regardless of role defaults |
| `deny` | User loses permission regardless of role defaults |
| `default` (no record) | Falls through to role defaults |

**Stored in `user_permissions` table**:
```
| user_id | permission_id | type  |
|---------|--------------|-------|
| 5       | 3            | grant |  -- User 5 explicitly granted permission 3
| 5       | 7            | deny  |  -- User 5 explicitly denied permission 7
```

## Caching

| Cache Key | TTL | Content |
|-----------|-----|---------|
| `user_perms:{userId}` | 300s (5 min) | User's permission overrides |
| `user_role_ids:{userId}` | 300s (5 min) | User's role IDs |
| `role_perms:{roleIds}` | 300s (5 min) | Combined role permission slugs |

**Cache Clearing**:
```php
PermissionService::clearUserCache($user)   // Single user
PermissionService::clearRoleCache()        // All roles
PermissionService::clearAllCaches()        // Everything
```

Caches are cleared automatically when:
- Permissions matrix is updated (`PermissionController@update`)
- User permissions are changed (`UserController@update`)

## Permission Management UI

**Route**: `GET /permissions` (requires `manage_permissions`)

The UI shows a matrix grid:
- Rows = individual permissions grouped by category
- Columns = all 7 roles
- Super_admin always checked/disabled
- Checkboxes to toggle each role-permission combination
- Save updates all `role_permission` records and clears cache

## Database Tables

### roles
Stores role definitions (seeded via migration).

### role_user
Maps users to roles (many-to-many pivot).

### role_permission
Maps roles to permissions (many-to-many pivot).

### user_permissions
Per-user overrides (updated via User Edit page).

See [database.md](database.md) for full schema.
