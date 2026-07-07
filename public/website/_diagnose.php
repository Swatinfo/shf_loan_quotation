<?php
/**
 * SHF marketing site — deployment diagnostic.
 *
 * Upload this file to the same folder as index.php, then visit:
 *   https://shfworld.com/_diagnose.php
 *
 * It forces error display, checks every critical file/permission/function,
 * then tries to render one page to surface the actual error.
 *
 * DELETE THIS FILE AFTER DEBUGGING — it exposes server paths + PHP info.
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

function row(string $label, $ok, string $detail = ''): void
{
    $icon = $ok === true ? '✅' : ($ok === false ? '❌' : '⚠️');
    $color = $ok === true ? 'green' : ($ok === false ? 'crimson' : 'orange');
    echo '<tr><td>' . $icon . '</td>';
    echo '<td><strong>' . htmlspecialchars($label) . '</strong></td>';
    echo '<td style="color:' . $color . ';">' . htmlspecialchars($detail ?: ($ok ? 'ok' : 'FAIL')) . '</td></tr>';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>SHF site diagnostic</title>
<style>
body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; max-width: 900px; margin: 30px auto; padding: 0 20px; color: #222; }
h1 { color: #f15a29; }
h2 { border-bottom: 1px solid #eee; padding-bottom: 6px; margin-top: 30px; }
table { border-collapse: collapse; width: 100%; font-size: 14px; }
td { padding: 6px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
td:first-child { width: 24px; text-align: center; }
code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
pre { background: #f8f8f8; padding: 14px; border-radius: 6px; overflow: auto; font-size: 12px; border: 1px solid #eee; }
.warn { background: #fff4ef; border-left: 4px solid #f15a29; padding: 12px 16px; margin: 16px 0; }
</style>
</head>
<body>

<h1>SHF — Deployment Diagnostic</h1>
<p class="warn"><strong>Delete this file after you're done.</strong> It exposes server paths and PHP configuration.</p>

<h2>1. Environment</h2>
<table>
<?php
row('PHP version', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION . (version_compare(PHP_VERSION, '8.0.0', '<') ? ' — TOO OLD, need 8.0+' : ''));
row('PHP SAPI', true, PHP_SAPI);
row('Operating system', true, PHP_OS);
row('Current dir', true, __DIR__);
row('Document root', true, (string) ($_SERVER['DOCUMENT_ROOT'] ?? 'n/a'));
row('Script path', true, (string) ($_SERVER['SCRIPT_NAME'] ?? 'n/a'));
row('User', true, (string) (function_exists('posix_getpwuid') && function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '?') : get_current_user()));
row('sys_get_temp_dir()', is_writable(sys_get_temp_dir()), sys_get_temp_dir() . (is_writable(sys_get_temp_dir()) ? ' — writable' : ' — NOT writable'));
?>
</table>

<h2>2. Required PHP extensions / functions</h2>
<table>
<?php
foreach (['mbstring', 'openssl', 'filter', 'json', 'hash'] as $ext) {
    row("ext: $ext", extension_loaded($ext));
}
foreach (['checkdnsrr', 'stream_socket_client', 'stream_socket_enable_crypto', 'random_bytes', 'file_get_contents', 'mb_strlen', 'str_starts_with'] as $fn) {
    row("fn: $fn()", function_exists($fn));
}
?>
</table>

<h2>3. Required files</h2>
<table>
<?php
$required = [
    '_bootstrap.php', '_seo-pages.php', '_seo-head.php', '_seo-foot.php',
    'config.php', 'asset-version.txt', '.htaccess',
    'index.php', 'contact.php', 'contact-submit.php',
    'assets/css/site.css', 'assets/js/site.js', 'assets/img/logo.png',
    'assets/favicon/favicon.ico', 'assets/favicon/favicon-32x32.png',
];
foreach ($required as $rel) {
    $p = __DIR__ . '/' . $rel;
    $exists = is_file($p);
    $readable = $exists && is_readable($p);
    row($rel, $exists && $readable, $exists ? ($readable ? 'exists, readable' : 'exists but NOT readable') : 'MISSING');
}
?>
</table>

<h2>4. Writable paths (for cache-bust + rate-limit)</h2>
<table>
<?php
row('asset-version.txt writable', is_writable(__DIR__ . '/asset-version.txt'));
row('Website folder writable (not required)', is_writable(__DIR__), is_writable(__DIR__) ? 'yes' : 'no — that is fine');
row('sys_get_temp_dir writable', is_writable(sys_get_temp_dir()), sys_get_temp_dir());
?>
</table>

<h2>5. .htaccess / mod_rewrite</h2>
<table>
<?php
$modRewrite = function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules()) : null;
row('mod_rewrite enabled', $modRewrite, $modRewrite === null ? 'unknown (not Apache, or fn unavailable)' : ($modRewrite ? 'yes' : 'NO'));
$htaccess = @file_get_contents(__DIR__ . '/.htaccess');
row('.htaccess present', (bool) $htaccess, $htaccess ? strlen($htaccess) . ' bytes' : 'NOT FOUND');
?>
</table>

<h2>6. Try loading _bootstrap.php</h2>
<?php
try {
    require __DIR__ . '/_bootstrap.php';
    echo '<pre style="color:green;">✅ Bootstrap loaded successfully.
  $assetVersion    = ' . htmlspecialchars((string)($assetVersion ?? 'UNSET')) . '
  $siteUrl         = ' . htmlspecialchars((string)($siteUrl ?? 'UNSET')) . '
  $pageSlug        = ' . htmlspecialchars((string)($pageSlug ?? 'UNSET')) . '
  $turnstileSiteKey = ' . htmlspecialchars((string)($turnstileSiteKey ?? 'UNSET')) . '
  $canonicalUrl    = ' . htmlspecialchars((string)($canonicalUrl ?? 'UNSET')) . '</pre>';
} catch (Throwable $e) {
    echo '<pre style="color:crimson;">❌ Bootstrap FAILED:
  ' . htmlspecialchars($e->getMessage()) . '
  at ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</pre>';
}
?>

<h2>7. Try rendering index.php</h2>
<?php
try {
    ob_start();
    include __DIR__ . '/index.php';
    $body = ob_get_clean();
    echo '<p>✅ index.php rendered <strong>' . number_format(strlen($body)) . '</strong> bytes (first 400 chars):</p>';
    echo '<pre>' . htmlspecialchars(substr($body, 0, 400)) . '…</pre>';
} catch (Throwable $e) {
    while (ob_get_level()) { ob_end_clean(); }
    echo '<pre style="color:crimson;">❌ index.php FAILED:
  ' . htmlspecialchars($e->getMessage()) . '
  at ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '
Stack:
' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}
?>

<h2>8. Listing of .php files present in folder</h2>
<pre><?php
$files = glob(__DIR__ . '/*.php') ?: [];
foreach ($files as $f) {
    echo basename($f) . ' — ' . number_format(filesize($f)) . ' bytes, perm ' . substr(sprintf('%o', fileperms($f)), -4) . "\n";
}
?></pre>

<h2>9. Recent PHP error log (tail)</h2>
<pre><?php
$log = ini_get('error_log');
echo 'error_log path: ' . htmlspecialchars($log ?: 'php system default') . "\n\n";
if ($log && is_readable($log)) {
    $content = @file_get_contents($log);
    if ($content) {
        $lines = array_slice(explode("\n", $content), -40);
        echo htmlspecialchars(implode("\n", $lines));
    }
} else {
    echo '(log unreadable — check cPanel → Errors instead, or SSH to /var/log)';
}
?></pre>

</body>
</html>
