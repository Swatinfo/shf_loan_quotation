<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('task_role')->nullable()->after('phone');
            $table->string('employee_id')->nullable()->after('task_role');
            $table->foreignId('default_branch_id')->nullable()->after('employee_id')
                ->constrained('branches')->nullOnDelete();
            $table->foreignId('task_bank_id')->nullable()->after('default_branch_id')
                ->constrained('banks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_bank_id');
            $table->dropConstrainedForeignId('default_branch_id');
            $table->dropColumn(['employee_id', 'task_role']);
        });
    }
};
