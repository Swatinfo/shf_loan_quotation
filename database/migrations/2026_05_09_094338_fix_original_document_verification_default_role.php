<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The original ODV migration stored default_role with the virtual `task_owner`
 * slug, which doesn't match any real role in `hasAnyRole(...)`. That made the
 * stage's eligible-user pool collapse to office_employees only, breaking the
 * transfer dropdown and skewing auto-assignment. Mirror the seeder's expanded
 * slug list so existing installs match fresh installs.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('stages')
            ->where('stage_key', 'original_document_verification')
            ->update([
                'default_role' => json_encode(['branch_manager', 'loan_advisor', 'office_employee']),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('stages')
            ->where('stage_key', 'original_document_verification')
            ->update([
                'default_role' => json_encode(['task_owner', 'office_employee']),
                'updated_at' => now(),
            ]);
    }
};
