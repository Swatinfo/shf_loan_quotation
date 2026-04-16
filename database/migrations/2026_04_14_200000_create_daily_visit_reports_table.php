<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_visit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('visit_date');
            $table->string('contact_name');
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_type');
            $table->string('purpose');
            $table->text('notes')->nullable();
            $table->text('outcome')->nullable();
            $table->boolean('follow_up_needed')->default(false);
            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->boolean('is_follow_up_done')->default(false);
            $table->foreignId('parent_visit_id')->nullable()->constrained('daily_visit_reports')->nullOnDelete();
            $table->foreignId('follow_up_visit_id')->nullable()->constrained('daily_visit_reports')->nullOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loan_details')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'visit_date']);
            $table->index('follow_up_date');
            $table->index(['follow_up_needed', 'is_follow_up_done']);
        });

        // Seed DVR permissions
        $dvrPermissions = [
            ['slug' => 'view_dvr', 'name' => 'View DVR', 'group' => 'DVR', 'description' => 'View daily visit reports'],
            ['slug' => 'create_dvr', 'name' => 'Create DVR', 'group' => 'DVR', 'description' => 'Create daily visit reports'],
            ['slug' => 'edit_dvr', 'name' => 'Edit DVR', 'group' => 'DVR', 'description' => 'Edit daily visit reports'],
            ['slug' => 'delete_dvr', 'name' => 'Delete DVR', 'group' => 'DVR', 'description' => 'Delete daily visit reports'],
            ['slug' => 'view_all_dvr', 'name' => 'View All DVR', 'group' => 'DVR', 'description' => 'View all daily visit reports across users'],
        ];

        $now = now();
        foreach ($dvrPermissions as $perm) {
            \Illuminate\Support\Facades\DB::table('permissions')->insert(array_merge($perm, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // Assign DVR permissions to roles
        $rolePermMap = [
            'super_admin' => ['view_dvr', 'create_dvr', 'edit_dvr', 'delete_dvr', 'view_all_dvr'],
            'admin' => ['view_dvr', 'view_all_dvr', 'delete_dvr'],
            'branch_manager' => ['view_dvr', 'create_dvr', 'edit_dvr'],
            'bdh' => ['view_dvr', 'create_dvr', 'edit_dvr'],
            'loan_advisor' => ['view_dvr', 'create_dvr', 'edit_dvr'],
            'office_employee' => ['view_dvr', 'create_dvr', 'edit_dvr'],
        ];

        foreach ($rolePermMap as $roleSlug => $permSlugs) {
            $roleId = \Illuminate\Support\Facades\DB::table('roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) {
                continue;
            }

            foreach ($permSlugs as $permSlug) {
                $permId = \Illuminate\Support\Facades\DB::table('permissions')->where('slug', $permSlug)->value('id');
                if ($permId) {
                    \Illuminate\Support\Facades\DB::table('role_permission')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_visit_reports');

        $slugs = ['view_dvr', 'create_dvr', 'edit_dvr', 'delete_dvr', 'view_all_dvr'];
        $permIds = \Illuminate\Support\Facades\DB::table('permissions')->whereIn('slug', $slugs)->pluck('id');
        \Illuminate\Support\Facades\DB::table('role_permission')->whereIn('permission_id', $permIds)->delete();
        \Illuminate\Support\Facades\DB::table('permissions')->whereIn('slug', $slugs)->delete();
    }
};
