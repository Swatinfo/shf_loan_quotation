<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'SHF World'))</title>

    {{-- Favicons --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon/favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">

    {{-- Newtheme shared assets (read-only — never edit these) --}}
    @php
        $v = config('app.shf_version');
    @endphp
    <link rel="stylesheet" href="{{ asset('newtheme/assets/shf.css') }}?v={{ $v }}">
    <link rel="stylesheet" href="{{ asset('newtheme/assets/shf-extras.css') }}?v={{ $v }}">
    <link rel="stylesheet" href="{{ asset('newtheme/assets/shf-workflow.css') }}?v={{ $v }}">

    {{-- Shared modal styles (create-task, create-dvr) so they render
         consistently on every page, not just the tasks index. --}}
    <link rel="stylesheet" href="{{ asset('newtheme/assets/shf-modals.css') }}?v={{ $v }}">

    {{-- Layout-wide overrides for the Blade-rendered topbar.
         shf-workflow.css turns the .nav-dd background white but keeps the
         .nav-dd-item color from shf-extras.css as white-on-white. We retint
         the items for the light dropdown here, since we cannot edit those files. --}}
    <style>
        /* Pin the topbar to the viewport so it stays visible while the page
           scrolls. Reserve `padding-top` on .app so the first row of content
           sits below the bar. */
        .topbar {
            position: fixed !important;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }

        /* Collapse the gap between the page-header and the first card on every
           newtheme page. Three stacked sources produce the visible gray strip:
             (1) `.content { padding: 1% 1% }` in shf.css   — ~19px on wide
             (2) `.content { padding: 20px 20px 60px }` at ≤1280 in shf-extras
             (3) `.mt-4 { margin-top: 16px }` on the first child
           Constrain the content's top padding to a small consistent value AND
           zero the first-child top margin, so the first card gets a single,
           calibrated 8px of breathing room instead of ~35px of stacked spacing.

           Dashboard extra: each tab panel sits two levels deeper (grid > group
           > panel), so any per-panel margin can drift the gap from tab to tab.
           Zero the top margin on every `[data-panel-id]` panel inside a tab
           group — sibling non-panel cards (e.g. "Pipeline by stage") keep
           their own mt-4 because they don't carry `data-panel-id`. */
        main.content {
            padding-top: 8px !important;
            /* The floating Create FAB sits at right:24px / bottom:24px with a
               52px-diameter button. Reserve breathing room below page content
               so action bars (Cancel/Save, form actions, delete buttons) aren't
               covered by the FAB. On ≤1024px the mobile bottom-nav (~56px) +
               FAB stack need more; that's handled in the media query below. */
            padding-bottom: 96px !important;
        }
        main.content > *:first-child {
            margin-top: 0 !important;
        }
        @media (max-width: 1024px) {
            main.content { padding-bottom: 140px !important; }
        }
        main.content [data-tab-panel-group] > [data-panel-id] {
            margin-top: 0 !important;
        }

        .app {
            padding-top: 60px;
            /* matches .topbar height */
        }

        @media (max-width: 599px) {
            .app {
                padding-top: 56px;
            }
        }

        /* Settings has a dropdown wrapper that doesn't fill topbar height by
           default — making the hover background shorter than sibling nav items.
           Stretch the wrap so its child .nav-item picks up height: 100%. */
        .topbar .nav-primary .nav-dd-wrap {
            height: 100%;
            align-self: stretch;
        }

        .topbar .nav-primary .nav-dd-wrap>.nav-item {
            height: 100%;
        }

        /* User-chip dropdown — anchors to the chip's right edge (chip is on the
           topbar's right). The .nav-dd CSS defaults to left:0 which would push
           it offscreen to the right, so override here. */
        .topbar .user-chip-wrap {
            position: relative;
            height: 100%;
            display: inline-flex;
            align-items: center;
        }

        .topbar .user-chip-wrap>.user-chip {
            cursor: pointer;
        }

        .topbar .user-chip-wrap>.user-chip-dd {
            left: auto !important;
            right: 0 !important;
            min-width: 200px;
        }

        .topbar .user-chip-wrap:hover>.user-chip-dd,
        .topbar .user-chip-wrap.open>.user-chip-dd {
            opacity: 1;
            visibility: visible;
            transform: translateY(6px);
            pointer-events: auto;
        }

        .topbar .user-chip-wrap>.user-chip-dd .nav-dd-item {
            display: flex;
            align-items: center;
        }

        /* FAB menu items: icon-first, then label, both left-aligned. */
        .shf-fab-item {
            flex-direction: row !important;
            justify-content: flex-start !important;
            text-align: left;
        }

        .shf-fab-item .shf-fab-item-icon {
            order: 0;
            /* margin-right: 10px; */
            flex: 0 0 auto;
        }

        .shf-fab-item .shf-fab-item-label {
            order: 1;
            flex: 1 1 auto;
            text-align: left !important;
        }

        /* Settings dropdown panel — opaque accent-tinted surface that visually
           matches the topbar (#f16c4559 over white renders as roughly #fbdcd0).
           Using a fully opaque colour so page content doesn't bleed through.  */
        .topbar .nav-dd {
            background: #fbdcd0 !important;
            border: 1px solid rgba(241, 90, 41, 0.40);
            box-shadow: 0 10px 28px rgba(0, 0, 0, 0.20);
        }

        .topbar .nav-dd .nav-dd-item {
            color: var(--ink) !important;
            font-weight: 500;
        }

        .topbar .nav-dd .nav-dd-item:hover {
            background: rgba(255, 255, 255, 0.65);
            color: var(--ink) !important;
        }

        .topbar .nav-dd .nav-dd-item.active {
            background: rgba(255, 255, 255, 0.80);
            color: var(--accent-deep, #c0392b) !important;
        }
    </style>

    {{-- Page-specific stylesheet (one file per Blade page) --}}
    @stack('page-styles')

    {{-- Vendor (jQuery first, datepicker, sortable, sweetalert) --}}
    <link rel="stylesheet"
        href="{{ asset('newtheme/vendor/datepicker/css/bootstrap-datepicker3.min.css') }}?v={{ $v }}">
    <link rel="stylesheet"
        href="{{ asset('newtheme/vendor/sweetalert2/sweetalert2.min.css') }}?v={{ $v }}">
    <script src="{{ asset('newtheme/vendor/jquery/jquery-3.7.1.min.js') }}?v={{ $v }}"></script>
    <script src="{{ asset('newtheme/vendor/datepicker/js/bootstrap-datepicker.min.js') }}?v={{ $v }}"></script>
    <script src="{{ asset('newtheme/vendor/sortablejs/Sortable.min.js') }}?v={{ $v }}"></script>
    <script src="{{ asset('newtheme/vendor/sweetalert2/sweetalert2.all.min.js') }}?v={{ $v }}"></script>

    {{-- SHF core helpers — a focused subset of shf-app.js (validateForm,
         formatIndianNumber, auto-clear .is-invalid, textarea auto-expand,
         password toggle, toast dismiss, saved-msg fade, confirm-delete).
         The full shf-app.js ships a mobile FAB binding that double-bound with
         this layout's own FAB wiring; this trimmed file skips that block. --}}
    <script src="{{ asset('newtheme/assets/shf-newtheme.js') }}?v={{ $v }}"></script>

    {{-- Google Fonts URL uses URL-encoded `%40` instead of literal `@` so Blade
         doesn't interpret `wght@400` as a directive (it would short-circuit
         compilation of the rest of this template). --}}
    <link
        href="https://fonts.googleapis.com/css2?family=Jost:wght%40400;500;600;700&family=Archivo:wght%40400;500;600;700&family=JetBrains+Mono:wght%40400;500&display=swap"
        rel="stylesheet">
</head>

<body>
    <div class="app">
        @include('newtheme.partials.header', ['pageKey' => $pageKey ?? ''])

        @yield('content')
    </div>

    @auth
        {{-- Site-wide Create-Task modal — any FAB / button / dispatchEvent can open it --}}
        @include('newtheme.partials.create-task-modal')

        {{-- Site-wide Create-DVR modal — gated by create_dvr permission --}}
        @if (auth()->user()->hasPermission('create_dvr'))
            @include('newtheme.partials.create-dvr-modal')
        @endif

        {{-- Mobile bottom nav (visible ≤1024px via CSS) + More bottom-sheet --}}
        @include('newtheme.partials.bottom-nav')

        {{-- Floating Create FAB (Quotation / Task / Visit), gated per-permission --}}
        @include('newtheme.partials.fab')
    @endauth

    {{-- ─────────────────────────────────────────────────────────────────────
     PWA install prompt + Web Push opt-in banner.
     Ported from resources/views/layouts/app.blade.php so the newtheme also
     surfaces these prompts on every authenticated page (not only dashboard).
     ───────────────────────────────────────────────────────────────────── --}}
    @auth
        {{-- PWA Install Banner --}}
        <div id="installBanner"
            style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:9998;width:calc(100% - 32px);max-width:400px;">
            <div
                style="background:linear-gradient(135deg,#3a3536 0%,#4a4546 100%);border-radius:16px;padding:16px 20px;box-shadow:0 8px 32px rgba(0,0,0,0.25);border:1px solid rgba(241,90,41,0.3);display:flex;align-items:center;gap:14px;">
                <img src="{{ asset('images/icon-192x192.png') }}" alt="SHF"
                    style="width:48px;height:48px;border-radius:12px;flex-shrink:0;">
                <div style="flex:1;min-width:0;">
                    <div style="color:#fff;font-weight:700;font-size:0.9rem;font-family:'Jost',sans-serif;">Install SHF
                        World</div>
                    <div style="color:rgba(255,255,255,0.6);font-size:0.75rem;margin-top:2px;">Quick access from your home
                        screen</div>
                </div>
                <button id="installBtn"
                    style="background:linear-gradient(135deg,#f15a29,#f47929);color:#fff;border:none;padding:8px 18px;border-radius:8px;font-weight:700;font-size:0.8rem;cursor:pointer;white-space:nowrap;font-family:'Jost',sans-serif;">Install</button>
                <button id="installDismiss"
                    style="background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer;padding:4px;font-size:1.2rem;line-height:1;"
                    aria-label="Dismiss">&times;</button>
            </div>
        </div>

        {{-- Push opt-in banner --}}
        @php
            $newthemeHasPushSub = auth()->user()->pushSubscriptions()->exists();
        @endphp
        <div id="pushOptInBanner"
            style="display:none;position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:9997;width:calc(100% - 24px);max-width:400px;">
            <div
                style="background:linear-gradient(135deg,#3a3536 0%,#4a4546 100%);border-radius:16px;padding:14px 16px;box-shadow:0 12px 32px rgba(0,0,0,0.28);border:1px solid rgba(241,90,41,0.3);">
                <div style="display:flex;align-items:flex-start;gap:12px;">
                    <div
                        style="width:40px;height:40px;border-radius:10px;flex-shrink:0;background:linear-gradient(135deg,#f15a29,#f47929);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 10px rgba(241,90,41,0.35);">
                        <svg fill="none" stroke="#fff" viewBox="0 0 24 24" style="width:22px;height:22px;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <div style="flex:1;min-width:0;padding-top:1px;">
                        <div
                            style="color:#fff;font-weight:700;font-size:0.95rem;font-family:'Jost',sans-serif;line-height:1.25;">
                            Enable notifications</div>
                        <div style="color:rgba(255,255,255,0.65);font-size:0.78rem;margin-top:4px;line-height:1.4;">Loan
                            updates on your phone + desktop, even when SHF is closed.</div>
                    </div>
                    <button id="pushOptInDismissBtn"
                        style="background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;padding:2px 4px;font-size:1.4rem;line-height:1;margin:-4px -4px 0 0;flex-shrink:0;"
                        aria-label="Dismiss">&times;</button>
                </div>
                <div style="display:flex;justify-content:flex-end;align-items:center;gap:16px;margin-top:12px;">
                    <a href="{{ route('notifications.index') }}" id="pushOptInSettingsLink"
                        style="color:rgba(255,255,255,0.75);text-decoration:none;font-size:0.8rem;font-family:'Jost',sans-serif;font-weight:500;">Settings</a>
                    <button id="pushOptInEnableBtn"
                        style="background:linear-gradient(135deg,#f15a29,#f47929);color:#fff;border:none;padding:9px 22px;border-radius:10px;font-weight:700;font-size:0.85rem;cursor:pointer;font-family:'Jost',sans-serif;box-shadow:0 4px 12px rgba(241,90,41,0.35);white-space:nowrap;">Enable</button>
                </div>
            </div>
        </div>

        {{-- Notification bell badge — poll /api/notifications/count every 60s.
             Updates every `.js-notif-badge` element (topbar bell + any page-level
             clones) and exposes window.updateNotifBadge() so other scripts can
             trigger an immediate refresh after marking notifications read. --}}
        <script>
            (function () {
                'use strict';
                var URL = @json(route('api.notifications.count'));

                function apply(count) {
                    document.querySelectorAll('.js-notif-badge').forEach(function (b) {
                        if (!count || count <= 0) {
                            b.classList.add('d-none');
                            b.textContent = '';
                        } else {
                            b.classList.remove('d-none');
                            b.textContent = count > 99 ? '99+' : String(count);
                        }
                    });
                }

                window.updateNotifBadge = function () {
                    fetch(URL, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .then(function (data) { if (data && typeof data.count !== 'undefined') { apply(data.count); } })
                        .catch(function () {});
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', window.updateNotifBadge);
                } else {
                    window.updateNotifBadge();
                }
                setInterval(window.updateNotifBadge, 60000);
            })();
        </script>

        {{-- Existing push helper script (window.SHFPush) --}}
        <script src="{{ asset('newtheme/js/push-notifications.js') }}?v={{ $v }}"></script>
        <script>
            (function() {
                if (!window.SHFPush || !window.SHFPush.supported()) {
                    return;
                }
                var hasSub = @json($newthemeHasPushSub);
                var isImpersonating = @json(app('impersonate')->isImpersonating());
                var banner = document.getElementById('pushOptInBanner');
                var enableBtn = document.getElementById('pushOptInEnableBtn');
                var dismissBtn = document.getElementById('pushOptInDismissBtn');
                var explicitlyDisabled = (typeof window.SHFPush.wasExplicitlyDisabled === 'function') ?
                    window.SHFPush.wasExplicitlyDisabled() :
                    false;

                // Already opted in & subscribed — keep endpoint mapped to current user.
                if (window.SHFPush.permission() === 'granted' && hasSub) {
                    window.SHFPush.resync().catch(function() {});
                    return;
                }

                // Permission granted, no server sub, and user hasn't deliberately
                // disabled → silently re-subscribe.
                if (window.SHFPush.permission() === 'granted' && !hasSub &&
                    (isImpersonating || !explicitlyDisabled)) {
                    window.SHFPush.enable().catch(function() {
                        if (!isImpersonating) {
                            showBanner();
                        }
                    });
                    return;
                }

                if (isImpersonating) {
                    return;
                }
                if (window.SHFPush.permission() === 'denied') {
                    showBanner(true);
                    return;
                }

                // User explicitly disabled — always show the re-enable banner.
                // They've shown they actively manage this state, so the 24h
                // dismiss cooldown shouldn't keep the toggle hidden from them.
                if (explicitlyDisabled) {
                    showBanner();
                    return;
                }

                var lastDismiss = parseInt(localStorage.getItem('push-optin-dismissed') || '0', 10);
                if (lastDismiss && (Date.now() - lastDismiss) < 86400000) {
                    return;
                }
                showBanner();

                function showBanner(blocked) {
                    if (!banner) {
                        return;
                    }
                    if (blocked) {
                        enableBtn.style.display = 'none';
                    }
                    banner.style.display = 'block';
                    enableBtn.addEventListener('click', function() {
                        enableBtn.disabled = true;
                        enableBtn.textContent = 'Enabling…';
                        window.SHFPush.enable().then(function() {
                                banner.style.display = 'none';
                            })
                            .catch(function() {
                                enableBtn.disabled = false;
                                enableBtn.textContent = 'Enable';
                                window.location.href = @json(route('notifications.index'));
                            });
                    });
                    dismissBtn.addEventListener('click', function() {
                        localStorage.setItem('push-optin-dismissed', Date.now().toString());
                        banner.style.display = 'none';
                    });
                }
            })
            ();
        </script>

        {{-- Leave-impersonation interceptor — detach push endpoint before redirect.
         Aggressive 600ms guard so the user isn't kept waiting if the network
         call stalls (the original layout used 1500ms, which is the main reason
         "leave impersonate" felt slow on the newtheme dashboard). --}}
        <script>
            (function() {
                var leaveUrl = @json(route('impersonate.leave'));
                var links = document.querySelectorAll('a[href="' + leaveUrl + '"]');
                if (!links.length) {
                    return;
                }
                links.forEach(function(link) {
                    link.addEventListener('click', function(e) {
                        if (link.__shfLeaveCleaned) {
                            return;
                        }
                        e.preventDefault();
                        link.__shfLeaveCleaned = true;
                        var go = function() {
                            window.location.href = leaveUrl;
                        };
                        if (window.SHFPush && typeof window.SHFPush.detachFromCurrentUser === 'function') {
                            var done = false;
                            var guard = setTimeout(function() {
                                if (!done) {
                                    done = true;
                                    go();
                                }
                            }, 600);
                            window.SHFPush.detachFromCurrentUser().finally(function() {
                                if (done) {
                                    return;
                                }
                                done = true;
                                clearTimeout(guard);
                                go();
                            });
                        } else {
                            go();
                        }
                    });
                });
            })();
        </script>
    @endauth

    {{-- Service Worker registration --}}
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(function(err) {
                console.warn('SW register failed', err);
            });
        }
        (function() {
            var deferredPrompt = null;
            var banner = document.getElementById('installBanner');
            if (!banner) {
                return;
            }
            var installBtn = document.getElementById('installBtn');
            var dismissBtn = document.getElementById('installDismiss');
            if (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone) {
                return;
            }
            var dismissed = localStorage.getItem('pwa-install-dismissed');
            if (dismissed && (Date.now() - parseInt(dismissed)) < 86400000) {
                return;
            }
            window.addEventListener('beforeinstallprompt', function(e) {
                e.preventDefault();
                deferredPrompt = e;
                banner.style.display = '';
            });
            installBtn && installBtn.addEventListener('click', function() {
                if (!deferredPrompt) return;
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function(r) {
                    if (r.outcome === 'accepted') {
                        banner.style.display = 'none';
                    }
                    deferredPrompt = null;
                });
            });
            dismissBtn && dismissBtn.addEventListener('click', function() {
                localStorage.setItem('pwa-install-dismissed', Date.now().toString());
                banner.style.display = 'none';
            });
        })();

        {{-- Tab horizontal-scroll arrows for narrow viewports.
         Mirrors the wrapper logic from public/newtheme/assets/menu.js so any
         .tabs row gets left/right arrows when its content overflows. CSS for
         .tabs-wrap / .tabs-arrow already lives in shf-workflow.css. --}}
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.tabs').forEach(function(tabs) {
                if (tabs.dataset.shfTabsWrapped) {
                    return;
                }
                tabs.dataset.shfTabsWrapped = '1';

                var wrap = document.createElement('div');
                wrap.className = 'tabs-wrap';
                tabs.parentNode.insertBefore(wrap, tabs);

                var leftBtn = document.createElement('button');
                leftBtn.type = 'button';
                leftBtn.className = 'tabs-arrow tabs-arrow-left';
                leftBtn.setAttribute('aria-label', 'Scroll tabs left');
                leftBtn.innerHTML =
                    '<svg viewBox="0 0 24 24" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>';

                var rightBtn = document.createElement('button');
                rightBtn.type = 'button';
                rightBtn.className = 'tabs-arrow tabs-arrow-right';
                rightBtn.setAttribute('aria-label', 'Scroll tabs right');
                rightBtn.innerHTML =
                    '<svg viewBox="0 0 24 24" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg>';

                wrap.appendChild(leftBtn);
                wrap.appendChild(tabs);
                wrap.appendChild(rightBtn);

                function amount() {
                    return Math.max(160, Math.round(tabs.clientWidth * 0.7));
                }
                leftBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    tabs.scrollBy({
                        left: -amount(),
                        behavior: 'smooth'
                    });
                });
                rightBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    tabs.scrollBy({
                        left: amount(),
                        behavior: 'smooth'
                    });
                });

                function update() {
                    wrap.classList.toggle('has-left', tabs.scrollLeft > 4);
                    wrap.classList.toggle('has-right', tabs.scrollLeft + tabs.clientWidth < tabs
                        .scrollWidth - 4);
                }
                tabs.addEventListener('scroll', update, {
                    passive: true
                });
                window.addEventListener('resize', update);
                setTimeout(update, 50);
                setTimeout(update, 300);

                tabs.querySelectorAll('.tab').forEach(function(t) {
                    t.addEventListener('click', function() {
                        setTimeout(function() {
                            var aR = t.getBoundingClientRect();
                            var wR = tabs.getBoundingClientRect();
                            if (aR.left < wR.left) tabs.scrollBy({
                                left: aR.left - wR.left - 20,
                                behavior: 'smooth'
                            });
                            else if (aR.right > wR.right) tabs.scrollBy({
                                left: aR.right - wR.right + 20,
                                behavior: 'smooth'
                            });
                            update();
                        }, 20);
                    });
                });
            });
        });
    </script>

    {{-- Shared interactive helpers (read-only — never edit these) --}}
    <script src="{{ asset('newtheme/assets/shf-interactive.js') }}?v={{ $v }}"></script>

    {{-- Generic themed dropdown component used by any page that drops a
     `.shf-dd-wrap` widget. Auto-initialises on DOMContentLoaded; multiple
     dropdowns on the same page work in isolation (only one open at a time). --}}
    <script src="{{ asset('newtheme/assets/shf-dropdown.js') }}?v={{ $v }}"></script>

    {{-- Site-wide Create-Task + Create-DVR modal behaviour — open/close,
         SHF.validateForm (jQuery) validation, loan-autocomplete, submit, toast. --}}
    @auth
        <script src="{{ asset('newtheme/assets/shf-create-task.js') }}?v={{ $v }}"></script>
        @if (auth()->user()->hasPermission('create_dvr'))
            <script src="{{ asset('newtheme/assets/shf-create-dvr.js') }}?v={{ $v }}"></script>
        @endif
    @endauth

    {{-- FAB + More-sheet wiring. shf-newtheme.js deliberately omits FAB handling
         to avoid the double-bind that used to cancel every click, so we wire
         both elements once here. --}}
    <script>
        (function() {
            // FAB expand/collapse
            var fabMain = document.getElementById('shfFabMain');
            if (fabMain && !fabMain.dataset.shfBound) {
                fabMain.dataset.shfBound = '1';
                var closeFab = function() {
                    document.body.classList.remove('shf-fab-open');
                    fabMain.setAttribute('aria-expanded', 'false');
                };
                fabMain.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var open = document.body.classList.toggle('shf-fab-open');
                    fabMain.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                document.querySelectorAll('.shf-fab-backdrop').forEach(function(b) {
                    b.addEventListener('click', closeFab);
                });
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && document.body.classList.contains('shf-fab-open')) {
                        closeFab();
                    }
                });
            }

            // Mobile More-sheet open/close
            var moreBtn = document.getElementById('shfMoreBtn');
            var moreSheet = document.getElementById('shfMoreSheet');
            var moreClose = document.getElementById('shfMoreClose');
            var moreBackdrop = document.getElementById('shfMoreBackdrop');
            if (moreBtn && moreSheet && !moreBtn.dataset.shfBound) {
                moreBtn.dataset.shfBound = '1';
                var closeMore = function() {
                    document.body.classList.remove('shf-more-open');
                };
                moreBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    document.body.classList.toggle('shf-more-open');
                });
                if (moreClose)     { moreClose.addEventListener('click', closeMore); }
                if (moreBackdrop)  { moreBackdrop.addEventListener('click', closeMore); }
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && document.body.classList.contains('shf-more-open')) {
                        closeMore();
                    }
                });
            }
        })();
    </script>

    @stack('page-scripts')

    {{-- Shared tab-persistence. Loads AFTER @stack('page-scripts') so it runs
         once every page has wired its own tab handlers — `shf-tab-persist.js`
         just fires a synthetic click on the stored tab, reusing each page's
         existing activation logic. Keyed by {group}+pathname so pages with the
         same group name (e.g. multiple `loan` groups) don't cross-contaminate. --}}
    <script src="{{ asset('newtheme/assets/shf-tab-persist.js') }}?v={{ $v }}"></script>
</body>

</html>
