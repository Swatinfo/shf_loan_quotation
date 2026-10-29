<?php

namespace App\Services;

use App\Models\DisbursementDetail;
use App\Models\ProductPayoutSlab;
use Carbon\CarbonImmutable;

/**
 * Payout helpers shared by the Payout Run engine ({@see PayoutRunService}), the
 * bank-statement reconciliation and the disbursement form (current GST rates).
 * Both payout screens use the same base rules from here:
 *   - normal product: commission on tranche + one-time PF + admin (as entered)
 *   - PF-based:       commission on PF excluding GST only (no admin)
 *   - insurance:      shown at the configured rate, never added to the payout
 *   - TDS:            on the final total
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
     * One-time charges that join a NORMAL (amount-based) product's commission
     * base: PF + admin exactly as entered (GST-inclusive, no back-calculation) —
     * the bank's `loan_amount` already includes them. PF-based products never
     * add these (they earn on PF only, see pfBase()).
     */
    public static function oneTimeChargesBase(DisbursementDetail $disbursement): int
    {
        return (int) $disbursement->pf_amount + (int) $disbursement->admin_charges;
    }

    /**
     * A PF-based product's commission base: the PF entered (GST-inclusive)
     * brought back to its GST-exclusive figure at the configured PF GST rate —
     * the same figure the bank reports in its `pf_amount` column.
     */
    public static function pfBase(DisbursementDetail $disbursement, float $pfGstRate): int
    {
        return DisbursementDetail::exGst((int) $disbursement->pf_amount, $pfGstRate);
    }

    /**
     * Payout breakdown on a computed commission. No GST maths anywhere:
     *   total     = commission                       (insurance is NOT added)
     *   tds       = total × tds_rate
     *   net       = max(0, total − tds)
     *   insurance = insurance × insurance_rate       (shown for information only)
     *
     * @param  array{pf_gst:float, admin_gst:float, tds:float, insurance:float}|null  $rates
     * @return array{commission:int, insurance_base:int, insurance_payout:int, gross:int, tds:int, net:int, rates:array}
     */
    public function breakdown(int $commission, int $insurance, ?array $rates = null): array
    {
        $r = $rates ?? $this->payoutRates();
        $gross = max(0, $commission);
        $tds = (int) round($gross * $r['tds']);

        return [
            'commission' => $commission,
            'insurance_base' => $insurance,
            'insurance_payout' => (int) round($insurance * $r['insurance']),
            'gross' => $gross,
            'tds' => $tds,
            'net' => max(0, $gross - $tds),
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
