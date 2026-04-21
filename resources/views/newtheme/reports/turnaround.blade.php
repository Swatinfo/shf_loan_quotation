@extends('newtheme.layouts.app', ['pageKey' => 'reports'])

@section('title', 'Turnaround Time · Reports · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/turnaround.css') }}?v={{ config('app.shf_version') }}">
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
                    <span>Turnaround</span>
                </div>
                <h1>Turnaround Time</h1>
                <div class="sub">How long completed loans take end-to-end and per stage.</div>
            </div>
        </div>
    </header>

    <main class="content">

        {{-- ========== Filters ========== --}}
        <div class="card tat-filters">
            <div class="card-hd">
                <div class="t">
                    <span class="tat-ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    </span>
                    Filters
                </div>
                <div class="actions">
                    <button type="button" class="btn" id="tatClear">Clear</button>
                    <button type="button" class="btn primary" id="tatApply">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                        Apply
                    </button>
                </div>
            </div>
            <div class="card-bd">
                <div class="tat-grid">

                    <div class="tat-field">
                        <label for="filterPeriod" class="tat-lbl">Period</label>
                        <select id="filterPeriod" class="input tat-input">
                            <option value="current_month" selected>Current Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="current_quarter">Current Quarter</option>
                            <option value="last_quarter">Last Quarter</option>
                            <option value="current_year">Current Year</option>
                            <option value="last_year">Last Year</option>
                            <option value="all_time">All Time</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>

                    <div class="tat-field tat-custom-dates" style="display:none;">
                        <label for="filterDateFrom" class="tat-lbl">From Date</label>
                        <input type="text" id="filterDateFrom" class="input tat-input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                    </div>

                    <div class="tat-field tat-custom-dates" style="display:none;">
                        <label for="filterDateTo" class="tat-lbl">To Date</label>
                        <input type="text" id="filterDateTo" class="input tat-input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                    </div>

                    <div class="tat-field">
                        <label for="filterBank" class="tat-lbl">Bank</label>
                        <select id="filterBank" class="input tat-input">
                            <option value="">All Banks</option>
                            @foreach ($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="tat-field">
                        <label for="filterProduct" class="tat-lbl">Product</label>
                        <select id="filterProduct" class="input tat-input">
                            <option value="">All Products</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->bank?->name }} / {{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if ($scope['type'] !== 'self')
                        <div class="tat-field">
                            <label for="filterBranch" class="tat-lbl">Branch</label>
                            <select id="filterBranch" class="input tat-input">
                                <option value="">All Branches</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="tat-field">
                            <label for="filterUser" class="tat-lbl">User</label>
                            <select id="filterUser" class="input tat-input">
                                <option value="">All Users</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" id="filterBranch" value="">
                        <input type="hidden" id="filterUser" value="">
                    @endif

                    <div class="tat-field tat-stage-wrap" style="display:none;">
                        <label for="filterStage" class="tat-lbl">Stage</label>
                        <select id="filterStage" class="input tat-input">
                            <option value="">All Stages</option>
                            @foreach ($stages as $stage)
                                <option value="{{ $stage->stage_key }}">{{ $stage->stage_name_en }}</option>
                            @endforeach
                        </select>
                    </div>

                </div>
            </div>
        </div>

        {{-- ========== Tabs ========== --}}
        <div class="tabs mt-4" style="border-bottom:none;padding:0;">
            <a href="#" class="tab active" id="tatTabOverall" data-panel="overall">
                Overall TAT
            </a>
            <a href="#" class="tab" id="tatTabStage" data-panel="stagewise">
                Stage-wise TAT
            </a>
        </div>

        {{-- ========== Overall panel ========== --}}
        <div class="card mt-0 tat-panel" id="tatPanelOverall" data-panel-id="overall">
            <div class="card-hd">
                <div class="t">Overall Turnaround Time <span class="sub">completed loans, by advisor</span></div>
                <div class="actions"><span class="sub" id="tatOverallCount">—</span></div>
            </div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="tatOverallRows"><div class="tat-loader">Loading…</div></div>
            </div>
        </div>

        {{-- ========== Stage-wise panel ========== --}}
        <div class="card mt-0 tat-panel" id="tatPanelStage" data-panel-id="stagewise" style="display:none;">
            <div class="card-hd">
                <div class="t">Stage-wise Turnaround Time <span class="sub">per user, per bank</span></div>
                <div class="actions"><span class="sub" id="tatStageCount">—</span></div>
            </div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="tatStageRows"><div class="tat-loader">Select the tab to load.</div></div>
            </div>
        </div>

    </main>
@endsection

@push('page-scripts')
    <script>
        window.__TAT = {
            dataUrl: @json(route('reports.turnaround.data')),
        };
    </script>
    <script src="{{ asset('newtheme/pages/turnaround.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
