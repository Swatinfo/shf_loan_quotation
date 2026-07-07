@extends('newtheme.layouts.guest')
@section('title', 'Register · SHF World')

@section('content')
    <h1>Create account</h1>
    <p class="auth-lead">Registration is managed by your administrator. If you need access, please contact your branch manager.</p>

    @if (session('status'))
        <div class="auth-flash auth-flash-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('register') }}" id="authForm" autocomplete="on">
        @csrf

        <div class="auth-field">
            <label for="name" class="lbl">Name</label>
            <input id="name" class="input" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            @if ($errors->has('name'))
                <ul class="auth-err">@foreach ($errors->get('name') as $m)<li>{{ $m }}</li>@endforeach</ul>
            @endif
        </div>

        <div class="auth-field">
            <label for="email" class="lbl">Email</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
            @if ($errors->has('email'))
                <ul class="auth-err">@foreach ($errors->get('email') as $m)<li>{{ $m }}</li>@endforeach</ul>
            @endif
        </div>

        <div class="auth-field">
            <label for="password" class="lbl">Password</label>
            <input id="password" class="input" type="password" name="password" required autocomplete="new-password">
            @if ($errors->has('password'))
                <ul class="auth-err">@foreach ($errors->get('password') as $m)<li>{{ $m }}</li>@endforeach</ul>
            @endif
        </div>

        <div class="auth-field">
            <label for="password_confirmation" class="lbl">Confirm Password</label>
            <input id="password_confirmation" class="input" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>

        <div class="auth-actions">
            <a class="auth-link" href="{{ route('login') }}">Already registered?</a>
            <button type="submit" class="btn primary">
                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v16m8-8H4"/></svg>
                Register
            </button>
        </div>
    </form>
@endsection
