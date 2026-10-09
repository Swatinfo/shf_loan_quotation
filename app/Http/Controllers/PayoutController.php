<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanPayout;
use App\Models\Product;
use App\Services\PayoutConfigService;
use App\Services\PayoutService;
use App\Services\XlsxExportService;
use App\Services\XlsxImportService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    /** The bank-statement columns we export/import for reconciliation. */
    private const COLUMNS = ['loan_acc_no', 'customer_name', 'cheque_number', 'disbursement_date', 'loan_amount', 'pf_amount', 'admin_charges'];

    /**
     * D6 — user-wise payout report (finalized loan_payouts), grouped by cycle.
     */
    public function report(Request $request)
    {
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfMonth();

        $query = LoanPayout::query()
            ->with(['payoutUser', 'loan.bank', 'loan.product'])
            ->whereBetween('finalized_at', [$from, $to]);

        if ($request->filled('user_id')) {
            $query->where('payout_user_id', $request->user_id);
        }
        if ($request->filled('bank_id')) {
            $query->whereHas('loan', fn ($q) => $q->where('bank_id', $request->bank_id));
        }
        if ($request->filled('product_id')) {
            $query->whereHas('loan', fn ($q) => $q->where('product_id', $request->product_id));
        }

        $payouts = $query->orderByDesc('finalized_at')->get();
        $byUser = $payouts->groupBy('payout_user_id')->map(fn ($g) => [
            'name' => $g->first()->payoutUser?->name ?? '—',
            'count' => $g->count(),
            'total' => (int) $g->sum('net_payout_amount'),
        ])->sortByDesc('total')->values();

        if ($request->get('export') === 'xlsx') {
            return $this->exportReport($payouts, $from, $to);
        }

        $banks = Bank::active()->orderBy('name')->get();

        return view('newtheme.payouts.report', [
            'payouts' => $payouts,
            'byUser' => $byUser,
            'total' => (int) $payouts->sum('net_payout_amount'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'banks' => $banks,
            'filters' => $request->only(['bank_id', 'product_id', 'user_id']),
            'pageKey' => 'reports',
        ]);
    }

    /**
     * E — reconciliation screen: pick bank + date range + upload the bank's
     * filled statement; matches our disbursed entries against it.
     */
    public function reconcileForm(Request $request)
    {
        return view('newtheme.payouts.reconcile', [
            'banks' => Bank::active()->orderBy('name')->get(),
            'result' => null,
            'input' => $request->only(['bank_id', 'from', 'to']),
            'canFinalizePayout' => $request->user()->hasPermission('finalize_payout'),
            'pageKey' => 'reports',
        ]);
    }

    /**
     * Download a blank Excel template — just the bank-statement column headers
     * so the user can paste the bank's statement into the expected format. No
     * bank or date range needed; this is purely the format.
     */
    public function reconcileTemplate(Request $request)
    {
        return app(XlsxExportService::class)->download(
            'payout-reconciliation-template.xlsx',
            self::COLUMNS,
            [], // headers only — blank format
            [4 => XlsxExportService::TYPE_NUMBER, 5 => XlsxExportService::TYPE_NUMBER, 6 => XlsxExportService::TYPE_NUMBER],
        );
    }

    public function reconcile(Request $request)
    {
        $request->validate([
            'bank_id' => 'required|exists:banks,id',
            'from' => 'required|date',
            'to' => 'required|date',
            'file' => 'required|file|mimes:xlsx|max:10240',
        ]);

        $excelRows = app(XlsxImportService::class)->readAssoc($request->file('file')->getRealPath());
        $our = $this->ourEntries((int) $request->bank_id, $request->from, $request->to);
        $payoutService = app(PayoutService::class);

        // Build match keys for both sides.
        $excelByKey = [];
        foreach ($excelRows as $r) {
            $date = XlsxImportService::excelDate($r['disbursement_date'] ?? null)
                ?? $this->parseDate($r['disbursement_date'] ?? null);
            $key = $this->matchKey($r['loan_acc_no'] ?? '', $r['customer_name'] ?? '', (int) ($r['loan_amount'] ?? 0), $date?->toDateString());
            $excelByKey[$key] = $r + ['_date' => $date?->toDateString()];
        }

        $matched = [];
        $dbOnly = [];
        $totalPayout = 0;
        $seenExcel = [];

        foreach ($our as $e) {
            $key = $this->matchKey($e->loan_account_number ?? '', $e->loan->customer_name ?? '', (int) $e->amount, optional($e->otc_handover_date)->toDateString());
            if (isset($excelByKey[$key])) {
                $seenExcel[$key] = true;
                $matched[] = ['entry' => $e, 'excel' => $excelByKey[$key]];
            } else {
                $dbOnly[] = $e;
            }
        }

        // One-time PF/Admin/Insurance ride the EARLIEST matched tranche of each loan
        // (mirrors how finalize carries them), so summaries never double-count them.
        $carrierId = [];
        foreach (collect($matched)->groupBy(fn ($m) => $m['entry']->loan_id) as $loanId => $ms) {
            $carrier = $ms->sortBy(fn ($m) => (optional($m['entry']->otc_handover_date)->format('Ymd') ?? '99999999')
                .str_pad((string) $m['entry']->id, 10, '0', STR_PAD_LEFT))->first();
            $carrierId[$loanId] = $carrier['entry']->id;
        }

        foreach ($matched as $i => $m) {
            $e = $m['entry'];
            $carry = ($carrierId[$e->loan_id] ?? null) === $e->id;
            $calc = $this->entryCalc($e, $m['excel'], $payoutService, $carry);
            $matched[$i]['calc'] = $calc;
            $totalPayout += $calc['net'];
        }

        $excelOnly = collect($excelByKey)->reject(fn ($r, $k) => isset($seenExcel[$k]))->values();

        // Bottom summaries (from the matched rows): bank + product-wise and user-wise,
        // aggregating the full breakdown (mirrors the upload-Excel PDF).
        $sumCalc = fn ($g, string $k): int => (int) $g->sum(fn ($m) => $m['calc'][$k]);
        $matchedCol = collect($matched);
        $byProduct = $matchedCol
            ->groupBy(fn ($m) => ($m['entry']->loan?->bank?->name ?? '—').'||'.($m['entry']->loan?->product?->name ?? '—'))
            ->map(fn ($g, $k) => [
                'bank' => explode('||', $k)[0],
                'product' => explode('||', $k)[1],
                'count' => $g->count(),
                'loan' => $sumCalc($g, 'base'),
                'pf' => $sumCalc($g, 'pf'),
                'commission' => $sumCalc($g, 'commission'),
                'insurance' => $sumCalc($g, 'insurance'),
                'insurance_payout' => $sumCalc($g, 'insurance_payout'),
                'gst' => $sumCalc($g, 'gst'),
                'gross' => $sumCalc($g, 'gross'),
                'tds' => $sumCalc($g, 'tds'),
                'total' => $sumCalc($g, 'net'),
            ])->sortByDesc('total')->values();
        $byUser = $matchedCol
            ->groupBy(fn ($m) => $m['entry']->loan?->payoutUser?->name ?? '— (no payout user)')
            ->map(fn ($g, $k) => [
                'user' => $k,
                'count' => $g->count(),
                'loan' => $sumCalc($g, 'base'),
                'pf' => $sumCalc($g, 'pf'),
                'commission' => $sumCalc($g, 'commission'),
                'insurance' => $sumCalc($g, 'insurance'),
                'insurance_payout' => $sumCalc($g, 'insurance_payout'),
                'gst' => $sumCalc($g, 'gst'),
                'gross' => $sumCalc($g, 'gross'),
                'tds' => $sumCalc($g, 'tds'),
                'total' => $sumCalc($g, 'net'),
            ])->sortByDesc('total')->values();

        return view('newtheme.payouts.reconcile', [
            'banks' => Bank::active()->orderBy('name')->get(),
            'input' => $request->only(['bank_id', 'from', 'to']),
            'result' => [
                'matched' => $matched,
                'db_only' => $dbOnly,
                'excel_only' => $excelOnly,
                'total_payout' => $totalPayout,
                'by_product' => $byProduct,
                'by_user' => $byUser,
            ],
            'canFinalizePayout' => $request->user()->hasPermission('finalize_payout'),
            'pageKey' => 'reports',
        ]);
    }

    /**
     * Our active disbursement entries for a bank + date range.
     */
    private function ourEntries(int $bankId, string $from, string $to)
    {
        return DisbursementEntry::query()
            ->with([
                'loan:id,customer_name,bank_id,product_id,payout_user_id',
                'loan.bank:id,name', 'loan.product:id,name', 'loan.payoutUser:id,name', 'loan.disbursement',
            ])
            ->whereHas('loan', fn ($q) => $q->where('bank_id', $bankId))
            ->where('is_active', true)
            // Settlement-date basis: match our settled tranches (otc_handover_date =
            // NEFT transfer date / cheque cleared date) against the bank statement.
            ->whereBetween('otc_handover_date', [Carbon::parse($from)->toDateString(), Carbon::parse($to)->toDateString()])
            ->get();
    }

    /**
     * Match key: loan_acc_no + amount + date, falling back to customer_name +
     * amount + date for placeholder accounts (all-zero account numbers).
     */
    private function matchKey(string $acc, string $name, int $amount, ?string $date): string
    {
        $accDigits = preg_replace('/\D/', '', $acc);
        $isPlaceholder = $acc === '' || ($accDigits !== '' && (int) $accDigits === 0);

        $head = $isPlaceholder
            ? 'NAME:'.preg_replace('/\s+/', ' ', strtoupper(trim($name)))
            : 'ACC:'.strtoupper(trim($acc));

        return $head.'|'.$amount.'|'.($date ?? '');
    }

    /**
     * Full payout breakdown for one matched entry (for the reconcile table +
     * summaries). Commission is the slab rate on the tranche's disbursed amount
     * (or, for a PF-based product, on the loan's one-time PF base). The one-time
     * PF/Admin/Insurance only ride the loan's carrier tranche ($carryOneTime).
     * Zeros + ok=false when no product/version/slab resolves.
     *
     * @param  array<string, mixed>  $excel
     * @return array{ok:bool, base:int, rate_type:?string, rate_value:float, cap:?int, commission:int, pf:int, admin:int, insurance:int, insurance_payout:int, gst:int, gross:int, tds:int, net:int}
     */
    private function entryCalc(DisbursementEntry $e, array $excel, PayoutService $svc, bool $carryOneTime): array
    {
        $loan = $e->loan;
        $header = $loan?->disbursement;
        $pfIncl = $carryOneTime ? (int) ($header->pf_amount ?? 0) : 0;
        $adminIncl = $carryOneTime ? (int) ($header->admin_charges ?? 0) : 0;
        $insurance = $carryOneTime ? (int) ($header->insurance_amount ?? 0) : 0;

        $zero = [
            'ok' => false, 'base' => 0, 'rate_type' => null, 'rate_value' => 0.0, 'cap' => null,
            'commission' => 0, 'pf' => $pfIncl, 'admin' => $adminIncl,
            'insurance' => $insurance, 'insurance_payout' => 0, 'gst' => 0,
            'gross' => 0, 'tds' => 0, 'net' => 0,
        ];

        $product = $loan?->product ?? ($e->product_id ? Product::find($e->product_id) : null);
        if (! $product) {
            return $zero;
        }

        // Resolve the config version + rates in force on this tranche's disbursement date.
        $date = $e->otc_handover_date ? CarbonImmutable::parse($e->otc_handover_date) : CarbonImmutable::now();
        $cfg = app(PayoutConfigService::class);
        $version = $cfg->productVersionAsOf($product, $date);
        if (! $version) {
            return $zero;
        }
        $rates = $cfg->ratesAsOf($date);

        // PF-based → commission on the loan's one-time PF base (carrier row only);
        // otherwise on this tranche's disbursed amount.
        $base = $version->is_pf_based
            ? ($carryOneTime ? DisbursementDetail::exGst($pfIncl, $rates['pf_gst']) : 0)
            : (int) $e->amount;

        if ($base <= 0) {
            // No commission base for this row; one-time parts (if carried) still apply.
            $bd = $svc->breakdown(0, $pfIncl, $adminIncl, $insurance, $rates);

            return [
                'ok' => true, 'base' => 0, 'rate_type' => null, 'rate_value' => 0.0, 'cap' => null,
                'commission' => 0, 'pf' => $pfIncl, 'admin' => $adminIncl,
                'insurance' => $bd['insurance_base'], 'insurance_payout' => $bd['insurance_payout'],
                'gst' => $bd['gst'], 'gross' => $bd['gross'], 'tds' => $bd['tds'], 'net' => $bd['net'],
            ];
        }

        $slab = $version->payoutSlabs->first(
            fn ($s) => $base >= (int) $s->low_amount && $base <= (int) $s->high_amount
        );
        if (! $slab) {
            return $zero;
        }

        $payoutUser = $loan?->payoutUser()->with('roles')->first();
        $isConnector = $payoutUser?->hasRole('connector') ?? false;
        $rate = $slab->rateFor($isConnector);
        $cap = $version->max_payout_amount ? (int) $version->max_payout_amount : null;

        $commission = $svc->computeAmount($rate['type'], $rate['value'], $base, $cap);
        $bd = $svc->breakdown($commission, $pfIncl, $adminIncl, $insurance, $rates);

        return [
            'ok' => true,
            'base' => $base,
            'rate_type' => $rate['type'],
            'rate_value' => (float) $rate['value'],
            'cap' => $cap,
            'commission' => $bd['commission'],
            'pf' => $pfIncl,
            'admin' => $adminIncl,
            'insurance' => $bd['insurance_base'],
            'insurance_payout' => $bd['insurance_payout'],
            'gst' => $bd['gst'],
            'gross' => $bd['gross'],
            'tds' => $bd['tds'],
            'net' => $bd['net'],
        ];
    }

    private function parseDate(?string $v): ?Carbon
    {
        if (! $v) {
            return null;
        }
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $v);
            } catch (\Throwable $e) {
                // try next
            }
        }

        return null;
    }

    private function exportReport($payouts, Carbon $from, Carbon $to)
    {
        $rows = $payouts->map(fn ($p) => [
            optional($p->finalized_at)->format('d/m/Y'),
            $p->payoutUser?->name ?? '—',
            $p->loan?->loan_number ?? '',
            $p->loan?->bank?->name ?? '',
            $p->loan?->product?->name ?? '',
            $p->basis_amount,
            $p->payout_amount,
            $p->insurance_payout_amount,
            $p->gst_amount,
            $p->tds_amount,
            $p->net_payout_amount,
            optional($p->cycle_start)->format('d/m/Y').'–'.optional($p->cycle_end)->format('d/m/Y'),
        ]);

        return app(XlsxExportService::class)->download(
            'payout-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xlsx',
            ['Date', 'Payout User', 'Loan #', 'Bank', 'Product', 'Basis', 'Commission', 'Insurance', 'GST', 'TDS', 'Net Payout', 'Cycle'],
            $rows,
            array_fill_keys([5, 6, 7, 8, 9, 10], XlsxExportService::TYPE_NUMBER),
        );
    }
}
