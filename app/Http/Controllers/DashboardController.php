<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Stats for the dashboard cards
        $statsQuery = Quotation::query();
        if (! $user->hasPermission('view_all_quotations')) {
            $statsQuery->where('user_id', $user->id);
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'today' => (clone $statsQuery)->whereDate('created_at', today())->count(),
            'this_month' => (clone $statsQuery)->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
        ];

        // Get users for the "Created By" filter (admin/super_admin only)
        $users = [];
        if ($user->hasPermission('view_all_quotations')) {
            $users = User::select('id', 'name')->orderBy('name')->get();
        }

        $permissions = [
            'view_all' => $user->hasPermission('view_all_quotations'),
            'download_pdf' => $user->hasPermission('download_pdf'),
            'delete_quotations' => $user->hasPermission('delete_quotations'),
        ];

        return view('dashboard', compact('stats', 'users', 'permissions'));
    }

    /**
     * Server-side DataTables AJAX endpoint for quotations.
     */
    public function quotationData(Request $request): JsonResponse
    {
        $user = Auth::user();
        $canViewAll = $user->hasPermission('view_all_quotations');
        $canDownload = $user->hasPermission('download_pdf');
        $canDelete = $user->hasPermission('delete_quotations');

        // Base query
        $query = Quotation::with(['user', 'banks']);

        // Permission-based scoping: staff sees own only
        if (! $canViewAll) {
            $query->where('user_id', $user->id);
        }

        // Total records (before any filtering)
        $recordsTotal = (clone $query)->count();

        // Custom filters
        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('created_by') && $canViewAll) {
            $query->where('user_id', $request->created_by);
        }

        // DataTables search
        $search = $request->input('search.value', '');
        if ($search !== '' && $search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('pdf_filename', 'like', "%{$search}%");
            });
        }

        // Filtered count (after search + custom filters, before pagination)
        $recordsFiltered = (clone $query)->count();

        // Column ordering
        $columns = ['id', 'customer_name', 'customer_type', 'loan_amount', 'banks'];
        if ($canViewAll) {
            $columns[] = 'created_by';
        }
        $columns[] = 'created_at';

        $orderColumnIndex = (int) $request->input('order.0.column', count($columns) - 1);
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';

        // Map virtual columns to actual DB columns
        $orderableMap = [
            'id' => 'id',
            'customer_name' => 'customer_name',
            'customer_type' => 'customer_type',
            'loan_amount' => 'loan_amount',
            'created_at' => 'created_at',
            'created_by' => 'user_id',
        ];

        if (isset($orderableMap[$orderColumn])) {
            $query->orderBy($orderableMap[$orderColumn], $orderDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 20);
        $quotations = $query->skip($start)->take($length)->get();

        // Format data for DataTables
        $data = $quotations->map(function ($q) use ($canViewAll, $canDownload, $canDelete) {
            $bankNames = $q->banks ? $q->banks->pluck('bank_name')->toArray() : [];

            $typeLabels = [
                'proprietor' => 'Proprietor',
                'partnership_llp' => 'Partnership/LLP',
                'pvt_ltd' => 'PVT LTD',
                'all' => 'All Types',
            ];

            $typeBadgeClass = match ($q->customer_type) {
                'proprietor' => 'shf-badge-green',
                'partnership_llp' => 'shf-badge-blue',
                'pvt_ltd' => 'shf-badge-orange',
                default => 'shf-badge-gray',
            };

            return [
                'id' => $q->id,
                'customer_name' => $q->customer_name,
                'customer_type' => $q->customer_type,
                'type_label' => $typeLabels[$q->customer_type] ?? ucfirst($q->customer_type),
                'type_badge_class' => $typeBadgeClass,
                'loan_amount' => $q->loan_amount,
                'formatted_amount' => $q->formatted_amount,
                'banks' => $bankNames,
                'created_by' => $canViewAll ? ($q->user?->name ?? '—') : null,
                'date' => $q->created_at ? $q->created_at->format('d M Y, h:i A') : '—',
                'date_raw' => $q->created_at?->toISOString(),
                'show_url' => route('quotations.show', $q->id),
                'download_url' => ($canDownload && $q->pdf_filename)
                    ? route('quotations.download-file', ['file' => $q->pdf_filename])
                    : null,
                'delete_url' => $canDelete
                    ? route('quotations.destroy', $q->id)
                    : null,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data->values(),
        ]);
    }

    public function activityLog(Request $request)
    {
        $query = ActivityLog::with('user')->orderBy('created_at', 'desc');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->action}%");
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(30)->appends($request->query());

        $users = User::select('id', 'name')->orderBy('name')->get();

        // Get unique action types for filter dropdown
        $actionTypes = ActivityLog::select('action')->distinct()->orderBy('action')->pluck('action');

        return view('activity-log', compact('logs', 'users', 'actionTypes'));
    }
}
