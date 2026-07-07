@extends('newtheme.layouts.app', ['pageKey' => 'reports'])

@section('title', 'Management Summary · Reports · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/management.css') }}?v={{ config('app.shf_version') }}">
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
                    <span>Management</span>
                </div>
                <h1>Management Summary</h1>
                <div class="sub">Funnel, trends, branch &amp; advisor performance, and exceptions.</div>
            </div>
        </div>
    </header>

    <main class="content">

        {{-- ========== Filters ========== --}}
        <div class="card mg-filters">
            <div class="card-hd">
                <div class="t">
                    <span class="mg-ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    </span>
                    Filters
                </div>
                <div class="actions">
                    <button type="button" class="btn" id="mgClear">Clear</button>
                    <button type="button" class="btn primary" id="mgApply">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                        Apply
                    </button>
                </div>
            </div>
            <div class="card-bd">
                <div class="mg-grid">
                    <div class="mg-field">
                        <label for="filterPeriod" class="mg-lbl">Period</label>
                        <select id="filterPeriod" class="input mg-input">
                            <option value="current_month">Current Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="current_quarter">Current Quarter</option>
                            <option value="last_quarter">Last Quarter</option>
                            <option value="current_year" selected>Current Year</option>
                            <option value="last_year">Last Year</option>
                            <option value="all_time">All Time</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    <div class="mg-field mg-custom-dates" style="display:none;">
                        <label for="filterDateFrom" class="mg-lbl">From Date</label>
                        <input type="text" id="filterDateFrom" class="input mg-input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                    </div>
                    <div class="mg-field mg-custom-dates" style="display:none;">
                        <label for="filterDateTo" class="mg-lbl">To Date</label>
                        <input type="text" id="filterDateTo" class="input mg-input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                    </div>
                    <div class="mg-field">
                        <label for="filterBranch" class="mg-lbl">Branch</label>
                        <select id="filterBranch" class="input mg-input">
                            <option value="">All Branches</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- ========== Funnel ========== --}}
        <div class="card mt-4 mg-panel">
            <div class="card-hd"><div class="t">Conversion Funnel <span class="sub">quotation → loan → sanction → disbursement</span></div></div>
            <div class="card-bd">
                <div class="mg-funnel" id="mgFunnel"><div class="mg-loader">Loading…</div></div>
            </div>
        </div>

        {{-- ========== Monthly trend ========== --}}
        <div class="card mt-4 mg-panel">
            <div class="card-hd"><div class="t">Monthly Trend <span class="sub">last 12 months (period filter does not apply)</span></div></div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="mgTrend"><div class="mg-loader">Loading…</div></div>
            </div>
        </div>

        {{-- ========== Scoreboard ========== --}}
        <div class="card mt-4 mg-panel">
            <div class="card-hd"><div class="t">Branch &amp; Advisor Scoreboard <span class="sub">loans created in the period · click a branch to expand advisors</span></div></div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="mgScore"><div class="mg-loader">Loading…</div></div>
            </div>
        </div>

        {{-- ========== Exceptions ========== --}}
        <div class="mg-exceptions mt-4">
            <div class="card mg-panel">
                <div class="card-hd"><div class="t">Stages Stuck &gt; 14 Days</div></div>
                <div class="card-bd" style="padding:0;"><div id="mgExStages"><div class="mg-loader">Loading…</div></div></div>
            </div>
            <div class="card mg-panel">
                <div class="card-hd"><div class="t">Queries Open &gt; 7 Days</div></div>
                <div class="card-bd" style="padding:0;"><div id="mgExQueries"><div class="mg-loader">Loading…</div></div></div>
            </div>
            <div class="card mg-panel">
                <div class="card-hd"><div class="t">On Hold &gt; 30 Days</div></div>
                <div class="card-bd" style="padding:0;"><div id="mgExHolds"><div class="mg-loader">Loading…</div></div></div>
            </div>
        </div>

    </main>
@endsection

@push('page-scripts')
    <script>
        window.__MG = {
            dataUrl: @json(route('reports.management.data')),
        };
    </script>
    <script src="{{ asset('newtheme/pages/management.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
