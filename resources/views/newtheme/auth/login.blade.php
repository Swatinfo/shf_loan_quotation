@extends('newtheme.layouts.guest')
@section('title', 'Log In · SHF World')

@section('content')
    <h1>Welcome back</h1>
    <p class="auth-lead">Sign in to your SHF account to manage quotations and loans.</p>

    @if (session('status'))
        <div class="auth-flash auth-flash-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="auth-flash auth-flash-error">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}" id="authForm" autocomplete="on">
        @csrf

        <div class="auth-field">
            <label for="email" class="lbl">Email</label>
            <input id="email" class="input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @if ($errors->has('email'))
                <ul class="auth-err">@foreach ($errors->get('email') as $m)<li>{{ $m }}</li>@endforeach</ul>
            @endif
        </div>

        <div class="auth-field">
            <label for="password" class="lbl">Password</label>
            <div class="auth-pw-wrap">
                <input id="password" class="input" type="password" name="password" required autocomplete="current-password">
                <button type="button" class="auth-pw-eye" data-target="password" aria-label="Toggle password visibility">
                    <svg class="pw-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <svg class="pw-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M3 3l18 18"/></svg>
                </button>
            </div>
            @if ($errors->has('password'))
                <ul class="auth-err">@foreach ($errors->get('password') as $m)<li>{{ $m }}</li>@endforeach</ul>
            @endif
        </div>

        <div class="auth-actions">
            @if (Route::has('password.request'))
                <a class="auth-link" href="{{ route('password.request') }}">Forgot password?</a>
            @else
                <span></span>
            @endif
            <button type="submit" class="btn primary">
                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                Log in
            </button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
$(function() {
    $('.auth-pw-eye').on('click', function() {
        var $input = $('#' + $(this).data('target'));
        var isPw = $input.attr('type') === 'password';
        $input.attr('type', isPw ? 'text' : 'password');
        $(this).find('.pw-open').toggle(!isPw);
        $(this).find('.pw-closed').toggle(isPw);
    });
    $('#authForm').on('input change', '.is-invalid', function() { $(this).removeClass('is-invalid'); });
    $('#authForm').on('submit', function(e) {
        if (window.SHF && typeof SHF.validateForm === 'function') {
            if (!SHF.validateForm($(this), {
                email: { required: true, email: true, label: 'Email' },
                password: { required: true, label: 'Password' }
            })) { e.preventDefault(); }
        }
    });
});
</script>
@endpush
