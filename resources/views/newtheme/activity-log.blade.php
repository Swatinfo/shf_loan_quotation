@extends('newtheme.layouts.app', ['pageKey' => 'activity-log'])

@section('title', 'Activity Log · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/activity-log.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <span>Activity Log</span>
                </div>
                <h1>Activity Log</h1>
                <div class="sub" id="alStatsLine">Loading summary…</div>
            </div>
        </div>
    </header>

    <main class="content">

        {{-- ========== Filters ========== --}}
        <div class="card al-filters">
            <div class="card-hd">
                <div class="t">
                    <span class="al-ic">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    Filters
                </div>
                <div class="actions">
                    <select id="alPerPage" class="select" style="width:auto;">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                    <button type="button" class="btn" id="alClear">Clear</button>
                    <button type="button" class="btn primary" id="alApply">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                        Apply
                    </button>
                </div>
            </div>
            <div class="card-bd">
                <div class="al-grid">

                    <div class="al-field al-search">
                        <label for="alSearch" class="al-lbl">Search</label>
                        <input type="text" id="alSearch" class="input" placeholder="User or action…" autocomplete="off">
                    </div>

                    <div class="al-field">
                        <label for="filterUser" class="al-lbl">User</label>
                        <select id="filterUser" class="input">
                            <option value="">All Users</option>
                            @foreach ($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="al-field">
                        <label for="filterAction" class="al-lbl">Action</label>
                        <select id="filterAction" class="input">
                            <option value="">All Actions</option>
                            @foreach ($actionTypes as $type)
                                <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="al-field">
                        <label for="alDateFrom" class="al-lbl">From</label>
                        <input type="text" id="alDateFrom" class="input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                    </div>

                    <div class="al-field">
                        <label for="alDateTo" class="al-lbl">To</label>
                        <input type="text" id="alDateTo" class="input shf-datepicker" placeholder="dd/mm/yyyy" autocomplete="off">
                    </div>

                </div>
            </div>
        </div>

        {{-- ========== Table panel ========== --}}
        <div class="card mt-4 al-results">
            <div class="card-hd">
                <div class="t"><span class="num">A</span>Activity <span class="sub" id="alResultCount">—</span></div>
            </div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="alRows" class="d-desktop-only"><div class="al-loader">Loading…</div></div>
                <div id="alMobileRows" class="d-mobile-only"></div>
            </div>
            <div class="al-pager" id="alPager"></div>
        </div>

    </main>
@endsection

@push('page-scripts')
    <script>
        window.__AL = {
            dataUrl: @json(route('activity-log.data')),
        };
    </script>
    <script src="{{ asset('newtheme/pages/activity-log.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
