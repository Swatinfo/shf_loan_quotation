<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\PayoutRun;
use App\Models\ProductPayoutSlab;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate payout engine (date-range, volume-tier) — mirrors the legacy
 * UpdateExcelDataAdmin::checkDatabaseData model on the current flat-product schema.
 *
 * For a date range, over tranches not yet covered by a run and dated in range:
 *  - AMOUNT-BASED product: product-wide disbursed volume picks the slab tier; each
 *    payout user earns their disbursed × that tier rate (capped by max_payout).
 *  - PF-BASED product: excluded from volume; aggregate PF (ex-GST) picks the slab
 *    tier; each user earns their PF × that rate (capped). One-time per loan.
 *  - INSURANCE: per loan, insurance × insurance rate, to the loan's payout user.
 *  - Per user: total = Σcommission + Σpf + Σinsurance; net = total − total×tds.
 * No GST deduction; admin charges never contribute. max_payout = -1 → uncapped.
 */
class PayoutRunService
{
    public function __construct(private PayoutConfigService $payoutConfig) {}

    /**
     * Compute (without persisting) the payout for a date range.
     *
     * @return array<string, mixed>
     */
    public function previewRun(string $from, string $to): array
    {
        return $this->computeRun($from, $to);
    }

    /**
     * Finalize a run: persist the snapshot (run + products + lines + per-user totals)
     * and stamp coverage so the tranches / one-time PF+insurance are never paid twice.
     */
    public function finalizeRun(string $from, string $to, User $actor): PayoutRun
    {
        return DB::transaction(function () use ($from, $to, $actor) {
            $data = $this->computeRun($from, $to);

            $run = PayoutRun::create([
                'from_date' => $data['from'],
                'to_date' => $data['to'],
                'status' => PayoutRun::STATUS_FINALIZED,
                'insurance_rate' => $data['rates']['insurance'],
                'tds_rate' => $data['rates']['tds'],
                'gst_rate' => $data['rates']['pf_gst'],
                'total_gross' => $data['totals']['gross'],
                'total_tds' => $data['totals']['tds'],
                'total_net' => $data['totals']['net'],
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
                'created_by' => $actor->id,
            ]);

            // Products (tier context) → remember the created id per product for the lines.
            $productRowId = [];
            foreach ($data['products'] as $p) {
                $row = $run->products()->create([
                    'product_id' => $p['product_id'],
                    'product_name' => $p['product_name'],
                    'bank_id' => $p['bank_id'],
                    'is_pf_based' => $p['is_pf_based'],
                    'aggregate_base' => $p['aggregate_base'],
                    'product_payout_version_id' => $p['version_id'],
                    'slab_id' => $p['slab_id'],
                    'slab_low' => $p['slab_low'],
                    'slab_high' => $p['slab_high'],
                    'tier_rate_type' => $p['rate_type'],
                    'tier_rate' => $p['rate_value'],
                    'connector_tier_rate' => $p['connector_rate_value'],
                    'max_payout' => $p['max_payout'],
                ]);
                $productRowId[$p['product_id']] = $row->id;
            }

            foreach ($data['lines'] as $line) {
                $run->lines()->create([
                    'payout_run_product_id' => $productRowId[$line['product_id']],
                    'payout_user_id' => $line['payout_user_id'],
                    'role_context' => $line['role_context'],
                    'base_amount' => $line['base_amount'],
                    'rate_applied' => $line['rate_applied'],
                    'commission' => $line['commission'],
                    'pf_base' => $line['pf_base'],
                    'pf_payout' => $line['pf_payout'],
                    'insurance_base' => $line['insurance_base'],
                    'insurance_rate' => $data['rates']['insurance'],
                    'insurance_payout' => $line['insurance_payout'],
                    'line_total' => $line['line_total'],
                ]);
            }

            foreach ($data['users'] as $u) {
                $run->userTotals()->create([
                    'payout_user_id' => $u['payout_user_id'],
                    'role_context' => $u['role_context'],
                    'total_commission' => $u['total_commission'],
                    'total_pf_payout' => $u['total_pf_payout'],
                    'total_insurance_payout' => $u['total_insurance_payout'],
                    'total_payout' => $u['total_payout'],
                    'tds_rate' => $data['rates']['tds'],
                    'tds_amount' => $u['tds_amount'],
                    'net_payout' => $u['net_payout'],
                ]);
            }

            // Stamp coverage: tranches (volume) + one-time PF/insurance (per loan).
            if (! empty($data['covered_entry_ids'])) {
                foreach ($data['covered_entry_counts'] as $entryId => $counted) {
                    DisbursementEntry::whereKey($entryId)->update([
                        'payout_run_id' => $run->id,
                        'paid_amount_counted' => $counted,
                    ]);
                }
            }
            if (! empty($data['pf_paid_detail_ids'])) {
                DisbursementDetail::whereIn('id', $data['pf_paid_detail_ids'])->update(['pf_payout_run_id' => $run->id]);
            }
            if (! empty($data['insurance_paid_detail_ids'])) {
                DisbursementDetail::whereIn('id', $data['insurance_paid_detail_ids'])->update(['insurance_payout_run_id' => $run->id]);
            }

            ActivityLog::log('finalize_payout_run', $run, [
                'from' => $data['from'], 'to' => $data['to'],
                'users' => count($data['users']), 'net' => $data['totals']['net'],
            ]);

            return $run->fresh(['products', 'lines', 'userTotals']);
        });
    }

    /**
     * The shared computation. Returns the full structure + the coverage ids that
     * finalizeRun stamps. Pure (no writes).
     *
     * @return array<string, mixed>
     */
    private function computeRun(string $from, string $to): array
    {
        $fromDate = CarbonImmutable::parse($from)->startOfDay();
        $toDate = CarbonImmutable::parse($to)->endOfDay();
        $rates = $this->payoutConfig->ratesAsOf($toDate);

        /** @var Collection<int, DisbursementEntry> $entries */
        // Keyed on the SETTLEMENT date (otc_handover_date): NEFT = transfer date,
        // cheque = cleared date. Un-cleared cheque tranches have a null handover and
        // are excluded until cleared — payout is paid only on settled money.
        $entries = DisbursementEntry::query()
            ->whereNull('payout_run_id')
            ->where('is_active', true)
            ->whereBetween('otc_handover_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->with(['loan.product.bank', 'loan.payoutUser.roles', 'loan.disbursement'])
            ->get();

        $errors = [];
        // Accumulators keyed by product_id, and (product_id|user_id).
        $products = [];      // product_id => context
        $userProd = [];      // "pid|uid" => line accumulator
        $coveredEntryCounts = []; // entry_id => amount counted (volume) or 0
        $pfLoansSeen = [];   // loan_id => true (PF counted once)
        $insLoansSeen = [];  // loan_id => true (insurance counted once)
        $pfPaidDetailIds = [];
        $insPaidDetailIds = [];

        foreach ($entries as $e) {
            $loan = $e->loan;
            $product = $loan?->product;
            $user = $loan?->payoutUser;
            if (! $loan || ! $product) {
                continue;
            }
            if (! $user) {
                $errors[] = "Loan {$loan->loan_number} has no payout user — skipped.";

                continue;
            }

            $pid = $product->id;
            $uid = $user->id;
            $key = $pid.'|'.$uid;
            $isConnector = $user->hasRole('connector');
            $header = $loan->disbursement;

            if (! isset($products[$pid])) {
                $products[$pid] = [
                    'product' => $product,
                    'is_pf_based' => (bool) $product->is_pf_based,
                    'volume' => 0,      // amount-based aggregate
                    'pf_aggregate' => 0, // pf-based aggregate (ex-GST)
                ];
            }
            if (! isset($userProd[$key])) {
                $userProd[$key] = [
                    'product_id' => $pid, 'payout_user_id' => $uid, 'is_connector' => $isConnector,
                    'user_volume' => 0, 'user_pf' => 0, 'insurance_base' => 0,
                ];
            }

            $coveredEntryCounts[$e->id] = 0;

            if (! $product->is_pf_based) {
                // Amount-based: tranche amount feeds the product volume + the user's volume.
                $products[$pid]['volume'] += (int) $e->amount;
                $userProd[$key]['user_volume'] += (int) $e->amount;
                $coveredEntryCounts[$e->id] = (int) $e->amount;
            } else {
                // PF-based: the tranche amount is NOT volume; the loan's one-time PF (ex-GST)
                // feeds the PF aggregate + the user's PF, counted once per loan.
                if ($header && empty($pfLoansSeen[$loan->id]) && $header->pf_payout_run_id === null) {
                    $pfBase = DisbursementDetail::exGst((int) $header->pf_amount, $rates['pf_gst']);
                    if ($pfBase > 0) {
                        $products[$pid]['pf_aggregate'] += $pfBase;
                        $userProd[$key]['user_pf'] += $pfBase;
                        $pfLoansSeen[$loan->id] = true;
                        $pfPaidDetailIds[] = $header->id;
                    }
                }
            }

            // Insurance (any product), one-time per loan, to the loan's payout user.
            if ($header && empty($insLoansSeen[$loan->id]) && $header->insurance_payout_run_id === null && (int) $header->insurance_amount > 0) {
                $userProd[$key]['insurance_base'] += (int) $header->insurance_amount;
                $insLoansSeen[$loan->id] = true;
                $insPaidDetailIds[] = $header->id;
            }
        }

        // Resolve each product's tier (slab) from its aggregate.
        $productOut = [];
        foreach ($products as $pid => $ctx) {
            $product = $ctx['product'];
            $version = $this->payoutConfig->productVersionAsOf($product, $toDate);
            $aggregate = $ctx['is_pf_based'] ? $ctx['pf_aggregate'] : $ctx['volume'];
            $slab = $version ? $this->matchSlab($version, $aggregate) : null;
            if (! $version || ! $slab) {
                $errors[] = "No payout slab/version for product {$product->name} (aggregate ₹ ".inr($aggregate).').';
            }
            $max = $version && $version->max_payout_amount !== null ? (int) $version->max_payout_amount : null;
            $productOut[$pid] = [
                'product_id' => $pid,
                'product_name' => $product->name.($product->bank ? ' — '.$product->bank->name : ''),
                'bank_id' => $product->bank_id,
                'is_pf_based' => $ctx['is_pf_based'],
                'aggregate_base' => $aggregate,
                'version' => $version,
                'version_id' => $version?->id,
                'slab' => $slab,
                'slab_id' => $slab?->id,
                'slab_low' => $slab ? (int) $slab->low_amount : null,
                'slab_high' => $slab ? (int) $slab->high_amount : null,
                'rate_type' => $slab ? $slab->payout_type : 'percent',
                'rate_value' => $slab ? (float) $slab->payout_value : 0.0,
                'connector_rate_value' => $slab ? (float) $slab->connector_payout_value : null,
                'max_payout' => $max,
            ];
        }

        // Build per (user × product) lines.
        $lines = [];
        foreach ($userProd as $key => $up) {
            $pid = $up['product_id'];
            $pctx = $productOut[$pid];
            $slab = $pctx['slab'];
            $rate = $slab ? $slab->rateFor($up['is_connector']) : ['type' => 'percent', 'value' => 0.0];
            $cap = $pctx['max_payout'] !== null && $pctx['max_payout'] > 0 ? $pctx['max_payout'] : null;

            $commission = 0;
            $pfPayout = 0;
            $baseAmount = 0;
            $pfBase = 0;
            if (! $pctx['is_pf_based']) {
                $baseAmount = $up['user_volume'];
                $commission = $this->applyRate($rate['type'], (float) $rate['value'], $baseAmount, $cap);
            } else {
                $pfBase = $up['user_pf'];
                $baseAmount = $pfBase;
                $pfPayout = $this->applyRate($rate['type'], (float) $rate['value'], $pfBase, $cap);
            }
            $insurancePayout = (int) round($up['insurance_base'] * $rates['insurance']);
            $lineTotal = $commission + $pfPayout + $insurancePayout;

            $lines[] = [
                'product_id' => $pid,
                'payout_user_id' => $up['payout_user_id'],
                'role_context' => $up['is_connector'] ? 'connector' : 'standard',
                'base_amount' => $baseAmount,
                'rate_applied' => (float) $rate['value'],
                'commission' => $commission,
                'pf_base' => $pfBase,
                'pf_payout' => $pfPayout,
                'insurance_base' => $up['insurance_base'],
                'insurance_payout' => $insurancePayout,
                'line_total' => $lineTotal,
            ];
        }

        // Per-user roll-up + TDS.
        $users = [];
        foreach ($lines as $l) {
            $uid = $l['payout_user_id'];
            if (! isset($users[$uid])) {
                $users[$uid] = [
                    'payout_user_id' => $uid, 'role_context' => $l['role_context'],
                    'total_commission' => 0, 'total_pf_payout' => 0, 'total_insurance_payout' => 0,
                ];
            }
            $users[$uid]['total_commission'] += $l['commission'];
            $users[$uid]['total_pf_payout'] += $l['pf_payout'];
            $users[$uid]['total_insurance_payout'] += $l['insurance_payout'];
        }
        $gross = 0;
        $tdsTotal = 0;
        $netTotal = 0;
        foreach ($users as $uid => &$u) {
            $total = $u['total_commission'] + $u['total_pf_payout'] + $u['total_insurance_payout'];
            $tds = (int) round($total * $rates['tds']);
            $u['total_payout'] = $total;
            $u['tds_amount'] = $tds;
            $u['net_payout'] = max(0, $total - $tds);
            $gross += $total;
            $tdsTotal += $tds;
            $netTotal += $u['net_payout'];
        }
        unset($u);

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'rates' => $rates,
            'products' => array_values($productOut),
            'lines' => $lines,
            'users' => array_values($users),
            'totals' => ['gross' => $gross, 'tds' => $tdsTotal, 'net' => $netTotal],
            'errors' => $errors,
            'covered_entry_ids' => array_keys($coveredEntryCounts),
            'covered_entry_counts' => $coveredEntryCounts,
            'pf_paid_detail_ids' => array_values(array_unique($pfPaidDetailIds)),
            'insurance_paid_detail_ids' => array_values(array_unique($insPaidDetailIds)),
            'can_finalize' => ! empty($lines),
        ];
    }

    /**
     * First slab whose high_amount covers the base (smallest such), falling back to
     * the top slab when the base exceeds every bracket — matches the legacy engine.
     */
    private function matchSlab($version, int $base): ?ProductPayoutSlab
    {
        $slabs = $version->payoutSlabs;
        if ($slabs->isEmpty()) {
            return null;
        }
        $match = $slabs->filter(fn (ProductPayoutSlab $s) => (int) $s->high_amount >= $base)
            ->sortBy(fn (ProductPayoutSlab $s) => (int) $s->high_amount)
            ->first();

        return $match ?: $slabs->sortByDesc(fn (ProductPayoutSlab $s) => (int) $s->high_amount)->first();
    }

    /** Apply a percent/amount rate to a base, capped by max payout (null = uncapped). */
    private function applyRate(string $type, float $value, int $base, ?int $cap): int
    {
        $amount = $type === ProductPayoutSlab::TYPE_PERCENT
            ? (int) round($base * $value / 100)
            : (int) round($value);
        if ($cap !== null) {
            $amount = min($amount, $cap);
        }

        return max(0, $amount);
    }
}
