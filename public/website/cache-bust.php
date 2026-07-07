<?php
/**
 * SHF marketing site — cache-bust utility.
 *
 * Regenerates `asset-version.txt` so every page rendered after this runs
 * will serve CSS/JS/favicon URLs with a new `?v=...` query string — forcing
 * browsers (and any intermediate caches) to re-fetch the assets.
 *
 * Also wipes the contact-form rate-limit cache (_rate-cache/*.log)
 * by default, so IP / email / phone counters are reset alongside the
 * new asset version. Pass --keep-rate to skip that step.
 *
 * Usage:
 *   CLI:   php cache-bust.php                 # bump version + clear rate cache
 *          php cache-bust.php --keep-rate     # bump version, KEEP rate counters
 *
 *   Web:   https://your-domain/cache-bust?key=YOUR_SECRET
 *          (key must equal CACHE_BUST_KEY below — change it before going live)
 *          Append &keep-rate=1 to skip the rate-cache clear.
 */

declare(strict_types=1);

const CACHE_BUST_KEY   = 'shf-refresh-2026';   // change this to something private
const VERSION_FILE     = __DIR__ . '/asset-version.txt';
const IS_CLI           = PHP_SAPI === 'cli';

/* Guard: allow CLI freely, but web requests must pass ?key= */
if (!IS_CLI) {
    $key = $_GET['key'] ?? '';
    if (!hash_equals(CACHE_BUST_KEY, (string) $key)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Forbidden: missing or invalid key.\n";
        exit;
    }
}

/* Random 10-char hex string (e.g. "a3f9e2b4c1") — shorter and less
   predictable than a timestamp. random_bytes() is cryptographically
   secure; failure falls back to uniqid() so cache-busting still works. */
try {
    $newVersion = bin2hex(random_bytes(5));
} catch (Throwable $e) {
    $newVersion = substr(str_replace('.', '', uniqid('', true)), -10);
}

$ok = @file_put_contents(VERSION_FILE, $newVersion);
if ($ok === false) {
    $msg = "ERROR: could not write to " . VERSION_FILE . " — check file permissions.";
    if (IS_CLI) {
        fwrite(STDERR, $msg . "\n");
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

/* Optional: also clear PHP's OPcache if enabled, so subsequent requests
   see the new version file immediately. */
$opcacheCleared = false;
if (function_exists('opcache_reset')) {
    $opcacheCleared = (bool) @opcache_reset();
}

/* Rate-limit cache clear — ON BY DEFAULT.
   By default every cache-bust also wipes IP / email / phone counters so
   visitors get a clean slate alongside the new asset version.
   Pass  ?keep-rate=1  (web)  or  --keep-rate  (CLI)  to skip this step. */
$rateSkip =
    (IS_CLI && in_array('--keep-rate', $argv ?? [], true))
    || (!IS_CLI && !empty($_GET['keep-rate']));

$rateFilesRemoved = null;
if (!$rateSkip) {
    $rateDir = __DIR__ . '/_rate-cache';
    $rateFilesRemoved = 0;
    if (is_dir($rateDir)) {
        foreach ((array) @glob($rateDir . '/*.log') as $f) {
            if (@unlink($f)) { $rateFilesRemoved++; }
        }
    }
}

$summary = [
    'old_version'      => 'n/a',
    'new_version'      => $newVersion,
    'file'             => VERSION_FILE,
    'bytes_written'    => $ok,
    'opcache_cleared'  => $opcacheCleared,
    'rate_limit_cleared' => $rateFilesRemoved,  // null = not requested, 0+ = file count
    'when'             => date('c'),
];

if (IS_CLI) {
    echo "SHF cache-bust complete.\n";
    echo "  New asset version: {$newVersion}\n";
    echo "  Version file:      " . VERSION_FILE . "\n";
    echo "  OPcache cleared:   " . ($opcacheCleared ? 'yes' : 'not enabled') . "\n";
    echo "  Rate-limit cache:  " . ($rateFilesRemoved === null
        ? 'preserved (via --keep-rate)'
        : "cleared ({$rateFilesRemoved} file(s))") . "\n";
    exit(0);
}

/* Web response */
if (!empty($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $summary, JSON_PRETTY_PRINT);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>SHF — Cache Busted</title>
<style>
body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; max-width: 560px; margin: 60px auto; padding: 0 20px; color: #3a3536; }
h1 { color: #f15a29; }
table { border-collapse: collapse; margin-top: 20px; width: 100%; }
td { padding: 8px 12px; border-bottom: 1px solid #eee; }
td:first-child { font-weight: 600; color: #6b7280; width: 40%; }
.ok { color: #27ae60; font-weight: 600; }
</style>
</head>
<body>
  <h1>✓ Cache Busted</h1>
  <p class="ok">Asset cache version regenerated successfully.</p>
  <table>
    <tr><td>New version</td><td><code><?= htmlspecialchars($newVersion) ?></code></td></tr>
    <tr><td>Bytes written</td><td><?= (int) $ok ?></td></tr>
    <tr><td>OPcache cleared</td><td><?= $opcacheCleared ? 'yes' : 'not enabled' ?></td></tr>
    <tr><td>Rate-limit cache</td><td><?= $rateFilesRemoved === null
      ? '<span style="color:#6b7280;">preserved (via ?keep-rate=1)</span>'
      : ('<span style="color:#27ae60;">cleared (' . (int) $rateFilesRemoved . ' file' . ((int) $rateFilesRemoved === 1 ? '' : 's') . ')</span>') ?></td></tr>
    <tr><td>Timestamp</td><td><?= htmlspecialchars(date('r')) ?></td></tr>
  </table>
  <p style="margin-top:28px;color:#6b7280;font-size:13px;">Browsers will now re-fetch CSS, JS and favicon assets with the new version query string on their next visit.</p>
</body>
</html>
