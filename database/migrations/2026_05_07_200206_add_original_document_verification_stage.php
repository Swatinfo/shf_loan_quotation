<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('stages')->where('stage_key', 'original_document_verification')->exists()) {
            return;
        }

        $now = now();
        DB::table('stages')->insert([
            'stage_key' => 'original_document_verification',
            'is_enabled' => true,
            'stage_name_en' => 'Original Document Verification',
            'stage_name_gu' => 'મૂળ દસ્તાવેજ ચકાસણી',
            'sequence_order' => 4,
            'is_parallel' => false,
            'parent_stage_key' => 'parallel_processing',
            'stage_type' => 'sequential',
            'description_en' => 'Verify physical original documents collected from customer (post legal verification).',
            'description_gu' => null,
            'default_role' => json_encode(['branch_manager', 'loan_advisor', 'office_employee']),
            'assigned_role' => 'task_owner',
            'sub_actions' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('stages')->where('stage_key', 'original_document_verification')->delete();
    }
};
