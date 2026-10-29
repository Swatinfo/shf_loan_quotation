<?php

namespace Tests\Feature;

use App\Models\DisbursementDetail;
use App\Models\ProductPayoutSlab;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payout helpers shared by the Payout Run and reconciliation: the breakdown
 * (no GST maths, insurance info-only, TDS on the total), the one-time base rules
 * and the rate utilities.
 * The finalize/preview engine now lives in PayoutRunService — see
 * {@see PayoutRunServiceTest}.
 */
class PayoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PayoutService
    {
        return app(PayoutService::class);
    }

    /** @return array{pf_gst:float, admin_gst:float, tds:float, insurance:float} */
    private function rates(): array
    {
        return ['pf_gst' => 0.18, 'admin_gst' => 0.18, 'tds' => 0.05, 'insurance' => 0.02];
    }

    public function test_breakdown_has_no_gst_and_insurance_is_info_only(): void
    {
        // commission 60,000; insurance 2L. No GST maths; insurance not added.
        $bd = $this->service()->breakdown(60000, 200000, $this->rates());

        $this->assertSame(60000, $bd['commission']);
        $this->assertSame(200000, $bd['insurance_base']);
        $this->assertSame(4000, $bd['insurance_payout']); // 2L × 2% — shown only
        $this->assertSame(60000, $bd['gross']);           // total = commission only
        $this->assertSame(3000, $bd['tds']);              // 5% of the final total
        $this->assertSame(57000, $bd['net']);
    }

    public function test_breakdown_floors_at_zero(): void
    {
        $bd = $this->service()->breakdown(-500, 0, $this->rates());

        $this->assertSame(0, $bd['gross']);
        $this->assertSame(0, $bd['tds']);
        $this->assertSame(0, $bd['net']);
    }

    public function test_one_time_charges_use_entered_amounts_and_pf_base_excludes_gst(): void
    {
        $header = new DisbursementDetail(['pf_amount' => 11800, 'admin_charges' => 2360]);

        // Normal product: PF + admin exactly as entered (GST-inclusive, no maths).
        $this->assertSame(14160, PayoutService::oneTimeChargesBase($header));
        // PF-based: PF entered brought back to excluding GST at the PF GST rate.
        $this->assertSame(10000, PayoutService::pfBase($header, 0.18));
    }

    public function test_compute_amount_percent_amount_and_cap(): void
    {
        $svc = $this->service();

        // Percent: 1% of 60L = 60,000.
        $this->assertSame(60000, $svc->computeAmount(ProductPayoutSlab::TYPE_PERCENT, 1.0, 6000000, null));
        // Fixed amount.
        $this->assertSame(50000, $svc->computeAmount(ProductPayoutSlab::TYPE_AMOUNT, 50000, 6000000, null));
        // Cap applies.
        $this->assertSame(30000, $svc->computeAmount(ProductPayoutSlab::TYPE_AMOUNT, 50000, 6000000, 30000));
        // Negative cap (-1 → uncapped sentinel handled upstream): cap <= 0 is ignored.
        $this->assertSame(60000, $svc->computeAmount(ProductPayoutSlab::TYPE_PERCENT, 1.0, 6000000, -1));
    }

    public function test_payout_rates_resolve_seeded_defaults(): void
    {
        $r = $this->service()->payoutRates();

        $this->assertEqualsWithDelta(0.18, $r['pf_gst'], 0.0001);
        $this->assertEqualsWithDelta(0.05, $r['tds'], 0.0001);
        $this->assertEqualsWithDelta(0.02, $r['insurance'], 0.0001);
        $this->assertArrayHasKey('admin_gst', $r);
    }
}
