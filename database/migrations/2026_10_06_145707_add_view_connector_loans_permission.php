<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `view_connector_loans` — read-only access for a connector to the loans created
 * from their own quotations (list, loan detail, stages, timeline). Seeds the
 * catalog row and grants it to the connector role. All mutating loan routes keep
 * their own permissions, which connectors do not have, so this is view-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['slug' => 'view_connector_loans'],
            [
                'name' => 'View Connector Loans',
                'group' => 'Loans',
                'description' => "Read-only view of loans created from the connector's own quotations (list, loan detail, stages, timeline)",
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $permId = DB::table('permissions')->where('slug', 'view_connector_loans')->value('id');
        $roleId = DB::table('roles')->where('slug', 'connector')->value('id');

        if ($permId && $roleId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permId,
            ]);
        }
    }

    public function down(): void
    {
        $permId = DB::table('permissions')->where('slug', 'view_connector_loans')->value('id');
        if ($permId) {
            DB::table('role_permission')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }
};
