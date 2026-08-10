@extends('newtheme.layouts.app', ['pageKey' => 'reports'])

@section('title', 'Loan Pipeline · Reports · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/pipeline.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <span>Reports</span>
                    <span class="sep">/</span>
                    <span>Pipeline</span>
                </div>
                <h1>Loan Pipeline</h1>
                <div class="sub">Where every loan stands right now — stage, owner, and how long it has been there.</div>
            </div>
        </div>
    </header>

    <main class="content">

        {{-- ========== Status chips ========== --}}
        <div class="pl-chips" id="plChips">
            @foreach (['all' => 'All', 'active' => 'Active', 'on_hold' => 'On Hold', 'completed' => 'Completed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'] as $key => $label)
                <button type="button" class="pl-chip {{ $key === 'active' ? 'is-active' : '' }}" data-status="{{ $key }}">
                    <span class="pl-chip-label">{{ $label }}</span>
                    <span class="pl-chip-count" data-count="{{ $key }}">—</span>
                    <span class="pl-chip-amount" data-amount="{{ $key }}"></span>
                    @if ($key === 'active')
                        <span class="pl-chip-sub" id="plQueuedNote"></span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- ========== Filters ========== --}}
        <div class="card pl-filters">
            <div class="card-hd">
                <div class="t">
                    <span class="pl-ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    </span>
                    Filters
                </div>
                <div class="actions">
                    <button type="button" class="btn" id="plExport">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0 0l-4-4m4 4l4-4"/></svg>
                        Export
                    </button>
                    <button type="button" class="btn" id="plClear">Clear</button>
                    <button type="button" class="btn primary" id="plApply">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                        Apply
                    </button>
                </div>
            </div>
            <div class="card-bd">
                <div class="pl-grid">
                    <div class="pl-field">
                        <label for="filterPeriod" class="pl-lbl">Period</label>
                        <select id="filterPeriod" class="input pl-input">
                            <option value="all_time" selected>All Time</option>
                            <option value="current_month">Current Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="current_quarter">Current Quarter</option>
                            <option value="last_quarter">Last Quarter</option>
                            <option value="current_year">Current Year</option>
                            <option value="last_year">Last Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    <div class="pl-field pl-custom-dates" style="display:none;">
                        <label for="filterDateFrom" class="pl-lbl">From Date</label>
                        <input type="text" id="filterDateFrom" class="input pl-input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                    </div>
                    <div class="pl-field pl-custom-dates" style="display:none;">
                        <label for="filterDateTo" class="pl-lbl">To Date</label>
                        <input type="text" id="filterDateTo" class="input pl-input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                    </div>
                    <div class="pl-field">
                        <label for="filterBank" class="pl-lbl">Bank</label>
                        <select id="filterBank" class="input pl-input">
                            <option value="">All Banks</option>
                            @foreach ($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pl-field">
                        <label for="filterProduct" class="pl-lbl">Product</label>
                        <select id="filterProduct" class="input pl-input">
                            <option value="">All Products</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->bank?->name }} / {{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($scope['type'] !== 'own')
                        <div class="pl-field">
                            <label for="filterBranch" class="pl-lbl">Branch</label>
                            <select id="filterBranch" class="input pl-input">
                                <option value="">All Branches</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pl-field">
                            <label for="filterUser" class="pl-lbl">User (Advisor)</label>
                            <select id="filterUser" class="input pl-input">
                                <option value="">All Users</option>
                                @foreach ($users as $reportUser)
                                    <option value="{{ $reportUser->id }}">{{ $reportUser->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="pl-field pl-active-only">
                        <label for="filterStage" class="pl-lbl">Stage</label>
                        <select id="filterStage" class="input pl-input">
                            <option value="">All Stages</option>
                            @foreach ($stages as $stage)
                                <option value="{{ $stage->stage_key }}">{{ $stage->parent_stage_key ? '— ' : '' }}{{ $stage->stage_name_en }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="pl-field pl-active-only">
                        <label for="filterStuck" class="pl-lbl">Stuck ≥ days</label>
                        <input type="number" id="filterStuck" class="input pl-input" min="1" placeholder="e.g. 7">
                    </div>
                </div>
            </div>
        </div>

        {{-- ========== Tabs ========== --}}
        <div class="tabs mt-4" style="border-bottom:none;padding:0;">
            <a href="#" class="tab active" data-panel="loans">Loans</a>
            <a href="#" class="tab" data-panel="workload">Workload by User</a>
        </div>

        <div class="card mt-0 pl-panel" data-panel-id="loans">
            <div class="card-hd">
                <div class="t">Pipeline <span class="sub" id="plModeLabel">active</span></div>
                <div class="actions"><span class="sub" id="plRowCount">—</span></div>
            </div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="plRows"><div class="pl-loader">Loading…</div></div>
            </div>
        </div>

        <div class="card mt-0 pl-panel" data-panel-id="workload" style="display:none;">
            <div class="card-hd">
                <div class="t">Workload by User <span class="sub">in-progress stages of active loans</span></div>
                <div class="actions"><span class="sub" id="plWorkCount">—</span></div>
            </div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="plWorkRows"><div class="pl-loader">Select the tab to load.</div></div>
            </div>
        </div>

    </main>
@endsection

@push('page-scripts')
    <script>
        window.__PL = {
            dataUrl: @json(route('reports.pipeline.data')),
            exportUrl: @json(route('reports.pipeline.export')),
        };
    </script>
    <script src="{{ asset('newtheme/pages/pipeline.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
