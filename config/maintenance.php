<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maintenance-mode IP allowlist
    |--------------------------------------------------------------------------
    |
    | While the site is in maintenance mode (`php artisan down`), requests from
    | these IPs are let through; everyone else gets the 503 page. Set a
    | comma-separated list in MAINTENANCE_ALLOWED_IPS. Single IPs and CIDR
    | ranges are both supported (IPv4 + IPv6), e.g.:
    |
    |   MAINTENANCE_ALLOWED_IPS=203.0.113.10,198.51.100.5,192.168.1.0/24
    |
    | Empty = no bypass (the whole site is down for everyone). Re-run
    | `php artisan config:cache` after changing the value if config is cached.
    |
    */

    'allowed_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MAINTENANCE_ALLOWED_IPS', ''))
    ))),
];
