<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permission = [
        'name' => 'Edit Quotation',
        'slug' => 'edit_quotation',
        'group' => 'Quotations',
        'description' => 'Edit a quotation until it is converted to a loan (creator + branch_manager + bdh)',
    ];

    private array $roleSlugs = ['admin', 'branch_manager', 'bdh', 'loan_advisor'];

    public function up(): void
    {
        $now = now();

        $existing = DB::table('permissions')->where('slug', $this->permission['slug'])->value('id');
        if ($existing) {
            return;
        }

        $permissionId = DB::table('permissions')->insertGetId(array_merge($this->permission, [
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $roleIds = DB::table('roles')->whereIn('slug', $this->roleSlugs)->pluck('id');

        $inserts = $roleIds->map(fn ($roleId) => [
            'role_id' => $roleId,
            'permission_id' => $permissionId,
        ])->all();

        if ($inserts) {
            DB::table('role_permission')->insert($inserts);
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('slug', $this->permission['slug'])->value('id');
        if (! $id) {
            return;
        }

        DB::table('role_permission')->where('permission_id', $id)->delete();
        DB::table('user_permissions')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();
    }
};
