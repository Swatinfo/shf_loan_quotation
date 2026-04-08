<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add sub_actions JSON column to stages
        if (! Schema::hasColumn('stages', 'sub_actions')) {
            Schema::table('stages', function (Blueprint $table) {
                $table->json('sub_actions')->nullable()->after('default_role');
            });
        }

        // Add sub_actions_override JSON column to product_stages
        if (! Schema::hasColumn('product_stages', 'sub_actions_override')) {
            Schema::table('product_stages', function (Blueprint $table) {
                $table->json('sub_actions_override')->nullable()->after('allow_skip');
            });
        }

        // Seed default_role from the hardcoded STAGE_ROLE_ELIGIBILITY
        $roleMap = [
            'inquiry' => ['loan_advisor', 'branch_manager'],
            'document_selection' => ['loan_advisor', 'branch_manager'],
            'document_collection' => ['loan_advisor', 'branch_manager'],
            'app_number' => ['loan_advisor', 'branch_manager'],
            'bsm_osv' => ['bank_employee'],
            'legal_verification' => ['legal_advisor'],
            'technical_valuation' => ['branch_manager', 'office_employee'],
            'rate_pf' => ['loan_advisor', 'bank_employee', 'branch_manager'],
            'sanction' => ['bank_employee', 'loan_advisor', 'branch_manager'],
            'docket' => ['office_employee', 'branch_manager'],
            'kfs' => ['office_employee', 'loan_advisor', 'branch_manager'],
            'esign' => ['bank_employee', 'loan_advisor', 'branch_manager'],
            'disbursement' => ['loan_advisor', 'branch_manager'],
            'cibil_check' => ['bank_employee'],
            'property_valuation' => ['branch_manager', 'office_employee'],
            'vehicle_valuation' => ['branch_manager', 'office_employee'],
            'business_valuation' => ['branch_manager', 'office_employee'],
            'title_search' => ['legal_advisor'],
            'financial_analysis' => ['bank_employee'],
            'site_visit' => ['branch_manager'],
            'approval_committee' => ['branch_manager'],
            'credit_committee' => ['branch_manager'],
            'insurance' => ['office_employee', 'loan_advisor'],
            'mortgage' => ['office_employee', 'legal_advisor'],
        ];

        foreach ($roleMap as $stageKey => $roles) {
            DB::table('stages')
                ->where('stage_key', $stageKey)
                ->update(['default_role' => json_encode($roles)]);
        }

        // Seed sub_actions for rate_pf
        DB::table('stages')
            ->where('stage_key', 'rate_pf')
            ->update(['sub_actions' => json_encode([
                [
                    'key' => 'bank_rate_details',
                    'name' => 'Bank Rate Details',
                    'sequence' => 1,
                    'roles' => ['bank_employee'],
                    'type' => 'form',
                ],
                [
                    'key' => 'processing_charges',
                    'name' => 'Processing & Charges',
                    'sequence' => 2,
                    'roles' => ['loan_advisor', 'branch_manager', 'office_employee'],
                    'type' => 'form',
                ],
            ])]);

        // Seed sub_actions for sanction
        DB::table('stages')
            ->where('stage_key', 'sanction')
            ->update(['sub_actions' => json_encode([
                [
                    'key' => 'send_for_sanction',
                    'name' => 'Send for Sanction Letter',
                    'sequence' => 1,
                    'roles' => ['loan_advisor', 'branch_manager'],
                    'type' => 'action_button',
                    'action' => 'send_for_sanction',
                    'transfer_to_role' => 'bank_employee',
                ],
                [
                    'key' => 'sanction_generated',
                    'name' => 'Sanction Letter Generated',
                    'sequence' => 2,
                    'roles' => ['bank_employee'],
                    'type' => 'action_button',
                    'action' => 'sanction_generated',
                    'transfer_to_role' => 'loan_advisor',
                ],
                [
                    'key' => 'sanction_details',
                    'name' => 'Sanction Details',
                    'sequence' => 3,
                    'roles' => ['loan_advisor', 'branch_manager'],
                    'type' => 'form',
                ],
            ])]);
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            if (Schema::hasColumn('stages', 'sub_actions')) {
                $table->dropColumn('sub_actions');
            }
        });

        Schema::table('product_stages', function (Blueprint $table) {
            if (Schema::hasColumn('product_stages', 'sub_actions_override')) {
                $table->dropColumn('sub_actions_override');
            }
        });
    }
};
