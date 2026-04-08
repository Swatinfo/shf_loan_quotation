# Authentication System

## Overview

Authentication is handled by **Laravel Breeze** with session-based auth. User registration is **disabled** — only admins/super_admins create new users via the User Management module.

## Controllers

All auth controllers live in `app/Http/Controllers/Auth/`.

### AuthenticatedSessionController

**Login** (`GET /login`, `POST /login`):
- Renders `auth.login` view (extends `layouts.guest`)
- Uses `LoginRequest` form request for validation
- On success: regenerates session, logs `ActivityLog::log('login', ...)`, redirects to `/dashboard`
- On failure: returns validation errors

**Logout** (`POST /logout`):
- Logs `ActivityLog::log('logout', ...)` before destroying session
- Invalidates session, regenerates CSRF token
- Redirects to `/`

### PasswordResetLinkController

**Forgot Password** (`GET /forgot-password`, `POST /forgot-password`):
- Renders `auth.forgot-password` view
- Validates email, sends reset link via `Password::sendResetLink()`
- Returns success status or validation error

### NewPasswordController

**Reset Password** (`GET /reset-password/{token}`, `POST /reset-password`):
- Renders `auth.reset-password` view with token
- Validates: token (required), email (required, email), password (required, confirmed, meets Password::defaults())
- Uses `Password::reset()` to validate token and update password
- Fires `PasswordReset` event on success

### PasswordController

**Change Password** (`PUT /password`):
- For authenticated users only
- Validates: current_password (required, must match), password (required, confirmed, meets defaults)
- Updates user password hash
- Returns with status `password-updated`

### RegisteredUserController

**Registration** — exists but effectively **disabled** in production. Admins create users through `/users/create`.

### Other Auth Controllers

| Controller | Purpose |
|-----------|---------|
| `ConfirmablePasswordController` | Re-confirm password for sensitive actions |
| `EmailVerificationPromptController` | Show email verification prompt |
| `EmailVerificationNotificationController` | Resend verification email |
| `VerifyEmailController` | Handle email verification link click |

## Middleware

### EnsureUserIsActive (`active`)

Applied globally to all web routes via `bootstrap/app.php`.

**Behavior**:
1. Checks `auth()->user()->is_active`
2. If `false`: logs out user, invalidates session, redirects to login with error "Your account has been deactivated"
3. If `true`: proceeds to next middleware

**Registration in `bootstrap/app.php`**:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'permission' => CheckPermission::class,
        'active' => EnsureUserIsActive::class,
    ]);
    $middleware->appendToGroup('web', [EnsureUserIsActive::class]);
})
```

## Login View

- Extends `layouts.guest` (dark background, centered white card)
- Email + password fields
- Password visibility toggle (eye icon, client-side JS)
- Session status messages (green) and error messages (red)
- No "Remember me" checkbox
- No registration link

## Session Configuration

- Driver: `database` (sessions table)
- Lifetime: default Laravel (120 minutes)
- CSRF protection on all POST/PUT/PATCH/DELETE routes

## Activity Logging

Login and logout events are recorded in the `activity_logs` table:
```php
ActivityLog::log('login', null, ['username' => $request->email]);
ActivityLog::log('logout', null, ['username' => $user->email]);
```

## Important Notes

- Registration is disabled — users are created by admins via UserController
- The `EnsureUserIsActive` middleware runs on ALL web routes, so deactivated users are immediately locked out even with valid sessions
- Password reset uses Laravel's built-in token-based system
- Auth routes are defined in `routes/auth.php` and included via `bootstrap/app.php`
