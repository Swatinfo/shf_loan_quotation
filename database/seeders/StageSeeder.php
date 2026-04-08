<?php

namespace Database\Seeders;

use App\Models\Stage;
use Illuminate\Database\Seeder;

class StageSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            // Main sequential stages
            ['stage_key' => 'inquiry', 'stage_name_en' => 'Loan Inquiry', 'stage_name_gu' => 'લોન પૂછપરછ', 'sequence_order' => 1, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Initial customer and loan details entry'],
            ['stage_key' => 'document_selection', 'stage_name_en' => 'Document Selection', 'stage_name_gu' => 'દસ્તાવેજ પસંદગી', 'sequence_order' => 2, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Select required documents for the loan'],
            ['stage_key' => 'document_collection', 'stage_name_en' => 'Document Collection', 'stage_name_gu' => 'દસ્તાવેજ સંગ્રહ', 'sequence_order' => 3, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Collect and verify all required documents'],

            // Stage 4: Parallel parent
            ['stage_key' => 'parallel_processing', 'stage_name_en' => 'Parallel Processing', 'stage_name_gu' => 'સમાંતર પ્રક્રિયા', 'sequence_order' => 4, 'is_parallel' => true, 'parent_stage_key' => null, 'stage_type' => 'parallel', 'description_en' => 'Four parallel tracks processed simultaneously'],

            // Stage 4 sub-stages
            ['stage_key' => 'app_number', 'stage_name_en' => 'Application Number', 'stage_name_gu' => 'અરજી નંબર', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Enter bank application number'],
            ['stage_key' => 'bsm_osv', 'stage_name_en' => 'BSM/OSV Approval', 'stage_name_gu' => 'BSM/OSV મંજૂરી', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Bank site and office verification'],
            ['stage_key' => 'legal_verification', 'stage_name_en' => 'Legal Verification', 'stage_name_gu' => 'કાનૂની ચકાસણી', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Legal document verification'],
            ['stage_key' => 'technical_valuation', 'stage_name_en' => 'Technical Valuation', 'stage_name_gu' => 'ટેકનિકલ મૂલ્યાંકન', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Property/asset technical valuation'],

            // Stages 5-9: Sequential
            ['stage_key' => 'rate_pf', 'stage_name_en' => 'Rate & PF Request', 'stage_name_gu' => 'દર અને PF વિનંતી', 'sequence_order' => 5, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Request interest rate and processing fee from bank'],
            ['stage_key' => 'sanction', 'stage_name_en' => 'Sanction Letter', 'stage_name_gu' => 'મંજૂરી પત્ર', 'sequence_order' => 6, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Bank issues sanction letter'],
            ['stage_key' => 'docket', 'stage_name_en' => 'Docket Login', 'stage_name_gu' => 'ડોકેટ લોગિન', 'sequence_order' => 7, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Physical document processing and docket creation'],
            ['stage_key' => 'kfs', 'stage_name_en' => 'KFS Generation', 'stage_name_gu' => 'KFS જનરેશન', 'sequence_order' => 8, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Key Fact Statement generation'],
            ['stage_key' => 'esign', 'stage_name_en' => 'E-Sign & eNACH', 'stage_name_gu' => 'ઈ-સાઇન અને eNACH', 'sequence_order' => 9, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Digital signature and eNACH mandate'],

            // Stage 10: Decision tree
            ['stage_key' => 'disbursement', 'stage_name_en' => 'Disbursement', 'stage_name_gu' => 'વિતરણ', 'sequence_order' => 10, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'decision', 'description_en' => 'Fund disbursement — transfer or cheque with OTC handling'],

            // Bank/product-specific optional stages (enabled via product_stages in Stage I)
            ['stage_key' => 'cibil_check', 'stage_name_en' => 'CIBIL Score Check', 'stage_name_gu' => 'CIBIL સ્કોર તપાસ', 'sequence_order' => 5, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Credit score verification (optional)'],
            ['stage_key' => 'property_valuation', 'stage_name_en' => 'Property Valuation', 'stage_name_gu' => 'મિલકત મૂલ્યાંકન', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Dedicated property valuation for LAP'],
            ['stage_key' => 'vehicle_valuation', 'stage_name_en' => 'Vehicle Valuation', 'stage_name_gu' => 'વાહન મૂલ્યાંકન', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Vehicle valuation for car/vehicle loans'],
            ['stage_key' => 'business_valuation', 'stage_name_en' => 'Business Valuation', 'stage_name_gu' => 'વ્યવસાય મૂલ્યાંકન', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Business valuation for business loans'],
            ['stage_key' => 'title_search', 'stage_name_en' => 'Title Search', 'stage_name_gu' => 'ટાઇટલ સર્ચ', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Property title verification for LAP'],
            ['stage_key' => 'financial_analysis', 'stage_name_en' => 'Financial Analysis', 'stage_name_gu' => 'નાણાકીય વિશ્લેષણ', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Financial analysis for business loans'],
            ['stage_key' => 'site_visit', 'stage_name_en' => 'Site Visit Report', 'stage_name_gu' => 'સાઇટ મુલાકાત રિપોર્ટ', 'sequence_order' => 4, 'is_parallel' => false, 'parent_stage_key' => 'parallel_processing', 'stage_type' => 'sequential', 'description_en' => 'Physical site visit for business loans'],
            ['stage_key' => 'approval_committee', 'stage_name_en' => 'Approval Committee', 'stage_name_gu' => 'મંજૂરી સમિતિ', 'sequence_order' => 5, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Committee approval (ICICI specific)'],
            ['stage_key' => 'credit_committee', 'stage_name_en' => 'Credit Committee', 'stage_name_gu' => 'ક્રેડિટ સમિતિ', 'sequence_order' => 5, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Credit committee review (Kotak specific)'],
            ['stage_key' => 'insurance', 'stage_name_en' => 'Insurance', 'stage_name_gu' => 'વીમો', 'sequence_order' => 9, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Insurance requirement for vehicle loans'],
            ['stage_key' => 'mortgage', 'stage_name_en' => 'Mortgage Registration', 'stage_name_gu' => 'મોર્ટગેજ નોંધણી', 'sequence_order' => 9, 'is_parallel' => false, 'parent_stage_key' => null, 'stage_type' => 'sequential', 'description_en' => 'Mortgage registration for LAP'],
        ];

        foreach ($stages as $stage) {
            Stage::updateOrCreate(
                ['stage_key' => $stage['stage_key']],
                $stage,
            );
        }
    }
}
