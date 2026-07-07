@extends('newtheme.layouts.app', ['pageKey' => 'users'])

@section('title', 'Users · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/users.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs"><a href="{{ route('dashboard') }}">Dashboard</a> · <span>Users</span></div>
                <h1>Users</h1>
                <div class="sub" id="uxStatsLine">Loading summary…</div>
            </div>
            @if ($permissions['create_users'])
                <div class="head-actions">
                    <a class="btn primary" href="{{ route('users.create') }}">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
                        New User
                    </a>
                </div>
            @endif
        </div>
    </header>

    <main class="content">

        <div class="card ux-filters-card">
            <div class="card-hd">
                <div class="t">
                    <span class="ux-filter-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4a1 1 0 011-1h16a1 1 0 01.78 1.625l-6.28 7.85V20a1 1 0 01-1.45.894l-4-2A1 1 0 019 18v-5.525L2.22 4.625A1 1 0 013 4z"/></svg>
                    </span>
                    Filters
                    <span class="ux-active-count" id="uxActiveFilterCount">0</span>
                </div>
                <div class="actions">
                    <button type="button" class="btn sm" id="uxClear">Clear</button>
                </div>
            </div>
            <div class="card-bd">
                <div class="ux-filters">
                    <div class="ux-field">
                        <label class="lbl">Search</label>
                        <input type="text" id="uxSearch" class="input" placeholder="Name, email, or phone…" autocomplete="off">
                    </div>
                    <div class="ux-field">
                        <label class="lbl">Role</label>
                        <select id="uxRole" class="select">
                            <option value="">All Roles</option>
                            @foreach ($roles as $r)
                                <option value="{{ $r->slug }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ux-field">
                        <label class="lbl">Status</label>
                        <select id="uxStatus" class="select">
                            <option value="">All Statuses</option>
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="ux-field">
                        <label class="lbl">Per page</label>
                        <select id="uxPerPage" class="select">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4 ux-results">
            <div class="card-hd">
                <div class="t"><span class="num">U</span>Users <span class="sub" id="uxResultCount">—</span></div>
            </div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="uxRows" class="d-desktop-only"><div class="ux-loader">Loading…</div></div>
                <div id="uxMobileRows" class="d-mobile-only"></div>
            </div>
            <div class="ux-pager" id="uxPager"></div>
        </div>

    </main>
@endsection

@push('page-scripts')
    <script>
        window.__UX = {
            dataUrl: @json(route('users.data')),
        };
    </script>
    <script src="{{ asset('newtheme/pages/users.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
