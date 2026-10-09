<?php

namespace App\Http\Controllers;

use App\Models\PayoutRun;
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
        $input = $request->only(['from', 'to']);
        $preview = null;
        if ($request->filled('from') && $request->filled('to')) {
            $data = $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after_or_equal:from',
            ]);
            $preview = $this->enrich($this->service->previewRun($data['from'], $data['to']));
        }

        return view('newtheme.payouts.runs', [
            'preview' => $preview,
            'input' => $input,
            'runs' => PayoutRun::with('finalizedBy')->latest('id')->limit(50)->get(),
            'canFinalize' => $request->user()->hasPermission('finalize_payout'),
            'pageKey' => 'reports',
        ]);
    }

    public function finalize(Request $request)
    {
        $data = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $run = $this->service->finalizeRun($data['from'], $data['to'], $request->user());

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

        return view('newtheme.payouts.run-show', [
            'run' => $run,
            'linesByUser' => $linesByUser,
            'productName' => $productName,
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
