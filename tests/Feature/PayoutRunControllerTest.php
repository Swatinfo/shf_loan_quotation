<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use App\Models\PayoutRun;
use App\Models\Product;
use App\Models\ProductPayoutSlab;
use App\Models\ProductPayoutVersion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aggregate payout runs over HTTP: date-range preview → finalize → run show.
 * The compute itself is covered by PayoutRunServiceTest; this proves the
 * controller, routes, permission gate and views wire up.
 */
class PayoutRunControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['name' => 'SA', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->roles()->sync(Role::where('slug', 'super_admin')->pluck('id'));

        return $u->fresh('roles');
    }

    private function loanWithEntry(User $payoutUser, int $amount, string $date): LoanDetail
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Br-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'HL', 'bank_id' => $bank->id, 'is_active' => true]);
        $version = ProductPayoutVersion::create([
            'product_id' => $product->id, 'effective_from' => '2000-01-01',
            'is_pf_based' => false, 'payout_cycle_start_day' => 1, 'payout_cycle_end_day' => 31,
        ]);
        $product->update(['current_payout_version_id' => $version->id]);
        ProductPayoutSlab::create([
            'product_id' => $product->id, 'version_id' => $version->id,
            'low_amount' => 0, 'high_amount' => 1000000000,
            'payout_type' => 'percent', 'payout_value' => 1.0,
            'connector_payout_type' => 'percent', 'connector_payout_value' => 2.0,
        ]);

        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'customer_name' => 'C', 'customer_type' => 'salaried',
            'loan_amount' => 10000000, 'status' => 'partial_disbursed', 'current_stage' => 'disbursement',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $payoutUser->id, 'payout_user_id' => $payoutUser->id,
        ]);
        $disb = DisbursementDetail::create(['loan_id' => $loan->id, 'disbursement_type' => 'fund_transfer', 'amount_disbursed' => 0]);
        DisbursementEntry::create([
            'loan_id' => $loan->id, 'disbursement_detail_id' => $disb->id, 'method' => 'fund_transfer',
            'amount' => $amount, 'disbursement_date' => $date,
            'transfer_date' => $date, 'otc_status' => 'skipped', 'otc_handover_date' => $date,
            'is_active' => true,
        ]);

        return $loan;
    }

    public function test_preview_shows_gross_for_the_range(): void
    {
        $admin = $this->admin();
        $advisor = User::create(['name' => 'Adv', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $advisor->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));
        $this->loanWithEntry($advisor->fresh('roles'), 6000000, '2026-10-15'); // 60L @ 1% = 60,000

        $resp = $this->actingAs($admin)->get(route('payouts.runs', ['from' => '2026-10-01', 'to' => '2026-10-31']));

        $resp->assertOk();
        $preview = $resp->viewData('preview');
        $this->assertTrue($preview['can_finalize']);
        $this->assertSame(60000, $preview['totals']['gross']);
        $this->assertSame(57000, $preview['totals']['net']); // − 5% TDS
    }

    public function test_finalize_creates_run_stamps_entry_and_redirects(): void
    {
        $admin = $this->admin();
        $advisor = User::create(['name' => 'Adv', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $advisor->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));
        $loan = $this->loanWithEntry($advisor->fresh('roles'), 6000000, '2026-10-15');

        $resp = $this->actingAs($admin)->post(route('payouts.runs.finalize'), ['from' => '2026-10-01', 'to' => '2026-10-31']);

        $run = PayoutRun::latest('id')->first();
        $this->assertNotNull($run);
        $resp->assertRedirect(route('payouts.runs.show', $run));
        $this->assertSame(PayoutRun::STATUS_FINALIZED, $run->status);
        $this->assertSame(57000, (int) $run->total_net);
        // The tranche is stamped against the run.
        $this->assertSame($run->id, (int) $loan->disbursementEntries()->first()->payout_run_id);

        // Show page renders.
        $this->actingAs($admin)->get(route('payouts.runs.show', $run))->assertOk();
    }

    public function test_preview_can_not_finalize_when_nothing_in_range(): void
    {
        $admin = $this->admin();
        $resp = $this->actingAs($admin)->get(route('payouts.runs', ['from' => '2026-10-01', 'to' => '2026-10-31']));

        $resp->assertOk();
        $this->assertFalse($resp->viewData('preview')['can_finalize']);
    }
}
