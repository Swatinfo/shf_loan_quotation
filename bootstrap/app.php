<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => CheckPermission::class,
            'active' => EnsureUserIsActive::class,
        ]);

        $middleware->web(append: [
            EnsureUserIsActive::class,
        ]);

        // Maintenance mode (`php artisan down`) with an IP allowlist: allowlisted
        // client IPs (MAINTENANCE_ALLOWED_IPS → config/maintenance.php) keep access
        // while everyone else gets the 503 page.
        $middleware->replace(
            Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
            PreventRequestsDuringMaintenance::class,
        );

        // Nginx -> Apache run on the same host, so the real client IP arrives via
        // X-Forwarded-For from the loopback proxy. Without this, $request->ip()
        // would be 127.0.0.1 and the allowlist could never match.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A stale CSRF token on an expired session throws TokenMismatchException,
        // which the framework maps to an HttpException(419) in prepareException()
        // BEFORE render callbacks run — so we must match the 419 HttpException here,
        // not TokenMismatchException, or this callback never fires.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || ! $e->getPrevious() instanceof TokenMismatchException) {
                return null;
            }

            // Logout on a dead session: they got what they wanted — just send them
            // to the login screen instead of showing a 419 page.
            if ($request->routeIs('logout')) {
                return redirect()->route('login');
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired. Please log in again.'], 419);
            }

            return redirect()->route('login')
                ->with('status', 'Your session expired. Please log in again.');
        });
    })->create();
