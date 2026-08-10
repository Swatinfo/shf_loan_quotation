<?php

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Guards the CSRF/session-expiry handling in bootstrap/app.php.
 *
 * When a user's session has already expired, the stale CSRF token on the
 * logout form triggers a TokenMismatchException (HTTP 419). Logging out on a
 * dead session should succeed silently (redirect to login), not 419.
 */
class LogoutSessionExpiredTest extends TestCase
{
    public function test_token_mismatch_on_logout_redirects_to_login_instead_of_419(): void
    {
        // Real CSRF middleware self-skips during tests, so simulate the mismatch
        // by throwing the same exception the VerifyCsrfToken middleware would.
        Route::post('logout', fn () => throw new TokenMismatchException('CSRF token mismatch.'))
            ->name('logout');

        $this->post('/logout')
            ->assertRedirect(route('login'));
    }

    public function test_token_mismatch_on_other_post_redirects_to_login_with_status(): void
    {
        Route::post('__csrf_probe', fn () => throw new TokenMismatchException('CSRF token mismatch.'))
            ->name('csrf.probe');

        $this->post('/__csrf_probe')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');
    }

    public function test_token_mismatch_on_json_request_returns_419(): void
    {
        Route::post('__csrf_probe', fn () => throw new TokenMismatchException('CSRF token mismatch.'))
            ->name('csrf.probe');

        $this->postJson('/__csrf_probe')
            ->assertStatus(419)
            ->assertJson(['message' => 'Session expired. Please log in again.']);
    }
}
