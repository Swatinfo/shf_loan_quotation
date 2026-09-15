<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<int, array{name:string, slug:string, group:string, description:string}> */
    private array $permissions = [
        [
            'name' => 'Waive Legal Verification',
            'slug' => 'waive_legal_verification',
            'group' => 'Loans',
            'description' => 'Complete Legal Verification without sending to bank, at any phase (in addition to the loan owner / branch manager / BDH)',
        ],
        [
            'name' => 'Verify Original Documents',
            'slug' => 'verify_original_documents',
            'group' => 'Loans',
            'description' => 'Complete Original Document Verification (seen original) even when not the stage assignee',
        ],
    ];

    /** @var array<int, string> */
    private array $roleSlugs = ['admin', 'branch_manager', 'bdh', 'loan_advisor'];

    public function up(): void
    {
        $now = now();
        $roleIds = DB::table('roles')->whereIn('slug', $this->roleSlugs)->pluck('id');

        foreach ($this->permissions as $permission) {
            $existing = DB::table('permissions')->where('slug', $permission['slug'])->value('id');
            if ($existing) {
                continue;
            }

            $permissionId = DB::table('permissions')->insertGetId(array_merge($permission, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            $inserts = $roleIds->map(fn ($roleId) => [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ])->all();

            if ($inserts) {
                DB::table('role_permission')->insert($inserts);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->permissions as $permission) {
            $id = DB::table('permissions')->where('slug', $permission['slug'])->value('id');
            if (! $id) {
                continue;
            }

            DB::table('role_permission')->where('permission_id', $id)->delete();
            DB::table('user_permissions')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
