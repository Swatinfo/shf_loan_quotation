# Permission System

## Overview

The app uses a 3-tier permission system with 34 permissions across 6 groups. Permissions are defined in `config/permissions.php`, seeded via `PermissionSeeder`, and resolved by `PermissionService`.

## Architecture

```
config/permissions.php          → Permission definitions + role defaults
database/seeders/PermissionSeeder → Seeds permissions + role_permissions tables
app/Services/PermissionService  → Resolution logic + caching
app/Http/Middleware/CheckPermission → Route-level enforcement
app/Models/User::hasPermission() → Model-level check (delegates to PermissionService)
```

## Resolution Order

When checking if a user has a permission:

1. **Super Admin Bypass**: If `user->role === 'super_admin'` → always `true`
2. **User Override**: Check `user_permissions` table for explicit `grant` or `deny` for this user + permission
3. **Role Default**: Check `role_permissions` table for the user's role + permission
4. **No Match**: Returns `false`

```php
// PermissionService::userHasPermission()
public function userHasPermission(User $user, string $slug): bool
{
    if ($user->isSuperAdmin()) return true;

    $override = $this->getUserOverride($user, $slug);
    if ($override !== null) return $override;

    return $this->roleHasPermission($user->role, $slug);
}
```

## All Permissions

### Settings Group
| Slug | Description | Admin Default | Staff Default |
|------|-------------|---------------|---------------|
| `view_settings` | View settings page | yes | no |
| `edit_company_info` | Edit company details | yes | no |
| `edit_banks` | Edit bank list | yes | no |
| `edit_tenures` | Edit tenure options | yes | no |
| `edit_documents` | Edit document lists | yes | no |
| `edit_charges` | Edit IOM/bank charges | yes | no |
| `edit_services` | Edit services list | yes | no |
| `edit_gst` | Edit GST percentage | yes | no |

### Quotations Group
| Slug | Description | Admin Default | Staff Default |
|------|-------------|---------------|---------------|
| `create_quotation` | Create new quotations | yes | yes |
| `generate_pdf` | Generate PDF files | yes | yes |
| `view_all_quotations` | View all users' quotations | yes | no |
| `download_pdf` | Download PDF files | yes | yes |
| `view_own_quotations` | View own quotations | yes | yes |
| `delete_quotations` | Delete quotations | yes | no |

### Users Group
| Slug | Description | Admin Default | Staff Default |
|------|-------------|---------------|---------------|
| `view_users` | View user list | yes | no |
| `create_users` | Create new users | yes | no |
| `edit_users` | Edit existing users | yes | no |
| `delete_users` | Delete users | no | no |
| `assign_roles` | Assign user roles | yes | no |

### System Group
| Slug | Description | Admin Default | Staff Default |
|------|-------------|---------------|---------------|
| `manage_permissions` | Manage permission matrix | no | no |
| `view_activity_log` | View activity log | yes | no |
| `change_own_password` | Change own password | yes | yes |

### Loans Group
| Slug | Description | Admin Default | Staff Default |
|------|-------------|---------------|---------------|
| `convert_to_loan` | Convert quotation to loan task | yes | yes |
| `view_loans` | View loan task list | yes | yes |
| `view_all_loans` | View all loans across users | yes | no |
| `create_loan` | Create loan tasks directly | yes | yes |
| `edit_loan` | Edit loan details | yes | no |
| `delete_loan` | Delete loan tasks | yes | no |
| `manage_loan_documents` | Mark documents received/pending | yes | yes |
| `manage_loan_stages` | Update stage status and assignments | yes | yes |
| `skip_loan_stages` | Skip stages in loan workflow | yes | no |
| `add_remarks` | Add remarks to loan stages | yes | yes |
| `manage_workflow_config` | Configure workflow settings | yes | no |

**Note**: `super_admin` always has ALL permissions (hardcoded bypass in PermissionService).

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
    // Staff can only see own quotations
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
| `grant` | User gets permission regardless of role default |
| `deny` | User loses permission regardless of role default |
| `default` (no record) | Falls through to role default |

**Stored in `user_permissions` table**:
```
| user_id | permission_id | type  |
|---------|--------------|-------|
| 5       | 3            | grant |  ← User 5 explicitly granted permission 3
| 5       | 7            | deny  |  ← User 5 explicitly denied permission 7
```

## Caching

| Cache Key | TTL | Content |
|-----------|-----|---------|
| `user_perms:{userId}` | 300s (5 min) | User's permission overrides |
| `role_perms:{role}` | 300s (5 min) | Role's default permissions |

**Cache Clearing**:
```php
PermissionService::clearUserCache($user)   // Single user
PermissionService::clearRoleCache($role)   // Single role
PermissionService::clearAllCaches()        // All roles + all users
```

Caches are cleared automatically when:
- Permissions matrix is updated (`PermissionController@update`)
- User permissions are changed (`UserController@update`)

## Permission Management UI

**Route**: `GET /permissions` (requires `manage_permissions`)

The UI shows a matrix grid:
- Rows = individual permissions grouped by category
- Columns = roles (super_admin always checked/disabled, admin, staff)
- Checkboxes to toggle each role-permission combination
- Save updates all `role_permissions` records and clears cache

## Database Tables

### permissions
Stores permission definitions (seeded, rarely changes).

### role_permissions
Maps roles to permissions (updated via Permission Management UI).

### user_permissions
Per-user overrides (updated via User Edit page).

See [database.md](database.md) for full schema.
