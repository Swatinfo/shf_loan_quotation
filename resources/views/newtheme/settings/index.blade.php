@extends('newtheme.layouts.app', ['pageKey' => 'settings'])

@section('title', 'Settings · SHF World')

@push('page-styles')
    {{-- Legacy SHF CSS is required because the tab panes (forms, tag inputs,
         sortable docs, badges) still render with `.shf-*` classes. The
         newtheme settings.css below heavily restyles those inside the
         `.settings-nt` scope so the page reads as a corporate system. --}}
    <link rel="stylesheet" href="{{ asset('newtheme/css/shf.css') }}?v={{ config('app.shf_version') }}">
    <link rel="stylesheet" href="{{ asset('newtheme/pages/settings.css') }}?v={{ config('app.shf_version') }}">
@endpush

@php
    $tabs = [
        'company' => ['label' => 'Company', 'perm' => 'edit_company_info', 'num' => '01'],
        'charges' => ['label' => 'IOM Stamp Paper', 'perm' => 'edit_charges', 'num' => '02'],
        'bank-charges' => ['label' => 'Bank Charges', 'perm' => 'edit_charges', 'num' => '03'],
        'gst' => ['label' => 'GST', 'perm' => 'edit_gst', 'num' => '04'],
        'services' => ['label' => 'Services', 'perm' => 'edit_services', 'num' => '05'],
        'tenures' => ['label' => 'Tenures', 'perm' => 'edit_tenures', 'num' => '06'],
        'documents' => ['label' => 'Documents', 'perm' => 'edit_documents', 'num' => '07'],
        'dvr' => ['label' => 'DVR', 'perm' => 'view_settings', 'num' => '08'],
        'quotation-reasons' => ['label' => 'Quotation Reasons', 'perm' => 'view_settings', 'num' => '09'],
        'permissions' => ['label' => 'Permissions', 'perm' => 'manage_permissions', 'num' => '10'],
    ];
    $activeTab = request('tab', 'company');
@endphp

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs">
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                    <span class="sep">/</span>
                    <span>Settings</span>
                </div>
                <h1>Quotation Settings</h1>
                <div class="sub">Configure company info, charges, documents, DVR vocabulary, and quotation reasons.</div>
            </div>
        </div>
    </header>

    <main class="content settings-nt">

        <div class="settings-nt-shell">
            {{-- Left rail — permanent section index --}}
            <aside class="settings-nt-rail">
                <div class="settings-nt-rail-label">Sections</div>
                <nav class="settings-nt-rail-nav">
                    @foreach ($tabs as $key => $info)
                        <a href="#{{ $key }}"
                           class="settings-nt-rail-item {{ $activeTab === $key ? 'active' : '' }}"
                           data-tab="{{ $key }}">
                            <span class="num">{{ $info['num'] }}</span>
                            <span class="lbl">{{ $info['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
            </aside>

            {{-- Content area --}}
            <section class="settings-nt-main">
                {{-- Mobile/tablet tab bar (rail collapses below 1024px).
                     Wrapped so the left/right scroll chevrons can sit on top
                     of the scroll container. --}}
                <div class="settings-nt-tabs-wrap">
                    <button type="button" class="settings-nt-tab-nav prev" id="settingsTabPrev" aria-label="Previous tab">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <div class="tabs settings-nt-tabs" id="settingsTabsStrip">
                        @foreach ($tabs as $key => $info)
                            <a href="#{{ $key }}"
                               class="tab {{ $activeTab === $key ? 'active' : '' }}"
                               data-tab="{{ $key }}">
                                {{ $info['label'] }}
                            </a>
                        @endforeach
                    </div>
                    <button type="button" class="settings-nt-tab-nav next" id="settingsTabNext" aria-label="Next tab">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>

                <div class="card settings-nt-card">
                    <div class="card-hd settings-nt-card-hd">
                        <div class="t">
                            <span class="num" id="settingsActiveNum">01</span>
                            <span id="settingsActiveLabel">Company</span>
                        </div>
                        <div class="actions">
                            <span class="settings-nt-breadcrumb">Settings / <span id="settingsActiveCrumb">Company</span></span>
                        </div>
                    </div>
                    <div class="card-bd settings-nt-card-bd">
                        @include('newtheme.settings._panes')
                    </div>
                </div>
            </section>
        </div>

    </main>

    {{-- Tab-label sync: when a tab is activated, update the card header label + number. --}}
    <script>
        window.__SETTINGS_TABS = @json(collect($tabs)->map(fn ($t) => ['label' => $t['label'], 'num' => $t['num']])->all());
    </script>
@endsection

@push('page-scripts')
    @include('newtheme.settings._scripts')
    <script>
        (function () {
            var map = window.__SETTINGS_TABS || {};
            function syncHeader(tab) {
                var info = map[tab];
                if (!info) return;
                var n = document.getElementById('settingsActiveNum');
                var l = document.getElementById('settingsActiveLabel');
                var c = document.getElementById('settingsActiveCrumb');
                if (n) n.textContent = info.num;
                if (l) l.textContent = info.label;
                if (c) c.textContent = info.label;
            }
            // Run once on load (in case a non-first tab is restored).
            var initial = (window.location.hash || '').replace('#', '')
                || (function () { try { return localStorage.getItem('shf_settings_active_tab') || ''; } catch (e) { return ''; } })()
                || @json($activeTab);
            syncHeader(initial);
            // Intercept clicks — the shared _scripts handler runs first, we just mirror the label.
            document.addEventListener('click', function (e) {
                var t = e.target.closest('[data-tab]');
                if (!t) return;
                syncHeader(t.getAttribute('data-tab'));
            });
        })();

        // Mobile tab strip: left/right scroll chevrons + hide-on-extreme.
        (function () {
            var strip = document.getElementById('settingsTabsStrip');
            var prev = document.getElementById('settingsTabPrev');
            var next = document.getElementById('settingsTabNext');
            if (!strip || !prev || !next) return;

            function scrollBy(delta) { strip.scrollBy({ left: delta, behavior: 'smooth' }); }

            function updateNavVisibility() {
                // Only relevant while the strip is visible (mobile/tablet).
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

            // Scroll the active tab into view on load/restore.
            function centreActive() {
                var active = strip.querySelector('.tab.active');
                if (!active) return;
                var target = active.offsetLeft - (strip.clientWidth - active.clientWidth) / 2;
                strip.scrollTo({ left: Math.max(0, target), behavior: 'smooth' });
            }

            // Re-centre whenever the shared tab switcher activates a tab
            // (clicks, hash restore, localStorage restore — all paths).
            document.addEventListener('settings:tab-activated', function () {
                updateNavVisibility();
                setTimeout(centreActive, 0);
            });

            // Initial paint — plus a couple of delayed recentres to cover the
            // case where the shared _scripts.blade.php handler hadn't run yet
            // when this IIFE executed (script ordering can vary).
            updateNavVisibility();
            centreActive();
            setTimeout(function () { updateNavVisibility(); centreActive(); }, 50);
            setTimeout(function () { updateNavVisibility(); centreActive(); }, 300);
        })();
    </script>
@endpush
