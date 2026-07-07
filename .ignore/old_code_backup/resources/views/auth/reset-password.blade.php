@extends('layouts.guest')
@section('title', 'Reset Password — SHF')

@section('content')
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div class="mb-3">
            <label for="email" class="shf-form-label">{{ __('Email') }}</label>
            <input id="email" class="shf-input w-100" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
            @if ($errors->has('email'))
                <ul class="list-unstyled mt-1 mb-0 small shf-text-error">
                    @foreach ($errors->get('email') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <!-- Password -->
        <div class="mb-3">
            <label for="password" class="shf-form-label">{{ __('Password') }}</label>
            <input id="password" class="shf-input w-100" type="password" name="password" required autocomplete="new-password">
            @if ($errors->has('password'))
                <ul class="list-unstyled mt-1 mb-0 small shf-text-error">
                    @foreach ($errors->get('password') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <!-- Confirm Password -->
        <div class="mb-3">
            <label for="password_confirmation" class="shf-form-label">{{ __('Confirm Password') }}</label>
            <input id="password_confirmation" class="shf-input w-100" type="password" name="password_confirmation" required autocomplete="new-password">
            @if ($errors->has('password_confirmation'))
                <ul class="list-unstyled mt-1 mb-0 small shf-text-error">
                    @foreach ($errors->get('password_confirmation') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="d-flex align-items-center justify-content-end mt-4">
            <button type="submit" class="btn-accent">
                <svg class="shf-icon-md" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                {{ __('Reset Password') }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
<script>
$(function() {
    $('form').on('submit', function(e) {
        if (!SHF.validateForm($(this), {
            email: { required: true, email: true },
            password: { required: true, minlength: 8 },
            password_confirmation: {
                required: true,
                custom: function() {
                    var pw = $('#password').val();
                    var confirm = $('#password_confirmation').val();
                    if (confirm && pw !== confirm) return 'Passwords do not match.';
                    return null;
                }
            }
        })) { e.preventDefault(); }
    });
});
</script>
@endpush
