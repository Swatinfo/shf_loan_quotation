<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('permissions');

        // Create all permissions
        $permissionIds = [];
        foreach ($config['groups'] as $group => $permissions) {
            foreach ($permissions as $perm) {
                $permission = Permission::updateOrCreate(
                    ['slug' => $perm['slug']],
                    [
                        'name' => $perm['name'],
                        'group' => $group,
                        'description' => $perm['description'] ?? null,
                    ]
                );
                $permissionIds[$perm['slug']] = $permission->id;
            }
        }

        // Assign role defaults
        foreach ($config['role_defaults'] as $role => $slugs) {
            // Clear existing role permissions
            RolePermission::where('role', $role)->delete();

            if ($slugs === '*') {
                // All permissions
                foreach ($permissionIds as $slug => $id) {
                    RolePermission::create(['role' => $role, 'permission_id' => $id]);
                }
            } else {
                foreach ($slugs as $slug) {
                    if (isset($permissionIds[$slug])) {
                        RolePermission::create(['role' => $role, 'permission_id' => $permissionIds[$slug]]);
                    }
                }
            }
        }
    }
}
