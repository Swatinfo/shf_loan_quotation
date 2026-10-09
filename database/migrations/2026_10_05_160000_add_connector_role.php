<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New `connector` role (8th role). A connector is an external introducer who can
 * create quotations and download them (plain / unbranded only — they get
 * `download_pdf_plain` but NOT `download_pdf_branded`), and cannot convert to a
 * loan (no `convert_to_loan`). super_admin/admin/branch_manager/bdh convert a
 * connector's quotation to a loan and assign an internal processing user; the
 * loan's payout can then be directed to the connector. The quotation UI already
 * gates its buttons on these permission slugs, so no view change is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (! DB::table('roles')->where('slug', 'connector')->exists()) {
            DB::table('roles')->insert([
                'name' => 'Connector',
                'slug' => 'connector',
                'description' => 'External connector — creates quotations (plain/unbranded) and downloads them; cannot convert to loans. Can be a loan payout beneficiary.',
                'is_system' => true,
                'can_be_advisor' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleId = DB::table('roles')->where('slug', 'connector')->value('id');

        $slugs = [
            'create_quotation', 'edit_quotation', 'generate_pdf', 'view_own_quotations',
            'download_pdf', 'download_pdf_plain',
            'change_own_password', 'view_dashboard', 'manage_notifications',
        ];
        $permIds = DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');

        foreach ($permIds as $pid) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $pid,
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', 'connector')->value('id');
        if ($roleId) {
            DB::table('role_permission')->where('role_id', $roleId)->delete();
            DB::table('role_user')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
