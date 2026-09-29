<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $slug = 'reset_loan_stages';

    /** @var array<int, string> Roles granted the permission by default (super_admin bypasses). */
    private array $roleSlugs = ['admin', 'branch_manager', 'bdh'];

    public function up(): void
    {
        $now = now();

        $permissionId = DB::table('permissions')->where('slug', $this->slug)->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'Reset Loan Stages',
                'slug' => $this->slug,
                'group' => 'Loans',
                'description' => 'Rewind a loan to an earlier stage (destructive: clears all following stages + dependent data)',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roleIds = DB::table('roles')->whereIn('slug', $this->roleSlugs)->pluck('id');

        $inserts = $roleIds
            ->reject(fn ($roleId) => DB::table('role_permission')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists())
            ->map(fn ($roleId) => [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ])->all();

        if ($inserts) {
            DB::table('role_permission')->insert($inserts);
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('slug', $this->slug)->value('id');
        if (! $id) {
            return;
        }

        DB::table('role_permission')->where('permission_id', $id)->delete();
        DB::table('user_permissions')->where('permission_id', $id)->delete();
        DB::table('permissions')->where('id', $id)->delete();
    }
};
