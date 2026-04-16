<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Remove skip_loan_stages permission from all roles
        $skipPermId = DB::table('permissions')->where('slug', 'skip_loan_stages')->value('id');
        if ($skipPermId) {
            DB::table('role_permission')->where('permission_id', $skipPermId)->delete();
        }

        // Set allow_skip = false on all product_stages
        DB::table('product_stages')->update(['allow_skip' => false]);
    }

    public function down(): void
    {
        // Restore skip_loan_stages to super_admin
        $skipPermId = DB::table('permissions')->where('slug', 'skip_loan_stages')->value('id');
        $superAdminId = DB::table('roles')->where('slug', 'super_admin')->value('id');
        if ($skipPermId && $superAdminId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $superAdminId,
                'permission_id' => $skipPermId,
            ]);
        }

        // Restore allow_skip = true on all product_stages
        DB::table('product_stages')->update(['allow_skip' => true]);
    }
};
