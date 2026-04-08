<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stages')->insert([
            'stage_key' => 'otc_clearance',
            'is_enabled' => true,
            'stage_name_en' => 'OTC Clearance',
            'stage_name_gu' => 'OTC ક્લિયરન્સ',
            'sequence_order' => 11,
            'is_parallel' => false,
            'parent_stage_key' => null,
            'stage_type' => 'sequential',
            'description_en' => 'Cheque handover and OTC clearance',
            'description_gu' => null,
            'default_role' => json_encode(['branch_manager', 'loan_advisor', 'office_employee']),
            'sub_actions' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('stages')->where('stage_key', 'otc_clearance')->delete();
    }
};
