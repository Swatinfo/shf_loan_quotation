@extends('newtheme.layouts.app')

@section('title', 'Profile · SHF World')

@push('page-styles')
    <link rel="stylesheet" href="{{ asset('newtheme/pages/profile.css') }}?v={{ config('app.shf_version') }}">
@endpush

@section('content')
    <header class="page-header">
        <div class="head-row">
            <div>
                <div class="crumbs"><a href="{{ route('dashboard') }}">Dashboard</a> · <span>Profile</span></div>
                <h1>{{ $user->name }}</h1>
                <div class="sub">{{ $user->email }} · {{ $user->role_label }}</div>
            </div>
        </div>
    </header>

    <main class="content">
        <div class="grid c-form mt-4" style="max-width: 760px;">

            {{-- ===== Profile Information ===== --}}
            <div class="card">
                <div class="card-hd">
                    <div class="t"><span class="num">1</span>Profile Information</div>
                </div>
                <div class="card-bd">
                    <p class="text-xs text-muted" style="margin-bottom:14px;">Update your account's profile information and email address.</p>

                    <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>

                    <form method="post" action="{{ route('profile.update') }}">
                        @csrf
                        @method('patch')

                        <div class="form-row">
                            <label for="name" class="lbl">Name</label>
                            <input id="name" name="name" type="text" class="input" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
                            @error('name')<div class="err">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-row">
                            <label for="email" class="lbl">Email</label>
                            <input id="email" name="email" type="email" class="input" value="{{ old('email', $user->email) }}" required autocomplete="username">
                            @error('email')<div class="err">{{ $message }}</div>@enderror

                            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                                <div class="text-xs" style="margin-top:6px;color:var(--ink-3);">
                                    Your email address is unverified.
                                    <button form="send-verification" class="btn-link" style="background:none;border:none;color:var(--accent);text-decoration:underline;cursor:pointer;padding:0;font-size:inherit;">Click here to re-send the verification email.</button>
                                </div>
                                @if (session('status') === 'verification-link-sent')
                                    <div class="text-xs" style="margin-top:6px;color:var(--green);font-weight:600;">A new verification link has been sent.</div>
                                @endif
                            @endif
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn primary">
                                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                                Save
                            </button>
                            @if (session('status') === 'profile-updated')
                                <span class="saved-msg" style="color:var(--green);font-weight:600;font-size:12px;">Saved.</span>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            {{-- ===== Update Password ===== --}}
            <div class="card mt-4" id="password">
                <div class="card-hd">
                    <div class="t"><span class="num">2</span>Update Password</div>
                </div>
                <div class="card-bd">
                    <p class="text-xs text-muted" style="margin-bottom:14px;">Ensure your account is using a long, random password to stay secure.</p>

                    <form method="post" action="{{ route('password.update') }}">
                        @csrf
                        @method('put')

                        <div class="form-row">
                            <label for="current_password" class="lbl">Current Password</label>
                            <div class="pwd-wrap">
                                <input id="current_password" name="current_password" type="password" class="input" autocomplete="current-password">
                                <button type="button" class="pwd-toggle" data-target="current_password" tabindex="-1" aria-label="Show password">
                                    <svg class="i eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            @if ($errors->updatePassword->has('current_password'))
                                <div class="err">{{ $errors->updatePassword->first('current_password') }}</div>
                            @endif
                        </div>

                        <div class="form-row">
                            <label for="new_password" class="lbl">New Password</label>
                            <div class="pwd-wrap">
                                <input id="new_password" name="password" type="password" class="input" autocomplete="new-password">
                                <button type="button" class="pwd-toggle" data-target="new_password" tabindex="-1" aria-label="Show password">
                                    <svg class="i eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            @if ($errors->updatePassword->has('password'))
                                <div class="err">{{ $errors->updatePassword->first('password') }}</div>
                            @endif
                        </div>

                        <div class="form-row">
                            <label for="confirm_password" class="lbl">Confirm Password</label>
                            <div class="pwd-wrap">
                                <input id="confirm_password" name="password_confirmation" type="password" class="input" autocomplete="new-password">
                                <button type="button" class="pwd-toggle" data-target="confirm_password" tabindex="-1" aria-label="Show password">
                                    <svg class="i eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </button>
                            </div>
                            @if ($errors->updatePassword->has('password_confirmation'))
                                <div class="err">{{ $errors->updatePassword->first('password_confirmation') }}</div>
                            @endif
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn primary">
                                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                                Save
                            </button>
                            @if (session('status') === 'password-updated')
                                <span class="saved-msg" style="color:var(--green);font-weight:600;font-size:12px;">Saved.</span>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

            {{-- ===== Danger Zone (super_admin only) ===== --}}
            @if(auth()->user()->hasRole('super_admin'))
                <div class="card mt-4 danger-zone">
                    <div class="card-hd">
                        <div class="t"><span class="num" style="background:var(--red);">!</span>Danger Zone</div>
                    </div>
                    <div class="card-bd">
                        <p class="text-xs text-muted" style="margin-bottom:14px;">
                            Once your account is deleted, all of its resources and data will be permanently deleted.
                            Before deleting your account, please download any data you wish to retain.
                        </p>
                        <button type="button" class="btn" id="openDeleteAcct" style="background:var(--red);color:#fff;border:1px solid var(--red);">
                            <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete Account
                        </button>
                    </div>
                </div>

                {{-- Delete confirm dialog (lightweight, native modal) --}}
                <div id="delAcctBackdrop" style="display:none;position:fixed;inset:0;background:rgba(20,18,19,0.55);z-index:1200;"></div>
                <div id="delAcctModal" role="dialog" aria-label="Confirm account deletion"
                     style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:calc(100% - 32px);max-width:440px;background:#fff;border-radius:12px;box-shadow:0 24px 48px rgba(0,0,0,0.28);z-index:1201;padding:20px;">
                    <h3 style="font-family:Jost,sans-serif;font-size:16px;font-weight:600;margin:0 0 8px;color:var(--ink);">Are you sure you want to delete your account?</h3>
                    <p class="text-xs text-muted" style="margin:0 0 14px;">
                        Once your account is deleted, all of its resources and data will be permanently deleted.
                        Please enter your password to confirm.
                    </p>
                    <form method="post" action="{{ route('profile.destroy') }}">
                        @csrf
                        @method('delete')
                        <input type="password" name="password" class="input" placeholder="Password" autocomplete="current-password" required>
                        @if ($errors->userDeletion->has('password'))
                            <div class="err" style="margin-top:6px;">{{ $errors->userDeletion->first('password') }}</div>
                        @endif
                        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                            <button type="button" class="btn" id="closeDelAcct">Cancel</button>
                            <button type="submit" class="btn" style="background:var(--red);color:#fff;border:1px solid var(--red);">
                                <svg class="i" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                Delete Account
                            </button>
                        </div>
                    </form>
                </div>
            @endif

        </div>
    </main>
@endsection

@push('page-scripts')
    <script src="{{ asset('newtheme/pages/profile.js') }}?v={{ config('app.shf_version') }}"></script>
    @if(auth()->user()->hasRole('super_admin') && $errors->userDeletion->isNotEmpty())
        <script>
            // Re-open the delete-account modal if there's a validation error.
            document.addEventListener('DOMContentLoaded', function () {
                document.getElementById('delAcctBackdrop').style.display = 'block';
                document.getElementById('delAcctModal').style.display = 'block';
            });
        </script>
    @endif
@endpush
