<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reports (Loan Pipeline + Loan Report) are now available to every user via
 * the previously-orphaned `view_reports` permission. Ensure the permission row
 * exists and grant it to all roles. (Management Summary stays role-gated to
 * super_admin / admin / bdh in the controller — no permission needed there.)
 */
return new class extends Migration
{
    public function up(): void
    {
        $permId = DB::table('permissions')->where('slug', 'view_reports')->value('id');

        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => 'View Reports',
                'slug' => 'view_reports',
                'group' => 'System',
                'description' => 'View the Loan Pipeline and Loan Report pages',
            ]);
        }

        foreach (DB::table('roles')->pluck('id') as $roleId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permId,
            ]);
        }
    }

    public function down(): void
    {
        $permId = DB::table('permissions')->where('slug', 'view_reports')->value('id');

        if ($permId) {
            DB::table('role_permission')->where('permission_id', $permId)->delete();
        }
    }
};
