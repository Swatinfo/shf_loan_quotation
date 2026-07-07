@extends('newtheme.layouts.app')

@section('title', 'Quotations · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/quotations.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs"><a href="{{ route('dashboard') }}">Dashboard</a> · <span>Quotations</span></div>
                <h1>Quotations</h1>
                <div class="sub" id="qxStatsLine">Loading…</div>
            </div>
            <div class="head-actions">
                <a class="btn" href="{{ route('dashboard') }}">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                @if ($permissions['create_quotation'])
                    <a class="btn primary" href="{{ route('quotations.create') }}">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
                        New Quotation
                    </a>
                @endif
            </div>
        </div>
    </header>

    <main class="content">

        {{-- ===== Filters card (collapsed by default — click header to toggle) ===== --}}
        <div class="card mt-4 qx-filters-card collapsed" id="qxFiltersCard">
            <div class="card-hd qx-filters-toggle" id="qxFiltersToggle" role="button" tabindex="0" aria-expanded="false" aria-controls="qxFiltersBody">
                <div class="t">
                    <span class="qx-filter-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4a1 1 0 011-1h16a1 1 0 01.78 1.625l-6.28 7.85V20a1 1 0 01-1.45.894l-4-2A1 1 0 019 18v-5.525L2.22 4.625A1 1 0 013 4z"/></svg>
                    </span>
                    Filters
                    <span class="qx-active-count" id="qxActiveFilterCount">0</span>
                </div>
                <div class="actions">
                    <button type="button" class="btn sm" id="qxClear" onclick="event.stopPropagation();">Clear</button>
                    <button type="button" class="btn primary sm" id="qxFilter" onclick="event.stopPropagation();">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Apply
                    </button>
                    <span class="qx-chevron" aria-hidden="true">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </div>
            </div>
            <div class="card-bd qx-filters-body" id="qxFiltersBody">
                <div class="qx-filters">
                    <div class="qx-field">
                        <label class="lbl">Search</label>
                        <input type="text" id="qxSearch" class="input" placeholder="Customer name or filename…">
                    </div>
                    <div class="qx-field">
                        <label class="lbl">Type</label>
                        <select id="qxType" class="select">
                            <option value="">All Types</option>
                            <option value="proprietor">Proprietor</option>
                            <option value="partnership_llp">Partnership/LLP</option>
                            <option value="pvt_ltd">PVT LTD</option>
                            <option value="salaried">Salaried</option>
                        </select>
                    </div>
                    <div class="qx-field">
                        <label class="lbl">Status</label>
                        <select id="qxStatus" class="select">
                            <option value="not_cancelled">Active + On Hold</option>
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="all">All Statuses</option>
                        </select>
                    </div>
                    <div class="qx-field">
                        <label class="lbl">Loan Status</label>
                        <select id="qxLoanStatus" class="select">
                            <option value="not_converted">Not Converted</option>
                            <option value="converted">All Converted</option>
                            <option value="active">Loan Active</option>
                            <option value="completed">Loan Completed</option>
                            <option value="rejected">Loan Rejected</option>
                            <option value="all">All Quotations</option>
                        </select>
                    </div>
                    <div class="qx-field">
                        <label class="lbl">From</label>
                        <input type="text" id="qxDateFrom" class="input shf-datepicker-past" autocomplete="off" placeholder="dd/mm/yyyy">
                    </div>
                    <div class="qx-field">
                        <label class="lbl">To</label>
                        <input type="text" id="qxDateTo" class="input shf-datepicker-past" autocomplete="off" placeholder="dd/mm/yyyy">
                    </div>
                    @if ($permissions['view_all'] && count($users) > 0)
                        <div class="qx-field">
                            <label class="lbl">Created By</label>
                            <select id="qxCreatedBy" class="select">
                                <option value="">All Users</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="qx-field">
                        <label class="lbl">Per page</label>
                        <select id="qxPerPage" class="select">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Results card ===== --}}
        <div class="card mt-4 qx-results">
            <div class="card-hd">
                <div class="t"><span class="num">Q</span>Quotations <span class="sub" id="qxResultCount">—</span></div>
            </div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="qxRows">
                    <div class="qx-loader">Loading…</div>
                </div>
            </div>
            <div class="qx-pager" id="qxPager"></div>
        </div>

    </main>
@endsection

@push('page-scripts')
    <script>
        window.__QX = {
            dataUrl: @json(route('dashboard.quotation-data')),
            canViewAll: @json($permissions['view_all'] ?? false),
        };
    </script>
    <script src="{{ asset('newtheme/pages/quotations.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
