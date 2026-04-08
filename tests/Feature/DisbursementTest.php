<?php

namespace Tests\Feature;

use App\Models\DisbursementDetail;
use App\Models\LoanDetail;
use App\Models\User;
use App\Services\DisbursementService;
use App\Services\LoanStageService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class DisbursementTest extends TestCase
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

    private function createLoanAtDisbursement(User $user): LoanDetail
    {
        Auth::login($user);

        $loan = LoanDetail::create([
            'loan_number' => LoanDetail::generateLoanNumber(),
            'customer_name' => 'Test Customer',
            'customer_type' => 'proprietor',
            'loan_amount' => 5000000,
            'status' => LoanDetail::STATUS_ACTIVE,
            'current_stage' => 'disbursement',
            'created_by' => $user->id,
        ]);

        app(LoanStageService::class)->initializeStages($loan);

        $assignment = $loan->stageAssignments()->where('stage_key', 'disbursement')->first();
        $assignment->update(['status' => 'in_progress', 'started_at' => now()]);

        return $loan;
    }

    // ── Fund Transfer (auto-complete) ──

    public function test_fund_transfer_completes_loan(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        $service = app(DisbursementService::class);
        $disbursement = $service->processDisbursement($loan, [
            'disbursement_type' => DisbursementDetail::TYPE_FUND_TRANSFER,
            'disbursement_date' => now()->toDateString(),
            'amount_disbursed' => 5000000,
            'bank_account_number' => '1234567890',
            'ifsc_code' => 'HDFC0001234',
            'reference_number' => 'REF-001',
        ]);

        $this->assertEquals(DisbursementDetail::TYPE_FUND_TRANSFER, $disbursement->disbursement_type);
        $this->assertTrue($disbursement->isComplete());

        $loan->refresh();
        $this->assertEquals(LoanDetail::STATUS_COMPLETED, $loan->status);
    }

    // ── Cheque without OTC (auto-complete) ──

    public function test_cheque_without_otc_completes_loan(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        $service = app(DisbursementService::class);
        $disbursement = $service->processDisbursement($loan, [
            'disbursement_type' => DisbursementDetail::TYPE_CHEQUE,
            'disbursement_date' => now()->toDateString(),
            'amount_disbursed' => 5000000,
            'cheque_number' => 'CHQ-12345',
            'cheque_date' => now()->toDateString(),
            'is_otc' => false,
        ]);

        $this->assertTrue($disbursement->isComplete());

        $loan->refresh();
        $this->assertEquals(LoanDetail::STATUS_COMPLETED, $loan->status);
    }

    // ── Cheque with OTC (pending until cleared) ──

    public function test_cheque_with_otc_stays_pending(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        $service = app(DisbursementService::class);
        $disbursement = $service->processDisbursement($loan, [
            'disbursement_type' => DisbursementDetail::TYPE_CHEQUE,
            'disbursement_date' => now()->toDateString(),
            'amount_disbursed' => 5000000,
            'cheque_number' => 'CHQ-12345',
            'cheque_date' => now()->toDateString(),
            'is_otc' => true,
            'otc_branch' => 'Main Branch',
        ]);

        $this->assertFalse($disbursement->isComplete());
        $this->assertTrue($disbursement->needsOtcClearance());

        $loan->refresh();
        $this->assertEquals(LoanDetail::STATUS_ACTIVE, $loan->status);
    }

    public function test_otc_clearance_completes_loan(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        $service = app(DisbursementService::class);

        $disbursement = $service->processDisbursement($loan, [
            'disbursement_type' => DisbursementDetail::TYPE_CHEQUE,
            'disbursement_date' => now()->toDateString(),
            'amount_disbursed' => 5000000,
            'cheque_number' => 'CHQ-12345',
            'cheque_date' => now()->toDateString(),
            'is_otc' => true,
            'otc_branch' => 'Main Branch',
        ]);

        $clearedDisbursement = $service->clearOtc($disbursement);

        $this->assertTrue($clearedDisbursement->otc_cleared);
        $this->assertNotNull($clearedDisbursement->otc_cleared_date);

        $loan->refresh();
        $this->assertEquals(LoanDetail::STATUS_COMPLETED, $loan->status);
    }

    // ── Demand Draft (auto-complete) ──

    public function test_demand_draft_completes_loan(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        $service = app(DisbursementService::class);
        $disbursement = $service->processDisbursement($loan, [
            'disbursement_type' => DisbursementDetail::TYPE_DEMAND_DRAFT,
            'disbursement_date' => now()->toDateString(),
            'amount_disbursed' => 5000000,
            'dd_number' => 'DD-67890',
            'dd_date' => now()->toDateString(),
            'reference_number' => 'REF-DD-001',
        ]);

        $this->assertTrue($disbursement->isComplete());

        $loan->refresh();
        $this->assertEquals(LoanDetail::STATUS_COMPLETED, $loan->status);
    }

    // ── Model Logic ──

    public function test_is_complete_logic(): void
    {
        $ft = new DisbursementDetail(['disbursement_type' => DisbursementDetail::TYPE_FUND_TRANSFER]);
        $this->assertTrue($ft->isComplete());

        $dd = new DisbursementDetail(['disbursement_type' => DisbursementDetail::TYPE_DEMAND_DRAFT]);
        $this->assertTrue($dd->isComplete());

        $cheque = new DisbursementDetail(['disbursement_type' => DisbursementDetail::TYPE_CHEQUE, 'is_otc' => false]);
        $this->assertTrue($cheque->isComplete());

        $otcPending = new DisbursementDetail(['disbursement_type' => DisbursementDetail::TYPE_CHEQUE, 'is_otc' => true, 'otc_cleared' => false]);
        $this->assertFalse($otcPending->isComplete());

        $otcCleared = new DisbursementDetail(['disbursement_type' => DisbursementDetail::TYPE_CHEQUE, 'is_otc' => true, 'otc_cleared' => true]);
        $this->assertTrue($otcCleared->isComplete());
    }

    public function test_needs_otc_clearance(): void
    {
        $pending = new DisbursementDetail(['is_otc' => true, 'otc_cleared' => false]);
        $this->assertTrue($pending->needsOtcClearance());

        $cleared = new DisbursementDetail(['is_otc' => true, 'otc_cleared' => true]);
        $this->assertFalse($cleared->needsOtcClearance());

        $noOtc = new DisbursementDetail(['is_otc' => false]);
        $this->assertFalse($noOtc->needsOtcClearance());
    }

    // ── HTTP Routes ──

    public function test_disbursement_page_loads(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        $response = $this->actingAs($user)->get(route('loans.disbursement', $loan));
        $response->assertOk();
    }

    public function test_store_disbursement_via_http(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        $response = $this->actingAs($user)->post(
            route('loans.disbursement.store', $loan),
            [
                'disbursement_type' => 'fund_transfer',
                'disbursement_date' => now()->toDateString(),
                'amount_disbursed' => 5000000,
                'bank_account_number' => '1234567890',
                'ifsc_code' => 'HDFC0001234',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('disbursement_details', [
            'loan_id' => $loan->id,
            'disbursement_type' => 'fund_transfer',
        ]);
    }

    public function test_clear_otc_via_http(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        DisbursementDetail::create([
            'loan_id' => $loan->id,
            'disbursement_type' => DisbursementDetail::TYPE_CHEQUE,
            'disbursement_date' => now()->toDateString(),
            'amount_disbursed' => 5000000,
            'cheque_number' => 'CHQ-001',
            'cheque_date' => now()->toDateString(),
            'is_otc' => true,
            'otc_branch' => 'Main Branch',
        ]);

        $response = $this->actingAs($user)->post(
            route('loans.disbursement.clear-otc', $loan)
        );

        $response->assertRedirect();
    }
}
