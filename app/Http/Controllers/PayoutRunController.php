<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\DisbursementEntry;
use App\Models\PayoutRun;
use App\Models\Product;
use App\Models\User;
use App\Services\PayoutRunService;
use Illuminate\Http\Request;

/**
 * Aggregate payout runs: pick a date range → preview (per-product tiers + per-user
 * breakdown) → finalize a run. Finalized runs are the historical payout record.
 */
class PayoutRunController extends Controller
{
    public function __construct(private PayoutRunService $service) {}

    public function index(Request $request)
    {
        $input = $request->only(['from', 'to', 'bank_id', 'product_ids']);
        $preview = null;
        if ($request->filled('from') && $request->filled('to') && $request->filled('bank_id') && $request->filled('product_ids')) {
            $data = $request->validate([
                'bank_id' => 'required|exists:banks,id',
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
                'product_ids' => 'required|array|min:1',
                'product_ids.*' => 'integer|exists:products,id',
            ]);
            $preview = $this->enrich($this->service->previewRun(
                $data['from'], $data['to'], (int) $data['bank_id'],
                array_map('intval', $data['product_ids'] ?? []),
            ));
        }

        return view('newtheme.payouts.runs', [
            'preview' => $preview,
            'input' => $input,
            'banks' => Bank::active()->orderBy('name')->get(['id', 'name']),
            'products' => Product::active()->orderBy('name')->get(['id', 'name', 'bank_id']),
            'runs' => PayoutRun::with(['finalizedBy', 'bank'])->latest('id')->limit(50)->get(),
            'canFinalize' => $request->user()->hasPermission('finalize_payout'),
            'pageKey' => 'reports',
        ]);
    }

    public function finalize(Request $request)
    {
        $data = $request->validate([
            'bank_id' => 'required|exists:banks,id',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        $run = $this->service->finalizeRun(
            $data['from'], $data['to'], $request->user(), (int) $data['bank_id'],
            array_map('intval', $data['product_ids'] ?? []),
        );

        return redirect()->route('payouts.runs.show', $run)->with(
            'success',
            'Payout run finalized — ₹ '.inr($run->total_net).' net to '.$run->userTotals()->count().' user(s).'
        );
    }

    public function show(PayoutRun $run)
    {
        $run->load(['products', 'lines.payoutUser', 'userTotals.payoutUser', 'finalizedBy']);

        // Group lines by user for the drill-down. Map the run-product id → name.
        $linesByUser = $run->lines->groupBy('payout_user_id');
        $productName = $run->products->pluck('product_name', 'id');

        // Loans considered in this run — derived from the tranches stamped with this
        // run id, grouped by payout user → product. (One row per loan.)
        $loansByUser = DisbursementEntry::where('payout_run_id', $run->id)
            ->with(['loan:id,loan_number,payout_user_id,product_id', 'loan.product:id,name', 'loan.payoutUser:id,name'])
            ->get()
            ->groupBy('loan_id')
            ->map(function ($entries) {
                $loan = $entries->first()->loan;

                return [
                    'loan_number' => $loan?->loan_number ?? '—',
                    'payout_user_id' => $loan?->payout_user_id,
                    'payout_user' => $loan?->payoutUser?->name ?? '—',
                    'product_name' => $loan?->product?->name ?? '—',
                    'amount' => (int) $entries->sum('amount'),
                    'settlement_date' => optional($entries->max('otc_handover_date'))->toDateString(),
                ];
            })
            ->values()
            ->groupBy('payout_user_id');

        return view('newtheme.payouts.run-show', [
            'run' => $run,
            'linesByUser' => $linesByUser,
            'productName' => $productName,
            'loansByUser' => $loansByUser,
            'pageKey' => 'reports',
        ]);
    }

    /**
     * Attach display names (users + products) to a preview payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enrich(array $data): array
    {
        $userIds = collect($data['users'])->pluck('payout_user_id')
            ->merge(collect($data['lines'])->pluck('payout_user_id'))->unique();
        $data['user_names'] = User::whereIn('id', $userIds)->pluck('name', 'id');
        $data['product_names'] = collect($data['products'])->pluck('product_name', 'product_id');

        return $data;
    }
}
