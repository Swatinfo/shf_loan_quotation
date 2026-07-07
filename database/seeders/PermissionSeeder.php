<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('permissions');

        // Create/update all permissions from config
        foreach ($config['groups'] as $group => $permissions) {
            foreach ($permissions as $perm) {
                Permission::updateOrCreate(
                    ['slug' => $perm['slug']],
                    [
                        'name' => $perm['name'],
                        'group' => $group,
                        'description' => $perm['description'] ?? null,
                    ]
                );
            }
        }

        // Role-permission mappings are managed by the unified_roles_system migration
        // and editable at runtime via Permissions and Loan Settings pages.
    }
}
