<?php

namespace App\Services;

use App\Models\DisbursementDetail;
use App\Models\ProductPayoutSlab;
use Carbon\CarbonImmutable;

/**
 * Payout helpers shared by the disbursement form (current GST rates) and the
 * bank-statement reconciliation (per-entry verification breakdown). The actual
 * payout computation + finalization now lives in {@see PayoutRunService}
 * (aggregate, product-wide, date-range runs); this class holds only the small
 * rate/breakdown utilities those two screens still need.
 */
class PayoutService
{
    public function __construct(private PayoutConfigService $payoutConfig) {}

    /**
     * The payout-config rate multipliers (decimals) in force on a date (default
     * now), resolved from the version history.
     *
     * @return array{pf_gst: float, admin_gst: float, tds: float, insurance: float}
     */
    public function payoutRates(?CarbonImmutable $date = null): array
    {
        $r = $this->payoutConfig->ratesAsOf($date ?? CarbonImmutable::now());

        return ['pf_gst' => $r['pf_gst'], 'admin_gst' => $r['admin_gst'], 'tds' => $r['tds'], 'insurance' => $r['insurance']];
    }

    /**
     * Net payout breakdown. PF + Admin are ONE-TIME, GST-INCLUSIVE charges; the
     * included GST (back-calculated) is deducted. Insurance (no GST) is paid out
     * at the insurance rate. Pass the one-time amounts only on the finalize that
     * carries them (first finalize); 0 otherwise.
     *   gst_pf    = pf_incl − pf_incl/(1+pf_gst)           pf_base = pf_incl/(1+pf_gst)
     *   gst_admin = admin_incl − admin_incl/(1+admin_gst)
     *   gst       = gst_pf + gst_admin                     (deducted)
     *   insurance = insurance × insurance_rate             (added)
     *   gross     = commission + insurance − gst
     *   tds       = gross × tds_rate                       (deducted, on positive gross)
     *   net       = max(0, gross − tds)
     *
     * @param  array{pf_gst:float, admin_gst:float, tds:float, insurance:float}|null  $rates
     * @return array{commission:int, pf_incl:int, pf_base:int, admin_incl:int, gst:int, gst_pf:int, gst_admin:int, insurance_base:int, insurance_payout:int, gross:int, tds:int, net:int, rates:array}
     */
    public function breakdown(int $commission, int $pfIncl, int $adminIncl, int $insurance, ?array $rates = null): array
    {
        $r = $rates ?? $this->payoutRates();
        $pfBase = DisbursementDetail::exGst($pfIncl, $r['pf_gst']);
        $gstPf = $pfIncl - $pfBase;
        $gstAdmin = DisbursementDetail::gstPortion($adminIncl, $r['admin_gst'] ?? 0);
        $gst = $gstPf + $gstAdmin;
        $insurancePayout = (int) round($insurance * $r['insurance']);
        $gross = $commission + $insurancePayout - $gst;
        // TDS applies only to a positive total; a payout is never negative.
        $tds = (int) round(max(0, $gross) * $r['tds']);
        $net = max(0, $gross - $tds);

        return [
            'commission' => $commission,
            'pf_incl' => $pfIncl,
            'pf_base' => $pfBase,
            'admin_incl' => $adminIncl,
            'gst' => $gst,
            'gst_pf' => $gstPf,
            'gst_admin' => $gstAdmin,
            'insurance_base' => $insurance,
            'insurance_payout' => $insurancePayout,
            'gross' => $gross,
            'tds' => $tds,
            'net' => $net,
            'rates' => $r,
        ];
    }

    /**
     * Apply an amount/percent rate to a base, capped by max payout.
     */
    public function computeAmount(string $type, float $value, int $base, ?int $maxCap): int
    {
        $amount = $type === ProductPayoutSlab::TYPE_PERCENT
            ? (int) round($base * $value / 100)
            : (int) round($value);

        if ($maxCap !== null && $maxCap > 0) {
            $amount = min($amount, $maxCap);
        }

        return max(0, $amount);
    }
}
