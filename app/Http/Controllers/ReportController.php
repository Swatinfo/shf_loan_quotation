<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Stage;
use App\Models\User;
use App\Services\NumberToWordsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function turnaround()
    {
        $user = auth()->user();
        $scope = $this->getUserScope($user);

        // Scope filter options based on role
        $banks = Bank::active()->orderBy('name')->get();
        $products = Product::active()->with('bank')->orderBy('name')->get();

        // Stage-tab data contains sub-stage rows (legal, technical, sanction
        // decision, …), so the filter must offer them too — mains in sequence,
        // each followed by its sub-stages.
        $mainStages = Stage::enabled()->mainStages()->orderBy('sequence_order')->get();
        $subStages = Stage::enabled()->whereNotNull('parent_stage_key')->orderBy('sequence_order')->get()->groupBy('parent_stage_key');
        $stages = $mainStages->flatMap(fn ($main) => collect([$main])->concat($subStages->get($main->stage_key, collect())));

        if ($scope['type'] === 'all') {
            $branches = Branch::active()->orderBy('name')->get();
            $users = User::where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('slug', ['loan_advisor', 'branch_manager', 'bdh', 'bank_employee', 'office_employee']))
                ->orderBy('name')->get();
        } elseif ($scope['type'] === 'branch') {
            $branches = Branch::active()->whereIn('id', $scope['branch_ids'])->orderBy('name')->get();
            $users = User::where('is_active', true)
                ->whereHas('branches', fn ($q) => $q->whereIn('branches.id', $scope['branch_ids']))
                ->orderBy('name')->get();
        } else {
            $branches = collect();
            $users = collect([$user]);
        }

        $template = 'newtheme.reports.turnaround';

        return view($template, compact('banks', 'products', 'branches', 'stages', 'users', 'scope'));
    }

    public function turnaroundData(Request $request): JsonResponse
    {
        $tab = $request->input('tab', 'overall');

        if ($tab === 'overall') {
            return $this->overallTatData($request);
        }

        return $this->stageTatData($request);
    }

    private function overallTatData(Request $request): JsonResponse
    {
        // Completion time = last completed stage's completed_at. Never use
        // ld.updated_at — it bumps on any later touch (edits, transfers,
        // backfills) and inflates the TAT (see report_plan.md Part 0, Bug 1).
        $completion = DB::table('stage_assignments')
            ->select('loan_id', DB::raw('MAX(completed_at) as done_at'))
            ->where('status', 'completed')
            ->groupBy('loan_id');

        $tat = $this->dayDiffSql('ld.created_at', 'fin.done_at');

        $query = DB::table('loan_details as ld')
            ->joinSub($completion, 'fin', 'fin.loan_id', '=', 'ld.id')
            ->join('users as u', DB::raw('COALESCE(ld.assigned_advisor, ld.created_by)'), '=', DB::raw('u.id'))
            ->where('ld.status', 'completed')
            ->whereNull('ld.deleted_at')
            ->select([
                'u.id as user_id',
                'u.name as user_name',
                'ld.bank_name',
                'ld.bank_id',
                DB::raw('COUNT(*) as total_loans'),
                DB::raw("MIN({$tat}) as min_days"),
                DB::raw("ROUND(AVG({$tat}), 1) as avg_days"),
                DB::raw("MAX({$tat}) as max_days"),
            ])
            ->groupBy('u.id', 'u.name', 'ld.bank_name', 'ld.bank_id');

        $this->applyFilters($query, $request, 'ld');
        $this->applyRoleScope($query, 'ld', null);

        $results = $query->orderBy('u.name')->orderBy('ld.bank_name')->get();

        $data = $results->map(fn ($r) => [
            'user_name' => $r->user_name,
            'bank_name' => $r->bank_name ?? '—',
            'total_loans' => $r->total_loans,
            'min_days' => $r->min_days.' days',
            'avg_days' => $r->avg_days.' days',
            'max_days' => $r->max_days.' days',
            'min_days_raw' => (int) $r->min_days,
            'avg_days_raw' => (float) $r->avg_days,
            'max_days_raw' => (int) $r->max_days,
        ]);

        return response()->json(['data' => $data->values()]);
    }

    private function stageTatData(Request $request): JsonResponse
    {
        $query = DB::table('stage_assignments as sa')
            ->join('loan_details as ld', 'ld.id', '=', 'sa.loan_id')
            ->join('users as u', 'u.id', '=', 'sa.assigned_to')
            ->join('stages as s', function ($join) {
                $join->on('s.stage_key', '=', 'sa.stage_key')->where('s.is_enabled', true);
            })
            ->where('sa.status', 'completed')
            ->whereNotNull('sa.started_at')
            ->whereNotNull('sa.completed_at')
            ->whereNull('ld.deleted_at')
            ->select([
                'u.id as user_id',
                'u.name as user_name',
                'ld.bank_name',
                'ld.bank_id',
                'sa.stage_key',
                's.stage_name_en',
                's.sequence_order',
                DB::raw('COUNT(*) as times_handled'),
                DB::raw("MIN({$this->dayDiffSql('sa.started_at', 'sa.completed_at')}) as min_days"),
                DB::raw("ROUND(AVG({$this->dayDiffSql('sa.started_at', 'sa.completed_at')}), 1) as avg_days"),
                DB::raw("MAX({$this->dayDiffSql('sa.started_at', 'sa.completed_at')}) as max_days"),
                DB::raw("MIN({$this->hourDiffSql('sa.started_at', 'sa.completed_at')}) as min_hours"),
                DB::raw("ROUND(AVG({$this->hourDiffSql('sa.started_at', 'sa.completed_at')}), 1) as avg_hours"),
                DB::raw("MAX({$this->hourDiffSql('sa.started_at', 'sa.completed_at')}) as max_hours"),
            ])
            ->groupBy('u.id', 'u.name', 'ld.bank_name', 'ld.bank_id', 'sa.stage_key', 's.stage_name_en', 's.sequence_order');

        // Stage rows belong to the stage assignee — the user filter must match
        // sa.assigned_to here, not the loan's advisor.
        $this->applyFilters($query, $request, 'ld', 'sa.assigned_to');
        $this->applyRoleScope($query, 'ld', 'sa');

        if ($request->filled('stage_key')) {
            $query->where('sa.stage_key', $request->stage_key);
        }

        $results = $query->orderBy('u.name')->orderBy('ld.bank_name')->orderBy('s.sequence_order')->get();

        $data = $results->map(function ($r) {
            $minLabel = $r->min_days > 0 ? $r->min_days.' days' : $r->min_hours.'h';
            $avgLabel = $r->avg_days > 0 ? $r->avg_days.' days' : $r->avg_hours.'h';
            $maxLabel = $r->max_days > 0 ? $r->max_days.' days' : $r->max_hours.'h';

            return [
                'user_name' => $r->user_name,
                'bank_name' => $r->bank_name ?? '—',
                'stage_name' => $r->stage_name_en,
                'stage_key' => $r->stage_key,
                'times_handled' => $r->times_handled,
                'min_time' => $minLabel,
                'avg_time' => $avgLabel,
                'max_time' => $maxLabel,
                'min_hours_raw' => (float) $r->min_hours,
                'avg_hours_raw' => (float) $r->avg_hours,
                'max_hours_raw' => (float) $r->max_hours,
            ];
        });

        return response()->json(['data' => $data->values()]);
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
     * Whole-day difference between two datetimes — MySQL DATEDIFF vs SQLite
     * julianday, so the report is testable on the SQLite test connection.
     */
    private function dayDiffSql(string $from, string $to): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "CAST(julianday(date({$to})) - julianday(date({$from})) AS INTEGER)";
        }

        return "DATEDIFF({$to}, {$from})";
    }

    /**
     * Whole-hour difference between two datetimes (driver-aware).
     */
    private function hourDiffSql(string $from, string $to): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "CAST((julianday({$to}) - julianday({$from})) * 24 AS INTEGER)";
        }

        return "TIMESTAMPDIFF(HOUR, {$from}, {$to})";
    }

    /**
     * Scope queries based on the current user's role.
     * - super_admin/admin: see all
     * - branch_manager/bdh: see loans from their branches
     * - loan_advisor/bank_employee/office_employee: see only their own data
     */
    private function applyRoleScope($query, string $loanAlias, ?string $stageAlias): void
    {
        $user = auth()->user();
        $scope = $this->getUserScope($user);

        if ($scope['type'] === 'all') {
            return;
        }

        if ($scope['type'] === 'branch') {
            $query->whereIn("{$loanAlias}.branch_id", $scope['branch_ids']);

            return;
        }

        // Self-only: filter by advisor on overall tab, or by stage assignee on stage tab
        if ($stageAlias) {
            $query->where(function ($q) use ($user, $loanAlias, $stageAlias) {
                $q->whereRaw("COALESCE({$loanAlias}.assigned_advisor, {$loanAlias}.created_by) = ?", [$user->id])
                    ->orWhere("{$stageAlias}.assigned_to", $user->id);
            });
        } else {
            $query->whereRaw("COALESCE({$loanAlias}.assigned_advisor, {$loanAlias}.created_by) = ?", [$user->id]);
        }
    }

    // ── Loan Report (sanctioned / disbursed) ──

    public function loanReport()
    {
        $user = $this->authorizeLoanReport();
        $scope = $this->loanReportScope($user);

        $banks = Bank::active()->orderBy('name')->get();
        $products = Product::active()->with('bank')->orderBy('name')->get();

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

        return view('newtheme.reports.loan-report', compact('banks', 'products', 'branches', 'users', 'scope'));
    }

    public function loanReportData(Request $request): JsonResponse
    {
        $user = $this->authorizeLoanReport();
        $scope = $this->loanReportScope($user);

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

    /**
     * Role gate for the loan report — role-based, no permission slug.
     */
    private function authorizeLoanReport(): User
    {
        $user = auth()->user();
        abort_unless($user && $user->hasAnyRole(['super_admin', 'admin', 'bdh', 'branch_manager']), 403);

        return $user;
    }

    /**
     * Loan-report scope. Unlike getUserScope(), BDH sees ALL branches here
     * (explicit requirement — see report_plan.md).
     */
    private function loanReportScope(User $user): array
    {
        if ($user->isSuperAdmin() || $user->hasAnyRole(['admin', 'bdh'])) {
            return ['type' => 'all'];
        }

        // branch_manager (the only remaining role past the gate)
        return ['type' => 'branch', 'branch_ids' => $user->branches()->pluck('branches.id')->toArray()];
    }

    /**
     * Determine the user's data scope for reports.
     */
    private function getUserScope(User $user): array
    {
        if ($user->isSuperAdmin() || $user->hasAnyRole(['admin'])) {
            return ['type' => 'all'];
        }

        if ($user->hasAnyRole(['branch_manager', 'bdh'])) {
            $branchIds = $user->branches()->pluck('branches.id')->toArray();
            if (! empty($branchIds)) {
                return ['type' => 'branch', 'branch_ids' => $branchIds];
            }
        }

        return ['type' => 'self', 'user_id' => $user->id];
    }
}
