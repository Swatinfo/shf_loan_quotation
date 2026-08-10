<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\LoanDetail;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\Stage;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\LoanConversionService;
use App\Services\LoanStageService;
use App\Services\LoanTimelineService;
use App\Validation\LoanValidationRules;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LoanController extends Controller
{
    public function __construct(
        private LoanConversionService $conversionService,
        private CustomerService $customerService,
    ) {}

    public function index()
    {
        $baseQuery = LoanDetail::visibleTo(auth()->user());
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('status', 'active')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'this_month' => (clone $baseQuery)->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)->count(),
        ];

        $user = auth()->user();
        $banks = Bank::active()->orderBy('name')->get();
        $branches = Branch::active()->orderBy('name')->get();
        $isBankEmployee = $user->hasRole('bank_employee');
        // Bank employees participate in these stages (via default_role or phase actions)
        $bankEmployeeStages = ['bsm_osv', 'rate_pf', 'sanction', 'legal_verification', 'esign'];
        // Non-bank-employees see top-level stages plus bsm_osv (a parallel sub-stage)
        // so they can filter for loans currently sitting at BSM/OSV.
        $stages = Stage::where('is_enabled', true)
            ->when($isBankEmployee, fn ($q) => $q->whereIn('stage_key', $bankEmployeeStages))
            ->when(! $isBankEmployee, fn ($q) => $q->where(
                fn ($w) => $w->whereNull('parent_stage_key')->orWhere('stage_key', 'bsm_osv')
            ))
            ->orderBy('sequence_order')
            ->get();

        // Product filter options — bank employees only see their own bank's products.
        $products = Product::active()->with('bank')
            ->when($isBankEmployee && $user->task_bank_id, fn ($q) => $q->where('bank_id', $user->task_bank_id))
            ->orderBy('name')->get();

        $roles = Role::orderBy('id')->get();
        $isAdminOrManager = $user->hasAnyRole(['super_admin', 'admin', 'branch_manager', 'bdh']);
        $permissions = [
            'create_loan' => $user->hasPermission('create_loan'),
            'edit_loan' => $user->hasPermission('edit_loan'),
            'delete_loan' => $user->hasPermission('delete_loan'),
            'is_bank_employee' => $isBankEmployee,
            'is_admin_or_manager' => $isAdminOrManager,
        ];

        // User filter list (current task owner) — only for admin/manager, mirroring the Owner Role filter.
        $users = $isAdminOrManager
            ? User::where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        $template = 'newtheme.loans.index';

        return view($template, compact('stats', 'banks', 'branches', 'products', 'stages', 'roles', 'users', 'permissions'));
    }

    public function loanData(Request $request): JsonResponse
    {
        $user = auth()->user();
        $canEdit = $user->hasPermission('edit_loan');
        $canDelete = $user->hasPermission('delete_loan');

        $query = LoanDetail::visibleTo($user)->with(['creator', 'advisor', 'bank', 'branch', 'product', 'location.parent', 'stageAssignments.assignee.roles']);

        $recordsTotal = (clone $query)->count();

        // Custom filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }
        if ($request->filled('bank_id')) {
            $query->where('bank_id', $request->bank_id);
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        // Stage + date range filter on stage COMPLETION activity, not on
        // loan_details.created_at. With a stage selected we match loans that
        // have COMPLETED that stage (optionally with its completion date inside
        // the range). With only a date range we match the loan's LATEST stage
        // completion, so it surfaces in the window where it last moved.
        $stage = $request->filled('stage') ? $request->stage : null;
        $dateFrom = $request->filled('date_from') ? $request->date_from : null;
        $dateTo = $request->filled('date_to') ? $request->date_to : null;

        if ($stage !== null) {
            // "Loans where this stage is completed" — one closure matches a
            // single completed stage_assignments row (works for top-level and
            // parallel sub-stage keys alike); date bounds apply to its completed_at.
            $query->whereHas('stageAssignments', function ($q) use ($stage, $dateFrom, $dateTo) {
                $q->where('stage_key', $stage)->where('status', 'completed');
                if ($dateFrom !== null) {
                    $q->whereDate('completed_at', '>=', $dateFrom);
                }
                if ($dateTo !== null) {
                    $q->whereDate('completed_at', '<=', $dateTo);
                }
            });
        } elseif ($dateFrom !== null || $dateTo !== null) {
            // Date range without a stage → filter on the loan's most-recent
            // stage completion (MAX(completed_at)). Correlated subquery is
            // portable across MySQL (prod) and SQLite (tests); DATE() drops time.
            $latest = '(SELECT MAX(sa.completed_at) FROM stage_assignments sa WHERE sa.loan_id = loan_details.id)';
            if ($dateFrom !== null) {
                $query->whereRaw("DATE($latest) >= ?", [$dateFrom]);
            }
            if ($dateTo !== null) {
                $query->whereRaw("DATE($latest) <= ?", [$dateTo]);
            }
        }
        if ($request->filled('role')) {
            $role = $request->role;
            $query->whereHas('stageAssignments', function ($q) use ($role) {
                $q->whereColumn('stage_key', 'loan_details.current_stage')
                    ->whereHas('assignee', fn ($uq) => $uq->whereHas('roles', fn ($rq) => $rq->where('slug', $role)));
            });
        }
        // Current task owner filter — matches any user shown in the Task Owner
        // column: the current-stage assignee, OR (only while the loan is in
        // parallel_processing) the assignee of any in-progress sub-stage.
        if ($request->filled('user')) {
            $userId = (int) $request->user;
            $query->where(function ($outer) use ($userId) {
                $outer->whereHas('stageAssignments', fn ($q) => $q
                    ->where('assigned_to', $userId)
                    ->whereColumn('stage_key', 'loan_details.current_stage')
                )->orWhere(fn ($w) => $w
                    ->where('current_stage', 'parallel_processing')
                    ->whereHas('stageAssignments', fn ($q) => $q
                        ->where('assigned_to', $userId)
                        ->where('parent_stage_key', 'parallel_processing')
                        ->where('status', 'in_progress')
                    )
                );
            });
        }
        if ($request->filled('docket')) {
            $docket = $request->docket;
            $sPlus = ['s_plus_1' => '1', 's_plus_2' => '2', 's_plus_3' => '3'];

            // Both branches restrict to active loans with docket not yet completed.
            $query->where('status', 'active')
                ->whereHas('stageAssignments', fn ($q) => $q->where('stage_key', 'docket')->where('status', '!=', 'completed'));

            if (isset($sPlus[$docket])) {
                // Commitment-type filter: read docket_days_offset from app_number notes,
                // ignore the date entirely. Surfaces all S+N loans regardless of stage.
                $query->whereHas('stageAssignments', fn ($q) => $q
                    ->where('stage_key', 'app_number')
                    ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(notes, '$.docket_days_offset')) = ?", [$sPlus[$docket]])
                );
            } else {
                // Date-range filter. Effective docket date is COALESCE of:
                //   1. loan_details.expected_docket_date (authoritative, set when sanction completes)
                //   2. app_number notes.custom_docket_date (when offset='0', d/m/Y string)
                //   3. today + app_number notes.docket_days_offset (tentative for pre-sanction loans)
                $today = now()->toDateString();
                $rangeEnd = match ($docket) {
                    'overdue' => null,
                    'due_today' => $today,
                    'due_soon' => now()->addDays(7)->toDateString(),
                    'due_15' => now()->addDays(15)->toDateString(),
                    'due_month' => now()->addMonth()->toDateString(),
                    'custom' => $request->filled('docket_date') ? $request->docket_date : null,
                    default => null,
                };

                $effDateSql = "COALESCE(
                    ld_inner.expected_docket_date,
                    STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(sa_app.notes, '$.custom_docket_date')), '%d/%m/%Y'),
                    DATE_ADD(CURDATE(), INTERVAL CAST(JSON_UNQUOTE(JSON_EXTRACT(sa_app.notes, '$.docket_days_offset')) AS UNSIGNED) DAY)
                )";

                if ($docket === 'overdue') {
                    $query->whereRaw("EXISTS (
                        SELECT 1 FROM loan_details ld_inner
                        LEFT JOIN stage_assignments sa_app ON sa_app.loan_id = ld_inner.id AND sa_app.stage_key = 'app_number'
                        WHERE ld_inner.id = loan_details.id
                          AND {$effDateSql} IS NOT NULL
                          AND {$effDateSql} < ?
                    )", [$today]);
                } elseif ($rangeEnd !== null) {
                    $query->whereRaw("EXISTS (
                        SELECT 1 FROM loan_details ld_inner
                        LEFT JOIN stage_assignments sa_app ON sa_app.loan_id = ld_inner.id AND sa_app.stage_key = 'app_number'
                        WHERE ld_inner.id = loan_details.id
                          AND {$effDateSql} IS NOT NULL
                          AND {$effDateSql} BETWEEN ? AND ?
                    )", [$today, $rangeEnd]);
                }
            }
        }

        // Search
        $search = $request->input('search.value', '');
        if ($search !== '' && $search !== null) {
            $query->where(function ($q) use ($search) {
                $q->where('loan_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        $recordsFiltered = (clone $query)->count();

        // Ordering
        $columns = ['loan_number', 'customer_name', 'bank_name', 'product_id', 'loan_amount', 'current_stage', 'status', 'updated_at'];
        $orderColumnIndex = (int) $request->input('order.0.column', 7);
        $orderDir = $request->input('order.0.dir', 'desc') === 'asc' ? 'asc' : 'desc';
        $orderColumn = $columns[$orderColumnIndex] ?? 'updated_at';
        $query->orderBy($orderColumn, $orderDir);

        // Pagination
        $start = (int) $request->input('start', 0);
        $length = (int) $request->input('length', 25);
        $loans = $query->skip($start)->take($length)->get();

        $data = $loans->map(function ($loan) use ($canEdit) {
            $ntIcon = fn (string $path) => '<svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="'.$path.'"/></svg>';
            $actions = '<div class="lx-actions">';
            $actions .= '<a class="lx-act tone-info" href="'.route('loans.show', $loan).'" title="View" aria-label="View">'.$ntIcon('M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z').'</a>';
            if (in_array($loan->status, ['active', 'on_hold'])) {
                $actions .= '<a class="lx-act tone-accent" href="'.route('loans.stages', $loan).'" title="Stages" aria-label="Stages">'.$ntIcon('M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4').'</a>';
            }
            if ($canEdit && ! $loan->isBasicEditLocked()) {
                $actions .= '<a class="lx-act tone-gray" href="'.route('loans.edit', $loan).'" title="Edit" aria-label="Edit">'.$ntIcon('M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z').'</a>';
            }
            $actions .= '</div>';

            $ownerName = $loan->current_owner?->name ?? '—';
            $timeWithOwner = $loan->time_with_current_owner;

            // Sanctioned & disbursed amounts now live in dedicated loan columns
            // (populated at docket login and disbursement respectively), shown as
            // separate columns in the listing. Em-dash when not yet captured.
            $amountInfo = $loan->formatted_amount;
            $sanctionedDisplay = $loan->formatted_sanctioned_amount ?? '—';
            $disbursedDisplay = $loan->formatted_disbursed_amount ?? '—';

            $locationName = $loan->location ? ($loan->location->parent?->name ? $loan->location->parent->name.'/' : '').$loan->location->name : '';

            // Docket overdue check
            $isOverdue = false;
            $docketInfo = '';
            if ($loan->status === 'active') {
                $appNumberAssignment = $loan->stageAssignments->where('stage_key', 'app_number')->first();
                $docketAssignment = $loan->stageAssignments->where('stage_key', 'docket')->first();
                if ($appNumberAssignment?->status === 'completed' && $docketAssignment && $docketAssignment->status !== 'completed') {
                    $appNotes = $appNumberAssignment->getNotesData();
                    $sanctionAssignmentForDocket = $loan->stageAssignments->where('stage_key', 'sanction')->first();

                    $expectedDate = null;
                    if (! empty($appNotes['custom_docket_date'])) {
                        $expectedDate = Carbon::createFromFormat('d/m/Y', $appNotes['custom_docket_date']);
                    } elseif (! empty($appNotes['docket_days_offset']) && $sanctionAssignmentForDocket?->completed_at) {
                        $expectedDate = $sanctionAssignmentForDocket->completed_at->copy()->addDays((int) $appNotes['docket_days_offset']);
                    }

                    if ($expectedDate) {
                        $isOverdue = now()->gt($expectedDate);
                        $docketInfo = $expectedDate->format('d M Y');
                    }
                }
            }

            return [
                'loan_number' => $loan->loan_number.($isOverdue ? '<br><small class="text-danger fw-bold" title="Docket expected by '.$docketInfo.'">⚠ Docket Overdue</small>' : ($docketInfo ? '<br><small class="text-muted" title="Expected docket date">📅 '.$docketInfo.'</small>' : '')),
                'customer_name' => $loan->customer_name,
                'show_url' => route('loans.show', $loan),
                'stages_url' => route('loans.stages', $loan),
                'bank_name' => $loan->bank_name ?? '—',
                'bank_product' => ($loan->bank_name ?? '—').'<br><small class="text-muted">'.($loan->product?->name ?? '—').'</small>'
                    .($locationName ? '<br><small class="location-info" style="font-size:0.65rem;">'.$locationName.'</small>' : ''),
                'location_name' => $locationName,
                'amount_info' => $amountInfo,
                'formatted_amount' => $loan->formatted_amount,
                'sanctioned_info' => $sanctionedDisplay,
                'disbursed_info' => $disbursedDisplay,
                'current_stage_name' => $loan->stage_badge_html,
                'owner_info' => $ownerName !== '—' ? $ownerName.'<br><small class="text-muted">'.$timeWithOwner.'</small>' : '—',
                'task_owner_info' => $loan->current_task_owners->pluck('name')->implode(', ') ?: '—',
                'status_label' => '<span class="shf-badge shf-badge-'.$this->statusBadgeClass($loan->status).'">'.$loan->status_label.'</span>'
                    .(in_array($loan->status, ['on_hold', 'cancelled', 'rejected']) && ($loan->status_reason || $loan->rejection_reason)
                        ? '<br><small class="text-muted" title="'.e($loan->status_reason ?? $loan->rejection_reason).'">'.e(\Str::limit($loan->status_reason ?? $loan->rejection_reason, 40)).'</small>'
                        : ''),
                'created_at' => $loan->created_at?->format('d M Y').'<br><small class="text-muted">'.$loan->total_loan_time.'</small>',
                'actions_html' => $actions,
            ];
        });

        return response()->json([
            'draw' => (int) $request->input('draw', 1),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data->values(),
        ]);
    }

    public function create()
    {
        if (! auth()->user()->canCreateLoans()) {
            abort(403, 'You do not have permission to create loans.');
        }

        $user = auth()->user();
        $banks = Bank::active()->orderBy('name')->get();

        // Filter branches: admin/super_admin see all, others see only their assigned branches
        $isAdminOrSuper = $user->hasAnyRole(['super_admin', 'admin']);
        $branches = $isAdminOrSuper
            ? Branch::active()->with('location.parent')->orderBy('name')->get()
            : $user->branches()->where('is_active', true)->with('location.parent')->orderBy('name')->get();

        $products = Product::active()->with(['bank', 'locations'])->orderBy('name')->get();
        $advisors = User::advisorEligible()->with(['branches', 'locations'])->orderBy('name')->get();

        // Build branch → location map and product → location map for JS filtering
        $branchLocationMap = $branches->mapWithKeys(fn ($b) => [
            $b->id => ['city_id' => $b->location_id, 'state_id' => $b->location?->parent_id],
        ]);
        $productLocationMap = $products->mapWithKeys(fn ($p) => [
            $p->id => $p->locations->pluck('id')->toArray(),
        ]);

        $template = 'newtheme.loans.create';

        return view($template, compact('banks', 'branches', 'products', 'advisors', 'branchLocationMap', 'productLocationMap') + ['pageKey' => 'loans']);
    }

    public function store(Request $request)
    {
        if (! auth()->user()->canCreateLoans()) {
            abort(403, 'You do not have permission to create loans.');
        }

        $validated = $request->validate(LoanValidationRules::upsert());

        // Convert date format for storage
        $validated['date_of_birth'] = Carbon::createFromFormat('d/m/Y', $validated['date_of_birth'])->toDateString();
        $validated['pan_number'] = strtoupper($validated['pan_number']);

        try {
            $loan = $this->conversionService->createDirectLoan($validated);
        } catch (\Throwable $e) {
            // Surface a clear error instead of a raw 500 so the user knows the
            // loan was NOT created and can retry.
            report($e);

            return redirect()->back()->withInput()
                ->with('error', 'Could not create the loan. Please try again.');
        }

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Loan #'.$loan->loan_number.' created successfully');
    }

    public function show(LoanDetail $loan)
    {
        $this->authorizeView($loan);

        $loan->load(['quotation', 'branch', 'bank', 'product', 'creator', 'advisor', 'dme', 'customerKycDetails', 'location.parent']);
        $stages = app(LoanStageService::class)->getOrderedStages();

        // DME change UI: only once the Application Number sub-stage is complete.
        $appNumberDone = $loan->stageAssignments()
            ->where('stage_key', 'app_number')
            ->where('status', 'completed')
            ->exists();
        $canChangeDme = auth()->user()->hasAnyRole(['super_admin', 'admin', 'bdh']);
        $dmeUsers = ($appNumberDone && $canChangeDme)
            ? User::whereHas('roles', fn ($q) => $q->whereNotIn('slug', ['super_admin', 'admin']))
                ->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            : collect();

        $template = 'newtheme.loans.show';

        return view($template, compact('loan', 'stages', 'appNumberDone', 'canChangeDme', 'dmeUsers') + ['pageKey' => 'loans']);
    }

    /**
     * Set or change the loan's DME (Direct Marketing Executive). Allowed only
     * after the Application Number sub-stage is complete, and only for
     * super_admin / admin / bdh — at which point it's locked from the stage form.
     */
    public function updateDme(Request $request, LoanDetail $loan): JsonResponse
    {
        $this->authorizeView($loan);

        abort_unless(auth()->user()->hasAnyRole(['super_admin', 'admin', 'bdh']), 403, 'You cannot change the DME for this loan.');

        $appNumberDone = $loan->stageAssignments()
            ->where('stage_key', 'app_number')
            ->where('status', 'completed')
            ->exists();
        abort_unless($appNumberDone, 422, 'DME can only be set after the Application Number stage is complete.');

        $validated = $request->validate([
            'dme_user_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_active', true)),
            ],
        ]);

        // Disallow assigning a super_admin/admin as DME (matches the dropdown).
        $target = User::with('roles')->find($validated['dme_user_id']);
        abort_if(! $target || $target->hasAnyRole(['super_admin', 'admin']), 422, 'Selected user is not a valid DME.');

        $previous = $loan->dme_user_id;
        $loan->update(['dme_user_id' => $target->id]);

        ActivityLog::log('change_dme', $loan, [
            'from' => $previous,
            'to' => $target->id,
            'dme_name' => $target->name,
        ]);

        return response()->json([
            'success' => true,
            'dme_name' => $target->name,
            'message' => 'DME updated.',
        ]);
    }

    public function timeline(LoanDetail $loan)
    {
        $this->authorizeView($loan);

        $timeline = app(LoanTimelineService::class)->getTimeline($loan);

        $template = 'newtheme.loans.timeline';

        return view($template, compact('loan', 'timeline') + ['pageKey' => 'loans']);
    }

    public function edit(LoanDetail $loan)
    {
        $this->authorizeView($loan);
        $loan->loadMissing('stageAssignments');

        if ($loan->isBasicEditLocked()) {
            return redirect()->route('loans.show', $loan)
                ->with('error', 'Loan details cannot be edited after Application Number has been completed.');
        }

        $banks = Bank::active()->orderBy('name')->get();
        $branches = Branch::active()->with('location.parent')->orderBy('name')->get();
        $products = Product::active()->with(['bank', 'locations'])->orderBy('name')->get();
        $advisors = User::whereHas('roles', fn ($q) => $q->whereNotIn('slug', ['super_admin', 'admin']))->where('is_active', true)->with(['branches', 'locations'])->orderBy('name')->get();

        $template = 'newtheme.loans.edit';

        return view($template, compact('loan', 'banks', 'branches', 'products', 'advisors') + ['pageKey' => 'loans']);
    }

    public function update(Request $request, LoanDetail $loan)
    {
        $this->authorizeView($loan);
        $loan->loadMissing('stageAssignments');

        if ($loan->isBasicEditLocked()) {
            return redirect()->route('loans.show', $loan)
                ->with('error', 'Loan details cannot be edited after Application Number has been completed.');
        }

        $validated = $request->validate(LoanValidationRules::upsert());

        // Convert date format for storage
        $validated['date_of_birth'] = Carbon::createFromFormat('d/m/Y', $validated['date_of_birth'])->toDateString();
        $validated['pan_number'] = strtoupper($validated['pan_number']);

        // Track changed fields
        $changed = array_keys(array_diff_assoc($validated, $loan->only(array_keys($validated))));

        if ($validated['bank_id'] ?? null) {
            $validated['bank_name'] = Bank::find($validated['bank_id'])?->name;
        }

        $loan->update($validated);

        // Reconcile customer identity + KYC snapshot (update in place if PAN
        // unchanged; re-resolve master + new snapshot if the PAN changed).
        $this->customerService->syncLoanKyc($loan, [
            'customer_name' => $validated['customer_name'],
            'mobile' => $validated['customer_phone'] ?? null,
            'email' => $validated['customer_email'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'pan_number' => $validated['pan_number'] ?? null,
        ]);

        ActivityLog::log('edit_loan', $loan, [
            'loan_number' => $loan->loan_number,
            'changed_fields' => $changed,
        ]);

        return redirect()->route('loans.show', $loan)->with('success', 'Loan updated');
    }

    public function updateStatus(Request $request, LoanDetail $loan): JsonResponse
    {
        $validated = $request->validate(LoanValidationRules::statusChange());

        $user = auth()->user();

        // Only BDH/Branch Manager/Admin/Super Admin can cancel or reactivate rejected loans
        if ($validated['status'] === 'cancelled' || ($validated['status'] === 'active' && $loan->status === 'rejected')) {
            if (! $user->hasAnyRole(['super_admin', 'admin', 'branch_manager', 'bdh'])) {
                return response()->json(['error' => 'Only Branch Manager or BDH can perform this action'], 403);
            }
        }

        $oldStatus = $loan->status;
        $updateData = ['status' => $validated['status']];

        if (in_array($validated['status'], ['on_hold', 'cancelled'])) {
            $updateData['status_reason'] = $validated['reason'];
            $updateData['status_changed_at'] = now();
            $updateData['status_changed_by'] = auth()->id();
        } elseif ($validated['status'] === 'active') {
            $updateData['status_reason'] = null;
            $updateData['status_changed_at'] = now();
            $updateData['status_changed_by'] = auth()->id();

            // Reactivating from rejected — clear rejection fields and restore stages
            if ($oldStatus === 'rejected') {
                $updateData['rejected_at'] = null;
                $updateData['rejected_by'] = null;
                $updateData['rejected_stage'] = null;
                $updateData['rejection_reason'] = null;
                $updateData['is_sanctioned'] = false;

                // Restore rejected stages to their previous status
                $loan->stageAssignments()
                    ->where('status', 'rejected')
                    ->get()
                    ->each(function ($sa) {
                        $restoreTo = $sa->previous_status ?? 'pending';
                        $sa->update([
                            'status' => $restoreTo,
                            'previous_status' => null,
                            'completed_at' => null,
                            'completed_by' => null,
                        ]);
                    });

                // Recalculate progress
                app(LoanStageService::class)->recalculateProgress($loan);
            }
        }

        $loan->update($updateData);

        ActivityLog::log('change_loan_status', $loan, [
            'loan_number' => $loan->loan_number,
            'old_status' => $oldStatus,
            'new_status' => $validated['status'],
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json(['success' => true, 'status' => $validated['status']]);
    }

    public function destroy(LoanDetail $loan): JsonResponse
    {
        // Only someone who can see this loan may delete it — the delete_loan
        // permission alone must not let a user destroy loans outside their scope.
        $this->authorizeView($loan);

        // Clear loan reference on linked quotation so it can be re-converted
        Quotation::where('loan_id', $loan->id)->update(['loan_id' => null]);

        ActivityLog::log('delete_loan', null, [
            'loan_number' => $loan->loan_number,
            'customer_name' => $loan->customer_name,
        ]);

        $loan->delete();

        return response()->json(['success' => true, 'redirect' => route('loans.index')]);
    }

    private function authorizeView(LoanDetail $loan): void
    {
        $user = auth()->user();
        if ($user->hasPermission('view_all_loans')) {
            return;
        }
        if ($loan->created_by === $user->id || $loan->assigned_advisor === $user->id) {
            return;
        }
        if ($loan->stageAssignments()->where('assigned_to', $user->id)->exists()) {
            return;
        }
        // Branch managers and BDHs can view loans in their branches
        if (($user->hasRole('branch_manager') || $user->hasRole('bdh')) && $loan->branch_id) {
            if ($user->branches()->where('branches.id', $loan->branch_id)->exists()) {
                return;
            }
        }
        abort(403);
    }

    private function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'active' => 'blue',
            'completed' => 'green',
            'rejected' => 'orange',
            'cancelled' => 'gray',
            'on_hold' => 'orange',
            default => 'gray',
        };
    }
}
