@extends('newtheme.layouts.app', ['pageKey' => 'loans'])

@section('title', 'Loans · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/loans.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs"><a href="{{ route('dashboard') }}">Dashboard</a> · <span>Loans</span></div>
                <h1>Loans</h1>
                <div class="sub" id="lxStatsLine">
                    <strong>{{ number_format($stats['total']) }}</strong> total ·
                    {{ number_format($stats['active']) }} active ·
                    {{ number_format($stats['completed']) }} completed ·
                    {{ number_format($stats['this_month']) }} this month
                </div>
            </div>
            {{-- Loans are created by converting a quotation — no direct create action here. --}}
        </div>
    </header>

    <main class="content">

        {{-- ===== Filters card (collapsed by default — click header to toggle) ===== --}}
        <div class="card mt-4 lx-filters-card collapsed" id="lxFiltersCard">
            <div class="card-hd lx-filters-toggle" id="lxFiltersToggle" role="button" tabindex="0" aria-expanded="false" aria-controls="lxFiltersBody">
                <div class="t">
                    <span class="lx-filter-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4a1 1 0 011-1h16a1 1 0 01.78 1.625l-6.28 7.85V20a1 1 0 01-1.45.894l-4-2A1 1 0 019 18v-5.525L2.22 4.625A1 1 0 013 4z"/></svg>
                    </span>
                    Filters
                    <span class="lx-active-count" id="lxActiveFilterCount">0</span>
                </div>
                <div class="actions">
                    <button type="button" class="btn sm" id="lxClear" onclick="event.stopPropagation();">Clear</button>
                    <button type="button" class="btn primary sm" id="lxFilter" onclick="event.stopPropagation();">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        Apply
                    </button>
                    <span class="lx-chevron" aria-hidden="true">
                        <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </div>
            </div>
            <div class="card-bd lx-filters-body" id="lxFiltersBody">
                <div class="lx-filters">
                    <div class="lx-field">
                        <label class="lbl">Status</label>
                        <select id="lxStatus" class="select">
                            <option value="">All Status</option>
                            @foreach (\App\Models\LoanDetail::STATUS_LABELS as $key => $label)
                                <option value="{{ $key }}" {{ $key === 'active' ? 'selected' : '' }}>{{ $label['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="lx-field">
                        <label class="lbl">Type</label>
                        <select id="lxType" class="select">
                            <option value="">All Types</option>
                            @foreach (\App\Models\LoanDetail::CUSTOMER_TYPE_LABELS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (! $permissions['is_bank_employee'])
                        <div class="lx-field">
                            <label class="lbl">Bank</label>
                            <select id="lxBank" class="select">
                                <option value="">All Banks</option>
                                @foreach ($banks as $bank)
                                    <option value="{{ $bank->id }}">{{ $bank->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if ($permissions['is_admin_or_manager'])
                        <div class="lx-field">
                            <label class="lbl">Branch</label>
                            <select id="lxBranch" class="select">
                                <option value="">All Branches</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="lx-field">
                        <label class="lbl">Stage</label>
                        <select id="lxStage" class="select">
                            <option value="">All Stages</option>
                            @foreach ($stages as $stage)
                                <option value="{{ $stage->stage_key }}">{{ $stage->stage_name_en }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($permissions['is_admin_or_manager'])
                        <div class="lx-field">
                            <label class="lbl">Owner Role</label>
                            <select id="lxRole" class="select">
                                <option value="">All Roles</option>
                                @foreach ($roles as $r)
                                    <option value="{{ $r->slug }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    @if (! $permissions['is_bank_employee'])
                        <div class="lx-field">
                            <label class="lbl">Docket</label>
                            <select id="lxDocket" class="select">
                                <option value="">All</option>
                                <option value="overdue">Overdue</option>
                                <option value="due_today">Due Today</option>
                                <option value="due_soon">Due Soon (7 days)</option>
                                <option value="due_15">Due in 15 days</option>
                                <option value="due_month">Due in 1 month</option>
                                <option value="custom">Custom Date…</option>
                            </select>
                        </div>
                        <div class="lx-field lx-hidden" id="lxDocketDateWrap">
                            <label class="lbl">Docket By</label>
                            <input type="text" id="lxDocketDate" class="input shf-datepicker" autocomplete="off" placeholder="dd/mm/yyyy">
                        </div>
                    @endif
                    <div class="lx-field">
                        <label class="lbl">From</label>
                        <input type="text" id="lxDateFrom" class="input shf-datepicker" autocomplete="off" placeholder="dd/mm/yyyy">
                    </div>
                    <div class="lx-field">
                        <label class="lbl">To</label>
                        <input type="text" id="lxDateTo" class="input shf-datepicker" autocomplete="off" placeholder="dd/mm/yyyy">
                    </div>
                    <div class="lx-field">
                        <label class="lbl">Per page</label>
                        <select id="lxPerPage" class="select">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== Results card ===== --}}
        <div class="card mt-4 lx-results">
            <div class="card-hd">
                <div class="t"><span class="num">L</span>Loans <span class="sub" id="lxResultCount">—</span></div>
            </div>
            <div class="card-bd" style="padding:0;overflow-x:auto;">
                <div id="lxRows" class="d-desktop-only">
                    <div class="lx-loader">Loading…</div>
                </div>
                <div id="lxMobileRows" class="d-mobile-only"></div>
            </div>
            <div class="lx-pager" id="lxPager"></div>
        </div>

    </main>
@endsection

@push('page-scripts')
    <script>
        window.__LX = {
            dataUrl: @json(route('loans.data')),
            canSeeBank: @json(! $permissions['is_bank_employee']),
            canSeeBranch: @json($permissions['is_admin_or_manager']),
            canSeeRole: @json($permissions['is_admin_or_manager']),
        };
    </script>
    <script src="{{ asset('newtheme/pages/loans.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
