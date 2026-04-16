<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $oeRoleId = DB::table('roles')->where('slug', 'office_employee')->value('id');
        if (! $oeRoleId) {
            return;
        }

        // Remove reject_loan, change_loan_status, resolve_query from office_employee
        $removePerms = DB::table('permissions')
            ->whereIn('slug', ['reject_loan', 'change_loan_status', 'resolve_query'])
            ->pluck('id')
            ->toArray();

        if ($removePerms) {
            DB::table('role_permission')
                ->where('role_id', $oeRoleId)
                ->whereIn('permission_id', $removePerms)
                ->delete();
        }

        // Add change_loan_status to loan_advisor (for on-hold)
        $laRoleId = DB::table('roles')->where('slug', 'loan_advisor')->value('id');
        $clsPermId = DB::table('permissions')->where('slug', 'change_loan_status')->value('id');

        if ($laRoleId && $clsPermId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $laRoleId,
                'permission_id' => $clsPermId,
            ]);
        }
    }

    public function down(): void
    {
        $oeRoleId = DB::table('roles')->where('slug', 'office_employee')->value('id');
        if (! $oeRoleId) {
            return;
        }

        // Re-add the removed permissions
        $reAddPerms = DB::table('permissions')
            ->whereIn('slug', ['reject_loan', 'change_loan_status', 'resolve_query'])
            ->pluck('id')
            ->toArray();

        foreach ($reAddPerms as $permId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $oeRoleId,
                'permission_id' => $permId,
            ]);
        }

        // Remove change_loan_status from loan_advisor
        $laRoleId = DB::table('roles')->where('slug', 'loan_advisor')->value('id');
        $clsPermId = DB::table('permissions')->where('slug', 'change_loan_status')->value('id');

        if ($laRoleId && $clsPermId) {
            DB::table('role_permission')
                ->where('role_id', $laRoleId)
                ->where('permission_id', $clsPermId)
                ->delete();
        }
    }
};
