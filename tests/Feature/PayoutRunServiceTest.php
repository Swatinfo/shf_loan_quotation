<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\ProductPayoutSlab;
use App\Models\ProductPayoutVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PayoutRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Aggregate payout engine: product-wide disbursed volume picks the slab tier,
 * each user earns their volume × tier rate (volume includes a normal product's
 * one-time PF + admin as entered, once per loan); PF-based pays PF(ex-GST) × rate,
 * no admin, excluded from volume; insurance per loan shown for information only;
 * TDS per user on the final total; caps + idempotency.
 */
class PayoutRunServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayoutRunService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(NotificationService::class)->shouldIgnoreMissing();
        $this->svc = app(PayoutRunService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function user(string $role = 'loan_advisor'): User
    {
        $u = User::create(['name' => $role.'-'.uniqid(), 'email' => uniqid().'@t', 'password' => bcrypt('x'), 'is_active' => true]);
        $u->roles()->sync(Role::where('slug', $role)->pluck('id'));

        return $u->fresh('roles');
    }

    /**
     * @param  array<int, array{0:int,1:int,2:string,3:float,4:string,5:float}>  $slabs  [low,high,type,val,connType,connVal]
     */
    private function product(bool $pfBased, array $slabs, ?int $maxCap = null): Product
    {
        $bank = Bank::create(['name' => 'Bank-'.uniqid(), 'is_active' => true]);
        $product = Product::create([
            'name' => 'Product-'.uniqid(), 'bank_id' => $bank->id, 'is_active' => true,
            'is_pf_based' => $pfBased, 'max_payout_amount' => $maxCap,
        ]);
        $version = ProductPayoutVersion::create([
            'product_id' => $product->id, 'effective_from' => '2000-01-01',
            'is_pf_based' => $pfBased, 'max_payout_amount' => $maxCap,
            'payout_cycle_start_day' => 1, 'payout_cycle_end_day' => 31,
        ]);
        $product->update(['current_payout_version_id' => $version->id]);
        foreach ($slabs as $s) {
            ProductPayoutSlab::create([
                'product_id' => $product->id, 'version_id' => $version->id,
                'low_amount' => $s[0], 'high_amount' => $s[1],
                'payout_type' => $s[2], 'payout_value' => $s[3],
                'connector_payout_type' => $s[4], 'connector_payout_value' => $s[5],
            ]);
        }

        return $product->fresh();
    }

    /**
     * @param  array<int, array{0:int,1?:string}>  $entries  [amount, date?]
     */
    private function loan(Product $product, User $payoutUser, array $entries, int $pf = 0, int $insurance = 0, int $admin = 0): LoanDetail
    {
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'customer_name' => 'C', 'customer_type' => 'salaried',
            'loan_amount' => 10000000, 'status' => 'partial_disbursed', 'current_stage' => 'disbursement',
            'bank_id' => $product->bank_id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $payoutUser->id, 'payout_user_id' => $payoutUser->id,
        ]);
        $detail = DisbursementDetail::create([
            'loan_id' => $loan->id, 'disbursement_type' => 'fund_transfer',
            'pf_amount' => $pf, 'admin_charges' => $admin, 'insurance_amount' => $insurance, 'amount_disbursed' => 0,
        ]);
        foreach ($entries as $e) {
            DisbursementEntry::create([
                'loan_id' => $loan->id, 'disbursement_detail_id' => $detail->id,
                'method' => 'fund_transfer', 'amount' => $e[0],
                'disbursement_date' => $e[1] ?? '2026-10-10',
                // Settlement-date basis: NEFT settles on its transfer date.
                'transfer_date' => $e[1] ?? '2026-10-10',
                'otc_status' => 'skipped', 'otc_handover_date' => $e[1] ?? '2026-10-10',
                'is_active' => true,
            ]);
        }

        return $loan;
    }

    private function pct(int $low, int $high, float $val): array
    {
        return [$low, $high, 'percent', $val, 'percent', $val];
    }

    public function test_amount_based_product_wide_volume_picks_tier_and_pays_per_user(): void
    {
        // Slabs: 0-1Cr → 0.3%, 1-2Cr → 0.4%.
        $product = $this->product(false, [$this->pct(0, 10000000, 0.3), $this->pct(10000001, 20000000, 0.4)]);
        $a = $this->user();
        $b = $this->user();
        // Product-wide volume: A 60L+40L + B 50L = 1.5Cr → tier 0.4%.
        $this->loan($product, $a, [[6000000], [4000000]]);
        $this->loan($product, $b, [[5000000]]);

        $r = $this->svc->previewRun('2026-10-01', '2026-10-31');

        $this->assertSame(15000000, $r['products'][0]['aggregate_base']);
        $this->assertSame(0.4, $r['products'][0]['rate_value']);
        $byUser = collect($r['users'])->keyBy('payout_user_id');
        // A: 1Cr × 0.4% = 40,000; B: 50L × 0.4% = 20,000.
        $this->assertSame(40000, $byUser[$a->id]['total_commission']);
        $this->assertSame(20000, $byUser[$b->id]['total_commission']);
        // TDS 5%: A net 38,000; B net 19,000.
        $this->assertSame(2000, $byUser[$a->id]['tds_amount']);
        $this->assertSame(38000, $byUser[$a->id]['net_payout']);
        $this->assertSame(19000, $byUser[$b->id]['net_payout']);
    }

    public function test_only_settled_tranches_count_pending_cheque_excluded(): void
    {
        // Payout keys on otc_handover_date: a cleared cheque (settled in range) counts;
        // a pending cheque (null handover) is excluded until it is cleared.
        $product = $this->product(false, [$this->pct(0, 100000000, 1.0)]); // 1%
        $u = $this->user();
        $branch = Branch::create(['name' => 'Branch-'.uniqid(), 'is_active' => true]);
        $loan = LoanDetail::create([
            'loan_number' => 'L-'.uniqid(), 'customer_name' => 'C', 'customer_type' => 'salaried',
            'loan_amount' => 10000000, 'status' => 'partial_disbursed', 'current_stage' => 'otc_clearance',
            'bank_id' => $product->bank_id, 'branch_id' => $branch->id, 'product_id' => $product->id,
            'created_by' => $u->id, 'payout_user_id' => $u->id,
        ]);
        $detail = DisbursementDetail::create(['loan_id' => $loan->id, 'disbursement_type' => 'cheque', 'amount_disbursed' => 0]);
        DisbursementEntry::create([ // cleared cheque — settled in range
            'loan_id' => $loan->id, 'disbursement_detail_id' => $detail->id, 'method' => 'cheque',
            'amount' => 4000000, 'disbursement_date' => '2026-10-05',
            'otc_status' => 'cleared', 'otc_handover_date' => '2026-10-20', 'is_active' => true,
        ]);
        DisbursementEntry::create([ // pending cheque — no handover → excluded
            'loan_id' => $loan->id, 'disbursement_detail_id' => $detail->id, 'method' => 'cheque',
            'amount' => 6000000, 'disbursement_date' => '2026-10-06',
            'otc_status' => 'pending', 'otc_handover_date' => null, 'is_active' => true,
        ]);

        $r = $this->svc->previewRun('2026-10-01', '2026-10-31');

        $this->assertSame(4000000, $r['products'][0]['aggregate_base']); // only the cleared 40L
        $byUser = collect($r['users'])->keyBy('payout_user_id');
        $this->assertSame(40000, $byUser[$u->id]['total_commission']); // 1% of 40L
    }

    public function test_pf_based_pays_ex_gst_pf_times_rate_and_excluded_from_volume(): void
    {
        $product = $this->product(true, [$this->pct(0, 100000000, 75.0)]);
        $a = $this->user();
        // PF 11,800 incl GST → ex-GST base 10,000; payout = 10,000 × 75% = 7,500.
        $this->loan($product, $a, [[500000]], pf: 11800);

        $r = $this->svc->previewRun('2026-10-01', '2026-10-31');

        $this->assertTrue($r['products'][0]['is_pf_based']);
        $this->assertSame(10000, $r['products'][0]['aggregate_base']); // PF ex-GST, not the 5L disbursed
        $line = $r['lines'][0];
        $this->assertSame(10000, $line['pf_base']);
        $this->assertSame(7500, $line['pf_payout']);
        $this->assertSame(0, $line['commission']); // disbursed is not a commission base
    }

    public function test_insurance_is_info_only_and_tds_on_commission_total(): void
    {
        $product = $this->product(false, [$this->pct(0, 100000000, 1.0)]);
        $a = $this->user();
        // 60L × 1% = 60,000 commission; insurance 25,000 × 2% = 500 (shown only).
        $this->loan($product, $a, [[6000000]], insurance: 25000);

        $r = $this->svc->previewRun('2026-10-01', '2026-10-31');
        $u = $r['users'][0];
        $this->assertSame(60000, $u['total_commission']);
        $this->assertSame(500, $u['total_insurance_payout']);
        // Insurance not added: total 60,000; TDS 5% = 3,000; net 57,000.
        $this->assertSame(60000, $u['total_payout']);
        $this->assertSame(3000, $u['tds_amount']);
        $this->assertSame(57000, $u['net_payout']);
        $this->assertSame(500, $r['loan_lines'][0]['insurance_payout']);
        $this->assertSame(60000, $r['loan_lines'][0]['payout']);
    }

    public function test_normal_product_adds_pf_and_admin_as_entered_to_base_once_per_loan(): void
    {
        $product = $this->product(false, [$this->pct(0, 100000000, 1.0)]);
        $a = $this->user();
        // Tranche 5,00,000 + PF 11,800 + admin 2,360 (as entered) = 5,14,160 base.
        $loan = $this->loan($product, $a, [[500000, '2026-10-10']], pf: 11800, admin: 2360);
        $actor = $this->user('super_admin');

        $r = $this->svc->previewRun('2026-10-01', '2026-10-31');
        $this->assertSame(514160, $r['products'][0]['aggregate_base']);
        $this->assertSame(5142, $r['users'][0]['total_commission']); // 1% of 5,14,160
        $this->assertSame(14160, $r['loan_lines'][0]['charges']);
        $this->assertSame(500000, $r['loan_lines'][0]['volume']);

        $run = $this->svc->finalizeRun('2026-10-01', '2026-10-31', $actor);
        $this->assertSame($run->id, $loan->disbursement->fresh()->charges_payout_run_id);
        $this->assertSame(514160, $loan->disbursementEntries()->first()->paid_amount_counted);

        // Next month's tranche: tranche only — PF + admin never re-counted.
        $detail = $loan->disbursement;
        DisbursementEntry::create([
            'loan_id' => $loan->id, 'disbursement_detail_id' => $detail->id, 'method' => 'fund_transfer',
            'amount' => 300000, 'disbursement_date' => '2026-11-05', 'transfer_date' => '2026-11-05',
            'otc_status' => 'skipped', 'otc_handover_date' => '2026-11-05', 'is_active' => true,
        ]);
        $r2 = $this->svc->previewRun('2026-11-01', '2026-11-30');
        $this->assertSame(300000, $r2['products'][0]['aggregate_base']);
        $this->assertSame(3000, $r2['users'][0]['total_commission']);
        $this->assertSame(0, $r2['loan_lines'][0]['charges']);
    }

    public function test_pf_based_product_gets_no_admin(): void
    {
        $product = $this->product(true, [$this->pct(0, 100000000, 20.0)]);
        $a = $this->user();
        // PF 11,800 incl GST → 10,000 excl GST × 20% = 2,000. Admin is ignored.
        $this->loan($product, $a, [[500000]], pf: 11800, admin: 2360);

        $r = $this->svc->previewRun('2026-10-01', '2026-10-31');
        $this->assertSame(10000, $r['products'][0]['aggregate_base']);
        $this->assertSame(2000, $r['users'][0]['total_pf_payout']);
        $this->assertSame(2000, $r['users'][0]['total_payout']);
        $this->assertSame(0, $r['loan_lines'][0]['charges']);
        $this->assertEmpty($r['charges_paid_detail_ids']);
    }

    public function test_max_payout_caps_each_user_product_payout(): void
    {
        // 1% with a ₹30,000 cap; user volume 60L → 60,000 → capped to 30,000.
        $product = $this->product(false, [$this->pct(0, 100000000, 1.0)], maxCap: 30000);
        $a = $this->user();
        $this->loan($product, $a, [[6000000]]);

        $r = $this->svc->previewRun('2026-10-01', '2026-10-31');
        $this->assertSame(30000, $r['users'][0]['total_commission']);
    }

    public function test_connector_uses_connector_rate(): void
    {
        $product = $this->product(false, [[0, 100000000, 'percent', 1.0, 'percent', 2.0]]);
        $conn = $this->user('connector');
        $this->loan($product, $conn, [[6000000]]);

        $r = $this->svc->previewRun('2026-10-01', '2026-10-31');
        $this->assertSame('connector', $r['users'][0]['role_context']);
        $this->assertSame(120000, $r['users'][0]['total_commission']); // 60L × 2%
    }

    public function test_finalize_stamps_coverage_and_is_idempotent(): void
    {
        $product = $this->product(false, [$this->pct(0, 100000000, 1.0)]);
        $a = $this->user();
        $loan = $this->loan($product, $a, [[6000000]], insurance: 25000);
        $actor = $this->user('super_admin');

        $run = $this->svc->finalizeRun('2026-10-01', '2026-10-31', $actor);

        $this->assertSame(60000, (int) $run->total_gross); // insurance is info only
        $this->assertCount(1, $run->userTotals);
        // Tranche + insurance stamped paid.
        $entry = $loan->disbursementEntries()->first();
        $this->assertSame($run->id, $entry->payout_run_id);
        $this->assertSame(6000000, $entry->paid_amount_counted);
        $this->assertSame($run->id, $loan->disbursement->fresh()->insurance_payout_run_id);

        // Re-running the same range finds nothing unpaid.
        $preview2 = $this->svc->previewRun('2026-10-01', '2026-10-31');
        $this->assertFalse($preview2['can_finalize']);
        $this->assertEmpty($preview2['lines']);
    }

    public function test_out_of_range_tranches_excluded(): void
    {
        $product = $this->product(false, [$this->pct(0, 100000000, 1.0)]);
        $a = $this->user();
        $this->loan($product, $a, [[6000000, '2026-09-20']]); // before the range

        $r = $this->svc->previewRun('2026-10-01', '2026-10-31');
        $this->assertFalse($r['can_finalize']);
    }
}
