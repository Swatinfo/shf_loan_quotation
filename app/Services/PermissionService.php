<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    /**
     * Check if a user has a specific permission.
     *
     * Resolution order:
     * 1. Super Admin → always true
     * 2. User-specific grant/deny override
     * 3. Role default permissions
     */
    public function userHasPermission(User $user, string $slug): bool
    {
        // Super Admin bypass
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Check user-specific override
        $userOverride = $this->getUserOverride($user, $slug);
        if ($userOverride !== null) {
            return $userOverride;
        }

        // Fall back to role default
        return $this->roleHasPermission($user->role, $slug);
    }

    /**
     * Check for user-specific permission override.
     * Returns true (grant), false (deny), or null (no override).
     */
    protected function getUserOverride(User $user, string $slug): ?bool
    {
        $cacheKey = "user_perms:{$user->id}";

        $overrides = Cache::remember($cacheKey, 300, function () use ($user) {
            return UserPermission::where('user_id', $user->id)
                ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
                ->pluck('user_permissions.type', 'permissions.slug')
                ->toArray();
        });

        if (!isset($overrides[$slug])) {
            return null;
        }

        return $overrides[$slug] === 'grant';
    }

    /**
     * Check if a role has a permission by default.
     */
    public function roleHasPermission(string $role, string $slug): bool
    {
        $cacheKey = "role_perms:{$role}";

        $permissions = Cache::remember($cacheKey, 300, function () use ($role) {
            return RolePermission::where('role', $role)
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->pluck('permissions.slug')
                ->toArray();
        });

        return in_array($slug, $permissions);
    }

    /**
     * Get all permissions for a user (merged role + overrides).
     */
    public function getUserPermissions(User $user): array
    {
        $allPermissions = Permission::all();
        $result = [];

        foreach ($allPermissions as $permission) {
            $result[$permission->slug] = $this->userHasPermission($user, $permission->slug);
        }

        return $result;
    }

    /**
     * Get all permissions grouped.
     */
    public function getGroupedPermissions(): array
    {
        return Permission::all()->groupBy('group')->toArray();
    }

    /**
     * Clear cached permissions for a user.
     */
    public function clearUserCache(User $user): void
    {
        Cache::forget("user_perms:{$user->id}");
    }

    /**
     * Clear cached permissions for a role.
     */
    public function clearRoleCache(string $role): void
    {
        Cache::forget("role_perms:{$role}");
    }

    /**
     * Clear all permission caches.
     */
    public function clearAllCaches(): void
    {
        foreach (['super_admin', 'admin', 'staff'] as $role) {
            $this->clearRoleCache($role);
        }

        // Clear user-specific caches
        User::pluck('id')->each(function ($userId) {
            Cache::forget("user_perms:{$userId}");
        });
    }
}
