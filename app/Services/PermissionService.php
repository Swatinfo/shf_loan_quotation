<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    /**
     * Check if a user has a specific permission.
     *
     * Resolution order (3-tier):
     * 1. Super Admin → always true
     * 2. User-specific grant/deny override
     * 3. Any of the user's roles grants the permission
     */
    public function userHasPermission(User $user, string $slug): bool
    {
        // 1. Super Admin bypass
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // 2. Check user-specific override
        $userOverride = $this->getUserOverride($user, $slug);
        if ($userOverride !== null) {
            return $userOverride;
        }

        // 3. Check if ANY of the user's roles has this permission
        return $this->userRolesHavePermission($user, $slug);
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

        if (! isset($overrides[$slug])) {
            return null;
        }

        return $overrides[$slug] === 'grant';
    }

    /**
     * Check if any of the user's roles has a given permission.
     */
    public function userRolesHavePermission(User $user, string $slug): bool
    {
        $roleIds = $this->getUserRoleIds($user);
        if (empty($roleIds)) {
            return false;
        }

        $permSlugs = $this->getRolePermissionSlugs($roleIds);

        return in_array($slug, $permSlugs);
    }

    /**
     * Get all role IDs for a user (cached).
     */
    protected function getUserRoleIds(User $user): array
    {
        $cacheKey = "user_role_ids:{$user->id}";

        return Cache::remember($cacheKey, 300, function () use ($user) {
            return $user->roles()->pluck('roles.id')->toArray();
        });
    }

    /**
     * Get permission slugs for a set of role IDs (cached).
     */
    protected function getRolePermissionSlugs(array $roleIds): array
    {
        sort($roleIds);
        $cacheKey = 'role_perms:'.implode(',', $roleIds);

        return Cache::remember($cacheKey, 300, function () use ($roleIds) {
            return DB::table('role_permission')
                ->whereIn('role_id', $roleIds)
                ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                ->pluck('permissions.slug')
                ->unique()
                ->toArray();
        });
    }

    /**
     * Get all permissions for a user (merged roles + overrides).
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
        Cache::forget("user_role_ids:{$user->id}");
    }

    /**
     * Clear cached permissions for roles.
     */
    public function clearRoleCache(): void
    {
        // Clear all role permission combo caches
        $roleIds = DB::table('roles')->pluck('id')->toArray();
        // Clear individual and combo caches by pattern
        foreach ($roleIds as $id) {
            Cache::forget("role_perms:{$id}");
        }
    }

    /**
     * Clear all permission caches.
     */
    public function clearAllCaches(): void
    {
        $this->clearRoleCache();

        User::pluck('id')->each(function ($userId) {
            Cache::forget("user_perms:{$userId}");
            Cache::forget("user_role_ids:{$userId}");
        });
    }
}
