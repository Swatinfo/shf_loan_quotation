<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SHF Loan Proposal') }}</title>

        <!-- PWA Manifest -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#3a3536">
        <link rel="apple-touch-icon" href="/images/icon-192x192.png">

        <!-- Fonts: now loaded via @font-face in shf.css (local woff2 files) -->

        <!-- Bootstrap 5.3 CSS (local) -->
        <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">

        <!-- Bootstrap Datepicker -->
        <link rel="stylesheet" href="/vendor/datepicker/css/bootstrap-datepicker3.min.css">

        <!-- SHF Custom Design System -->
        <link rel="stylesheet" href="/css/shf.css">

        @stack('styles')
    </head>
    <body class="font-body" style="background: #f8f8f8;">
        <div style="min-height: 100vh;">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @hasSection('header')
                <header class="shadow" style="background: linear-gradient(135deg, #3a3536cc 0%, #3a353680 100%); backdrop-filter: blur(10px);">
                    <div class="py-3 px-3 px-sm-4 px-lg-5">
                        @yield('header')
                    </div>
                </header>
            @endif

            <!-- Flash Messages (Toast Style) — jQuery-driven -->
            @if(session('success'))
                <div class="shf-toast-wrapper">
                    <div class="shf-toast success" data-auto-dismiss="5000">
                        <svg style="width:16px;height:16px;color:#4ade80;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('success') }}</span>
                        <button type="button" class="shf-toast-close" style="background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;margin-left:8px;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            @endif
            @if(session('error'))
                <div class="shf-toast-wrapper">
                    <div class="shf-toast error" data-auto-dismiss="8000">
                        <svg style="width:16px;height:16px;color:#f87171;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('error') }}</span>
                        <button type="button" class="shf-toast-close" style="background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;margin-left:8px;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            @endif
            @if(session('warning'))
                <div class="shf-toast-wrapper">
                    <div class="shf-toast warning" data-auto-dismiss="7000">
                        <svg style="width:16px;height:16px;color:#facc15;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>{{ session('warning') }}</span>
                        <button type="button" class="shf-toast-close" style="background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;margin-left:8px;">
                            <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            @endif

            <!-- Page Content -->
            <main>
                @yield('content')
            </main>
        </div>

        <!-- PWA Install Banner -->
        <div id="installBanner" style="display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:9998;width:calc(100% - 32px);max-width:400px;">
            <div style="background:linear-gradient(135deg,#3a3536 0%,#4a4546 100%);border-radius:16px;padding:16px 20px;box-shadow:0 8px 32px rgba(0,0,0,0.25);border:1px solid rgba(241,90,41,0.3);display:flex;align-items:center;gap:14px;">
                <img src="/images/icon-192x192.png" alt="SHF" style="width:48px;height:48px;border-radius:12px;flex-shrink:0;">
                <div style="flex:1;min-width:0;">
                    <div style="color:#fff;font-weight:700;font-size:0.9rem;font-family:'Jost',sans-serif;">Install SHF Loans</div>
                    <div style="color:rgba(255,255,255,0.6);font-size:0.75rem;margin-top:2px;">Quick access from your home screen</div>
                </div>
                <button id="installBtn" style="background:linear-gradient(135deg,#f15a29,#f47929);color:#fff;border:none;padding:8px 18px;border-radius:8px;font-weight:700;font-size:0.8rem;cursor:pointer;white-space:nowrap;font-family:'Jost',sans-serif;">Install</button>
                <button id="installDismiss" style="background:none;border:none;color:rgba(255,255,255,0.4);cursor:pointer;padding:4px;font-size:1.2rem;line-height:1;" aria-label="Dismiss">&times;</button>
            </div>
        </div>

        <!-- Offline Status Banner -->
        <div id="offlineBanner" class="offline-banner" style="display:none;position:fixed;bottom:0;left:0;right:0;padding:8px 16px;text-align:center;font-size:14px;z-index:9999;transition:transform 0.3s ease;">
        </div>

        <style>
            .offline-banner { transform: translateY(100%); }
            .offline-banner.show { display:block !important; transform: translateY(0); }
            .offline-banner.offline { background:#c0392b; color:#fff; border-top:2px solid #e74c3c; }
            .offline-banner.syncing { background:#f39c12; color:#fff; border-top:2px solid #f1c40f; }
            .offline-banner.online { background:#27ae60; color:#fff; border-top:2px solid #2ecc71; }
        </style>

        <!-- jQuery + Bootstrap JS + Datepicker + SHF App -->
        <script src="/vendor/jquery/jquery-3.7.1.min.js"></script>
        <script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="/vendor/datepicker/js/bootstrap-datepicker.min.js"></script>
        <script src="/js/shf-app.js"></script>

        @stack('scripts')

        <!-- Offline Manager (IndexedDB + sync) -->
        <script src="/js/offline-manager.js"></script>

        <!-- Client-side PDF renderer (offline PDF via print dialog) -->
        <script src="/js/pdf-renderer.js"></script>

        <!-- Service Worker Registration -->
        <script>
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.register('/sw.js').then(function(reg) {
                    console.log('SW registered:', reg.scope);
                }).catch(function(err) {
                    console.warn('SW registration failed:', err);
                });
            }
            // Wire up offline/online banner + auto-sync via OfflineManager
            if (typeof OfflineManager !== 'undefined') {
                OfflineManager.setupNetworkListeners();

                // Auto-sync on page load if online with pending items
                if (navigator.onLine) {
                    OfflineManager.getPendingQuotations().then(function(items) {
                        if (items.length > 0) {
                            OfflineManager.syncAll();
                        }
                    }).catch(function() {});
                }
            }

            // PWA Install Prompt
            (function() {
                var deferredPrompt = null;
                var banner = document.getElementById('installBanner');
                var installBtn = document.getElementById('installBtn');
                var dismissBtn = document.getElementById('installDismiss');

                // Don't show if already installed or previously dismissed this session
                if (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone) {
                    return;
                }

                // Check if user dismissed recently (24h cooldown)
                var dismissed = localStorage.getItem('pwa-install-dismissed');
                if (dismissed && (Date.now() - parseInt(dismissed)) < 86400000) {
                    return;
                }

                window.addEventListener('beforeinstallprompt', function(e) {
                    e.preventDefault();
                    deferredPrompt = e;
                    banner.style.display = '';
                });

                installBtn.addEventListener('click', function() {
                    if (!deferredPrompt) return;
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function(result) {
                        if (result.outcome === 'accepted') {
                            banner.style.display = 'none';
                        }
                        deferredPrompt = null;
                    });
                });

                dismissBtn.addEventListener('click', function() {
                    banner.style.display = 'none';
                    localStorage.setItem('pwa-install-dismissed', Date.now().toString());
                    deferredPrompt = null;
                });

                // Hide banner if app gets installed
                window.addEventListener('appinstalled', function() {
                    banner.style.display = 'none';
                    deferredPrompt = null;
                });
            })();
        </script>
    </body>
</html>
