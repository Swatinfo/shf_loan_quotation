@extends('layouts.guest')

@section('content')
    <div class="mb-3 small" style="color: #6b7280;">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->
    @if (session('status'))
        <div class="mb-3 small fw-medium" style="color: #27ae60;">
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
                <ul class="list-unstyled mt-1 mb-0 small" style="color: #c0392b;">
                    @foreach ($errors->get('email') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="d-flex align-items-center justify-content-end mt-4">
            <button type="submit" class="btn-accent">
                {{ __('Email Password Reset Link') }}
            </button>
        </div>
    </form>
@endsection
