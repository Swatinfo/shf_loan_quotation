<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Loan payout user: the single beneficiary of the loan's disbursement payout,
 * chosen at quotation→loan conversion and editable later (perm `change_payout_user`).
 * Dropdown = all users except bank_employee/office_employee. The payout slab used
 * switches on the user's role (connector → connector slab; else → standard slab).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_details', function (Blueprint $table) {
            $table->foreignId('payout_user_id')->nullable()->after('dme_user_id')->constrained('users')->nullOnDelete();
        });

        // Permission to change the loan payout user after conversion.
        $permId = DB::table('permissions')->where('slug', 'change_payout_user')->value('id');
        if (! $permId) {
            $permId = DB::table('permissions')->insertGetId([
                'name' => 'Change Payout User',
                'slug' => 'change_payout_user',
                'group' => 'Loans',
                'description' => 'Change the loan payout user (beneficiary of the disbursement payout)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIds = DB::table('roles')->whereIn('slug', ['admin', 'branch_manager', 'bdh'])->pluck('id');
        foreach ($roleIds as $rid) {
            DB::table('role_permission')->insertOrIgnore(['role_id' => $rid, 'permission_id' => $permId]);
        }
    }

    public function down(): void
    {
        Schema::table('loan_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_user_id');
        });

        $permId = DB::table('permissions')->where('slug', 'change_payout_user')->value('id');
        if ($permId) {
            DB::table('role_permission')->where('permission_id', $permId)->delete();
            DB::table('user_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
};
