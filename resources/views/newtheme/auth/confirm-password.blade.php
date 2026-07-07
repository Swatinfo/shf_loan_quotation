@extends('newtheme.layouts.guest')
@section('title', 'Confirm Password · SHF World')

@section('content')
    <h1>Confirm password</h1>
    <p class="auth-lead">This is a secure area of the application. Please confirm your password before continuing.</p>

    <form method="POST" action="{{ route('password.confirm') }}" id="authForm" autocomplete="on">
        @csrf

        <div class="auth-field">
            <label for="password" class="lbl">Password</label>
            <input id="password" class="input" type="password" name="password" required autocomplete="current-password">
            @if ($errors->has('password'))
                <ul class="auth-err">@foreach ($errors->get('password') as $m)<li>{{ $m }}</li>@endforeach</ul>
            @endif
        </div>

        <div class="auth-actions">
            <span></span>
            <button type="submit" class="btn primary">
                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                Confirm
            </button>
        </div>
    </form>
@endsection
