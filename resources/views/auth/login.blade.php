@extends('layouts.guest')

@section('content')
    <!-- Session Status -->
    @if (session('status'))
        <div class="mb-3 small fw-medium" style="color: #27ae60;">
            {{ session('status') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-3 small rounded p-3" style="color: #dc2626; background: #fef2f2; border: 1px solid #fca5a5;">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-3">
            <label for="email" class="shf-form-label">{{ __('Email') }}</label>
            <input id="email" class="shf-input w-100" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @if ($errors->has('email'))
                <ul class="list-unstyled mt-1 mb-0 small" style="color: #c0392b;">
                    @foreach ($errors->get('email') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <!-- Password -->
        <div class="mb-3">
            <label for="password" class="shf-form-label">{{ __('Password') }}</label>
            <input id="password" class="shf-input w-100" type="password" name="password" required autocomplete="current-password">
            @if ($errors->has('password'))
                <ul class="list-unstyled mt-1 mb-0 small" style="color: #c0392b;">
                    @foreach ($errors->get('password') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <!-- Remember Me -->
        <div class="mb-3">
            <label for="remember_me" class="d-inline-flex align-items-center">
                <input id="remember_me" type="checkbox" class="shf-checkbox" name="remember" style="width:16px;height:16px;">
                <span class="ms-2 small" style="color: #6b7280;">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="d-flex align-items-center justify-content-between mt-4">
            @if (Route::has('password.request'))
                <a class="small fw-medium" style="color: #f15a29;" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <button type="submit" class="btn-accent ms-3" style="padding-left: 2rem; padding-right: 2rem;">
                {{ __('Log in') }}
            </button>
        </div>
    </form>
@endsection
