    <div class="py-4 shf-stages-wrap" data-loan-amount="{{ $loan->loan_amount }}">
        <div class="px-3 px-sm-4 px-lg-5">

            {{-- Customer & Loan Info (compact) --}}
            <div class="card mb-3 border-0 bg-body-tertiary">
                <div class="card-body py-2 px-3">
                    <div class="row g-1 shf-text-xs">
                        <div class="col-6 col-md-3"><span class="text-muted">Customer:</span>
                            <strong>{{ $loan->customer_name }}</strong>
                        </div>
                        <div class="col-6 col-md-3"><span class="text-muted">Bank:</span>
                            <strong>{{ $loan->bank_name ?? '—' }}</strong>
                        </div>
                        <div class="col-6 col-md-3"><span class="text-muted">Product:</span>
                            <strong>{{ $loan->product?->name ?? '—' }}</strong>
                        </div>
                        <div class="col-6 col-md-3"><span class="text-muted">Amount:</span> <strong>₹
                                {{ number_format($loan->loan_amount) }}</strong></div>
                        @if ($loan->branch)
                            <div class="col-6 col-md-3"><span class="text-muted">Branch:</span>
                                <strong>{{ $loan->branch->name }}</strong>
                            </div>
                        @endif
                        @if ($loan->location)
                            <div class="col-6 col-md-3"><span class="text-muted">Location:</span>
                                <strong>{{ $loan->location->name }}</strong>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Stage Pipeline --}}
            @if ($progress)
                @php
                    // Stage icon SVGs (stroke-based, no fill)
                    $stageIcons = [
                        'inquiry' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>',
                        'document_selection' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
                        'document_collection' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>',
                        'parallel_processing' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>',
                        'sanction_decision' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                        'rate_pf' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                        'sanction' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
                        'docket' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>',
                        'kfs' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>',
                        'esign' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>',
                        'disbursement' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>',
                        'otc_clearance' =>
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>',
                    ];
                @endphp
                @php
                    // Animation with capped total budget for completed stages:
                    // Total budget for completed = 1200ms, divided equally per beat.
                    // Minimum 80ms, maximum 180ms per beat. Pending stages: 80ms.
                    $delayPending = 80;
                    $isLoanActive = $loan->status === 'active';

                    // Count completed beats to calculate per-beat delay
                    // Completed stages: connector appear+fill merged into 1 beat + dot = 2 beats
                    // Pending stages: connector appear + dot = 2 beats (no fill)
                    $completedBeats = 0;
                    $tempPastCurrent = false;
                    foreach ($mainStages->values() as $idx => $sa) {
                        $isCur = $loan->current_stage === $sa->stage_key && $isLoanActive;
                        if (!$tempPastCurrent) {
                            if ($idx > 0) $completedBeats++; // connector (appear+fill merged)
                            $completedBeats++; // dot beat
                        }
                        if ($isCur || ($sa->status === 'in_progress' && !$isCur)) $tempPastCurrent = true;
                    }
                    $delayActive = $completedBeats > 0 ? max(60, min(150, intval(1000 / $completedBeats))) : 150;

                    $animMs = 0;
                    $animData = [];
                    $pastCurrent = false;
                    foreach ($mainStages->values() as $i => $sa) {
                        $isCurrent = $loan->current_stage === $sa->stage_key && $isLoanActive;
                        $isCompleted = in_array($sa->status, ['completed', 'skipped', 'in_progress']) || $isCurrent;
                        $beat = $pastCurrent ? $delayPending : $delayActive;

                        if ($i > 0) {
                            $animData[$i]['conn_appear'] = $animMs;
                            // Completed: fill starts at same time as appear (merged beat)
                            if (!$pastCurrent && $isCompleted) {
                                $animData[$i]['conn_fill'] = $animMs;
                            }
                            $animMs += $beat;
                        }
                        $animData[$i]['dot_step'] = $animMs;
                        $animMs += $beat;

                        if ($isCurrent || ($sa->status === 'in_progress' && !$isCurrent)) {
                            $pastCurrent = true;
                        }
                    }
                @endphp
                <div class="mb-4">
                    <div class="shf-pipeline" data-animate data-anim-duration="{{ $animMs + 500 }}"
                        data-progress="{{ number_format($progress->overall_percentage, 0) }}">
                        {{-- SVG border that fills around the perimeter --}}
                        <svg class="shf-pipeline-border">
                            <path class="shf-pipeline-border-path" />
                        </svg>
                        @foreach ($mainStages as $sa)
                            @php
                                $idx = $loop->index;
                                $isCurrent = $loan->current_stage === $sa->stage_key && $loan->status === 'active';
                                $dotClass = match (true) {
                                    $isCurrent => 'shf-stage-dot--current',
                                    $sa->status === 'completed' => 'shf-stage-dot--completed',
                                    $sa->status === 'in_progress' => 'shf-stage-dot--in-progress',
                                    $sa->status === 'skipped' => 'shf-stage-dot--skipped',
                                    $sa->status === 'rejected' => 'shf-stage-dot--rejected',
                                    default => 'shf-stage-dot--pending',
                                };
                                $labelClass = match (true) {
                                    $isCurrent => 'shf-stage-label--current',
                                    in_array($sa->status, ['completed', 'skipped']) => 'shf-stage-label--completed',
                                    default => '',
                                };
                                $stageName = $sa->stage?->stage_name_en ?? 'Stage ' . $loop->iteration;
                                $shortName = str_replace(
                                    [
                                        'Loan Inquiry',
                                        'Document Selection',
                                        'Document Collection',
                                        'Parallel Processing',
                                        'Sanction Decision',
                                        'Sanction Letter',
                                        'Docket Login',
                                        'KFS Generation',
                                        'E-Sign & eNACH',
                                        'Disbursement',
                                        'OTC Clearance',
                                    ],
                                    [
                                        'Inquiry',
                                        'Doc Select',
                                        'Doc Collect',
                                        'Parallel',
                                        'Sanction Dec',
                                        'Sanction Ltr',
                                        'Docket',
                                        'KFS',
                                        'E-Sign',
                                        'Disburse',
                                        'OTC',
                                    ],
                                    $stageName,
                                );
                                $iconSvg = $stageIcons[$sa->stage_key] ?? $stageIcons['inquiry'];

                                // Connector class
                                $prevSa = $loop->first ? null : $mainStages->values()[$idx - 1];
                                $connClass = 'shf-connector--empty';
                                if ($prevSa) {
                                    $connClass = match (true) {
                                        in_array($prevSa->status, ['completed', 'skipped']) &&
                                            in_array($sa->status, ['completed', 'skipped'])
                                            => 'shf-connector--filled',
                                        in_array($prevSa->status, ['completed', 'skipped']) &&
                                            ($isCurrent || $sa->status === 'in_progress')
                                            => 'shf-connector--arriving',
                                        (($loan->current_stage === $prevSa->stage_key && $isLoanActive) ||
                                            $prevSa->status === 'in_progress') &&
                                            $sa->status === 'pending'
                                            => 'shf-connector--leaving',
                                        default => 'shf-connector--empty',
                                    };
                                }

                                // Animation delays (already in ms)
                                $connAppearDelay = $animData[$idx]['conn_appear'] ?? null;
                                $connFillDelay = $animData[$idx]['conn_fill'] ?? null;
                                $dotDelay = $animData[$idx]['dot_step'] ?? null;
                            @endphp

                            {{-- Connector before this step --}}
                            @if (!$loop->first)
                                <div class="shf-connector {{ $connClass }} shf-anim-conn-appear"
                                    style="--appear-delay:{{ $connAppearDelay ?? 0 }}ms;{{ $connFillDelay !== null ? '--fill-delay:' . $connFillDelay . 'ms' : '' }}">
                                </div>
                            @endif

                            <div class="shf-pipeline-step">
                                <div class="rounded-circle d-flex align-items-center justify-content-center shf-stage-dot {{ $dotClass }} shf-anim-dot"
                                    title="{{ $stageName }}" style="--anim-delay:{{ $dotDelay ?? 0 }}ms">
                                    <svg fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">{!! $iconSvg !!}</svg>
                                </div>
                                <div class="shf-stage-label {{ $labelClass }}">{{ $shortName }}</div>
                            </div>
                        @endforeach
                    </div>

                </div>
            @endif

            {{-- Precompute role labels, bank filter, and location context once --}}
            @php
                $roleLabels = \App\Models\Role::pluck('name', 'slug')->toArray();
                $bankFilterRoles = ['bank_employee', 'office_employee'];
                $locationRoles = ['bank_employee'];
                $branchRoles = ['office_employee', 'loan_advisor', 'branch_manager', 'bdh'];
                $loanCityId = $loan->branch?->location_id;
                $loanStateId = $loan->branch?->location?->parent_id;
            @endphp

            {{-- Stage Cards --}}
            <div class="row g-3">
                @foreach ($mainStages as $assignment)
                    @php
                        // Precompute stage-specific data once per card
                        $stageKey = $assignment->stage_key;
                        $eligibleRoles = $stageRoleEligibility[$stageKey] ?? [];
                        $stageUsers = $eligibleRoles
                            ? $allActiveUsers->filter(fn($u) => $u->hasAnyRole($eligibleRoles))
                            : collect();
                        // Filter by bank, location, and branch based on role type
                        $stageUsers = $stageUsers->filter(function ($u) use (
                            $loan,
                            $bankFilterRoles,
                            $locationRoles,
                            $branchRoles,
                            $loanCityId,
                            $loanStateId,
                        ) {
                            if (
                                $u->hasAnyRole($bankFilterRoles) &&
                                $loan->bank_id &&
                                !$u->employerBanks->contains('id', $loan->bank_id)
                            ) {
                                return false;
                            }
                            if (
                                $u->hasAnyRole($locationRoles) &&
                                $loanCityId &&
                                $u->locations->isNotEmpty() &&
                                !$u->locations->contains('id', $loanCityId) &&
                                !$u->locations->contains('id', $loanStateId)
                            ) {
                                return false;
                            }
                            if (
                                $u->hasAnyRole($branchRoles) &&
                                $loan->branch_id &&
                                $u->branches->isNotEmpty() &&
                                !$u->branches->contains('id', $loan->branch_id)
                            ) {
                                return false;
                            }
                            return true;
                        });
                        $assignedUserRole = $assignment->assignee
                            ? \App\Models\LoanDetail::userRoleSlug($assignment->assignee)
                            : null;
                        $isMainAssignee =
                            $assignment->assigned_to === auth()->id() ||
                            auth()
                                ->user()
                                ->hasAnyRole(['super_admin', 'admin']);
                    @endphp
                    @php
                        // parallel_processing parent is never directly actionable — sub-stages are
                        // No actions on completed/rejected/cancelled loans
                        $isActionable =
                            $loan->status === 'active' &&
                            $isMainAssignee &&
                            $assignment->status === 'in_progress' &&
                            $assignment->stage_key !== 'parallel_processing';
                    @endphp
                    <div
                        class="{{ $assignment->status === 'in_progress' || $assignment->stage_key === 'parallel_processing' ? 'col-12' : 'col-xl-6' }}">
                        <div class="card mb-0 border-start border-3 {{ match ($assignment->status) {
                            'completed' => 'border-success',
                            'in_progress' => 'border-primary',
                            'rejected' => 'border-danger',
                            'skipped' => 'border-warning',
                            default => 'border-secondary',
                        } }}"
                            id="stage-{{ $assignment->stage_key }}"
                            @if ($isActionable) data-actionable="true" @endif>
                            <div
                                class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-1 py-2">
                                <div class="d-flex align-items-center gap-2">
                                    <span
                                        class="shf-badge shf-badge-{{ match (\App\Models\StageAssignment::STATUS_LABELS[$assignment->status]['color']) {
                                            'success' => 'green',
                                            'primary' => 'blue',
                                            'danger' => 'orange',
                                            'warning' => 'orange',
                                            default => 'gray',
                                        } }} shf-text-xs">
                                        {{ \App\Models\StageAssignment::STATUS_LABELS[$assignment->status]['label'] }}
                                    </span>
                                    <strong>{{ $assignment->stage?->stage_name_en }}</strong>
                                    @if ($assignment->stage?->stage_name_gu)
                                        <small
                                            class="text-muted d-none d-sm-inline">({{ $assignment->stage->stage_name_gu }})</small>
                                    @endif
                                </div>
                                @if ($assignment->assignee)
                                    <div class="d-flex align-items-center gap-1">
                                        <small class="text-muted">{{ $assignment->assignee->name }}</small>
                                        @if (config('app.show_stage_impersonate') &&
                                                auth()->user()->isSuperAdmin() &&
                                                $assignment->assigned_to &&
                                                $assignment->assigned_to !== auth()->id())
                                            <a href="/impersonate/take/{{ $assignment->assigned_to }}"
                                                class="shf-badge shf-badge-orange shf-text-2xs flex-shrink-0"
                                                title="Impersonate {{ $assignment->assignee->name }}"
                                                onclick="event.preventDefault(); Swal.fire({ title: 'Impersonate?', html: 'Switch to <strong>{{ $assignment->assignee->name }}</strong>', icon: 'question', showCancelButton: true, confirmButtonColor: '#f15a29', confirmButtonText: 'Yes' }).then(function(r) { if(r.isConfirmed) window.location.href='/impersonate/take/{{ $assignment->assigned_to }}'; });">
                                                <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="card-body py-2">
                                @if ($assignment->started_at)
                                    <small class="text-muted">Started:
                                        {{ $assignment->started_at->format('d M Y H:i') }}</small>
                                @endif
                                @if ($assignment->completed_at)
                                    <small
                                        class="text-muted ms-3">{{ $assignment->status === 'completed' ? 'Completed' : ucfirst($assignment->status) }}:
                                        {{ $assignment->completed_at->format('d M Y H:i') }}</small>
                                @endif

                                {{-- Phase progress indicator — reads from workflow_config snapshot --}}
                                @php
                                    $wfConfig = $loan->workflow_config ?? [];
                                    $stageWf = $wfConfig[$assignment->stage_key] ?? null;
                                    // Helper: get resolved role for a phase from snapshot
                                    $getPhaseRole = fn(int $phaseIdx) => $stageWf['phases'][(string)$phaseIdx]['role'] ?? 'task_owner';
                                    $stageSubActions = \App\Models\Stage::where('stage_key', $assignment->stage_key)->value('sub_actions');
                                    $stageSubActions = is_string($stageSubActions) ? json_decode($stageSubActions, true) : $stageSubActions;
                                    $phaseConfig = null;

                                    if (is_array($stageSubActions) && count($stageSubActions) > 1) {
                                        $roleLabelsMap = ['task_owner' => 'Task Owner', 'bank_employee' => 'Bank Employee', 'office_employee' => 'Office Employee'];
                                        $currentPhaseKey = match ($assignment->stage_key) {
                                            'rate_pf' => $assignment->getNotesData()['rate_pf_phase'] ?? '1',
                                            'sanction' => $assignment->getNotesData()['sanction_phase'] ?? '1',
                                            'legal_verification' => $assignment->getNotesData()['legal_phase'] ?? '1',
                                            'docket' => $assignment->getNotesData()['docket_phase'] ?? '1',
                                            'technical_valuation' => $assignment->getNotesData()['tv_phase'] ?? '1',
                                            'esign' => $assignment->getNotesData()['esign_phase'] ?? '1',
                                            default => '1',
                                        };
                                        $phases = [];
                                        foreach ($stageSubActions as $idx => $sa) {
                                            $phaseRole = $stageWf['phases'][(string)$idx]['role'] ?? $sa['role'] ?? 'task_owner';
                                            $phaseUserId = $stageWf['phases'][(string)$idx]['default_user_id'] ?? null;
                                            $phaseUserName = $phaseUserId ? (\App\Models\User::find($phaseUserId)?->name ?? '') : '';
                                            if (!$phaseUserName && $phaseRole === 'task_owner') {
                                                $phaseUserName = $loan->advisor?->name ?? $loan->creator?->name ?? '';
                                            }
                                            $phases[] = [
                                                'key' => (string)($idx + 1),
                                                'label' => $sa['name'] ?? $sa['key'] ?? 'Phase '.($idx + 1),
                                                'role' => $roleLabelsMap[$phaseRole] ?? ucwords(str_replace('_', ' ', $phaseRole)),
                                                'user' => $phaseUserName,
                                            ];
                                        }
                                        $phaseConfig = ['current' => $currentPhaseKey, 'phases' => $phases];
                                    }
                                @endphp
                                @php
                                    $roleBgCss = function ($role) {
                                        if (str_contains($role, 'Bank Employee')) {
                                            return 'shf-role-bg-bank-employee';
                                        }
                                        if (str_contains($role, 'Office Employee')) {
                                            return 'shf-role-bg-office-employee';
                                        }
                                        if (str_contains($role, 'Branch Manager')) {
                                            return 'shf-role-bg-branch-manager';
                                        }
                                        if (str_contains($role, 'Loan Advisor')) {
                                            return 'shf-role-bg-loan-advisor';
                                        }
                                        return 'shf-role-bg-task-owner';
                                    };
                                    $transferSuggestion = match ($assignment->stage_key) {
                                        'inquiry',
                                        'document_selection',
                                        'document_collection',
                                        'app_number'
                                            => 'Can transfer to: Loan Advisor, Branch Manager',
                                        'bsm_osv' => 'Can transfer to: Bank Employee (same bank)',
                                        'legal_verification'
                                            => 'Phase auto-transfers between Task Owner ↔ Bank Employee',
                                        'technical_valuation' => 'Phase auto-transfers: Task Owner → Office Employee',
                                        'property_valuation' => 'Can transfer to: Office Employee, Branch Manager',
                                        'rate_pf'
                                            => 'Phase auto-transfers: Loan Advisor → Bank Employee → Loan Advisor',
                                        'sanction' => 'Phase auto-transfers: Task Owner → Bank Employee → Task Owner',
                                        'docket' => 'Phase auto-transfers: Task Owner → Office Employee',
                                        'kfs' => 'Can transfer to: Loan Advisor, Branch Manager, Office Employee',
                                        'sanction_decision' => 'Escalation: Office Employee → Branch Manager → BDH',
                                        'esign' => 'Phase auto-transfers: Advisor → Bank → Advisor → Bank',
                                        'disbursement' => 'Assigned to Office Employee (per product stage config)',
                                        'otc_clearance'
                                            => 'Can transfer to: Office Employee, Loan Advisor, Branch Manager',
                                        default => null,
                                    };
                                @endphp
                                @if ($phaseConfig && in_array($assignment->status, ['in_progress', 'completed']))
                                    <div class="d-flex align-items-center gap-2 mt-2 mb-1 flex-wrap">
                                        @foreach ($phaseConfig['phases'] as $pi => $phase)
                                            @php
                                                $isCurrent = $assignment->status === 'in_progress' && $phaseConfig['current'] === $phase['key'];
                                                $isDone = $assignment->status === 'completed' || (int) $phaseConfig['current'] > (int) $phase['key'];
                                                $phaseUserLabel = !empty($phase['user']) ? $phase['user'] : $phase['role'];
                                            @endphp
                                            <div class="d-flex align-items-center gap-2">
                                                @if ($isDone)
                                                    <span class="shf-phase-pill shf-phase-pill--done">
                                                        <svg class="shf-icon-sm text-success" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="3" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        {{ $phase['label'] }}
                                                        <span class="shf-pill-role">({{ $phaseUserLabel }})</span>
                                                    </span>
                                                @elseif($isCurrent)
                                                    <span
                                                        class="shf-phase-pill shf-phase-pill--active {{ $roleBgCss($phase['role']) }}">
                                                        <svg class="shf-icon-sm" fill="currentColor" viewBox="0 0 24 24">
                                                            <circle cx="12" cy="12" r="5" />
                                                        </svg>
                                                        {{ $phase['label'] }}
                                                        <span class="shf-pill-role">({{ $phaseUserLabel }})</span>
                                                    </span>
                                                @else
                                                    <span class="shf-phase-pill shf-phase-pill--pending">
                                                        {{ $phase['label'] }}
                                                        <span class="shf-pill-role">({{ $phaseUserLabel }})</span>
                                                    </span>
                                                @endif
                                                @if (!$loop->last)
                                                    <svg class="shf-phase-chevron" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($transferSuggestion && $assignment->status === 'in_progress')
                                    <div class="shf-transfer-hint">
                                        <svg class="shf-icon-2xs shf-icon-inline" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                        </svg>
                                        {{ $transferSuggestion }}
                                    </div>
                                @endif

                                {{-- Parallel sub-stages --}}
                                @if ($assignment->stage_key === 'parallel_processing' && $subStages->isNotEmpty())
                                    <p class="text-muted small mt-2 mb-2">All parallel tracks must complete before
                                        advancing.</p>
                                    <div class="row">
                                        @foreach ($subStages->where('parent_stage_key', 'parallel_processing') as $sub)
                                            @php
                                                $subKey = $sub->stage_key;
                                                $subRoles = $stageRoleEligibility[$subKey] ?? [];
                                                $subUsers = $subRoles
                                                    ? $allActiveUsers->filter(fn($u) => $u->hasAnyRole($subRoles))
                                                    : collect();
                                                $subUsers = $subUsers->filter(function ($u) use (
                                                    $loan,
                                                    $bankFilterRoles,
                                                    $locationRoles,
                                                    $branchRoles,
                                                    $loanCityId,
                                                    $loanStateId,
                                                ) {
                                                    if (
                                                        $u->hasAnyRole($bankFilterRoles) &&
                                                        $loan->bank_id &&
                                                        !$u->employerBanks->contains('id', $loan->bank_id)
                                                    ) {
                                                        return false;
                                                    }
                                                    if (
                                                        $u->hasAnyRole($locationRoles) &&
                                                        $loanCityId &&
                                                        $u->locations->isNotEmpty() &&
                                                        !$u->locations->contains('id', $loanCityId) &&
                                                        !$u->locations->contains('id', $loanStateId)
                                                    ) {
                                                        return false;
                                                    }
                                                    if (
                                                        $u->hasAnyRole($branchRoles) &&
                                                        $loan->branch_id &&
                                                        $u->branches->isNotEmpty() &&
                                                        !$u->branches->contains('id', $loan->branch_id)
                                                    ) {
                                                        return false;
                                                    }
                                                    return true;
                                                });
                                                $subAssignedRole = $sub->assignee
                                                    ? \App\Models\LoanDetail::userRoleSlug($sub->assignee)
                                                    : null;
                                                $isSubAssignee =
                                                    $sub->assigned_to === auth()->id() ||
                                                    auth()
                                                        ->user()
                                                        ->hasAnyRole(['super_admin', 'admin']);
                                            @endphp
                                            @php $isSubActionable = $loan->status === 'active' && $isSubAssignee && $sub->status === 'in_progress'; @endphp
                                            <div class="col-md-6 mb-2">
                                                <div class="card border-start border-2 {{ match ($sub->status) {'completed' => 'border-success','in_progress' => 'border-primary',default => 'border-secondary'} }}"
                                                    @if ($isSubActionable) data-actionable="true" @endif>
                                                    <div class="card-body py-2 px-3">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <strong
                                                                class="shf-text-sm">{{ $sub->stage?->stage_name_en }}</strong>
                                                            <span
                                                                class="shf-badge shf-badge-{{ match (\App\Models\StageAssignment::STATUS_LABELS[$sub->status]['color']) {'success' => 'green','primary' => 'blue',default => 'gray'} }} shf-text-2xs">
                                                                {{ \App\Models\StageAssignment::STATUS_LABELS[$sub->status]['label'] }}
                                                            </span>
                                                        </div>
                                                        @if ($sub->assignee)
                                                            <div class="d-flex align-items-center gap-1">
                                                                <small
                                                                    class="text-muted">{{ $sub->assignee->name }}</small>
                                                                @if (config('app.show_stage_impersonate') &&
                                                                        auth()->user()->isSuperAdmin() &&
                                                                        $sub->assigned_to &&
                                                                        $sub->assigned_to !== auth()->id())
                                                                    <a href="/impersonate/take/{{ $sub->assigned_to }}"
                                                                        class="shf-badge shf-badge-orange shf-text-2xs flex-shrink-0"
                                                                        title="Impersonate {{ $sub->assignee->name }}"
                                                                        onclick="event.preventDefault(); Swal.fire({ title: 'Impersonate?', html: 'Switch to <strong>{{ $sub->assignee->name }}</strong>', icon: 'question', showCancelButton: true, confirmButtonColor: '#f15a29', confirmButtonText: 'Yes' }).then(function(r) { if(r.isConfirmed) window.location.href='/impersonate/take/{{ $sub->assigned_to }}'; });">
                                                                        <svg class="shf-icon-2xs" fill="none"
                                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round" stroke-width="2"
                                                                                d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                        </svg>
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        @endif

                                                        {{-- Completed: show saved data with Edit toggle --}}
                                                        @if ($sub->status === 'completed')
                                                            @php $notesData = $sub->getNotesData(); @endphp
                                                            <div class="mt-2 border-top pt-2 shf-stage-saved-data"
                                                                id="saved-{{ $sub->stage_key }}">
                                                                @switch($sub->stage_key)
                                                                    @case('app_number')
                                                                        <div class="small"><span class="text-muted">Application
                                                                                No:</span>
                                                                            <strong>{{ $notesData['application_number'] ?? '—' }}</strong>
                                                                        </div>
                                                                        @if (!empty($notesData['docket_days_offset']))
                                                                            <div class="small"><span class="text-muted">Docket
                                                                                    Timeline:</span>
                                                                                <strong>S+{{ $notesData['docket_days_offset'] }}</strong>
                                                                            </div>
                                                                        @elseif(!empty($notesData['custom_docket_date']))
                                                                            <div class="small"><span class="text-muted">Expected
                                                                                    Docket:</span>
                                                                                <strong>{{ $notesData['custom_docket_date'] }}</strong>
                                                                            </div>
                                                                        @endif
                                                                        @if (!empty($notesData['stageRemarks']))
                                                                            <div class="small text-muted mt-1">
                                                                                {{ $notesData['stageRemarks'] }}</div>
                                                                        @endif
                                                                    @break

                                                                    @case('bsm_osv')
                                                                        <div class="small text-success">BSM/OSV verification
                                                                            completed</div>
                                                                    @break

                                                                    @case('legal_verification')
                                                                        @if (!empty($notesData['confirmed_legal_advisor'] ?? ($notesData['suggested_legal_advisor'] ?? null)))
                                                                            <div class="small"><span class="text-muted">Legal
                                                                                    Advisor:</span>
                                                                                <strong>{{ $notesData['confirmed_legal_advisor'] ?? $notesData['suggested_legal_advisor'] }}</strong>
                                                                            </div>
                                                                        @endif
                                                                        <div class="small text-success">Legal verification
                                                                            completed</div>
                                                                    @break

                                                                    @case('technical_valuation')
                                                                    @case('property_valuation')
                                                                        @php $val = $loan->valuationDetails->where('valuation_type', 'property')->first(); @endphp
                                                                        @if ($val)
                                                                            @if ($val->final_valuation)
                                                                                <div class="small"><span
                                                                                        class="text-muted">Valuation:</span>
                                                                                    <strong>₹
                                                                                        {{ number_format($val->final_valuation) }}</strong>
                                                                                </div>
                                                                            @endif
                                                                            @if ($val->property_type)
                                                                                <div class="small"><span
                                                                                        class="text-muted">Type:</span>
                                                                                    {{ \App\Models\ValuationDetail::PROPERTY_TYPES[$val->property_type] ?? $val->property_type }}
                                                                                </div>
                                                                            @endif
                                                                            @if ($val->valuator_name)
                                                                                <div class="small"><span
                                                                                        class="text-muted">Valuator:</span>
                                                                                    {{ $val->valuator_name }}</div>
                                                                            @endif
                                                                        @else
                                                                            <div class="small text-muted">No valuation details
                                                                            </div>
                                                                        @endif
                                                                    @break
                                                                @endswitch
                                                                @if ($isSubAssignee && $loan->canEditStage($sub->stage_key))
                                                                    <button type="button"
                                                                        class="btn-accent-sm mt-1 shf-edit-saved shf-text-2xs shf-btn-gray"
                                                                        data-target="#edit-{{ $sub->stage_key }}">
                                                                        <svg class="shf-icon-xs" fill="none"
                                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round"
                                                                                stroke-linejoin="round" stroke-width="2"
                                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                        </svg> Edit
                                                                    </button>
                                                                @endif
                                                            </div>
                                                            {{-- Hidden edit form for completed sub-stages --}}
                                                            <div id="edit-{{ $sub->stage_key }}"
                                                                class="shf-collapse-hidden">
                                                                @switch($sub->stage_key)
                                                                    @case('app_number')
                                                                        @include(
                                                                            'newtheme.loans.partials.stage-notes-form',
                                                                            [
                                                                                'assignment' => $sub,
                                                                                'loan' => $loan,
                                                                                'fields' => [
                                                                                    [
                                                                                        'name' =>
                                                                                            'application_number',
                                                                                        'label' =>
                                                                                            'Application Number',
                                                                                        'required' => true,
                                                                                        'placeholder' =>
                                                                                            'e.g. HL20250113001',
                                                                                    ],
                                                                                    [
                                                                                        'name' =>
                                                                                            'docket_days_offset',
                                                                                        'label' =>
                                                                                            'Docket Timeline',
                                                                                        'type' => 'select',
                                                                                        'required' => true,
                                                                                        'options' => [
                                                                                            '' => 'Select...',
                                                                                            '1' =>
                                                                                                'S+1 (1 day after sanction)',
                                                                                            '2' =>
                                                                                                'S+2 (2 days after sanction)',
                                                                                            '3' =>
                                                                                                'S+3 (3 days after sanction)',
                                                                                            '0' => 'Custom Date',
                                                                                        ],
                                                                                    ],
                                                                                    [
                                                                                        'name' =>
                                                                                            'custom_docket_date',
                                                                                        'label' =>
                                                                                            'Custom Docket Date',
                                                                                        'type' => 'date',
                                                                                        'allow_future' => true,
                                                                                    ],
                                                                                    [
                                                                                        'name' => 'stageRemarks',
                                                                                        'label' => 'Remarks',
                                                                                        'type' => 'textarea',
                                                                                        'col' => 12,
                                                                                    ],
                                                                                ],
                                                                            ]
                                                                        )
                                                                    @break

                                                                    @case('bsm_osv')
                                                                    @break

                                                                    @case('legal_verification')
                                                                    @break

                                                                    @case('technical_valuation')
                                                                    @case('property_valuation')
                                                                        <div class="mt-2 border-top pt-2 d-flex gap-2 flex-wrap">
                                                                            {{-- <a href="{{ route('loans.valuation', $loan) }}"
                                                                                class="btn-accent-sm">
                                                                                <svg class="shf-icon-2xs" fill="none"
                                                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round" stroke-width="2"
                                                                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                                                </svg>
                                                                                Edit Valuation
                                                                            </a> --}}
                                                                            <a href="{{ route('loans.valuation.map', $loan) }}"
                                                                                class="btn-accent-outline btn-accent-sm">
                                                                                <svg class="shf-icon-2xs" fill="none"
                                                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round" stroke-width="2"
                                                                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round" stroke-width="2"
                                                                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                                </svg>
                                                                                Edit Valuation
                                                                            </a>
                                                                        </div>
                                                                    @break
                                                                @endswitch
                                                            </div>
                                                        @elseif($sub->status === 'in_progress' && $isSubAssignee)
                                                            {{-- In-progress: show editable form (only for assignee) --}}
                                                            @switch($sub->stage_key)
                                                                @case('app_number')
                                                                    @include(
                                                                        'newtheme.loans.partials.stage-notes-form',
                                                                        [
                                                                            'assignment' => $sub,
                                                                            'loan' => $loan,
                                                                            'fields' => [
                                                                                [
                                                                                    'name' => 'application_number',
                                                                                    'label' =>
                                                                                        'Application Number',
                                                                                    'required' => true,
                                                                                    'placeholder' =>
                                                                                        'e.g. HL20250113001',
                                                                                ],
                                                                                [
                                                                                    'name' => 'docket_days_offset',
                                                                                    'label' => 'Docket Timeline',
                                                                                    'type' => 'select',
                                                                                    'required' => true,
                                                                                    'options' => [
                                                                                        '' => 'Select...',
                                                                                        '1' =>
                                                                                            'S+1 (1 day after sanction)',
                                                                                        '2' =>
                                                                                            'S+2 (2 days after sanction)',
                                                                                        '3' =>
                                                                                            'S+3 (3 days after sanction)',
                                                                                        '0' => 'Custom Date',
                                                                                    ],
                                                                                ],
                                                                                [
                                                                                    'name' => 'custom_docket_date',
                                                                                    'label' =>
                                                                                        'Custom Docket Date',
                                                                                    'type' => 'date',
                                                                                    'min_date' => empty($assignment->getNotesData()['custom_docket_date']) ? now()->addDays(3)->format('d/m/Y') : $assignment->created_at->format('d/m/Y'),
                                                                                ],
                                                                                [
                                                                                    'name' => 'stageRemarks',
                                                                                    'label' => 'Remarks',
                                                                                    'type' => 'textarea',
                                                                                    'col' => 12,
                                                                                ],
                                                                            ],
                                                                        ]
                                                                    )
                                                                @break

                                                                @case('bsm_osv')
                                                                    <div class="mt-2 border-top pt-2">
                                                                        <small class="text-muted d-block mb-2">Assigned to bank
                                                                            employee for BSM/OSV verification. Mark complete when
                                                                            done.</small>
                                                                        <button class="btn-accent-sm shf-stage-action"
                                                                            data-loan-id="{{ $loan->id }}"
                                                                            data-stage="bsm_osv" data-action="completed">
                                                                            <svg class="shf-icon-2xs" fill="none"
                                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round" stroke-width="2"
                                                                                    d="M5 13l4 4L19 7" />
                                                                            </svg>
                                                                            Complete
                                                                        </button>
                                                                    </div>
                                                                @break

                                                                @case('legal_verification')
                                                                    @php
                                                                        $legalNotes = $sub->getNotesData();
                                                                        $legalPhase = $legalNotes['legal_phase'] ?? '1';
                                                                    @endphp

                                                                    @if ($legalPhase === '1')
                                                                        {{-- Phase 1: Task owner sends for legal --}}
                                                                        @php $legalP2Role = ($wfConfig['legal_verification']['phases']['1']['role'] ?? 'bank_employee'); @endphp
                                                                        <div class="mt-2 border-top pt-2">
                                                                            <small class="text-muted d-block mb-2">Enter suggested
                                                                                legal advisor name and send to {{ str_replace('_', ' ', $legalP2Role) }}.</small>
                                                                            <input type="text"
                                                                                class="shf-input shf-input-sm mb-2"
                                                                                id="legalAdvisorName"
                                                                                placeholder="Suggested Legal Advisor name (required)"
                                                                                value="{{ $legalNotes['suggested_legal_advisor'] ?? '' }}">
                                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                                <select
                                                                                    class="shf-input shf-input-sm shf-transfer-user"
                                                                                    data-stage="legal_verification"
                                                                                    data-role="{{ $legalP2Role }}"
                                                                                    data-loan-id="{{ $loan->id }}"
                                                                                    style="max-width:220px">
                                                                                    <option value="">Select {{ ucwords(str_replace('_', ' ', $legalP2Role)) }}...
                                                                                    </option>
                                                                                </select>
                                                                                <button class="btn-accent-sm shf-legal-action"
                                                                                    data-loan-id="{{ $loan->id }}"
                                                                                    data-action="send_to_bank">
                                                                                    Send to {{ ucwords(str_replace('_', ' ', $legalP2Role)) }}
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    @elseif($legalPhase === '2')
                                                                        {{-- Phase 2: Bank employee confirms legal advisor and initiates --}}
                                                                        <div class="mt-2 border-top pt-2">
                                                                            <div class="alert alert-info py-2 mb-2 shf-text-sm">
                                                                                <strong>Legal verification requested.</strong>
                                                                                Confirm or change the legal advisor and click
                                                                                Initiate.
                                                                            </div>
                                                                            <label class="form-label small">Legal Advisor Name
                                                                                <span class="text-danger">*</span></label>
                                                                            <input type="text"
                                                                                class="shf-input shf-input-sm mb-2"
                                                                                id="legalAdvisorName"
                                                                                placeholder="Legal Advisor name (required)"
                                                                                value="{{ $legalNotes['suggested_legal_advisor'] ?? '' }}">
                                                                            <button
                                                                                class="btn-accent-sm shf-legal-action shf-btn-success"
                                                                                data-loan-id="{{ $loan->id }}"
                                                                                data-action="initiate_legal">
                                                                                Initiate Legal Verification
                                                                            </button>
                                                                        </div>
                                                                    @elseif($legalPhase === '3')
                                                                        {{-- Phase 3: Back to task owner — can reassign or complete --}}
                                                                        <div class="mt-2 border-top pt-2">
                                                                            <small class="text-muted d-block mb-1">Legal
                                                                                verification initiated. Legal Advisor:
                                                                                <strong>{{ $legalNotes['confirmed_legal_advisor'] ?? ($legalNotes['suggested_legal_advisor'] ?? '—') }}</strong></small>
                                                                            <small class="text-muted d-block mb-2">You can reassign
                                                                                to another eligible user or mark as
                                                                                complete.</small>
                                                                            <button class="btn-accent-sm shf-stage-action"
                                                                                data-loan-id="{{ $loan->id }}"
                                                                                data-stage="legal_verification"
                                                                                data-action="completed">
                                                                                <svg class="shf-icon-2xs" fill="none"
                                                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round" stroke-width="2"
                                                                                        d="M5 13l4 4L19 7" />
                                                                                </svg>
                                                                                Complete
                                                                            </button>
                                                                        </div>
                                                                    @endif
                                                                @break

                                                                @case('technical_valuation')
                                                                    @php
                                                                        $tvNotes = $sub->getNotesData();
                                                                        $tvPhase = $tvNotes['tv_phase'] ?? '1';
                                                                        $tvVal = $loan->valuationDetails
                                                                            ->where('valuation_type', 'property')
                                                                            ->first();
                                                                    @endphp
                                                                    @if ($tvPhase === '1')
                                                                        {{-- Phase 1: Task owner sends for valuation --}}
                                                                        @php $tvP2Role = ($wfConfig['technical_valuation']['phases']['1']['role'] ?? 'office_employee'); @endphp
                                                                        <div class="mt-2 border-top pt-2">
                                                                            <small class="text-muted d-block mb-2">Send for technical valuation.</small>
                                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                                <select
                                                                                    class="shf-input shf-input-sm shf-transfer-user"
                                                                                    data-stage="technical_valuation"
                                                                                    data-role="{{ $tvP2Role }}"
                                                                                    data-loan-id="{{ $loan->id }}"
                                                                                    style="max-width:220px">
                                                                                    <option value="">Select {{ ucwords(str_replace('_', ' ', $tvP2Role)) }}...</option>
                                                                                </select>
                                                                                <button class="btn-accent-sm shf-tv-action"
                                                                                    data-loan-id="{{ $loan->id }}"
                                                                                    data-action="send_to_office">
                                                                                    Send for Technical Valuation
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                    @else
                                                                        {{-- Phase 2: Office employee fills valuation form --}}
                                                                        @if ($tvVal)
                                                                            <div class="mt-2 border-top pt-2">
                                                                                @if ($tvVal->final_valuation)
                                                                                    <div class="small"><span
                                                                                            class="text-muted">Valuation:</span>
                                                                                        <strong>₹
                                                                                            {{ number_format($tvVal->final_valuation) }}</strong>
                                                                                    </div>
                                                                                @endif
                                                                                @if ($tvVal->property_type)
                                                                                    <div class="small"><span
                                                                                            class="text-muted">Type:</span>
                                                                                        {{ \App\Models\ValuationDetail::PROPERTY_TYPES[$tvVal->property_type] ?? $tvVal->property_type }}
                                                                                    </div>
                                                                                @endif
                                                                                @if ($tvVal->valuator_name)
                                                                                    <div class="small"><span
                                                                                            class="text-muted">Valuator:</span>
                                                                                        {{ $tvVal->valuator_name }}</div>
                                                                                @endif
                                                                            </div>
                                                                        @endif
                                                                        <div class="mt-2 d-flex gap-2 flex-wrap">
                                                                            {{-- <a href="{{ route('loans.valuation', $loan) }}"
                                                                                class="btn-accent-sm shf-primary-action">
                                                                                <svg class="shf-icon-2xs" fill="none"
                                                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round" stroke-width="2"
                                                                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5" />
                                                                                </svg>
                                                                                {{ $tvVal ? 'Edit Valuation' : 'Fill Valuation Form' }}
                                                                            </a> --}}
                                                                            <a href="{{ route('loans.valuation.map', $loan) }}"
                                                                                class="btn-accent-outline btn-accent-sm shf-primary-action">
                                                                                <svg class="shf-icon-2xs" fill="none"
                                                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round" stroke-width="2"
                                                                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round" stroke-width="2"
                                                                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                                </svg>
                                                                                {{ $tvVal ? 'Edit Valuation' : 'Fill Valuation Form' }}
                                                                            </a>
                                                                        </div>
                                                                    @endif
                                                                @break

                                                                @case('property_valuation')
                                                                    @php $pvVal = $loan->valuationDetails->where('valuation_type', 'property')->first(); @endphp
                                                                    @if ($pvVal)
                                                                        <div class="mt-2 border-top pt-2">
                                                                            @if ($pvVal->final_valuation)
                                                                                <div class="small"><span
                                                                                        class="text-muted">Valuation:</span>
                                                                                    <strong>₹
                                                                                        {{ number_format($pvVal->final_valuation) }}</strong>
                                                                                </div>
                                                                            @endif
                                                                            @if ($pvVal->property_type)
                                                                                <div class="small"><span
                                                                                        class="text-muted">Type:</span>
                                                                                    {{ \App\Models\ValuationDetail::PROPERTY_TYPES[$pvVal->property_type] ?? $pvVal->property_type }}
                                                                                </div>
                                                                            @endif
                                                                            @if ($pvVal->valuator_name)
                                                                                <div class="small"><span
                                                                                        class="text-muted">Valuator:</span>
                                                                                    {{ $pvVal->valuator_name }}</div>
                                                                            @endif
                                                                        </div>
                                                                    @endif
                                                                    <div class="mt-2 d-flex gap-2 flex-wrap">
                                                                        {{-- <a href="{{ route('loans.valuation', $loan) }}"
                                                                            class="btn-accent-sm shf-primary-action">
                                                                            <svg class="shf-icon-2xs" fill="none"
                                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round" stroke-width="2"
                                                                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5" />
                                                                            </svg>
                                                                            {{ $pvVal ? 'Edit Valuation' : 'Fill Valuation Form' }}
                                                                        </a> --}}
                                                                        <a href="{{ route('loans.valuation.map', $loan) }}"
                                                                            class="btn-accent-outline btn-accent-sm shf-primary-action">
                                                                            <svg class="shf-icon-2xs" fill="none"
                                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round" stroke-width="2"
                                                                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                                                <path stroke-linecap="round"
                                                                                    stroke-linejoin="round" stroke-width="2"
                                                                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                                                            </svg>
                                                                            {{ $pvVal ? 'Edit Valuation' : 'Fill Valuation Form' }}
                                                                        </a>
                                                                    </div>
                                                                @break

                                                                @case('sanction_decision')
                                                                    @php
                                                                        $sdNotes = $sub->getNotesData();
                                                                        $sdEscHistory =
                                                                            $sdNotes['escalation_history'] ?? [];
                                                                        $sdUser = auth()->user();
                                                                        $sdIsOE = $sdUser->hasRole('office_employee');
                                                                        $sdIsBM = $sdUser->hasRole('branch_manager');
                                                                        $sdIsBDH = $sdUser->hasRole('bdh');
                                                                        $sdIsAdm = $sdUser->hasAnyRole([
                                                                            'super_admin',
                                                                            'admin',
                                                                        ]);
                                                                    @endphp
                                                                    <div class="mt-2 border-top pt-2">
                                                                        @if (!empty($sdEscHistory))
                                                                            <small
                                                                                class="fw-semibold text-muted d-block mb-2">Escalation
                                                                                History:</small>
                                                                            @foreach ($sdEscHistory as $esc)
                                                                                <div
                                                                                    class="shf-text-xs border-start border-2 border-warning ps-2 mb-1">
                                                                                    <strong>{{ $esc['from_user_name'] ?? 'Unknown' }}</strong>
                                                                                    →
                                                                                    {{ ucfirst(str_replace('_', ' ', $esc['to_role'])) }}
                                                                                    <small
                                                                                        class="text-muted ms-1">{{ $esc['date'] ?? '' }}</small>
                                                                                    @if (!empty($esc['remarks']))
                                                                                        <br><em
                                                                                            class="text-muted">{{ $esc['remarks'] }}</em>
                                                                                    @endif
                                                                                </div>
                                                                            @endforeach
                                                                        @endif

                                                                        <div class="mb-2">
                                                                            <label
                                                                                class="shf-form-label shf-text-xs">Remarks</label>
                                                                            <textarea class="shf-input shf-input-sm shf-sd-remarks" rows="2"
                                                                                placeholder="Required for escalation or rejection..."></textarea>
                                                                        </div>

                                                                        <div class="d-flex flex-wrap gap-2">
                                                                            {{-- Approve — all roles --}}
                                                                            <button
                                                                                class="btn-accent-sm shf-btn-success shf-sd-action"
                                                                                data-loan-id="{{ $loan->id }}"
                                                                                data-action="approve">
                                                                                <svg class="shf-icon-2xs" fill="none"
                                                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                                                    <path stroke-linecap="round"
                                                                                        stroke-linejoin="round" stroke-width="2"
                                                                                        d="M5 13l4 4L19 7" />
                                                                                </svg> Approve
                                                                            </button>

                                                                            {{-- Escalate to BM — Office Employee --}}
                                                                            @if ($sdIsOE || $sdIsAdm)
                                                                                <button
                                                                                    class="btn-accent-sm shf-btn-warning shf-sd-action"
                                                                                    data-loan-id="{{ $loan->id }}"
                                                                                    data-action="escalate_to_bm">
                                                                                    Escalate to BM
                                                                                </button>
                                                                            @endif

                                                                            {{-- Escalate to BDH — Branch Manager --}}
                                                                            @if ($sdIsBM || $sdIsAdm)
                                                                                <button
                                                                                    class="btn-accent-sm shf-btn-warning shf-sd-action"
                                                                                    data-loan-id="{{ $loan->id }}"
                                                                                    data-action="escalate_to_bdh">
                                                                                    Escalate to BDH
                                                                                </button>
                                                                            @endif

                                                                            {{-- Reject — BM / BDH / Admin --}}
                                                                            @if ($sdIsBM || $sdIsBDH || $sdIsAdm)
                                                                                <button
                                                                                    class="btn-accent-sm shf-btn-danger-alt shf-sd-action"
                                                                                    data-loan-id="{{ $loan->id }}"
                                                                                    data-action="reject">
                                                                                    Reject
                                                                                </button>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @break
                                                            @endswitch
                                                        @endif

                                                        @if ($sub->status === 'pending')
                                                            <div class="mt-2 border-top pt-2">
                                                                <small class="text-muted">
                                                                    <svg class="shf-icon-xs me-1" fill="none"
                                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                                    </svg>
                                                                    Waiting for previous stage to complete
                                                                </small>
                                                            </div>
                                                        @endif

                                                        {{-- Active Queries Banner --}}
                                                        @php
                                                            $subActiveQueries = \App\Models\StageQuery::where(
                                                                'loan_id',
                                                                $loan->id,
                                                            )
                                                                ->where('stage_key', $sub->stage_key)
                                                                ->whereIn('status', ['pending', 'responded'])
                                                                ->with(['raisedByUser', 'responses.respondedByUser'])
                                                                ->get();
                                                        @endphp
                                                        @if ($subActiveQueries->isNotEmpty())
                                                            <div class="alert alert-warning py-2 mt-2 mb-1 shf-text-sm">
                                                                <strong>Active Queries
                                                                    ({{ $subActiveQueries->count() }})
                                                                </strong> — Stage
                                                                cannot be completed until resolved.
                                                                @foreach ($subActiveQueries as $q)
                                                                    <div class="border-top mt-2 pt-2">
                                                                        <div
                                                                            class="d-flex justify-content-between align-items-center">
                                                                            <strong>{{ $q->raisedByUser->name }}</strong>
                                                                            <span
                                                                                class="shf-badge shf-badge-{{ $q->status === 'pending' ? 'orange' : 'blue' }} shf-text-2xs">{{ ucfirst($q->status) }}</span>
                                                                        </div>
                                                                        <p class="mb-1">{{ $q->query_text }}</p>
                                                                        @foreach ($q->responses as $resp)
                                                                            <div
                                                                                class="ps-3 border-start border-2 border-info mb-1">
                                                                                <small><strong>{{ $resp->respondedByUser->name }}</strong>
                                                                                    ·
                                                                                    {{ $resp->created_at->diffForHumans() }}</small>
                                                                                <p class="mb-0 small">
                                                                                    {{ $resp->response_text }}</p>
                                                                            </div>
                                                                        @endforeach
                                                                        {{-- Response form (any user can respond to a pending query) --}}
                                                                        @if ($q->status === 'pending')
                                                                            <form class="shf-query-respond mt-1"
                                                                                data-url="{{ route('loans.queries.respond', $q) }}">
                                                                                <div class="input-group input-group-sm">
                                                                                    <input type="text"
                                                                                        name="response_text"
                                                                                        class="shf-input shf-text-sm"
                                                                                        placeholder="Type response..."
                                                                                        required>
                                                                                    <button type="submit"
                                                                                        class="btn-accent-sm"><svg
                                                                                            class="shf-icon-2xs"
                                                                                            fill="none"
                                                                                            stroke="currentColor"
                                                                                            viewBox="0 0 24 24">
                                                                                            <path stroke-linecap="round"
                                                                                                stroke-linejoin="round"
                                                                                                stroke-width="2"
                                                                                                d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                                                                        </svg> Respond</button>
                                                                                </div>
                                                                            </form>
                                                                        @endif
                                                                        {{-- Resolve button (only for user who raised the query, and query is responded) --}}
                                                                        @if ($q->status === 'responded' && $q->raised_by === auth()->id())
                                                                            <button
                                                                                class="btn-accent-sm mt-1 shf-query-resolve shf-text-2xs shf-btn-success"
                                                                                data-url="{{ route('loans.queries.resolve', $q) }}">
                                                                                Resolve Query
                                                                            </button>
                                                                        @endif
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @endif

                                                        {{-- Resolved Queries Summary --}}
                                                        @php
                                                            $subResolvedQueries = \App\Models\StageQuery::where(
                                                                'loan_id',
                                                                $loan->id,
                                                            )
                                                                ->where('stage_key', $sub->stage_key)
                                                                ->where('status', 'resolved')
                                                                ->with(['raisedByUser', 'responses.respondedByUser'])
                                                                ->get();
                                                        @endphp
                                                        @if ($subResolvedQueries->isNotEmpty())
                                                            <div class="mt-1">
                                                                <a class="shf-text-xs text-muted text-decoration-none shf-clickable"
                                                                    data-bs-toggle="collapse"
                                                                    href="#resolved-sub-{{ $sub->stage_key }}">
                                                                    <svg class="shf-icon-2xs shf-icon-inline"
                                                                        fill="none" stroke="currentColor"
                                                                        viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M9 12l2 2 4-4" />
                                                                    </svg>
                                                                    {{ $subResolvedQueries->count() }} query/queries
                                                                    resolved <small>&#9656;</small>
                                                                </a>
                                                                <div class="collapse"
                                                                    id="resolved-sub-{{ $sub->stage_key }}">
                                                                    @foreach ($subResolvedQueries as $rq)
                                                                        <div
                                                                            class="ps-3 border-start border-2 border-success mt-1 shf-text-xs">
                                                                            <strong>{{ $rq->raisedByUser->name }}</strong>:
                                                                            {{ $rq->query_text }}
                                                                            @foreach ($rq->responses as $resp)
                                                                                <div class="text-muted">→
                                                                                    {{ $resp->respondedByUser->name }}:
                                                                                    {{ $resp->response_text }}</div>
                                                                            @endforeach
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif

                                                        @if ($isSubAssignee && auth()->user()->hasPermission('manage_loan_stages'))
                                                            <div class="mt-1 d-flex gap-1 flex-wrap align-items-center">
                                                                @if ($sub->status === 'in_progress')
                                                                    <button
                                                                        class="btn-accent-sm shf-raise-query-btn shf-text-2xs shf-btn-warning"
                                                                        data-loan-id="{{ $loan->id }}"
                                                                        data-stage="{{ $subKey }}">
                                                                        <span class="fw-bold">?</span> Query
                                                                    </button>
                                                                @endif
                                                                {{-- Transfer: single grouped dropdown + button (in_progress only, skip sanction_decision) --}}
                                                                @if (
                                                                    $sub->status === 'in_progress' &&
                                                                        $sub->assigned_to &&
                                                                        !in_array($subKey, ['sanction_decision', 'legal_verification']) &&
                                                                        auth()->user()->hasPermission('transfer_loan_stages') &&
                                                                        !auth()->user()->hasAnyRole(['bank_employee', 'office_employee']))
                                                                    @php $transferUsers = $subUsers->where('id', '!=', $sub->assigned_to); @endphp
                                                                    @if ($transferUsers->isNotEmpty())
                                                                        <select
                                                                            class="shf-input shf-input-sm shf-text-2xs shf-stage-transfer-select"
                                                                            data-loan-id="{{ $loan->id }}"
                                                                            data-stage="{{ $subKey }}"
                                                                            style="max-width:170px;">
                                                                            <option value="">Transfer to...</option>
                                                                            @php $groupedTransferUsers = $transferUsers->groupBy(fn($u) => $u->roles->first()?->name ?? 'Other'); @endphp
                                                                            @foreach ($groupedTransferUsers as $roleName => $users)
                                                                                <optgroup label="{{ $roleName }}">
                                                                                    @foreach ($users as $tru)
                                                                                        <option
                                                                                            value="{{ $tru->id }}">
                                                                                            {{ $tru->name }}</option>
                                                                                    @endforeach
                                                                                </optgroup>
                                                                            @endforeach
                                                                        </select>
                                                                        <button
                                                                            class="btn-accent-sm shf-text-2xs shf-btn-gray shf-stage-transfer-btn"
                                                                            data-loan-id="{{ $loan->id }}"
                                                                            data-stage="{{ $subKey }}">Transfer</button>
                                                                    @endif
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- OTC skip reason (shown regardless of editable state) --}}
                                @if ($assignment->stage_key === 'otc_clearance' && $assignment->status === 'skipped' && $loan->disbursement?->disbursement_type === 'fund_transfer')
                                    <div class="mt-2 text-muted shf-text-sm">
                                        <svg class="shf-icon-sm shf-icon-inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Fund Transfer — OTC not required / ફંડ ટ્રાન્સફર — OTC જરૂરી નથી
                                    </div>
                                @endif

                                {{-- Stage-specific forms and links (only for assignee or admin) --}}
                                @php $stageEditable = in_array($assignment->status, ['in_progress', 'completed']) && $isMainAssignee; @endphp
                                @if ($stageEditable)
                                    @switch($assignment->stage_key)
                                        @case('inquiry')
                                            @if ($assignment->status === 'in_progress' || ($assignment->status === 'completed' && !$loan->isBasicEditLocked()))
                                                <div class="mt-2 border-top pt-2">
                                                    <small class="text-muted">Customer and loan details were captured during loan
                                                        creation.</small>
                                                    <a href="{{ route('loans.edit', $loan) }}" class="btn-accent-sm ms-2">
                                                        <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        Edit Loan Details
                                                    </a>
                                                </div>
                                            @endif
                                        @break

                                        @case('document_selection')
                                            @if (
                                                $assignment->status === 'in_progress' ||
                                                    ($assignment->status === 'completed' && $loan->canEditStage('document_selection')))
                                                <div class="mt-2 border-top pt-2">
                                                    <small class="text-muted">Select and manage required documents for this
                                                        loan.</small>
                                                    <a href="{{ route('loans.documents', $loan) }}" class="btn-accent-sm ms-2">
                                                        <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                        Manage Documents
                                                    </a>
                                                </div>
                                            @endif
                                        @break

                                        @case('document_collection')
                                            @if (
                                                $assignment->status === 'in_progress' ||
                                                    ($assignment->status === 'completed' && $loan->canEditStage('document_collection')))
                                                @php $docProgress = app(\App\Services\LoanDocumentService::class)->getProgress($loan); @endphp
                                                <div class="mt-2 border-top pt-2">
                                                    <div class="d-flex align-items-center gap-3 mb-2">
                                                        <div class="flex-grow-1">
                                                            <div class="progress shf-progress-sm">
                                                                <div class="progress-bar bg-success"
                                                                    style="width: {{ $docProgress['percentage'] }}%"></div>
                                                            </div>
                                                        </div>
                                                        <small
                                                            class="text-muted text-nowrap">{{ $docProgress['resolved'] }}/{{ $docProgress['total'] }}
                                                            ({{ $docProgress['percentage'] }}%)</small>
                                                    </div>
                                                    <a href="{{ route('loans.documents', $loan) }}" class="btn-accent-sm">
                                                        <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                                        </svg>
                                                        Collect Documents
                                                    </a>
                                                    @if ($docProgress['percentage'] < 100)
                                                        <small class="text-warning ms-2">All required documents must be collected
                                                            before completing this stage.</small>
                                                    @endif
                                                </div>
                                            @endif
                                        @break

                                        @case('rate_pf')
                                            @php
                                                $ratePfNotes = $assignment->getNotesData();
                                                $ratePfPhase = $ratePfNotes['rate_pf_phase'] ?? '1';
                                                $ratePfCompleted = $assignment->status === 'completed';
                                            @endphp

                                            @if (!$loan->is_sanctioned && $assignment->status === 'pending')
                                                <div class="alert alert-warning py-2 mt-2 shf-text-sm">
                                                    <strong>Waiting for Loan Sanction Decision.</strong> This stage will become
                                                    available once the loan is sanctioned.
                                                </div>
                                            @endif

                                            {{-- Completed: show saved data with Edit toggle --}}
                                            @if ($ratePfCompleted)
                                                <div class="mt-2 border-top pt-2 shf-stage-saved-data" id="saved-rate_pf">
                                                    <div class="row g-2">
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">Interest Rate:</span>
                                                                <strong>{{ $ratePfNotes['interest_rate'] ?? '—' }}%</strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">Rate Offered Date:</span>
                                                                <strong>{{ $ratePfNotes['rate_offered_date'] ?? '—' }}</strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">Valid Until:</span>
                                                                <strong>{{ $ratePfNotes['rate_valid_until'] ?? '—' }}</strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">Bank Reference:</span>
                                                                <strong>{{ $ratePfNotes['bank_reference'] ?? '—' }}</strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">Repo Rate:</span>
                                                                <strong>{{ $ratePfNotes['repo_rate'] ?? '—' }}%</strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">Bank Margin:</span>
                                                                <strong>{{ $ratePfNotes['bank_rate'] ?? '—' }}%</strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">Processing Fee:</span>
                                                                <strong>{{ $ratePfNotes['processing_fee'] ?? '0' }}{{ ($ratePfNotes['processing_fee_type'] ?? 'percent') === 'percent' ? '%' : '' }}</strong>
                                                                <small
                                                                    class="text-muted">({{ ($ratePfNotes['processing_fee_type'] ?? 'percent') === 'percent' ? '% of loan' : 'Fixed' }})</small>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">PF Amount:</span>
                                                                <strong>₹
                                                                    {{ number_format((float) ($ratePfNotes['processing_fee_amount'] ?? 0)) }}</strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">GST
                                                                    ({{ $ratePfNotes['gst_percent'] ?? '18' }}%):</span> <strong>₹
                                                                    {{ number_format((float) ($ratePfNotes['pf_gst_amount'] ?? 0)) }}</strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">Total PF:</span>
                                                                <strong>₹
                                                                    {{ number_format((float) ($ratePfNotes['total_pf'] ?? 0)) }}</strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">Admin Charges:</span>
                                                                <strong>₹
                                                                    {{ number_format((float) ($ratePfNotes['admin_charges'] ?? 0)) }}</strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">Admin GST
                                                                    ({{ $ratePfNotes['admin_charges_gst_percent'] ?? '18' }}%):</span>
                                                                <strong>₹
                                                                    {{ number_format((float) ($ratePfNotes['admin_charges_gst_amount'] ?? 0)) }}</strong>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">Total Admin:</span>
                                                                <strong>₹
                                                                    {{ number_format((float) ($ratePfNotes['total_admin_charges'] ?? 0)) }}</strong>
                                                            </div>
                                                        </div>
                                                        @if (!empty($ratePfNotes['special_conditions']))
                                                            <div class="col-12">
                                                                <div class="small"><span class="text-muted">Special
                                                                        Conditions:</span>
                                                                    {{ $ratePfNotes['special_conditions'] }}</div>
                                                            </div>
                                                        @endif
                                                        @if (!empty($ratePfNotes['stageRemarks']))
                                                            <div class="col-12">
                                                                <div class="small text-muted">{{ $ratePfNotes['stageRemarks'] }}
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    @if ($loan->canEditStage('rate_pf'))
                                                        <button type="button"
                                                            class="btn-accent-sm mt-2 shf-edit-saved shf-text-2xs shf-btn-gray"
                                                            data-target="#edit-rate_pf">
                                                            <svg class="shf-icon-xs" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg> Edit
                                                        </button>
                                                    @endif
                                                </div>
                                                <div id="edit-rate_pf" class="shf-collapse-hidden">
                                                    @include('newtheme.loans.partials.stage-notes-form', [
                                                        'fields' => [
                                                            [
                                                                'name' => 'interest_rate',
                                                                'label' => 'Interest Rate (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                            ],
                                                            [
                                                                'name' => 'rate_offered_date',
                                                                'label' => 'Rate Offered Date',
                                                                'type' => 'date',
                                                            ],
                                                            [
                                                                'name' => 'rate_valid_until',
                                                                'label' => 'Valid Until',
                                                                'type' => 'date',
                                                                'min_date' => empty($assignment->getNotesData()['rate_valid_until']) ? now()->format('d/m/Y') : $assignment->created_at->format('d/m/Y'),
                                                            ],
                                                            [
                                                                'name' => 'bank_reference',
                                                                'label' => 'Bank Reference',
                                                            ],
                                                            [
                                                                'name' => 'repo_rate',
                                                                'label' => 'Repo Rate (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                            ],
                                                            [
                                                                'name' => 'bank_rate',
                                                                'label' => 'Bank Margin (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'readonly' => true,
                                                            ],
                                                            [
                                                                'name' => 'processing_fee_type',
                                                                'label' => 'PF Type',
                                                                'type' => 'select',
                                                                'options' => [
                                                                    'percent' => 'Percentage (%)',
                                                                    'amount' => 'Fixed Amount (₹)',
                                                                ],
                                                            ],
                                                            [
                                                                'name' => 'processing_fee',
                                                                'label' => 'Processing Fee',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                            ],
                                                            [
                                                                'name' => 'processing_fee_amount',
                                                                'label' => 'PF Amount (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                            ],
                                                            [
                                                                'name' => 'gst_percent',
                                                                'label' => 'GST (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                            ],
                                                            [
                                                                'name' => 'pf_gst_amount',
                                                                'label' => 'PF GST Amount (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                            ],
                                                            [
                                                                'name' => 'total_pf',
                                                                'label' => 'Total PF (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                            ],
                                                            [
                                                                'name' => 'admin_charges',
                                                                'label' => 'Admin Charges (₹)',
                                                                'type' => 'currency',
                                                            ],
                                                            [
                                                                'name' => 'admin_charges_gst_percent',
                                                                'label' => 'Admin GST (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                            ],
                                                            [
                                                                'name' => 'admin_charges_gst_amount',
                                                                'label' => 'Admin GST Amount (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                            ],
                                                            [
                                                                'name' => 'total_admin_charges',
                                                                'label' => 'Total Admin Charges (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                            ],
                                                            [
                                                                'name' => 'special_conditions',
                                                                'label' => 'Special Conditions',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                            ],
                                                            [
                                                                'name' => 'stageRemarks',
                                                                'label' => 'Remarks',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                            ],
                                                        ],
                                                    ])
                                                </div>

                                                {{-- Phase 1: Eligible user fills ALL fields, then sends to bank --}}
                                            @elseif($ratePfPhase === '1')
                                                @php
                                                    $quotationRoi = '';
                                                    if ($loan->quotation_id && $loan->bank_name) {
                                                        $qBank = \App\Models\QuotationBank::where(
                                                            'quotation_id',
                                                            $loan->quotation_id,
                                                        )
                                                            ->where('bank_name', $loan->bank_name)
                                                            ->first();
                                                        if ($qBank) {
                                                            $quotationRoi = (string) $qBank->roi_min;
                                                        }
                                                    }
                                                @endphp
                                                <div class="mt-2 border-top pt-2">
                                                    <small class="fw-semibold text-muted d-block mb-1">Rate & Processing
                                                        Details</small>
                                                    <small class="text-muted d-block mb-2">Fill all details before sending to
                                                        bank.</small>
                                                    @include('newtheme.loans.partials.stage-notes-form', [
                                                        'hideSubmit' => true,
                                                        'fields' => [
                                                            [
                                                                'name' => 'interest_rate',
                                                                'label' => 'Interest Rate (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'default' => $quotationRoi,
                                                                'placeholder' => 'e.g. 8.5',
                                                            ],
                                                            [
                                                                'name' => 'repo_rate',
                                                                'label' => 'Repo Rate (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'placeholder' => 'e.g. 6.5',
                                                            ],
                                                            [
                                                                'name' => 'bank_rate',
                                                                'label' => 'Bank Margin (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'readonly' => true,
                                                                'placeholder' => 'e.g. 2.5',
                                                            ],
                                                            [
                                                                'name' => 'rate_offered_date',
                                                                'label' => 'Rate Offered Date',
                                                                'type' => 'date',
                                                                'required' => true,
                                                                'default' => now()->format('d/m/Y'),
                                                            ],
                                                            [
                                                                'name' => 'rate_valid_until',
                                                                'label' => 'Valid Until',
                                                                'type' => 'date',
                                                                'required' => true,
                                                                'default' => now()->addDays(15)->format('d/m/Y'),
                                                                'allow_future' => true,
                                                            ],
                                                            [
                                                                'name' => 'bank_reference',
                                                                'label' => 'Bank Reference',
                                                            ],
                                                            [
                                                                'name' => 'processing_fee_type',
                                                                'label' => 'PF Type',
                                                                'type' => 'select',
                                                                'required' => true,
                                                                'options' => [
                                                                    'percent' => 'Percentage (%)',
                                                                    'amount' => 'Fixed Amount (₹)',
                                                                ],
                                                                'default' => 'percent',
                                                            ],
                                                            [
                                                                'name' => 'processing_fee',
                                                                'label' => 'Processing Fee',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'default' =>
                                                                    isset($qBank) && $qBank
                                                                        ? (string) $qBank->pf_charge
                                                                        : '0',
                                                            ],
                                                            [
                                                                'name' => 'processing_fee_amount',
                                                                'label' => 'PF Amount (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                                'default' => '0',
                                                            ],
                                                            [
                                                                'name' => 'gst_percent',
                                                                'label' => 'GST (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'default' => '18',
                                                            ],
                                                            [
                                                                'name' => 'pf_gst_amount',
                                                                'label' => 'PF GST Amount (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                                'default' => '0',
                                                            ],
                                                            [
                                                                'name' => 'total_pf',
                                                                'label' => 'Total PF (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                                'default' => '0',
                                                            ],
                                                            [
                                                                'name' => 'admin_charges',
                                                                'label' => 'Admin Charges (₹)',
                                                                'type' => 'currency',
                                                                'required' => true,
                                                                'default' => '0',
                                                            ],
                                                            [
                                                                'name' => 'admin_charges_gst_percent',
                                                                'label' => 'Admin GST (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'default' => '18',
                                                            ],
                                                            [
                                                                'name' => 'admin_charges_gst_amount',
                                                                'label' => 'Admin GST Amount (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                                'default' => '0',
                                                            ],
                                                            [
                                                                'name' => 'total_admin_charges',
                                                                'label' => 'Total Admin Charges (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                                'default' => '0',
                                                            ],
                                                            [
                                                                'name' => 'special_conditions',
                                                                'label' => 'Special Conditions',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                            ],
                                                            [
                                                                'name' => 'stageRemarks',
                                                                'label' => 'Remarks',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                            ],
                                                        ],
                                                    ])
                                                    <div class="d-flex align-items-center gap-2 flex-wrap mt-1">
                                                        @php $ratePfP1Role = $getPhaseRole(0); @endphp
                                                        <select class="shf-input shf-input-sm shf-transfer-user"
                                                            data-stage="rate_pf" data-role="{{ $ratePfP1Role }}"
                                                            data-loan-id="{{ $loan->id }}" style="max-width:220px">
                                                            <option value="">Select {{ ucwords(str_replace('_', ' ', $ratePfP1Role)) }}...</option>
                                                        </select>
                                                        <button class="btn-accent-sm shf-rate-pf-action"
                                                            data-loan-id="{{ $loan->id }}" data-action="send_to_bank">
                                                            Send to {{ ucwords(str_replace('_', ' ', $ratePfP1Role)) }}
                                                        </button>
                                                    </div>
                                                </div>

                                                {{-- Phase 2: Bank employee sees ALL fields (editable), no hints --}}
                                            @elseif($ratePfPhase === '2')
                                                <div class="mt-2 border-top pt-2">
                                                    <div class="alert alert-info py-2 mb-2 shf-text-sm">
                                                        <strong>Rate request received.</strong> Review and update all details, then
                                                        return to task owner.
                                                    </div>
                                                    @include('newtheme.loans.partials.stage-notes-form', [
                                                        'hideSubmit' => true,
                                                        'fields' => [
                                                            [
                                                                'name' => 'interest_rate',
                                                                'label' => 'Interest Rate (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                            ],
                                                            [
                                                                'name' => 'repo_rate',
                                                                'label' => 'Repo Rate (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                            ],
                                                            [
                                                                'name' => 'bank_rate',
                                                                'label' => 'Bank Margin (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'readonly' => true,
                                                            ],
                                                            [
                                                                'name' => 'rate_offered_date',
                                                                'label' => 'Rate Offered Date',
                                                                'type' => 'date',
                                                                'required' => true,
                                                            ],
                                                            [
                                                                'name' => 'rate_valid_until',
                                                                'label' => 'Valid Until',
                                                                'type' => 'date',
                                                                'required' => true,
                                                                'allow_future' => true,
                                                            ],
                                                            [
                                                                'name' => 'bank_reference',
                                                                'label' => 'Bank Reference',
                                                            ],
                                                            [
                                                                'name' => 'processing_fee_type',
                                                                'label' => 'PF Type',
                                                                'type' => 'select',
                                                                'required' => true,
                                                                'options' => [
                                                                    'percent' => 'Percentage (%)',
                                                                    'amount' => 'Fixed Amount (₹)',
                                                                ],
                                                            ],
                                                            [
                                                                'name' => 'processing_fee',
                                                                'label' => 'Processing Fee',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                            ],
                                                            [
                                                                'name' => 'processing_fee_amount',
                                                                'label' => 'PF Amount (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                            ],
                                                            [
                                                                'name' => 'gst_percent',
                                                                'label' => 'GST (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                            ],
                                                            [
                                                                'name' => 'pf_gst_amount',
                                                                'label' => 'PF GST Amount (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                            ],
                                                            [
                                                                'name' => 'total_pf',
                                                                'label' => 'Total PF (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                            ],
                                                            [
                                                                'name' => 'admin_charges',
                                                                'label' => 'Admin Charges (₹)',
                                                                'type' => 'currency',
                                                                'required' => true,
                                                            ],
                                                            [
                                                                'name' => 'admin_charges_gst_percent',
                                                                'label' => 'Admin GST (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                            ],
                                                            [
                                                                'name' => 'admin_charges_gst_amount',
                                                                'label' => 'Admin GST Amount (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                            ],
                                                            [
                                                                'name' => 'total_admin_charges',
                                                                'label' => 'Total Admin Charges (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                            ],
                                                            [
                                                                'name' => 'special_conditions',
                                                                'label' => 'Special Conditions',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                            ],
                                                            [
                                                                'name' => 'stageRemarks',
                                                                'label' => 'Remarks',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                            ],
                                                        ],
                                                    ])
                                                    <button class="btn-accent-sm shf-rate-pf-action mt-1"
                                                        data-loan-id="{{ $loan->id }}" data-action="return_to_owner">
                                                        Save & Return to Task Owner
                                                    </button>
                                                </div>

                                                {{-- Phase 3: Eligible user sees editable form with original values as hints --}}
                                            @elseif($ratePfPhase === '3')
                                                @php $origValues = $ratePfNotes['original_values'] ?? []; @endphp
                                                <div class="mt-2 border-top pt-2">
                                                    <small class="fw-semibold text-muted d-block mb-1">Bank has reviewed. Values
                                                        you originally entered are shown below each field.</small>
                                                    @include('newtheme.loans.partials.stage-notes-form', [
                                                        'hideSubmit' => true,
                                                        'fields' => [
                                                            [
                                                                'name' => 'interest_rate',
                                                                'label' => 'Interest Rate (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'hint' =>
                                                                    ($origValues['interest_rate'] ?? '') !== ''
                                                                        ? $origValues['interest_rate'] . '%'
                                                                        : '',
                                                            ],
                                                            [
                                                                'name' => 'repo_rate',
                                                                'label' => 'Repo Rate (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'hint' =>
                                                                    ($origValues['repo_rate'] ?? '') !== ''
                                                                        ? $origValues['repo_rate'] . '%'
                                                                        : '',
                                                            ],
                                                            [
                                                                'name' => 'bank_rate',
                                                                'label' => 'Bank Margin (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'readonly' => true,
                                                                'hint' =>
                                                                    ($origValues['bank_rate'] ?? '') !== ''
                                                                        ? $origValues['bank_rate'] . '%'
                                                                        : '',
                                                            ],
                                                            [
                                                                'name' => 'rate_offered_date',
                                                                'label' => 'Rate Offered Date',
                                                                'type' => 'date',
                                                                'required' => true,
                                                                'hint' => $origValues['rate_offered_date'] ?? '',
                                                            ],
                                                            [
                                                                'name' => 'rate_valid_until',
                                                                'label' => 'Valid Until',
                                                                'type' => 'date',
                                                                'required' => true,
                                                                'allow_future' => true,
                                                                'hint' => $origValues['rate_valid_until'] ?? '',
                                                            ],
                                                            [
                                                                'name' => 'bank_reference',
                                                                'label' => 'Bank Reference',
                                                                'hint' => $origValues['bank_reference'] ?? '',
                                                            ],
                                                            [
                                                                'name' => 'processing_fee_type',
                                                                'label' => 'PF Type',
                                                                'type' => 'select',
                                                                'required' => true,
                                                                'options' => [
                                                                    'percent' => 'Percentage (%)',
                                                                    'amount' => 'Fixed Amount (₹)',
                                                                ],
                                                                'hint' =>
                                                                    ($origValues['processing_fee_type'] ?? '') !==
                                                                    ''
                                                                        ? ($origValues['processing_fee_type'] ===
                                                                        'percent'
                                                                            ? 'Percentage'
                                                                            : 'Fixed Amount')
                                                                        : '',
                                                            ],
                                                            [
                                                                'name' => 'processing_fee',
                                                                'label' => 'Processing Fee',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'hint' =>
                                                                    ($origValues['processing_fee'] ?? '') !== ''
                                                                        ? $origValues['processing_fee']
                                                                        : '',
                                                            ],
                                                            [
                                                                'name' => 'processing_fee_amount',
                                                                'label' => 'PF Amount (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                                'hint' =>
                                                                    ($origValues['processing_fee_amount'] ??
                                                                        '') !==
                                                                    ''
                                                                        ? '₹ ' .
                                                                            $origValues['processing_fee_amount']
                                                                        : '',
                                                            ],
                                                            [
                                                                'name' => 'gst_percent',
                                                                'label' => 'GST (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'hint' =>
                                                                    ($origValues['gst_percent'] ?? '') !== ''
                                                                        ? $origValues['gst_percent'] . '%'
                                                                        : '',
                                                            ],
                                                            [
                                                                'name' => 'pf_gst_amount',
                                                                'label' => 'PF GST Amount (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                                'hint' =>
                                                                    ($origValues['pf_gst_amount'] ?? '') !== ''
                                                                        ? '₹ ' . $origValues['pf_gst_amount']
                                                                        : '',
                                                            ],
                                                            [
                                                                'name' => 'total_pf',
                                                                'label' => 'Total PF (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                                'hint' =>
                                                                    ($origValues['total_pf'] ?? '') !== ''
                                                                        ? '₹ ' . $origValues['total_pf']
                                                                        : '',
                                                            ],
                                                            [
                                                                'name' => 'admin_charges',
                                                                'label' => 'Admin Charges (₹)',
                                                                'type' => 'currency',
                                                                'required' => true,
                                                                'hint' =>
                                                                    ($origValues['admin_charges'] ?? '') !== ''
                                                                        ? '₹ ' . $origValues['admin_charges']
                                                                        : '',
                                                            ],
                                                            [
                                                                'name' => 'admin_charges_gst_percent',
                                                                'label' => 'Admin GST (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'hint' =>
                                                                    ($origValues['admin_charges_gst_percent'] ??
                                                                        '') !==
                                                                    ''
                                                                        ? $origValues[
                                                                                'admin_charges_gst_percent'
                                                                            ] . '%'
                                                                        : '',
                                                            ],
                                                            [
                                                                'name' => 'admin_charges_gst_amount',
                                                                'label' => 'Admin GST Amount (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                                'hint' =>
                                                                    ($origValues['admin_charges_gst_amount'] ??
                                                                        '') !==
                                                                    ''
                                                                        ? '₹ ' .
                                                                            $origValues['admin_charges_gst_amount']
                                                                        : '',
                                                            ],
                                                            [
                                                                'name' => 'total_admin_charges',
                                                                'label' => 'Total Admin Charges (₹)',
                                                                'type' => 'currency',
                                                                'readonly' => true,
                                                                'hint' =>
                                                                    ($origValues['total_admin_charges'] ?? '') !==
                                                                    ''
                                                                        ? '₹ ' . $origValues['total_admin_charges']
                                                                        : '',
                                                            ],
                                                            [
                                                                'name' => 'special_conditions',
                                                                'label' => 'Special Conditions',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                                'hint' => $origValues['special_conditions'] ?? '',
                                                            ],
                                                            [
                                                                'name' => 'stageRemarks',
                                                                'label' => 'Remarks',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                            ],
                                                        ],
                                                    ])
                                                    <button class="btn-accent-sm shf-rate-pf-action mt-1"
                                                        data-loan-id="{{ $loan->id }}" data-action="complete">
                                                        Complete Rate & PF
                                                    </button>
                                                </div>
                                            @endif
                                        @break

                                        @case('sanction')
                                            @php
                                                $sanctionNotes = $assignment->getNotesData();
                                                $sanctionPhase = $sanctionNotes['sanction_phase'] ?? '1';
                                                $sanctionCompleted = $assignment->status === 'completed';
                                                $ratePfAssignment = $loan
                                                    ->stageAssignments()
                                                    ->where('stage_key', 'rate_pf')
                                                    ->first();
                                                $interestRateDefault = $ratePfAssignment
                                                    ? $ratePfAssignment->getNotesData()['interest_rate'] ?? ''
                                                    : '';
                                            @endphp

                                            {{-- Completed: show saved data with Edit toggle --}}
                                            @if ($sanctionCompleted)
                                                <div class="mt-2 border-top pt-2 shf-stage-saved-data" id="saved-sanction">
                                                    <div class="row g-2">
                                                        <div class="col-sm-6">
                                                            <div class="small"><span class="text-muted">Sanction Date:</span>
                                                                <strong>{{ $sanctionNotes['sanction_date'] ?? '—' }}</strong>
                                                            </div>
                                                        </div>
                                                        @if (!empty($sanctionNotes['conditions']))
                                                            <div class="col-12">
                                                                <div class="small"><span class="text-muted">Conditions:</span>
                                                                    {{ $sanctionNotes['conditions'] }}</div>
                                                            </div>
                                                        @endif
                                                        @if (!empty($sanctionNotes['stageRemarks']))
                                                            <div class="col-12">
                                                                <div class="small text-muted">
                                                                    {{ $sanctionNotes['stageRemarks'] }}</div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                    @if (
                                                        $loan->status === 'active' && ($loan->canEditStage('sanction') ||
                                                            auth()->user()->hasAnyRole(['super_admin', 'admin', 'bdh', 'branch_manager'])))
                                                        <button type="button"
                                                            class="btn-accent-sm mt-2 shf-edit-saved shf-text-2xs shf-btn-gray"
                                                            data-target="#edit-sanction">
                                                            <svg class="shf-icon-xs" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg> Edit
                                                        </button>
                                                    @endif
                                                </div>
                                                <div id="edit-sanction" class="shf-collapse-hidden">
                                                    @include('newtheme.loans.partials.stage-notes-form', [
                                                        'fields' => [
                                                            [
                                                                'name' => 'sanction_date',
                                                                'label' => 'Sanction Date',
                                                                'type' => 'date',
                                                                'required' => true,
                                                                'readonly' => true,
                                                            ],
                                                            [
                                                                'name' => 'conditions',
                                                                'label' => 'Conditions',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                            ],
                                                            [
                                                                'name' => 'stageRemarks',
                                                                'label' => 'Remarks',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                            ],
                                                        ],
                                                    ])
                                                </div>

                                                {{-- Phase 1: Task owner sends for sanction letter --}}
                                            @elseif($sanctionPhase === '1')
                                                <div class="mt-2 border-top pt-2">
                                                    <small class="text-muted d-block mb-2">Send this loan for sanction letter
                                                        generation.</small>
                                                    @php $sanctionP2Role = $getPhaseRole(1); @endphp
                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                        <select class="shf-input shf-input-sm shf-transfer-user"
                                                            data-stage="sanction" data-role="{{ $sanctionP2Role }}"
                                                            data-loan-id="{{ $loan->id }}" style="max-width:220px">
                                                            <option value="">Select {{ ucwords(str_replace('_', ' ', $sanctionP2Role)) }}...</option>
                                                        </select>
                                                        <button class="btn-accent-sm shf-sanction-action"
                                                            data-loan-id="{{ $loan->id }}" data-action="send_for_sanction">
                                                            Send for Sanction Letter Generation
                                                        </button>
                                                    </div>
                                                </div>

                                                {{-- Phase 2: Bank employee marks sanction letter as generated --}}
                                            @elseif($sanctionPhase === '2')
                                                <div class="mt-2 border-top pt-2">
                                                    <div class="alert alert-info py-2 mb-2 shf-text-sm">
                                                        <strong>Waiting for sanction letter.</strong> Please generate the sanction
                                                        letter for this loan and click the button below when done.
                                                    </div>
                                                    <button class="btn-accent-sm shf-sanction-action"
                                                        data-loan-id="{{ $loan->id }}" data-action="sanction_generated">
                                                        Sanction Letter Generated
                                                    </button>
                                                </div>

                                                {{-- Phase 3: Task owner fills sanction date (financials are captured at docket login) --}}
                                            @elseif($sanctionPhase === '3')
                                                <div class="mt-2 border-top pt-2">
                                                    <small class="fw-semibold text-muted d-block mb-1">Sanction letter has been
                                                        generated. Enter the sanction date below. Loan amount, rate, tenure and
                                                        EMI will be captured by the office employee at docket login.</small>
                                                    @include('newtheme.loans.partials.stage-notes-form', [
                                                        'fields' => [
                                                            [
                                                                'name' => 'sanction_date',
                                                                'label' => 'Sanction Date',
                                                                'type' => 'date',
                                                                'required' => true,
                                                                'default' => now()->format('d/m/Y'),
                                                            ],
                                                            [
                                                                'name' => 'conditions',
                                                                'label' => 'Conditions',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                            ],
                                                            [
                                                                'name' => 'stageRemarks',
                                                                'label' => 'Remarks',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                            ],
                                                        ],
                                                    ])
                                                </div>
                                            @endif
                                        @break

                                        @case('docket')
                                            @php
                                                $docketNotes = $assignment->getNotesData();
                                                $docketPhase = $docketNotes['docket_phase'] ?? '1';

                                                // Default sanctioned rate from rate_pf stage (if already filled)
                                                $docketRatePfAssignment = $loan
                                                    ->stageAssignments()
                                                    ->where('stage_key', 'rate_pf')
                                                    ->first();
                                                $docketInterestRateDefault = $docketRatePfAssignment
                                                    ? $docketRatePfAssignment->getNotesData()['interest_rate'] ?? ''
                                                    : '';

                                                // Calculate expected docket date from sanction_date + app_number offset
                                                $appNumberAssignment = $loan
                                                    ->stageAssignments()
                                                    ->where('stage_key', 'app_number')
                                                    ->first();
                                                $appNotes = $appNumberAssignment
                                                    ? $appNumberAssignment->getNotesData()
                                                    : [];
                                                $sanctionAssignment = $loan
                                                    ->stageAssignments()
                                                    ->where('stage_key', 'sanction')
                                                    ->first();
                                                $sanctionNotesDocket = $sanctionAssignment
                                                    ? $sanctionAssignment->getNotesData()
                                                    : [];
                                                $expectedDocketDate = null;
                                                $expectedDocketCarbon = null;
                                                $docketOffset = $appNotes['docket_days_offset'] ?? null;

                                                if (
                                                    $docketOffset &&
                                                    $docketOffset !== '0' &&
                                                    !empty($sanctionNotesDocket['sanction_date'])
                                                ) {
                                                    $expectedDocketCarbon = \Carbon\Carbon::createFromFormat(
                                                        'd/m/Y',
                                                        $sanctionNotesDocket['sanction_date'],
                                                    )->addDays((int) $docketOffset);
                                                    $expectedDocketDate = $expectedDocketCarbon->format('d/m/Y');
                                                } elseif (
                                                    $docketOffset === '0' &&
                                                    !empty($appNotes['custom_docket_date'])
                                                ) {
                                                    $expectedDocketCarbon = \Carbon\Carbon::createFromFormat(
                                                        'd/m/Y',
                                                        $appNotes['custom_docket_date'],
                                                    );
                                                    $expectedDocketDate = $appNotes['custom_docket_date'];
                                                }

                                                $docketDaysInfo = '';
                                                if ($expectedDocketCarbon) {
                                                    $diffDays = now()
                                                        ->startOfDay()
                                                        ->diffInDays($expectedDocketCarbon->startOfDay(), false);
                                                    if ($diffDays > 0) {
                                                        $docketDaysInfo =
                                                            '<span class="text-success fw-semibold">' .
                                                            $diffDays .
                                                            ' day' .
                                                            ($diffDays > 1 ? 's' : '') .
                                                            ' remaining</span>';
                                                    } elseif ($diffDays === 0) {
                                                        $docketDaysInfo =
                                                            '<span class="text-warning fw-semibold">Due today</span>';
                                                    } else {
                                                        $docketDaysInfo =
                                                            '<span class="text-danger fw-semibold">' .
                                                            abs($diffDays) .
                                                            ' day' .
                                                            (abs($diffDays) > 1 ? 's' : '') .
                                                            ' overdue</span>';
                                                    }
                                                }
                                            @endphp

                                            {{-- Expected docket date banner --}}
                                            @if ($expectedDocketDate)
                                                <div
                                                    class="alert {{ $expectedDocketCarbon && $expectedDocketCarbon->isPast() ? 'alert-danger' : 'alert-info' }} py-2 mt-2 mb-2 shf-text-sm">
                                                    <strong>Expected Docket Date:</strong> {{ $expectedDocketDate }}
                                                    @if ($docketOffset && $docketOffset !== '0')
                                                        <small class="text-muted">(Sanction + {{ $docketOffset }}d)</small>
                                                    @endif
                                                    — {!! $docketDaysInfo !!}
                                                </div>
                                            @endif

                                            {{-- Phase 1: User fills login date, sends to office employee --}}
                                            @if ($docketPhase === '1')
                                                @include('newtheme.loans.partials.stage-notes-form', [
                                                    'hideSubmit' => true,
                                                    'fields' => [
                                                        [
                                                            'name' => 'stageRemarks',
                                                            'label' => 'Remarks',
                                                            'type' => 'textarea',
                                                            'col' => 12,
                                                        ],
                                                    ],
                                                ])
                                                @php $docketP2Role = $getPhaseRole(1); @endphp
                                                <div class="d-flex align-items-center gap-2 flex-wrap mt-2">
                                                    <select class="shf-input shf-input-sm shf-transfer-user" data-stage="docket"
                                                        data-role="{{ $docketP2Role }}" data-loan-id="{{ $loan->id }}"
                                                        style="max-width:220px">
                                                        <option value="">Select {{ ucwords(str_replace('_', ' ', $docketP2Role)) }}...</option>
                                                    </select>
                                                    <button class="btn-accent-sm shf-docket-action"
                                                        data-loan-id="{{ $loan->id }}" data-action="send_to_office">
                                                        Send to {{ ucwords(str_replace('_', ' ', $docketP2Role)) }}
                                                    </button>
                                                </div>

                                                {{-- Phase 2: Office employee fills login date + loan financials and completes docket --}}
                                            @elseif($docketPhase === '2')
                                                @if ($assignment->status === 'in_progress')
                                                    @include('newtheme.loans.partials.stage-notes-form', [
                                                        'hideSubmit' => true,
                                                        'fields' => [
                                                            [
                                                                'name' => 'login_date',
                                                                'label' => 'Login Date',
                                                                'type' => 'date',
                                                                'required' => true,
                                                                'default' => now()->format('d/m/Y'),
                                                            ],
                                                            [
                                                                'name' => 'sanctioned_amount',
                                                                'label' => 'Sanctioned Amount',
                                                                'type' => 'currency',
                                                                'required' => true,
                                                                'default' => $loan->loan_amount,
                                                            ],
                                                            [
                                                                'name' => 'sanctioned_rate',
                                                                'label' => 'Sanctioned Rate (%)',
                                                                'type' => 'number',
                                                                'step' => '0.01',
                                                                'required' => true,
                                                                'default' => $docketInterestRateDefault,
                                                            ],
                                                            [
                                                                'name' => 'tenure_months',
                                                                'label' => 'Tenure (Months)',
                                                                'type' => 'number',
                                                                'step' => '1',
                                                                'required' => true,
                                                                'placeholder' => 'e.g. 240',
                                                            ],
                                                            [
                                                                'name' => 'emi_amount',
                                                                'label' => 'EMI Amount (₹)',
                                                                'type' => 'currency',
                                                                'required' => true,
                                                            ],
                                                            [
                                                                'name' => 'stageRemarks',
                                                                'label' => 'Remarks',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                            ],
                                                        ],
                                                    ])
                                                    <small class="text-muted d-block mb-2">Fill login date and loan financials,
                                                        then click below to complete docket and generate KFS.</small>
                                                    <button class="btn-accent-sm shf-stage-action"
                                                        data-loan-id="{{ $loan->id }}" data-stage="docket"
                                                        data-action="completed">
                                                        <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                        Generate KFS
                                                    </button>
                                                @else
                                                    <div class="mt-2 border-top pt-2">
                                                        <div class="row g-2">
                                                            <div class="col-sm-6">
                                                                <div class="small"><span class="text-muted">Login Date:</span>
                                                                    <strong>{{ $docketNotes['login_date'] ?? '—' }}</strong>
                                                                </div>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <div class="small"><span class="text-muted">Sanctioned Amount:</span>
                                                                    <strong>₹ {{ $docketNotes['sanctioned_amount'] ?? '—' }}</strong>
                                                                </div>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <div class="small"><span class="text-muted">Sanctioned Rate:</span>
                                                                    <strong>{{ $docketNotes['sanctioned_rate'] ?? '—' }}%</strong>
                                                                </div>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <div class="small"><span class="text-muted">Tenure:</span>
                                                                    <strong>{{ $docketNotes['tenure_months'] ?? '—' }} months</strong>
                                                                </div>
                                                            </div>
                                                            <div class="col-sm-6">
                                                                <div class="small"><span class="text-muted">EMI Amount:</span>
                                                                    <strong>₹ {{ $docketNotes['emi_amount'] ?? '—' }}</strong>
                                                                </div>
                                                            </div>
                                                            @if (!empty($docketNotes['stageRemarks']))
                                                                <div class="col-12">
                                                                    <div class="small text-muted">{{ $docketNotes['stageRemarks'] }}</div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif
                                            @endif
                                        @break

                                        @case('kfs')
                                            @if ($assignment->status === 'in_progress')
                                                <div class="mt-2 border-top pt-2">
                                                    <small class="text-muted d-block mb-2">Review the KFS for this loan, then click
                                                        below to complete.</small>
                                                    <button class="btn-accent-sm shf-stage-action"
                                                        data-loan-id="{{ $loan->id }}" data-stage="kfs"
                                                        data-action="completed">
                                                        <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                d="M5 13l4 4L19 7" />
                                                        </svg>
                                                        KFS Complete
                                                    </button>
                                                </div>
                                            @endif
                                        @break

                                        @case('esign')
                                            @php $esignPhase = ($assignment->getNotesData()['esign_phase'] ?? '1'); @endphp

                                            @if ($esignPhase === '1')
                                                {{-- Phase 1: Advisor sends for E-Sign --}}
                                                @php $esignP2Role = $getPhaseRole(1); @endphp
                                                <div class="mt-2 border-top pt-2">
                                                    <small class="text-muted d-block mb-2">Send for E-Sign & eNACH
                                                        generation.</small>
                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                        <select class="shf-input shf-input-sm shf-transfer-user"
                                                            data-stage="esign" data-role="{{ $esignP2Role }}"
                                                            data-loan-id="{{ $loan->id }}" style="max-width:220px">
                                                            <option value="">Select {{ ucwords(str_replace('_', ' ', $esignP2Role)) }}...</option>
                                                        </select>
                                                        <button class="btn-accent-sm shf-esign-action"
                                                            data-loan-id="{{ $loan->id }}" data-action="send_for_esign">
                                                            Send to {{ ucwords(str_replace('_', ' ', $esignP2Role)) }}
                                                        </button>
                                                    </div>
                                                </div>
                                            @elseif($esignPhase === '2')
                                                {{-- Phase 2: Bank employee generates E-Sign & eNACH --}}
                                                <div class="mt-2 border-top pt-2">
                                                    <small class="text-muted d-block mb-2">Generate E-Sign & eNACH documents for
                                                        this loan, then click below.</small>
                                                    <button class="btn-accent-sm shf-esign-action"
                                                        data-loan-id="{{ $loan->id }}" data-action="esign_generated">
                                                        E-Sign & eNACH Generated
                                                    </button>
                                                </div>
                                            @elseif($esignPhase === '3')
                                                {{-- Phase 3: Advisor completes with customer --}}
                                                <div class="mt-2 border-top pt-2">
                                                    <small class="text-muted d-block mb-2">E-Sign & eNACH has been generated by
                                                        bank. Complete the signing process with the customer.</small>
                                                    <button class="btn-accent-sm shf-esign-action"
                                                        data-loan-id="{{ $loan->id }}" data-action="esign_customer_done">
                                                        E-Sign & eNACH Completed with Customer
                                                    </button>
                                                </div>
                                            @elseif($esignPhase === '4')
                                                @if ($assignment->status === 'in_progress')
                                                    {{-- Phase 4: Bank employee final confirmation --}}
                                                    <div class="mt-2 border-top pt-2">
                                                        <small class="text-muted d-block mb-2">Customer has completed E-Sign &
                                                            eNACH. Click Complete to finish this stage.</small>
                                                        <button class="btn-accent-sm shf-esign-action"
                                                            data-loan-id="{{ $loan->id }}" data-action="esign_complete">
                                                            <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                            Complete E-Sign & eNACH
                                                        </button>
                                                    </div>
                                                @elseif($assignment->status === 'completed')
                                                    <div class="mt-2 border-top pt-2">
                                                        <div class="small text-muted">E-Sign & eNACH completed</div>
                                                    </div>
                                                @endif
                                            @endif
                                        @break

                                        @case('disbursement')
                                            @php $disbData = $loan->disbursement; @endphp
                                            @if ($assignment->status === 'completed' && $disbData)
                                                <div class="mt-2 border-top pt-2">
                                                    <div class="row g-2 small">
                                                        <div class="col-sm-4"><span class="text-muted">Type:</span> <strong>{{ $disbData->disbursement_type === 'fund_transfer' ? 'Fund Transfer' : 'Cheque' }}</strong></div>
                                                        <div class="col-sm-4"><span class="text-muted">Amount:</span> <strong>₹ {{ number_format($disbData->amount_disbursed) }}</strong></div>
                                                        <div class="col-sm-4"><span class="text-muted">Date:</span> <strong>{{ $disbData->disbursement_date?->format('d/m/Y') ?? '—' }}</strong></div>
                                                        @if ($disbData->bank_account_number)
                                                            <div class="col-sm-4"><span class="text-muted">Account #:</span> <strong>{{ $disbData->bank_account_number }}</strong></div>
                                                        @endif
                                                        @if ($disbData->notes)
                                                            <div class="col-12"><span class="text-muted">Notes:</span> {{ $disbData->notes }}</div>
                                                        @endif
                                                    </div>
                                                    <a href="{{ route('loans.disbursement', $loan) }}" class="btn-accent-outline btn-accent-sm mt-2">
                                                        <svg class="shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                        View Details
                                                    </a>
                                                </div>
                                            @else
                                                <div class="mt-2">
                                                    <a href="{{ route('loans.disbursement', $loan) }}" class="btn-accent-sm shf-primary-action">
                                                        <svg class="shf-icon-2xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                                        Open Disbursement Form
                                                    </a>
                                                </div>
                                            @endif
                                        @break

                                        @case('otc_clearance')
                                            @php
                                                $otcNotes = $assignment->getNotesData();
                                                $disbursementData = $loan->disbursement;
                                                $chequeList = $disbursementData?->cheques ?? [];
                                                $isOtcAssignee =
                                                    $assignment->assigned_to === auth()->id() ||
                                                    auth()
                                                        ->user()
                                                        ->hasAnyRole(['super_admin', 'admin']);
                                            @endphp
                                            <div class="mt-2 border-top pt-2">
                                                @if (!empty($chequeList))
                                                    <small class="fw-semibold text-muted d-block mb-2">Cheques to be handed
                                                        over:</small>
                                                    <div class="table-responsive mb-2">
                                                        <table class="table table-sm table-hover mb-0 shf-text-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>Name</th>
                                                                    <th>Cheque No.</th>
                                                                    <th>Date</th>
                                                                    <th class="text-end">Amount</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($chequeList as $chq)
                                                                    <tr>
                                                                        <td>{{ $chq['cheque_name'] ?? '—' }}</td>
                                                                        <td>{{ $chq['cheque_number'] ?? '—' }}</td>
                                                                        <td>{{ $chq['cheque_date'] ?? '—' }}</td>
                                                                        <td class="text-end">₹
                                                                            {{ number_format($chq['cheque_amount'] ?? 0) }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @endif

                                                @if ($isOtcAssignee && $assignment->status === 'in_progress')
                                                    @include('newtheme.loans.partials.stage-notes-form', [
                                                        'hideSubmit' => true,
                                                        'fields' => [
                                                            [
                                                                'name' => 'handover_date',
                                                                'label' => 'Handover Date',
                                                                'type' => 'date',
                                                                'required' => true,
                                                                'default' => now()->format('d/m/Y'),
                                                            ],
                                                            [
                                                                'name' => 'stageRemarks',
                                                                'label' => 'Remarks',
                                                                'type' => 'textarea',
                                                                'col' => 12,
                                                            ],
                                                        ],
                                                    ])

                                                    <div class="d-flex align-items-center gap-2 flex-wrap mt-2">
                                                        <button class="btn-accent-sm shf-stage-action"
                                                            data-loan-id="{{ $loan->id }}" data-stage="otc_clearance"
                                                            data-action="completed">
                                                            <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M5 13l4 4L19 7" />
                                                            </svg>
                                                            Complete OTC Clearance
                                                        </button>
                                                    </div>

                                                    {{-- OTC: Transfer to Office Employee option (only for non-office-employee roles) --}}
                                                    @if (!auth()->user()->hasRole('office_employee'))
                                                        <div class="mt-2 border-top pt-2">
                                                            <small class="fw-semibold text-muted d-block mb-2">Or transfer to
                                                                Office Employee:</small>
                                                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                                                <select class="shf-input shf-input-sm shf-transfer-user"
                                                                    data-stage="otc_clearance" data-role="office_employee"
                                                                    data-loan-id="{{ $loan->id }}" style="max-width:220px">
                                                                    <option value="">Select Office Employee...</option>
                                                                </select>
                                                                <button class="btn-accent-outline btn-accent-sm shf-otc-transfer"
                                                                    data-loan-id="{{ $loan->id }}">
                                                                    Transfer to Office Employee
                                                                </button>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @elseif($assignment->status === 'in_progress' && !$isOtcAssignee)
                                                    <div class="alert alert-info py-2 mt-2 shf-text-sm">
                                                        <strong>Transferred to {{ $assignment->assignee?->name }}.</strong>
                                                        Waiting for them to complete the handover.
                                                    </div>
                                                @elseif($assignment->status === 'completed')
                                                    <div class="mt-2">
                                                        @if (!empty($otcNotes['handover_date']))
                                                            <div class="small"><span class="text-muted">Handover Date:</span>
                                                                <strong>{{ $otcNotes['handover_date'] }}</strong>
                                                            </div>
                                                        @endif
                                                        @if (!empty($otcNotes['stageRemarks']))
                                                            <div class="small text-muted">{{ $otcNotes['stageRemarks'] }}</div>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @break

                                        {{-- sanction_decision is handled in sub-stages section above --}}
                                    @endswitch
                                @endif

                                {{-- Active Queries Banner (main stages) --}}
                                @php
                                    $queryStages = ['rate_pf', 'sanction', 'docket', 'kfs', 'esign', 'disbursement'];
                                    $mainActiveQueries = in_array($assignment->stage_key, $queryStages)
                                        ? \App\Models\StageQuery::where('loan_id', $loan->id)
                                            ->where('stage_key', $assignment->stage_key)
                                            ->whereIn('status', ['pending', 'responded'])
                                            ->with(['raisedByUser', 'responses.respondedByUser'])
                                            ->get()
                                        : collect();
                                @endphp
                                @if ($mainActiveQueries->isNotEmpty())
                                    <div class="alert alert-warning py-2 mt-2 shf-text-sm">
                                        <strong>Active Queries ({{ $mainActiveQueries->count() }})</strong> — Stage cannot
                                        be completed until resolved.
                                        @foreach ($mainActiveQueries as $q)
                                            <div class="border-top mt-2 pt-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <strong>{{ $q->raisedByUser->name }}</strong>
                                                    <span
                                                        class="shf-badge shf-badge-{{ $q->status === 'pending' ? 'orange' : 'blue' }} shf-text-2xs">{{ ucfirst($q->status) }}</span>
                                                </div>
                                                <p class="mb-1">{{ $q->query_text }}</p>
                                                @foreach ($q->responses as $resp)
                                                    <div class="ps-3 border-start border-2 border-info mb-1">
                                                        <small><strong>{{ $resp->respondedByUser->name }}</strong> ·
                                                            {{ $resp->created_at->diffForHumans() }}</small>
                                                        <p class="mb-0 small">{{ $resp->response_text }}</p>
                                                    </div>
                                                @endforeach
                                                {{-- Response form (any user can respond to a pending query) --}}
                                                @if ($q->status === 'pending')
                                                    <form class="shf-query-respond mt-1"
                                                        data-url="{{ route('loans.queries.respond', $q) }}">
                                                        <div class="input-group input-group-sm">
                                                            <input type="text" name="response_text"
                                                                class="shf-input shf-text-sm"
                                                                placeholder="Type response..." required>
                                                            <button type="submit" class="btn-accent-sm"><svg
                                                                    class="shf-icon-2xs" fill="none"
                                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                                                </svg> Respond</button>
                                                        </div>
                                                    </form>
                                                @endif
                                                {{-- Resolve button (only for user who raised the query, and query is responded) --}}
                                                @if ($q->status === 'responded' && $q->raised_by === auth()->id())
                                                    <button
                                                        class="btn-accent-sm mt-1 shf-query-resolve shf-text-2xs shf-btn-success"
                                                        data-url="{{ route('loans.queries.resolve', $q) }}">
                                                        Resolve Query
                                                    </button>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Resolved Queries Summary (main stages) --}}
                                @if (in_array($assignment->stage_key, $queryStages ?? []))
                                    @php
                                        $mainResolvedQueries = \App\Models\StageQuery::where('loan_id', $loan->id)
                                            ->where('stage_key', $assignment->stage_key)
                                            ->where('status', 'resolved')
                                            ->with(['raisedByUser', 'responses.respondedByUser'])
                                            ->get();
                                    @endphp
                                    @if ($mainResolvedQueries->isNotEmpty())
                                        <div class="mt-1">
                                            <a class="shf-text-xs text-muted text-decoration-none shf-clickable"
                                                data-bs-toggle="collapse"
                                                href="#resolved-main-{{ $assignment->stage_key }}">
                                                <svg class="shf-icon-2xs shf-icon-inline" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M9 12l2 2 4-4" />
                                                </svg>
                                                {{ $mainResolvedQueries->count() }} query/queries resolved
                                                <small>&#9656;</small>
                                            </a>
                                            <div class="collapse" id="resolved-main-{{ $assignment->stage_key }}">
                                                @foreach ($mainResolvedQueries as $rq)
                                                    <div
                                                        class="ps-3 border-start border-2 border-success mt-1 shf-text-xs">
                                                        <strong>{{ $rq->raisedByUser->name }}</strong>:
                                                        {{ $rq->query_text }}
                                                        @foreach ($rq->responses as $resp)
                                                            <div class="text-muted">→ {{ $resp->respondedByUser->name }}:
                                                                {{ $resp->response_text }}</div>
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endif

                                {{-- Actions (only for assignee or admin) --}}
                                @if (
                                    $isMainAssignee &&
                                        auth()->user()->hasPermission('manage_loan_stages') &&
                                        $assignment->stage_key !== 'parallel_processing')
                                    <div class="mt-2 d-flex gap-2 flex-wrap align-items-center">
                                        @if ($assignment->status === 'in_progress')
                                            @if (auth()->user()->hasAnyRole(['super_admin', 'admin', 'branch_manager', 'bdh']))
                                                <button class="btn-accent-sm shf-reject-btn shf-btn-danger"
                                                    data-loan-id="{{ $loan->id }}"
                                                    data-stage="{{ $assignment->stage_key }}">
                                                    <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    Reject
                                                </button>
                                            @endif
                                            {{-- Raise Query button for main stages --}}
                                            @if (in_array($assignment->stage_key, $queryStages))
                                                <button class="btn-accent-sm shf-raise-query-btn shf-btn-warning"
                                                    data-loan-id="{{ $loan->id }}"
                                                    data-stage="{{ $assignment->stage_key }}">
                                                    <span class="shf-text-sm fw-bold">?</span> Query
                                                </button>
                                            @endif
                                        @endif
                                        {{-- Skip: only for in_progress stages, with skip permission, and allowed by product config --}}
                                        @if (
                                            $assignment->status === 'in_progress' &&
                                                auth()->user()->hasPermission('skip_loan_stages') &&
                                                ($skipAllowed[$assignment->stage_key] ?? true))
                                            <button class="btn-accent-sm shf-stage-action shf-btn-warning"
                                                data-loan-id="{{ $loan->id }}"
                                                data-stage="{{ $assignment->stage_key }}" data-action="skipped">
                                                <svg class="shf-icon-2xs" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                                                </svg>
                                                Skip
                                            </button>
                                        @endif
                                        {{-- Transfer: single grouped dropdown + button (in_progress only, requires transfer permission) --}}
                                        @if (
                                            $assignment->status === 'in_progress' &&
                                                $assignment->assigned_to &&
                                                auth()->user()->hasPermission('transfer_loan_stages') &&
                                                !auth()->user()->hasAnyRole(['bank_employee', 'office_employee']))
                                            @php $mainTransferUsers = $stageUsers->where('id', '!=', $assignment->assigned_to); @endphp
                                            @if ($mainTransferUsers->isNotEmpty())
                                                <div class="d-inline-flex align-items-center gap-1">
                                                    <select
                                                        class="shf-input shf-input-sm shf-text-xs shf-stage-transfer-select"
                                                        data-loan-id="{{ $loan->id }}"
                                                        data-stage="{{ $assignment->stage_key }}"
                                                        style="max-width:190px;">
                                                        <option value="">Transfer to...</option>
                                                        @php $groupedMainUsers = $mainTransferUsers->groupBy(fn($u) => $u->roles->first()?->name ?? 'Other'); @endphp
                                                        @foreach ($groupedMainUsers as $roleName => $users)
                                                            <optgroup label="{{ $roleName }}">
                                                                @foreach ($users as $ru)
                                                                    <option value="{{ $ru->id }}">
                                                                        {{ $ru->name }}</option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endforeach
                                                    </select>
                                                    <button
                                                        class="btn-accent-sm shf-text-xs shf-btn-gray shf-stage-transfer-btn"
                                                        data-loan-id="{{ $loan->id }}"
                                                        data-stage="{{ $assignment->stage_key }}">Transfer</button>
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-center mt-3">
                <a href="{{ route('loans.transfers', $loan) }}" class="btn-accent-outline btn-accent-sm"><svg
                        class="shf-btn-icon shf-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>Transfer History</a>
            </div>

        </div>
    </div>

    {{-- Raise Query Modal --}}
    <div class="modal fade" id="raiseQueryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border: none; border-radius: 12px;">
                <div class="modal-header"
                    style="background: var(--primary-dark-solid); color: #fff; border-radius: 12px 12px 0 0;">
                    <h5 class="modal-title font-display">Raise Query / ક્વેરી ઉઠાવો</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" id="queryStageKey">
                    <input type="hidden" id="queryLoanId">
                    <div class="mb-3">
                        <label class="shf-form-label">Query</label>
                        <textarea id="queryText" class="shf-input" rows="3" placeholder="Describe your query..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-accent-outline btn-accent-sm" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn-accent btn-accent-sm" id="submitQueryBtn"><svg class="shf-icon-md" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg> Raise Query</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Floating action buttons for mobile/tablet --}}
    <div class="shf-bottom-bar" id="stageBottomBar"></div>
