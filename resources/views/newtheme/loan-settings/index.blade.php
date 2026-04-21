@extends('newtheme.layouts.app', ['pageKey' => 'loan-settings'])

@section('title', 'Loan Settings · SHF World')

@push('page-styles')
    {{-- Legacy SHF CSS — `.shf-*` classes in the panes (forms, buttons,
         badges, tables). The loan-settings.css below restyles those
         inside the `.loan-settings-nt` scope so the page reads as a
         corporate system. Bootstrap CSS is intentionally NOT loaded —
         it would override newtheme base styles. The handful of Bootstrap
         `.collapse` rules used by the Add-form reveals are re-declared
         inside loan-settings.css. --}}
    <link rel="stylesheet" href="{{ asset('newtheme/css/shf.css') }}?v={{ config('app.shf_version') }}">
    <link rel="stylesheet" href="{{ asset('newtheme/pages/loan-settings.css') }}?v={{ config('app.shf_version') }}">
@endpush

@push('page-scripts')
    {{-- Bootstrap bundle — required for `bootstrap.Collapse.getOrCreateInstance`
         used by the Add-form show/hide logic in _scripts.blade.php. --}}
    <script src="{{ asset('newtheme/vendor/bootstrap/js/bootstrap.bundle.min.js') }}?v={{ config('app.shf_version') }}"></script>
@endpush

@php
    $tabs = [
        'locations' => ['label' => 'Locations', 'num' => '01'],
        'banks' => ['label' => 'Banks', 'num' => '02'],
        'branches' => ['label' => 'Branches', 'num' => '03'],
        'master-stages' => ['label' => 'Stage Master', 'num' => '04'],
        'products' => ['label' => 'Products & Stages', 'num' => '05'],
        'role-permissions' => ['label' => 'Role Permissions', 'num' => '06'],
    ];
    $activeTab = request('tab', 'locations');
@endphp

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <span>Loan Settings</span>
                </div>
                <h1>Loan Settings</h1>
                <div class="sub">Locations, banks, branches, stage master, product-stage overrides, and role permissions.</div>
            </div>
        </div>
    </header>

    <main class="content loan-settings-nt">

        <div class="loan-settings-nt-shell">
            {{-- Left rail — desktop only --}}
            <aside class="loan-settings-nt-rail">
                <div class="loan-settings-nt-rail-label">Sections</div>
                <nav class="loan-settings-nt-rail-nav">
                    @foreach ($tabs as $key => $info)
                        <a href="#{{ $key }}"
                           class="loan-settings-nt-rail-item {{ $activeTab === $key ? 'active' : '' }}"
                           data-tab="{{ $key }}">
                            <span class="num">{{ $info['num'] }}</span>
                            <span class="lbl">{{ $info['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </aside>

            {{-- Content area --}}
            <section class="loan-settings-nt-main">
                {{-- Mobile/tablet tab bar with scroll chevrons --}}
                <div class="loan-settings-nt-tabs-wrap">
                    <button type="button" class="loan-settings-nt-tab-nav prev" id="loanSettingsTabPrev" aria-label="Previous tab">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <div class="tabs loan-settings-nt-tabs" id="loanSettingsTabsStrip">
                        @foreach ($tabs as $key => $info)
                            <a href="#{{ $key }}"
                               class="tab {{ $activeTab === $key ? 'active' : '' }}"
                               data-tab="{{ $key }}">
                                {{ $info['label'] }}
                            </a>
                        @endforeach
                    </div>
                    <button type="button" class="loan-settings-nt-tab-nav next" id="loanSettingsTabNext" aria-label="Next tab">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

                <div class="card loan-settings-nt-card">
                    <div class="card-hd loan-settings-nt-card-hd">
                        <div class="t">
                            <span class="num" id="loanSettingsActiveNum">01</span>
                            <span id="loanSettingsActiveLabel">Locations</span>
                        </div>
                        <div class="actions">
                            <span class="loan-settings-nt-breadcrumb">Loan Settings / <span id="loanSettingsActiveCrumb">Locations</span></span>
                        </div>
                    </div>
                    <div class="card-bd loan-settings-nt-card-bd">
                        @include('newtheme.loan-settings._panes')
                    </div>
                </div>
            </section>
        </div>

    </main>

    {{-- Tab-label sync --}}
    <script>
        window.__LOAN_SETTINGS_TABS = @json(collect($tabs)->map(fn ($t) => ['label' => $t['label'], 'num' => $t['num']])->all());
    </script>
@endsection

@push('page-scripts')
    @include('newtheme.loan-settings._scripts')
    <script>
        // Sync the card header label/num with the active tab on every activation.
        (function () {
            var map = window.__LOAN_SETTINGS_TABS || {};
            function syncHeader(tab) {
                var info = map[tab];
                if (!info) return;
                var n = document.getElementById('loanSettingsActiveNum');
                var l = document.getElementById('loanSettingsActiveLabel');
                var c = document.getElementById('loanSettingsActiveCrumb');
                if (n) n.textContent = info.num;
                if (l) l.textContent = info.label;
                if (c) c.textContent = info.label;
            }
            var initial = (window.location.hash || '').replace('#', '')
                || (function () { try { return localStorage.getItem('shf_loan_settings_active_tab') || ''; } catch (e) { return ''; } })()
                || @json($activeTab);
            syncHeader(initial);
            document.addEventListener('loan-settings:tab-activated', function (e) {
                syncHeader(e.detail && e.detail.tab);
            });
        })();

        // Mobile tab strip: chevrons + auto-centre on activation.
        (function () {
            var strip = document.getElementById('loanSettingsTabsStrip');
            var prev = document.getElementById('loanSettingsTabPrev');
            var next = document.getElementById('loanSettingsTabNext');
            if (!strip || !prev || !next) return;

            function scrollBy(delta) { strip.scrollBy({ left: delta, behavior: 'smooth' }); }

            function updateNavVisibility() {
                var visible = window.getComputedStyle(strip).display !== 'none';
                var overflows = strip.scrollWidth > strip.clientWidth + 2;
                var showNav = visible && overflows;
                prev.style.display = showNav ? '' : 'none';
                next.style.display = showNav ? '' : 'none';
                if (!showNav) return;
                prev.disabled = strip.scrollLeft <= 1;
                next.disabled = strip.scrollLeft + strip.clientWidth >= strip.scrollWidth - 1;
            }

            prev.addEventListener('click', function () { scrollBy(-Math.round(strip.clientWidth * 0.75)); });
            next.addEventListener('click', function () { scrollBy(Math.round(strip.clientWidth * 0.75)); });

            strip.addEventListener('scroll', updateNavVisibility);
            window.addEventListener('resize', updateNavVisibility);

            function centreActive() {
                var active = strip.querySelector('.tab.active');
                if (!active) return;
                var target = active.offsetLeft - (strip.clientWidth - active.clientWidth) / 2;
                strip.scrollTo({ left: Math.max(0, target), behavior: 'smooth' });
            }

            document.addEventListener('loan-settings:tab-activated', function () {
                updateNavVisibility();
                setTimeout(centreActive, 0);
            });

            updateNavVisibility();
            centreActive();
            setTimeout(function () { updateNavVisibility(); centreActive(); }, 50);
            setTimeout(function () { updateNavVisibility(); centreActive(); }, 300);
        })();
    </script>
@endpush
