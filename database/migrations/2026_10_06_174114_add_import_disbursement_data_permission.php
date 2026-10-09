<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `import_disbursement_data` — the disbursement + OTC correction export/import
 * tool. Seeded into the catalog but granted to NO role: only super_admin reaches
 * it (super_admin bypasses permission checks), and the controller hard-checks the
 * super_admin role on top.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['slug' => 'import_disbursement_data'],
            [
                'name' => 'Import Disbursement Data',
                'group' => 'System',
                'description' => 'Export/import the disbursement + OTC correction sheet (super_admin only)',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
        // Intentionally granted to no role.
    }

    public function down(): void
    {
        $permId = DB::table('permissions')->where('slug', 'import_disbursement_data')->value('id');
        if ($permId) {
            DB::table('role_permission')->where('permission_id', $permId)->delete();
            DB::table('user_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
};
