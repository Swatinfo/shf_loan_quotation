# Authentication

## Overview

Session-based authentication via Laravel Breeze. Registration is disabled. Users are created by admins only.

## Stack

- **Package:** Laravel Breeze (session-based)
- **Middleware:** `EnsureUserIsActive` appended globally to all web routes
- **Login form:** `auth/login.blade.php` (guest layout)

## Login Flow

1. User visits `/login` → `AuthenticatedSessionController@create`
2. Submits email + password → `AuthenticatedSessionController@store`
3. `LoginRequest` validates credentials and rate limits (5 attempts per minute)
4. Session regenerated on success
5. Activity logged: "login"
6. Redirects to intended URL or dashboard

## Logout

1. POST `/logout` → `AuthenticatedSessionController@destroy`
2. Session invalidated, token regenerated
3. Activity logged: "logout"
4. Redirects to home

## Active User Enforcement

`EnsureUserIsActive` middleware (appended to all web routes):
- Checks `auth()->user()->is_active`
- If false: logs out, invalidates session, redirects to login with error
- Runs on every authenticated request

## Password Reset

1. GET `/forgot-password` → email form
2. POST `/forgot-password` → sends reset link email
3. GET `/reset-password/{token}` → new password form
4. POST `/reset-password` → processes reset

## Password Change

- PUT `/password` → `PasswordController@update`
- Requires current password + new password confirmation
- Permission: `change_own_password`

## Registration

Disabled. Registration route exists but is not linked in the UI. Users are created via User Management by admins.

## Email Verification

Routes exist but not actively used in the current setup:
- `/verify-email` — verification prompt
- `/verify-email/{id}/{hash}` — verify email
- `/email/verification-notification` — resend verification

## Session Configuration

Standard Laravel session config in `config/session.php`. SQLite-backed session storage.

## Known Test Issues

Auth/Profile tests (Breeze defaults) have pre-existing failures due to:
- `EnsureUserIsActive` middleware rejecting test users
- Disabled registration
- Do NOT debug these during unrelated work.
