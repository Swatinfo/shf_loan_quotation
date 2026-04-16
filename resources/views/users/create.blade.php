@extends('layouts.app')

@section('header')
    <h2 class="font-display fw-semibold text-white" style="font-size: 1.25rem; line-height: 1.75rem; margin: 0;">
        <svg style="width:16px;height:16px;display:inline;margin-right:6px;color:#f15a29;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
        Create User
    </h2>
@endsection

@section('content')
    <div class="py-4">
        <div class="mx-auto px-3 px-sm-4 px-lg-5" style="max-width: 42rem;">
            <div class="shf-section">
                <div class="shf-section-header">
                    <div class="shf-section-number">1</div>
                    <span class="shf-section-title">User Information</span>
                </div>
                <div class="shf-section-body">
                    <form method="POST" action="{{ route('users.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="shf-form-label d-block mb-1">Name</label>
                            <input type="text" id="name" name="name" class="shf-input" value="{{ old('name') }}" required autofocus>
                            @if ($errors->has('name'))
                                <ul class="list-unstyled mt-1 mb-0 small" style="color: #c0392b;">
                                    @foreach ($errors->get('name') as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="shf-form-label d-block mb-1">Email</label>
                            <input type="email" id="email" name="email" class="shf-input" value="{{ old('email') }}" required>
                            @if ($errors->has('email'))
                                <ul class="list-unstyled mt-1 mb-0 small" style="color: #c0392b;">
                                    @foreach ($errors->get('email') as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="shf-form-label d-block mb-1">Phone (optional)</label>
                            <input type="text" id="phone" name="phone" class="shf-input" value="{{ old('phone') }}">
                            @if ($errors->has('phone'))
                                <ul class="list-unstyled mt-1 mb-0 small" style="color: #c0392b;">
                                    @foreach ($errors->get('phone') as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="shf-form-label d-block mb-1">Role</label>
                            <select id="role" name="role" class="shf-input">
                                @foreach($roles as $value => $label)
                                    <option value="{{ $value }}" {{ old('role', 'staff') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if ($errors->has('role'))
                                <ul class="list-unstyled mt-1 mb-0 small" style="color: #c0392b;">
                                    @foreach ($errors->get('role') as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="shf-form-label d-block mb-1">Password</label>
                            <div class="position-relative">
                                <input type="password" id="password" name="password" class="shf-input" style="padding-right: 2.5rem;" required>
                                <button type="button" class="shf-password-toggle" data-target="password" tabindex="-1" style="position:absolute;top:0;right:0;bottom:0;display:flex;align-items:center;padding-right:12px;background:none;border:none;color:#9ca3af;cursor:pointer;">
                                    <svg class="shf-eye-open" style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg class="shf-eye-closed" style="width:20px;height:20px;display:none;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                            @if ($errors->has('password'))
                                <ul class="list-unstyled mt-1 mb-0 small" style="color: #c0392b;">
                                    @foreach ($errors->get('password') as $message)
                                        <li>{{ $message }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="shf-form-label d-block mb-1">Confirm Password</label>
                            <div class="position-relative">
                                <input type="password" id="password_confirmation" name="password_confirmation" class="shf-input" style="padding-right: 2.5rem;" required>
                                <button type="button" class="shf-password-toggle" data-target="password_confirmation" tabindex="-1" style="position:absolute;top:0;right:0;bottom:0;display:flex;align-items:center;padding-right:12px;background:none;border:none;color:#9ca3af;cursor:pointer;">
                                    <svg class="shf-eye-open" style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <svg class="shf-eye-closed" style="width:20px;height:20px;display:none;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="d-flex align-items-center">
                                <input type="checkbox" name="is_active" value="1" checked class="shf-checkbox" style="width:16px;height:16px;">
                                <span class="ms-2 small" style="color: #6b7280;">Active</span>
                            </label>
                        </div>

                        <div class="d-flex align-items-center justify-content-end gap-3 mt-4 pt-4" style="border-top: 1px solid var(--border);">
                            <a href="{{ route('users.index') }}" class="btn-accent-outline">Cancel</a>
                            <button type="submit" class="btn-accent">
                                <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Create User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
