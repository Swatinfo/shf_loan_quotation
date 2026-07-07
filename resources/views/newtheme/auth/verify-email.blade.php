@extends('newtheme.layouts.guest')
@section('title', 'Verify Email · SHF World')

@section('content')
    <h1>Verify your email</h1>
    <p class="auth-lead">Thanks for signing up. Before getting started, please verify your email by clicking the link we sent. If you didn't receive it, we'll gladly send another.</p>

    @if (session('status') == 'verification-link-sent')
        <div class="auth-flash auth-flash-success">
            A new verification link has been sent to the email address you provided during registration.
        </div>
    @endif

    <div class="auth-actions">
        <form method="POST" action="{{ route('logout') }}" style="display:inline;">
            @csrf
            <button type="submit" class="auth-link">Log out</button>
        </form>
        <form method="POST" action="{{ route('verification.send') }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn primary">
                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Resend Verification Email
            </button>
        </form>
    </div>
@endsection
