{{--
    Newtheme guest (auth) layout. Used by newtheme/auth/* views.
    Pre-login, so no topbar / bottom-nav — just a centred SHF-branded
    card on a soft accent-tinted background.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon/favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon/favicon-32x32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#f15a29">

    <title>@yield('title', config('app.name', 'SHF World'))</title>

    @php $v = config('app.shf_version'); @endphp

    {{-- Newtheme tokens (gives us .input / .btn / .card + --accent/--red tokens) --}}
    <link rel="stylesheet" href="{{ asset('newtheme/assets/shf.css') }}?v={{ $v }}">
    <link rel="stylesheet" href="{{ asset('newtheme/assets/shf-extras.css') }}?v={{ $v }}">
    <link rel="stylesheet" href="{{ asset('newtheme/assets/shf-workflow.css') }}?v={{ $v }}">

    {{-- Scoped auth styles --}}
    <link rel="stylesheet" href="{{ asset('newtheme/pages/auth.css') }}?v={{ $v }}">

    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700&family=Archivo:wght@400;500;600&display=swap" rel="stylesheet">
</head>

<body class="auth-body">
    <a href="/" class="auth-brand">
        <img src="{{ asset('images/logo3.png') }}" alt="SHF" class="auth-brand-logo">
        <span class="auth-brand-name">SHF WORLD</span>
        <span class="auth-brand-tagline">Loan Management</span>
    </a>

    <div class="auth-card">
        @yield('content')
    </div>

    <div class="auth-foot">© {{ date('Y') }} Shreenathji Home Finance</div>

    <script src="{{ asset('newtheme/vendor/jquery/jquery-3.7.1.min.js') }}?v={{ $v }}"></script>
    <script src="{{ asset('newtheme/assets/shf-newtheme.js') }}?v={{ $v }}"></script>
    @stack('scripts')
</body>

</html>
