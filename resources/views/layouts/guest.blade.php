<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SHF Loan Proposal') }}</title>

        <!-- Bootstrap 5.3 CSS (local) -->
        <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">

        <!-- SHF Custom Design System -->
        <link rel="stylesheet" href="/css/shf.css">
    </head>
    <body class="font-body" style="background: #3a3536; min-height: 100vh;">
        <div class="d-flex flex-column align-items-center justify-content-center" style="min-height: 100vh; padding-top: 1.5rem;">
            <div class="mb-4 text-center">
                <a href="/" class="d-flex flex-column align-items-center gap-2 text-decoration-none">
                    <img src="/images/logo3.png" alt="SHF Logo" style="height: 48px; width: auto;">
                    <span class="font-display fw-bold" style="font-size: 1.5rem; color: #f15a29; letter-spacing: 0.15em;">SHF</span>
                    <span class="font-display" style="color: rgba(255,255,255,0.6); font-size: 0.875rem; font-weight: 500;">Loan Proposal System</span>
                </a>
            </div>

            <div class="bg-white shadow-lg overflow-hidden" style="width: 100%; max-width: 28rem; padding: 2rem; border-radius: 10px; border-top: 3px solid #f15a29;">
                @yield('content')
            </div>
        </div>

        <!-- jQuery + Bootstrap JS + SHF App -->
        <script src="/vendor/jquery/jquery-3.7.1.min.js"></script>
        <script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
        <script src="/js/shf-app.js"></script>
    </body>
</html>
