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

    // ── Fund Transfer (completes loan immediately) ──

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
        ]);

        $this->assertEquals(DisbursementDetail::TYPE_FUND_TRANSFER, $disbursement->disbursement_type);
        $this->assertTrue($disbursement->isComplete());

        $loan->refresh();
        $this->assertEquals(LoanDetail::STATUS_COMPLETED, $loan->status);
    }

    // ── Cheque (loan stays active for OTC) ──

    public function test_cheque_keeps_loan_active(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        $service = app(DisbursementService::class);
        $disbursement = $service->processDisbursement($loan, [
            'disbursement_type' => DisbursementDetail::TYPE_CHEQUE,
            'disbursement_date' => now()->toDateString(),
            'amount_disbursed' => 5000000,
            'cheques' => [
                ['cheque_number' => 'CHQ-001', 'cheque_date' => '10/04/2026', 'cheque_amount' => 3000000],
                ['cheque_number' => 'CHQ-002', 'cheque_date' => '15/04/2026', 'cheque_amount' => 2000000],
            ],
        ]);

        $this->assertEquals(DisbursementDetail::TYPE_CHEQUE, $disbursement->disbursement_type);
        $this->assertCount(2, $disbursement->cheques);

        $loan->refresh();
        $this->assertEquals(LoanDetail::STATUS_ACTIVE, $loan->status);
    }

    // ── Multiple Cheques ──

    public function test_multiple_cheques_stored(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        $service = app(DisbursementService::class);
        $disbursement = $service->processDisbursement($loan, [
            'disbursement_type' => DisbursementDetail::TYPE_CHEQUE,
            'disbursement_date' => now()->toDateString(),
            'amount_disbursed' => 5000000,
            'cheques' => [
                ['cheque_number' => 'CHQ-A', 'cheque_date' => '10/04/2026', 'cheque_amount' => 2000000],
                ['cheque_number' => 'CHQ-B', 'cheque_date' => '12/04/2026', 'cheque_amount' => 1500000],
                ['cheque_number' => 'CHQ-C', 'cheque_date' => '14/04/2026', 'cheque_amount' => 1500000],
            ],
        ]);

        $this->assertCount(3, $disbursement->cheques);
        $this->assertEquals('CHQ-A', $disbursement->cheques[0]['cheque_number']);
        $this->assertEquals(2000000, $disbursement->cheques[0]['cheque_amount']);
    }

    // ── Model Logic ──

    public function test_is_complete_logic(): void
    {
        $ft = new DisbursementDetail(['disbursement_type' => DisbursementDetail::TYPE_FUND_TRANSFER]);
        $this->assertTrue($ft->isComplete());

        $cheque = new DisbursementDetail(['disbursement_type' => DisbursementDetail::TYPE_CHEQUE]);
        $this->assertTrue($cheque->isComplete());
    }

    public function test_types_constant(): void
    {
        $this->assertArrayHasKey('fund_transfer', DisbursementDetail::TYPES);
        $this->assertArrayHasKey('cheque', DisbursementDetail::TYPES);
        $this->assertCount(2, DisbursementDetail::TYPES);
    }

    // ── HTTP Routes ──

    public function test_disbursement_page_loads(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        $response = $this->actingAs($user)->get(route('loans.disbursement', $loan));
        $response->assertOk();
    }

    public function test_store_fund_transfer_via_http(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        $response = $this->actingAs($user)->post(
            route('loans.disbursement.store', $loan),
            [
                'disbursement_type' => 'fund_transfer',
                'disbursement_date' => now()->format('d/m/Y'),
                'amount_disbursed' => 5000000,
                'bank_account_number' => '1234567890',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('disbursement_details', [
            'loan_id' => $loan->id,
            'disbursement_type' => 'fund_transfer',
        ]);
    }

    public function test_store_cheque_via_http(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        $response = $this->actingAs($user)->post(
            route('loans.disbursement.store', $loan),
            [
                'disbursement_type' => 'cheque',
                'disbursement_date' => now()->format('d/m/Y'),
                'amount_disbursed' => 5000000,
                'cheques' => [
                    ['cheque_number' => 'CHQ-001', 'cheque_date' => '10/04/2026', 'cheque_amount' => 5000000],
                ],
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('disbursement_details', [
            'loan_id' => $loan->id,
            'disbursement_type' => 'cheque',
        ]);
    }

    public function test_cheque_total_cannot_exceed_amount(): void
    {
        $user = $this->createUser();
        $loan = $this->createLoanAtDisbursement($user);

        $response = $this->actingAs($user)->post(
            route('loans.disbursement.store', $loan),
            [
                'disbursement_type' => 'cheque',
                'disbursement_date' => now()->format('d/m/Y'),
                'amount_disbursed' => 1000000,
                'cheques' => [
                    ['cheque_number' => 'CHQ-001', 'cheque_date' => '10/04/2026', 'cheque_amount' => 600000],
                    ['cheque_number' => 'CHQ-002', 'cheque_date' => '12/04/2026', 'cheque_amount' => 600000],
                ],
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
