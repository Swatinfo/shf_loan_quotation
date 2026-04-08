<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementDetail;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationBank;
use App\Models\QuotationDocument;
use App\Models\User;
use App\Services\DisbursementService;
use App\Services\LoanDocumentService;
use App\Services\LoanStageService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LoanEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(StageSeeder::class);
    }

    private function createUser(string $role = 'admin', array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'is_active' => true,
            'task_role' => 'loan_advisor',
        ], $overrides));
    }

    /**
     * Full lifecycle: quotation → convert → all stages → disbursement → completed
     */
    public function test_full_loan_lifecycle(): void
    {
        $user = $this->createUser();
        $bank = Bank::create(['name' => 'HDFC Bank']);
        $branch = Branch::create(['name' => 'Main', 'code' => 'M01']);
        $product = Product::create(['bank_id' => $bank->id, 'name' => 'Home Loan', 'code' => 'HL01', 'is_active' => true]);

        // ── Step 1: Create quotation ──
        $quotation = Quotation::create([
            'user_id' => $user->id,
            'customer_name' => 'Rajesh Patel',
            'customer_type' => 'proprietor',
            'loan_amount' => 5000000,
            'pdf_filename' => 'test.pdf',
            'pdf_path' => '/tmp/test.pdf',
            'selected_tenures' => [5, 10, 15],
        ]);

        QuotationBank::create([
            'quotation_id' => $quotation->id,
            'bank_name' => 'HDFC Bank',
            'roi_min' => 8.50,
            'roi_max' => 9.00,
            'pf_charge' => 1.00,
            'admin_charge' => 5000,
            'stamp_notary' => 1000,
            'registration_fee' => 0,
            'advocate_fees' => 3000,
            'iom_charge' => 500,
            'tc_report' => 0,
            'total_charges' => 9500,
        ]);

        QuotationDocument::create([
            'quotation_id' => $quotation->id,
            'document_name_en' => 'PAN Card',
            'document_name_gu' => 'પાન કાર્ડ',
        ]);

        QuotationDocument::create([
            'quotation_id' => $quotation->id,
            'document_name_en' => 'Aadhar Card',
            'document_name_gu' => 'આધાર કાર્ડ',
        ]);

        // ── Step 2: Convert to loan ──
        $response = $this->actingAs($user)->post(
            route('quotations.convert.store', $quotation),
            [
                'bank_index' => 0,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'customer_phone' => '9876543210',
                'assigned_advisor' => $user->id,
            ]
        );

        $response->assertRedirect();
        $loan = LoanDetail::first();

        $this->assertNotNull($loan);
        $this->assertEquals('Rajesh Patel', $loan->customer_name);
        $this->assertEquals('active', $loan->status);
        $this->assertStringStartsWith('SHF-', $loan->loan_number);

        // inquiry + document_selection auto-completed, now at document_collection
        $this->assertEquals('document_collection', $loan->current_stage);

        // Documents should be copied
        $this->assertEquals(2, $loan->documents()->count());

        // ── Step 3: Process documents ──
        $docs = $loan->documents()->get();
        $docService = app(LoanDocumentService::class);
        foreach ($docs as $doc) {
            $docService->updateStatus($doc, 'received', $user->id);
        }
        $this->assertTrue($docService->allRequiredResolved($loan));

        // ── Step 4: Progress through sequential stages ──
        $stageService = app(LoanStageService::class);

        // Complete document_collection
        $stageService->updateStageStatus($loan, 'document_collection', 'in_progress', $user->id);
        $stageService->updateStageStatus($loan, 'document_collection', 'completed', $user->id);

        $loan->refresh();
        $this->assertEquals('parallel_processing', $loan->current_stage);

        // ── Step 5: Complete parallel sub-stages ──
        $subStages = $loan->stageAssignments()
            ->where('parent_stage_key', 'parallel_processing')
            ->pluck('stage_key')
            ->toArray();

        foreach ($subStages as $subKey) {
            $sub = $loan->stageAssignments()->where('stage_key', $subKey)->first();
            $sub->update(['status' => 'in_progress', 'started_at' => now()]);
            $stageService->updateStageStatus($loan, $subKey, 'completed', $user->id);
        }

        $loan->refresh();
        $this->assertEquals('rate_pf', $loan->current_stage);

        // ── Step 6: Complete remaining sequential stages until disbursement ──
        // Note: auto-advance may already set stages to in_progress
        // Dynamically process all stages between parallel_processing and disbursement
        $loan->refresh();
        $maxIterations = 20;
        $iterations = 0;
        while ($loan->current_stage !== 'disbursement' && $iterations < $maxIterations) {
            $stageKey = $loan->current_stage;
            $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->first();

            if (! $assignment) {
                // Stage exists in stages table but has no assignment — skip by advancing manually
                $nextKey = $stageService->getNextStage($stageKey);
                $loan->update(['current_stage' => $nextKey]);
                $loan->refresh();
                $iterations++;

                continue;
            }

            if ($assignment->status === 'pending') {
                $stageService->updateStageStatus($loan, $stageKey, 'in_progress', $user->id);
            }
            $stageService->updateStageStatus($loan, $stageKey, 'completed', $user->id);
            $loan->refresh();
            $iterations++;
        }

        $this->assertEquals('disbursement', $loan->current_stage);

        // ── Step 7: Process disbursement ──
        $disbursementAssignment = $loan->stageAssignments()->where('stage_key', 'disbursement')->first();
        $disbursementAssignment->update(['status' => 'in_progress', 'started_at' => now()]);

        $disbursementService = app(DisbursementService::class);
        Auth::login($user);
        $disbursementService->processDisbursement($loan, [
            'disbursement_type' => DisbursementDetail::TYPE_FUND_TRANSFER,
            'disbursement_date' => now()->toDateString(),
            'amount_disbursed' => 5000000,
            'bank_account_number' => '1234567890',
            'ifsc_code' => 'HDFC0001234',
            'reference_number' => 'REF-001',
        ]);

        // ── Step 8: Verify final state ──
        $loan->refresh();
        $this->assertEquals(LoanDetail::STATUS_COMPLETED, $loan->status);

        // Progress should show significant completion
        $progress = $loan->progress()->first();
        $this->assertNotNull($progress);
        $this->assertGreaterThan(80, $progress->overall_percentage);

        // Activity log should have entries
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'convert_quotation_to_loan',
        ]);
    }

    /**
     * Direct loan creation → stages → rejection
     */
    public function test_direct_loan_rejection_flow(): void
    {
        $user = $this->createUser();
        $bank = Bank::create(['name' => 'SBI']);
        $branch = Branch::create(['name' => 'Central', 'code' => 'C01']);
        $product = Product::create(['bank_id' => $bank->id, 'name' => 'Personal Loan', 'code' => 'PL01', 'is_active' => true]);

        // Create direct loan
        $response = $this->actingAs($user)->post(route('loans.store'), [
            'customer_name' => 'Suresh Shah',
            'customer_type' => 'salaried',
            'loan_amount' => 2000000,
            'bank_id' => $bank->id,
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'customer_phone' => '9876543210',
        ]);

        $response->assertRedirect();
        $loan = LoanDetail::first();
        $this->assertEquals('inquiry', $loan->current_stage);

        // Start and reject at inquiry stage
        $stageService = app(LoanStageService::class);
        $stageService->updateStageStatus($loan, 'inquiry', 'in_progress', $user->id);
        $stageService->rejectLoan($loan, 'inquiry', 'CIBIL score below 650', $user->id);

        $loan->refresh();
        $this->assertEquals(LoanDetail::STATUS_REJECTED, $loan->status);
        $this->assertEquals('inquiry', $loan->rejected_stage);
        $this->assertEquals('CIBIL score below 650', $loan->rejection_reason);
        $this->assertNotNull($loan->rejected_at);
    }

    /**
     * Cheque disbursement keeps loan active (OTC handled separately)
     */
    public function test_cheque_disbursement_keeps_loan_active(): void
    {
        $user = $this->createUser();

        $loan = LoanDetail::create([
            'loan_number' => LoanDetail::generateLoanNumber(),
            'customer_name' => 'Cheque Customer',
            'customer_type' => 'pvt_ltd',
            'loan_amount' => 10000000,
            'status' => LoanDetail::STATUS_ACTIVE,
            'current_stage' => 'disbursement',
            'created_by' => $user->id,
        ]);

        app(LoanStageService::class)->initializeStages($loan);
        $loan->stageAssignments()->where('stage_key', 'disbursement')->first()
            ->update(['status' => 'in_progress', 'started_at' => now()]);

        $disbursementService = app(DisbursementService::class);
        Auth::login($user);

        $disbursement = $disbursementService->processDisbursement($loan, [
            'disbursement_type' => DisbursementDetail::TYPE_CHEQUE,
            'disbursement_date' => now()->toDateString(),
            'amount_disbursed' => 10000000,
            'cheques' => [
                ['cheque_number' => 'CHQ-99999', 'cheque_date' => '10/04/2026', 'cheque_amount' => 10000000],
            ],
        ]);

        $loan->refresh();
        $this->assertEquals(LoanDetail::STATUS_ACTIVE, $loan->status);
        $this->assertCount(1, $disbursement->cheques);
    }

    /**
     * Loan number generation is unique and sequential
     */
    public function test_loan_numbers_are_sequential(): void
    {
        $user = $this->createUser();

        $loan1 = LoanDetail::create([
            'loan_number' => LoanDetail::generateLoanNumber(),
            'customer_name' => 'Customer 1',
            'customer_type' => 'proprietor',
            'loan_amount' => 1000000,
            'status' => 'active',
            'current_stage' => 'inquiry',
            'created_by' => $user->id,
        ]);

        $loan2 = LoanDetail::create([
            'loan_number' => LoanDetail::generateLoanNumber(),
            'customer_name' => 'Customer 2',
            'customer_type' => 'proprietor',
            'loan_amount' => 2000000,
            'status' => 'active',
            'current_stage' => 'inquiry',
            'created_by' => $user->id,
        ]);

        $this->assertNotEquals($loan1->loan_number, $loan2->loan_number);
        // Both should have SHF- prefix with same month
        $prefix = 'SHF-'.now()->format('Ym').'-';
        $this->assertStringStartsWith($prefix, $loan1->loan_number);
        $this->assertStringStartsWith($prefix, $loan2->loan_number);

        // Second should have higher number
        $num1 = (int) substr($loan1->loan_number, -4);
        $num2 = (int) substr($loan2->loan_number, -4);
        $this->assertEquals($num1 + 1, $num2);
    }
}
