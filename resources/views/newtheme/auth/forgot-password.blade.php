@extends('newtheme.layouts.guest')
@section('title', 'Forgot Password · SHF World')

@section('content')
    <h1>Forgot your password?</h1>
    <p class="auth-lead">Enter your email address and we'll send you a password reset link.</p>

    @if (session('status'))
        <div class="auth-flash auth-flash-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" id="authForm" autocomplete="on">
        @csrf

        <div class="auth-field">
            <label for="email" class="lbl">Email</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @if ($errors->has('email'))
                <ul class="auth-err">@foreach ($errors->get('email') as $m)<li>{{ $m }}</li>@endforeach</ul>
            @endif
        </div>

        <div class="auth-actions">
            <a class="auth-link" href="{{ route('login') }}">Back to login</a>
            <button type="submit" class="btn primary">
                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Email Reset Link
            </button>
        </div>
    </form>
@endsection
