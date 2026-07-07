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
        <div class="kpi-strip" id="kpiStrip"></div>
        @php
            $countId = ['personal-tasks' => 'cnt-ptasks', 'tasks' => 'cnt-tasks', 'loans' => 'cnt-loans', 'dvr' => 'cnt-dvr', 'quotations' => 'cnt-quot'];
        @endphp
        <div class="tabs" data-tab-panel-group="dash">
            @foreach ($payload['tabs'] as $tab)
                @if ($tab['visible'])
                    <a class="tab {{ $payload['defaultTab'] === $tab['key'] ? 'active' : '' }}"
                       id="dash-tab-{{ $tab['key'] }}"
                       data-panel="{{ $tab['key'] }}">
                        {{ $tab['label'] }}
                        <span class="count" id="{{ $countId[$tab['key']] ?? 'cnt-'.$tab['key'] }}">0</span>
                    </a>
                @endif
            @endforeach
        </div>
    </header>

    <main class="content">
        <div class="grid c-main mt-4">
            {{-- ===== MAIN: tab panels ===== --}}
            <div data-tab-panel-group="dash">

                <div class="card" id="dash-panel-personal-tasks" data-panel-id="personal-tasks">
                    <div class="card-hd">
                        <div class="t"><span class="num">1</span>Personal Tasks <span class="sub" id="ptasksSub">loading…</span></div>
                        <div class="actions">
                            <a class="btn sm ghost" href="{{ route('general-tasks.index') }}">View all →</a>
                        </div>
                    </div>
                    <div class="card-bd" style="padding:0;overflow-x:auto;"><div id="rows-ptasks"></div></div>
                </div>

                <div class="card" id="dash-panel-tasks" data-panel-id="tasks" style="display:none;">
                    <div class="card-hd">
                        <div class="t"><span class="num">2</span>My Loan Tasks <span class="sub">stages assigned to me</span></div>
                        <div class="actions">
                            <select class="select" id="dashTaskStageFilter" style="height:28px;font-size:11.5px;width:auto;">
                                <option value="">All stages</option>
                            </select>
                            <a class="btn sm ghost" href="{{ route('loans.index') }}">View loans →</a>
                        </div>
                    </div>
                    <div class="card-bd" style="padding:0;overflow-x:auto;"><div id="rows-mytasks"></div></div>
                </div>

                <div class="card" id="dash-panel-loans" data-panel-id="loans" style="display:none;">
                    <div class="card-hd">
                        <div class="t"><span class="num">3</span>Loans <span class="sub">active + recently completed</span></div>
                        <div class="actions">
                            <a class="btn sm ghost" href="{{ route('loans.index') }}">View all →</a>
                        </div>
                    </div>
                    <div class="card-bd" style="padding:0;overflow-x:auto;"><div id="rows-loans"></div></div>
                </div>

                <div class="card" id="dash-panel-dvr" data-panel-id="dvr" style="display:none;">
                    <div class="card-hd">
                        <div class="t"><span class="num">4</span>Daily Visit Report <span class="sub" id="dvrSub"></span></div>
                        <div class="actions">
                            <a class="btn sm ghost" href="{{ route('dvr.index') }}">View all →</a>
                        </div>
                    </div>
                    <div class="card-bd" style="padding:0;overflow-x:auto;"><div id="rows-dvr"></div></div>
                </div>

                <div class="card" id="dash-panel-quotations" data-panel-id="quotations" style="display:none;">
                    <div class="card-hd">
                        <div class="t"><span class="num">5</span>Quotations <span class="sub" id="quotSub"></span></div>
                        <div class="actions">
                            <select class="select" id="dashQuotStatusFilter" style="height:28px;font-size:11.5px;width:auto;">
                                <option value="">All status</option>
                                <option value="active">Active</option>
                                <option value="on_hold">On hold</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <a class="btn sm ghost" href="{{ route('quotations.index') }}">View all →</a>
                        </div>
                    </div>
                    <div class="card-bd" style="padding:0;overflow-x:auto;"><div id="rows-quot"></div></div>
                </div>

                <div class="card mt-4">
                    <div class="card-hd">
                        <div class="t"><span class="num">6</span>Pipeline by stage <span class="sub">branch-wide</span></div>
                        <div class="actions"><a class="btn sm ghost" href="{{ route('loans.index') }}">Open loans →</a></div>
                    </div>
                    <div class="card-bd">
                        <div id="pipelineGrid" style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;"></div>
                    </div>
                </div>
            </div>

            {{-- ===== SIDEBAR ===== --}}
            <aside>
                <div class="card">
                    <div class="card-hd"><div class="t"><span class="num">A</span>Today's follow-ups</div><a class="btn sm ghost" href="{{ route('dvr.index') }}">All</a></div>
                    <div class="card-bd" style="padding:0;"><ul class="timeline" id="timelineList" style="padding:12px 18px;"></ul></div>
                </div>

                <div class="card mt-4">
                    <div class="card-hd"><div class="t"><span class="num">B</span>Open queries</div><span class="badge red sq" id="openQueryCount">0</span></div>
                    <div class="card-bd" style="padding:0;" id="openQueriesList"></div>
                </div>

                <div class="card mt-4">
                    <div class="card-hd"><div class="t"><span class="num">C</span>Field activity <span class="sub">today</span></div></div>
                    <div class="card-bd"><div class="strip" style="border:none;" id="fieldStrip"></div></div>
                </div>

                <div class="card mt-4">
                    <div class="card-hd"><div class="t"><span class="num">D</span>Bank mix MTD</div></div>
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
