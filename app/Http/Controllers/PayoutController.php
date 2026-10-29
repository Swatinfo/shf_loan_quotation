<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Bank;
use App\Models\DisbursementEntry;
use App\Models\LoanDetail;
use App\Models\LoanPayout;
use App\Models\Product;
use App\Services\PayoutConfigService;
use App\Services\PayoutRunService;
use App\Services\PayoutService;
use App\Services\XlsxExportService;
use App\Services\XlsxImportService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PayoutController extends Controller
{
    /**
     * The bank-statement columns we export/import for reconciliation. `loan_acc_no`
     * and `application_number` are BOTH identifiers — a row matches our loan on
     * either one (plus a customer-name fallback), so the bank can fill whichever
     * their statement carries (or both). There is no admin column: the bank's
     * `disbursed_amount` already includes PF + admin. `pf_amount` (excluding GST) is
     * what a PF-based product is reconciled on. An old file that still carries an
     * `admin_charges` column is read fine — the column is simply ignored — and an
     * old `loan_amount` header is read as `disbursed_amount`.
     * `product` is a CHECK, not a match key: the bank's product name is compared
     * with our loan's product (name, code or a configured bank alias).
     */
    private const COLUMNS = ['application_number', 'loan_acc_no', 'customer_name', 'product', 'cheque_number', 'disbursement_date', 'disbursed_amount', 'pf_amount', 'insurance_amount'];

    /** Where an uploaded statement is kept so the page can re-run without re-uploading. */
    private const UPLOAD_DIR = 'reconcile-uploads';

    /** Kept uploads older than this (hours) are deleted on the next upload. */
    private const UPLOAD_TTL_HOURS = 24;

    /**
     * PF-based reconciliation tolerance (₹): our PF excluding GST is back-calculated
     * and rounded, so a difference of ₹1 or less still counts as a match.
     */
    private const PF_TOLERANCE = 1;

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
            'payout-reconciliation-template-'.now()->format('d-m-Y_H-i-s').'.xlsx',
            self::COLUMNS,
            [], // headers only — blank format
            [6 => XlsxExportService::TYPE_NUMBER, 7 => XlsxExportService::TYPE_NUMBER, 8 => XlsxExportService::TYPE_NUMBER],
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
            'file' => 'required_without:upload_token|file|mimes:xlsx|max:10240',
            'upload_token' => 'nullable|uuid',
        ]);

        // A fresh upload is kept (per user) so the result page can re-run on the same
        // file — e.g. right after a product correction — without uploading it again.
        [$uploadToken, $uploadPath] = $request->hasFile('file')
            ? $this->keepUpload($request->file('file'), $request->user()->id)
            : [$request->upload_token, $this->keptUploadPath($request->upload_token, $request->user()->id)];
        if ($uploadPath === null) {
            return redirect()->route('payouts.reconcile')
                ->with('error', 'The uploaded statement has expired — please upload the file again.');
        }

        $productIds = array_map('intval', $request->input('product_ids', []));
        $excelRows = app(XlsxImportService::class)->readAssoc($uploadPath);
        $our = $this->ourEntries((int) $request->bank_id, $request->from, $request->to, $productIds);
        $payoutService = app(PayoutService::class);
        // Every product of the bank — the Excel `product` column may name any of them.
        $bankProducts = Product::where('bank_id', $request->bank_id)->get()->keyBy('id');

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
                // `loan_amount` = the column's name in files made before the rename.
                '_amount' => (int) round((float) ($r['disbursed_amount'] ?? $r['loan_amount'] ?? 0)),
                '_pf' => (int) round((float) ($r['pf_amount'] ?? 0)),
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

        // The one-time PF / admin / insurance belong to each loan's FIRST settled
        // tranche overall — so a later period's tranche never re-counts them.
        $carriers = $this->carrierEntryIds(array_keys($groups));
        foreach ($groups as $lid => $g) {
            $groups[$lid]['carrier_id'] = $carriers[$lid] ?? null;
        }

        // Same method as Payout Runs: rates + product version as of the window END
        // date, and each product's slab picked from the TOTAL of all its loans in the
        // window (every user), summing each loan's payout base (entries + PF + admin,
        // or PF excluding GST for a PF-based product). That one tier rate then applies
        // to every loan of the product.
        $toDate = CarbonImmutable::parse($request->to)->endOfDay();
        $rates = app(PayoutConfigService::class)->ratesAsOf($toDate);
        foreach ($groups as $lid => $g) {
            $groups[$lid]['side'] = $this->loanSide($groups[$lid], $rates['pf_gst']);
        }
        $tiers = $this->productTiers($groups, $toDate);

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
                $dbOnly[] = $g['side'];

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
            $ourSide = $g['side'];
            $calc = $this->loanCalc($ourSide, $tiers[$g['loan']?->product_id] ?? null, $g['loan'], $ex['_insurance'], $rates, $payoutService);

            // Normal product: our entries + PF + admin vs the bank's disbursed_amount.
            // PF-based: our PF excluding GST vs the bank's pf_amount (±₹1).
            $ex['_compare'] = $ourSide['is_pf_based'] ? $ex['_pf'] : $ex['_amount'];
            $amountFlag = $ourSide['is_pf_based']
                ? abs($ourSide['amount'] - $ex['_pf']) > self::PF_TOLERANCE
                : $ourSide['amount'] !== $ex['_amount'];
            // Date mismatch when unreadable, or the bank's date is not one of our
            // tranche settlement dates.
            $dateFlag = $ex['_date_error']
                || ($ex['_date'] !== null && ! in_array($ex['_date'], $ourSide['settlement_dates'], true));
            $insFlag = $ex['_insurance'] !== null && $ex['_insurance'] !== $ourSide['insurance'];
            $product = $this->productCheck($ex['product'] ?? null, $g['loan']?->product, $bankProducts);
            $productFlag = in_array($product['status'], ['mismatch', 'unknown'], true);
            $hasDisc = $amountFlag || $dateFlag || $insFlag || $productFlag;

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
                'product' => $product,
                'flags' => ['amount' => $amountFlag, 'date' => $dateFlag, 'insurance' => $insFlag, 'product' => $productFlag],
                'has_discrepancy' => $hasDisc,
            ];
        }

        $unmatchedExcel = collect($excel)->reject(fn ($r, $i) => isset($consumed[$i]))->values();

        // "In Excel, not in DB" must mean the record truly does NOT exist. A bank
        // row that didn't match an in-window loan but whose identifier DOES exist in
        // our DB (same bank, ANY product, any date) is a data/period mismatch — e.g.
        // settled outside the selected window, logged under another product, or
        // different amount/date — so we can't pay it, but it is NOT "missing".
        // Surface those separately.
        $dbIndex = $this->dbIdentifierIndex((int) $request->bank_id);
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
                $rc = $this->mismatchReason($hit, $matchedLoanIds, $fromStr, $toStr, $productIds);
                $excelInDb[] = [
                    'excel' => $r, 'loan' => $hit, 'reason' => $rc['reason'], 'category' => $rc['category'],
                    'product' => $this->productCheck($r['product'] ?? null, $bankProducts->get($hit['product_id']), $bankProducts),
                ];
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
                'tiers' => array_values($tiers),
                'by_product' => $byProduct,
                'by_user' => $byUser,
                'discrepancy_count' => $discrepancyCount,
                'date_error_count' => $dateErrorCount,
                'product_issue_count' => collect($matched)->where('flags.product', true)->count()
                    + collect($excelInDb)->whereIn('product.status', ['mismatch', 'unknown'])->count(),
                'upload_token' => $uploadToken,
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
                'loan.bank:id,name', 'loan.product', 'loan.payoutUser.roles', 'loan.disbursement',
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
     * Identifier → loan-info index over ALL loans for a bank (every product — a loan
     * logged under the wrong product is still found), ANY status, ANY date —
     * including loans with no disbursement entries. Used to
     * tell "truly not in our DB" apart from "exists but didn't match this period".
     * Keyed (normalized) by A/c number + application number (→ 'acc') and by customer
     * name (→ 'name').
     *
     * @return array{acc: array<string, array<string,mixed>>, name: array<string, array<string,mixed>>}
     */
    private function dbIdentifierIndex(int $bankId): array
    {
        // Start from the LOANS themselves (bank, ANY product/status) so a loan with
        // NO disbursement entries — e.g. an on-hold / cancelled loan the bank lists —
        // is still recognised as "exists in our DB" by its application number / name.
        $loans = LoanDetail::query()
            ->where('bank_id', $bankId)
            ->with(['product:id,name,is_pf_based', 'disbursement'])
            ->get(['id', 'loan_number', 'application_number', 'customer_name', 'product_id', 'status']);

        $byLoan = [];
        $headers = [];
        foreach ($loans as $loan) {
            $byLoan[$loan->id] = [
                'loan_id' => $loan->id, 'loan_number' => $loan->loan_number, 'application_number' => $loan->application_number,
                'customer_name' => $loan->customer_name, 'product_id' => $loan->product_id, 'product_name' => $loan->product?->name, 'status' => $loan->status,
                'is_pf_based' => (bool) $loan->product?->is_pf_based,
                'accounts' => [], 'amount' => 0, 'settlement_dates' => [], 'pending' => false,
            ];
            $headers[$loan->id] = $loan->disbursement;
        }

        // Enrich with tranche data: accounts from ANY tranche (so a loan is found by A/c
        // even when deactivated); amounts/dates only from ACTIVE tranches.
        $entries = DisbursementEntry::query()
            ->whereIn('loan_id', array_keys($byLoan))
            ->get(['id', 'loan_id', 'loan_account_number', 'amount', 'is_active', 'otc_handover_date']);

        foreach ($entries as $e) {
            $lid = $e->loan_id;
            if (! isset($byLoan[$lid])) {
                continue;
            }
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

        // Same compared figure as the matched table: entries + PF + admin (normal),
        // or PF excluding GST (PF-based).
        $pfGst = app(PayoutConfigService::class)->ratesAsOf(CarbonImmutable::now())['pf_gst'];
        foreach ($byLoan as $lid => $info) {
            $header = $headers[$lid] ?? null;
            if (! $header) {
                continue;
            }
            $byLoan[$lid]['amount'] = $info['is_pf_based']
                ? PayoutService::pfBase($header, $pfGst)
                : $info['amount'] + PayoutService::oneTimeChargesBase($header);
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
     * @param  array<int,int>  $productIds  the products picked for this reconciliation
     * @return array{category:string, reason:string}
     */
    private function mismatchReason(array $loan, array $matchedLoanIds, string $from, string $to, array $productIds = []): array
    {
        if (in_array($loan['loan_id'] ?? null, $matchedLoanIds, true)) {
            return ['category' => 'difference', 'reason' => 'Already reconciled under another account number of the same loan'];
        }
        // Loan exists but is paused / closed unfavourably — excluded from payout.
        if (in_array($loan['status'] ?? null, LoanDetail::PAYOUT_EXCLUDED_STATUSES, true)) {
            $label = LoanDetail::STATUS_LABELS[$loan['status']]['label'] ?? ucfirst((string) $loan['status']);

            return ['category' => 'status', 'reason' => 'Loan is '.$label.' — not payable'];
        }
        // Logged under a product that isn't part of this reconciliation.
        if ($productIds && ! in_array($loan['product_id'] ?? null, $productIds, true)) {
            return ['category' => 'other_product', 'reason' => 'Logged under '.($loan['product_name'] ?? 'another product').' — not one of the selected products'];
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
     * One-click product correction from the reconciliation screen: the bank's
     * statement names a different product than the one the loan was logged under.
     * Moves the loan (and its active tranches, incl. the entries JSON) to that
     * product of the SAME bank. Refused once any of the loan's money is in a payout
     * run (or a legacy finalized payout) — a paid run must stay consistent.
     */
    public function changeLoanProduct(Request $request, LoanDetail $loan): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        if ((int) $product->bank_id !== (int) $loan->bank_id) {
            return response()->json(['success' => false, 'message' => 'That product belongs to another bank.'], 422);
        }
        if ((int) $product->id === (int) $loan->product_id) {
            return response()->json(['success' => false, 'message' => 'The loan is already under '.$product->name.'.'], 422);
        }

        $header = $loan->disbursement;
        $paid = DisbursementEntry::where('loan_id', $loan->id)
            ->where(fn ($q) => $q->whereNotNull('payout_run_id')->orWhereNotNull('loan_payout_id'))
            ->exists()
            || LoanPayout::where('loan_id', $loan->id)->exists()
            || ($header && ($header->pf_payout_run_id || $header->insurance_payout_run_id || $header->charges_payout_run_id));
        if ($paid) {
            return response()->json([
                'success' => false,
                'message' => 'Loan #'.$loan->loan_number.' is already in a payout run — its product can\'t be changed here.',
            ], 422);
        }

        $oldProduct = $loan->product;
        DB::transaction(function () use ($loan, $product, $header) {
            $loan->update(['product_id' => $product->id]);

            DisbursementEntry::where('loan_id', $loan->id)->where('is_active', true)
                ->update(['product_id' => $product->id, 'product_name' => $product->name]);

            if ($header && ! empty($header->entries)) {
                $header->update(['entries' => array_map(
                    fn (array $e) => array_merge($e, ['product_id' => $product->id, 'product_name' => $product->name]),
                    $header->entries,
                )]);
            }
        });

        ActivityLog::log('change_loan_product', $loan, [
            'loan_number' => $loan->loan_number,
            'source' => 'reconcile',
            'old_product_id' => $oldProduct?->id,
            'old_product' => $oldProduct?->name,
            'new_product_id' => $product->id,
            'new_product' => $product->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Loan #'.$loan->loan_number.' moved from '.($oldProduct?->name ?? '—').' to '.$product->name.'.',
        ]);
    }

    /**
     * Compare the bank's product name (Excel `product` column) with our loan's
     * product, within the bank's products (name, code or a configured bank alias).
     *
     *   ok       — the bank's name is our product
     *   mismatch — the bank's name is ANOTHER product of the bank (`target` = it)
     *   unknown  — the name is none of the bank's products (add it as an alias)
     *   blank    — no product in the file (old format / empty cell): not checked
     *
     * @param  Collection<int, Product>  $bankProducts
     * @return array{status:string, excel:string, target_id:?int, target_name:?string}
     */
    private function productCheck(mixed $excelName, ?Product $ours, Collection $bankProducts): array
    {
        $excel = trim((string) $excelName);
        $check = ['status' => 'blank', 'excel' => $excel, 'target_id' => null, 'target_name' => null];
        if (Product::normalizeBankName($excel) === '') {
            return $check;
        }
        if ($ours?->matchesBankName($excel)) {
            return ['status' => 'ok'] + $check;
        }

        $targets = $bankProducts->filter(fn (Product $p) => $p->matchesBankName($excel));
        if ($targets->count() !== 1) {
            return ['status' => 'unknown'] + $check;
        }

        return ['status' => 'mismatch', 'target_id' => $targets->first()->id, 'target_name' => $targets->first()->name] + $check;
    }

    /**
     * Keep an uploaded statement for this user and return [token, absolute path].
     * Older kept files (past the TTL) are cleared at the same time.
     *
     * @return array{0:string, 1:string}
     */
    private function keepUpload(UploadedFile $file, int $userId): array
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHours(self::UPLOAD_TTL_HOURS)->getTimestamp();
        foreach ($disk->files(self::UPLOAD_DIR) as $old) {
            if ($disk->lastModified($old) < $cutoff) {
                $disk->delete($old);
            }
        }

        $token = (string) Str::uuid();
        $disk->putFileAs(self::UPLOAD_DIR, $file, $userId.'_'.$token.'.xlsx');

        return [$token, $disk->path(self::UPLOAD_DIR.'/'.$userId.'_'.$token.'.xlsx')];
    }

    /** Absolute path of this user's kept upload, or null when it no longer exists. */
    private function keptUploadPath(?string $token, int $userId): ?string
    {
        $relative = self::UPLOAD_DIR.'/'.$userId.'_'.$token.'.xlsx';

        return $token && Storage::disk('local')->exists($relative) ? Storage::disk('local')->path($relative) : null;
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
     * Each loan's carrier tranche: its FIRST settled active tranche overall
     * (earliest transaction date, tie = lowest id), regardless of the selected
     * window. The one-time PF / admin / insurance ride only this tranche.
     *
     * @param  array<int,int>  $loanIds
     * @return array<int,int> loan_id => carrier entry id
     */
    private function carrierEntryIds(array $loanIds): array
    {
        if (! $loanIds) {
            return [];
        }

        $carriers = [];
        DisbursementEntry::query()
            ->whereIn('loan_id', $loanIds)
            ->where('is_active', true)
            ->whereNotNull('otc_handover_date')
            ->orderByRaw(DisbursementEntry::TXN_DATE_SQL)
            ->orderBy('id')
            ->get(['id', 'loan_id'])
            ->each(function (DisbursementEntry $e) use (&$carriers) {
                $carriers[$e->loan_id] ??= $e->id;
            });

        return $carriers;
    }

    /**
     * The loan side of a reconciliation group: distinct A/c numbers, the figure
     * compared with the bank (also the loan's payout base), the tranche
     * count/amounts, the distinct settlement dates, and the loan's one-time
     * insurance (header).
     *
     * Compared figure (`amount`) — the one-time parts count only when the loan's
     * carrier tranche is in this group:
     *   - normal product: Σ tranches + PF + admin (as entered) vs Excel disbursed_amount
     *   - PF-based:       PF excluding GST                      vs Excel pf_amount
     *
     * @param  array{loan: ?LoanDetail, entries: Collection, accounts: array<int, string>, carrier_id?: ?int}  $g
     * @return array{loan: ?LoanDetail, accounts: array<int,string>, is_pf_based:bool, carries_one_time:bool, amount:int, disbursed:int, charges:int, pf_ex_gst:int, tranche_count:int, tranche_amounts:array<int,int>, settlement_dates:array<int,string>, insurance:int}
     */
    private function loanSide(array $g, float $pfGst): array
    {
        $entries = $g['entries'];
        $header = $g['loan']?->disbursement;
        $isPf = (bool) $g['loan']?->product?->is_pf_based;
        $carries = $header && $entries->contains('id', $g['carrier_id'] ?? null);
        $disbursed = (int) $entries->sum('amount');

        $charges = ($carries && ! $isPf) ? PayoutService::oneTimeChargesBase($header) : 0;
        $pfExGst = ($carries && $isPf) ? PayoutService::pfBase($header, $pfGst) : 0;

        return [
            'loan' => $g['loan'],
            'accounts' => $g['accounts'],
            'is_pf_based' => $isPf,
            'carries_one_time' => $carries,
            'amount' => $isPf ? $pfExGst : $disbursed + $charges,
            'disbursed' => $disbursed,
            'charges' => $charges,
            'pf_ex_gst' => $pfExGst,
            'tranche_count' => $entries->count(),
            'tranche_amounts' => $entries->map(fn ($e) => (int) $e->amount)->values()->all(),
            'settlement_dates' => $entries->map(fn ($e) => optional($e->transactionDate())->toDateString())
                ->filter()->unique()->sort()->values()->all(),
            'insurance' => (int) ($header->insurance_amount ?? 0),
        ];
    }

    /**
     * Each product's payout tier for the window, exactly like a payout run: the
     * aggregate is the sum of every in-window loan's payout base (all users,
     * matched or not), and the slab is the one covering that aggregate in the
     * product version in force on the window end date.
     *
     * @param  array<int, array<string, mixed>>  $groups  keyed by loan id, each with 'side'
     * @return array<int, array<string, mixed>> keyed by product id
     */
    private function productTiers(array $groups, CarbonImmutable $toDate): array
    {
        $tiers = [];
        foreach ($groups as $g) {
            $product = $g['loan']?->product;
            if (! $product) {
                continue;
            }
            $tiers[$product->id] ??= ['product' => $product, 'aggregate' => 0, 'loans' => 0];
            $tiers[$product->id]['aggregate'] += $g['side']['amount'];
            $tiers[$product->id]['loans']++;
        }

        $cfg = app(PayoutConfigService::class);
        $runs = app(PayoutRunService::class);
        foreach ($tiers as $pid => $t) {
            $version = $cfg->productVersionAsOf($t['product'], $toDate);
            $slab = $version ? $runs->matchSlab($version, $t['aggregate']) : null;
            $tiers[$pid] += [
                'product_name' => $t['product']->name,
                'is_pf_based' => (bool) $t['product']->is_pf_based,
                'slab' => $slab,
                'slab_low' => $slab ? (int) $slab->low_amount : null,
                'slab_high' => $slab ? (int) $slab->high_amount : null,
                'rate_type' => $slab ? $slab->payout_type : 'percent',
                'rate_value' => $slab ? (float) $slab->payout_value : 0.0,
                'max_payout' => $version && $version->max_payout_amount !== null ? (int) $version->max_payout_amount : null,
            ];
        }

        return $tiers;
    }

    /**
     * Loan-level payout on the product's tier (see productTiers) — the same per-loan
     * figure a payout run shows: tier rate × the loan's payout base (pre-cap; the
     * max-payout cap applies per user × product in the run). Insurance payout is
     * information only; TDS on the commission. The uploaded insurance overrides the
     * DB header (null = keep DB) when the loan's carrier tranche is in the window.
     *
     * @param  array<string, mixed>  $side  from loanSide()
     * @param  array<string, mixed>|null  $tier  from productTiers()
     * @return array{ok:bool, base:int, rate_type:?string, rate_value:float, commission:int, pf:int, admin:int, insurance:int, insurance_payout:int, gross:int, tds:int, net:int}
     */
    private function loanCalc(array $side, ?array $tier, ?LoanDetail $loan, ?int $excelInsurance, array $rates, PayoutService $svc): array
    {
        $header = $loan?->disbursement;
        $insurance = $side['carries_one_time'] ? ($excelInsurance ?? (int) ($header->insurance_amount ?? 0)) : 0;
        // PF column: PF excl. GST for PF-based; PF + admin as entered for a normal product.
        $pf = $side['is_pf_based'] ? $side['pf_ex_gst'] : ($side['charges'] > 0 ? (int) $header->pf_amount : 0);
        $admin = $side['is_pf_based'] ? 0 : $side['charges'] - $pf;

        $slab = $tier['slab'] ?? null;
        $rate = $slab ? $slab->rateFor($loan?->payoutUser?->hasRole('connector') ?? false) : null;
        $commission = $rate ? $svc->computeAmount($rate['type'], (float) $rate['value'], $side['amount'], null) : 0;
        $bd = $svc->breakdown($commission, $insurance, $rates);

        return [
            'ok' => $slab !== null,
            'base' => $side['amount'],
            'rate_type' => $rate['type'] ?? null,
            'rate_value' => (float) ($rate['value'] ?? 0),
            'commission' => $bd['commission'],
            'pf' => $pf,
            'admin' => $admin,
            'insurance' => $bd['insurance_base'],
            'insurance_payout' => $bd['insurance_payout'],
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
            'payout-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'-'.now()->format('His').'.xlsx',
            ['Date', 'Payout User', 'Loan #', 'App #', 'Bank', 'Product', 'Basis', 'Commission', 'Insurance', 'GST', 'TDS', 'Net Payout', 'Cycle'],
            $rows,
            array_fill_keys([6, 7, 8, 9, 10, 11], XlsxExportService::TYPE_NUMBER),
        );
    }
}
