<?php

namespace App\Services;

use App\Models\PayoutRateVersion;
use App\Models\Product;
use App\Models\ProductPayoutVersion;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Effective-dated payout config: resolves the global rate + per-product payout
 * version in force on a given date, and writes new versions. The live
 * `payoutConfig` (app_config) and `products.*` columns are kept as denormalized
 * "current" (today's) mirrors so UI defaults and legacy readers keep working.
 */
class PayoutConfigService
{
    public function __construct(private ConfigService $config) {}

    /**
     * Rate multipliers (calc decimals) in force on $date, resolved from the
     * version history (greatest effective_from on/before $date; else earliest).
     *
     * @return array{admin_gst: float, pf_gst: float, tds: float, insurance: float}
     */
    public function ratesAsOf(CarbonInterface $date): array
    {
        $out = ['admin_gst' => 0.0, 'pf_gst' => 0.0, 'tds' => 0.0, 'insurance' => 0.0];
        $map = ['admin_gst' => 'admin_gst', 'pf_gst' => 'pf_gst', 'user_tds' => 'tds', 'user_insurance' => 'insurance'];

        foreach ($map as $key => $out_key) {
            $v = PayoutRateVersion::where('rate_key', $key)
                ->whereDate('effective_from', '<=', $date->toDateString())
                ->orderByDesc('effective_from')->first()
                ?? PayoutRateVersion::where('rate_key', $key)->orderBy('effective_from')->first();
            $out[$out_key] = $v ? (float) $v->calc : 0.0;
        }

        return $out;
    }

    /**
     * The product payout version in force on $date (with slabs eager-loaded).
     */
    public function productVersionAsOf(Product $product, CarbonInterface $date): ?ProductPayoutVersion
    {
        return $product->payoutVersions()
            ->with('payoutSlabs')
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->orderByDesc('effective_from')->first()
            ?? $product->payoutVersions()->with('payoutSlabs')->orderBy('effective_from')->first();
    }

    /**
     * Upsert the global-rate versions (one row per key+date) and refresh the
     * payoutConfig "current" mirror (today's active version per key).
     *
     * @param  array<string, array{value: float, effective_from: ?string}>  $rates
     */
    public function saveRateVersions(array $rates, ?int $actorId = null): void
    {
        foreach ($rates as $key => $row) {
            if (! in_array($key, PayoutRateVersion::KEYS, true)) {
                continue;
            }
            $value = round((float) ($row['value'] ?? 0), 2);
            $effective = $row['effective_from'] ?: now()->toDateString();

            PayoutRateVersion::updateOrCreate(
                ['rate_key' => $key, 'effective_from' => $effective],
                ['value' => $value, 'calc' => round($value / 100, 4), 'created_by' => $actorId],
            );
        }

        $this->refreshRateMirror();
    }

    /**
     * Create/update a product payout version (keyed by product+date) and replace
     * its slabs, then refresh the product's "current" mirror columns.
     *
     * @param  array{is_pf_based: bool, max_payout_amount: ?float, payout_cycle_start_day: int, payout_cycle_end_day: int}  $fields
     * @param  array<int, array<string, mixed>>  $slabs
     */
    public function saveProductVersion(Product $product, array $fields, array $slabs, ?string $effectiveFrom, ?int $actorId = null): ProductPayoutVersion
    {
        $effective = $effectiveFrom ?: now()->toDateString();

        $version = ProductPayoutVersion::updateOrCreate(
            ['product_id' => $product->id, 'effective_from' => $effective],
            $fields + ['created_by' => $actorId],
        );

        $version->payoutSlabs()->delete();
        foreach ($slabs as $slab) {
            $version->payoutSlabs()->create($slab + ['product_id' => $product->id]);
        }

        $this->refreshProductMirror($product);

        return $version;
    }

    /**
     * Copy today's active rate versions back onto the payoutConfig mirror.
     */
    public function refreshRateMirror(): void
    {
        $today = Carbon::now();
        $rates = $this->ratesAsOf($today);
        $effBy = [];
        foreach (PayoutRateVersion::KEYS as $key) {
            $v = PayoutRateVersion::where('rate_key', $key)
                ->whereDate('effective_from', '<=', $today->toDateString())
                ->orderByDesc('effective_from')->first();
            $effBy[$key] = $v?->effective_from?->toDateString();
        }

        $payout = [
            'admin_gst' => ['value' => round($rates['admin_gst'] * 100, 2), 'calc' => $rates['admin_gst'], 'effective_from' => $effBy['admin_gst']],
            'pf_gst' => ['value' => round($rates['pf_gst'] * 100, 2), 'calc' => $rates['pf_gst'], 'effective_from' => $effBy['pf_gst']],
            'user_tds' => ['value' => round($rates['tds'] * 100, 2), 'calc' => $rates['tds'], 'effective_from' => $effBy['user_tds']],
            'user_insurance' => ['value' => round($rates['insurance'] * 100, 2), 'calc' => $rates['insurance'], 'effective_from' => $effBy['user_insurance']],
        ];

        $this->config->updateSection('payoutConfig', $payout);
    }

    /**
     * Copy the product's active (today's) version onto its mirror columns.
     */
    public function refreshProductMirror(Product $product): void
    {
        $current = $this->productVersionAsOf($product, Carbon::now());
        if (! $current) {
            return;
        }

        $product->update([
            'is_pf_based' => $current->is_pf_based,
            'max_payout_amount' => $current->max_payout_amount,
            'payout_cycle_start_day' => $current->payout_cycle_start_day,
            'payout_cycle_end_day' => $current->payout_cycle_end_day,
            'current_payout_version_id' => $current->id,
        ]);
    }
}
