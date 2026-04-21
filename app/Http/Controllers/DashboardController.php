<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Bank;
use App\Models\DailyVisitReport;
use App\Models\GeneralTask;
use App\Models\LoanDetail;
use App\Models\Quotation;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\StageQuery;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        return view('newtheme.dashboard', [
            'payload' => $this->newthemePayload($user),
            'pageKey' => 'dashboard',
        ]);
    }

    /**
     * Server-side DataTables AJAX endpoint for quotations.
     */
    public function quotationData(Request $request): JsonResponse
    {
        $user = Auth::user();
        $canViewAll = $user->hasPermission('view_all_quotations');
        $canDownload = $user->hasPermission('download_pdf');
        $canDownloadBranded = $user->hasPermission('download_pdf_branded');
        $canDownloadPlain = $user->hasPermission('download_pdf_plain');
        $canDelete = $user->hasPermission('delete_quotations');
        $canHold = $user->hasPermission('hold_quotation');
        $canCancel = $user->hasPermission('cancel_quotation');
        $canResume = $user->hasPermission('resume_quotation');

        // Base query — scoped by permission/role (view_all, own, or branch)
        $query = Quotation::visibleTo($user)
            ->with(['user', 'banks', 'loan', 'location.parent']);

        // Total records (before any filtering)
        $recordsTotal = (clone $query)->count();

        // Quotation status filter (active / on_hold / cancelled / not_cancelled / all)
        $status = $request->input('status', 'not_cancelled');
        if ($status === 'active') {
            $query->where('status', Quotation::STATUS_ACTIVE);
        } elseif ($status === 'on_hold') {
            $query->where('status', Quotation::STATUS_ON_HOLD);
        } elseif ($status === 'cancelled') {
            $query->where('status', Quotation::STATUS_CANCELLED);
        } elseif ($status === 'not_cancelled') {
            $query->where('status', '!=', Quotation::STATUS_CANCELLED);
        }
        // 'all' → no status filter

        // Loan status filter
        $loanStatus = $request->input('loan_status', 'not_converted');
        if ($loanStatus === 'not_converted') {
            // Default: only quotations not yet converted to a loan
            $query->whereNull('loan_id');
        } elseif ($loanStatus === 'active') {
            // Converted with active loan
            $query->whereHas('loan', fn ($q) => $q->where('status', 'active'));
        } elseif ($loanStatus === 'completed') {
            $query->whereHas('loan', fn ($q) => $q->where('status', 'completed'));
        } elseif ($loanStatus === 'rejected') {
            $query->whereHas('loan', fn ($q) => $q->where('status', 'rejected'));
        } elseif ($loanStatus === 'converted') {
            // All converted (any loan status)
            $query->whereNotNull('loan_id');
        }
        // 'all' → no filter

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
        $orderColumn = $columns[$orderColumnIndex] ?? 'updated_at';

        // Map virtual columns to actual DB columns
        $orderableMap = [
            'id' => 'id',
            'customer_name' => 'customer_name',
            'customer_type' => 'customer_type',
            'loan_amount' => 'loan_amount',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'created_by' => 'user_id',
        ];

        if (isset($orderableMap[$orderColumn])) {
            $query->orderBy($orderableMap[$orderColumn], $orderDir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $quotations = $query->skip($start)->take($length)->get();

        // Format data for DataTables
        $canConvert = $user->hasPermission('convert_to_loan');

        $data = $quotations->map(function ($q) use ($canViewAll, $canDownload, $canDownloadBranded, $canDownloadPlain, $canDelete, $canConvert, $canHold, $canCancel, $canResume) {
            $bankNames = $q->banks ? $q->banks->pluck('bank_name')->toArray() : [];

            $typeLabels = [
                'proprietor' => 'Proprietor',
                'partnership_llp' => 'Partnership/LLP',
                'pvt_ltd' => 'PVT LTD',
                'salaried' => 'Salaried',
                'all' => 'All Types',
            ];

            $typeBadgeClass = match ($q->customer_type) {
                'proprietor' => 'shf-badge-green',
                'partnership_llp' => 'shf-badge-blue',
                'pvt_ltd' => 'shf-badge-orange',
                'salaried' => 'shf-badge-purple',
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
                'download_url' => $canDownload
                    ? route('quotations.download', $q->id)
                    : null,
                'download_branded_url' => $canDownloadBranded
                    ? route('quotations.download', [$q->id, 'branded' => 1])
                    : null,
                'download_plain_url' => $canDownloadPlain
                    ? route('quotations.download', [$q->id, 'branded' => 0])
                    : null,
                'delete_url' => $canDelete
                    ? route('quotations.destroy', $q->id)
                    : null,
                'convert_url' => ($canConvert && ! $q->loan_id)
                    ? route('quotations.convert', $q->id)
                    : null,
                'loan_url' => $q->loan_id
                    ? route('loans.show', $q->loan_id)
                    : null,
                'is_converted' => (bool) $q->loan_id,
                'loan_status' => $q->loan?->status,
                'loan_status_label' => $q->loan?->status_label,
                'status' => $q->status,
                'status_html' => $q->status_badge_html,
                'is_on_hold' => $q->is_on_hold,
                'is_cancelled' => $q->is_cancelled,
                'hold_follow_up_date' => $q->hold_follow_up_date?->format('d M Y'),
                'hold_reason_label' => $q->hold_reason_label,
                'cancel_reason_label' => $q->cancel_reason_label,
                'hold_url' => ($canHold && $q->status === Quotation::STATUS_ACTIVE && ! $q->loan_id)
                    ? route('quotations.hold', $q->id)
                    : null,
                'cancel_url' => ($canCancel && $q->status !== Quotation::STATUS_CANCELLED && ! $q->loan_id)
                    ? route('quotations.cancel', $q->id)
                    : null,
                'resume_url' => ($canResume && $q->status === Quotation::STATUS_ON_HOLD)
                    ? route('quotations.resume', $q->id)
                    : null,
                'location_name' => $q->location ? ($q->location->parent?->name ? $q->location->parent->name.'/' : '').$q->location->name : '',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data->values(),
        ]);
    }

    /**
     * AJAX endpoint for My Tasks on dashboard.
     */
    public function taskData(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = StageAssignment::where('assigned_to', $user->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['loan.bank', 'loan.product', 'loan.branch', 'loan.location.parent', 'loan.stageAssignments.assignee', 'stage']);

        $recordsTotal = (clone $query)->count();

        // Filters
        if ($request->filled('stage')) {
            $query->where('stage_key', $request->stage);
        }
        if ($request->filled('task_status')) {
            $query->where('status', $request->task_status);
        }

        // Search
        $search = $request->input('search.value', '');
        if ($search !== '' && $search !== null) {
            $query->whereHas('loan', function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        // Order
        $query->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END")
            ->orderBy('updated_at', 'desc');

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $tasks = $query->skip($start)->take($length)->get();

        $stagesIcon = '<svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>';

        $data = $tasks->map(function ($task) use ($stagesIcon) {
            $loan = $task->loan;
            $locationName = $loan->location ? $loan->location->name : '';
            $stageName = $task->stage?->stage_name_en ?? ucwords(str_replace('_', ' ', $task->stage_key));
            $cssClass = LoanDetail::stageBadgeClass($task->stage_key);

            $typeLabel = LoanDetail::CUSTOMER_TYPE_LABELS[$loan->customer_type] ?? ucfirst($loan->customer_type ?? '');

            return [
                'loan_number' => $loan->loan_number,
                'customer_name' => $loan->customer_name
                    .($typeLabel ? '<br><small class="text-muted">'.$typeLabel.'</small>' : ''),
                'customer_name_plain' => $loan->customer_name,
                'bank_name' => ($loan->bank?->name ?? $loan->bank_name)
                    .($loan->product ? '<br><small class="text-muted">'.$loan->product->name.'</small>' : '')
                    .($locationName ? '<br><small class="location-info" style="font-size:0.65rem;">'.$locationName.'</small>' : ''),
                'bank_name_plain' => $loan->bank?->name ?? $loan->bank_name ?? '—',
                'formatted_amount' => $loan->formatted_amount,
                'stage_name' => '<span class="shf-badge '.$cssClass.' shf-text-2xs">'.$stageName.'</span>',
                'status_label' => '<span class="shf-badge '.($task->status === 'in_progress' ? 'shf-badge-blue' : 'shf-badge-gray').'">'
                    .($task->status === 'in_progress' ? 'In Progress' : 'Pending').'</span>',
                'assigned_at' => $task->updated_at->diffForHumans(),
                'actions_html' => '<a href="'.route('loans.stages', $loan).'" class="btn-accent-sm" style="background:linear-gradient(135deg,#2563eb,#3b82f6);">'.$stagesIcon.' Stages</a>',
                'location_name' => $locationName,
                'owner_info' => $loan->current_owner?->name ?? '—',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data->values(),
        ]);
    }

    /**
     * AJAX endpoint for Recent Loans on dashboard.
     */
    public function dashboardLoanData(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = LoanDetail::visibleTo($user)
            ->with(['bank', 'product', 'creator', 'advisor', 'location.parent', 'stageAssignments.assignee.roles']);

        $recordsTotal = (clone $query)->count();

        // Filters (same as loan listing)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Default: exclude closed loans
            $query->whereNotIn('status', ['completed', 'rejected', 'cancelled']);
        }
        if ($request->filled('stage')) {
            $query->where('current_stage', $request->stage);
        }
        if ($request->filled('bank')) {
            $query->where('bank_id', $request->bank);
        }
        if ($request->filled('branch')) {
            $query->where('branch_id', $request->branch);
        }
        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }
        if ($request->filled('role')) {
            $role = $request->role;
            $query->whereHas('stageAssignments', function ($q) use ($role) {
                $q->whereColumn('stage_key', 'loan_details.current_stage')
                    ->whereHas('assignee', fn ($uq) => $uq->whereHas('roles', fn ($rq) => $rq->where('slug', $role)));
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search
        $search = $request->input('search.value', '');
        if ($search !== '' && $search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        // Order
        $columns = ['loan_number', 'customer_name', 'bank_name', 'loan_amount', 'current_stage', 'status', 'created_at'];
        $orderColumnIndex = (int) $request->input('order.0.column', 6);
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';
        $query->orderBy($orderColumn, $orderDir);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $loans = $query->skip($start)->take($length)->get();

        $statusBadgeClass = fn ($s) => match ($s) {
            'active' => 'blue', 'completed' => 'green', 'rejected' => 'red', 'on_hold' => 'orange', default => 'gray'
        };
        $viewIcon = '<svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
        $stagesIcon = '<svg style="width:12px;height:12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>';

        $data = $loans->map(function ($loan) use ($statusBadgeClass, $viewIcon, $stagesIcon) {
            $locationName = $loan->location ? $loan->location->name : '';
            $sl = LoanDetail::STATUS_LABELS[$loan->status] ?? null;

            $actions = '<div class="d-flex gap-1">';
            $actions .= '<a href="'.route('loans.show', $loan).'" class="btn-accent-sm">'.$viewIcon.' View</a>';
            if (in_array($loan->status, ['active', 'on_hold'])) {
                $actions .= '<a href="'.route('loans.stages', $loan).'" class="btn-accent-sm" style="background:linear-gradient(135deg,#2563eb,#3b82f6);">'.$stagesIcon.' Stages</a>';
            }
            $actions .= '</div>';

            return [
                'loan_number' => $loan->loan_number,
                'customer_name' => $loan->customer_name,
                'bank_name' => ($loan->bank?->name ?? $loan->bank_name)
                    .($loan->product ? '<br><small class="text-muted">'.$loan->product->name.'</small>' : '')
                    .($locationName ? '<br><small class="location-info" style="font-size:0.65rem;">'.$locationName.'</small>' : ''),
                'bank_name_plain' => $loan->bank?->name ?? $loan->bank_name ?? '—',
                'formatted_amount' => $loan->formatted_amount,
                'stage_name' => $loan->stage_badge_html,
                'status_label' => '<span class="shf-badge shf-badge-'.$statusBadgeClass($loan->status).'">'.($sl['label'] ?? ucfirst($loan->status)).'</span>',
                'created_at' => $loan->created_at?->format('d M Y'),
                'actions_html' => $actions,
                'location_name' => $locationName,
                'owner_info' => $loan->current_owner?->name ?? '—',
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data->values(),
        ]);
    }

    /**
     * Server-side DataTables AJAX endpoint for DVR on dashboard.
     */
    public function dvrData(Request $request): JsonResponse
    {
        $user = Auth::user();
        $query = \App\Models\DailyVisitReport::visibleTo($user)
            ->with(['user', 'loan', 'branch']);

        $recordsTotal = (clone $query)->count();

        // Default: show pending follow-ups + today's visits (exclude completed)
        $filter = $request->input('dvr_filter', 'pending');
        if ($filter === 'pending') {
            $query->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('follow_up_needed', true)->where('is_follow_up_done', false);
                })->orWhere('visit_date', today());
            });
        } elseif ($filter === 'overdue') {
            $query->overdueFollowUps();
        } elseif ($filter === 'today') {
            $query->where('visit_date', today());
        } elseif ($filter === 'active') {
            // All except completed follow-ups
            $query->where(function ($q) {
                $q->where('follow_up_needed', false)
                    ->orWhere(function ($q2) {
                        $q2->where('follow_up_needed', true)->where('is_follow_up_done', false);
                    });
            });
        }
        // 'all' = no filter (includes completed)

        // Search
        $search = $request->input('search.value', '');
        if ($search !== '' && $search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('contact_name', 'like', "%{$search}%")
                    ->orWhere('contact_phone', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        // Primary sort: pending first (is_follow_up_done=0 before 1), then by user column
        $query->orderBy('is_follow_up_done', 'asc');

        $columns = ['visit_date', 'contact_name', 'contact_type', 'follow_up_date', 'created_at'];
        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderColumnIndex] ?? 'visit_date';
        $query->orderBy($orderColumn, $orderDir);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $visits = $query->skip($start)->take($length)->get();

        $config = app(\App\Services\ConfigService::class)->load();
        $contactTypeLabels = collect($config['dvrContactTypes'] ?? [])->pluck('label_en', 'key')->toArray();
        $purposeLabels = collect($config['dvrPurposes'] ?? [])->pluck('label_en', 'key')->toArray();

        // Chain maps for the follow-ups-taken count (see DailyVisitReportController::dvrData).
        $childMap = \App\Models\DailyVisitReport::query()
            ->whereNotNull('follow_up_visit_id')
            ->pluck('follow_up_visit_id', 'id')
            ->all();
        $parentMap = \App\Models\DailyVisitReport::query()
            ->whereNotNull('parent_visit_id')
            ->pluck('parent_visit_id', 'id')
            ->all();

        $countFollowUps = function (int $visitId) use ($childMap, $parentMap): int {
            $root = $visitId;
            $seenUp = [];
            while (isset($parentMap[$root]) && ! isset($seenUp[$root])) {
                $seenUp[$root] = true;
                $root = $parentMap[$root];
            }
            $count = 0;
            $cursor = $root;
            $seenDown = [];
            while (isset($childMap[$cursor]) && ! isset($seenDown[$cursor])) {
                $seenDown[$cursor] = true;
                $cursor = $childMap[$cursor];
                $count++;
            }

            return $count;
        };

        $data = $visits->map(function (\App\Models\DailyVisitReport $visit) use ($contactTypeLabels, $purposeLabels, $countFollowUps) {
            // Follow-up urgency (same as DVR index)
            $followUpHtml = '—';
            $followUpUrgency = null;
            if ($visit->is_follow_up_done && ! $visit->follow_up_needed) {
                $followUpHtml = '<span class="shf-badge shf-badge-green shf-text-2xs">Completed</span>';
            } elseif ($visit->follow_up_needed) {
                if ($visit->is_follow_up_done) {
                    $followUpHtml = '<span class="shf-badge shf-badge-green shf-text-2xs">Completed</span>';
                } elseif ($visit->follow_up_date) {
                    $daysUntil = (int) today()->diffInDays($visit->follow_up_date, false);
                    $dateStr = $visit->follow_up_date->format('d M Y');
                    if ($daysUntil < 0) {
                        $overdueDays = abs($daysUntil);
                        $followUpUrgency = 'overdue';
                        $followUpHtml = $dateStr.'<br><span class="shf-badge shf-badge-red shf-text-2xs">Overdue by '.$overdueDays.' '.($overdueDays === 1 ? 'day' : 'days').'</span>';
                    } elseif ($daysUntil === 0) {
                        $followUpUrgency = 'due_today';
                        $followUpHtml = $dateStr.'<br><span class="shf-badge shf-badge-orange shf-text-2xs">Due Today</span>';
                    } elseif ($daysUntil === 1) {
                        $followUpUrgency = 'due_tomorrow';
                        $followUpHtml = $dateStr.'<br><span class="shf-badge shf-badge-orange shf-text-2xs">Due Tomorrow</span>';
                    } elseif ($daysUntil <= 3) {
                        $followUpUrgency = 'due_soon';
                        $followUpHtml = $dateStr.'<br><span class="shf-badge shf-badge-blue shf-text-2xs">Due in '.$daysUntil.' days</span>';
                    } else {
                        $followUpHtml = $dateStr.'<br><span class="shf-badge shf-badge-gray shf-text-2xs">Pending</span>';
                    }
                } else {
                    $followUpHtml = '<span class="shf-badge shf-badge-orange shf-text-2xs">Pending</span>';
                }
            }

            $followUpsTaken = $countFollowUps($visit->id);
            if ($followUpsTaken > 0) {
                $label = $followUpsTaken === 1 ? 'follow-up taken' : 'follow-ups taken';
                $followUpHtml .= '<br><span class="shf-badge shf-badge-blue shf-text-2xs">'.$followUpsTaken.' '.$label.'</span>';
            }

            return [
                'id' => $visit->id,
                'visit_date' => $visit->visit_date->format('d M Y'),
                'contact_name' => e($visit->contact_name),
                'contact_phone' => e($visit->contact_phone ?? ''),
                'contact_type' => $contactTypeLabels[$visit->contact_type] ?? $visit->contact_type,
                'purpose' => $purposeLabels[$visit->purpose] ?? $visit->purpose,
                'follow_up_html' => $followUpHtml,
                'follow_up_urgency' => $followUpUrgency,
                'follow_up_needed' => $visit->follow_up_needed,
                'is_follow_up_done' => $visit->is_follow_up_done,
                'follow_ups_taken' => $followUpsTaken,
                'user_name' => e($visit->user?->name ?? '—'),
                'show_url' => route('dvr.show', $visit),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data->values(),
        ]);
    }

    public function activityLog()
    {
        $users = User::select('id', 'name')->orderBy('name')->get();
        $actionTypes = ActivityLog::select('description')->distinct()->orderBy('description')->pluck('description');

        $template = 'newtheme.activity-log';

        return view($template, compact('users', 'actionTypes'));
    }

    public function activityLogData(Request $request): JsonResponse
    {
        $query = ActivityLog::with('user');

        $recordsTotal = (clone $query)->count();

        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->user_id);
        }
        if ($request->filled('action_type')) {
            $query->where('description', $request->action_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $search = $request->input('search.value', '');
        if ($search !== '' && $search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $recordsFiltered = (clone $query)->count();

        $columns = ['created_at', 'causer_id', 'description', 'subject_type', 'created_at'];
        $orderColumnIndex = (int) $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderColumnIndex] ?? 'created_at';
        $query->orderBy($orderColumn, $orderDir);

        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $logs = $query->skip($start)->take($length)->get();

        $actionBadges = [
            'login' => 'shf-badge-green', 'logout' => 'shf-badge-gray',
            'create_quotation' => 'shf-badge-blue', 'delete_quotation' => 'shf-badge-red',
            'update_settings' => 'shf-badge-orange', 'create_user' => 'shf-badge-blue',
            'update_user' => 'shf-badge-blue', 'delete_user' => 'shf-badge-red',
            'update_permissions' => 'shf-badge-orange', 'create_loan' => 'shf-badge-blue',
            'save_product_stages' => 'shf-badge-orange', 'impersonate_start' => 'shf-badge-orange',
            'impersonate_end' => 'shf-badge-gray',
        ];

        $data = $logs->map(function ($log) use ($actionBadges) {
            $badge = $actionBadges[$log->action] ?? 'shf-badge-gray';
            $actionLabel = ucwords(str_replace('_', ' ', $log->action));

            $subject = $log->subject_type
                ? class_basename($log->subject_type).' #'.$log->subject_id
                : '—';

            $details = '—';
            if ($log->properties) {
                $props = $log->properties;
                if (isset($props['customer_name'])) {
                    $details = e($props['customer_name']);
                    if (isset($props['loan_amount'])) {
                        $details .= ' — ₹ '.number_format($props['loan_amount']);
                    }
                } elseif (isset($props['name'])) {
                    $details = e($props['name']);
                } elseif (isset($props['section'])) {
                    $details = 'Section: '.e($props['section']);
                }
            }

            return [
                'date_html' => '<span style="white-space:nowrap;color:#6b7280;">'.$log->created_at->format('d M Y')
                    .'<span class="d-block small" style="color:#9ca3af;">'.$log->created_at->format('h:i A').'</span></span>',
                'user_name' => $log->user?->name ?? 'System',
                'action_html' => '<span class="shf-badge '.$badge.'">'.$actionLabel.'</span>',
                'subject' => $subject,
                'details' => $details,
                'ip_address' => $log->ip_address ?? '—',
                // For mobile
                'action' => $log->action,
                'action_label' => $actionLabel,
                'action_badge' => $badge,
                'date_short' => $log->created_at->format('d M Y, h:i A'),
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data->values(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // Newtheme dashboard payload (super_admin only — see ::index branch)
    // Returns a single normalized array consumed by the page-specific JS in
    // public/newtheme/pages/dashboard.js to render the redesigned dashboard.
    // ──────────────────────────────────────────────────────────────────────

    /**
     * Build the full data payload for the newtheme dashboard.
     *
     * @return array<string,mixed>
     */
    private function newthemePayload(User $user): array
    {
        $tabCounts = $this->newthemeTabCounts($user);
        $tabs = $this->newthemeTabsConfig($user, $tabCounts);

        return [
            'currentUser' => $this->newthemeCurrentUser($user),
            'kpi' => $this->newthemeKpi($user),
            'tabCounts' => $tabCounts,
            'tabs' => $tabs,
            'defaultTab' => $this->newthemeDefaultTab($user, $tabs, $tabCounts),
            'subheader' => $this->newthemeSubheader($user),
            'personalTasks' => $this->newthemePersonalTasks($user),
            'myLoanTasks' => $this->newthemeMyLoanTasks($user),
            'loans' => $this->newthemeLoans($user),
            'dvr' => $this->newthemeDvr($user),
            'quotations' => $this->newthemeQuotations($user),
            'pipeline' => $this->newthemePipeline($user),
            'todayFollowUps' => $this->newthemeTodayFollowUps($user),
            'openQueries' => $this->newthemeOpenQueries(),
            'fieldActivity' => $this->newthemeFieldActivity($user),
            'bankMix' => $this->newthemeBankMix($user),
            'stagesDropdown' => $this->newthemeStagesDropdown(),
        ];
    }

    /**
     * Tab visibility (mirrors resources/views/dashboard.blade.php lines 124-159):
     *   - personal-tasks  : always
     *   - tasks (my loan) : if user has view_loans permission OR a workflow role
     *   - loans           : same as tasks
     *   - dvr             : view_dvr permission
     *   - quotations      : create_quotation | view_own_quotations | view_all_quotations
     *
     * @param  array<string,int>  $counts
     * @return array<int,array{key:string,label:string,visible:bool,count:int}>
     */
    private function newthemeTabsConfig(User $user, array $counts): array
    {
        $hasLoanContext = $user->hasPermission('view_loans') || $user->hasWorkflowRole();
        $canViewQuotations = $user->hasPermission('create_quotation')
            || $user->hasPermission('view_own_quotations')
            || $user->hasPermission('view_all_quotations');

        return [
            ['key' => 'personal-tasks', 'label' => 'Personal Tasks', 'visible' => true, 'count' => $counts['personal_tasks'] ?? 0],
            ['key' => 'tasks', 'label' => 'My Tasks', 'visible' => $hasLoanContext, 'count' => $counts['my_tasks'] ?? 0],
            ['key' => 'loans', 'label' => 'Loans', 'visible' => $hasLoanContext, 'count' => $counts['loans'] ?? 0],
            ['key' => 'dvr', 'label' => 'DVR', 'visible' => $user->hasPermission('view_dvr'), 'count' => $counts['dvr'] ?? 0],
            ['key' => 'quotations', 'label' => 'Quotations', 'visible' => $canViewQuotations, 'count' => $counts['quotations'] ?? 0],
        ];
    }

    /**
     * Pick the default tab using the same data-driven priority as the existing
     * dashboard (DashboardController::index, see lines 110-126):
     *   overdue DVR -> overdue personal -> loan stage tasks -> pending personal
     *   -> active loans -> unconverted quotations -> personal-tasks fallback.
     *
     * Only tabs that are visible to the user are eligible.
     *
     * @param  array<int,array<string,mixed>>  $tabs
     * @param  array<string,int>  $counts
     */
    private function newthemeDefaultTab(User $user, array $tabs, array $counts): string
    {
        $visible = collect($tabs)->where('visible', true)->pluck('key')->all();
        $isVisible = fn (string $k) => in_array($k, $visible, true);

        $overdueDvr = DailyVisitReport::visibleTo($user)
            ->where('follow_up_needed', true)
            ->where('is_follow_up_done', false)
            ->whereDate('follow_up_date', '<', today())
            ->count();

        $overduePersonal = GeneralTask::visibleTo($user)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->count();

        if ($overdueDvr > 0 && $isVisible('dvr')) {
            return 'dvr';
        }
        if ($overduePersonal > 0 && $isVisible('personal-tasks')) {
            return 'personal-tasks';
        }
        if (($counts['my_tasks'] ?? 0) > 0 && $isVisible('tasks')) {
            return 'tasks';
        }
        if (($counts['personal_tasks'] ?? 0) > 0 && $isVisible('personal-tasks')) {
            return 'personal-tasks';
        }
        if (($counts['loans'] ?? 0) > 0 && $isVisible('loans')) {
            return 'loans';
        }
        if (($counts['quotations'] ?? 0) > 0 && $isVisible('quotations')) {
            return 'quotations';
        }

        return $visible[0] ?? 'personal-tasks';
    }

    /** @return array<string,mixed> */
    private function newthemeCurrentUser(User $user): array
    {
        $first = strtok($user->name, ' ') ?: $user->name;
        $last = trim(substr($user->name, strlen($first)));
        $shortLast = $last !== '' ? strtoupper($last[0]).'.' : '';
        $initials = strtoupper(substr($first, 0, 1).substr($last, 0, 1)) ?: 'U';

        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'short' => trim($first.' '.$shortLast) ?: $user->name,
            'role' => $user->role_label,
            'initials' => $initials,
        ];
    }

    /**
     * Build the KPI strip with permission-aware tiles.
     *
     * Each tile is gated by the same permission groups used in the existing
     * dashboard (DashboardController::index lines 22-101). Counts use the
     * standard visibleTo() scopes so a branch-scoped user only counts their
     * branch's records — super_admin/admin keep their full bypasses.
     *
     * @return array<int,array<string,mixed>>
     */
    private function newthemeKpi(User $user): array
    {
        $now = now();
        $tiles = [];

        $canViewQuotations = $user->hasPermission('create_quotation')
            || $user->hasPermission('view_own_quotations')
            || $user->hasPermission('view_all_quotations');

        if ($canViewQuotations) {
            $quotBase = Quotation::visibleTo($user);
            $tiles[] = ['val' => (clone $quotBase)->count(), 'lbl' => 'Quotations', 'tone' => 'accent', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'];
            $tiles[] = ['val' => (clone $quotBase)->whereDate('created_at', $now->toDateString())->count(), 'lbl' => 'Today', 'tone' => 'accent', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'];
            $tiles[] = ['val' => (clone $quotBase)->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year)->count(), 'lbl' => 'This Month', 'tone' => 'accent', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'];
        }

        $hasLoanContext = $user->hasPermission('view_loans') || $user->hasWorkflowRole();
        if ($hasLoanContext) {
            $loanBase = LoanDetail::visibleTo($user);
            $tiles[] = ['val' => (clone $loanBase)->whereNotIn('status', ['completed', 'rejected', 'cancelled'])->count(), 'lbl' => 'Active Loans', 'tone' => 'blue', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'];
            $tiles[] = ['val' => StageAssignment::where('assigned_to', $user->id)->whereIn('status', ['pending', 'in_progress'])->count(), 'lbl' => 'My Tasks', 'tone' => 'amber', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'];
            $tiles[] = ['val' => (clone $loanBase)->where('status', 'completed')->count(), 'lbl' => 'Completed', 'tone' => 'green', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'];
        }

        if ($user->hasPermission('view_dvr')) {
            $dvrBase = DailyVisitReport::visibleTo($user);
            $tiles[] = ['val' => (clone $dvrBase)->whereDate('visit_date', today())->count(), 'lbl' => 'DVR Today', 'tone' => 'violet', 'icon' => 'M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z'];
            $tiles[] = ['val' => (clone $dvrBase)->where('follow_up_needed', true)->where('is_follow_up_done', false)->whereDate('follow_up_date', '<', today())->count(), 'lbl' => 'DVR Overdue', 'tone' => 'red', 'icon' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'];
        }

        // Personal tasks tile — always available (general tasks have no perm gate)
        $tiles[] = [
            'val' => GeneralTask::visibleTo($user)
                ->whereIn('status', ['pending', 'in_progress'])
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', today())
                ->count(),
            'lbl' => 'Tasks Overdue',
            'tone' => 'red',
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        ];

        return $tiles;
    }

    /** @return array<string,int> */
    private function newthemeTabCounts(User $user): array
    {
        return [
            'personal_tasks' => GeneralTask::visibleTo($user)->whereIn('status', ['pending', 'in_progress'])->count(),
            'my_tasks' => StageAssignment::where('assigned_to', $user->id)->whereIn('status', ['pending', 'in_progress'])->count(),
            'loans' => LoanDetail::visibleTo($user)->whereNotIn('status', ['completed', 'rejected', 'cancelled'])->count(),
            'dvr' => DailyVisitReport::visibleTo($user)->where('follow_up_needed', true)->where('is_follow_up_done', false)->count(),
            'quotations' => Quotation::visibleTo($user)->where('status', Quotation::STATUS_ACTIVE)->whereNull('loan_id')->count(),
        ];
    }

    /** @return array<string,mixed> */
    private function newthemeSubheader(User $user): array
    {
        return [
            'branch' => optional($user->branches()->first())->name ?? '—',
            'activeFiles' => LoanDetail::visibleTo($user)->whereNotIn('status', ['completed', 'rejected', 'cancelled'])->count(),
            'disbursementsToday' => LoanDetail::visibleTo($user)
                ->where('current_stage', 'disbursement')
                ->whereDate('updated_at', today())
                ->count(),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function newthemePersonalTasks(User $user): array
    {
        return GeneralTask::visibleTo($user)
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['assignee', 'creator', 'loan'])
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date', 'asc')
            ->limit(20)
            ->get()
            ->map(function (GeneralTask $t) use ($user) {
                $overdue = $t->due_date && $t->due_date->isPast() && $t->status !== 'completed';

                return [
                    'id' => $t->id,
                    'title' => $t->title,
                    'priority' => $t->priority,
                    'priorityLabel' => ucfirst($t->priority),
                    'priorityColor' => $this->newthemePriorityColor($t->priority),
                    'status' => $t->status,
                    'statusLabel' => ucwords(str_replace('_', ' ', $t->status)),
                    'statusColor' => $this->newthemeStatusColor($t->status),
                    'dueDate' => optional($t->due_date)->format('d/m/Y'),
                    'overdue' => $overdue,
                    'assignee' => optional($t->assignee)->name ?? '—',
                    'assignedToMe' => (int) $t->assigned_to === (int) $user->id,
                    'createdBy' => optional($t->creator)->name ?? '—',
                    'loanNumber' => optional($t->loan)->loan_number,
                    'showUrl' => route('general-tasks.show', $t->id),
                ];
            })
            ->values()
            ->toArray();
    }

    /** @return array<int,array<string,mixed>> */
    private function newthemeMyLoanTasks(User $user): array
    {
        return StageAssignment::where('assigned_to', $user->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->with(['loan.bank', 'loan.product', 'stage'])
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END")
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function (StageAssignment $a) {
                $loan = $a->loan;
                $stageName = optional($a->stage)->stage_name_en ?? ucwords(str_replace('_', ' ', $a->stage_key));

                return [
                    'loanNumber' => $loan->loan_number,
                    'customer' => $loan->customer_name,
                    'customerType' => LoanDetail::CUSTOMER_TYPE_LABELS[$loan->customer_type] ?? ucfirst((string) $loan->customer_type),
                    'amountFormatted' => $loan->formatted_amount,
                    'stageKey' => $a->stage_key,
                    'stageName' => $stageName,
                    'stageBadgeClass' => $this->newthemeStageBadgeColor($a->stage_key),
                    'progress' => $this->newthemeStageProgress($a->stage_key),
                    'bank' => $this->newthemeBankBlock($loan->bank, $loan->bank_name),
                    'productName' => optional($loan->product)->name,
                    'ageDays' => optional($loan->updated_at)->diffInDays(now()) ?? 0,
                    'showUrl' => route('loans.show', $loan),
                    'stagesUrl' => route('loans.stages', $loan),
                ];
            })
            ->values()
            ->toArray();
    }

    /** @return array<int,array<string,mixed>> */
    private function newthemeLoans(User $user): array
    {
        return LoanDetail::visibleTo($user)
            ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
            ->with(['bank', 'product', 'creator', 'advisor'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function (LoanDetail $loan) {
                return [
                    'loanNumber' => $loan->loan_number,
                    'customer' => $loan->customer_name,
                    'customerType' => LoanDetail::CUSTOMER_TYPE_LABELS[$loan->customer_type] ?? ucfirst((string) $loan->customer_type),
                    'amountFormatted' => $loan->formatted_amount,
                    'stageKey' => $loan->current_stage,
                    'stageName' => $this->newthemeStageName($loan->current_stage),
                    'stageBadgeClass' => $this->newthemeStageBadgeColor($loan->current_stage),
                    'bank' => $this->newthemeBankBlock($loan->bank, $loan->bank_name),
                    'productName' => optional($loan->product)->name,
                    'owner' => optional($loan->current_owner)->name ?? '—',
                    'showUrl' => route('loans.show', $loan),
                    'stagesUrl' => route('loans.stages', $loan),
                ];
            })
            ->values()
            ->toArray();
    }

    /** @return array<int,array<string,mixed>> */
    private function newthemeDvr(User $user): array
    {
        $config = app(\App\Services\ConfigService::class)->load();
        $contactTypeLabels = collect($config['dvrContactTypes'] ?? [])->pluck('label_en', 'key')->toArray();
        $purposeLabels = collect($config['dvrPurposes'] ?? [])->pluck('label_en', 'key')->toArray();

        // Chain maps so every visit in a follow-up chain can report the
        // total number of follow-ups taken (same logic as DailyVisitReportController::dvrData).
        $childMap = DailyVisitReport::query()
            ->whereNotNull('follow_up_visit_id')
            ->pluck('follow_up_visit_id', 'id')
            ->all();
        $parentMap = DailyVisitReport::query()
            ->whereNotNull('parent_visit_id')
            ->pluck('parent_visit_id', 'id')
            ->all();

        $countFollowUps = function (int $visitId) use ($childMap, $parentMap): int {
            $root = $visitId;
            $seenUp = [];
            while (isset($parentMap[$root]) && ! isset($seenUp[$root])) {
                $seenUp[$root] = true;
                $root = $parentMap[$root];
            }
            $count = 0;
            $cursor = $root;
            $seenDown = [];
            while (isset($childMap[$cursor]) && ! isset($seenDown[$cursor])) {
                $seenDown[$cursor] = true;
                $cursor = $childMap[$cursor];
                $count++;
            }

            return $count;
        };

        return DailyVisitReport::visibleTo($user)
            ->with(['user'])
            ->orderBy('visit_date', 'desc')
            ->limit(15)
            ->get()
            ->map(function (DailyVisitReport $v) use ($contactTypeLabels, $purposeLabels, $countFollowUps) {
                $followUp = ['state' => 'none', 'date' => null, 'overdue' => false];
                if ($v->follow_up_needed) {
                    if ($v->is_follow_up_done) {
                        $followUp['state'] = 'done';
                    } else {
                        $followUp['state'] = 'pending';
                        $followUp['date'] = optional($v->follow_up_date)->format('d/m/Y');
                        $followUp['overdue'] = $v->follow_up_date && $v->follow_up_date->isPast();
                    }
                } elseif ($v->is_follow_up_done) {
                    // Visit closed with no pending follow-up (new default).
                    $followUp['state'] = 'completed';
                }

                return [
                    'id' => $v->id,
                    'visitDate' => $v->visit_date->format('d/m/Y'),
                    'daysAgo' => (int) $v->visit_date->diffInDays(now()),
                    'contactName' => $v->contact_name,
                    'contactPhone' => $v->contact_phone,
                    'contactType' => $contactTypeLabels[$v->contact_type] ?? $v->contact_type,
                    'purpose' => $purposeLabels[$v->purpose] ?? $v->purpose,
                    'outcome' => $v->outcome,
                    'followUp' => $followUp,
                    'followUpsTaken' => $countFollowUps($v->id),
                    'user' => optional($v->user)->name ?? '—',
                    'showUrl' => route('dvr.show', $v),
                ];
            })
            ->values()
            ->toArray();
    }

    /** @return array<int,array<string,mixed>> */
    private function newthemeQuotations(User $user): array
    {
        return Quotation::visibleTo($user)
            ->with(['user', 'banks', 'loan'])
            ->orderBy('created_at', 'desc')
            ->limit(15)
            ->get()
            ->map(function (Quotation $q) {
                return [
                    'id' => $q->id,
                    'quotNumber' => 'Q-'.str_pad((string) $q->id, 6, '0', STR_PAD_LEFT),
                    'customer' => $q->customer_name,
                    'customerType' => ucfirst((string) $q->customer_type),
                    'amountFormatted' => $q->formatted_amount,
                    'banks' => $q->banks
                        ? $q->banks->map(fn ($b) => $this->newthemeBankBlock(
                            Bank::query()->where('name', $b->bank_name)->first(),
                            $b->bank_name,
                        ))->values()->toArray()
                        : [],
                    'status' => $q->status,
                    'statusLabel' => match ($q->status) {
                        Quotation::STATUS_ACTIVE => 'Active',
                        Quotation::STATUS_ON_HOLD => 'On Hold',
                        Quotation::STATUS_CANCELLED => 'Cancelled',
                        default => ucfirst((string) $q->status),
                    },
                    'statusColor' => match ($q->status) {
                        Quotation::STATUS_ACTIVE => 'green',
                        Quotation::STATUS_ON_HOLD => 'amber',
                        Quotation::STATUS_CANCELLED => 'red',
                        default => 'gray',
                    },
                    'isConverted' => (bool) $q->loan_id,
                    'date' => optional($q->created_at)->format('d/m/Y'),
                    'creator' => optional($q->user)->name ?? '—',
                    'showUrl' => route('quotations.show', $q->id),
                ];
            })
            ->values()
            ->toArray();
    }

    /** @return array<int,array<string,mixed>> */
    private function newthemePipeline(User $user): array
    {
        $stages = Stage::query()->mainStages()->get();
        $loanCounts = LoanDetail::visibleTo($user)
            ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
            ->selectRaw('current_stage, COUNT(*) as c')
            ->groupBy('current_stage')
            ->pluck('c', 'current_stage')
            ->toArray();

        $rows = [];
        foreach ($stages as $idx => $s) {
            $rows[] = [
                'n' => $idx + 1,
                'key' => $s->stage_key,
                'label' => $s->stage_name_en,
                'count' => (int) ($loanCounts[$s->stage_key] ?? 0),
                'color' => $this->newthemeStageBadgeColor($s->stage_key),
            ];
        }
        $rows[] = [
            'n' => count($rows) + 1,
            'key' => 'completed_mtd',
            'label' => 'Completed MTD',
            'count' => LoanDetail::visibleTo($user)
                ->where('status', 'completed')
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->count(),
            'color' => 'green',
        ];

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    private function newthemeTodayFollowUps(User $user): array
    {
        $today = today();

        return DailyVisitReport::visibleTo($user)
            ->with('user')
            ->where('follow_up_needed', true)
            ->where('is_follow_up_done', false)
            ->whereDate('follow_up_date', '<=', $today)
            ->orderBy('follow_up_date', 'asc')
            ->limit(6)
            ->get()
            ->map(function (DailyVisitReport $v) use ($today) {
                $overdue = $v->follow_up_date && $v->follow_up_date->lt($today);

                return [
                    'title' => 'Follow up — '.$v->contact_name,
                    'meta' => $overdue
                        ? 'Overdue · '.optional($v->follow_up_date)->format('d M')
                        : 'Due today',
                    'owner' => optional($v->user)->name ?? '—',
                    'active' => $overdue,
                ];
            })
            ->values()
            ->toArray();
    }

    /** @return array<int,array<string,mixed>> */
    private function newthemeOpenQueries(): array
    {
        return StageQuery::query()->active()
            ->with(['loan', 'raisedByUser.roles'])
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get()
            ->map(function (StageQuery $q) {
                $role = optional($q->raisedByUser)?->roles?->whereNotIn('slug', ['super_admin', 'admin'])->first();
                $age = $q->created_at ? $q->created_at->diffForHumans(null, true).' ago' : '';

                return [
                    'title' => Str::limit((string) $q->query_text, 60),
                    'loan' => optional($q->loan)->loan_number ?? '—',
                    'role' => $role?->name ?? optional($q->raisedByUser)->role_label ?? 'user',
                    'age' => $age,
                    'showUrl' => $q->loan_id ? route('loans.stages', $q->loan_id) : '#',
                ];
            })
            ->values()
            ->toArray();
    }

    /** @return array<int,array<string,mixed>> */
    private function newthemeFieldActivity(User $user): array
    {
        $todayDvr = DailyVisitReport::visibleTo($user)
            ->whereDate('visit_date', today())
            ->get();

        return [
            ['lbl' => 'Visits', 'val' => $todayDvr->count()],
            ['lbl' => 'Leads', 'val' => $todayDvr->where('purpose', 'new_lead')->count()],
            ['lbl' => 'Follow-ups', 'val' => $todayDvr->where('purpose', 'follow_up')->count()],
            ['lbl' => 'Active', 'val' => $todayDvr->whereNotNull('outcome')->count()],
        ];
    }

    /** @return array<string,mixed> */
    private function newthemeBankMix(User $user): array
    {
        $banks = Bank::query()->active()->get();

        $monthlyLoans = LoanDetail::visibleTo($user)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->select('bank_id')
            ->get();

        $counts = $monthlyLoans->groupBy('bank_id')->map->count();
        $total = $monthlyLoans->count();

        $rows = $banks->map(function (Bank $b) use ($counts, $total) {
            $count = (int) ($counts[$b->id] ?? 0);
            $palette = $this->newthemePaletteForBankName((string) $b->name);

            return [
                'name' => $b->name,
                'code' => $b->code ?: $this->newthemeBankCode($b->name),
                'count' => $count,
                'pct' => $total ? (int) round($count * 100 / $total) : 0,
                'bg' => $palette['bg'],
                'fg' => $palette['fg'],
            ];
        })->values()->toArray();

        return ['total' => $total, 'banks' => $rows];
    }

    /** @return array<int,array<string,mixed>> */
    private function newthemeStagesDropdown(): array
    {
        return Stage::query()->mainStages()->get()->values()->map(function (Stage $s, int $idx) {
            return [
                'key' => $s->stage_key,
                'label' => $s->stage_name_en,
                'n' => $idx + 1,
            ];
        })->toArray();
    }

    /** @return array<string,mixed> */
    private function newthemeBankBlock(?Bank $bank, ?string $fallbackName): array
    {
        $name = $bank?->name ?? $fallbackName ?? '—';
        $palette = $this->newthemePaletteForBankName($name);

        return ['name' => $name, 'bg' => $palette['bg'], 'fg' => $palette['fg']];
    }

    /** @return array<string,array<string,string>> */
    private function newthemeBankBrandPalette(): array
    {
        return [
            'HDFC' => ['bg' => '#004C8F', 'fg' => '#ffffff'],
            'ICICI' => ['bg' => '#F37E20', 'fg' => '#ffffff'],
            'AXIS' => ['bg' => '#97144D', 'fg' => '#ffffff'],
            'KOTAK' => ['bg' => '#fa1432', 'fg' => '#ffffff'],
            'SBI' => ['bg' => '#22409a', 'fg' => '#ffffff'],
            'PNB' => ['bg' => '#a8232f', 'fg' => '#ffffff'],
            'IDFC' => ['bg' => '#9c0c2c', 'fg' => '#ffffff'],
            'YES' => ['bg' => '#00518a', 'fg' => '#ffffff'],
        ];
    }

    /**
     * Find the brand palette for a bank by short code embedded in its name
     * (e.g. "HDFC Bank" -> HDFC). Falls back to a neutral gray palette.
     *
     * @return array{bg:string,fg:string}
     */
    private function newthemePaletteForBankName(string $name): array
    {
        $code = $this->newthemeBankCode($name);

        return $this->newthemeBankBrandPalette()[$code] ?? ['bg' => '#6b7280', 'fg' => '#ffffff'];
    }

    private function newthemeBankCode(string $name): string
    {
        $upper = strtoupper($name);
        foreach (['HDFC', 'ICICI', 'AXIS', 'KOTAK', 'SBI', 'PNB', 'IDFC', 'YES'] as $code) {
            if (str_contains($upper, $code)) {
                return $code;
            }
        }

        return strtoupper(substr(preg_replace('/[^a-z]/i', '', $name) ?? '', 0, 4));
    }

    private function newthemeStageProgress(string $stageKey): int
    {
        return [
            'inquiry' => 8, 'document_selection' => 16, 'document_collection' => 25,
            'parallel_processing' => 33, 'rate_pf' => 42, 'sanction' => 50,
            'docket' => 58, 'kfs' => 66, 'esign' => 75,
            'disbursement' => 83, 'otc_clearance' => 91,
        ][$stageKey] ?? 100;
    }

    private function newthemeStageBadgeColor(string $stageKey): string
    {
        return [
            'inquiry' => 'gray', 'document_selection' => 'gray', 'document_collection' => 'blue',
            'parallel_processing' => 'blue', 'rate_pf' => 'amber', 'sanction' => 'orange',
            'docket' => 'orange', 'kfs' => 'violet', 'esign' => 'violet',
            'disbursement' => 'green', 'otc_clearance' => 'dark',
        ][$stageKey] ?? 'gray';
    }

    private function newthemeStageName(string $stageKey): string
    {
        return Stage::query()->where('stage_key', $stageKey)->value('stage_name_en')
            ?? ucwords(str_replace('_', ' ', $stageKey));
    }

    private function newthemePriorityColor(string $priority): string
    {
        return ['urgent' => 'red', 'high' => 'amber', 'normal' => 'blue', 'low' => 'gray'][$priority] ?? 'gray';
    }

    private function newthemeStatusColor(string $status): string
    {
        return ['pending' => 'gray', 'in_progress' => 'blue', 'completed' => 'green', 'cancelled' => 'red'][$status] ?? 'gray';
    }
}
