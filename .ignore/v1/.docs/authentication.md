# Authentication

## Auth System

Authentication is provided by **Laravel Breeze** (session-based). The system uses standard Laravel session guards with cookie-based session management. There is no API token authentication -- all auth flows are web-based with CSRF protection.

## Controllers

All auth controllers live in `app/Http/Controllers/Auth/`:

| Controller | Purpose |
|-----------|---------|
| `AuthenticatedSessionController` | Login form display, login handling, logout |
| `RegisteredUserController` | Registration (DISABLED -- routes commented out) |
| `PasswordResetLinkController` | "Forgot password" form and reset link email |
| `NewPasswordController` | Password reset form and new password submission |
| `PasswordController` | Authenticated password change (current + new) |
| `ConfirmablePasswordController` | Password confirmation for sensitive actions |
| `EmailVerificationPromptController` | Email verification prompt display |
| `VerifyEmailController` | Email verification link handler |
| `EmailVerificationNotificationController` | Resend verification email |

## Login Flow

**Route**: `GET /login` and `POST /login`

1. User visits `/login` -- renders `auth.login` view (extends `layouts.guest`)
2. Login form collects **email**, **password**, and optional **remember me** checkbox
3. Form includes a password visibility toggle button
4. On submit, `LoginRequest` form request validates:
   - `email`: required, string, email
   - `password`: required, string
5. `LoginRequest::authenticate()` checks rate limiting first (max 5 attempts per email+IP)
6. `Auth::attempt()` verifies credentials with optional "remember" token
7. On success: session regenerated, `ActivityLog::log('login', ...)` records the event, redirects to `dashboard`
8. On failure: rate limiter incremented, validation error returned
9. On rate limit exceeded: `Lockout` event fired, throttle message with seconds remaining

**Rate Limiting**: 5 attempts per throttle key (`email|ip`). After exceeding, user must wait for the cooldown period.

**Activity Logging**: Both login and logout events are recorded via `ActivityLog::log()` with the user's name.

### Login View Structure

The login view (`resources/views/auth/login.blade.php`):
- Extends `layouts.guest`
- Displays session status messages (success in green, errors in red)
- Uses `shf-` prefixed CSS classes (`shf-form-label`, `shf-input`, `shf-checkbox`, `shf-text-error`)
- Submit button uses `btn-accent` styling with an SVG login icon
- No "Forgot Password" link on the login form itself (accessible via direct URL)

## Registration (DISABLED)

Registration is **permanently disabled**. Both routes are commented out in `routes/auth.php`:

```php
// Registration disabled -- users are created by admins via User Management
// Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
// Route::post('register', [RegisteredUserController::class, 'store']);
```

The `RegisteredUserController` class still exists in the codebase (standard Breeze scaffold) but is unreachable. New users are created exclusively by administrators through the User Management interface.

## Password Reset Flow

**Routes** (all under `guest` middleware):

| Method | URI | Name | Handler |
|--------|-----|------|---------|
| GET | `/forgot-password` | `password.request` | `PasswordResetLinkController@create` |
| POST | `/forgot-password` | `password.email` | `PasswordResetLinkController@store` |
| GET | `/reset-password/{token}` | `password.reset` | `NewPasswordController@create` |
| POST | `/reset-password` | `password.store` | `NewPasswordController@store` |

### Flow:

1. User visits `/forgot-password` and enters their email
2. Server validates email and calls `Password::sendResetLink()` to send a reset email
3. User clicks the link in the email, which includes a signed token
4. User lands on `/reset-password/{token}` with the reset form
5. User enters email, new password, and password confirmation
6. Server validates: token (required), email (required, email), password (required, confirmed, meets `Password::defaults()`)
7. On success: password is hashed and saved, remember token is regenerated, `PasswordReset` event fired, user redirected to login with status message
8. On failure: user redirected back with error

## Authenticated Password Change

**Route**: `PUT /password` (under `auth` middleware)

- Validates against `updatePassword` error bag
- Requires `current_password` (validated against stored hash)
- Requires `password` + `password_confirmation` meeting `Password::defaults()`
- Updates the user's password hash and redirects back with `password-updated` status

## Password Confirmation

**Routes**: `GET /confirm-password` and `POST /confirm-password` (under `auth` middleware)

Used for sensitive operations that require re-entering the password:

1. Shows the `auth.confirm-password` view
2. User enters their password
3. Server validates against `Auth::guard('web')->validate()`
4. On success: stores `auth.password_confirmed_at` timestamp in session, redirects to intended URL
5. On failure: throws validation exception

## Email Verification

**Routes** (all under `auth` middleware):

| Method | URI | Name | Handler |
|--------|-----|------|---------|
| GET | `/verify-email` | `verification.notice` | `EmailVerificationPromptController` |
| GET | `/verify-email/{id}/{hash}` | `verification.verify` | `VerifyEmailController` |
| POST | `/email/verification-notification` | `verification.send` | `EmailVerificationNotificationController` |

- Verification links are signed and throttled (6 per minute)
- If already verified, redirects to dashboard
- On verification: marks email as verified and fires `Verified` event
- Resend endpoint is throttled to 6 requests per minute

## EnsureUserIsActive Middleware

**File**: `app/Http/Middleware/EnsureUserIsActive.php`

This middleware checks the `is_active` flag on the authenticated user and forcefully logs out deactivated users:

```php
if ($request->user() && !$request->user()->is_active) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login')->with('error', 'Your account has been deactivated...');
}
```

**Behavior**:
- Applied to all authenticated web routes
- Checks on every request (not just login)
- If `is_active` is false: logs out user, invalidates session, regenerates CSRF token
- Redirects to login page with an error message: "Your account has been deactivated. Please contact an administrator."
- This means an admin can deactivate a user and they will be kicked out on their next page load

## Session Management

- **Driver**: Configured via `SESSION_DRIVER` env (Laravel default)
- **Session regeneration**: On login, `$request->session()->regenerate()` prevents session fixation
- **Session invalidation**: On logout and forced logout (deactivation), session is fully invalidated
- **CSRF regeneration**: Token regenerated on logout and forced logout
- **Remember me**: Optional checkbox on login form, handled by `Auth::attempt()` second parameter
- **Guard**: Uses the `web` guard (`Auth::guard('web')`)

## Route Middleware Groups

```
guest middleware:
  - Login (GET/POST)
  - Forgot password (GET/POST)
  - Reset password (GET/POST)

auth middleware:
  - Email verification (GET/POST)
  - Password confirmation (GET/POST)
  - Password update (PUT)
  - Logout (POST)

active middleware (applied separately on all web routes):
  - EnsureUserIsActive on every authenticated request
```
