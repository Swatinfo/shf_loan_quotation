<?php

namespace Tests\Feature;

use App\Models\ProductPayoutSlab;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payout helpers that survive the aggregate redesign: the per-entry net
 * breakdown (used by bank-statement reconciliation) and the rate utilities.
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

    public function test_breakdown_applies_pf_gst_insurance_and_tds(): void
    {
        // commission 60,000; one-time PF 1L (incl GST), insurance 2L; admin 0.
        $bd = $this->service()->breakdown(60000, 100000, 0, 200000, $this->rates());

        $this->assertSame(60000, $bd['commission']);
        // PF 1L incl → base round(100000/1.18)=84746, GST = 15254.
        $this->assertSame(84746, $bd['pf_base']);
        $this->assertSame(15254, $bd['gst']);
        // Insurance 2L × 2% = 4,000 (added).
        $this->assertSame(200000, $bd['insurance_base']);
        $this->assertSame(4000, $bd['insurance_payout']);
        // gross = 60000 + 4000 − 15254 = 48746; tds 5% = 2437; net = 46309.
        $this->assertSame(48746, $bd['gross']);
        $this->assertSame(2437, $bd['tds']);
        $this->assertSame(46309, $bd['net']);
    }

    public function test_breakdown_net_and_tds_floor_at_zero_when_gst_exceeds_gross(): void
    {
        // Tiny commission, large PF GST → negative gross → floored to 0, no TDS.
        $bd = $this->service()->breakdown(1695, 200000, 0, 0, $this->rates());

        $this->assertSame(1695, $bd['commission']);
        $this->assertTrue($bd['gross'] < 0);
        $this->assertSame(0, $bd['tds']);
        $this->assertSame(0, $bd['net']);
    }

    public function test_breakdown_includes_admin_gst_portion(): void
    {
        // Admin 1L incl GST → its GST portion is also deducted.
        $bd = $this->service()->breakdown(50000, 0, 100000, 0, $this->rates());

        $this->assertSame(0, $bd['gst_pf']);
        $this->assertSame(15254, $bd['gst_admin']); // 100000 − round(100000/1.18)
        $this->assertSame(15254, $bd['gst']);
        $this->assertSame(34746, $bd['gross']); // 50000 − 15254
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
