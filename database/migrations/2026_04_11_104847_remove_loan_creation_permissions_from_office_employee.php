<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $officeEmployeeRoleId = DB::table('roles')->where('slug', 'office_employee')->value('id');
        if (! $officeEmployeeRoleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['convert_to_loan', 'create_loan'])
            ->pluck('id')
            ->toArray();

        DB::table('role_permission')
            ->where('role_id', $officeEmployeeRoleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }

    public function down(): void
    {
        $officeEmployeeRoleId = DB::table('roles')->where('slug', 'office_employee')->value('id');
        if (! $officeEmployeeRoleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['convert_to_loan', 'create_loan'])
            ->pluck('id')
            ->toArray();

        foreach ($permissionIds as $permId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $officeEmployeeRoleId,
                'permission_id' => $permId,
            ]);
        }
    }
};
