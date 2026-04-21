@extends('newtheme.layouts.app', ['pageKey' => 'loans'])

@section('title', 'Loan #' . $loan->loan_number . ' · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/loan-show.css') }}?v={{ config('app.shf_version') }}">
@endpush

@php
    $statusTone = match ($loan->status_color) {
        'primary' => 'blue',
        'success' => 'green',
        'danger' => 'red',
        'warning' => 'orange',
        default => 'dark',
    };
    $customerTone = match ($loan->customer_type) {
        'proprietor' => 'green',
        'partnership_llp' => 'blue',
        'pvt_ltd' => 'orange',
        'salaried' => 'violet',
        default => 'dark',
    };
    $activeQueryCount = \App\Models\StageQuery::where('loan_id', $loan->id)
        ->whereIn('status', ['pending', 'responded'])
        ->count();
    $currentStage = $loan->current_stage;
    $subStages = collect();
    if ($currentStage === 'parallel_processing') {
        $subStages = $loan->stageAssignments()
            ->where('parent_stage_key', 'parallel_processing')
            ->orderBy('id')
            ->get();
    }
    $docProgress = app(\App\Services\LoanDocumentService::class)->getProgress($loan);
    $isAdmin = auth()->user()->hasAnyRole(['super_admin', 'admin', 'branch_manager', 'bdh']);
@endphp

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <a href="{{ route('loans.index') }}">Loans</a>
                    <span class="sep">/</span>
                    <span>#{{ $loan->loan_number }}</span>
                </div>
                <h1>Loan #{{ $loan->loan_number }}</h1>
                <div class="sub">
                    <strong>{{ $loan->customer_name }}</strong>
                    @if ($loan->bank_name) · {{ $loan->bank_name }}@endif
                    @if ($loan->product?->name) / {{ $loan->product->name }}@endif
                    <span class="badge {{ $statusTone }}" style="margin-left:8px;vertical-align:middle;">{{ $loan->status_label }}</span>
                </div>
            </div>
            <div class="head-actions">
                <a href="{{ route('loans.index') }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                <a href="{{ route('loans.timeline', $loan) }}" class="btn">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Timeline
                </a>
                @if (auth()->user()->hasPermission('edit_loan'))
                    @unless ($loan->isBasicEditLocked())
                        <a href="{{ route('loans.edit', $loan) }}" class="btn">
                            <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Edit
                        </a>
                    @endunless

                    @if (auth()->user()->hasPermission('change_loan_status'))
                        @if ($loan->status === 'active')
                            <button type="button" class="btn ls-status-change" data-status="on_hold">Put on Hold</button>
                            @if ($isAdmin)
                                <button type="button" class="btn danger ls-status-change" data-status="cancelled">Cancel Loan</button>
                            @endif
                        @elseif (in_array($loan->status, ['on_hold', 'cancelled']))
                            <button type="button" class="btn primary ls-status-change" data-status="active">Reactivate</button>
                        @elseif ($loan->status === 'rejected' && $isAdmin)
                            <button type="button" class="btn primary ls-status-change" data-status="active">Reactivate</button>
                        @endif
                    @endif
                @endif
                <a href="{{ route('loans.stages', $loan) }}" class="btn primary">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                    Stages
                </a>
            </div>
        </div>
    </header>

    <main class="content">
        {{-- ===== Ownership + Total Time banner ===== --}}
        <div class="card ls-banner">
            <div class="card-bd ls-banner-body">
                <div class="ls-banner-left">
                    <span class="ls-banner-label">Loan Advisor</span>
                    @if ($loan->current_owner)
                        <strong class="ls-banner-value">{{ $loan->current_owner->name }}</strong>
                        <span class="badge dark">{{ $loan->current_owner->workflow_role_label }}</span>
                    @else
                        <span class="ls-muted">Unassigned</span>
                    @endif
                </div>
                <div class="ls-banner-right">
                    <span class="ls-banner-label">Total Loan Time</span>
                    <strong class="ls-banner-value">{{ $loan->total_loan_time }}</strong>
                </div>
            </div>
        </div>

        {{-- ===== Rejection / Hold / Cancel alerts ===== --}}
        @if ($loan->status === 'rejected')
            <div class="card ls-alert ls-alert-red">
                <div class="card-bd">
                    <strong>Loan Rejected</strong>
                    @if ($loan->rejected_stage) at stage <strong>{{ $loan->rejected_stage }}</strong>@endif
                    @if ($loan->rejected_at) on {{ $loan->rejected_at->format('d M Y H:i') }}@endif
                    @if ($loan->rejection_reason)
                        <div class="ls-alert-reason">Reason: {{ $loan->rejection_reason }}</div>
                    @endif
                </div>
            </div>
        @endif

        @if (in_array($loan->status, ['on_hold', 'cancelled']) && $loan->status_reason)
            <div class="card ls-alert {{ $loan->status === 'on_hold' ? 'ls-alert-amber' : 'ls-alert-gray' }}">
                <div class="card-bd">
                    <strong>{{ $loan->status === 'on_hold' ? 'Loan On Hold' : 'Loan Cancelled' }}</strong>
                    @if ($loan->status_changed_at) on {{ $loan->status_changed_at->format('d M Y H:i') }}@endif
                    @if ($loan->statusChangedByUser) by {{ $loan->statusChangedByUser->name }}@endif
                    <div class="ls-alert-reason">Reason: {{ $loan->status_reason }}</div>
                </div>
            </div>
        @endif

        {{-- ===== Customer & loan info (collapsible, closed) ===== --}}
        <div class="card ls-card ls-collapsible collapsed" data-target="#lsDetails">
            <div class="card-hd ls-toggle" role="button" tabindex="0">
                <div class="t">
                    <span class="ls-chev" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>
                    </span>
                    <span class="ls-title">{{ $loan->customer_name }}</span>
                    @if ($loan->bank_name || $loan->product?->name)
                        <span class="ls-sub">{{ $loan->bank_name ?? '' }}{{ $loan->product ? ' / ' . $loan->product->name : '' }}</span>
                    @endif
                </div>
                <div class="actions">
                    <span class="badge green">{{ $loan->formatted_amount }}</span>
                </div>
            </div>
            <div class="card-bd ls-collapse-body" id="lsDetails">
                <div class="ls-kv-grid">
                    <div><span>Customer Name</span><span>{{ $loan->customer_name }}</span></div>
                    <div><span>Customer Type</span><span><span class="badge {{ $customerTone }}">{{ $loan->customer_type_label }}</span></span></div>
                    <div><span>Loan Amount</span><span class="ls-amount">{{ $loan->formatted_amount }}</span></div>
                    @if ($loan->customer_phone)
                        <div><span>Phone</span><span><a href="tel:{{ $loan->customer_phone }}" class="ls-link">{{ $loan->customer_phone }}</a></span></div>
                    @endif
                    @if ($loan->customer_email)
                        <div><span>Email</span><span><a href="mailto:{{ $loan->customer_email }}" class="ls-link">{{ $loan->customer_email }}</a></span></div>
                    @endif
                    <div><span>Bank</span><span>
                        {{ $loan->bank_name ?? '—' }}
                        @if ($loan->roi_min && $loan->roi_max)
                            <span class="ls-muted">(ROI {{ $loan->roi_min }}%–{{ $loan->roi_max }}%)</span>
                        @endif
                    </span></div>
                    <div><span>Product / Branch</span><span>{{ $loan->product?->name ?? '—' }} · {{ $loan->branch?->name ?? '—' }}</span></div>
                    @if ($loan->location)
                        <div><span>Location</span><span>{{ $loan->location->parent?->name ? $loan->location->parent->name . ' / ' : '' }}{{ $loan->location->name }}</span></div>
                    @endif
                    <div><span>Advisor</span><span>{{ $loan->advisor?->name ?? ($loan->creator?->name ?? '—') }}</span></div>
                    @if ($loan->due_date)
                        <div><span>Due Date</span><span>{{ $loan->due_date->format('d M Y') }}</span></div>
                    @endif
                    <div><span>Loan Number</span><span class="ls-mono">{{ $loan->loan_number }}</span></div>
                </div>
            </div>
        </div>

        {{-- ===== Current stage (clickable → stages) ===== --}}
        <a href="{{ route('loans.stages', $loan) }}" class="card ls-card ls-stage-card">
            <div class="card-hd">
                <div class="t">
                    <span class="num">S</span>
                    {{ in_array($loan->status, ['completed', 'rejected', 'cancelled']) ? 'Workflow' : 'Current Stage' }}
                    @if ($activeQueryCount > 0)
                        <span class="badge orange">{{ $activeQueryCount }} {{ Str::plural('query', $activeQueryCount) }}</span>
                    @endif
                </div>
                <div class="actions">
                    <span class="ls-arrow">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>
                    </span>
                </div>
            </div>
            <div class="card-bd ls-stage-body">
                <div class="ls-stage-row">
                    <div>
                        <div class="ls-stage-name">{{ $loan->current_stage_name }}</div>
                        @if ($loan->progress)
                            <div class="ls-muted">
                                {{ $loan->progress->completed_stages }}/{{ $loan->progress->total_stages }}
                                stages · {{ number_format($loan->progress->overall_percentage, 0) }}%
                            </div>
                        @endif
                    </div>
                    <span class="btn primary sm">View Stages</span>
                </div>

                @if ($activeQueryCount > 0)
                    <div class="ls-query-warning">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        {{ $activeQueryCount }} active {{ Str::plural('query', $activeQueryCount) }} — stages blocked until resolved
                    </div>
                @endif

                @if ($loan->progress)
                    <div class="ls-progress"><div class="ls-progress-fill" style="width:{{ $loan->progress->overall_percentage }}%"></div></div>
                @endif
            </div>
        </a>

        {{-- ===== Parallel sub-stages summary (when parallel_processing is current) ===== --}}
        @if ($currentStage === 'parallel_processing' && $subStages->isNotEmpty())
            <div class="card ls-card">
                <div class="card-hd"><div class="t"><span class="num">3</span>Parallel Processing — Sub-stages</div></div>
                <div class="card-bd ls-sub-list">
                    @foreach ($subStages as $sub)
                        @php
                            $subStageName = \App\Models\Stage::where('stage_key', $sub->stage_key)->value('stage_name_en')
                                ?? ucwords(str_replace('_', ' ', $sub->stage_key));
                            $statusTonesSub = [
                                'completed' => 'green',
                                'in_progress' => 'blue',
                                'pending' => 'dark',
                                'rejected' => 'red',
                                'skipped' => 'amber',
                            ];
                            $statusLabelsSub = [
                                'completed' => 'Done',
                                'in_progress' => 'In Progress',
                                'pending' => 'Pending',
                                'rejected' => 'Rejected',
                                'skipped' => 'Skipped',
                            ];
                        @endphp
                        <div class="ls-sub-row">
                            <div>
                                <span class="ls-sub-name">{{ $subStageName }}</span>
                                @if ($sub->assignee)
                                    <span class="ls-muted ls-sub-who">{{ $sub->assignee->name }}</span>
                                @endif
                            </div>
                            <span class="badge {{ $statusTonesSub[$sub->status] ?? 'dark' }}">{{ $statusLabelsSub[$sub->status] ?? $sub->status }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ===== Document collection (current stage) or collapsed documents ===== --}}
        @if ($currentStage === 'document_collection' && $docProgress['total'] > 0)
            <div class="card ls-card">
                <div class="card-hd">
                    <div class="t"><span class="num">D</span>Document Collection</div>
                    <div class="actions">
                        <a href="{{ route('loans.documents', $loan) }}" class="btn sm">
                            <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            Collect
                        </a>
                    </div>
                </div>
                <div class="card-bd">
                    <div class="ls-progress ls-progress-lg"><div class="ls-progress-fill" style="width:{{ $docProgress['percentage'] }}%"></div></div>
                    <div class="ls-muted ls-progress-meta">
                        {{ $docProgress['resolved'] }}/{{ $docProgress['total'] }} collected ({{ $docProgress['percentage'] }}%)
                        @if ($docProgress['rejected'] > 0) — <span class="ls-text-red">{{ $docProgress['rejected'] }} rejected</span>@endif
                    </div>
                </div>
            </div>
        @elseif ($docProgress['total'] > 0)
            <div class="card ls-card ls-collapsible collapsed" data-target="#lsDocs">
                <div class="card-hd ls-toggle" role="button" tabindex="0">
                    <div class="t">
                        <span class="ls-chev" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>
                        </span>
                        <span class="ls-title">Documents</span>
                        <span class="ls-sub">{{ $docProgress['resolved'] }}/{{ $docProgress['total'] }} · {{ $docProgress['percentage'] }}%</span>
                    </div>
                </div>
                <div class="card-bd ls-collapse-body" id="lsDocs">
                    <div class="ls-doc-row">
                        <div>
                            <div class="ls-progress ls-progress-lg" style="max-width:240px;"><div class="ls-progress-fill" style="width:{{ $docProgress['percentage'] }}%"></div></div>
                            <div class="ls-muted ls-progress-meta">
                                {{ $docProgress['resolved'] }}/{{ $docProgress['total'] }} collected
                                @if ($docProgress['rejected'] > 0) — <span class="ls-text-red">{{ $docProgress['rejected'] }} rejected</span>@endif
                            </div>
                        </div>
                        <a href="{{ route('loans.documents', $loan) }}" class="btn sm">
                            <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            View All
                        </a>
                    </div>
                </div>
            </div>
        @endif

        {{-- ===== Source quotation (collapsible) ===== --}}
        @if ($loan->quotation_id)
            <div class="card ls-card ls-collapsible collapsed" data-target="#lsQuotation">
                <div class="card-hd ls-toggle" role="button" tabindex="0">
                    <div class="t">
                        <span class="ls-chev" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>
                        </span>
                        <span class="ls-title">Source Quotation</span>
                        <span class="ls-sub">#{{ $loan->quotation_id }}</span>
                    </div>
                </div>
                <div class="card-bd ls-collapse-body" id="lsQuotation">
                    <div class="ls-inline-row">
                        <span>Quotation #{{ $loan->quotation_id }} — Created {{ $loan->quotation?->created_at?->format('d M Y') }}</span>
                        <a href="{{ route('quotations.show', $loan->quotation_id) }}" class="btn sm">View</a>
                        @if ($loan->quotation?->pdf_filename && auth()->user()->hasPermission('download_pdf'))
                            <a href="{{ route('quotations.download-file', ['file' => $loan->quotation->pdf_filename]) }}" class="btn sm">Download PDF</a>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- ===== Notes (collapsible) ===== --}}
        @if ($loan->notes)
            <div class="card ls-card ls-collapsible collapsed" data-target="#lsNotes">
                <div class="card-hd ls-toggle" role="button" tabindex="0">
                    <div class="t">
                        <span class="ls-chev" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>
                        </span>
                        <span class="ls-title">Notes</span>
                    </div>
                </div>
                <div class="card-bd ls-collapse-body" id="lsNotes">
                    <p class="ls-prose">{{ $loan->notes }}</p>
                </div>
            </div>
        @endif

        {{-- ===== Remarks (collapsible + AJAX) ===== --}}
        <div class="card ls-card ls-collapsible collapsed" data-target="#lsRemarks">
            <div class="card-hd ls-toggle" role="button" tabindex="0">
                <div class="t">
                    <span class="ls-chev" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>
                    </span>
                    <span class="ls-title">Remarks</span>
                    <span class="badge orange" id="lsRemarksCount" style="display:none;"></span>
                </div>
            </div>
            <div class="card-bd ls-collapse-body" id="lsRemarks">
                @if (auth()->user()->hasPermission('add_remarks'))
                    <form id="lsRemarkForm" class="ls-remark-form" autocomplete="off">
                        <input type="text" id="lsRemarkInput" class="input" placeholder="Add a remark…" maxlength="5000">
                        <button type="submit" class="btn primary sm">
                            <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            Post
                        </button>
                    </form>
                @endif
                <div id="lsRemarksList"><span class="ls-muted">Loading…</span></div>
            </div>
        </div>
    </main>
@endsection

@push('page-scripts')
    <script>
        window.__LS = {
            loanId: {{ $loan->id }},
            updateStatusUrl: @json(route('loans.update-status', $loan)),
            remarksIndexUrl: @json(route('loans.remarks.index', $loan)),
            remarksStoreUrl: @json(route('loans.remarks.store', $loan)),
        };
    </script>
    <script src="{{ asset('newtheme/pages/loan-show.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
