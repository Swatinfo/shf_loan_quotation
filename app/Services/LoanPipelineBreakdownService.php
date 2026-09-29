<?php

namespace App\Services;

use App\Models\LoanDetail;
use App\Models\StageAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the dashboard "Stage status breakdown" block: a stage-wise funnel where
 * each workflow stage is split into mutually-exclusive status buckets, every bucket
 * carrying a loan count + summed ₹ amount, scoped by (own | branch | all) and an
 * optional target user.
 *
 * Each bucket is date-filtered by its OWN stage event (sanction/technical/legal
 * completed_at or started_at, tranche disbursement_date, query raised date, …), with
 * a loan.created_at fallback for pending placeholders that have no event yet. This
 * mirrors the Management report's milestone dating (so e.g. disbursement reconciles).
 * Classification is single-pass in PHP (one fetch per scope, then precedence rules)
 * so buckets within a section never overlap. See tasks/todo.md for the locked spec.
 */
class LoanPipelineBreakdownService
{
    /** Calendar periods. 'all' = no lower bound, 'custom' = explicit from/to. */
    public const PERIODS = ['month', 'last_month', 'quarter', 'half', 'all', 'custom'];

    public const DEFAULT_PERIOD = 'month';

    private const CACHE_TTL = 60; // seconds

    /**
     * Which scope blocks a requester may see when NO user filter is applied.
     *
     * @return array<int,array{scope:string,label:string}>
     */
    public function allowedScopes(User $requester): array
    {
        if ($requester->hasPermission('view_all_loans')) {
            return [['scope' => 'all', 'label' => 'All data']];
        }

        if ($requester->hasRole('branch_manager') || $requester->hasRole('bdh')) {
            return [
                ['scope' => 'own', 'label' => 'My data'],
                ['scope' => 'branch', 'label' => 'My Branch'],
            ];
        }

        return [['scope' => 'own', 'label' => 'My data']];
    }

    /** Whether this requester gets a user-filter dropdown at all. */
    public function canFilterByUser(User $requester): bool
    {
        return $requester->hasPermission('view_all_loans')
            || $requester->hasRole('branch_manager')
            || $requester->hasRole('bdh');
    }

    /**
     * Users selectable in the dropdown: all active users for view_all_loans holders,
     * branch users for BM/BDH, none for everyone else.
     *
     * @return array<int,array{id:int,name:string}>
     */
    public function userOptions(User $requester): array
    {
        if (! $this->canFilterByUser($requester)) {
            return [];
        }

        $query = User::query()->where('is_active', true);

        if (! $requester->hasPermission('view_all_loans')) {
            $branchIds = $requester->branches()->pluck('branches.id')->all();
            if (empty($branchIds)) {
                return [];
            }
            $query->whereHas('branches', fn ($b) => $b->whereIn('branches.id', $branchIds));
        }

        return $query->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /**
     * Full block payload for the requester at the given period / optional target user.
     *
     * @return array{range:array{from:?string,to:string,label:string},blocks:array<int,array<string,mixed>>}
     */
    public function build(User $requester, string $period = self::DEFAULT_PERIOD, ?int $userId = null, ?string $from = null, ?string $to = null): array
    {
        $window = $this->resolveWindow($period, $from, $to);
        $userId = $this->resolveTargetUser($requester, $userId);

        $blocks = [];
        if ($userId !== null) {
            // A specific user was picked → show that user's OWN involvement, one block.
            $name = User::find($userId)?->name ?? 'User';
            $blocks[] = $this->buildBlock($requester, 'own', $userId, $window, 'Selected: '.$name);
        } else {
            foreach ($this->allowedScopes($requester) as $s) {
                $blocks[] = $this->buildBlock($requester, $s['scope'], null, $window, $s['label']);
            }
        }

        return [
            'range' => ['from' => $window['from'], 'to' => $window['to'], 'label' => $window['label']],
            'blocks' => $blocks,
        ];
    }

    /**
     * Loan IDs classified into one bucket — powers the exact click-through from a tile.
     *
     * @return array<int,int>
     */
    public function loanIdsFor(User $requester, string $scope, ?int $userId, string $period, string $section, string $bucket, ?string $from = null, ?string $to = null): array
    {
        $window = $this->resolveWindow($period, $from, $to);
        $userId = $this->resolveTargetUser($requester, $userId);
        $scope = $this->authorizeScope($requester, $scope, $userId);

        $ids = [];
        foreach ($this->loans($requester, $scope, $userId, $window) as $loan) {
            $classified = $this->classifyLoan($loan)[$section] ?? null;
            // The derived "Total Disbursed" tile matches any loan in Entry OR OTC.
            $matches = $bucket === 'total' && $section === 'disbursement'
                ? in_array($classified, ['entry', 'otc'], true)
                : $classified === $bucket;

            if ($matches && $this->bucketInWindow($loan, $section, $bucket, $window)) {
                $ids[] = (int) $loan->id;
            }
        }

        return $ids;
    }

    // ── internals ──────────────────────────────────────────────────────────

    /**
     * Validate a picked user id against the requester's allowed population.
     * Returns the id if allowed, otherwise null (silently ignored — never leaks other data).
     */
    private function resolveTargetUser(User $requester, ?int $userId): ?int
    {
        if ($userId === null || ! $this->canFilterByUser($requester)) {
            return null;
        }

        if ($requester->hasPermission('view_all_loans')) {
            return User::where('id', $userId)->where('is_active', true)->exists() ? $userId : null;
        }

        $branchIds = $requester->branches()->pluck('branches.id')->all();
        if (empty($branchIds)) {
            return null;
        }
        $inBranch = User::where('id', $userId)->where('is_active', true)
            ->whereHas('branches', fn ($b) => $b->whereIn('branches.id', $branchIds))
            ->exists();

        return $inBranch ? $userId : null;
    }

    /** Reject a scope the requester may not view (falls back to their narrowest allowed scope). */
    private function authorizeScope(User $requester, string $scope, ?int $userId): string
    {
        if ($userId !== null) {
            return 'own';
        }
        $allowed = array_column($this->allowedScopes($requester), 'scope');

        return in_array($scope, $allowed, true) ? $scope : $allowed[0];
    }

    /**
     * Resolve the created-at cohort window from a preset period or an explicit range.
     *
     * @return array{period:string,from:?string,to:?string,label:string}
     */
    private function resolveWindow(string $period, ?string $from, ?string $to): array
    {
        $now = CarbonImmutable::now();
        $period = in_array($period, self::PERIODS, true) ? $period : self::DEFAULT_PERIOD;

        if ($period === 'custom' && ($from || $to)) {
            $f = $from ? CarbonImmutable::parse($from)->startOfDay() : null;
            $t = $to ? CarbonImmutable::parse($to)->endOfDay() : $now->endOfDay();
            $label = ($f ? $f->format('d M Y') : '…').' – '.$t->format('d M Y');

            return ['period' => 'custom', 'from' => $f?->toDateString(), 'to' => $t->toDateString(), 'label' => $label];
        }

        if ($period === 'all') {
            return ['period' => 'all', 'from' => null, 'to' => null, 'label' => 'All time (up to '.$now->format('d M Y').')'];
        }

        // Calendar windows.
        [$f, $t] = match ($period) {
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$now->startOfQuarter(), $now->endOfQuarter()],
            'half' => $this->halfYear($now),
            default => [$now->startOfMonth(), $now->endOfMonth()], // 'month'
        };
        $period = in_array($period, ['last_month', 'quarter', 'half'], true) ? $period : 'month';

        return [
            'period' => $period,
            'from' => $f->toDateString(),
            'to' => $t->toDateString(),
            'label' => $f->format('d M Y').' – '.$t->format('d M Y'),
        ];
    }

    /**
     * Current half-year: Jan 1–Jun 30, or Jul 1–Dec 31.
     *
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    private function halfYear(CarbonImmutable $now): array
    {
        if ($now->month <= 6) {
            return [$now->startOfYear(), $now->startOfYear()->addMonths(6)->subDay()->endOfDay()];
        }

        return [$now->startOfYear()->addMonths(6), $now->endOfYear()];
    }

    /**
     * @param  array{period:string,from:?string,to:?string,label:string}  $window
     */
    private function buildBlock(User $requester, string $scope, ?int $userId, array $window, string $label): array
    {
        $key = sprintf('shf.stagebrk.%d.%s.%s.%s.%s.%s', $requester->id, $scope, $userId ?? 0, $window['period'], $window['from'] ?? '-', $window['to'] ?? '-');

        return Cache::remember($key, self::CACHE_TTL, function () use ($requester, $scope, $userId, $window, $label) {
            $loans = $this->loans($requester, $scope, $userId, $window);

            return $this->aggregate($loans, $scope, $userId, $window, $label);
        });
    }

    /**
     * Fetch the cohort of loans for a scope, eager-loading everything the classifier needs.
     *
     * @param  array{period:string,from:?string,to:?string,label:string}  $window
     * @return Collection<int,LoanDetail>
     */
    private function loans(User $requester, string $scope, ?int $userId, array $window): Collection
    {
        // NOTE: no created_at filter here — each bucket is date-gated by its OWN stage
        // event date (see bucketInWindow), with a created_at fallback for pending rows.
        $query = LoanDetail::query()
            ->select(['id', 'loan_amount', 'sanctioned_amount', 'status', 'current_stage', 'created_at', 'status_changed_at', 'rejected_at'])
            ->with([
                'stageAssignments:id,loan_id,stage_key,status,started_at,completed_at',
                'stageQueries' => fn ($q) => $q->active()->select(['id', 'loan_id', 'stage_key', 'status', 'created_at']),
                'disbursementEntries:id,loan_id,amount,disbursement_date',
            ]);

        $this->applyScope($query, $requester, $scope, $userId);

        return $query->get();
    }

    private function applyScope(Builder $query, User $requester, string $scope, ?int $userId): void
    {
        if ($scope === 'all') {
            return; // no constraint — only reachable with view_all_loans
        }

        if ($scope === 'branch') {
            $branchIds = $requester->branches()->pluck('branches.id')->all();
            $query->whereIn('branch_id', $branchIds ?: [-1]);

            return;
        }

        // own — the target user's personal involvement (self by default)
        $targetId = $userId ?? $requester->id;
        $query->where(function ($q) use ($targetId) {
            $q->where('created_by', $targetId)
                ->orWhere('assigned_advisor', $targetId)
                ->orWhereHas('stageAssignments', fn ($s) => $s->where('assigned_to', $targetId))
                ->orWhereHas('stageTransfers', fn ($s) => $s->where('transferred_from', $targetId)->orWhere('transferred_to', $targetId));
        });
    }

    /**
     * Roll the classified cohort up into the block's sections + bucket tiles.
     *
     * @param  array{period:string,from:?string,to:?string,label:string}  $window
     */
    private function aggregate(Collection $loans, string $scope, ?int $userId, array $window, string $label): array
    {
        $defs = $this->sectionDefs();

        // seed accumulators
        $acc = [];
        foreach ($defs as $sectionKey => $section) {
            foreach ($section['buckets'] as $bucketKey => $bucket) {
                $acc[$sectionKey][$bucketKey] = ['count' => 0, 'amount' => 0];
            }
        }

        $totalLoanIds = [];
        $totalAmount = 0;
        foreach ($loans as $loan) {
            $buckets = $this->classifyLoan($loan);
            $counted = false;
            foreach ($buckets as $sectionKey => $bucketKey) {
                if ($bucketKey === null || ! $this->bucketInWindow($loan, $sectionKey, $bucketKey, $window)) {
                    continue;
                }
                $acc[$sectionKey][$bucketKey]['count']++;
                $acc[$sectionKey][$bucketKey]['amount'] += $this->bucketAmount($loan, $sectionKey, $bucketKey, $window);
                $counted = true;
            }
            // Block total = distinct loans that appear in ≥1 in-window bucket.
            if ($counted && ! isset($totalLoanIds[$loan->id])) {
                $totalLoanIds[$loan->id] = true;
                $totalAmount += (int) ($loan->loan_amount ?? 0);
            }
        }

        // Derived "Total Disbursed" tile = Entry + OTC (they're disjoint, so no double count).
        $acc['disbursement']['total'] = [
            'count' => $acc['disbursement']['entry']['count'] + $acc['disbursement']['otc']['count'],
            'amount' => $acc['disbursement']['entry']['amount'] + $acc['disbursement']['otc']['amount'],
        ];

        $sections = [];
        foreach ($defs as $sectionKey => $section) {
            $tiles = [];
            $subCount = 0;
            $subAmount = 0;
            foreach ($section['buckets'] as $bucketKey => $bucketLabel) {
                $c = $acc[$sectionKey][$bucketKey]['count'];
                $a = $acc[$sectionKey][$bucketKey]['amount'];
                // 'total' is a derived summary (Entry+OTC) — render it, but don't let it
                // double-count into the section subtotal.
                $derived = $bucketKey === 'total';
                if (! $derived) {
                    $subCount += $c;
                    $subAmount += $a;
                }
                $tiles[] = [
                    'key' => $bucketKey,
                    'label' => $bucketLabel,
                    'count' => $c,
                    'amount' => $a,
                    'derived' => $derived,
                    'url' => route('loans.index', array_filter([
                        'brk_section' => $sectionKey,
                        'brk_bucket' => $bucketKey,
                        'brk_period' => $window['period'],
                        'brk_from' => $window['period'] === 'custom' ? $window['from'] : null,
                        'brk_to' => $window['period'] === 'custom' ? $window['to'] : null,
                        'brk_scope' => $scope,
                        'brk_user' => $userId,
                    ], fn ($v) => $v !== null && $v !== '')),
                ];
            }
            $sections[] = [
                'key' => $sectionKey,
                'label' => $section['label'],
                'subtotalCount' => $subCount,
                'subtotalAmount' => $subAmount,
                'buckets' => $tiles,
            ];
        }

        return [
            'scope' => $scope,
            'label' => $label,
            'totalCount' => count($totalLoanIds),
            'totalAmount' => $totalAmount,
            'sections' => $sections,
        ];
    }

    /**
     * Classify one loan into (at most) one bucket per section, by precedence.
     *
     * @return array<string,?string> section-key → bucket-key|null
     */
    private function classifyLoan(LoanDetail $loan): array
    {
        $assign = [];
        foreach ($loan->stageAssignments as $a) {
            // one row per stage_key; if duplicated, keep the furthest-progressed status
            $rank = ['pending' => 1, 'in_progress' => 2, 'rejected' => 3, 'completed' => 4, 'skipped' => 0];
            if (! isset($assign[$a->stage_key]) || ($rank[$a->status] ?? 0) > ($rank[$assign[$a->stage_key]] ?? 0)) {
                $assign[$a->stage_key] = $a->status;
            }
        }

        $activeQueryKeys = $loan->stageQueries->pluck('stage_key')->unique()->all();
        $hasQuery = fn (string $key) => in_array($key, $activeQueryKeys, true);
        $currentStage = (string) $loan->current_stage;

        return [
            'sanction' => $this->classifySanction($loan, $assign, $hasQuery, $currentStage),
            'technical' => $this->classifyStageStatus($assign, 'technical_valuation', $hasQuery, $currentStage),
            'legal' => $this->classifyStageStatus($assign, 'legal_verification', $hasQuery, $currentStage),
            'disbursement' => $this->classifyDisbursement($loan, $assign),
        ];
    }

    /**
     * A stage's `pending` assignment is pre-created at loan init (started_at null) for
     * every stage, so "pending" alone does NOT mean the loan reached that stage. A loan
     * only genuinely sits at the parallel sub-stages (sanction_decision / technical /
     * legal) while `current_stage === 'parallel_processing'`; before that the pending row
     * is just a placeholder and must not be counted.
     *
     * @param  array<string,string>  $assign
     */
    private function classifySanction(LoanDetail $loan, array $assign, callable $hasQuery, string $currentStage): ?string
    {
        $sanction = $assign['sanction_decision'] ?? null;

        // Loan-level outcomes apply regardless of which stage the loan sits at.
        if ($loan->status === 'cancelled') {
            return 'withdrawn';
        }
        if ($sanction === 'rejected' || $loan->status === 'rejected') {
            return 'rejected';
        }
        if ($loan->status === 'on_hold') {
            return 'hold';
        }
        if ($hasQuery('sanction_decision')) {
            return 'query';
        }
        if ($sanction === 'completed' && ! $this->isDisbursed($loan)) {
            return 'sanctioned';
        }
        if ($sanction === 'in_progress') {
            return 'sip';
        }
        // pending decision only counts once the loan is actually in the parallel phase
        if ($sanction === 'pending' && $currentStage === 'parallel_processing') {
            return 'sip';
        }

        return null;
    }

    /**
     * Technical / Legal share identical logic on their own stage_key.
     *
     * @param  array<string,string>  $assign
     */
    private function classifyStageStatus(array $assign, string $stageKey, callable $hasQuery, string $currentStage): ?string
    {
        $status = $assign[$stageKey] ?? null;
        if ($status === null) {
            return null; // no assignment row at all
        }
        if ($status === 'rejected') {
            return 'rejected';
        }
        if ($status === 'completed') {
            return 'completed';
        }
        if ($hasQuery($stageKey)) {
            return 'query';
        }
        if ($status === 'in_progress') {
            return 'under_process';
        }

        // pending — a genuine "Not Initiated" only once the loan is in the parallel
        // phase; a pre-parallel placeholder assignment must not be counted here.
        return $currentStage === 'parallel_processing' ? 'not_initiated' : null;
    }

    /**
     * @param  array<string,string>  $assign
     */
    private function classifyDisbursement(LoanDetail $loan, array $assign): ?string
    {
        // OTC = cleared (completed) OR skipped (non-cheque loans) OR the loan is fully
        // completed — so every completed loan lands in OTC Clearance.
        if ($loan->status === 'completed' || in_array($assign['otc_clearance'] ?? null, ['completed', 'skipped'], true)) {
            return 'otc';
        }
        if ($loan->disbursementEntries->isNotEmpty()) {
            return 'entry';
        }
        // Docket is a sequential stage AFTER parallel processing. Its pending row is a
        // pre-created placeholder (loan hasn't reached docket) — only `in_progress`
        // means the loan is actually sitting at docket ("spill").
        $docket = $assign['docket'] ?? null;
        if ($docket === 'completed') {
            return 'logged_in';
        }
        if ($docket === 'in_progress') {
            return 'spill';
        }

        return null;
    }

    private function isDisbursed(LoanDetail $loan): bool
    {
        return $loan->disbursementEntries->isNotEmpty();
    }

    /**
     * Σ of tranche amounts whose disbursement_date falls inside the window
     * (the Management-report basis — this is what makes "Cheque/Transfer Entry" reconcile).
     *
     * @param  array{period:string,from:?string,to:?string,label:string}  $window
     */
    private function windowedTrancheAmount(LoanDetail $loan, array $window): int
    {
        $from = $window['from'] ? CarbonImmutable::parse($window['from'])->startOfDay() : null;
        $to = $window['to'] ? CarbonImmutable::parse($window['to'])->endOfDay() : null;

        return (int) $loan->disbursementEntries
            ->filter(function ($e) use ($from, $to) {
                if ($e->disbursement_date === null) {
                    return false;
                }
                $d = CarbonImmutable::parse($e->disbursement_date);

                return ($from === null || $d >= $from) && ($to === null || $d <= $to);
            })
            ->sum('amount');
    }

    /**
     * Amount summed for a given bucket (per the locked spec).
     *
     * @param  array{period:string,from:?string,to:?string,label:string}  $window
     */
    private function bucketAmount(LoanDetail $loan, string $section, string $bucket, array $window): int
    {
        if ($section === 'disbursement') {
            return match ($bucket) {
                // Both disbursed-money buckets sum only the tranches dated in the window,
                // so Entry + OTC = the Management report's "Disbursed" for that window.
                'entry', 'otc' => $this->windowedTrancheAmount($loan, $window),
                // Sanctioned amount for the docket phase; fall back to the requested loan
                // amount when a loan carries no sanctioned figure yet (never ₹0 for a real loan).
                'spill', 'logged_in' => (int) ($loan->sanctioned_amount ?: $loan->loan_amount ?: 0),
                default => 0,
            };
        }

        return (int) ($loan->loan_amount ?? 0);
    }

    // ── per-bucket date gating ───────────────────────────────────────────────

    /**
     * Whether a loan's classified bucket falls inside the window, dated by that
     * bucket's OWN stage event (with a created_at fallback for pending placeholders).
     *
     * @param  array{period:string,from:?string,to:?string,label:string}  $window
     */
    private function bucketInWindow(LoanDetail $loan, string $section, string $bucket, array $window): bool
    {
        // The disbursed-money buckets (and the derived Total) are dated per-tranche:
        // include iff ≥1 tranche in window (so they reconcile with the report's "Disbursed").
        if ($section === 'disbursement' && in_array($bucket, ['entry', 'otc', 'total'], true)) {
            return $this->hasTrancheInWindow($loan, $window);
        }

        return $this->inWindow($this->bucketDate($loan, $section, $bucket), $window);
    }

    /**
     * The event date that a bucket is filtered by, falling back to loan.created_at
     * when the stage event has not happened yet (pending placeholders).
     */
    private function bucketDate(LoanDetail $loan, string $section, string $bucket): CarbonImmutable
    {
        $byKey = $this->assignmentsByKey($loan);
        $started = fn (string $k) => isset($byKey[$k]) && $byKey[$k]->started_at ? CarbonImmutable::parse($byKey[$k]->started_at) : null;
        $completed = fn (string $k) => isset($byKey[$k]) && $byKey[$k]->completed_at ? CarbonImmutable::parse($byKey[$k]->completed_at) : null;
        $queryDate = fn (string $k) => $this->latestActiveQueryDate($loan, $k);
        $statusChanged = $loan->status_changed_at ? CarbonImmutable::parse($loan->status_changed_at) : null;
        $rejectedAt = $loan->rejected_at ? CarbonImmutable::parse($loan->rejected_at) : null;

        $date = match ($section.'.'.$bucket) {
            'sanction.sip' => $started('sanction_decision'),          // pending → null → created_at
            'sanction.query' => $queryDate('sanction_decision'),
            'sanction.sanctioned' => $completed('sanction_decision'),
            'sanction.hold', 'sanction.withdrawn' => $statusChanged,
            'sanction.rejected' => $rejectedAt ?? $completed('sanction_decision'),
            'technical.under_process' => $started('technical_valuation'),
            'technical.completed', 'technical.rejected' => $completed('technical_valuation'),
            'technical.query' => $queryDate('technical_valuation'),
            'legal.under_process' => $started('legal_verification'),
            'legal.completed', 'legal.rejected' => $completed('legal_verification'),
            'legal.query' => $queryDate('legal_verification'),
            'disbursement.spill' => $started('docket'),
            'disbursement.logged_in' => $completed('docket'),
            // entry + otc are tranche-dated in bucketInWindow (not here).
            // not_initiated (pending) and anything else → loan creation date
            default => null,
        };

        return $date ?? $this->loanCreatedAt($loan);
    }

    private function loanCreatedAt(LoanDetail $loan): CarbonImmutable
    {
        return CarbonImmutable::parse($loan->created_at);
    }

    /**
     * @param  array{period:string,from:?string,to:?string,label:string}  $window
     */
    private function inWindow(CarbonImmutable $date, array $window): bool
    {
        if ($window['from'] !== null && $date->lt(CarbonImmutable::parse($window['from'])->startOfDay())) {
            return false;
        }
        if ($window['to'] !== null && $date->gt(CarbonImmutable::parse($window['to'])->endOfDay())) {
            return false;
        }

        return true;
    }

    /**
     * Assignments indexed by stage_key (furthest-progressed kept), giving access to
     * started_at/completed_at for dating.
     *
     * @return array<string,StageAssignment>
     */
    private function assignmentsByKey(LoanDetail $loan): array
    {
        $rank = ['pending' => 1, 'in_progress' => 2, 'rejected' => 3, 'completed' => 4, 'skipped' => 0];
        $byKey = [];
        foreach ($loan->stageAssignments as $a) {
            if (! isset($byKey[$a->stage_key]) || ($rank[$a->status] ?? 0) > ($rank[$byKey[$a->stage_key]->status] ?? 0)) {
                $byKey[$a->stage_key] = $a;
            }
        }

        return $byKey;
    }

    /**
     * @param  array{period:string,from:?string,to:?string,label:string}  $window
     */
    private function hasTrancheInWindow(LoanDetail $loan, array $window): bool
    {
        $from = $window['from'] ? CarbonImmutable::parse($window['from'])->startOfDay() : null;
        $to = $window['to'] ? CarbonImmutable::parse($window['to'])->endOfDay() : null;

        return $loan->disbursementEntries->contains(function ($e) use ($from, $to) {
            if ($e->disbursement_date === null) {
                return false;
            }
            $d = CarbonImmutable::parse($e->disbursement_date);

            return ($from === null || $d >= $from) && ($to === null || $d <= $to);
        });
    }

    private function latestActiveQueryDate(LoanDetail $loan, string $stageKey): ?CarbonImmutable
    {
        $dates = $loan->stageQueries
            ->where('stage_key', $stageKey)
            ->pluck('created_at')
            ->filter()
            ->map(fn ($d) => CarbonImmutable::parse($d));

        return $dates->isNotEmpty() ? $dates->max() : null;
    }

    /**
     * Section + bucket definitions, in display order.
     *
     * @return array<string,array{label:string,buckets:array<string,string>}>
     */
    private function sectionDefs(): array
    {
        return [
            'sanction' => [
                'label' => 'Sanction cases',
                'buckets' => [
                    'sip' => 'SIP (decision pending)',
                    'query' => 'Query',
                    'sanctioned' => 'Sanctioned',
                    'hold' => 'Hold',
                    'withdrawn' => 'Withdrawn',
                    'rejected' => 'Rejected',
                ],
            ],
            'technical' => [
                'label' => 'Technical',
                'buckets' => [
                    'not_initiated' => 'Not Initiated',
                    'under_process' => 'Under Process',
                    'completed' => 'Completed',
                    'query' => 'Query',
                    'rejected' => 'Rejected',
                ],
            ],
            'legal' => [
                'label' => 'Legal',
                'buckets' => [
                    'not_initiated' => 'Not Initiated',
                    'under_process' => 'Under Process',
                    'completed' => 'Completed',
                    'query' => 'Query',
                    'rejected' => 'Rejected',
                ],
            ],
            'disbursement' => [
                'label' => 'Disbursement',
                'buckets' => [
                    'spill' => 'Spill (docket pending)',
                    'logged_in' => 'Logged In',
                    'entry' => 'Cheque / Transfer Entry',
                    'otc' => 'OTC Clearance',
                    // Derived summary tile = Entry + OTC (all disbursed money in window).
                    // Excluded from the section subtotal; reconciles with the Management report.
                    'total' => 'Total Disbursed',
                ],
            ],
        ];
    }
}
