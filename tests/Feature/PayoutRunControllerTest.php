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

    private function loanWithEntry(User $payoutUser, int $amount, string $date, ?Bank $bank = null, string $status = 'partial_disbursed'): LoanDetail
    {
        $bank = $bank ?: Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $branch = Branch::create(['name' => 'Br-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'HL-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true]);
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
            'loan_number' => 'L-'.uniqid(), 'application_number' => 'APP-'.uniqid(), 'customer_name' => 'C', 'customer_type' => 'salaried',
            'loan_amount' => 10000000, 'status' => $status, 'current_stage' => 'disbursement',
            'bank_id' => $bank->id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $payoutUser->id, 'payout_user_id' => $payoutUser->id,
        ]);
        $disb = DisbursementDetail::create(['loan_id' => $loan->id, 'disbursement_type' => 'fund_transfer', 'amount_disbursed' => 0]);
        DisbursementEntry::create([
            'loan_id' => $loan->id, 'disbursement_detail_id' => $disb->id, 'method' => 'fund_transfer',
            'loan_account_number' => 'AC-'.uniqid(), 'amount' => $amount, 'disbursement_date' => $date,
            'transfer_date' => $date, 'otc_status' => 'skipped', 'otc_handover_date' => $date,
            'is_active' => true,
        ]);

        return $loan;
    }

    public function test_on_hold_loan_is_excluded_from_payout_run(): void
    {
        $admin = $this->admin();
        $advisor = User::create(['name' => 'Adv', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $advisor->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));
        // On-hold loan with a fully-settled tranche in the range → must NOT be paid.
        $loan = $this->loanWithEntry($advisor->fresh('roles'), 6000000, '2026-10-15', null, 'on_hold');

        $resp = $this->actingAs($admin)->get(route('payouts.runs', [
            'bank_id' => $loan->bank_id, 'product_ids' => [$loan->product_id],
            'from' => '2026-10-01', 'to' => '2026-10-31',
        ]))->assertOk();

        $preview = $resp->viewData('preview');
        $this->assertFalse($preview['can_finalize']);        // nothing payable
        $this->assertSame(0, $preview['totals']['gross']);
        $this->assertCount(0, $preview['loan_lines']);
    }

    public function test_calculate_preview_works_via_post(): void
    {
        $admin = $this->admin();
        $advisor = User::create(['name' => 'Adv', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $advisor->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));
        $loan = $this->loanWithEntry($advisor->fresh('roles'), 6000000, '2026-10-15');

        // Calculate now posts (clean URL) to payouts.runs.calculate.
        $resp = $this->actingAs($admin)->post(route('payouts.runs.calculate'), [
            'bank_id' => $loan->bank_id, 'product_ids' => [$loan->product_id],
            'from' => '2026-10-01', 'to' => '2026-10-31',
        ])->assertOk();

        $this->assertNotNull($resp->viewData('preview'));
        $this->assertSame(60000, $resp->viewData('preview')['totals']['gross']);
    }

    public function test_preview_shows_gross_for_the_range(): void
    {
        $admin = $this->admin();
        $advisor = User::create(['name' => 'Adv', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $advisor->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));
        $loan = $this->loanWithEntry($advisor->fresh('roles'), 6000000, '2026-10-15'); // 60L @ 1% = 60,000

        $resp = $this->actingAs($admin)->get(route('payouts.runs', ['bank_id' => $loan->bank_id, 'product_ids' => [$loan->product_id], 'from' => '2026-10-01', 'to' => '2026-10-31']));

        $resp->assertOk();
        $preview = $resp->viewData('preview');
        $this->assertTrue($preview['can_finalize']);
        $this->assertSame(60000, $preview['totals']['gross']);
        $this->assertSame(57000, $preview['totals']['net']); // − 5% TDS

        // Loan-level breakdown is surfaced (grouped by user → product in the view),
        // with distinct loan amount / PF / insurance.
        $this->assertCount(1, $preview['loan_lines']);
        $this->assertSame($loan->loan_number, $preview['loan_lines'][0]['loan_number']);
        // Internal loan #, application #, and the loan's distinct A/c number(s) are surfaced.
        $this->assertSame($loan->fresh()->application_number, $preview['loan_lines'][0]['application_number']);
        $this->assertSame(
            DisbursementEntry::where('loan_id', $loan->id)->pluck('loan_account_number')->filter()->unique()->values()->all(),
            $preview['loan_lines'][0]['account_numbers'],
        );
        $this->assertSame(6000000, $preview['loan_lines'][0]['volume']); // disbursed
        $this->assertSame(0, $preview['loan_lines'][0]['pf_base']);      // amount-based
        $this->assertSame(0, $preview['loan_lines'][0]['insurance_base']);
        $this->assertSame(60000, $preview['loan_lines'][0]['payout']);   // 1% of 60L
        // The A/c number links to the loan's Stages page.
        $resp->assertSee('href="'.route('loans.stages', $loan).'"', false);
        // …and so does the loan number, opening in a new tab.
        $this->assertMatchesRegularExpression(
            '#<a href="'.preg_quote(route('loans.stages', $loan), '#').'" target="_blank"[^>]*>\s*'.preg_quote($loan->loan_number, '#').'</a>#',
            $resp->getContent(),
        );

        // Per-user base totals are exposed alongside the payouts.
        $this->assertSame(6000000, $preview['users'][0]['total_volume']);
        $this->assertSame(0, $preview['users'][0]['total_pf_base']);
        $this->assertSame(0, $preview['users'][0]['total_insurance_base']);
    }

    public function test_finalize_creates_run_stamps_entry_and_redirects(): void
    {
        $admin = $this->admin();
        $advisor = User::create(['name' => 'Adv', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $advisor->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));
        $loan = $this->loanWithEntry($advisor->fresh('roles'), 6000000, '2026-10-15');

        $resp = $this->actingAs($admin)->post(route('payouts.runs.finalize'), ['bank_id' => $loan->bank_id, 'product_ids' => [$loan->product_id], 'from' => '2026-10-01', 'to' => '2026-10-31']);

        $run = PayoutRun::latest('id')->first();
        $this->assertNotNull($run);
        $resp->assertRedirect(route('payouts.runs.show', $run));
        $this->assertSame(PayoutRun::STATUS_FINALIZED, $run->status);
        $this->assertSame((int) $loan->bank_id, (int) $run->bank_id);
        $this->assertSame(57000, (int) $run->total_net);
        // The tranche is stamped against the run.
        $this->assertSame($run->id, (int) $loan->disbursementEntries()->first()->payout_run_id);

        // Show page renders, with the A/c number linking to the loan's Stages page.
        $this->actingAs($admin)->get(route('payouts.runs.show', $run))->assertOk()
            ->assertSee('href="'.route('loans.stages', $loan).'"', false)
            ->assertSee('class="po-loan-no"', false) // brand-orange loan #
            ->assertSee('title="Open loan stages">'.$loan->loan_number.'</a>', false);
    }

    public function test_preview_can_not_finalize_when_nothing_in_range(): void
    {
        $admin = $this->admin();
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $product = Product::create(['name' => 'P', 'bank_id' => $bank->id, 'is_active' => true]);
        $resp = $this->actingAs($admin)->get(route('payouts.runs', ['bank_id' => $bank->id, 'product_ids' => [$product->id], 'from' => '2026-10-01', 'to' => '2026-10-31']));

        $resp->assertOk();
        $this->assertFalse($resp->viewData('preview')['can_finalize']);
    }

    public function test_finalize_requires_a_bank_and_products(): void
    {
        $admin = $this->admin();
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);

        $this->actingAs($admin)
            ->post(route('payouts.runs.finalize'), ['from' => '2026-10-01', 'to' => '2026-10-31'])
            ->assertSessionHasErrors(['bank_id', 'product_ids']);

        // Bank present but no products → still blocked on product_ids.
        $this->actingAs($admin)
            ->post(route('payouts.runs.finalize'), ['bank_id' => $bank->id, 'from' => '2026-10-01', 'to' => '2026-10-31'])
            ->assertSessionHasErrors('product_ids');
    }

    public function test_bank_filter_scopes_the_run_to_the_selected_bank(): void
    {
        $admin = $this->admin();
        $advisor = User::create(['name' => 'Adv', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $advisor->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));
        $advisor = $advisor->fresh('roles');

        $loanA = $this->loanWithEntry($advisor, 6000000, '2026-10-15'); // bank A: 60L @ 1% = 60,000
        $this->loanWithEntry($advisor, 9000000, '2026-10-15');          // bank B: must be excluded

        $resp = $this->actingAs($admin)->get(route('payouts.runs', ['bank_id' => $loanA->bank_id, 'product_ids' => [$loanA->product_id], 'from' => '2026-10-01', 'to' => '2026-10-31']));

        $preview = $resp->viewData('preview');
        $this->assertSame(60000, $preview['totals']['gross']); // only bank A counted
    }

    public function test_product_filter_scopes_run_and_leaves_other_products_untouched(): void
    {
        $admin = $this->admin();
        $advisor = User::create(['name' => 'Adv', 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $advisor->roles()->sync(Role::where('slug', 'loan_advisor')->pluck('id'));
        $advisor = $advisor->fresh('roles');

        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $loanA = $this->loanWithEntry($advisor, 6000000, '2026-10-15', $bank); // product A (same bank)
        $loanB = $this->loanWithEntry($advisor, 5000000, '2026-10-15', $bank); // product B (same bank)

        // Run for product A only.
        $this->actingAs($admin)->post(route('payouts.runs.finalize'), [
            'bank_id' => $bank->id, 'product_ids' => [$loanA->product_id],
            'from' => '2026-10-01', 'to' => '2026-10-31',
        ])->assertRedirect();

        // A is covered; B is left untouched (not deleted/affected).
        $this->assertNotNull($loanA->disbursementEntries()->first()->payout_run_id);
        $this->assertNull($loanB->disbursementEntries()->first()->payout_run_id);

        // A later run for product B picks up B's still-uncovered tranche.
        $this->actingAs($admin)->post(route('payouts.runs.finalize'), [
            'bank_id' => $bank->id, 'product_ids' => [$loanB->product_id],
            'from' => '2026-10-01', 'to' => '2026-10-31',
        ])->assertRedirect();
        $this->assertNotNull($loanB->disbursementEntries()->first()->fresh()->payout_run_id);
    }
}
