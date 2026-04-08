<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the database with all production data as of 2026-04-08.
 * This seeder captures every record from the live database to allow
 * a fresh install to start with the exact same data.
 *
 * Run: php artisan db:seed --class=DefaultDataSeeder
 */
class DefaultDataSeeder extends Seeder
{
    public function run(): void
    {
        // Order matters: parent tables first, then children with FKs

        $this->seedPermissions();
        $this->seedLocations();
        $this->seedBranches();
        $this->seedBanks();
        $this->seedStages();
        $this->seedUsers();
        $this->seedRolePermissions();
        $this->seedUserBranches();
        $this->seedBankEmployees();
        $this->seedBankLocations();
        $this->seedLocationUsers();
        $this->seedBankCharges();
        $this->seedAppConfig();
        $this->seedAppSettings();
        $this->seedQuotations();
        $this->seedQuotationBanks();
        $this->seedQuotationEmi();
        $this->seedQuotationDocuments();
    }

    private function seedPermissions(): void
    {
        $permissions = [
            ['id' => 1, 'name' => 'View Settings', 'slug' => 'view_settings', 'group' => 'Settings', 'description' => 'View the settings page'],
            ['id' => 2, 'name' => 'Edit Company Info', 'slug' => 'edit_company_info', 'group' => 'Settings', 'description' => 'Edit company information'],
            ['id' => 3, 'name' => 'Edit Banks', 'slug' => 'edit_banks', 'group' => 'Settings', 'description' => 'Add/edit/remove banks'],
            ['id' => 4, 'name' => 'Edit Documents', 'slug' => 'edit_documents', 'group' => 'Settings', 'description' => 'Add/edit/remove required documents'],
            ['id' => 5, 'name' => 'Edit Tenures', 'slug' => 'edit_tenures', 'group' => 'Settings', 'description' => 'Add/edit/remove loan tenures'],
            ['id' => 6, 'name' => 'Edit Charges', 'slug' => 'edit_charges', 'group' => 'Settings', 'description' => 'Edit bank charges'],
            ['id' => 7, 'name' => 'Edit Services', 'slug' => 'edit_services', 'group' => 'Settings', 'description' => 'Edit service charges'],
            ['id' => 8, 'name' => 'Edit GST', 'slug' => 'edit_gst', 'group' => 'Settings', 'description' => 'Edit GST percentage'],
            ['id' => 9, 'name' => 'Create Quotation', 'slug' => 'create_quotation', 'group' => 'Quotations', 'description' => 'Create new loan quotations'],
            ['id' => 10, 'name' => 'Generate PDF', 'slug' => 'generate_pdf', 'group' => 'Quotations', 'description' => 'Generate PDF for quotations'],
            ['id' => 11, 'name' => 'View Own Quotations', 'slug' => 'view_own_quotations', 'group' => 'Quotations', 'description' => 'View quotations created by self'],
            ['id' => 12, 'name' => 'View All Quotations', 'slug' => 'view_all_quotations', 'group' => 'Quotations', 'description' => 'View all quotations across users'],
            ['id' => 13, 'name' => 'Delete Quotations', 'slug' => 'delete_quotations', 'group' => 'Quotations', 'description' => 'Delete quotations'],
            ['id' => 14, 'name' => 'Download PDF', 'slug' => 'download_pdf', 'group' => 'Quotations', 'description' => 'Download generated PDFs'],
            ['id' => 15, 'name' => 'View Users', 'slug' => 'view_users', 'group' => 'Users', 'description' => 'View the users list'],
            ['id' => 16, 'name' => 'Create Users', 'slug' => 'create_users', 'group' => 'Users', 'description' => 'Create new user accounts'],
            ['id' => 17, 'name' => 'Edit Users', 'slug' => 'edit_users', 'group' => 'Users', 'description' => 'Edit existing user accounts'],
            ['id' => 18, 'name' => 'Delete Users', 'slug' => 'delete_users', 'group' => 'Users', 'description' => 'Delete user accounts'],
            ['id' => 19, 'name' => 'Assign Roles', 'slug' => 'assign_roles', 'group' => 'Users', 'description' => 'Assign roles to users'],
            ['id' => 20, 'name' => 'Change Own Password', 'slug' => 'change_own_password', 'group' => 'System', 'description' => 'Change own password'],
            ['id' => 21, 'name' => 'Manage Permissions', 'slug' => 'manage_permissions', 'group' => 'System', 'description' => 'Manage role and user permissions'],
            ['id' => 22, 'name' => 'View Activity Log', 'slug' => 'view_activity_log', 'group' => 'System', 'description' => 'View system activity log'],
            ['id' => 23, 'name' => 'Convert to Loan', 'slug' => 'convert_to_loan', 'group' => 'Loans', 'description' => 'Convert quotation to loan task'],
            ['id' => 24, 'name' => 'View Loans', 'slug' => 'view_loans', 'group' => 'Loans', 'description' => 'View loan task list'],
            ['id' => 25, 'name' => 'View All Loans', 'slug' => 'view_all_loans', 'group' => 'Loans', 'description' => 'View all loans across users/branches'],
            ['id' => 26, 'name' => 'Create Loan', 'slug' => 'create_loan', 'group' => 'Loans', 'description' => 'Create loan tasks directly'],
            ['id' => 27, 'name' => 'Edit Loan', 'slug' => 'edit_loan', 'group' => 'Loans', 'description' => 'Edit loan details'],
            ['id' => 28, 'name' => 'Delete Loan', 'slug' => 'delete_loan', 'group' => 'Loans', 'description' => 'Delete loan tasks'],
            ['id' => 29, 'name' => 'Manage Loan Documents', 'slug' => 'manage_loan_documents', 'group' => 'Loans', 'description' => 'Mark documents as received/pending, add/remove documents'],
            ['id' => 30, 'name' => 'Manage Loan Stages', 'slug' => 'manage_loan_stages', 'group' => 'Loans', 'description' => 'Update stage status and assignments'],
            ['id' => 31, 'name' => 'Skip Loan Stages', 'slug' => 'skip_loan_stages', 'group' => 'Loans', 'description' => 'Skip stages in loan workflow'],
            ['id' => 32, 'name' => 'Add Remarks', 'slug' => 'add_remarks', 'group' => 'Loans', 'description' => 'Add remarks to loan stages'],
            ['id' => 33, 'name' => 'Manage Workflow Config', 'slug' => 'manage_workflow_config', 'group' => 'Loans', 'description' => 'Configure banks, products, branches, stage workflows'],
            ['id' => 34, 'name' => 'Upload Loan Documents', 'slug' => 'upload_loan_documents', 'group' => 'Loans', 'description' => 'Upload document files to loan documents'],
            ['id' => 35, 'name' => 'Download Loan Documents', 'slug' => 'download_loan_documents', 'group' => 'Loans', 'description' => 'Download/preview uploaded document files'],
            ['id' => 36, 'name' => 'Delete Loan Files', 'slug' => 'delete_loan_files', 'group' => 'Loans', 'description' => 'Remove uploaded document files'],
        ];

        foreach ($permissions as $p) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $p['slug']],
                array_merge($p, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    private function seedLocations(): void
    {
        $locations = [
            ['id' => 1, 'parent_id' => null, 'name' => 'Gujarat', 'type' => 'state', 'code' => 'GJ', 'is_active' => true],
            ['id' => 2, 'parent_id' => 1, 'name' => 'Rajkot', 'type' => 'city', 'code' => 'RJT', 'is_active' => true],
            ['id' => 3, 'parent_id' => 1, 'name' => 'Jamnagar', 'type' => 'city', 'code' => 'JAM', 'is_active' => true],
            ['id' => 4, 'parent_id' => 1, 'name' => 'Ahmedabad', 'type' => 'city', 'code' => 'AMD', 'is_active' => true],
        ];

        foreach ($locations as $loc) {
            DB::table('locations')->updateOrInsert(
                ['id' => $loc['id']],
                array_merge($loc, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    private function seedBranches(): void
    {
        DB::table('branches')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Rajkot Main Office',
                'code' => 'RJK-MAIN',
                'address' => 'OFFICE NO 911, R K PRIME, CIRCLE, next to SILVER HEIGHT, Nehru Nagar Co operative Society, Nana Mava, Rajkot, Gujarat 360004',
                'city' => 'Rajkot',
                'phone' => '+91 99747 89089',
                'is_active' => true,
                'manager_id' => 2,
                'location_id' => 2,
                'created_at' => '2026-04-06 15:24:26',
                'updated_at' => '2026-04-07 23:22:31',
            ]
        );
    }

    private function seedBanks(): void
    {
        $banks = [
            ['id' => 1, 'name' => 'HDFC Bank', 'code' => 'HDFC', 'is_active' => true, 'default_employee_id' => null],
            ['id' => 2, 'name' => 'ICICI Bank', 'code' => 'ICICI', 'is_active' => true, 'default_employee_id' => null],
            ['id' => 3, 'name' => 'Axis Bank', 'code' => 'AXIS', 'is_active' => true, 'default_employee_id' => null],
            ['id' => 4, 'name' => 'Kotak Mahindra Bank', 'code' => 'KOTAK', 'is_active' => true, 'default_employee_id' => null],
        ];

        foreach ($banks as $bank) {
            DB::table('banks')->updateOrInsert(
                ['id' => $bank['id']],
                array_merge($bank, ['created_at' => '2026-04-06 15:24:26', 'updated_at' => '2026-04-06 15:24:26'])
            );
        }
    }

    private function seedStages(): void
    {
        $stages = [
            ['id' => 1, 'stage_key' => 'inquiry', 'is_enabled' => true, 'stage_name_en' => 'Loan Inquiry', 'stage_name_gu' => 'લોન પૂછપરછ', 'sequence_order' => 1, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Initial customer and loan details entry', 'description_gu' => null, 'default_role' => '["branch_manager","loan_advisor"]', 'sub_actions' => null],
            ['id' => 2, 'stage_key' => 'document_selection', 'is_enabled' => true, 'stage_name_en' => 'Document Selection', 'stage_name_gu' => 'દસ્તાવેજ પસંદગી', 'sequence_order' => 2, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Select required documents for the loan', 'description_gu' => null, 'default_role' => '["branch_manager","loan_advisor"]', 'sub_actions' => null],
            ['id' => 3, 'stage_key' => 'document_collection', 'is_enabled' => true, 'stage_name_en' => 'Document Collection', 'stage_name_gu' => "\u{0AA6}\u{0AB8}\u{0ACD}\u{0AA4}\u{0ABE}\u{0AB5}\u{0AC7}\u{0A9C} \u{0AB8}\u{0A82}\u{0A97}\u{0ACD}\u{0AB0}\u{0AB9}", 'sequence_order' => 3, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Collect and verify all required documents', 'description_gu' => null, 'default_role' => '["branch_manager","loan_advisor"]', 'sub_actions' => null],
            ['id' => 4, 'stage_key' => 'parallel_processing', 'is_enabled' => true, 'stage_name_en' => 'Parallel Processing', 'stage_name_gu' => "\u{0AB8}\u{0AAE}\u{0ABE}\u{0A82}\u{0AA4}\u{0AB0} \u{0AAA}\u{0ACD}\u{0AB0}\u{0A95}\u{0ACD}\u{0AB0}\u{0ABF}\u{0AAF}\u{0ABE}", 'sequence_order' => 4, 'is_parallel' => true, 'parent_stage_key' => null, 'stage_type' => 'parallel', 'description_en' => 'Four parallel tracks processed simultaneously', 'description_gu' => null, 'default_role' => null, 'sub_actions' => null],
            ['id' => 5, 'stage_key' => 'app_number', 'is_enabled' => true, 'stage_name_en' => 'Application Number', 'stage_name_gu' => "\u{0A85}\u{0AB0}\u{0A9C}\u{0AC0} \u{0AA8}\u{0A82}\u{0AAC}\u{0AB0}", 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Enter bank application number', 'description_gu' => null, 'default_role' => '["branch_manager","loan_advisor"]', 'sub_actions' => null],
            ['id' => 6, 'stage_key' => 'bsm_osv', 'is_enabled' => true, 'stage_name_en' => 'BSM/OSV Approval', 'stage_name_gu' => "BSM/OSV \u{0AAE}\u{0A82}\u{0A9C}\u{0AC2}\u{0AB0}\u{0AC0}", 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Bank site and office verification', 'description_gu' => null, 'default_role' => '["bank_employee"]', 'sub_actions' => null],
            ['id' => 7, 'stage_key' => 'legal_verification', 'is_enabled' => true, 'stage_name_en' => 'Legal Verification', 'stage_name_gu' => "\u{0A95}\u{0ABE}\u{0AA8}\u{0AC2}\u{0AA8}\u{0AC0} \u{0A9A}\u{0A95}\u{0ABE}\u{0AB8}\u{0AA3}\u{0AC0}", 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Legal document verification', 'description_gu' => null, 'default_role' => '["legal_advisor"]', 'sub_actions' => null],
            ['id' => 8, 'stage_key' => 'technical_valuation', 'is_enabled' => true, 'stage_name_en' => 'Technical Valuation', 'stage_name_gu' => "\u{0A9F}\u{0AC7}\u{0A95}\u{0AA8}\u{0ABF}\u{0A95}\u{0AB2} \u{0AAE}\u{0AC2}\u{0AB2}\u{0ACD}\u{0AAF}\u{0ABE}\u{0A82}\u{0A95}\u{0AA8}", 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Property/asset technical valuation', 'description_gu' => null, 'default_role' => '["branch_manager","office_employee"]', 'sub_actions' => null],
            ['id' => 9, 'stage_key' => 'rate_pf', 'is_enabled' => true, 'stage_name_en' => 'Rate & PF Request', 'stage_name_gu' => "\u{0AA6}\u{0AB0} \u{0A85}\u{0AA8}\u{0AC7} PF \u{0AB5}\u{0ABF}\u{0AA8}\u{0A82}\u{0AA4}\u{0AC0}", 'sequence_order' => 5, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Request interest rate and processing fee from bank', 'description_gu' => null, 'default_role' => null, 'sub_actions' => '[{"key":"bank_rate_details","name":"Bank Rate Details","sequence":1,"roles":["bank_employee"],"type":"form","is_enabled":true},{"key":"processing_charges","name":"Processing & Charges","sequence":2,"roles":["branch_manager","loan_advisor","office_employee"],"type":"form","is_enabled":true}]'],
            ['id' => 10, 'stage_key' => 'sanction', 'is_enabled' => true, 'stage_name_en' => 'Sanction Letter', 'stage_name_gu' => "\u{0AAE}\u{0A82}\u{0A9C}\u{0AC2}\u{0AB0}\u{0AC0} \u{0AAA}\u{0AA4}\u{0ACD}\u{0AB0}", 'sequence_order' => 6, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Bank issues sanction letter', 'description_gu' => null, 'default_role' => null, 'sub_actions' => '[{"key":"send_for_sanction","name":"Send for Sanction Letter","sequence":1,"roles":["branch_manager","loan_advisor"],"type":"action_button","action":"send_for_sanction","transfer_to_role":"bank_employee","is_enabled":true},{"key":"sanction_generated","name":"Sanction Letter Generated","sequence":2,"roles":["bank_employee"],"type":"action_button","action":"sanction_generated","transfer_to_role":"loan_advisor","is_enabled":true},{"key":"sanction_details","name":"Sanction Details","sequence":3,"roles":["branch_manager","loan_advisor"],"type":"form","is_enabled":true}]'],
            ['id' => 11, 'stage_key' => 'docket', 'is_enabled' => true, 'stage_name_en' => 'Docket Login', 'stage_name_gu' => "\u{0AA1}\u{0ACB}\u{0A95}\u{0AC7}\u{0A9F} \u{0AB2}\u{0ACB}\u{0A97}\u{0ABF}\u{0AA8}", 'sequence_order' => 7, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Physical document processing and docket creation', 'description_gu' => null, 'default_role' => '["branch_manager","office_employee"]', 'sub_actions' => null],
            ['id' => 12, 'stage_key' => 'kfs', 'is_enabled' => true, 'stage_name_en' => 'KFS Generation', 'stage_name_gu' => "KFS \u{0A9C}\u{0AA8}\u{0AB0}\u{0AC7}\u{0AB6}\u{0AA8}", 'sequence_order' => 8, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Key Fact Statement generation', 'description_gu' => null, 'default_role' => '["branch_manager","loan_advisor","office_employee"]', 'sub_actions' => null],
            ['id' => 13, 'stage_key' => 'esign', 'is_enabled' => true, 'stage_name_en' => 'E-Sign & eNACH', 'stage_name_gu' => "\u{0A88}-\u{0AB8}\u{0ABE}\u{0A87}\u{0AA8} \u{0A85}\u{0AA8}\u{0AC7} eNACH", 'sequence_order' => 9, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Digital signature and eNACH mandate', 'description_gu' => null, 'default_role' => '["branch_manager","loan_advisor","bank_employee"]', 'sub_actions' => null],
            ['id' => 14, 'stage_key' => 'disbursement', 'is_enabled' => true, 'stage_name_en' => 'Disbursement', 'stage_name_gu' => "\u{0AB5}\u{0ABF}\u{0AA4}\u{0AB0}\u{0AA3}", 'sequence_order' => 10, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'decision', 'description_en' => 'Fund disbursement \u{2014} transfer or cheque with OTC handling', 'description_gu' => null, 'default_role' => '["branch_manager","loan_advisor"]', 'sub_actions' => null],
            ['id' => 15, 'stage_key' => 'cibil_check', 'is_enabled' => false, 'stage_name_en' => 'CIBIL Score Check', 'stage_name_gu' => "CIBIL \u{0AB8}\u{0ACD}\u{0A95}\u{0ACB}\u{0AB0} \u{0AA4}\u{0AAA}\u{0ABE}\u{0AB8}", 'sequence_order' => 5, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Credit score verification (optional)', 'description_gu' => null, 'default_role' => '["bank_employee"]', 'sub_actions' => null],
            ['id' => 16, 'stage_key' => 'property_valuation', 'is_enabled' => true, 'stage_name_en' => 'Property Valuation', 'stage_name_gu' => "\u{0AAE}\u{0ABF}\u{0AB2}\u{0A95}\u{0AA4} \u{0AAE}\u{0AC2}\u{0AB2}\u{0ACD}\u{0AAF}\u{0ABE}\u{0A82}\u{0A95}\u{0AA8}", 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Dedicated property valuation for LAP', 'description_gu' => null, 'default_role' => '["branch_manager","office_employee"]', 'sub_actions' => null],
            ['id' => 17, 'stage_key' => 'vehicle_valuation', 'is_enabled' => false, 'stage_name_en' => 'Vehicle Valuation', 'stage_name_gu' => "\u{0AB5}\u{0ABE}\u{0AB9}\u{0AA8} \u{0AAE}\u{0AC2}\u{0AB2}\u{0ACD}\u{0AAF}\u{0ABE}\u{0A82}\u{0A95}\u{0AA8}", 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Vehicle valuation for car/vehicle loans', 'description_gu' => null, 'default_role' => null, 'sub_actions' => null],
            ['id' => 18, 'stage_key' => 'business_valuation', 'is_enabled' => false, 'stage_name_en' => 'Business Valuation', 'stage_name_gu' => "\u{0AB5}\u{0ACD}\u{0AAF}\u{0AB5}\u{0AB8}\u{0ABE}\u{0AAF} \u{0AAE}\u{0AC2}\u{0AB2}\u{0ACD}\u{0AAF}\u{0ABE}\u{0A82}\u{0A95}\u{0AA8}", 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Business valuation for business loans', 'description_gu' => null, 'default_role' => '["branch_manager","office_employee"]', 'sub_actions' => null],
            ['id' => 19, 'stage_key' => 'title_search', 'is_enabled' => false, 'stage_name_en' => 'Title Search', 'stage_name_gu' => "\u{0A9F}\u{0ABE}\u{0A87}\u{0A9F}\u{0AB2} \u{0AB8}\u{0AB0}\u{0ACD}\u{0A9A}", 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Property title verification for LAP', 'description_gu' => null, 'default_role' => '["legal_advisor"]', 'sub_actions' => null],
            ['id' => 20, 'stage_key' => 'financial_analysis', 'is_enabled' => false, 'stage_name_en' => 'Financial Analysis', 'stage_name_gu' => "\u{0AA8}\u{0ABE}\u{0AA3}\u{0ABE}\u{0A95}\u{0AC0}\u{0AAF} \u{0AB5}\u{0ABF}\u{0AB6}\u{0ACD}\u{0AB2}\u{0AC7}\u{0AB7}\u{0AA3}", 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Financial analysis for business loans', 'description_gu' => null, 'default_role' => '["bank_employee"]', 'sub_actions' => null],
            ['id' => 21, 'stage_key' => 'site_visit', 'is_enabled' => false, 'stage_name_en' => 'Site Visit Report', 'stage_name_gu' => "\u{0AB8}\u{0ABE}\u{0A87}\u{0A9F} \u{0AAE}\u{0AC1}\u{0AB2}\u{0ABE}\u{0A95}\u{0ABE}\u{0AA4} \u{0AB0}\u{0ABF}\u{0AAA}\u{0ACB}\u{0AB0}\u{0ACD}\u{0A9F}", 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Physical site visit for business loans', 'description_gu' => null, 'default_role' => '["branch_manager"]', 'sub_actions' => null],
            ['id' => 22, 'stage_key' => 'approval_committee', 'is_enabled' => false, 'stage_name_en' => 'Approval Committee', 'stage_name_gu' => "\u{0AAE}\u{0A82}\u{0A9C}\u{0AC2}\u{0AB0}\u{0AC0} \u{0AB8}\u{0AAE}\u{0ABF}\u{0AA4}\u{0ABF}", 'sequence_order' => 5, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Committee approval (ICICI specific)', 'description_gu' => null, 'default_role' => '["branch_manager"]', 'sub_actions' => null],
            ['id' => 23, 'stage_key' => 'credit_committee', 'is_enabled' => false, 'stage_name_en' => 'Credit Committee', 'stage_name_gu' => "\u{0A95}\u{0ACD}\u{0AB0}\u{0AC7}\u{0AA1}\u{0ABF}\u{0A9F} \u{0AB8}\u{0AAE}\u{0ABF}\u{0AA4}\u{0ABF}", 'sequence_order' => 5, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Credit committee review (Kotak specific)', 'description_gu' => null, 'default_role' => '["branch_manager"]', 'sub_actions' => null],
            ['id' => 24, 'stage_key' => 'insurance', 'is_enabled' => true, 'stage_name_en' => 'Insurance', 'stage_name_gu' => "\u{0AB5}\u{0AC0}\u{0AAE}\u{0ACB}", 'sequence_order' => 9, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Insurance requirement for vehicle loans', 'description_gu' => null, 'default_role' => '["loan_advisor","office_employee"]', 'sub_actions' => null],
            ['id' => 25, 'stage_key' => 'mortgage', 'is_enabled' => true, 'stage_name_en' => 'Mortgage Registration', 'stage_name_gu' => "\u{0AAE}\u{0ACB}\u{0AB0}\u{0ACD}\u{0A9F}\u{0A97}\u{0AC7}\u{0A9C} \u{0AA8}\u{0ACB}\u{0A82}\u{0AA7}\u{0AA3}\u{0AC0}", 'sequence_order' => 9, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Mortgage registration for LAP', 'description_gu' => null, 'default_role' => '["office_employee","legal_advisor"]', 'sub_actions' => null],
        ];

        foreach ($stages as $stage) {
            DB::table('stages')->updateOrInsert(
                ['stage_key' => $stage['stage_key']],
                array_merge($stage, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    private function seedUsers(): void
    {
        $defaultPassword = Hash::make('password');
        $adminPassword = Hash::make('Admin@123');

        $users = [
            ['id' => 1, 'name' => 'Super Admin', 'email' => 'admin@shf.com', 'role' => 'super_admin', 'is_active' => true, 'created_by' => null, 'phone' => '+91 99747 89089', 'task_role' => null, 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => null],
            ['id' => 2, 'name' => 'Denish Malviya', 'email' => 'denish@shfworld.com', 'role' => 'admin', 'is_active' => true, 'created_by' => null, 'phone' => '+91 99747 89089', 'task_role' => 'branch_manager', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => null],
            ['id' => 3, 'name' => 'JAYDEEP THESHIYA', 'email' => 'jaydeep@shfworld.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 2, 'phone' => '9725248300', 'task_role' => 'loan_advisor', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => null],
            ['id' => 4, 'name' => 'KULDEEP VAISHNAV', 'email' => 'kuldeep@shfworld.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 2, 'phone' => '8866236688', 'task_role' => 'loan_advisor', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => null],
            ['id' => 5, 'name' => 'HARDIK NASIT', 'email' => 'hardik@shfworld.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 2, 'phone' => '9726179351', 'task_role' => 'loan_advisor', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => null],
            ['id' => 6, 'name' => 'RAHUL MARAKANA', 'email' => 'rahul@shfworld.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 2, 'phone' => '9913744162', 'task_role' => 'loan_advisor', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => null],
            ['id' => 7, 'name' => 'DIPAK VIRANI', 'email' => 'dipak@shfworld.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 2, 'phone' => '7600143537', 'task_role' => 'loan_advisor', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => null],
            ['id' => 8, 'name' => 'JAYESH MORI', 'email' => 'jayesh@shfworld.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 2, 'phone' => '8000232586', 'task_role' => 'loan_advisor', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => null],
            ['id' => 9, 'name' => 'CHIRAG DHOLAKIYA', 'email' => 'chirag@shfworld.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 2, 'phone' => '9016348138', 'task_role' => 'loan_advisor', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => null],
            ['id' => 10, 'name' => 'DAXIT MALAVIYA', 'email' => 'daxit@shfworld.som', 'role' => 'staff', 'is_active' => true, 'created_by' => 2, 'phone' => '81600000286', 'task_role' => 'loan_advisor', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => null],
            ['id' => 11, 'name' => 'MILAN DHOLAKIYA', 'email' => 'milan@shfworld.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 2, 'phone' => '8401277654', 'task_role' => 'loan_advisor', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => null],
            ['id' => 12, 'name' => 'NITIN FALDU', 'email' => 'nitin@shfworld.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 2, 'phone' => '968701525', 'task_role' => 'loan_advisor', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => null],
            ['id' => 13, 'name' => 'KRUPALI SHILU', 'email' => 'krupali@shfworld.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 2, 'phone' => '9099089072', 'task_role' => 'loan_advisor', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => null],
            ['id' => 14, 'name' => 'HDFC Employee 1', 'email' => 'hdfc@manager.cop', 'role' => 'staff', 'is_active' => true, 'created_by' => 1, 'phone' => null, 'task_role' => 'bank_employee', 'employee_id' => null, 'default_branch_id' => null, 'task_bank_id' => null],
            ['id' => 15, 'name' => 'HDFC Employee 2', 'email' => 'hdfc@manager2.cop', 'role' => 'staff', 'is_active' => true, 'created_by' => 1, 'phone' => null, 'task_role' => 'bank_employee', 'employee_id' => null, 'default_branch_id' => null, 'task_bank_id' => null],
            ['id' => 16, 'name' => 'Kotak Employee 1', 'email' => 'kotak@manager.cop', 'role' => 'staff', 'is_active' => true, 'created_by' => 1, 'phone' => 'hdfc@manager2.cop', 'task_role' => 'bank_employee', 'employee_id' => null, 'default_branch_id' => null, 'task_bank_id' => 4],
            ['id' => 17, 'name' => 'Kotak Employee 2', 'email' => 'kotak@manager2.cop', 'role' => 'staff', 'is_active' => true, 'created_by' => 1, 'phone' => null, 'task_role' => 'bank_employee', 'employee_id' => null, 'default_branch_id' => null, 'task_bank_id' => 4],
            ['id' => 18, 'name' => 'Axix Employee 1', 'email' => 'axis@manager.cop', 'role' => 'staff', 'is_active' => true, 'created_by' => 1, 'phone' => null, 'task_role' => 'bank_employee', 'employee_id' => null, 'default_branch_id' => null, 'task_bank_id' => 3],
            ['id' => 19, 'name' => 'Axix Employee 2', 'email' => 'axis@manager2.cop', 'role' => 'staff', 'is_active' => true, 'created_by' => 1, 'phone' => null, 'task_role' => 'bank_employee', 'employee_id' => null, 'default_branch_id' => null, 'task_bank_id' => 3],
            ['id' => 20, 'name' => 'ICICI Employee 1', 'email' => 'icici@manager.cop', 'role' => 'staff', 'is_active' => true, 'created_by' => 1, 'phone' => null, 'task_role' => 'bank_employee', 'employee_id' => null, 'default_branch_id' => null, 'task_bank_id' => 2],
            ['id' => 21, 'name' => 'ICICI Employee 2', 'email' => 'icici@manager2.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 1, 'phone' => null, 'task_role' => 'bank_employee', 'employee_id' => null, 'default_branch_id' => null, 'task_bank_id' => 2],
            ['id' => 22, 'name' => 'Office Employee1', 'email' => 'vipul@office.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 1, 'phone' => '+91 99747 89089', 'task_role' => 'office_employee', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => 1],
            ['id' => 23, 'name' => 'Office Employee2', 'email' => 'officeemployee2@shfworld.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 1, 'phone' => null, 'task_role' => 'office_employee', 'employee_id' => null, 'default_branch_id' => 1, 'task_bank_id' => 1],
            ['id' => 24, 'name' => 'Legal Advisor 1', 'email' => 'legal@shfworld.com', 'role' => 'staff', 'is_active' => true, 'created_by' => 1, 'phone' => null, 'task_role' => 'legal_advisor', 'employee_id' => null, 'default_branch_id' => null, 'task_bank_id' => 4],
        ];

        foreach ($users as $user) {
            DB::table('users')->updateOrInsert(
                ['email' => $user['email']],
                array_merge($user, [
                    'password' => (isset($user['id']) && $user['id'] == 1) ? $adminPassword : $defaultPassword,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        // Update bank default_employee_id after users exist
        DB::table('banks')->where('id', 1)->update(['default_employee_id' => 15]);
        DB::table('banks')->where('id', 2)->update(['default_employee_id' => 21]);
        DB::table('banks')->where('id', 3)->update(['default_employee_id' => 18]);
        DB::table('banks')->where('id', 4)->update(['default_employee_id' => 17]);
    }

    private function seedRolePermissions(): void
    {
        // Clear existing to avoid duplicates
        DB::table('role_permissions')->truncate();

        // super_admin gets ALL permissions
        $allPermissionIds = DB::table('permissions')->pluck('id');
        foreach ($allPermissionIds as $permId) {
            DB::table('role_permissions')->insert([
                'role' => 'super_admin',
                'permission_id' => $permId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // admin permissions (all except manage_permissions #21 and delete_users #18)
        $adminPermIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 19, 20, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36];
        foreach ($adminPermIds as $permId) {
            DB::table('role_permissions')->insert([
                'role' => 'admin',
                'permission_id' => $permId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // staff permissions (limited set)
        $staffPermIds = [9, 10, 11, 14, 20, 23, 24, 26, 27, 29, 30, 31, 32, 34, 35, 36];
        foreach ($staffPermIds as $permId) {
            DB::table('role_permissions')->insert([
                'role' => 'staff',
                'permission_id' => $permId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedUserBranches(): void
    {
        // All office staff (IDs 1-13) + office employees (22, 23) assigned to branch 1
        $userIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 22, 23];
        foreach ($userIds as $userId) {
            DB::table('user_branches')->updateOrInsert(
                ['user_id' => $userId, 'branch_id' => 1],
                ['created_at' => null, 'updated_at' => null]
            );
        }
    }

    private function seedBankEmployees(): void
    {
        $employees = [
            ['bank_id' => 1, 'user_id' => 14, 'is_default' => false],
            ['bank_id' => 1, 'user_id' => 15, 'is_default' => true],
            ['bank_id' => 2, 'user_id' => 20, 'is_default' => false],
            ['bank_id' => 2, 'user_id' => 21, 'is_default' => true],
            ['bank_id' => 3, 'user_id' => 18, 'is_default' => true],
            ['bank_id' => 3, 'user_id' => 19, 'is_default' => false],
            ['bank_id' => 4, 'user_id' => 16, 'is_default' => false],
            ['bank_id' => 4, 'user_id' => 17, 'is_default' => true],
            ['bank_id' => 3, 'user_id' => 22, 'is_default' => false],
            ['bank_id' => 1, 'user_id' => 22, 'is_default' => false],
            ['bank_id' => 3, 'user_id' => 23, 'is_default' => false],
            ['bank_id' => 1, 'user_id' => 23, 'is_default' => false],
            ['bank_id' => 4, 'user_id' => 24, 'is_default' => false],
            ['bank_id' => 3, 'user_id' => 24, 'is_default' => false],
        ];

        foreach ($employees as $emp) {
            DB::table('bank_employees')->updateOrInsert(
                ['bank_id' => $emp['bank_id'], 'user_id' => $emp['user_id']],
                array_merge($emp, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    private function seedBankLocations(): void
    {
        $bankLocations = [
            ['bank_id' => 3, 'location_id' => 2],
            ['bank_id' => 1, 'location_id' => 2],
            ['bank_id' => 2, 'location_id' => 3],
            ['bank_id' => 2, 'location_id' => 2],
            ['bank_id' => 4, 'location_id' => 3],
            ['bank_id' => 4, 'location_id' => 2],
            ['bank_id' => 3, 'location_id' => 3],
        ];

        foreach ($bankLocations as $bl) {
            DB::table('bank_location')->updateOrInsert(
                ['bank_id' => $bl['bank_id'], 'location_id' => $bl['location_id']],
                array_merge($bl, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    private function seedLocationUsers(): void
    {
        $locationUsers = [
            ['location_id' => 1, 'user_id' => 17],
            ['location_id' => 2, 'user_id' => 17],
            ['location_id' => 2, 'user_id' => 24],
            ['location_id' => 3, 'user_id' => 24],
            ['location_id' => 2, 'user_id' => 23],
            ['location_id' => 2, 'user_id' => 22],
        ];

        foreach ($locationUsers as $lu) {
            DB::table('location_user')->updateOrInsert(
                ['location_id' => $lu['location_id'], 'user_id' => $lu['user_id']],
                array_merge($lu, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    private function seedBankCharges(): void
    {
        $charges = [
            ['bank_name' => 'Axis Bank', 'pf' => 0.50, 'admin' => 0, 'stamp_notary' => 2500, 'registration_fee' => 5900, 'advocate' => 1000, 'tc' => 4500, 'extra1_name' => null, 'extra1_amt' => 0, 'extra2_name' => null, 'extra2_amt' => 0],
            ['bank_name' => 'HDFC Bank', 'pf' => 0.60, 'admin' => 0, 'stamp_notary' => 1500, 'registration_fee' => 5900, 'advocate' => 2500, 'tc' => 0, 'extra1_name' => null, 'extra1_amt' => 0, 'extra2_name' => null, 'extra2_amt' => 0],
            ['bank_name' => 'ICICI Bank', 'pf' => 0.60, 'admin' => 5000, 'stamp_notary' => 1500, 'registration_fee' => 5900, 'advocate' => 2000, 'tc' => 2500, 'extra1_name' => null, 'extra1_amt' => 0, 'extra2_name' => null, 'extra2_amt' => 0],
            ['bank_name' => 'Kotak Mahindra Bank', 'pf' => 0.50, 'admin' => 0, 'stamp_notary' => 2500, 'registration_fee' => 5900, 'advocate' => 2500, 'tc' => 0, 'extra1_name' => null, 'extra1_amt' => 0, 'extra2_name' => null, 'extra2_amt' => 0],
        ];

        foreach ($charges as $charge) {
            DB::table('bank_charges')->updateOrInsert(
                ['bank_name' => $charge['bank_name']],
                array_merge($charge, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    private function seedAppConfig(): void
    {
        $configJson = json_encode([
            'companyName' => 'Shreenathji Home Finance',
            'companyAddress' => 'OFFICE NO 911, R K PRIME, CIRCLE, next to SILVER HEIGHT, Nehru Nagar Co operative Society, Nana Mava, Rajkot, Gujarat 360004',
            'companyPhone' => '+91 99747 89089',
            'companyEmail' => 'info@shf.com',
            'banks' => ['HDFC Bank', 'ICICI Bank', 'Axis Bank', 'Kotak Mahindra Bank'],
            'iomCharges' => [
                'thresholdAmount' => 10000000,
                'fixedCharge' => 7000,
                'percentageAbove' => 0.35,
            ],
            'tenures' => [5, 10, 15, 20],
            'documents_en' => [
                'proprietor' => ['Passport Size Photographs Both', 'PAN Card Both', 'Aadhaar Card Both', 'GST Registration Certificate', 'Udyam Registration Certificate', 'ITR (Last 3 years)', 'Bank Statement (Last 12 months)', 'Current Loan Statement ( if applicable )', 'Property File Xerox'],
                'partnership_llp' => ['PAN Card of Firm', 'Partnership Deed', 'GST Registration Certificate', 'ITR With Audit of Firm (Last 3 years)', 'Firm Current A/c Bank Statement (Last 12 months)', 'Current Loan Statement ( if applicable )', 'Passport Size Photographs of All Partners', 'PAN Card of All Partners', 'Aadhaar Card of All Partners', 'ITR of Partners (Last 3 years)', 'Bank Statement of Partners (Last 12 months)'],
                'pvt_ltd' => ['PAN Card of Company', 'Memorandum of Association (MOA)', 'Articles of Association (AOA)', 'GST Registration Certificate', 'ITR With Audit Report of Company (Last 3 years)', 'Current Loan Statement ( if applicable )', 'Company Current A/C Statement (Last 12 months)', 'Passport Size Photographs of All Director', 'PAN Card of All Directors', 'Aadhaar Card of All Directors', 'ITR of Directors (Last 3 years)', 'Bank Statement of Directors (Last 12 months)'],
                'salaried' => ['Passport Size Photographs Both', 'PAN Card Both', 'Aadhaar Card Both', 'Salary Slips (Last 6 months)', 'ITR (Last 2 years)', 'Form 16 (Last 2 years)', 'Bank Statement (Last 6 months)', 'Property Documents (if applicable)'],
            ],
            'documents_gu' => [
                'proprietor' => ['પાસપોર્ટ સાઇઝ ફોટોગ્રાફ બંનેના', 'પાન કાર્ડ બંનેનું', 'આધાર કાર્ડ બંનેનું', 'GST સર્ટીફીકેટ', 'ઉદ્યમ સર્ટીફીકેટ', 'ITR (છેલ્લા ૩ વર્ષ)', 'બેંક સ્ટેટમેન્ટ ( છેલ્લા ૧૨ મહિનાનું )', 'ચાલુ લોન સ્ટેટમેન્ટ ( જો ચાલુ હોય તો )', 'પ્રોપર્ટી ફાઇલ ઝેરોક્ષ'],
                'partnership_llp' => ['ફર્મનું PAN ક���ર્ડ', 'ભાગીદારી દસ્તાવેજ', 'GST સર્ટીફીકેટ', 'ફર્મનું ITR ઓડિટ રિપોર્ટ સાથે (છેલ્લા ૩ વર્ષ)', 'ફર્મનું કરંટ A/c નું બેંક સ્ટેટમેન્ટ (છેલ્લા ૧૨ મહિના)', 'ચાલુ લોન સ્ટેટમેન્ટ ( જો ચાલુ હોય તો )', 'બધા ભાગીદારોના પાસપોર્ટ સાઇઝ ફોટોગ્રાફ', 'બધા ભાગીદારોનું PAN કાર્ડ', '��ધા ભાગીદારોનું આધાર કાર્ડ', 'ભાગીદારોનું ITR (છેલ્લા ૩ વર્ષ)', 'બધા ભાગીદારોના બેંક સ્ટેટમેન્ટ (છેલ્લા ૧૨ મહિના)'],
                'pvt_ltd' => ['કંપનીનું PAN કાર્ડ', 'મેમોરેન્ડમ ઓફ એસોસિએશન (MOA)', 'આર્ટિકલ્સ ઓફ એસોસિએશન (AOA)', 'GST સર્ટીફીકેટ', 'કંપનીનું ITR ઓડિટ રિપોર્ટ સાથે ( છેલ્લા ૩ વર્ષ )', 'ચાલુ લોન સ્ટેટમેન્ટ ( જો ચાલુ હોય તો )', 'કંપનીનું કરંટ A/c નું બેંક સ્ટેટમેન્ટ ( છેલ્લા ૧૨ મહિના )', 'બધા ડિરેક્ટરોના પાસપોર્ટ સાઇઝ ફોટોગ્ર���ફ', 'બધા ડિરેક્ટરોનું PAN કાર્ડ', 'બધા ડિરેક્ટરોનું આધાર કાર્ડ', 'ડિરેક���ટરોનું ITR ( છેલ્લા ૩ વર્ષ )', 'બધા ડિરેક્ટરોના બેંક સ્ટેટમેન્ટ ( છેલ્લા ૧૨ મહિના )'],
                'salaried' => ['પાસપોર્ટ સાઇઝ ફોટોગ્રા��� બંનેના', 'PAN કાર્ડ બંનેનું', 'આધાર કાર્ડ બંનેનું', 'સેલેરી સ્લિપ (છેલ્લા ૬ મહિના)', 'ITR (છેલ્લા ૨ વર્ષ)', 'ફોર્મ ૧૬ (છેલ્���ા ૨ વર્ષ)', 'બેંક સ્ટેટમેન્ટ (છ���લ્લા ૬ મહિના)', 'મિલકતના દસ્તાવેજો (જો લાગુ હોય)'],
            ],
            'gstPercent' => 18,
            'ourServices' => 'Home Loan, Mortgage Loan, Commercial Loan, Industrial Loan,Land Loan, Over Draft(OD)',
        ], JSON_UNESCAPED_UNICODE);

        DB::table('app_config')->updateOrInsert(
            ['config_key' => 'main'],
            [
                'config_json' => $configJson,
                'created_at' => '2026-02-27 15:59:58',
                'updated_at' => now(),
            ]
        );
    }

    private function seedAppSettings(): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['setting_key' => 'additional_notes'],
            [
                'setting_value' => "અમે આપેલ લોન અમાઉન્ટ વધઘટ થઈ શકે છે (બેંકની વિઝીટ ઉપર આધાર રાખે છે)\nROI તમારા સીબીલ મુજબ વધઘટ થઈ શકે છે\nપાર્ટ પેમેન્ટ તથા લોન વેલી પુરી કરવાનો કોઈ ચાર્જ નથી\nલોગીન ફી ઓનલાઈન ભરવાની થશે જે ટોટલ પ્રોસેસિંગ ફી માંથી કપાત થઈ જશે\nલોગીન ફી 3000 /-  નોન રીફન્ડેબલ રહેશે   \nAxis Bank ma account open kar va nu rese \nHealth Insurance & property insurance leva no rese",
                'updated_at' => '2026-04-06 06:47:53',
            ]
        );
    }

    private function seedQuotations(): void
    {
        $quotations = [
            ['id' => 21, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => 'ASHOKBHAI CHHANGOMALBHAI LALWANI', 'customer_type' => 'proprietor', 'loan_amount' => 4200000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "રેટ ઓફ ઇન્ટરેસ્ટ કસ્ટમર ન સિબિલ સ્કોર પર આધારિત છે\nઅહીં દર્શાવેલ રેટ ઓફ ઇન્ટરેસ્ટ પર જ લોન એપ્રૂવલ થાય એવું જરૂરી નથી", 'prepared_by_name' => 'KULDEEP PATEL', 'prepared_by_mobile' => '8866236688', 'selected_tenures' => '[20]', 'created_at' => '2026-02-28 15:05:53', 'updated_at' => '2026-02-28 15:05:53'],
            ['id' => 22, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => 'AMIPARA MAHESHBHAI UKABHAI', 'customer_type' => 'proprietor', 'loan_amount' => 5820000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "રેટ ઓફ ઇન્ટરેસ્ટ કસ્ટમર ન સિબિલ સ્કોર પર આધારિત છે\nઅહીં દર્શાવેલ રેટ ઓફ ઇન્ટરેસ્ટ પર જ લોન એપ્રૂવલ થાય એવું જરૂરી નથી", 'prepared_by_name' => 'HARDIK NASIT', 'prepared_by_mobile' => '+91 9726179351', 'selected_tenures' => '[15,20]', 'created_at' => '2026-03-03 12:51:14', 'updated_at' => '2026-03-03 12:51:14'],
            ['id' => 23, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => 'AMIPARA MAHESHBHAI UKABHAI', 'customer_type' => 'proprietor', 'loan_amount' => 5820000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "રેટ ઓફ ઇન્ટરેસ્ટ કસ્ટમર ન સિબિલ સ્કોર પર આધારિત છે\nઅહીં દર્શાવેલ રેટ ઓફ ઇન્ટરેસ્ટ પર જ લોન એપ્રૂવલ થાય એવું જરૂરી નથી", 'prepared_by_name' => 'HARDIK NASIT', 'prepared_by_mobile' => '+91 9726179351', 'selected_tenures' => '[15,20]', 'created_at' => '2026-03-03 12:53:29', 'updated_at' => '2026-03-03 12:53:29'],
            ['id' => 24, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => 'AMIPARA MAHESHBHAI UKABHAI', 'customer_type' => 'proprietor', 'loan_amount' => 5820000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "રેટ ઓફ ઇન��ટરેસ્ટ કસ્ટમર ન સિબિલ સ્કોર પર આધારિત છે\nઅહીં દર્શાવેલ રેટ ઓફ ઇન્ટરેસ્ટ પર જ લોન એપ્રૂવલ થાય એવું જરૂરી નથી", 'prepared_by_name' => 'HARDIK NASIT', 'prepared_by_mobile' => '+91 9726179351', 'selected_tenures' => '[15,20]', 'created_at' => '2026-03-03 12:54:43', 'updated_at' => '2026-03-03 12:54:43'],
            ['id' => 25, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => 'Brijesh Kumar unjiya', 'customer_type' => 'proprietor', 'loan_amount' => 2500000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "રેટ ઓફ ઇન્ટરેસ્ટ કસ્ટમર ન સિબિલ સ્કોર પર આધારિત છે\nઅહીં દર્શાવેલ રેટ ઓફ ઇન્ટરેસ્ટ પર જ લોન એપ્રૂવલ થાય એવું જરૂરી નથી. \nપ્રોપર્ટી ઇન્સ્યુરન્સ ફરજિયાત  છે", 'prepared_by_name' => 'Nitin faldu', 'prepared_by_mobile' => '+91 9687501525', 'selected_tenures' => '[15]', 'created_at' => '2026-03-05 10:51:54', 'updated_at' => '2026-03-05 10:51:54'],
            ['id' => 26, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => 'Brijesh Kumar unjiya', 'customer_type' => 'proprietor', 'loan_amount' => 2500000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "રેટ ઓફ ઇન્ટરેસ્ટ કસ્ટમર ન સિબિલ સ્કોર પર આધારિત છે\nઅહીં દર્શાવેલ રેટ ઓફ ઇન્ટરેસ્ટ પર જ લોન એપ્રૂવલ થાય એવું જરૂરી નથી. \nપ્રોપર્ટી ઇન્સ્યુરન્સ ફરજિયાત  છે", 'prepared_by_name' => 'Nitin faldu', 'prepared_by_mobile' => '+91 9687501525', 'selected_tenures' => '[15]', 'created_at' => '2026-03-05 10:56:14', 'updated_at' => '2026-03-05 10:56:14'],
            ['id' => 27, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => 'PRASHANT KISHORBHAI JADAV', 'customer_type' => 'proprietor', 'loan_amount' => 5000000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "રેટ ઓફ ઇન્ટરેસ્ટ કસ્ટમર ન સિબિલ સ્કોર પર આધારિત છે\nઅહીં દર્શાવેલ રેટ ઓફ ઇન્ટરેસ્ટ પર જ લોન એપ્રૂવલ થાય એવું જરૂરી નથી. \n ઇન્સ્યુરન્સ ફરજિયાત  છે \nAXIS BANK LTD  MA ACOOUNT OPEN  ફરજિયાત  છે", 'prepared_by_name' => 'CHIRAG DHOLAKIYA', 'prepared_by_mobile' => '09016348138', 'selected_tenures' => '[10,15]', 'created_at' => '2026-03-05 13:01:36', 'updated_at' => '2026-03-05 13:01:36'],
            ['id' => 29, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => 'SUBHASBHAI SORATHIYA', 'customer_type' => 'proprietor', 'loan_amount' => 2600000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "રેટ ઓફ ઇન્ટરેસ્ટ કસ્��મર ન સિબિલ સ્કોર પર આધારિત છે\nઅહીં દર્શાવેલ રેટ ઓફ ઇન્ટરેસ્ટ પર જ લોન એપ્રૂવલ થાય એવું જરૂરી નથી. \nICICI માં એકાઉન્ટ ખોલવું અને ઇન્શ્યોરન્સ લેવું જરૂરી નથી.\nખર્ચો જેટલો ઓછો થઈ શકે એટલો કરી આપીશું.", 'prepared_by_name' => 'Admin', 'prepared_by_mobile' => '+91 9974277500', 'selected_tenures' => '[15,20]', 'created_at' => '2026-03-09 11:33:47', 'updated_at' => '2026-03-09 11:33:47'],
            ['id' => 30, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => 'MEGHANI CHANDUBHAI UKABHAI', 'customer_type' => 'proprietor', 'loan_amount' => 7000000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "રેટ ઓફ ઇન્ટરેસ્ટ કસ્ટમર ન સિબિલ સ્કોર પર આધારિત છે\nઅહીં દર્શાવેલ રેટ ઓફ ઇન્ટરેસ્ટ પર જ લોન એપ્રૂવલ થાય એવું જરૂરી નથી. \nખર્ચો જેટલો ઓછો થઈ શકે એટલો કરી આપીશું.", 'prepared_by_name' => 'RUSHI SOJITRA  &  KULDEEP PATEL', 'prepared_by_mobile' => '8460244864  &  8866236688', 'selected_tenures' => '[15]', 'created_at' => '2026-03-09 12:45:57', 'updated_at' => '2026-03-09 12:45:57'],
            ['id' => 33, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => 'HIRAPARA KEYUR', 'customer_type' => 'proprietor', 'loan_amount' => 1900000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "રેટ ઓફ ઇન્ટરેસ્ટ કસ્ટમર ન સિબિલ સ્કોર પર આધારિત છે\nઅહીં દર્શાવેલ રેટ ઓફ ઇન્ટરેસ્ટ પર જ લોન એપ્રૂવલ થાય એવું જરૂરી નથી. \nખર્ચો જેટલો ઓછો થઈ શકે એટલો કરી આપીશું.", 'prepared_by_name' => 'Denish Malviya', 'prepared_by_mobile' => '+91 99747 89089', 'selected_tenures' => '[20]', 'created_at' => '2026-03-12 10:03:32', 'updated_at' => '2026-03-12 10:03:32'],
            ['id' => 35, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => '...', 'customer_type' => 'proprietor', 'loan_amount' => 2000000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "રેટ ઓફ ઇન્ટરેસ્ટ કસ્���મર ન સિબિલ સ્કોર પર આધારિત છે\nઅહીં દર્શાવેલ રેટ ઓફ ઇન્ટરેસ્ટ પર જ લોન એપ્રૂવલ થાય એવું જરૂરી નથી. \nLOGIN FEE 3000 /- NON REFUNDABLE", 'prepared_by_name' => 'KULDEEP PATEL', 'prepared_by_mobile' => '8866236688', 'selected_tenures' => '[20]', 'created_at' => '2026-03-15 06:06:32', 'updated_at' => '2026-03-15 06:06:32'],
            ['id' => 36, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => 'SHREE GANESH JEWELLERS', 'customer_type' => 'partnership_llp', 'loan_amount' => 5000000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "રેટ ઓફ ઇન્ટરેસ્ટ કસ્ટમર ન સિબિલ સ��કોર પર આધારિત છે\nઅહીં દર્શાવેલ રેટ ઓફ ઇન્ટરેસ્ટ પર જ લોન એપ્રૂવલ થાય એવું જરૂરી નથી. \nLOGIN FEE 5000 /- NON REFUNDABLE", 'prepared_by_name' => 'CHIRAG DHOLAKIYA', 'prepared_by_mobile' => '90163 48138', 'selected_tenures' => '[10,15]', 'created_at' => '2026-03-19 13:51:11', 'updated_at' => '2026-03-19 13:51:11'],
            ['id' => 37, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => 'TANSUKH DHIRAJLAL VEKARIYA', 'customer_type' => 'proprietor', 'loan_amount' => 3500000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "રેટ ઓફ ઇન્ટરેસ્ટ કસ્ટમર ન સિબિલ સ્કોર પર આધારિત છે\nઅહીં દર્શાવેલ રેટ ઓફ ઇન્ટરેસ્ટ પર જ લોન એપ્રૂવલ થાય એવું જરૂરી નથી. \nLOGIN FEE 5000 /- NON REFUNDABLE", 'prepared_by_name' => 'Denish Malviya', 'prepared_by_mobile' => '+91 99747 89089', 'selected_tenures' => '[20]', 'created_at' => '2026-03-26 13:04:28', 'updated_at' => '2026-03-26 13:04:28'],
            ['id' => 38, 'loan_id' => null, 'location_id' => 2, 'user_id' => 9, 'customer_name' => 'SAJANBEN MUKESHBHAI AAL', 'customer_type' => 'proprietor', 'loan_amount' => 4000000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "અમે આપેલ લોન અમાઉન્ટ વધઘટ થઈ શકે છે (બેંકની વિઝીટ ઉપર આધાર રાખે છે)\nROI તમારા સીબીલ મુજબ વધઘટ થઈ શકે છે\nપાર્ટ પેમેન્ટ તથા લોન વેલી પુરી કરવાનો કોઈ ચાર્જ નથી\nપ્રોપર્ટી ફાઈલ ની આખી કોપી (ઉતરોતર સાથે જે કોપી પરત આવશે નહીં)\nલોગીન ફી ઓનલાઈન ભરવાની થશે જે ટોટલ પ્રોસેસિંગ ફી માંથી કપાત થઈ જશે\nલોગીન ફી 5000 /-  નોન રીફન્ડેબલ રહેશે", 'prepared_by_name' => 'CHIRAG DHOLAKIYA', 'prepared_by_mobile' => '9016348138', 'selected_tenures' => '[10,15]', 'created_at' => '2026-03-28 07:44:32', 'updated_at' => '2026-03-28 07:44:32'],
            ['id' => 40, 'loan_id' => null, 'location_id' => 2, 'user_id' => 2, 'customer_name' => 'HARDIK VEKARIYA', 'customer_type' => 'proprietor', 'loan_amount' => 3000000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "અમે આપેલ લોન અમાઉન્ટ વધઘટ થઈ શકે છે (બેંકની વિઝીટ ઉપર આધાર રાખે છે)\nROI તમારા સીબીલ મુજબ વધઘટ થઈ શકે છે\nપાર્ટ પેમેન્ટ તથા લોન વેલી પુરી કરવાનો કોઈ ચાર્જ નથી\nલોગીન ફી ઓનલાઈન ભરવાની થશે જે ટોટલ પ્રોસેસિંગ ફી માંથી કપાત થઈ જશે\nલોગીન ફી 5000 /-  નોન રીફન્ડેબલ રહેશે", 'prepared_by_name' => 'Denish Malviya', 'prepared_by_mobile' => '+91 99747 89089', 'selected_tenures' => '[10,15]', 'created_at' => '2026-03-30 14:10:33', 'updated_at' => '2026-03-30 14:10:33'],
            ['id' => 41, 'loan_id' => null, 'location_id' => 2, 'user_id' => 9, 'customer_name' => 'PRASHANTBHAI JADAV', 'customer_type' => 'proprietor', 'loan_amount' => 2000000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "અમે આપેલ લોન અમાઉન્ટ વધઘટ થ�� શકે છે (બેંકની વિઝીટ ઉપર આધાર રાખે છે)\nROI તમારા સીબીલ મુજબ વધઘટ થઈ શકે છે\nપાર્ટ પેમેન્ટ તથા લોન વેલી પુરી કરવાનો કોઈ ચાર્જ નથી\nલોગીન ફી ઓનલાઈન ભરવાની થશે જે ટોટલ પ્રોસેસિંગ ફી માંથી કપાત થઈ જશે\nલોગીન ફી 5000 /-  નોન રીફન્ડેબલ રહેશે", 'prepared_by_name' => 'CHIRAG DHOLAKIYA', 'prepared_by_mobile' => '9016348138', 'selected_tenures' => '[10,15]', 'created_at' => '2026-04-04 12:06:48', 'updated_at' => '2026-04-04 12:06:48'],
            ['id' => 42, 'loan_id' => null, 'location_id' => 2, 'user_id' => 9, 'customer_name' => 'PRASHANTBHAI JADAV', 'customer_type' => 'proprietor', 'loan_amount' => 2000000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "અમે આપેલ લોન અમાઉન્ટ વધઘટ થઈ શકે છે (બેંકની વિઝીટ ઉપર આધાર રાખે છે)\nROI તમારા સીબીલ મુજબ વધઘટ થઈ શકે છે\nપાર્ટ પેમેન્ટ તથા લોન વેલી પુરી કરવાનો કોઈ ચાર્જ નથી\nલોગીન ફી ઓનલાઈન ભરવાની થશે જે ટોટલ પ્રોસેસિંગ ફી માંથી કપાત થઈ જશે\nલોગીન ફી 5000 /-  નોન રીફન્ડેબલ રહેશે", 'prepared_by_name' => 'CHIRAG DHOLAKIYA', 'prepared_by_mobile' => '9016348138', 'selected_tenures' => '[10,15]', 'created_at' => '2026-04-04 12:08:33', 'updated_at' => '2026-04-07 05:57:48'],
            ['id' => 43, 'loan_id' => null, 'location_id' => 2, 'user_id' => 9, 'customer_name' => 'NARIGARA SURESHBHAI R', 'customer_type' => 'proprietor', 'loan_amount' => 1600000, 'pdf_filename' => null, 'pdf_path' => null, 'additional_notes' => "અમે આપેલ લોન અમાઉન્ટ વધઘટ થઈ શકે છે (બેંકની વિઝીટ ઉપર આધાર રાખે છે)\nROI તમારા સીબીલ મુજબ વધઘટ થઈ શકે છે\nપાર્ટ પેમેન્��� તથા લોન વેલી પુરી કરવાનો કોઈ ચાર્જ નથી\nલોગીન ફી ઓનલાઈન ભરવાની થશે જે ટોટલ પ્રોસેસિંગ ફી માંથી કપાત થઈ જશે\nલોગીન ફી 3000 /-  નોન રીફન્ડેબલ રહેશે   \nAxis Bank ma account open kar va nu rese \nHealth Insurance & property insurance leva no rese", 'prepared_by_name' => 'CHIRAG DHOLAKIYA', 'prepared_by_mobile' => '9016348138', 'selected_tenures' => '[15,20]', 'created_at' => '2026-04-06 06:47:53', 'updated_at' => '2026-04-07 05:29:57'],
        ];

        foreach ($quotations as $q) {
            DB::table('quotations')->updateOrInsert(
                ['id' => $q['id']],
                $q
            );
        }
    }

    private function seedQuotationBanks(): void
    {
        $banks = [
            ['id' => 65, 'quotation_id' => 21, 'bank_name' => 'ICICI Bank', 'roi_min' => 7.55, 'roi_max' => 7.65, 'pf_charge' => 0.25, 'admin_charge' => 5000, 'stamp_notary' => 2000, 'registration_fee' => 6000, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 2500, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 38290],
            ['id' => 66, 'quotation_id' => 22, 'bank_name' => 'ICICI Bank', 'roi_min' => 7.40, 'roi_max' => 7.75, 'pf_charge' => 0.25, 'admin_charge' => 5000, 'stamp_notary' => 2000, 'registration_fee' => 6000, 'advocate_fees' => 3000, 'iom_charge' => 5900, 'tc_report' => 2500, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 42469],
            ['id' => 67, 'quotation_id' => 22, 'bank_name' => 'HDFC Bank', 'roi_min' => 7.40, 'roi_max' => 7.50, 'pf_charge' => 0.50, 'admin_charge' => 2360, 'stamp_notary' => 3000, 'registration_fee' => 6000, 'advocate_fees' => 3000, 'iom_charge' => 5900, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 55023],
            ['id' => 68, 'quotation_id' => 23, 'bank_name' => 'ICICI Bank', 'roi_min' => 7.40, 'roi_max' => 7.75, 'pf_charge' => 0.25, 'admin_charge' => 5000, 'stamp_notary' => 2000, 'registration_fee' => 6000, 'advocate_fees' => 3000, 'iom_charge' => 5900, 'tc_report' => 2500, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 42469],
            ['id' => 69, 'quotation_id' => 23, 'bank_name' => 'HDFC Bank', 'roi_min' => 7.35, 'roi_max' => 7.50, 'pf_charge' => 0.20, 'admin_charge' => 0, 'stamp_notary' => 3000, 'registration_fee' => 6000, 'advocate_fees' => 3000, 'iom_charge' => 5900, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 31635],
            ['id' => 70, 'quotation_id' => 24, 'bank_name' => 'ICICI Bank', 'roi_min' => 7.40, 'roi_max' => 7.75, 'pf_charge' => 0.25, 'admin_charge' => 5000, 'stamp_notary' => 2000, 'registration_fee' => 6000, 'advocate_fees' => 3000, 'iom_charge' => 5900, 'tc_report' => 2500, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 42469],
            ['id' => 71, 'quotation_id' => 24, 'bank_name' => 'HDFC Bank', 'roi_min' => 7.35, 'roi_max' => 7.50, 'pf_charge' => 0.20, 'admin_charge' => 0, 'stamp_notary' => 3000, 'registration_fee' => 6000, 'advocate_fees' => 3000, 'iom_charge' => 5900, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 31635],
            ['id' => 72, 'quotation_id' => 25, 'bank_name' => 'HDFC Bank', 'roi_min' => 9.00, 'roi_max' => 9.15, 'pf_charge' => 0.60, 'admin_charge' => 0, 'stamp_notary' => 2500, 'registration_fee' => 5900, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 35600],
            ['id' => 73, 'quotation_id' => 25, 'bank_name' => 'Kotak Mahindra Bank', 'roi_min' => 9.00, 'roi_max' => 9.20, 'pf_charge' => 0.50, 'admin_charge' => 11000, 'stamp_notary' => 3000, 'registration_fee' => 5900, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 0, 'extra1_name' => 'Login fees', 'extra1_amount' => 5900, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 52030],
            ['id' => 74, 'quotation_id' => 25, 'bank_name' => 'ICICI Bank', 'roi_min' => 9.05, 'roi_max' => 9.30, 'pf_charge' => 0.60, 'admin_charge' => 5000, 'stamp_notary' => 600, 'registration_fee' => 5900, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 2000, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 41600],
            ['id' => 75, 'quotation_id' => 25, 'bank_name' => 'Axis Bank', 'roi_min' => 9.00, 'roi_max' => 9.25, 'pf_charge' => 0.60, 'admin_charge' => 0, 'stamp_notary' => 2000, 'registration_fee' => 5900, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 35100],
            ['id' => 76, 'quotation_id' => 26, 'bank_name' => 'HDFC Bank', 'roi_min' => 9.00, 'roi_max' => 9.15, 'pf_charge' => 0.60, 'admin_charge' => 0, 'stamp_notary' => 2500, 'registration_fee' => 5900, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 35600],
            ['id' => 77, 'quotation_id' => 26, 'bank_name' => 'Kotak Mahindra Bank', 'roi_min' => 9.00, 'roi_max' => 9.20, 'pf_charge' => 0.50, 'admin_charge' => 0, 'stamp_notary' => 3000, 'registration_fee' => 5900, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 0, 'extra1_name' => 'Login fees', 'extra1_amount' => 5900, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 39050],
            ['id' => 78, 'quotation_id' => 26, 'bank_name' => 'ICICI Bank', 'roi_min' => 9.05, 'roi_max' => 9.30, 'pf_charge' => 0.60, 'admin_charge' => 5000, 'stamp_notary' => 600, 'registration_fee' => 5900, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 2000, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 41600],
            ['id' => 79, 'quotation_id' => 26, 'bank_name' => 'Axis Bank', 'roi_min' => 9.00, 'roi_max' => 9.25, 'pf_charge' => 0.60, 'admin_charge' => 0, 'stamp_notary' => 2000, 'registration_fee' => 5900, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 35100],
            ['id' => 80, 'quotation_id' => 27, 'bank_name' => 'Axis Bank', 'roi_min' => 9.00, 'roi_max' => 9.15, 'pf_charge' => 0.65, 'admin_charge' => 0, 'stamp_notary' => 4500, 'registration_fee' => 5900, 'advocate_fees' => 4600, 'iom_charge' => 5500, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 58850],
            ['id' => 82, 'quotation_id' => 29, 'bank_name' => 'ICICI Bank', 'roi_min' => 8.90, 'roi_max' => 9.40, 'pf_charge' => 0.60, 'admin_charge' => 5000, 'stamp_notary' => 600, 'registration_fee' => 7000, 'advocate_fees' => 2500, 'iom_charge' => 4000, 'tc_report' => 2000, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 40408],
            ['id' => 83, 'quotation_id' => 30, 'bank_name' => 'ICICI Bank', 'roi_min' => 9.00, 'roi_max' => 9.15, 'pf_charge' => 0.75, 'admin_charge' => 5000, 'stamp_notary' => 1000, 'registration_fee' => 6000, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 84350],
            ['id' => 86, 'quotation_id' => 33, 'bank_name' => 'HDFC Bank', 'roi_min' => 7.20, 'roi_max' => 7.50, 'pf_charge' => 0.25, 'admin_charge' => 0, 'stamp_notary' => 3000, 'registration_fee' => 5000, 'advocate_fees' => 3000, 'iom_charge' => 7000, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 23605],
            ['id' => 88, 'quotation_id' => 35, 'bank_name' => 'ICICI Bank', 'roi_min' => 7.55, 'roi_max' => 7.75, 'pf_charge' => 0.15, 'admin_charge' => 5000, 'stamp_notary' => 1500, 'registration_fee' => 6000, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 2500, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 28940],
            ['id' => 89, 'quotation_id' => 36, 'bank_name' => 'ICICI Bank', 'roi_min' => 9.05, 'roi_max' => 9.25, 'pf_charge' => 0.65, 'admin_charge' => 5900, 'stamp_notary' => 4500, 'registration_fee' => 6000, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 2500, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 67812],
            ['id' => 90, 'quotation_id' => 36, 'bank_name' => 'Kotak Mahindra Bank', 'roi_min' => 8.50, 'roi_max' => 9.55, 'pf_charge' => 0.70, 'admin_charge' => 0, 'stamp_notary' => 4500, 'registration_fee' => 5900, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 61200],
            ['id' => 91, 'quotation_id' => 37, 'bank_name' => 'HDFC Bank', 'roi_min' => 7.20, 'roi_max' => 7.40, 'pf_charge' => 0.15, 'admin_charge' => 0, 'stamp_notary' => 2500, 'registration_fee' => 5000, 'advocate_fees' => 3000, 'iom_charge' => 7000, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 23695],
            ['id' => 92, 'quotation_id' => 38, 'bank_name' => 'ICICI Bank', 'roi_min' => 9.40, 'roi_max' => 9.45, 'pf_charge' => 0.60, 'admin_charge' => 5900, 'stamp_notary' => 4500, 'registration_fee' => 6000, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 2500, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 57782],
            ['id' => 97, 'quotation_id' => 40, 'bank_name' => 'HDFC Bank', 'roi_min' => 8.90, 'roi_max' => 9.00, 'pf_charge' => 0.60, 'admin_charge' => 0, 'stamp_notary' => 1500, 'registration_fee' => 5900, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 38140],
            ['id' => 98, 'quotation_id' => 40, 'bank_name' => 'ICICI Bank', 'roi_min' => 9.05, 'roi_max' => 9.15, 'pf_charge' => 0.60, 'admin_charge' => 5000, 'stamp_notary' => 1500, 'registration_fee' => 5900, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 2500, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 46540],
            ['id' => 99, 'quotation_id' => 40, 'bank_name' => 'Axis Bank', 'roi_min' => 9.15, 'roi_max' => 9.25, 'pf_charge' => 0.65, 'admin_charge' => 0, 'stamp_notary' => 2500, 'registration_fee' => 5900, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 40910],
            ['id' => 100, 'quotation_id' => 40, 'bank_name' => 'Kotak Mahindra Bank', 'roi_min' => 8.90, 'roi_max' => 9.00, 'pf_charge' => 0.50, 'admin_charge' => 0, 'stamp_notary' => 2500, 'registration_fee' => 5900, 'advocate_fees' => 2500, 'iom_charge' => 7000, 'tc_report' => 0, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 35600],
            ['id' => 101, 'quotation_id' => 41, 'bank_name' => 'ICICI Bank', 'roi_min' => 9.00, 'roi_max' => 9.05, 'pf_charge' => 0.60, 'admin_charge' => 5900, 'stamp_notary' => 1500, 'registration_fee' => 5900, 'advocate_fees' => 2000, 'iom_charge' => 7000, 'tc_report' => 2500, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 40022],
            ['id' => 102, 'quotation_id' => 42, 'bank_name' => 'ICICI Bank', 'roi_min' => 9.00, 'roi_max' => 9.05, 'pf_charge' => 0.60, 'admin_charge' => 5000, 'stamp_notary' => 1500, 'registration_fee' => 5900, 'advocate_fees' => 2000, 'iom_charge' => 7000, 'tc_report' => 2500, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 38960],
            ['id' => 103, 'quotation_id' => 43, 'bank_name' => 'Axis Bank', 'roi_min' => 7.90, 'roi_max' => 8.10, 'pf_charge' => 0.50, 'admin_charge' => 0, 'stamp_notary' => 2500, 'registration_fee' => 5900, 'advocate_fees' => 1000, 'iom_charge' => 7000, 'tc_report' => 4500, 'extra1_name' => null, 'extra1_amount' => 0, 'extra2_name' => null, 'extra2_amount' => 0, 'total_charges' => 30340],
        ];

        foreach ($banks as $bank) {
            DB::table('quotation_banks')->updateOrInsert(
                ['id' => $bank['id']],
                array_merge($bank, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    private function seedQuotationEmi(): void
    {
        $emis = [
            ['id' => 207, 'quotation_bank_id' => 65, 'tenure_years' => 20, 'monthly_emi' => 33963, 'total_interest' => 3951225, 'total_payment' => 8151225],
            ['id' => 208, 'quotation_bank_id' => 66, 'tenure_years' => 15, 'monthly_emi' => 53622, 'total_interest' => 3831946, 'total_payment' => 9651946],
            ['id' => 209, 'quotation_bank_id' => 66, 'tenure_years' => 20, 'monthly_emi' => 46530, 'total_interest' => 5347271, 'total_payment' => 11167271],
            ['id' => 210, 'quotation_bank_id' => 67, 'tenure_years' => 15, 'monthly_emi' => 53622, 'total_interest' => 3831946, 'total_payment' => 9651946],
            ['id' => 211, 'quotation_bank_id' => 67, 'tenure_years' => 20, 'monthly_emi' => 46530, 'total_interest' => 5347271, 'total_payment' => 11167271],
            ['id' => 212, 'quotation_bank_id' => 68, 'tenure_years' => 15, 'monthly_emi' => 53622, 'total_interest' => 3831946, 'total_payment' => 9651946],
            ['id' => 213, 'quotation_bank_id' => 68, 'tenure_years' => 20, 'monthly_emi' => 46530, 'total_interest' => 5347271, 'total_payment' => 11167271],
            ['id' => 214, 'quotation_bank_id' => 69, 'tenure_years' => 15, 'monthly_emi' => 53457, 'total_interest' => 3802300, 'total_payment' => 9622300],
            ['id' => 215, 'quotation_bank_id' => 69, 'tenure_years' => 20, 'monthly_emi' => 46353, 'total_interest' => 5304760, 'total_payment' => 11124760],
            ['id' => 216, 'quotation_bank_id' => 70, 'tenure_years' => 15, 'monthly_emi' => 53622, 'total_interest' => 3831946, 'total_payment' => 9651946],
            ['id' => 217, 'quotation_bank_id' => 70, 'tenure_years' => 20, 'monthly_emi' => 46530, 'total_interest' => 5347271, 'total_payment' => 11167271],
            ['id' => 218, 'quotation_bank_id' => 71, 'tenure_years' => 15, 'monthly_emi' => 53457, 'total_interest' => 3802300, 'total_payment' => 9622300],
            ['id' => 219, 'quotation_bank_id' => 71, 'tenure_years' => 20, 'monthly_emi' => 46353, 'total_interest' => 5304760, 'total_payment' => 11124760],
            ['id' => 220, 'quotation_bank_id' => 72, 'tenure_years' => 15, 'monthly_emi' => 25357, 'total_interest' => 2064200, 'total_payment' => 4564200],
            ['id' => 221, 'quotation_bank_id' => 73, 'tenure_years' => 15, 'monthly_emi' => 25357, 'total_interest' => 2064200, 'total_payment' => 4564200],
            ['id' => 222, 'quotation_bank_id' => 74, 'tenure_years' => 15, 'monthly_emi' => 25431, 'total_interest' => 2077594, 'total_payment' => 4577594],
            ['id' => 223, 'quotation_bank_id' => 75, 'tenure_years' => 15, 'monthly_emi' => 25357, 'total_interest' => 2064200, 'total_payment' => 4564200],
            ['id' => 224, 'quotation_bank_id' => 76, 'tenure_years' => 15, 'monthly_emi' => 25357, 'total_interest' => 2064200, 'total_payment' => 4564200],
            ['id' => 225, 'quotation_bank_id' => 77, 'tenure_years' => 15, 'monthly_emi' => 25357, 'total_interest' => 2064200, 'total_payment' => 4564200],
            ['id' => 226, 'quotation_bank_id' => 78, 'tenure_years' => 15, 'monthly_emi' => 25431, 'total_interest' => 2077594, 'total_payment' => 4577594],
            ['id' => 227, 'quotation_bank_id' => 79, 'tenure_years' => 15, 'monthly_emi' => 25357, 'total_interest' => 2064200, 'total_payment' => 4564200],
            ['id' => 228, 'quotation_bank_id' => 80, 'tenure_years' => 10, 'monthly_emi' => 63338, 'total_interest' => 2600546, 'total_payment' => 7600546],
            ['id' => 229, 'quotation_bank_id' => 80, 'tenure_years' => 15, 'monthly_emi' => 50713, 'total_interest' => 4128399, 'total_payment' => 9128399],
            ['id' => 232, 'quotation_bank_id' => 82, 'tenure_years' => 15, 'monthly_emi' => 26216, 'total_interest' => 2118968, 'total_payment' => 4718968],
            ['id' => 233, 'quotation_bank_id' => 82, 'tenure_years' => 20, 'monthly_emi' => 23226, 'total_interest' => 2974221, 'total_payment' => 5574221],
            ['id' => 234, 'quotation_bank_id' => 83, 'tenure_years' => 15, 'monthly_emi' => 70999, 'total_interest' => 5779759, 'total_payment' => 12779759],
            ['id' => 237, 'quotation_bank_id' => 86, 'tenure_years' => 20, 'monthly_emi' => 14960, 'total_interest' => 1690313, 'total_payment' => 3590313],
            ['id' => 240, 'quotation_bank_id' => 88, 'tenure_years' => 20, 'monthly_emi' => 16173, 'total_interest' => 1881536, 'total_payment' => 3881536],
            ['id' => 241, 'quotation_bank_id' => 89, 'tenure_years' => 10, 'monthly_emi' => 63473, 'total_interest' => 2616792, 'total_payment' => 7616792],
            ['id' => 242, 'quotation_bank_id' => 89, 'tenure_years' => 15, 'monthly_emi' => 50862, 'total_interest' => 4155188, 'total_payment' => 9155188],
            ['id' => 243, 'quotation_bank_id' => 90, 'tenure_years' => 10, 'monthly_emi' => 61993, 'total_interest' => 2439141, 'total_payment' => 7439141],
            ['id' => 244, 'quotation_bank_id' => 90, 'tenure_years' => 15, 'monthly_emi' => 49237, 'total_interest' => 3862656, 'total_payment' => 8862656],
            ['id' => 245, 'quotation_bank_id' => 91, 'tenure_years' => 20, 'monthly_emi' => 27557, 'total_interest' => 3113734, 'total_payment' => 6613734],
            ['id' => 246, 'quotation_bank_id' => 92, 'tenure_years' => 10, 'monthly_emi' => 51540, 'total_interest' => 2184833, 'total_payment' => 6184833],
            ['id' => 247, 'quotation_bank_id' => 92, 'tenure_years' => 15, 'monthly_emi' => 41528, 'total_interest' => 3475033, 'total_payment' => 7475033],
            ['id' => 256, 'quotation_bank_id' => 97, 'tenure_years' => 10, 'monthly_emi' => 37841, 'total_interest' => 1540868, 'total_payment' => 4540868],
            ['id' => 257, 'quotation_bank_id' => 97, 'tenure_years' => 15, 'monthly_emi' => 30250, 'total_interest' => 2444963, 'total_payment' => 5444963],
            ['id' => 258, 'quotation_bank_id' => 98, 'tenure_years' => 10, 'monthly_emi' => 38084, 'total_interest' => 1570075, 'total_payment' => 4570075],
            ['id' => 259, 'quotation_bank_id' => 98, 'tenure_years' => 15, 'monthly_emi' => 30517, 'total_interest' => 2493113, 'total_payment' => 5493113],
            ['id' => 260, 'quotation_bank_id' => 99, 'tenure_years' => 10, 'monthly_emi' => 38247, 'total_interest' => 1589604, 'total_payment' => 4589604],
            ['id' => 261, 'quotation_bank_id' => 99, 'tenure_years' => 15, 'monthly_emi' => 30696, 'total_interest' => 2525329, 'total_payment' => 5525329],
            ['id' => 262, 'quotation_bank_id' => 100, 'tenure_years' => 10, 'monthly_emi' => 37841, 'total_interest' => 1540868, 'total_payment' => 4540868],
            ['id' => 263, 'quotation_bank_id' => 100, 'tenure_years' => 15, 'monthly_emi' => 30250, 'total_interest' => 2444963, 'total_payment' => 5444963],
            ['id' => 264, 'quotation_bank_id' => 101, 'tenure_years' => 10, 'monthly_emi' => 25335, 'total_interest' => 1040219, 'total_payment' => 3040219],
            ['id' => 265, 'quotation_bank_id' => 101, 'tenure_years' => 15, 'monthly_emi' => 20285, 'total_interest' => 1651360, 'total_payment' => 3651360],
            ['id' => 266, 'quotation_bank_id' => 102, 'tenure_years' => 10, 'monthly_emi' => 25335, 'total_interest' => 1040219, 'total_payment' => 3040219],
            ['id' => 267, 'quotation_bank_id' => 102, 'tenure_years' => 15, 'monthly_emi' => 20285, 'total_interest' => 1651360, 'total_payment' => 3651360],
            ['id' => 268, 'quotation_bank_id' => 103, 'tenure_years' => 15, 'monthly_emi' => 15198, 'total_interest' => 1135678, 'total_payment' => 2735678],
            ['id' => 269, 'quotation_bank_id' => 103, 'tenure_years' => 20, 'monthly_emi' => 13284, 'total_interest' => 1588073, 'total_payment' => 3188073],
        ];

        foreach ($emis as $emi) {
            DB::table('quotation_emi')->updateOrInsert(
                ['id' => $emi['id']],
                array_merge($emi, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    private function seedQuotationDocuments(): void
    {
        $docs = [
            // Quotation 21 (10 docs)
            [21, 'PAN Card of Proprietor', 'માલિકનું PAN કાર્ડ'],
            [21, 'Aadhaar Card of Proprietor', 'માલિકનું આધાર કાર્ડ'],
            [21, 'Business Address Proof', 'વ્યવસાય સરનામાનો પુરાવો'],
            [21, 'Bank Statement (12 months)', 'બેંક સ્ટેટમેન્ટ (૧૨ મહિના)'],
            [21, 'ITR (Last 3 years)', 'ITR (છેલ્લા ૩ વર્ષ)'],
            [21, 'GST Registration Certificate', 'GST નોંધણી પ્રમાણપત્ર'],
            [21, 'Shop & Establishment Certificate', 'દુકાન અને સ્થાપના પ્રમાણપત્ર'],
            [21, 'Property Documents (if applicable)', 'મિલકતના દસ્તાવેજો (જો લાગુ હોય)'],
            [21, 'Udyam Registration Certificate', 'ઉદ્યમ નોંધણી પ્રમાણપત્ર'],
            [21, 'Passport Size Photographs', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
            // Quotation 22 (10 docs)
            [22, 'PAN Card of Proprietor', 'માલિકનું PAN કાર્ડ'],
            [22, 'Aadhaar Card of Proprietor', 'માલિકનું આધાર કાર્ડ'],
            [22, 'Business Address Proof', 'વ્યવસાય સરનામાનો પુરાવો'],
            [22, 'Bank Statement (12 months)', 'બેંક સ્ટેટમેન્ટ (૧૨ મહિના)'],
            [22, 'ITR (Last 3 years)', 'ITR (છેલ્લા ૩ વર્ષ)'],
            [22, 'GST Registration Certificate', 'GST નોંધણી પ્રમાણપત્ર'],
            [22, 'Shop & Establishment Certificate', 'દુકાન અને સ્થાપના પ્રમાણપત્ર'],
            [22, 'Property Documents (if applicable)', 'મિલકતના દસ્તાવેજો (જો લાગુ હોય)'],
            [22, 'Udyam Registration Certificate', 'ઉદ્યમ નોંધણી પ્રમાણપત્ર'],
            [22, 'Passport Size Photographs', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
            // Quotation 23 (10 docs)
            [23, 'PAN Card of Proprietor', 'માલિકનું PAN કાર્ડ'],
            [23, 'Aadhaar Card of Proprietor', 'માલિકનું આધાર કાર્ડ'],
            [23, 'Business Address Proof', 'વ્યવસાય સરનામાનો પુરાવો'],
            [23, 'Bank Statement (12 months)', 'બેંક સ્ટેટમેન્ટ (૧૨ મહિના)'],
            [23, 'ITR (Last 3 years)', 'ITR (છેલ્લા ૩ વર્ષ)'],
            [23, 'GST Registration Certificate', 'GST નોંધણી પ્રમાણપત્ર'],
            [23, 'Shop & Establishment Certificate', 'દુકાન અને સ્થાપના પ્રમાણપત્ર'],
            [23, 'Property Documents (if applicable)', 'મિલકતના દસ્તાવેજો (જો લાગુ હોય)'],
            [23, 'Udyam Registration Certificate', 'ઉદ્યમ નોંધણી પ્રમાણપત્ર'],
            [23, 'Passport Size Photographs', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
            // Quotation 24 (8 docs)
            [24, 'PAN Card of Proprietor', 'માલિકનું PAN કાર્ડ'],
            [24, 'Aadhaar Card of Proprietor', 'માલિકનું આધાર કાર્ડ'],
            [24, 'Bank Statement (12 months)', 'બેંક સ્ટેટમેન્ટ (૧૨ મહિના)'],
            [24, 'ITR (Last 3 years)', 'ITR (છેલ્લા ૩ વર્ષ)'],
            [24, 'GST Registration Certificate', 'GST નોંધણી પ્રમાણપત્ર'],
            [24, 'Property Documents (if applicable)', 'મિલકતના દસ્તાવેજો (જો લાગુ હોય)'],
            [24, 'Udyam Registration Certificate', 'ઉદ્યમ નોંધણી પ્રમાણપત્ર'],
            [24, 'Passport Size Photographs', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
            // Quotation 25 (9 docs)
            [25, 'PAN Card of Proprietor', 'માલિકનું PAN કાર્ડ'],
            [25, 'Aadhaar Card of Proprietor', 'માલિકનું આધાર કાર્ડ'],
            [25, 'Business Address Proof', 'વ્યવસાય સરનામાનો પુરાવો'],
            [25, 'Bank Statement (12 months)', 'બેંક સ્ટેટમેન્ટ (૧૨ મહિના)'],
            [25, 'ITR (Last 3 years)', 'ITR (છેલ્લા ૩ વર્ષ)'],
            [25, 'Shop & Establishment Certificate', 'દુકાન અને સ્થાપના પ્રમાણપત્ર'],
            [25, 'Property Documents (if applicable)', 'મિલકતના દસ્તાવેજો (જો લાગુ હોય)'],
            [25, 'Udyam Registration Certificate', 'ઉદ્યમ નોંધણી પ્રમાણપત્ર'],
            [25, 'Passport Size Photographs', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
            // Quotation 26 (9 docs)
            [26, 'PAN Card of Proprietor', 'માલિકનું PAN કાર્ડ'],
            [26, 'Aadhaar Card of Proprietor', 'માલિકનું આધાર કાર્ડ'],
            [26, 'Business Address Proof', 'વ્યવસાય સરનામાનો પુરાવો'],
            [26, 'Bank Statement (12 months)', 'બેંક સ્ટેટમેન્ટ (૧૨ મહિના)'],
            [26, 'ITR (Last 3 years)', 'ITR (છેલ્લા ૩ વર્ષ)'],
            [26, 'Shop & Establishment Certificate', 'દુકાન અને સ્થાપના પ્રમાણપત્ર'],
            [26, 'Property Documents (if applicable)', 'મિલકતના દસ્તાવેજો (જો લાગુ હોય)'],
            [26, 'Udyam Registration Certificate', 'ઉદ્યમ નોંધણી પ્રમાણપત્ર'],
            [26, 'Passport Size Photographs', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
            // Quotation 27 (7 docs)
            [27, 'PAN Card of Proprietor', 'માલિકનું PAN કાર્ડ'],
            [27, 'Aadhaar Card of Proprietor', 'માલિકનું આધાર કાર્ડ'],
            [27, 'Business Address Proof', 'વ્યવસાય સરનામાનો પુરાવો'],
            [27, 'Bank Statement (12 months)', 'બેંક સ્ટેટમેન્ટ (૧૨ મહિના)'],
            [27, 'Property Documents (if applicable)', 'મિલકતના દસ્તાવેજો (જો લાગુ હોય)'],
            [27, 'Udyam Registration Certificate', 'ઉદ્યમ નોંધણી પ્રમાણપત્ર'],
            [27, 'Passport Size Photographs', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
            // Quotation 29 (10 docs)
            [29, 'PAN Card of Proprietor', 'માલિકનું PAN કાર્ડ'],
            [29, 'Aadhaar Card of Proprietor', 'માલિકનું આધાર કાર્ડ'],
            [29, 'Business Address Proof', 'વ્યવસાય સરનામાનો પુરાવો'],
            [29, 'Bank Statement (12 months)', 'બેંક સ્ટેટમેન્ટ (૧૨ મહિના)'],
            [29, 'ITR (Last 3 years)', 'ITR (છેલ્લા ૩ વર્ષ)'],
            [29, 'GST Registration Certificate', 'GST નોંધણી પ્રમાણપત્ર'],
            [29, 'Shop & Establishment Certificate', 'દુકાન અને સ્થાપના પ્રમાણપત્ર'],
            [29, 'Property Documents (if applicable)', 'મિલકતના દસ્તાવેજો (જો લાગુ હોય)'],
            [29, 'Udyam Registration Certificate', 'ઉદ્યમ નોંધણી પ્રમાણપત્ર'],
            [29, 'Passport Size Photographs', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
            // Quotation 30 (10 docs)
            [30, 'PAN Card of Proprietor', 'માલિકનું PAN કાર્ડ'],
            [30, 'Aadhaar Card of Proprietor', 'માલિકનું આધાર કાર્ડ'],
            [30, 'Business Address Proof', 'વ્યવસાય સરનામાનો પુરાવો'],
            [30, 'Bank Statement (12 months)', 'બેંક સ્ટેટમેન્ટ (૧૨ મહિના)'],
            [30, 'ITR (Last 3 years)', 'ITR (છેલ્લા ૩ વર્ષ)'],
            [30, 'GST Registration Certificate', 'GST નોંધણી પ્રમાણપત્ર'],
            [30, 'Shop & Establishment Certificate', 'દુકાન અને સ્થાપના પ્રમાણપત્ર'],
            [30, 'Property Documents (if applicable)', 'મિલકતના દસ્તાવેજો (જો લાગુ હોય)'],
            [30, 'Udyam Registration Certificate', 'ઉદ્યમ નોંધણી પ્રમાણપત્ર'],
            [30, 'Passport Size Photographs', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
            // Quotation 33 (9 docs - uses updated document names)
            [33, 'PAN Card Both', 'પાન કાર્ડ બંનેનું'],
            [33, 'Aadhaar Card Both', 'આધાર કાર્ડ બંનેનું'],
            [33, 'Bank Statement (12 months)', 'બેંક સ્ટેટમેન્ટ ( છેલ્લા ૧૨ મહિનાનું )'],
            [33, 'ITR (Last 3 years)', 'ITR (છેલ્લા ૩ વર્ષ)'],
            [33, 'GST Registration Certificate', 'GST સર્ટીફીકેટ'],
            [33, 'Property File Xerox', 'પ્રોપર્ટી ફાઇલ ઝેરોક્ષ'],
            [33, 'Udyam Registration Certificate', 'ઉદ્યમ સર્ટીફીકેટ'],
            [33, 'Current Loan Statement ( if applicable )', 'ચાલુ લોન સ્ટેટમેન્ટ ( જો ચાલુ હોય તો )'],
            [33, 'Passport Size Photographs', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ બંનેના'],
            // Quotation 35 (8 docs)
            [35, 'PAN Card Both', 'પાન કાર્ડ બંનેનું'],
            [35, 'Aadhaar Card Both', 'આધાર કાર્ડ બંનેનું'],
            [35, 'Bank Statement (12 months)', 'બેંક સ્ટેટમેન્ટ ( છેલ્લા ૧૨ મહિનાનું )'],
            [35, 'ITR (Last 3 years)', 'ITR (છેલ્લા ૩ વર્ષ)'],
            [35, 'Property File Xerox', 'પ્રોપર્ટી ફાઇલ ઝેરોક્ષ'],
            [35, 'Udyam Registration Certificate', 'ઉદ્યમ સર્ટીફીકેટ'],
            [35, 'Current Loan Statement ( if applicable )', 'ચાલુ લોન સ્ટેટમેન્ટ ( જો ચાલુ હોય તો )'],
            [35, 'Passport Size Photographs', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ બંનેના'],
            // Quotation 36 - Partnership (12 docs)
            [36, 'Passport Size Photographs of All Partners', 'બધા ભાગીદારોના પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
            [36, 'PAN Card of Firm', 'ફર્મનું PAN કાર્ડ'],
            [36, 'PAN Card of All Partners', 'બધા ભાગીદારોનું PAN કાર્ડ'],
            [36, 'Aadhaar Card of All Partners', 'બધા ભાગીદારોનું આધાર કાર્ડ'],
            [36, 'Bank Statement (12 months)', 'બેંક સ્ટેટમેન્ટ (૧૨ મહિના)'],
            [36, 'ITR of Firm (Last 3 years)', 'ફર્મનું ITR (છેલ્લા ૩ વર્ષ)'],
            [36, 'ITR of Partners (Last 3 years)', 'ભાગીદારોનું ITR (છેલ્લા ૩ વર્ષ)'],
            [36, 'GST Registration Certificate', 'GST નોંધણી પ્રમાણપત્ર'],
            [36, 'Board Resolution / Authority Letter', 'Authority Letter / બોર્ડ ઠરાવ / અધિકૃત પત્ર'],
            [36, 'Partnership Deed', 'ભાગીદારી દસ્તાવેજ'],
            [36, 'Firm Current A/c Bank Statement  (12 months)', 'પેઢીનું કરંટ A/c નું બેંક સ્ટેટમેન્ટ (૧૨ મહિના)'],
            [36, 'Passport Size Photographs of All Partners', 'બધા ભાગીદારોના પાસપોર્ટ સાઇઝ ફોટોગ્રાફ'],
            // Quotation 37 (9 docs)
            [37, 'Passport Size Photographs', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ બંનેના'],
            [37, 'PAN Card Both', 'પાન કાર્ડ બંનેનું'],
            [37, 'Aadhaar Card Both', 'આધાર કાર્ડ બંનેનું'],
            [37, 'ITR (Last 3 years)', 'ITR (છેલ્લા ૩ વર્ષ)'],
            [37, 'GST Registration Certificate', 'GST સર્ટીફીકેટ'],
            [37, 'Udyam Registration Certificate', 'ઉદ્યમ સર્ટીફીકેટ'],
            [37, 'Current Loan Statement ( if applicable )', 'ચાલુ લોન સ્ટેટમેન્ટ ( જો ચાલુ હોય તો )'],
            [37, 'Bank Statement (12 months)', 'બેંક સ્ટેટમેન્ટ ( છેલ્લા ૧૨ મહિનાનું )'],
            [37, 'Property File Xerox', 'પ્રોપર્ટી ફાઇલ ઝેરોક્ષ'],
            // Quotation 38 (7 docs)
            [38, 'Passport Size Photographs Both', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ બંનેના'],
            [38, 'PAN Card Both', 'પાન કાર્ડ બંનેના'],
            [38, 'Aadhaar Card Both', 'આધાર કાર્ડ બંનેના'],
            [38, 'GST Registration Certificate', 'GST સર્ટીફીકેટ'],
            [38, 'Udyam Registration Certificate', 'ઉદ્યમ સર્ટીફીકેટ'],
            [38, 'Bank Statement (Last 12 months)', 'બેંક સ્ટેટમેન્ટ ( છેલ્લા ૧૨ મહિનાનું )'],
            [38, 'Current Loan Statement ( if applicable )', 'ચાલુ લોન સ્ટેટમેન્ટ ( જો ચાલુ હોય તો )'],
            // Quotation 40 (8 docs)
            [40, 'Passport Size Photographs Both', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ બંનેના'],
            [40, 'PAN Card Both', 'પાન કાર્ડ બંનેના'],
            [40, 'Aadhaar Card Both', 'આધાર કાર્ડ બંનેના'],
            [40, 'GST Registration Certificate', 'GST સર્ટીફીકેટ'],
            [40, 'Udyam Registration Certificate', 'ઉદ્યમ સર્ટીફીકેટ'],
            [40, 'ITR (Last 3 years)', 'ITR (છેલ્લા ૩ વર્ષ)'],
            [40, 'Bank Statement (Last 12 months)', 'બેંક સ્ટેટમેન્ટ ( છેલ્લા ૧૨ મહિનાનું )'],
            [40, 'Current Loan Statement ( if applicable )', 'ચાલુ લોન સ્ટેટમેન્ટ ( જો ચાલુ હોય તો )'],
            // Quotation 43 (9 docs)
            [43, 'Passport Size Photographs Both', 'પાસપોર્ટ સાઇઝ ફોટોગ્રાફ બંનેના'],
            [43, 'PAN Card Both', 'પાન કાર્ડ બંનેના'],
            [43, 'Aadhaar Card Both', 'આધાર કાર્ડ બંનેના'],
            [43, 'GST Registration Certificate', 'GST સર્ટીફીકેટ'],
            [43, 'Udyam Registration Certificate', 'ઉદ્યમ સર્ટીફીકેટ'],
            [43, 'ITR (Last 3 years)', 'ITR (છેલ્લા ૩ વર્ષ)'],
            [43, 'Bank Statement (Last 12 months)', 'બેંક સ્ટેટમેન્ટ ( છેલ્લા ૧૨ મહિનાનું )'],
            [43, 'Current Loan Statement ( if applicable )', 'ચાલુ લોન સ્ટેટમેન્ટ ( જો ચાલુ હોય તો )'],
            [43, 'Property File Xerox', 'પ્રોપર્ટી ફાઇલ ઝેરોક્ષ'],
        ];

        // Note: Quotations 41 and 42 had no documents in the original DB export
        // (They were duplicates of quotation for PRASHANTBHAI JADAV)

        foreach ($docs as [$quotationId, $nameEn, $nameGu]) {
            DB::table('quotation_documents')->insert([
                'quotation_id' => $quotationId,
                'document_name_en' => $nameEn,
                'document_name_gu' => $nameGu,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
