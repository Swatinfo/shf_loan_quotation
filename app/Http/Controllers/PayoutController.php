<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\DisbursementDetail;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use App\Models\LoanPayout;
use App\Models\Product;
use App\Services\PayoutConfigService;
use App\Services\PayoutService;
use App\Services\XlsxExportService;
use App\Services\XlsxImportService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PayoutController extends Controller
{
    /**
     * The bank-statement columns we export/import for reconciliation. `loan_acc_no`
     * and `application_number` are BOTH identifiers — a row matches our loan on
     * either one (plus a customer-name fallback), so the bank can fill whichever
     * their statement carries (or both).
     */
    private const COLUMNS = ['application_number', 'loan_acc_no', 'customer_name', 'cheque_number', 'disbursement_date', 'loan_amount', 'pf_amount', 'admin_charges', 'insurance_amount'];

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
            'products' => Product::active()->orderBy('name')->get(['id', 'name', 'bank_id']),
            'result' => null,
            'input' => $request->only(['bank_id', 'from', 'to', 'product_ids']),
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
            [5 => XlsxExportService::TYPE_NUMBER, 6 => XlsxExportService::TYPE_NUMBER, 7 => XlsxExportService::TYPE_NUMBER, 8 => XlsxExportService::TYPE_NUMBER],
        );
    }

    public function reconcile(Request $request)
    {
        $request->validate([
            'bank_id' => 'required|exists:banks,id',
            'from' => 'required|date',
            'to' => 'required|date',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
            'file' => 'required|file|mimes:xlsx|max:10240',
        ]);

        $productIds = array_map('intval', $request->input('product_ids', []));
        $excelRows = app(XlsxImportService::class)->readAssoc($request->file('file')->getRealPath());
        $our = $this->ourEntries((int) $request->bank_id, $request->from, $request->to, $productIds);
        $payoutService = app(PayoutService::class);

        // ---- Uploaded rows: the bank files ONE row per loan. Index each by its
        // identifier (A/c or application number) and by customer name. Dates may
        // arrive in many formats (dd-mm-yyyy, dd/mm/yyyy, dd.mm.yyyy, dd,mm,yyyy,
        // d-m-yyyy, Excel serials); parseFlexibleDate normalizes them and flags
        // any it cannot read.
        $excel = [];
        $accIndex = [];   // normalized identifier => excel row index
        $nameIndex = [];  // normalized customer name => excel row index
        foreach ($excelRows as $idx => $r) {
            [$date, $dateError] = $this->parseFlexibleDate($r['disbursement_date'] ?? null);
            $ins = $r['insurance_amount'] ?? null;
            $excel[$idx] = $r + [
                '_date' => $date?->toDateString(),
                '_date_error' => $dateError,
                '_raw_date' => (string) ($r['disbursement_date'] ?? ''),
                '_amount' => (int) round((float) ($r['loan_amount'] ?? 0)),
                '_insurance' => ($ins !== null && $ins !== '' && is_numeric($ins)) ? (int) round((float) $ins) : null,
            ];
            // Index the row under BOTH its A/c number and its application number, so
            // our loan matches on either identifier.
            foreach ([$this->normId($r['loan_acc_no'] ?? ''), $this->normId($r['application_number'] ?? '')] as $id) {
                if ($id !== '' && ! isset($accIndex[$id])) {
                    $accIndex[$id] = $idx;
                }
            }
            $nm = $this->normName($r['customer_name'] ?? '');
            if ($nm !== '' && ! isset($nameIndex[$nm])) {
                $nameIndex[$nm] = $idx;
            }
        }

        // ---- Group our settled tranches by loan (one group per loan, to line up
        // with the bank's one-row-per-loan statement).
        $groups = [];
        foreach ($our as $e) {
            $lid = $e->loan_id;
            if (! isset($groups[$lid])) {
                $groups[$lid] = ['loan' => $e->loan, 'entries' => collect(), 'accounts' => []];
            }
            $groups[$lid]['entries']->push($e);
            $acc = trim((string) $e->loan_account_number);
            if ($acc !== '' && ! in_array($acc, $groups[$lid]['accounts'], true)) {
                $groups[$lid]['accounts'][] = $acc;
            }
        }

        // ---- Match each loan to an uploaded row by IDENTIFIER ONLY:
        // A/c # → application # → customer name. Amount/date are compared after
        // the match (shown side-by-side, flagged on mismatch), never used to match.
        $matched = [];
        $dbOnly = [];
        $consumed = [];
        foreach ($groups as $g) {
            $loan = $g['loan'];
            $idx = null;
            foreach ($g['accounts'] as $a) {
                $k = $this->normId($a);
                if ($k !== '' && isset($accIndex[$k]) && ! isset($consumed[$accIndex[$k]])) {
                    $idx = $accIndex[$k];
                    break;
                }
            }
            if ($idx === null && $loan?->application_number) {
                $k = $this->normId($loan->application_number);
                if ($k !== '' && isset($accIndex[$k]) && ! isset($consumed[$accIndex[$k]])) {
                    $idx = $accIndex[$k];
                }
            }
            if ($idx === null && $loan?->customer_name) {
                $k = $this->normName($loan->customer_name);
                if ($k !== '' && isset($nameIndex[$k]) && ! isset($consumed[$nameIndex[$k]])) {
                    $idx = $nameIndex[$k];
                }
            }

            if ($idx === null) {
                $dbOnly[] = $this->loanSide($g);

                continue;
            }
            $consumed[$idx] = true;
            $matched[] = ['group' => $g, 'excel_idx' => $idx];
        }

        // ---- Build each matched row: loan-level payout + both-sides comparison.
        $totalPayout = 0;
        $discrepancyCount = 0;
        $dateErrorCount = 0;
        foreach ($matched as $i => $m) {
            $g = $m['group'];
            $ex = $excel[$m['excel_idx']];
            $ourSide = $this->loanSide($g);
            $calc = $this->loanCalc($g['entries'], $payoutService, $ex['_insurance']);

            $amountFlag = $ourSide['amount'] !== $ex['_amount'];
            // Date mismatch when unreadable, or the bank's date is not one of our
            // tranche settlement dates.
            $dateFlag = $ex['_date_error']
                || ($ex['_date'] !== null && ! in_array($ex['_date'], $ourSide['settlement_dates'], true));
            $insFlag = $ex['_insurance'] !== null && $ex['_insurance'] !== $ourSide['insurance'];
            $hasDisc = $amountFlag || $dateFlag || $insFlag;

            if ($hasDisc) {
                $discrepancyCount++;
            }
            if ($ex['_date_error']) {
                $dateErrorCount++;
            }
            $totalPayout += $calc['net'];

            $matched[$i] += [
                'loan' => $g['loan'],
                'our' => $ourSide,
                'excel' => $ex,
                'calc' => $calc,
                'flags' => ['amount' => $amountFlag, 'date' => $dateFlag, 'insurance' => $insFlag],
                'has_discrepancy' => $hasDisc,
            ];
        }

        $unmatchedExcel = collect($excel)->reject(fn ($r, $i) => isset($consumed[$i]))->values();

        // "In Excel, not in DB" must mean the record truly does NOT exist. A bank
        // row that didn't match an in-window loan but whose identifier DOES exist in
        // our DB (same bank + products, any date) is a data/period mismatch — e.g.
        // settled outside the selected window, or different amount/date — so we can't
        // pay it, but it is NOT "missing". Surface those separately.
        $dbIndex = $this->dbIdentifierIndex((int) $request->bank_id, $productIds);
        $matchedLoanIds = collect($matched)->map(fn ($m) => $m['loan']?->id)->filter()->all();
        $fromStr = Carbon::parse($request->from)->toDateString();
        $toStr = Carbon::parse($request->to)->toDateString();
        $excelInDb = [];
        $excelOnly = [];
        foreach ($unmatchedExcel as $r) {
            $acc = $this->normId($r['loan_acc_no'] ?? '');
            $app = $this->normId($r['application_number'] ?? '');
            $nm = $this->normName($r['customer_name'] ?? '');
            // Does this row exist in our DB by A/c number, application number, or name?
            $hit = ($acc !== '' && isset($dbIndex['acc'][$acc])) ? $dbIndex['acc'][$acc]
                : (($app !== '' && isset($dbIndex['acc'][$app])) ? $dbIndex['acc'][$app]
                : (($nm !== '' && isset($dbIndex['name'][$nm])) ? $dbIndex['name'][$nm] : null));
            if ($hit) {
                $rc = $this->mismatchReason($hit, $matchedLoanIds, $fromStr, $toStr);
                $excelInDb[] = ['excel' => $r, 'loan' => $hit, 'reason' => $rc['reason'], 'category' => $rc['category']];
            } else {
                $excelOnly[] = $r;
            }
        }
        $excelOnly = collect($excelOnly)->values();
        $dateErrorCount += $excelOnly->filter(fn ($r) => $r['_date_error'])->count()
            + collect($excelInDb)->filter(fn ($m) => $m['excel']['_date_error'])->count();

        // Bottom summaries (from the matched rows): bank + product-wise and user-wise,
        // aggregating the full breakdown (mirrors the upload-Excel PDF).
        $sumCalc = fn ($g, string $k): int => (int) $g->sum(fn ($m) => $m['calc'][$k]);
        $matchedCol = collect($matched);
        $byProduct = $matchedCol
            ->groupBy(fn ($m) => ($m['loan']?->bank?->name ?? '—').'||'.($m['loan']?->product?->name ?? '—'))
            ->map(fn ($g, $k) => [
                'bank' => explode('||', $k)[0],
                'product' => explode('||', $k)[1],
                'count' => $g->count(),
                'loan' => $sumCalc($g, 'base'),
                'pf' => $sumCalc($g, 'pf'),
                'admin' => $sumCalc($g, 'admin'),
                'commission' => $sumCalc($g, 'commission'),
                'insurance' => $sumCalc($g, 'insurance'),
                'insurance_payout' => $sumCalc($g, 'insurance_payout'),
                'gst' => $sumCalc($g, 'gst'),
                'gross' => $sumCalc($g, 'gross'),
                'tds' => $sumCalc($g, 'tds'),
                'total' => $sumCalc($g, 'net'),
            ])->sortByDesc('total')->values();
        $byUser = $matchedCol
            ->groupBy(fn ($m) => $m['loan']?->payoutUser?->name ?? '— (no payout user)')
            ->map(fn ($g, $k) => [
                'user' => $k,
                'count' => $g->count(),
                'loan' => $sumCalc($g, 'base'),
                'pf' => $sumCalc($g, 'pf'),
                'admin' => $sumCalc($g, 'admin'),
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
            'products' => Product::active()->orderBy('name')->get(['id', 'name', 'bank_id']),
            'input' => $request->only(['bank_id', 'from', 'to', 'product_ids']),
            'result' => [
                'matched' => $matched,
                'db_only' => $dbOnly,
                'excel_only' => $excelOnly,
                'excel_in_db' => $excelInDb,
                'total_payout' => $totalPayout,
                'by_product' => $byProduct,
                'by_user' => $byUser,
                'discrepancy_count' => $discrepancyCount,
                'date_error_count' => $dateErrorCount,
            ],
            'canFinalizePayout' => $request->user()->hasPermission('finalize_payout'),
            'pageKey' => 'reports',
        ]);
    }

    /**
     * Our active disbursement entries for a bank + date range.
     */
    /**
     * @param  array<int,int>  $productIds  Empty = all products of the bank.
     */
    private function ourEntries(int $bankId, string $from, string $to, array $productIds = [])
    {
        return DisbursementEntry::query()
            ->with([
                'loan:id,loan_number,application_number,customer_name,bank_id,product_id,payout_user_id',
                'loan.bank:id,name', 'loan.product:id,name', 'loan.payoutUser:id,name', 'loan.disbursement',
            ])
            ->whereHas('loan', function ($q) use ($bankId, $productIds) {
                $q->where('bank_id', $bankId)
                    // Never pay (or match) a cancelled / on-hold / rejected loan.
                    ->whereNotIn('status', LoanDetail::PAYOUT_EXCLUDED_STATUSES);
                if ($productIds) {
                    $q->whereIn('product_id', $productIds);
                }
            })
            ->where('is_active', true)
            // Only settled money (a non-null handover = cleared cheque or NEFT), but
            // bucket by the TRANSACTION date (NEFT transfer / disbursement date) — that's
            // what the bank statement reports, not our internal OTC clearance date.
            ->whereNotNull('otc_handover_date')
            ->whereRaw(DisbursementEntry::TXN_DATE_SQL.' BETWEEN ? AND ?', [Carbon::parse($from)->toDateString(), Carbon::parse($to)->toDateString()])
            ->get();
    }

    /**
     * Identifier → loan-info index over ALL our active tranches for a bank (+ optional
     * products), ANY date. Used to tell "truly not in our DB" apart from "exists but
     * didn't match this period". Keyed (normalized) by A/c number + application number
     * (→ 'acc') and by customer name (→ 'name').
     *
     * @param  array<int,int>  $productIds
     * @return array{acc: array<string, array<string,mixed>>, name: array<string, array<string,mixed>>}
     */
    private function dbIdentifierIndex(int $bankId, array $productIds): array
    {
        // ALL entries (active or not) so a cancelled / on-hold loan — whose tranches
        // may have been deactivated — is still recognised as "exists in our DB".
        $entries = DisbursementEntry::query()
            ->with(['loan:id,loan_number,application_number,customer_name,bank_id,product_id,status', 'loan.product:id,name'])
            ->whereHas('loan', function ($q) use ($bankId, $productIds) {
                $q->where('bank_id', $bankId);
                if ($productIds) {
                    $q->whereIn('product_id', $productIds);
                }
            })
            ->get();

        $byLoan = [];
        foreach ($entries as $e) {
            $loan = $e->loan;
            if (! $loan) {
                continue;
            }
            $lid = $loan->id;
            if (! isset($byLoan[$lid])) {
                $byLoan[$lid] = [
                    'loan_id' => $lid, 'loan_number' => $loan->loan_number, 'application_number' => $loan->application_number,
                    'customer_name' => $loan->customer_name, 'product_name' => $loan->product?->name, 'status' => $loan->status,
                    'accounts' => [], 'amount' => 0, 'settlement_dates' => [], 'pending' => false,
                ];
            }
            // Accounts come from any tranche (so the loan is found by A/c even when
            // deactivated); amounts/dates only from ACTIVE tranches.
            $acc = trim((string) $e->loan_account_number);
            if ($acc !== '' && ! in_array($acc, $byLoan[$lid]['accounts'], true)) {
                $byLoan[$lid]['accounts'][] = $acc;
            }
            if (! $e->is_active) {
                continue;
            }
            $byLoan[$lid]['amount'] += (int) $e->amount;
            // The REAL clearance (settlement) date — not the disbursement fallback — so
            // the mismatch table shows why it isn't payable. Null handover = still pending.
            $hv = optional($e->otc_handover_date)->toDateString();
            if ($hv) {
                if (! in_array($hv, $byLoan[$lid]['settlement_dates'], true)) {
                    $byLoan[$lid]['settlement_dates'][] = $hv;
                }
            } else {
                $byLoan[$lid]['pending'] = true;
            }
        }

        $accIndex = [];
        $nameIndex = [];
        foreach ($byLoan as $info) {
            sort($info['settlement_dates']);
            foreach ($info['accounts'] as $a) {
                $k = $this->normId($a);
                if ($k !== '') {
                    $accIndex[$k] ??= $info;
                }
            }
            if ($info['application_number']) {
                $k = $this->normId((string) $info['application_number']);
                if ($k !== '') {
                    $accIndex[$k] ??= $info;
                }
            }
            $nk = $this->normName($info['customer_name']);
            if ($nk !== '') {
                $nameIndex[$nk] ??= $info;
            }
        }

        return ['acc' => $accIndex, 'name' => $nameIndex];
    }

    /**
     * Why a bank row exists in our DB but didn't match this period — a short
     * category (for the badge) + human-readable reason for the "mismatch" section.
     *
     * @param  array<string,mixed>  $loan  from dbIdentifierIndex()
     * @param  array<int,int>  $matchedLoanIds
     * @return array{category:string, reason:string}
     */
    private function mismatchReason(array $loan, array $matchedLoanIds, string $from, string $to): array
    {
        if (in_array($loan['loan_id'] ?? null, $matchedLoanIds, true)) {
            return ['category' => 'difference', 'reason' => 'Already reconciled under another account number of the same loan'];
        }
        // Loan exists but is paused / closed unfavourably — excluded from payout.
        if (in_array($loan['status'] ?? null, LoanDetail::PAYOUT_EXCLUDED_STATUSES, true)) {
            $label = LoanDetail::STATUS_LABELS[$loan['status']]['label'] ?? ucfirst((string) $loan['status']);

            return ['category' => 'status', 'reason' => 'Loan is '.$label.' — not payable'];
        }
        if (empty($loan['settlement_dates'])) {
            return $loan['pending']
                ? ['category' => 'pending', 'reason' => 'Cheque not cleared yet (OTC pending) — no settlement date']
                : ['category' => 'other', 'reason' => 'No settlement date on record'];
        }
        // Has a clearance date, but none of them fall inside the selected window.
        $inWindow = collect($loan['settlement_dates'])->contains(fn ($d) => $d >= $from && $d <= $to);
        if (! $inWindow) {
            return ['category' => 'outside', 'reason' => 'Settled outside the selected period ('.implode(', ', array_map(
                fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m/Y'), $loan['settlement_dates']
            )).')'];
        }

        return ['category' => 'other', 'reason' => 'Exists in our DB but did not match this period'];
    }

    /**
     * Normalize a bank identifier (A/c or application number) for matching:
     * upper-cased, whitespace stripped. Empty / all-zero placeholder accounts
     * return '' (no identifier) so matching falls through to the name.
     */
    private function normId(?string $v): string
    {
        // Keep only alphanumerics (bank A/c + application numbers are alphanumeric)
        // so stray spaces, hyphens, dots or typos (e.g. a trailing backtick) still match.
        $v = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $v)) ?? '';

        if ($v === '' || (ctype_digit($v) && (int) $v === 0)) {
            return '';
        }

        return $v;
    }

    /** Normalize a customer name for matching: upper-cased, single-spaced. */
    private function normName(?string $v): string
    {
        return preg_replace('/\s+/', ' ', strtoupper(trim((string) $v))) ?? '';
    }

    /**
     * The loan side of a reconciliation group: distinct A/c numbers, total
     * disbursed (Σ tranches), the tranche count/amounts, the distinct settlement
     * dates, and the loan's one-time insurance (header).
     *
     * @param  array{loan: ?LoanDetail, entries: Collection, accounts: array<int, string>}  $g
     * @return array{loan: ?LoanDetail, accounts: array<int,string>, amount:int, tranche_count:int, tranche_amounts:array<int,int>, settlement_dates:array<int,string>, insurance:int}
     */
    private function loanSide(array $g): array
    {
        $entries = $g['entries'];
        $header = $g['loan']?->disbursement;

        return [
            'loan' => $g['loan'],
            'accounts' => $g['accounts'],
            'amount' => (int) $entries->sum('amount'),
            'tranche_count' => $entries->count(),
            'tranche_amounts' => $entries->map(fn ($e) => (int) $e->amount)->values()->all(),
            'settlement_dates' => $entries->map(fn ($e) => optional($e->transactionDate())->toDateString())
                ->filter()->unique()->sort()->values()->all(),
            'insurance' => (int) ($header->insurance_amount ?? 0),
        ];
    }

    /**
     * Loan-level payout: sum the per-tranche {@see entryCalc} breakdowns. The
     * one-time PF/Admin/Insurance ride the loan's carrier tranche (earliest
     * settled, tie = lowest id); the uploaded insurance overrides the DB header
     * on that carrier (null = keep DB).
     *
     * @param  Collection<int, DisbursementEntry>  $entries
     * @return array{ok:bool, base:int, commission:int, pf:int, admin:int, insurance:int, insurance_payout:int, gst:int, gross:int, tds:int, net:int}
     */
    private function loanCalc($entries, PayoutService $svc, ?int $excelInsurance): array
    {
        $carrier = $entries->sortBy(fn ($e) => (optional($e->transactionDate())->format('Ymd') ?? '99999999')
            .str_pad((string) $e->id, 10, '0', STR_PAD_LEFT))->first();

        $agg = ['ok' => true, 'base' => 0, 'commission' => 0, 'pf' => 0, 'admin' => 0,
            'insurance' => 0, 'insurance_payout' => 0, 'gst' => 0, 'gross' => 0, 'tds' => 0, 'net' => 0];

        foreach ($entries as $e) {
            $carry = $carrier && $e->id === $carrier->id;
            $c = $this->entryCalc($e, [], $svc, $carry, $carry ? $excelInsurance : null);
            if (! $c['ok']) {
                $agg['ok'] = false;
            }
            foreach (['base', 'commission', 'pf', 'admin', 'insurance', 'insurance_payout', 'gst', 'gross', 'tds', 'net'] as $k) {
                $agg[$k] += $c[$k];
            }
        }

        return $agg;
    }

    /**
     * Full payout breakdown for one matched entry (for the reconcile table +
     * summaries). Commission is the slab rate on the tranche's disbursed amount
     * (or, for a PF-based product, on the loan's one-time PF base). The one-time
     * PF/Admin/Insurance only ride the loan's carrier tranche ($carryOneTime).
     * Insurance is taken from the uploaded sheet ($excelInsurance) when provided,
     * else from our DB header. Zeros + ok=false when no product/version/slab resolves.
     *
     * @param  array<string, mixed>  $excel
     * @return array{ok:bool, base:int, rate_type:?string, rate_value:float, cap:?int, commission:int, pf:int, admin:int, insurance:int, insurance_payout:int, gst:int, gross:int, tds:int, net:int}
     */
    private function entryCalc(DisbursementEntry $e, array $excel, PayoutService $svc, bool $carryOneTime, ?int $excelInsurance = null): array
    {
        $loan = $e->loan;
        $header = $loan?->disbursement;
        $pfIncl = $carryOneTime ? (int) ($header->pf_amount ?? 0) : 0;
        $adminIncl = $carryOneTime ? (int) ($header->admin_charges ?? 0) : 0;
        $insurance = $carryOneTime ? ($excelInsurance ?? (int) ($header->insurance_amount ?? 0)) : 0;

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

        // Resolve the config version + rates in force on this tranche's transaction
        // date (NEFT transfer / disbursement date).
        $date = $e->transactionDate() ? CarbonImmutable::parse($e->transactionDate()) : CarbonImmutable::now();
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

    /**
     * Parse a bank-statement date that may arrive in many shapes — dd-mm-yyyy,
     * dd/mm/yyyy, dd.mm.yyyy, dd,mm,yyyy, d-m-yyyy, yyyy-mm-dd, 2-digit years, or
     * an Excel date serial. Any separator run is normalized to a dash and the
     * day-first interpretation is preferred (Indian convention).
     *
     * @return array{0: ?CarbonImmutable, 1: bool} [date, error] — error is true
     *                                             when a non-empty value could not be read.
     */
    private function parseFlexibleDate(mixed $v): array
    {
        if ($v === null || (is_string($v) && trim($v) === '')) {
            return [null, false];
        }

        if (is_numeric($v)) {
            $d = XlsxImportService::excelDate($v);

            return [$d, $d === null];
        }

        // Collapse any run of separators (dash, slash, dot, comma, space) to one dash.
        $norm = preg_replace('/[\s.,\/\\\\-]+/', '-', trim((string) $v)) ?? '';

        foreach (['j-n-Y', 'j-n-y', 'Y-n-j'] as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat('!'.$fmt, $norm);
            $err = \DateTimeImmutable::getLastErrors();
            $bad = $err !== false && (($err['warning_count'] ?? 0) + ($err['error_count'] ?? 0)) > 0;
            if ($dt !== false && ! $bad) {
                return [CarbonImmutable::instance($dt), false];
            }
        }

        return [null, true];
    }

    private function exportReport($payouts, Carbon $from, Carbon $to)
    {
        $rows = $payouts->map(fn ($p) => [
            optional($p->finalized_at)->format('d/m/Y'),
            $p->payoutUser?->name ?? '—',
            $p->loan?->loan_number ?? '',
            $p->loan?->application_number ?? '',
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
            ['Date', 'Payout User', 'Loan #', 'App #', 'Bank', 'Product', 'Basis', 'Commission', 'Insurance', 'GST', 'TDS', 'Net Payout', 'Cycle'],
            $rows,
            array_fill_keys([6, 7, 8, 9, 10, 11], XlsxExportService::TYPE_NUMBER),
        );
    }
}
