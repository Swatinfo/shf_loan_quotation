<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Stage;
use App\Models\User;
use App\Services\NumberToWordsService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private const AGING_STUCK_DAYS = 14;

    private const QUERY_STALE_DAYS = 7;

    private const HOLD_STALE_DAYS = 30;

    // ── Loan Pipeline ──

    public function pipeline()
    {
        $user = $this->authorizeReports();
        $scope = $this->reportScope($user);

        [$branches, $users] = $this->filterOptions($scope);
        $banks = Bank::active()->orderBy('name')->get();
        $products = Product::active()->with('bank')->orderBy('name')->get();

        // Mains in sequence, each followed by its sub-stages (stage filter).
        $mainStages = Stage::enabled()->mainStages()->orderBy('sequence_order')->get();
        $subStages = Stage::enabled()->whereNotNull('parent_stage_key')->orderBy('sequence_order')->get()->groupBy('parent_stage_key');
        $stages = $mainStages->flatMap(fn ($main) => collect([$main])->concat($subStages->get($main->stage_key, collect())));

        return view('newtheme.reports.pipeline', compact('banks', 'products', 'branches', 'users', 'stages', 'scope'));
    }

    public function pipelineData(Request $request): JsonResponse
    {
        $user = $this->authorizeReports();
        $scope = $this->reportScope($user);

        if ($request->input('tab') === 'workload') {
            return $this->workloadData($request, $scope);
        }

        $base = DB::table('loan_details as ld')->whereNull('ld.deleted_at');
        $this->applyFilters($base, $request, 'ld');
        if ($scope['type'] === 'branch') {
            $base->whereIn('ld.branch_id', $scope['branch_ids']);
        }

        // Status chips (status filter NOT applied — chips show the whole set).
        $byStatus = (clone $base)
            ->select('ld.status', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(ld.loan_amount) as amount'))
            ->groupBy('ld.status')->get()->keyBy('status');
        $summary = [];
        foreach (['active', 'on_hold', 'completed', 'rejected', 'cancelled'] as $st) {
            $summary[$st] = [
                'count' => (int) ($byStatus[$st]->cnt ?? 0),
                'amount' => NumberToWordsService::formatCurrency((int) ($byStatus[$st]->amount ?? 0)),
            ];
        }
        $summary['all'] = [
            'count' => (int) $byStatus->sum('cnt'),
            'amount' => NumberToWordsService::formatCurrency((int) $byStatus->sum('amount')),
        ];

        $status = $request->input('status', 'active');
        $rowsQ = (clone $base)
            ->leftJoin('users as adv', DB::raw('COALESCE(ld.assigned_advisor, ld.created_by)'), '=', DB::raw('adv.id'))
            ->leftJoin('branches as br', 'br.id', '=', 'ld.branch_id')
            ->leftJoin('products as p', 'p.id', '=', 'ld.product_id')
            ->leftJoin('users as rej', 'rej.id', '=', 'ld.rejected_by')
            ->leftJoinSub($this->completionSub(), 'fin', 'fin.loan_id', '=', 'ld.id')
            ->select([
                'ld.id', 'ld.loan_number', 'ld.customer_name', 'ld.bank_name', 'ld.status',
                'ld.loan_amount', 'ld.sanctioned_amount', 'ld.disbursed_amount', 'ld.created_at',
                'ld.status_reason', 'ld.status_changed_at',
                'ld.rejected_stage', 'ld.rejection_reason', 'ld.rejected_at',
                'p.name as product_name', 'br.name as branch_name', 'adv.name as advisor_name',
                'rej.name as rejected_by_name', 'fin.done_at',
            ]);
        if ($status !== 'all') {
            $rowsQ->where('ld.status', $status);
        }
        $loans = $rowsQ->orderByDesc('ld.created_at')->get();

        // Stage lines for open loans: in-progress + pending-inside-active-parallel.
        $lineLoanIds = $loans->whereIn('status', ['active', 'on_hold'])->pluck('id');
        $linesByLoan = $lineLoanIds->isEmpty() ? collect() : $this->stageLines($lineLoanIds);

        $now = now();
        $data = $loans->map(function ($r) use ($linesByLoan, $now) {
            $lines = ($linesByLoan[$r->id] ?? collect())->map(function ($l) use ($now) {
                $pending = $l->status === 'pending';
                $heldSince = $pending ? null : max(
                    Carbon::parse($l->started_at ?? $now),
                    $l->held_since ? Carbon::parse($l->held_since) : Carbon::parse($l->started_at ?? $now),
                );

                return [
                    'stage_name' => $l->stage_name_en,
                    'stage_key' => $l->stage_key,
                    'kind' => $pending ? 'pending' : 'in_progress',
                    'owner' => $l->owner_name,
                    'days_in_stage' => $pending || ! $l->started_at ? null : (int) Carbon::parse($l->started_at)->diffInDays($now),
                    'days_with_owner' => $pending || ! $heldSince ? null : (int) $heldSince->diffInDays($now),
                    'queued_days' => $pending && $l->parent_started_at ? (int) Carbon::parse($l->parent_started_at)->diffInDays($now) : null,
                    'open_queries' => (int) $l->open_queries,
                ];
            })->values();

            return [
                'loan_number' => $r->loan_number,
                'stages_url' => route('loans.stages', $r->id),
                'customer_name' => $r->customer_name,
                'bank_product' => trim(($r->bank_name ?? '—').($r->product_name ? ' / '.$r->product_name : '')),
                'branch_name' => $r->branch_name ?? '—',
                'advisor_name' => $r->advisor_name ?? '—',
                'loan_amount' => $r->loan_amount ? NumberToWordsService::formatCurrency($r->loan_amount) : '—',
                'loan_age_days' => (int) Carbon::parse($r->created_at)->diffInDays($now),
                'status' => $r->status,
                'stage_lines' => $lines,
                'max_stage_days' => (int) $lines->max(fn ($l) => $l['days_in_stage'] ?? 0),
                'sanctioned_amount' => $r->sanctioned_amount ? NumberToWordsService::formatCurrency($r->sanctioned_amount) : '—',
                'disbursed_amount' => $r->disbursed_amount ? NumberToWordsService::formatCurrency($r->disbursed_amount) : '—',
                'tat_days' => $r->status === 'completed' && $r->done_at ? (int) Carbon::parse($r->created_at)->diffInDays(Carbon::parse($r->done_at)) : null,
                'status_reason' => $r->status_reason,
                'status_since' => $r->status_changed_at ? date('d/m/Y', strtotime($r->status_changed_at)) : '—',
                'rejected_stage' => $r->rejected_stage,
                'rejection_reason' => $r->rejection_reason,
                'rejected_at' => $r->rejected_at ? date('d/m/Y', strtotime($r->rejected_at)) : '—',
                'rejected_by' => $r->rejected_by_name ?? '—',
            ];
        });

        // Loan-level stage filters (applied on the computed lines).
        if ($request->filled('stage_key')) {
            $key = $request->stage_key;
            $data = $data->filter(fn ($row) => collect($row['stage_lines'])->contains('stage_key', $key));
        }
        if ($request->filled('stuck_days')) {
            $min = (int) $request->stuck_days;
            $data = $data->filter(fn ($row) => $row['max_stage_days'] >= $min);
        }

        // Most-stuck first for open loans; everything else stays newest-first.
        if (in_array($status, ['active', 'on_hold'], true)) {
            $data = $data->sortByDesc('max_stage_days');
        }

        $queuedParallel = $linesByLoan->flatten(1)->where('status', 'pending')->count();

        return response()->json([
            'summary' => $summary,
            'queued_parallel' => $queuedParallel,
            'data' => $data->values(),
        ]);
    }

    /**
     * Workload tab — in-progress stages of active loans grouped by holder.
     */
    private function workloadData(Request $request, array $scope): JsonResponse
    {
        $q = DB::table('stage_assignments as sa')
            ->join('loan_details as ld', 'ld.id', '=', 'sa.loan_id')
            ->join('stages as s', 's.stage_key', '=', 'sa.stage_key')
            ->join('users as u', 'u.id', '=', 'sa.assigned_to')
            ->where('sa.status', 'in_progress')
            ->where('ld.status', 'active')
            ->whereNull('ld.deleted_at')
            ->select(['u.id as user_id', 'u.name as user_name', 's.stage_name_en', 'sa.started_at']);
        $this->applyFilters($q, $request, 'ld', 'sa.assigned_to');
        if ($scope['type'] === 'branch') {
            $q->whereIn('ld.branch_id', $scope['branch_ids']);
        }

        $now = now();
        $rows = $q->get()->map(function ($r) use ($now) {
            $r->days = $r->started_at ? (int) Carbon::parse($r->started_at)->diffInDays($now) : 0;

            return $r;
        });

        $data = $rows->groupBy('user_id')->map(function (Collection $held) {
            return [
                'user_name' => $held->first()->user_name,
                'held' => $held->count(),
                'oldest_days' => (int) $held->max('days'),
                'avg_days' => round($held->avg('days'), 1),
                'stuck' => $held->where('days', '>', 7)->count(),
                'stages' => $held->groupBy('stage_name_en')
                    ->map(fn ($g, $name) => $name.($g->count() > 1 ? ' ×'.$g->count() : ''))
                    ->values()->implode(', '),
            ];
        })->sortByDesc('oldest_days')->values();

        return response()->json(['data' => $data]);
    }

    // ── Management Summary ──

    public function management()
    {
        $user = $this->authorizeReports();
        $scope = $this->reportScope($user);

        [$branches] = $this->filterOptions($scope);

        return view('newtheme.reports.management', compact('branches', 'scope'));
    }

    public function managementData(Request $request): JsonResponse
    {
        $user = $this->authorizeReports();
        $scope = $this->reportScope($user);

        $branchIds = null;
        if ($scope['type'] === 'branch') {
            $branchIds = $scope['branch_ids'];
        }
        if ($request->filled('branch_id')) {
            $requested = (int) $request->branch_id;
            // Never trust the dropdown — intersect with the user's scope.
            $branchIds = $branchIds === null ? [$requested] : array_intersect($branchIds, [$requested]);
        }

        $from = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : null;
        $to = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : null;

        return response()->json([
            'funnel' => $this->funnelSection($branchIds, $from, $to),
            'trend' => $this->trendSection($branchIds),
            'scoreboard' => $this->scoreboardSection($branchIds, $from, $to),
            'exceptions' => $this->exceptionsSection($branchIds),
        ]);
    }

    /**
     * Quotations → Converted → Sanctioned → Disbursed with step conversion %
     * and average days between milestones.
     *
     * @param  array<int>|null  $branchIds
     */
    private function funnelSection(?array $branchIds, ?Carbon $from, ?Carbon $to): array
    {
        $quotes = DB::table('quotations')->whereNull('deleted_at');
        $this->scopeBranchPeriod($quotes, 'quotations', $branchIds, $from, $to);
        $quoteCount = (clone $quotes)->count();
        $quoteAmount = (int) (clone $quotes)->sum('loan_amount');

        $loans = DB::table('loan_details as ld')->whereNull('ld.deleted_at');
        $this->scopeBranchPeriod($loans, 'ld', $branchIds, $from, $to);
        $loanRows = (clone $loans)
            ->leftJoin('quotations as q', 'q.id', '=', 'ld.quotation_id')
            ->select(['ld.id', 'ld.loan_amount', 'ld.created_at', 'q.created_at as quote_created_at'])
            ->get();

        // Sanction / disbursement milestones by their completion date.
        $milestones = fn (string $stageKey) => DB::table('stage_assignments as sa')
            ->join('loan_details as ld', 'ld.id', '=', 'sa.loan_id')
            ->where('sa.stage_key', $stageKey)->where('sa.status', 'completed')
            ->whereNull('ld.deleted_at')
            ->when($branchIds !== null, fn ($q) => $q->whereIn('ld.branch_id', $branchIds))
            ->when($from, fn ($q) => $q->where('sa.completed_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('sa.completed_at', '<=', $to))
            ->select(['ld.id', 'ld.created_at as loan_created_at', 'ld.sanctioned_amount', 'ld.disbursed_amount', 'sa.completed_at']);

        $sanctioned = $milestones('sanction')->get();
        $disbursed = $milestones('disbursement')
            ->leftJoin('stage_assignments as ssa', function ($j) {
                $j->on('ssa.loan_id', '=', 'sa.loan_id')->where('ssa.stage_key', 'sanction')->where('ssa.status', 'completed');
            })
            ->addSelect('ssa.completed_at as sanctioned_at')
            ->get();

        $avgDays = function (Collection $rows, callable $fromCol, callable $toCol): ?float {
            $diffs = $rows->map(function ($r) use ($fromCol, $toCol) {
                $a = $fromCol($r);
                $b = $toCol($r);

                return $a && $b ? Carbon::parse($a)->diffInDays(Carbon::parse($b)) : null;
            })->filter(fn ($d) => $d !== null);

            return $diffs->isEmpty() ? null : round($diffs->avg(), 1);
        };
        $pct = fn (int $part, int $whole): ?float => $whole > 0 ? round($part * 100 / $whole, 1) : null;

        return [
            'quotations' => ['count' => $quoteCount, 'amount' => NumberToWordsService::formatCurrency($quoteAmount)],
            'converted' => [
                'count' => $loanRows->count(),
                'amount' => NumberToWordsService::formatCurrency((int) $loanRows->sum('loan_amount')),
                'pct' => $pct($loanRows->count(), $quoteCount),
                'avg_days' => $avgDays($loanRows, fn ($r) => $r->quote_created_at, fn ($r) => $r->created_at),
            ],
            'sanctioned' => [
                'count' => $sanctioned->count(),
                'amount' => NumberToWordsService::formatCurrency((int) $sanctioned->sum('sanctioned_amount')),
                'pct' => $pct($sanctioned->count(), $loanRows->count()),
                'avg_days' => $avgDays($sanctioned, fn ($r) => $r->loan_created_at, fn ($r) => $r->completed_at),
            ],
            'disbursed' => [
                'count' => $disbursed->count(),
                'amount' => NumberToWordsService::formatCurrency((int) $disbursed->sum('disbursed_amount')),
                'pct' => $pct($disbursed->count(), $sanctioned->count()),
                'avg_days' => $avgDays($disbursed, fn ($r) => $r->sanctioned_at, fn ($r) => $r->completed_at),
            ],
        ];
    }

    /**
     * Last 12 calendar months: created / sanctioned / disbursed (count + ₹).
     *
     * @param  array<int>|null  $branchIds
     */
    private function trendSection(?array $branchIds): array
    {
        $start = now()->startOfMonth()->subMonths(11);

        $bucket = function (Collection $rows, string $dateKey, string $amountKey) {
            return $rows->groupBy(fn ($r) => Carbon::parse($r->{$dateKey})->format('Y-m'))
                ->map(fn ($g) => ['count' => $g->count(), 'amount' => (int) $g->sum($amountKey)]);
        };

        $created = DB::table('loan_details as ld')->whereNull('ld.deleted_at')
            ->when($branchIds !== null, fn ($q) => $q->whereIn('ld.branch_id', $branchIds))
            ->where('ld.created_at', '>=', $start)
            ->select(['ld.created_at', 'ld.loan_amount'])->get();

        $stageRows = fn (string $stageKey, string $amountCol) => DB::table('stage_assignments as sa')
            ->join('loan_details as ld', 'ld.id', '=', 'sa.loan_id')
            ->where('sa.stage_key', $stageKey)->where('sa.status', 'completed')
            ->whereNull('ld.deleted_at')
            ->when($branchIds !== null, fn ($q) => $q->whereIn('ld.branch_id', $branchIds))
            ->where('sa.completed_at', '>=', $start)
            ->select(['sa.completed_at', "ld.{$amountCol} as amount_col"])->get();

        $createdBuckets = $bucket($created, 'created_at', 'loan_amount');
        $sanctionedBuckets = $bucket($stageRows('sanction', 'sanctioned_amount'), 'completed_at', 'amount_col');
        $disbursedBuckets = $bucket($stageRows('disbursement', 'disbursed_amount'), 'completed_at', 'amount_col');

        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $key = $m->format('Y-m');
            $months[] = [
                'month' => $m->format('M Y'),
                'created' => $createdBuckets[$key] ?? ['count' => 0, 'amount' => 0],
                'sanctioned' => $sanctionedBuckets[$key] ?? ['count' => 0, 'amount' => 0],
                'disbursed' => $disbursedBuckets[$key] ?? ['count' => 0, 'amount' => 0],
            ];
        }

        // Indian-format the amounts after computing raw sums (mini-bars use raw).
        return array_map(function ($m) {
            foreach (['created', 'sanctioned', 'disbursed'] as $k) {
                $m[$k]['amount_raw'] = $m[$k]['amount'];
                $m[$k]['amount'] = NumberToWordsService::formatCurrency($m[$k]['amount']);
            }

            return $m;
        }, $months);
    }

    /**
     * Per-branch scoreboard (expandable to advisors) over loans created in the period.
     *
     * @param  array<int>|null  $branchIds
     */
    private function scoreboardSection(?array $branchIds, ?Carbon $from, ?Carbon $to): array
    {
        $loans = DB::table('loan_details as ld')->whereNull('ld.deleted_at')
            ->leftJoin('branches as br', 'br.id', '=', 'ld.branch_id')
            ->leftJoin('users as adv', DB::raw('COALESCE(ld.assigned_advisor, ld.created_by)'), '=', DB::raw('adv.id'))
            ->leftJoinSub($this->completionSub(), 'fin', 'fin.loan_id', '=', 'ld.id')
            ->select([
                'ld.id', 'ld.branch_id', 'br.name as branch_name', 'adv.id as advisor_id',
                'adv.name as advisor_name', 'ld.status', 'ld.created_at', 'ld.disbursed_amount', 'fin.done_at',
            ]);
        $this->scopeBranchPeriod($loans, 'ld', $branchIds, $from, $to);
        $rows = $loans->get();

        // Currently-stuck in-progress stages (>14d) per loan, current state.
        $stuckLoanIds = DB::table('stage_assignments as sa')
            ->join('loan_details as ld', 'ld.id', '=', 'sa.loan_id')
            ->where('sa.status', 'in_progress')->where('ld.status', 'active')
            ->whereNull('ld.deleted_at')
            ->where('sa.started_at', '<=', now()->subDays(self::AGING_STUCK_DAYS))
            ->whereIn('sa.loan_id', $rows->pluck('id'))
            ->pluck('sa.loan_id')->unique()->flip();

        $now = now();
        $aggregate = function (Collection $group) use ($stuckLoanIds): array {
            $completed = $group->where('status', 'completed');
            $tats = $completed->filter(fn ($r) => $r->done_at)
                ->map(fn ($r) => Carbon::parse($r->created_at)->diffInDays(Carbon::parse($r->done_at)));

            return [
                'created' => $group->count(),
                'active' => $group->where('status', 'active')->count(),
                'completed' => $completed->count(),
                'rejection_pct' => $group->count() ? round($group->where('status', 'rejected')->count() * 100 / $group->count(), 1) : 0,
                'avg_tat_days' => $tats->isEmpty() ? null : round($tats->avg(), 1),
                'disbursed' => NumberToWordsService::formatCurrency((int) $group->sum('disbursed_amount')),
                'stuck' => $group->filter(fn ($r) => isset($stuckLoanIds[$r->id]))->count(),
            ];
        };

        return $rows->groupBy('branch_id')->map(function (Collection $branchLoans) use ($aggregate) {
            return array_merge(['branch_name' => $branchLoans->first()->branch_name ?? '—'], $aggregate($branchLoans), [
                'advisors' => $branchLoans->groupBy('advisor_id')->map(
                    fn (Collection $advLoans) => array_merge(['advisor_name' => $advLoans->first()->advisor_name ?? '—'], $aggregate($advLoans))
                )->sortByDesc('created')->values(),
            ]);
        })->sortByDesc('created')->values()->all();
    }

    /**
     * Current-state exception lists (ignore the period filter on purpose).
     *
     * @param  array<int>|null  $branchIds
     */
    private function exceptionsSection(?array $branchIds): array
    {
        $now = now();

        $staleStages = DB::table('stage_assignments as sa')
            ->join('loan_details as ld', 'ld.id', '=', 'sa.loan_id')
            ->join('stages as s', 's.stage_key', '=', 'sa.stage_key')
            ->leftJoin('users as u', 'u.id', '=', 'sa.assigned_to')
            ->where('sa.status', 'in_progress')->where('ld.status', 'active')
            ->whereNull('ld.deleted_at')
            ->where('sa.started_at', '<=', $now->copy()->subDays(self::AGING_STUCK_DAYS))
            ->when($branchIds !== null, fn ($q) => $q->whereIn('ld.branch_id', $branchIds))
            ->orderBy('sa.started_at')
            ->limit(20)
            ->get(['ld.id', 'ld.loan_number', 's.stage_name_en', 'u.name as owner', 'sa.started_at'])
            ->map(fn ($r) => [
                'loan_number' => $r->loan_number,
                'stages_url' => route('loans.stages', $r->id),
                'stage' => $r->stage_name_en,
                'owner' => $r->owner ?? '—',
                'days' => (int) Carbon::parse($r->started_at)->diffInDays($now),
            ]);

        $staleQueries = DB::table('stage_queries as sq')
            ->join('loan_details as ld', 'ld.id', '=', 'sq.loan_id')
            ->leftJoin('users as ru', 'ru.id', '=', 'sq.raised_by')
            ->whereIn('sq.status', ['pending', 'responded'])
            ->whereNotIn('ld.status', ['completed', 'rejected', 'cancelled'])
            ->whereNull('ld.deleted_at')
            ->where('sq.created_at', '<=', $now->copy()->subDays(self::QUERY_STALE_DAYS))
            ->when($branchIds !== null, fn ($q) => $q->whereIn('ld.branch_id', $branchIds))
            ->orderBy('sq.created_at')
            ->limit(20)
            ->get(['ld.id', 'ld.loan_number', 'sq.stage_key', 'ru.name as raised_by', 'sq.created_at'])
            ->map(fn ($r) => [
                'loan_number' => $r->loan_number,
                'stages_url' => route('loans.stages', $r->id),
                'stage' => $r->stage_key,
                'raised_by' => $r->raised_by ?? '—',
                'days' => (int) Carbon::parse($r->created_at)->diffInDays($now),
            ]);

        $staleHolds = DB::table('loan_details as ld')
            ->where('ld.status', 'on_hold')
            ->whereNull('ld.deleted_at')
            ->where('ld.status_changed_at', '<=', $now->copy()->subDays(self::HOLD_STALE_DAYS))
            ->when($branchIds !== null, fn ($q) => $q->whereIn('ld.branch_id', $branchIds))
            ->orderBy('ld.status_changed_at')
            ->limit(20)
            ->get(['ld.id', 'ld.loan_number', 'ld.status_reason', 'ld.status_changed_at'])
            ->map(fn ($r) => [
                'loan_number' => $r->loan_number,
                'stages_url' => route('loans.stages', $r->id),
                'reason' => $r->status_reason ?? '—',
                'days' => (int) Carbon::parse($r->status_changed_at)->diffInDays($now),
            ]);

        return [
            'stale_stages' => $staleStages,
            'stale_queries' => $staleQueries,
            'stale_holds' => $staleHolds,
        ];
    }

    // ── Loan Report (sanctioned / disbursed) ──

    public function loanReport()
    {
        $user = $this->authorizeReports();
        $scope = $this->reportScope($user);

        [$branches, $users] = $this->filterOptions($scope);
        $banks = Bank::active()->orderBy('name')->get();
        $products = Product::active()->with('bank')->orderBy('name')->get();

        return view('newtheme.reports.loan-report', compact('banks', 'products', 'branches', 'users', 'scope'));
    }

    public function loanReportData(Request $request): JsonResponse
    {
        $user = $this->authorizeReports();
        $scope = $this->reportScope($user);

        $status = $request->input('status', 'sanctioned') === 'disbursed' ? 'disbursed' : 'sanctioned';

        $query = DB::table('loan_details as ld')
            ->leftJoin('users as adv', DB::raw('COALESCE(ld.assigned_advisor, ld.created_by)'), '=', DB::raw('adv.id'))
            ->leftJoin('branches as br', 'br.id', '=', 'ld.branch_id')
            ->leftJoin('products as p', 'p.id', '=', 'ld.product_id')
            ->leftJoin('stage_assignments as ssa', function ($join) {
                $join->on('ssa.loan_id', '=', 'ld.id')
                    ->where('ssa.stage_key', 'sanction')
                    ->where('ssa.status', 'completed');
            })
            ->leftJoin('stage_assignments as dsa', function ($join) {
                $join->on('dsa.loan_id', '=', 'ld.id')
                    ->where('dsa.stage_key', 'disbursement')
                    ->where('dsa.status', 'completed');
            })
            ->whereNull('ld.deleted_at')
            ->whereNotNull($status === 'disbursed' ? 'ld.disbursed_amount' : 'ld.sanctioned_amount')
            ->select([
                'ld.id', 'ld.loan_number', 'ld.customer_name', 'ld.bank_name',
                'p.name as product_name', 'br.name as branch_name', 'adv.name as advisor_name',
                'ld.loan_amount', 'ld.sanctioned_amount', 'ld.disbursed_amount', 'ld.status',
                'ld.created_at',
                'ssa.completed_at as sanctioned_on', 'dsa.completed_at as disbursed_on',
            ]);

        $this->applyFilters($query, $request, 'ld');

        // Server-side scope — never trust the branch dropdown value.
        if ($scope['type'] === 'branch') {
            $query->whereIn('ld.branch_id', $scope['branch_ids']);
        }

        $rows = $query->orderByDesc('ld.created_at')->get();

        $data = $rows->map(fn ($r) => [
            'loan_number' => $r->loan_number,
            'stages_url' => route('loans.stages', $r->id),
            'customer_name' => $r->customer_name,
            'bank_product' => trim(($r->bank_name ?? '—').($r->product_name ? ' / '.$r->product_name : '')),
            'branch_name' => $r->branch_name ?? '—',
            'advisor_name' => $r->advisor_name ?? '—',
            'loan_amount' => $r->loan_amount ? NumberToWordsService::formatCurrency($r->loan_amount) : '—',
            'sanctioned_amount' => $r->sanctioned_amount ? NumberToWordsService::formatCurrency($r->sanctioned_amount) : '—',
            'disbursed_amount' => $r->disbursed_amount ? NumberToWordsService::formatCurrency($r->disbursed_amount) : '—',
            'sanctioned_on' => $r->sanctioned_on ? date('d/m/Y', strtotime($r->sanctioned_on)) : '—',
            'disbursed_on' => $r->disbursed_on ? date('d/m/Y', strtotime($r->disbursed_on)) : '—',
            'status' => $r->status,
        ]);

        return response()->json([
            'data' => $data->values(),
            'totals' => [
                'count' => $rows->count(),
                'sanctioned' => NumberToWordsService::formatCurrency((int) $rows->sum('sanctioned_amount')),
                'disbursed' => NumberToWordsService::formatCurrency((int) $rows->sum('disbursed_amount')),
            ],
        ]);
    }

    // ── Shared helpers ──

    /**
     * Stage lines for the pipeline: in-progress assignments plus pending
     * sub-stages whose parent parallel block is in progress.
     *
     * @param  Collection<int, int>  $loanIds
     */
    private function stageLines(Collection $loanIds): Collection
    {
        return DB::table('stage_assignments as sa')
            ->join('stages as s', 's.stage_key', '=', 'sa.stage_key')
            ->leftJoin('users as u', 'u.id', '=', 'sa.assigned_to')
            ->leftJoin('stage_assignments as psa', function ($j) {
                $j->on('psa.loan_id', '=', 'sa.loan_id')->on('psa.stage_key', '=', 's.parent_stage_key');
            })
            ->whereIn('sa.loan_id', $loanIds)
            // The parallel container itself is not a work item — its
            // sub-stages are the lines that matter.
            ->where('s.stage_type', '!=', 'parallel')
            ->where(function ($q) {
                $q->where('sa.status', 'in_progress')
                    ->orWhere(function ($qq) {
                        $qq->where('sa.status', 'pending')
                            ->whereNotNull('s.parent_stage_key')
                            ->where('psa.status', 'in_progress');
                    });
            })
            ->orderBy('s.sequence_order')
            ->select([
                'sa.loan_id', 'sa.stage_key', 's.stage_name_en', 'sa.status', 'sa.assigned_to',
                'u.name as owner_name', 'sa.started_at', 'psa.started_at as parent_started_at',
                DB::raw('(SELECT MAX(st.created_at) FROM stage_transfers st WHERE st.stage_assignment_id = sa.id AND st.transferred_to = sa.assigned_to) as held_since'),
                DB::raw("(SELECT COUNT(*) FROM stage_queries sq WHERE sq.stage_assignment_id = sa.id AND sq.status IN ('pending', 'responded')) as open_queries"),
            ])
            ->get()
            ->groupBy('loan_id');
    }

    /**
     * Loan completion time = last completed stage's completed_at. Never use
     * ld.updated_at — it bumps on any later touch and inflates the TAT.
     */
    private function completionSub(): Builder
    {
        return DB::table('stage_assignments')
            ->select('loan_id', DB::raw('MAX(completed_at) as done_at'))
            ->where('status', 'completed')
            ->groupBy('loan_id');
    }

    /**
     * Branch + period constraints shared by the management sections.
     *
     * @param  array<int>|null  $branchIds
     */
    private function scopeBranchPeriod($query, string $alias, ?array $branchIds, ?Carbon $from, ?Carbon $to): void
    {
        if ($branchIds !== null) {
            $query->whereIn("{$alias}.branch_id", $branchIds);
        }
        if ($from) {
            $query->where("{$alias}.created_at", '>=', $from);
        }
        if ($to) {
            $query->where("{$alias}.created_at", '<=', $to);
        }
    }

    /**
     * Branch/user filter options for the report pages, per scope.
     *
     * @return array{0: Collection, 1: Collection}
     */
    private function filterOptions(array $scope): array
    {
        if ($scope['type'] === 'all') {
            $branches = Branch::active()->orderBy('name')->get();
            $users = User::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['loan_advisor', 'branch_manager', 'bdh', 'bank_employee', 'office_employee']))
                ->orderBy('name')->get();
        } else {
            $branches = Branch::active()->whereIn('id', $scope['branch_ids'])->orderBy('name')->get();
            $users = User::where('is_active', true)
                ->whereHas('branches', fn ($q) => $q->whereIn('branches.id', $scope['branch_ids']))
                ->orderBy('name')->get();
        }

        return [$branches, $users];
    }

    private function applyFilters($query, Request $request, string $loanAlias, ?string $userColumn = null): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate("{$loanAlias}.created_at", '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate("{$loanAlias}.created_at", '<=', $request->date_to);
        }
        if ($request->filled('bank_id')) {
            $query->where("{$loanAlias}.bank_id", $request->bank_id);
        }
        if ($request->filled('product_id')) {
            $query->where("{$loanAlias}.product_id", $request->product_id);
        }
        if ($request->filled('branch_id')) {
            $query->where("{$loanAlias}.branch_id", $request->branch_id);
        }
        if ($request->filled('user_id')) {
            // Cast to int — the COALESCE expression has no column affinity, so
            // a string binding would silently match nothing on SQLite.
            $userIds = array_map('intval', is_array($request->user_id) ? $request->user_id : [$request->user_id]);
            if ($userColumn) {
                $query->whereIn($userColumn, $userIds);
            } else {
                // Advisor attribution with creator fallback — same rule as the
                // rest of the app.
                $query->whereIn(DB::raw("COALESCE({$loanAlias}.assigned_advisor, {$loanAlias}.created_by)"), $userIds);
            }
        }
    }

    /**
     * Role gate for all report pages — role-based, no permission slug.
     */
    private function authorizeReports(): User
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['super_admin', 'admin', 'bdh', 'branch_manager']), 403);

        return $user;
    }

    /**
     * Report data scope. BDH sees ALL branches (explicit requirement —
     * see report_plan.md).
     */
    private function reportScope(User $user): array
    {
        if ($user->isSuperAdmin() || $user->hasAnyRole(['admin', 'bdh'])) {
            return ['type' => 'all'];
        }

        // branch_manager (the only remaining role past the gate)
        return ['type' => 'branch', 'branch_ids' => $user->branches()->pluck('branches.id')->toArray()];
    }
}
