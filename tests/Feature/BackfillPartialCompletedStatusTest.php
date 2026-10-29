<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\StageAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The one-time STATUS-ONLY backfill: partial_disbursed loans whose every tranche
 * is OTC-settled, below target and not declared full become partial_completed.
 * Nothing else (stages, entries) changes.
 */
class BackfillPartialCompletedStatusTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array{0:string, 1:string}>  $tranches  [method, otc_status]
     */
    private function partialLoan(array $tranches, int $perTranche = 500000, string $intent = 'open'): LoanDetail
    {
        $user = User::create(['name' => 'U', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $bank = Bank::create(['name' => 'B-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'P-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'customer_name' => 'C', 'customer_type' => 'salaried',
            'loan_amount' => 2000000, 'sanctioned_amount' => 2000000, 'status' => 'active', 'current_stage' => 'disbursement',
            'bank_id' => $bank->id, 'branch_id' => Branch::create(['name' => 'Br', 'is_active' => true])->id,
            'product_id' => $product->id, 'created_by' => $user->id,
        ]);
        // Status set directly (pre-migration data shape); bypasses the resolver.
        DB::table('loan_details')->where('id', $loan->id)->update(['status' => 'partial_disbursed']);
        StageAssignment::create(['loan_id' => $loan->id, 'stage_key' => 'disbursement', 'status' => 'in_progress', 'is_parallel_stage' => false]);

        $detail = DisbursementDetail::create([
            'loan_id' => $loan->id, 'disbursement_type' => 'cheque', 'amount_disbursed' => 0,
            'pf_amount' => 0, 'admin_charges' => 0, 'insurance_amount' => 0, 'completion_intent' => $intent,
        ]);
        foreach ($tranches as [$method, $otc]) {
            DisbursementEntry::create([
                'loan_id' => $loan->id, 'disbursement_detail_id' => $detail->id, 'method' => $method,
                'amount' => $perTranche, 'disbursement_date' => '2026-10-10',
                'otc_status' => $otc, 'otc_handover_date' => $otc === 'pending' ? null : '2026-10-10', 'is_active' => true,
            ]);
        }

        return $loan;
    }

    private function runBackfill(): void
    {
        (require database_path('migrations/2026_10_28_184008_backfill_partial_completed_loan_status.php'))->up();
    }

    public function test_settled_below_target_partial_loan_becomes_partial_completed(): void
    {
        $loan = $this->partialLoan([['cheque', 'cleared'], ['fund_transfer', 'skipped']]);

        $this->runBackfill();

        $this->assertSame(LoanDetail::STATUS_PARTIAL_COMPLETED, $loan->fresh()->status);
        // Status only — the disbursement stage is left exactly as it was.
        $this->assertSame('in_progress', $loan->stageAssignments()->where('stage_key', 'disbursement')->value('status'));
    }

    public function test_pending_cheque_full_intent_or_target_reached_stay_partial_disbursed(): void
    {
        $pending = $this->partialLoan([['cheque', 'cleared'], ['cheque', 'pending']]);
        $declaredFull = $this->partialLoan([['cheque', 'cleared']], intent: 'full');
        $atTarget = $this->partialLoan([['cheque', 'cleared']], perTranche: 2000000);

        $this->runBackfill();

        $this->assertSame(LoanDetail::STATUS_PARTIAL_DISBURSED, $pending->fresh()->status);
        $this->assertSame(LoanDetail::STATUS_PARTIAL_DISBURSED, $declaredFull->fresh()->status);
        $this->assertSame(LoanDetail::STATUS_PARTIAL_DISBURSED, $atTarget->fresh()->status);
    }
}
