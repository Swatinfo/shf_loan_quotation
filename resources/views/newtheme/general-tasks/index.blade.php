@extends('newtheme.layouts.app')

@section('title', 'Tasks · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/general-tasks.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs"><a href="{{ route('dashboard') }}">Dashboard</a> · <span>Tasks</span></div>
                <h1>Tasks</h1>
                <div class="sub" id="gtStatsLine">Loading…</div>
            </div>
            <div class="head-actions">
                {{-- Uses the site-wide create-task modal included by the layout --}}
                <button type="button" class="btn primary" data-shf-open="create-task">
                    <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
                    New Task
                </button>
            </div>
        </div>
    </header>

    <main class="content">

        {{-- ===== Filters card (collapsed by default) ===== --}}
        <div class="card mt-4 gt-filters-card collapsed" id="gtFiltersCard">
            <div class="card-hd gt-filters-toggle" id="gtFiltersToggle" role="button" tabindex="0" aria-expanded="false" aria-controls="gtFiltersBody">
                <div class="t">
                    <span class="gt-filter-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4a1 1 0 011-1h16a1 1 0 01.78 1.625l-6.28 7.85V20a1 1 0 01-1.45.894l-4-2A1 1 0 019 18v-5.525L2.22 4.625A1 1 0 013 4z"/></svg>
                    </span>
                    Filters
                    <span class="gt-active-count" id="gtActiveFilterCount">0</span>
                </div>
                <div class="actions">
                    <button type="button" class="btn sm" id="gtClear" onclick="event.stopPropagation();">Clear</button>
                    <button type="button" class="btn primary sm" id="gtFilter" onclick="event.stopPropagation();">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Apply
                    </button>
                    <span class="gt-chevron" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </div>
            </div>
            <div class="card-bd gt-filters-body" id="gtFiltersBody">
                <div class="gt-filters">
                    <div class="gt-field">
                        <label class="lbl">View</label>
                        <select id="gtView" class="input">
                            <option value="my_tasks_and_assigned">My &amp; Assigned</option>
                            <option value="my_tasks">Created by Me</option>
                            <option value="assigned_to_me">Assigned to Me</option>
                            @if ($isBdh)
                                <option value="my_branch">My Branch</option>
                            @endif
                            @if ($canViewAll)
                                <option value="all">All Tasks</option>
                            @endif
                        </select>
                    </div>
                    <div class="gt-field">
                        <label class="lbl">Status</label>
                        <select id="gtStatus" class="input">
                            <option value="active" selected>Active</option>
                            <option value="">All</option>
                            @foreach (\App\Models\GeneralTask::STATUS_LABELS as $key => $info)
                                <option value="{{ $key }}">{{ $info['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="gt-field">
                        <label class="lbl">Priority</label>
                        <select id="gtPriority" class="input">
                            <option value="">All</option>
                            @foreach (\App\Models\GeneralTask::PRIORITY_LABELS as $key => $info)
                                <option value="{{ $key }}">{{ $info['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="gt-field">
                        <label class="lbl">Search</label>
                        <input type="text" id="gtSearch" class="input" placeholder="Title, assignee, loan…">
                    </div>
                    <div class="gt-field">
                        <label class="lbl">Per page</label>
                        <select id="gtPerPage" class="input">
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
        <div class="card mt-4 gt-results">
            <div class="card-hd">
                <div class="t"><span class="num">T</span>Tasks <span class="sub" id="gtResultCount">—</span></div>
            </div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="gtRows"><div class="gt-loader">Loading…</div></div>
            </div>
            <div class="gt-pager" id="gtPager"></div>
        </div>

    </main>

    {{-- Modal markup has moved to newtheme/partials/create-task-modal.blade.php
         (loaded by the layout) so it's reachable from any page via the FAB or
         any `data-shf-open="create-task"` trigger. --}}
@endsection

@push('page-scripts')
    <script>
        window.__GT = {
            dataUrl: @json(route('general-tasks.data')),
            storeUrl: @json(route('general-tasks.store')),
            searchLoansUrl: @json(route('general-tasks.search-loans')),
            isBdh: @json((bool) $isBdh),
            canViewAll: @json((bool) $canViewAll),
        };
    </script>
    <script src="{{ asset('newtheme/pages/general-tasks.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
