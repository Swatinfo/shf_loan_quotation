@extends('layouts.guest')
@section('title', 'Forgot Password — SHF')

@section('content')
    <div class="mb-3 small shf-text-gray">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->
    @if (session('status'))
        <div class="mb-3 small fw-medium shf-text-success-alt">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-3">
            <label for="email" class="shf-form-label">{{ __('Email') }}</label>
            <input id="email" class="shf-input w-100" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @if ($errors->has('email'))
                <ul class="list-unstyled mt-1 mb-0 small shf-text-error">
                    @foreach ($errors->get('email') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="d-flex align-items-center justify-content-end mt-4">
            <button type="submit" class="btn-accent">
                <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                {{ __('Email Password Reset Link') }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
$(function() {
    $('form').on('submit', function(e) {
        if (!SHF.validateForm($(this), {
            email: { required: true, email: true }
        })) { e.preventDefault(); }
    });
});
</script>
@endpush
