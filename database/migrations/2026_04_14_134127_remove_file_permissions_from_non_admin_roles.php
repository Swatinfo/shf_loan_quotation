<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permSlugs = ['upload_loan_documents', 'download_loan_documents', 'delete_loan_files'];
        $keepRoleSlugs = ['super_admin', 'admin'];

        $permIds = DB::table('permissions')->whereIn('slug', $permSlugs)->pluck('id')->toArray();
        $keepRoleIds = DB::table('roles')->whereIn('slug', $keepRoleSlugs)->pluck('id')->toArray();

        if ($permIds) {
            DB::table('role_permission')
                ->whereIn('permission_id', $permIds)
                ->whereNotIn('role_id', $keepRoleIds)
                ->delete();
        }
    }

    public function down(): void
    {
        // Re-grant would need to know original state — handled by seeder
    }
};
