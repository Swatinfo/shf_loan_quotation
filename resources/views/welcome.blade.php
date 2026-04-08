<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'SHF Loan Proposal') }}</title>

        <!-- Bootstrap 5.3 CSS -->
        <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
        <!-- SHF Custom Design System -->
        <link rel="stylesheet" href="/css/shf.css">
    </head>
    <body class="font-body" style="background: #f8f8f8;">
        <div class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
            <div class="text-center" style="max-width: 400px; padding: 2rem;">

                {{-- Logo --}}
                <div class="mb-4">
                    <img src="/images/logo3.png" alt="{{ config('app.name') }}" style="height: 64px; margin: 0 auto; display: block;">
                </div>

                <h1 class="font-display fw-bold mb-2" style="font-size: 1.75rem; color: #3a3536;">
                    {{ config('app.name', 'SHF Loan Proposal') }}
                </h1>
                <p class="small mb-4" style="color: #6b7280;">
                    Bilingual loan quotation generator with bank comparison, EMI calculations, and PDF export.
                </p>

                @if (Route::has('login'))
                    <div class="d-flex align-items-center justify-content-center gap-3">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="btn-accent" style="padding: 8px 24px;">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn-accent" style="padding: 8px 24px;">
                                Log in
                            </a>

                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="btn-accent-outline" style="padding: 8px 24px;">
                                    Register
                                </a>
                            @endif
                        @endauth
                    </div>
                @endif
            </div>
        </div>

        <!-- jQuery + Bootstrap JS -->
        <script src="/vendor/jquery/jquery-3.7.1.min.js"></script>
        <script src="/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    </body>
</html>
