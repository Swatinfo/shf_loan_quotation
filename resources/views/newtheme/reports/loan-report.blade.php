@extends('newtheme.layouts.app', ['pageKey' => 'reports'])

@section('title', 'Loan Report · Reports · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/loan-report.css') }}?v={{ config('app.shf_version') }}">
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
                    <span>Loans</span>
                </div>
                <h1>Loan Report</h1>
                <div class="sub">Sanctioned and disbursed loans by product, user, bank and branch.</div>
            </div>
        </div>
    </header>

    <main class="content">

        {{-- ========== Filters ========== --}}
        <div class="card lr-filters">
            <div class="card-hd">
                <div class="t">
                    <span class="lr-ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                    </span>
                    Filters
                </div>
                <div class="actions">
                    <button type="button" class="btn" id="lrClear">Clear</button>
                    <button type="button" class="btn primary" id="lrApply">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                        Apply
                    </button>
                </div>
            </div>
            <div class="card-bd">
                <div class="lr-grid">

                    <div class="lr-field">
                        <label for="filterStatus" class="lr-lbl">Status</label>
                        <select id="filterStatus" class="input lr-input">
                            <option value="sanctioned" selected>Sanctioned</option>
                            <option value="disbursed">Disbursed</option>
                        </select>
                    </div>

                    <div class="lr-field">
                        <label for="filterPeriod" class="lr-lbl">Period</label>
                        <select id="filterPeriod" class="input lr-input">
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

                    <div class="lr-field lr-custom-dates" style="display:none;">
                        <label for="filterDateFrom" class="lr-lbl">From Date</label>
                        <input type="text" id="filterDateFrom" class="input lr-input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                    </div>

                    <div class="lr-field lr-custom-dates" style="display:none;">
                        <label for="filterDateTo" class="lr-lbl">To Date</label>
                        <input type="text" id="filterDateTo" class="input lr-input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                    </div>

                    <div class="lr-field">
                        <label for="filterBank" class="lr-lbl">Bank</label>
                        <select id="filterBank" class="input lr-input">
                            <option value="">All Banks</option>
                            @foreach ($banks as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="lr-field">
                        <label for="filterProduct" class="lr-lbl">Product</label>
                        <select id="filterProduct" class="input lr-input">
                            <option value="">All Products</option>
                            @foreach ($products as $product)
                                <option value="{{ $product->id }}">{{ $product->bank?->name }} / {{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="lr-field">
                        <label for="filterBranch" class="lr-lbl">Branch</label>
                        <select id="filterBranch" class="input lr-input">
                            <option value="">All Branches</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="lr-field">
                        <label for="filterUser" class="lr-lbl">User</label>
                        <select id="filterUser" class="input lr-input">
                            <option value="">All Users</option>
                            @foreach ($users as $reportUser)
                                <option value="{{ $reportUser->id }}">{{ $reportUser->name }}</option>
                            @endforeach
                        </select>
                    </div>

                </div>
            </div>
        </div>

        {{-- ========== Totals strip ========== --}}
        <div class="lr-totals mt-4">
            <div class="lr-total-card">
                <div class="lr-total-lbl">Loans</div>
                <div class="lr-total-val" id="lrTotalCount">—</div>
            </div>
            <div class="lr-total-card">
                <div class="lr-total-lbl">Total Sanctioned</div>
                <div class="lr-total-val" id="lrTotalSanctioned">—</div>
            </div>
            <div class="lr-total-card">
                <div class="lr-total-lbl">Total Disbursed</div>
                <div class="lr-total-val" id="lrTotalDisbursed">—</div>
            </div>
        </div>

        {{-- ========== Table ========== --}}
        <div class="card mt-0 lr-panel">
            <div class="card-hd">
                <div class="t">Loans <span class="sub" id="lrModeLabel">sanctioned</span></div>
                <div class="actions"><span class="sub" id="lrRowCount">—</span></div>
            </div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="lrRows"><div class="lr-loader">Loading…</div></div>
            </div>
        </div>

    </main>
@endsection

@push('page-scripts')
    <script>
        window.__LR = {
            dataUrl: @json(route('reports.loans.data')),
        };
    </script>
    <script src="{{ asset('newtheme/pages/loan-report.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
