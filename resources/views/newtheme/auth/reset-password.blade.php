@extends('newtheme.layouts.guest')
@section('title', 'Reset Password · SHF World')

@section('content')
    <h1>Reset password</h1>
    <p class="auth-lead">Enter your new password below.</p>

    <form method="POST" action="{{ route('password.store') }}" id="authForm" autocomplete="on">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="auth-field">
            <label for="email" class="lbl">Email</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
            @if ($errors->has('email'))
                <ul class="auth-err">@foreach ($errors->get('email') as $m)<li>{{ $m }}</li>@endforeach</ul>
            @endif
        </div>

        <div class="auth-field">
            <label for="password" class="lbl">New Password</label>
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
            <a class="auth-link" href="{{ route('login') }}">Back to login</a>
            <button type="submit" class="btn primary">
                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                Reset Password
            </button>
        </div>
    </form>
@endsection
