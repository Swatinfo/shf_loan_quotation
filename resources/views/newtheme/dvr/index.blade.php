@extends('newtheme.layouts.app', ['pageKey' => 'dvr'])

@section('title', 'Daily Visit Reports · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/dvr.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs"><a href="{{ route('dashboard') }}">Dashboard</a> · <span>Daily Visit Reports</span></div>
                <h1>Daily Visit Reports</h1>
                <div class="sub" id="dxStatsLine">Loading summary…</div>
            </div>
            <div class="head-actions">
                <a class="btn" href="{{ route('dashboard') }}">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </a>
                @if ($canCreate)
                    <button type="button" class="btn primary" data-shf-open="create-dvr">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
                        New Visit
                    </button>
                @endif
            </div>
        </div>
    </header>

    <main class="content">

        {{-- ===== Filters card (collapsed by default) ===== --}}
        <div class="card dx-filters-card collapsed" id="dxFiltersCard">
            <div class="card-hd dx-filters-toggle" id="dxFiltersToggle" role="button" tabindex="0" aria-expanded="false" aria-controls="dxFiltersBody">
                <div class="t">
                    <span class="dx-filter-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4a1 1 0 011-1h16a1 1 0 01.78 1.625l-6.28 7.85V20a1 1 0 01-1.45.894l-4-2A1 1 0 019 18v-5.525L2.22 4.625A1 1 0 013 4z"/></svg>
                    </span>
                    Filters
                    <span class="dx-active-count" id="dxActiveFilterCount">0</span>
                </div>
                <div class="actions">
                    <button type="button" class="btn sm" id="dxClear" onclick="event.stopPropagation();">Clear</button>
                    <button type="button" class="btn primary sm" id="dxFilter" onclick="event.stopPropagation();">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Apply
                    </button>
                    <span class="dx-chevron" aria-hidden="true">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </div>
            </div>
            <div class="card-bd dx-filters-body" id="dxFiltersBody">
                <div class="dx-filters">
                    <div class="dx-field">
                        <label class="lbl">View</label>
                        <select id="dxView" class="select">
                            <option value="my_visits">My Visits</option>
                            @if ($isBdh || $isBranchManager)
                                <option value="my_branch">My Branch</option>
                            @endif
                            @if ($canViewAll)
                                <option value="all">All</option>
                            @endif
                        </select>
                    </div>
                    <div class="dx-field">
                        <label class="lbl">Search</label>
                        <input type="text" id="dxSearch" class="input" placeholder="Contact, phone, loan…">
                    </div>
                    <div class="dx-field">
                        <label class="lbl">Contact Type</label>
                        <select id="dxContactType" class="select">
                            <option value="">All Types</option>
                            @foreach ($contactTypes as $t)
                                <option value="{{ $t['key'] }}">{{ $t['label_en'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="dx-field">
                        <label class="lbl">Purpose</label>
                        <select id="dxPurpose" class="select">
                            <option value="">All Purposes</option>
                            @foreach ($purposes as $p)
                                <option value="{{ $p['key'] }}">{{ $p['label_en'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="dx-field">
                        <label class="lbl">Follow-up</label>
                        <select id="dxFollowUp" class="select">
                            <option value="active">Active (open)</option>
                            <option value="pending">Pending</option>
                            <option value="overdue">Overdue</option>
                            <option value="done">Done</option>
                            <option value="all">All</option>
                        </select>
                    </div>
                    @if ($canViewAll || $isBdh || $isBranchManager)
                        <div class="dx-field">
                            <label class="lbl">User</label>
                            <select id="dxUser" class="select">
                                <option value="">All Users</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="dx-field">
                        <label class="lbl">From</label>
                        <input type="text" id="dxDateFrom" class="input shf-datepicker" autocomplete="off" placeholder="dd/mm/yyyy">
                    </div>
                    <div class="dx-field">
                        <label class="lbl">To</label>
                        <input type="text" id="dxDateTo" class="input shf-datepicker" autocomplete="off" placeholder="dd/mm/yyyy">
                    </div>
                    <div class="dx-field">
                        <label class="lbl">Per page</label>
                        <select id="dxPerPage" class="select">
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
        <div class="card dx-results">
            <div class="card-hd">
                <div class="t"><span class="num">D</span>Visits <span class="sub" id="dxResultCount">—</span></div>
            </div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="dxRows" class="d-desktop-only"><div class="dx-loader">Loading…</div></div>
                <div id="dxMobileRows" class="d-mobile-only"></div>
            </div>
            <div class="dx-pager" id="dxPager"></div>
        </div>

    </main>
@endsection

@push('page-scripts')
    <script>
        window.__DX = {
            dataUrl: @json(route('dvr.data')),
            showUrlBase: @json(url('/dvr')),
            canEditBase: @json($canCreate),
        };
    </script>
    <script src="{{ asset('newtheme/pages/dvr.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
