<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
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
 *  - AMOUNT-BASED product: product-wide volume picks the slab tier; each payout
 *    user earns their volume × that tier rate (capped by max_payout). Volume =
 *    settled tranche amounts + the loan's one-time PF + admin AS ENTERED (once per
 *    loan, with its first paid tranche) — matching the bank's loan_amount.
 *  - PF-BASED product: excluded from volume; aggregate PF (excluding GST) picks the
 *    slab tier; each user earns their PF × that rate (capped). One-time per loan.
 *    No admin.
 *  - INSURANCE: per loan, insurance × insurance rate — shown for information
 *    only, never added to the payout.
 *  - Per user: total = Σcommission + Σpf payout; net = total − total×tds.
 * max_payout = -1 → uncapped.
 */
class PayoutRunService
{
    public function __construct(private PayoutConfigService $payoutConfig) {}

    /**
     * Compute (without persisting) the payout for a date range.
     *
     * @return array<string, mixed>
     */
    /**
     * @param  array<int,int>  $productIds  Empty = all products of the bank.
     */
    public function previewRun(string $from, string $to, ?int $bankId = null, array $productIds = []): array
    {
        return $this->computeRun($from, $to, $bankId, $productIds);
    }

    /**
     * Finalize a run: persist the snapshot (run + products + lines + per-user totals)
     * and stamp coverage so the tranches / one-time PF+insurance are never paid twice.
     */
    /**
     * @param  array<int,int>  $productIds  Empty = all products of the bank.
     */
    public function finalizeRun(string $from, string $to, User $actor, ?int $bankId = null, array $productIds = []): PayoutRun
    {
        return DB::transaction(function () use ($from, $to, $actor, $bankId, $productIds) {
            $data = $this->computeRun($from, $to, $bankId, $productIds);

            $run = PayoutRun::create([
                'from_date' => $data['from'],
                'to_date' => $data['to'],
                'bank_id' => $data['bank_id'],
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

            // Stamp coverage: tranches (volume) + one-time PF / insurance / normal-product
            // PF+admin charges (per loan).
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
            if (! empty($data['charges_paid_detail_ids'])) {
                DisbursementDetail::whereIn('id', $data['charges_paid_detail_ids'])->update(['charges_payout_run_id' => $run->id]);
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
    /**
     * @param  array<int,int>  $productIds
     */
    private function computeRun(string $from, string $to, ?int $bankId = null, array $productIds = []): array
    {
        $fromDate = CarbonImmutable::parse($from)->startOfDay();
        $toDate = CarbonImmutable::parse($to)->endOfDay();
        $rates = $this->payoutConfig->ratesAsOf($toDate);

        /** @var Collection<int, DisbursementEntry> $entries */
        // Only settled money (a non-null handover = cleared cheque or NEFT; un-cleared
        // cheques are excluded until cleared), but bucket by the TRANSACTION date
        // (NEFT transfer / disbursement date) — see DisbursementEntry::TXN_DATE_SQL —
        // not the internal OTC clearance date.
        // Scoped to one bank (required by the UI) and, optionally, a subset of that
        // bank's products. Empty $productIds = all of the bank's products. The scope
        // only narrows the query — a run never touches tranches outside it, and
        // coverage is per-tranche (payout_run_id), so a later subset run picks up
        // only its own still-uncovered tranches.
        $entries = DisbursementEntry::query()
            ->whereNull('payout_run_id')
            ->where('is_active', true)
            ->whereNotNull('otc_handover_date')
            ->whereRaw(DisbursementEntry::TXN_DATE_SQL.' BETWEEN ? AND ?', [$fromDate->toDateString(), $toDate->toDateString()])
            // Never pay a cancelled / on-hold / rejected loan, regardless of scope.
            ->whereHas('loan', fn ($q2) => $q2->whereNotIn('status', LoanDetail::PAYOUT_EXCLUDED_STATUSES))
            ->when($bankId || $productIds, function ($q) use ($bankId, $productIds) {
                $q->whereHas('loan', function ($q2) use ($bankId, $productIds) {
                    if ($bankId) {
                        $q2->where('bank_id', $bankId);
                    }
                    if ($productIds) {
                        $q2->whereIn('product_id', $productIds);
                    }
                });
            })
            ->with(['loan.product.bank', 'loan.payoutUser.roles', 'loan.disbursement'])
            // Earliest first, so a loan's one-time charges ride its first paid tranche.
            ->orderByRaw(DisbursementEntry::TXN_DATE_SQL)
            ->orderBy('id')
            ->get();

        $errors = [];
        // Accumulators keyed by product_id, and (product_id|user_id).
        $products = [];      // product_id => context
        $userProd = [];      // "pid|uid" => line accumulator
        $coveredEntryCounts = []; // entry_id => amount counted (volume) or 0
        $pfLoansSeen = [];   // loan_id => true (PF counted once)
        $insLoansSeen = [];  // loan_id => true (insurance counted once)
        $chargesLoansSeen = []; // loan_id => true (normal product PF + admin counted once)
        $pfPaidDetailIds = [];
        $insPaidDetailIds = [];
        $chargesPaidDetailIds = [];
        $loanRows = [];      // "pid|uid|loan_id" => per-loan breakdown row

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

            // Per-loan breakdown row (grouped user → product in the UI). Tracks the
            // disbursed amount, the PF (ex-GST) counted, and the insurance — all distinct.
            $loanKey = $pid.'|'.$uid.'|'.$loan->id;
            if (! isset($loanRows[$loanKey])) {
                $loanRows[$loanKey] = [
                    'product_id' => $pid, 'payout_user_id' => $uid, 'loan_id' => $loan->id,
                    'loan_number' => $loan->loan_number, 'application_number' => $loan->application_number,
                    'account_numbers' => [], 'is_pf_based' => (bool) $product->is_pf_based,
                    'volume' => 0, 'charges' => 0, 'pf_base' => 0, 'insurance_base' => 0, 'settlement_date' => null,
                ];
            }
            $loanRows[$loanKey]['volume'] += (int) $e->amount; // disbursed (always)
            $acc = trim((string) $e->loan_account_number);
            if ($acc !== '' && ! in_array($acc, $loanRows[$loanKey]['account_numbers'], true)) {
                $loanRows[$loanKey]['account_numbers'][] = $acc; // distinct A/c #s across tranches
            }
            $sd = optional($e->transactionDate())->toDateString();
            if ($sd && ($loanRows[$loanKey]['settlement_date'] === null || $sd > $loanRows[$loanKey]['settlement_date'])) {
                $loanRows[$loanKey]['settlement_date'] = $sd;
            }

            if (! $product->is_pf_based) {
                // Amount-based: tranche amount feeds the product volume + the user's volume.
                // The loan's one-time PF + admin (as entered) join it once, on the first
                // paid tranche — the bank's loan_amount already includes them.
                $counted = (int) $e->amount;
                if ($header && empty($chargesLoansSeen[$loan->id]) && $header->charges_payout_run_id === null) {
                    $chargesLoansSeen[$loan->id] = true;
                    $charges = PayoutService::oneTimeChargesBase($header);
                    if ($charges > 0) {
                        $counted += $charges;
                        $chargesPaidDetailIds[] = $header->id;
                        $loanRows[$loanKey]['charges'] += $charges;
                    }
                }
                $products[$pid]['volume'] += $counted;
                $userProd[$key]['user_volume'] += $counted;
                $coveredEntryCounts[$e->id] = $counted;
            } else {
                // PF-based: the tranche amount is NOT volume; the loan's one-time PF (ex-GST)
                // feeds the PF aggregate + the user's PF, counted once per loan.
                if ($header && empty($pfLoansSeen[$loan->id]) && $header->pf_payout_run_id === null) {
                    $pfBase = PayoutService::pfBase($header, $rates['pf_gst']);
                    if ($pfBase > 0) {
                        $products[$pid]['pf_aggregate'] += $pfBase;
                        $userProd[$key]['user_pf'] += $pfBase;
                        $pfLoansSeen[$loan->id] = true;
                        $pfPaidDetailIds[] = $header->id;
                        $loanRows[$loanKey]['pf_base'] += $pfBase;
                    }
                }
            }

            // Insurance (any product), one-time per loan — information only.
            if ($header && empty($insLoansSeen[$loan->id]) && $header->insurance_payout_run_id === null && (int) $header->insurance_amount > 0) {
                $userProd[$key]['insurance_base'] += (int) $header->insurance_amount;
                $insLoansSeen[$loan->id] = true;
                $insPaidDetailIds[] = $header->id;
                $loanRows[$loanKey]['insurance_base'] += (int) $header->insurance_amount;
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
            // Insurance payout is shown for information only — not part of the total.
            $insurancePayout = (int) round($up['insurance_base'] * $rates['insurance']);
            $lineTotal = $commission + $pfPayout;

            $lines[] = [
                'product_id' => $pid,
                'payout_user_id' => $up['payout_user_id'],
                'role_context' => $up['is_connector'] ? 'connector' : 'standard',
                'base_amount' => $baseAmount,
                'volume_base' => $pctx['is_pf_based'] ? 0 : $baseAmount, // disbursed amount (amount-based only)
                'rate_applied' => (float) $rate['value'],
                'commission' => $commission,
                'pf_base' => $pfBase,
                'pf_payout' => $pfPayout,
                'insurance_base' => $up['insurance_base'],
                'insurance_payout' => $insurancePayout,
                'line_total' => $lineTotal,
            ];
        }

        // Per-user roll-up + TDS. total_volume/pf/insurance are the underlying
        // BASE amounts (loan amount, PF ex-GST, insurance); the rest are payouts.
        $users = [];
        foreach ($lines as $l) {
            $uid = $l['payout_user_id'];
            if (! isset($users[$uid])) {
                $users[$uid] = [
                    'payout_user_id' => $uid, 'role_context' => $l['role_context'],
                    'total_volume' => 0, 'total_pf_base' => 0, 'total_insurance_base' => 0,
                    'total_commission' => 0, 'total_pf_payout' => 0, 'total_insurance_payout' => 0,
                ];
            }
            $users[$uid]['total_volume'] += $l['volume_base'];
            $users[$uid]['total_pf_base'] += $l['pf_base'];
            $users[$uid]['total_insurance_base'] += $l['insurance_base'];
            $users[$uid]['total_commission'] += $l['commission'];
            $users[$uid]['total_pf_payout'] += $l['pf_payout'];
            $users[$uid]['total_insurance_payout'] += $l['insurance_payout'];
        }
        $gross = 0;
        $tdsTotal = 0;
        $netTotal = 0;
        foreach ($users as $uid => &$u) {
            $total = $u['total_commission'] + $u['total_pf_payout'];
            $tds = (int) round($total * $rates['tds']);
            $u['total_payout'] = $total;
            $u['tds_amount'] = $tds;
            $u['net_payout'] = max(0, $total - $tds);
            $gross += $total;
            $tdsTotal += $tds;
            $netTotal += $u['net_payout'];
        }
        unset($u);

        // Enrich the per-loan rows with the product name + each loan's payout share
        // (base × the user's rate for that product; insurance shown separately, info
        // only). These are gross, pre-cap contributions — the max_payout cap is
        // applied at the user×product line level, not per loan.
        foreach ($loanRows as &$lr) {
            $pctx = $productOut[$lr['product_id']] ?? null;
            $slab = $pctx['slab'] ?? null;
            $isConn = ($userProd[$lr['product_id'].'|'.$lr['payout_user_id']]['is_connector'] ?? false);
            $rate = $slab ? $slab->rateFor($isConn) : ['type' => 'percent', 'value' => 0.0];
            $payoutBase = (int) ($lr['is_pf_based'] ? $lr['pf_base'] : $lr['volume'] + $lr['charges']);
            $lr['product_name'] = $pctx['product_name'] ?? '';
            $lr['rate_applied'] = (float) $rate['value'];
            $lr['payout_base'] = $payoutBase;
            $lr['payout'] = $this->applyRate($rate['type'], (float) $rate['value'], $payoutBase, null);
            $lr['insurance_payout'] = (int) round($lr['insurance_base'] * $rates['insurance']);
        }
        unset($lr);

        return [
            'from' => $fromDate->toDateString(),
            'to' => $toDate->toDateString(),
            'bank_id' => $bankId,
            'product_ids' => array_values($productIds),
            'rates' => $rates,
            'products' => array_values($productOut),
            'lines' => $lines,
            'loan_lines' => array_values($loanRows),
            'users' => array_values($users),
            'totals' => ['gross' => $gross, 'tds' => $tdsTotal, 'net' => $netTotal],
            'errors' => $errors,
            'covered_entry_ids' => array_keys($coveredEntryCounts),
            'covered_entry_counts' => $coveredEntryCounts,
            'pf_paid_detail_ids' => array_values(array_unique($pfPaidDetailIds)),
            'insurance_paid_detail_ids' => array_values(array_unique($insPaidDetailIds)),
            'charges_paid_detail_ids' => array_values(array_unique($chargesPaidDetailIds)),
            'can_finalize' => ! empty($lines),
        ];
    }

    /**
     * First slab whose high_amount covers the base (smallest such), falling back to
     * the top slab when the base exceeds every bracket — matches the legacy engine.
     * Public so bank reconciliation picks the tier exactly like a payout run.
     */
    public function matchSlab($version, int $base): ?ProductPayoutSlab
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
