<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add bank_employee to default_role for legal_verification, rate_pf, and sanction stages.
     * This enables product stage config (location-based BE assignment) for these multi-phase stages,
     * matching the pattern already used by bsm_osv and esign.
     */
    public function up(): void
    {
        $stages = [
            'legal_verification' => '["branch_manager","loan_advisor","bank_employee"]',
            'rate_pf' => '["branch_manager","loan_advisor","bank_employee"]',
            'sanction' => '["branch_manager","loan_advisor","bank_employee"]',
        ];

        foreach ($stages as $key => $roles) {
            DB::table('stages')->where('stage_key', $key)->update(['default_role' => $roles]);
        }
    }

    public function down(): void
    {
        $stages = [
            'legal_verification' => '["branch_manager","loan_advisor"]',
            'rate_pf' => '["branch_manager","loan_advisor"]',
            'sanction' => '["branch_manager","loan_advisor"]',
        ];

        foreach ($stages as $key => $roles) {
            DB::table('stages')->where('stage_key', $key)->update(['default_role' => $roles]);
        }
    }
};
