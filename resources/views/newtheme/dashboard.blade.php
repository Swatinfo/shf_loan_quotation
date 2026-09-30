@extends('newtheme.layouts.app')

@section('title', 'Dashboard · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/dashboard.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs"><a href="{{ route('dashboard') }}">Dashboard</a></div>
                <h1 id="greeting">Good morning</h1>
                <div class="sub" id="dashSub">Loading summary…</div>
            </div>
            <div class="head-actions"></div>
        </div>
        {{-- KPI strip disabled — replaced by the card-styled tab bar below.
             Kept commented (not deleted) so it can be restored easily; the
             matching JS render block in dashboard.js is commented out too.
        <div class="kpi-strip" id="kpiStrip"></div>
        --}}

        @php
            $countId = [
                'personal-tasks' => 'cnt-ptasks',
                'tasks' => 'cnt-tasks',
                'loans' => 'cnt-loans',
                'dvr' => 'cnt-dvr',
                'quotations' => 'cnt-quot',
            ];
            // Per-tab icon + tone, mirroring the KPI-chip look (viewBox 0 0 24 24,
            // stroke SVGs). Tone drives the icon colour via .tab.tone-* in dashboard.css.
            $tabMeta = [
                'stage-breakdown' => ['tone' => 'blue', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                'personal-tasks' => ['tone' => 'accent', 'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                'tasks' => ['tone' => 'amber', 'icon' => 'M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                'loans' => ['tone' => 'blue', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                'dvr' => ['tone' => 'violet', 'icon' => 'M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z'],
                'quotations' => ['tone' => 'accent', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ];
        @endphp
        <div class="tabs" data-tab-panel-group="dash">
            @foreach ($payload['tabs'] as $tab)
                @if ($tab['visible'])
                    @php($meta = $tabMeta[$tab['key']] ?? null)
                    <a class="tab {{ $meta ? 'tone-' . $meta['tone'] : '' }} {{ $payload['defaultTab'] === $tab['key'] ? 'active' : '' }}"
                        id="dash-tab-{{ $tab['key'] }}" data-panel="{{ $tab['key'] }}">
                        @if ($meta)
                            <span class="tab-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="{{ $meta['icon'] }}"/></svg></span>
                        @endif
                        {{-- Number after the icon, before the label — mirrors the KPI chip order (icon → value → label). --}}
                        @if ($tab['key'] !== 'stage-breakdown')
                            <span class="count" id="{{ $countId[$tab['key']] ?? 'cnt-' . $tab['key'] }}">0</span>
                        @endif
                        {{ $tab['label'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </header>

    <main class="content">
        <div class="grid c-main mt-4">
            {{-- ===== MAIN: tab panels ===== --}}
            <div data-tab-panel-group="dash">

                <div class="card" id="dash-panel-stage-breakdown" data-panel-id="stage-breakdown" data-collapsible style="display:none;">
                    <div class="card-hd">
                        <div class="t"><span class="card-caret" aria-hidden="true">▾</span>Stage status breakdown <span class="sub" id="sbRange">—</span></div>
                        <div class="actions sb-filters">
                            <select id="sbUser" class="sb-select" style="display:none;"></select>
                            <select id="sbBranch" class="sb-select" style="display:none;"></select>
                            <select id="sbBank" class="sb-select"></select>
                            <select id="sbProduct" class="sb-select" disabled></select>
                            <select id="sbPeriod" class="sb-select"></select>
                            <input type="text" id="sbFrom" class="sb-select shf-datepicker sb-date"
                                placeholder="Start date" autocomplete="off" style="display:none;">
                            <input type="text" id="sbTo" class="sb-select shf-datepicker sb-date"
                                placeholder="End date" autocomplete="off" style="display:none;">
                            <button type="button" id="sbApply" class="btn sm primary sb-apply"
                                style="display:none;">Apply</button>
                        </div>
                    </div>
                    <div class="card-bd">
                        <div id="stageBreakdown" data-url="{{ route('dashboard.stage-breakdown') }}">
                            <div class="sb-loading text-xs text-muted">Loading…</div>
                        </div>
                    </div>
                </div>

                <div class="card" id="dash-panel-personal-tasks" data-panel-id="personal-tasks">
                    <div class="card-hd">
                        <div class="t"><span class="num">1</span>Personal Tasks <span class="sub"
                                id="ptasksSub">loading…</span></div>
                        <div class="actions">
                            <a class="btn sm ghost" href="{{ route('general-tasks.index') }}">View all →</a>
                        </div>
                    </div>
                    <div class="card-bd" style="padding:0;overflow-x:auto;">
                        <div id="rows-ptasks"></div>
                    </div>
                </div>

                <div class="card" id="dash-panel-tasks" data-panel-id="tasks" style="display:none;">
                    <div class="card-hd">
                        <div class="t"><span class="num">2</span>My Loan Tasks <span class="sub">stages assigned
                                to me</span></div>
                        <div class="actions">
                            <select class="select" id="dashTaskStageFilter"
                                style="height:28px;font-size:11.5px;width:auto;">
                                <option value="">All stages</option>
                            </select>
                            <a class="btn sm ghost" href="{{ route('loans.index') }}">View loans →</a>
                        </div>
                    </div>
                    <div class="card-bd" style="padding:0;overflow-x:auto;">
                        <div id="rows-mytasks"></div>
                    </div>
                </div>

                <div class="card" id="dash-panel-loans" data-panel-id="loans" style="display:none;">
                    <div class="card-hd">
                        <div class="t"><span class="num">3</span>Loans <span class="sub">active + recently
                                completed</span></div>
                        <div class="actions">
                            <a class="btn sm ghost" href="{{ route('loans.index') }}">View all →</a>
                        </div>
                    </div>
                    <div class="card-bd" style="padding:0;overflow-x:auto;">
                        <div id="rows-loans"></div>
                    </div>
                </div>

                <div class="card" id="dash-panel-dvr" data-panel-id="dvr" style="display:none;">
                    <div class="card-hd">
                        <div class="t"><span class="num">4</span>Daily Visit Report <span class="sub"
                                id="dvrSub"></span></div>
                        <div class="actions">
                            <a class="btn sm ghost" href="{{ route('dvr.index') }}">View all →</a>
                        </div>
                    </div>
                    <div class="card-bd" style="padding:0;overflow-x:auto;">
                        <div id="rows-dvr"></div>
                    </div>
                </div>

                <div class="card" id="dash-panel-quotations" data-panel-id="quotations" style="display:none;">
                    <div class="card-hd">
                        <div class="t"><span class="num">5</span>Quotations <span class="sub"
                                id="quotSub"></span></div>
                        <div class="actions">
                            <select class="select" id="dashQuotStatusFilter"
                                style="height:28px;font-size:11.5px;width:auto;">
                                <option value="">All status</option>
                                <option value="active">Active</option>
                                <option value="on_hold">On hold</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <a class="btn sm ghost" href="{{ route('quotations.index') }}">View all →</a>
                        </div>
                    </div>
                    <div class="card-bd" style="padding:0;overflow-x:auto;">
                        <div id="rows-quot"></div>
                    </div>
                </div>

                <div class="card mt-4" data-collapsible>
                    <div class="card-hd">
                        <div class="t"><span class="card-caret" aria-hidden="true">▾</span><span
                                class="num">6</span>Pipeline by stage <span class="sub">branch-wide</span></div>
                        <div class="actions"><a class="btn sm ghost" href="{{ route('loans.index') }}">Open loans →</a>
                        </div>
                    </div>
                    <div class="card-bd">
                        <div id="pipelineGrid" style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;"></div>
                    </div>
                </div>

            </div>

            {{-- ===== SIDEBAR ===== --}}
            <aside>
                <div class="card" data-collapsible>
                    <div class="card-hd">
                        <div class="t"><span class="card-caret" aria-hidden="true">▾</span><span
                                class="num">A</span>Today's follow-ups</div><a class="btn sm ghost"
                            href="{{ route('dvr.index') }}">All</a>
                    </div>
                    <div class="card-bd" style="padding:0;">
                        <ul class="timeline" id="timelineList" style="padding:12px 18px;"></ul>
                    </div>
                </div>

                <div class="card mt-4" data-collapsible>
                    <div class="card-hd">
                        <div class="t"><span class="card-caret" aria-hidden="true">▾</span><span
                                class="num">B</span>Open queries</div><span class="badge red sq"
                            id="openQueryCount">0</span>
                    </div>
                    <div class="card-bd" style="padding:0;" id="openQueriesList"></div>
                </div>

                <div class="card mt-4" data-collapsible>
                    <div class="card-hd">
                        <div class="t"><span class="card-caret" aria-hidden="true">▾</span><span
                                class="num">C</span>Field activity <span class="sub">today</span></div>
                    </div>
                    <div class="card-bd">
                        <div class="strip" style="border:none;" id="fieldStrip"></div>
                    </div>
                </div>

                <div class="card mt-4" data-collapsible>
                    <div class="card-hd">
                        <div class="t"><span class="card-caret" aria-hidden="true">▾</span><span
                                class="num">D</span>Bank mix MTD</div>
                    </div>
                    <div class="card-bd" style="display:flex;gap:20px;align-items:center;">
                        <svg class="donut" viewBox="0 0 42 42" id="bankDonut"></svg>
                        <div style="flex:1;" id="bankLegend"></div>
                    </div>
                </div>
            </aside>
        </div>
    </main>
@endsection

@push('page-scripts')
    <script>
        window.__DASHBOARD = @json($payload);
    </script>
    <script src="{{ asset('newtheme/pages/dashboard.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush
