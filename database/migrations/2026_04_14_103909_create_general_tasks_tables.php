<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. general_tasks table ──
        Schema::create('general_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('loan_detail_id')->nullable()->constrained('loan_details')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, in_progress, completed, cancelled
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['created_by', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        // ── 2. general_task_comments table ──
        Schema::create('general_task_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_task_id')->constrained('general_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        // ── 3. Seed view_all_tasks permission ──
        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'View All Tasks',
            'slug' => 'view_all_tasks',
            'group' => 'Tasks',
            'description' => 'View all general tasks across users (read-only)',
        ]);

        // Grant to admin role
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        if ($adminRole && $permissionId) {
            DB::table('role_permission')->insert([
                'role_id' => $adminRole->id,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('general_task_comments');
        Schema::dropIfExists('general_tasks');

        DB::table('role_permission')->whereIn('permission_id', function ($q) {
            $q->select('id')->from('permissions')->where('slug', 'view_all_tasks');
        })->delete();
        DB::table('permissions')->where('slug', 'view_all_tasks')->delete();
    }
};
