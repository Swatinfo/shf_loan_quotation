<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Stage;
use App\Models\User;
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
        $stages = Stage::enabled()->mainStages()->orderBy('sequence_order')->get();

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

        return view('reports.turnaround', compact('banks', 'products', 'branches', 'stages', 'users', 'scope'));
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
        $query = DB::table('loan_details as ld')
            ->join('users as u', 'u.id', '=', 'ld.assigned_advisor')
            ->where('ld.status', 'completed')
            ->whereNull('ld.deleted_at')
            ->select([
                'u.id as user_id',
                'u.name as user_name',
                'ld.bank_name',
                'ld.bank_id',
                DB::raw('COUNT(*) as total_loans'),
                DB::raw('MIN(DATEDIFF(ld.updated_at, ld.created_at)) as min_days'),
                DB::raw('ROUND(AVG(DATEDIFF(ld.updated_at, ld.created_at)), 1) as avg_days'),
                DB::raw('MAX(DATEDIFF(ld.updated_at, ld.created_at)) as max_days'),
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
                DB::raw('MIN(DATEDIFF(sa.completed_at, sa.started_at)) as min_days'),
                DB::raw('ROUND(AVG(DATEDIFF(sa.completed_at, sa.started_at)), 1) as avg_days'),
                DB::raw('MAX(DATEDIFF(sa.completed_at, sa.started_at)) as max_days'),
                DB::raw('MIN(TIMESTAMPDIFF(HOUR, sa.started_at, sa.completed_at)) as min_hours'),
                DB::raw('ROUND(AVG(TIMESTAMPDIFF(HOUR, sa.started_at, sa.completed_at)), 1) as avg_hours'),
                DB::raw('MAX(TIMESTAMPDIFF(HOUR, sa.started_at, sa.completed_at)) as max_hours'),
            ])
            ->groupBy('u.id', 'u.name', 'ld.bank_name', 'ld.bank_id', 'sa.stage_key', 's.stage_name_en', 's.sequence_order');

        $this->applyFilters($query, $request, 'ld');
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

    private function applyFilters($query, Request $request, string $loanAlias): void
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
            $userIds = is_array($request->user_id) ? $request->user_id : [$request->user_id];
            $query->whereIn("{$loanAlias}.assigned_advisor", $userIds);
        }
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
                $q->where("{$loanAlias}.assigned_advisor", $user->id)
                    ->orWhere("{$stageAlias}.assigned_to", $user->id);
            });
        } else {
            $query->where("{$loanAlias}.assigned_advisor", $user->id);
        }
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
