<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'SHF Loan Management') }}</title>

    <!-- Bootstrap 5.3 CSS -->
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <!-- SHF Custom Design System -->
    <link rel="stylesheet" href="{{ asset('css/shf.css') }}">
</head>

<body class="font-body bg-body-tertiary">
    <div class="d-flex align-items-center justify-content-center min-vh-100">
        <div class="text-center" style="max-width: 400px; padding: 2rem;">

            {{-- Logo --}}
            <div class="mb-4">
                <img src="{{ asset('images/logo3.png') }}" alt="{{ config('app.name') }}"
                    style="height: 64px; margin: 0 auto; display: block;">
            </div>

            <h1 class="font-display fw-bold mb-2" style="font-size: 1.75rem; color: #3a3536;">
                {{ config('app.name', 'SHF Loan Management') }}
            </h1>
            <p class="small mb-4 shf-text-gray">
                Bilingual loan quotation generator with bank comparison, EMI calculations, and PDF export.
            </p>

            @if (Route::has('login'))
                <div class="d-flex align-items-center justify-content-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="btn-accent" style="padding:8px 24px">
                            <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn-accent" style="padding:8px 24px">
                            <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            Log in
                        </a>

                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn-accent-outline" style="padding:8px 24px">
                                <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                                Register
                            </a>
                        @endif
                    @endauth
                </div>
            @endif
        </div>
    </div>

    <!-- jQuery + Bootstrap JS -->
    <script src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
</body>

</html>
