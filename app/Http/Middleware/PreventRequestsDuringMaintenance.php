<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets allowlisted client IPs through while the site is in maintenance mode
 * (`php artisan down`); everyone else gets the normal 503 page.
 *
 * The allowlist comes from config('maintenance.allowed_ips') (driven by the
 * MAINTENANCE_ALLOWED_IPS env var) and supports single IPs and CIDR ranges,
 * IPv4 + IPv6. The `--secret` token bypass still works because non-allowlisted
 * requests fall through to the framework's handler.
 *
 * NOTE: behind a proxy (this app runs Nginx -> Apache on one host), the real
 * client IP is only available once `trustProxies()` is configured in
 * bootstrap/app.php — otherwise $request->ip() is the proxy's address.
 */
class PreventRequestsDuringMaintenance extends Middleware
{
    public function handle($request, Closure $next): Response
    {
        if ($this->app->isDownForMaintenance()) {
            $allowed = (array) config('maintenance.allowed_ips', []);

            if ($allowed !== [] && IpUtils::checkIp((string) $request->ip(), $allowed)) {
                return $next($request);
            }
        }

        return parent::handle($request, $next);
    }
}
