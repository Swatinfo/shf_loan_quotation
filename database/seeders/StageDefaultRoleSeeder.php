<?php

namespace Database\Seeders;

use App\Models\Stage;
use Illuminate\Database\Seeder;

class StageDefaultRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roleMap = [
            'inquiry' => 'loan_advisor',
            'document_selection' => 'loan_advisor',
            'document_collection' => 'loan_advisor',
            'parallel_processing' => null,
            'app_number' => 'loan_advisor',
            'bsm_osv' => 'bank_employee',
            'legal_verification' => 'loan_advisor',
            'technical_valuation' => 'office_employee',
            'rate_pf' => 'loan_advisor',
            'sanction' => 'bank_employee',
            'docket' => 'office_employee',
            'kfs' => 'loan_advisor',
            'esign' => 'bank_employee',
            'disbursement' => 'loan_advisor',
            'property_valuation' => 'office_employee',
        ];

        foreach ($roleMap as $stageKey => $role) {
            Stage::where('stage_key', $stageKey)->update(['default_role' => $role]);
        }

        $this->command->info('Default roles assigned to '.count($roleMap).' stages.');
    }
}
