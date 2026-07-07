@extends('newtheme.layouts.app', ['pageKey' => 'customers'])

@section('title', 'Customers · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/customers.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs"><a href="{{ route('dashboard') }}">Dashboard</a> · <span>Customers</span></div>
                <h1>Customers</h1>
                <div class="sub" id="cxStatsLine">Loading summary…</div>
            </div>
            {{-- Customers are created when a quotation is converted to a loan. --}}
        </div>
    </header>

    <main class="content">

        <div class="card cx-filters-card">
            <div class="card-hd">
                <div class="t">
                    <span class="cx-filter-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    Search
                </div>
                <div class="actions">
                    <select id="cxPerPage" class="select" style="width:auto;">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
            <div class="card-bd">
                <div class="cx-search-wrap">
                    <input type="text" id="cxSearch" class="input" placeholder="Search by name, mobile, email, or PAN…" autocomplete="off">
                </div>
            </div>
        </div>

        <div class="card mt-4 cx-results">
            <div class="card-hd">
                <div class="t"><span class="num">C</span>Customers <span class="sub" id="cxResultCount">—</span></div>
            </div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="cxRows" class="d-desktop-only"><div class="cx-loader">Loading…</div></div>
                <div id="cxMobileRows" class="d-mobile-only"></div>
            </div>
            <div class="cx-pager" id="cxPager"></div>
        </div>

    </main>
@endsection

@push('page-scripts')
    <script>
        window.__CX = {
            dataUrl: @json(route('customers.data')),
            canEdit: @json($canEdit),
        };
    </script>
    <script src="{{ asset('newtheme/pages/customers.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
