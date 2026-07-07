<?php
/**
 * SHF marketing site — SMTP diagnostic.
 *
 * Walks through every SMTP step that contact-submit.php performs
 * (DNS → socket → STARTTLS → AUTH → send), printing pass/fail at each.
 *
 * Upload to the same folder as config.php. Visit:
 *     https://shfworld.com/_smtp-test.php
 *
 * DELETE AFTER USE — it prints your SMTP host config (not the password,
 * but still unnecessary to leave public).
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

$config = @require __DIR__ . '/config.php';
if (!is_array($config)) {
    die('❌ Could not load config.php');
}

function row(string $step, bool $ok, string $detail = ''): void
{
    $icon  = $ok ? '✅' : '❌';
    $color = $ok ? 'green' : 'crimson';
    echo '<tr><td>' . $icon . '</td><td><strong>' . htmlspecialchars($step)
       . '</strong></td><td style="color:' . $color . ';">' . htmlspecialchars($detail ?: ($ok ? 'ok' : 'FAIL')) . '</td></tr>';
}

function readSmtp($sock, int $timeout = 10): string
{
    $buf = '';
    stream_set_timeout($sock, $timeout);
    while (!feof($sock)) {
        $line = fgets($sock, 1024);
        if ($line === false) { break; }
        $buf .= $line;
        if (isset($line[3]) && $line[3] === ' ') { break; }
    }
    return $buf;
}

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>SHF — SMTP diagnostic</title>
<style>
body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; max-width: 900px; margin: 30px auto; padding: 0 20px; color: #222; }
h1 { color: #f15a29; }
h2 { border-bottom: 1px solid #eee; padding-bottom: 6px; margin-top: 30px; }
table { border-collapse: collapse; width: 100%; font-size: 14px; }
td { padding: 6px 10px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
td:first-child { width: 24px; text-align: center; }
pre { background: #f8f8f8; padding: 14px; border-radius: 6px; overflow: auto; font-size: 12px; border: 1px solid #eee; }
.warn { background: #fff4ef; border-left: 4px solid #f15a29; padding: 12px 16px; margin: 16px 0; }
.ok { background: #eaf7ef; border-left: 4px solid #27ae60; padding: 12px 16px; margin: 16px 0; }
</style>
</head>
<body>

<h1>SHF — SMTP Diagnostic</h1>
<p class="warn"><strong>Delete this file after you're done.</strong> It prints your SMTP configuration (host, user, port) — not the password.</p>

<h2>1. Configuration read from config.php</h2>
<table>
<?php
$host   = (string) ($config['SMTP_HOST']   ?? '');
$port   = (int)    ($config['SMTP_PORT']   ?? 0);
$secure = strtolower((string) ($config['SMTP_SECURE'] ?? ''));
$user   = (string) ($config['SMTP_USER']   ?? '');
$pass   = (string) ($config['SMTP_PASS']   ?? '');
$mailTo = (string) ($config['MAIL_TO']     ?? '');

row('SMTP_HOST',   $host !== '', $host ?: 'EMPTY');
row('SMTP_PORT',   $port > 0, (string) $port);
row('SMTP_SECURE', in_array($secure, ['tls', 'ssl'], true), $secure ?: 'EMPTY');
row('SMTP_USER',   $user !== '', $user ?: 'EMPTY');
row('SMTP_PASS',   $pass !== '', $pass ? '(' . strlen($pass) . ' characters set)' : 'EMPTY');
row('MAIL_TO',     $mailTo !== '', $mailTo ?: 'EMPTY');
?>
</table>

<h2>2. DNS lookup</h2>
<table>
<?php
$ip = @gethostbyname($host);
row("Resolve {$host}", $ip !== $host && $ip !== '', $ip ?: 'NO RESULT');
$mxHosts = [];
$ok = @getmxrr($host, $mxHosts);
row("MX records for {$host}", $ok && !empty($mxHosts), implode(', ', $mxHosts) ?: 'none');
?>
</table>

<h2>3. TCP connect to SMTP server</h2>
<?php
$transport = $secure === 'ssl' ? 'ssl://' : '';
$errno = 0; $errstr = '';
$t0 = microtime(true);
$sock = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 15);
$elapsed = round((microtime(true) - $t0) * 1000) . 'ms';

if (!$sock) {
    echo '<pre style="color:crimson;">❌ TCP connect FAILED after ' . $elapsed . '
  errno:  ' . $errno . '
  errstr: ' . htmlspecialchars($errstr) . '

Common causes of this on shared hosting:
  • Host blocks outbound port ' . $port . '.
  • Host blocks outbound SMTP entirely (GoDaddy cPanel shared commonly does).
  • Firewall / security module blocking the connection.

Fix: contact your host and ask them to whitelist outbound connections to ' . htmlspecialchars($host) . ':' . $port . '
  OR switch SMTP to port 465 with SMTP_SECURE = \'ssl\' (often unblocked when 587 is blocked).
  OR use PHP mail() via local relay (we can wire this up).</pre>';
    exit;
}

echo '<div class="ok">✅ Connected to ' . htmlspecialchars($host . ':' . $port) . ' in ' . $elapsed . '</div>';

$greeting = readSmtp($sock);
echo '<strong>Server greeting:</strong><pre>' . htmlspecialchars($greeting) . '</pre>';

if (!str_starts_with($greeting, '220')) {
    echo '<pre style="color:crimson;">❌ Unexpected greeting — expected 220. Aborting.</pre>';
    exit;
}
?>

<h2>4. EHLO</h2>
<?php
$clientHost = gethostname() ?: 'localhost';
@fwrite($sock, 'EHLO ' . $clientHost . "\r\n");
$ehloResp = readSmtp($sock);
echo '<pre>' . htmlspecialchars($ehloResp) . '</pre>';
$ehloOk = str_starts_with($ehloResp, '250');
echo $ehloOk
    ? '<div class="ok">✅ EHLO accepted.</div>'
    : '<pre style="color:crimson;">❌ EHLO failed.</pre>';
if (!$ehloOk) { exit; }
?>

<h2>5. STARTTLS upgrade</h2>
<?php
if ($secure === 'tls') {
    @fwrite($sock, "STARTTLS\r\n");
    $startResp = readSmtp($sock);
    echo '<pre>' . htmlspecialchars($startResp) . '</pre>';
    if (!str_starts_with($startResp, '220')) {
        echo '<pre style="color:crimson;">❌ STARTTLS not accepted.</pre>'; exit;
    }
    $crypto = @stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    if (!$crypto) {
        echo '<pre style="color:crimson;">❌ TLS handshake FAILED.
Possible cause: PHP openssl extension not compiled, or server cert chain unreadable.
Check that openssl extension is loaded (ext: openssl on _diagnose.php).</pre>'; exit;
    }
    echo '<div class="ok">✅ TLS handshake successful.</div>';

    // re-send EHLO after TLS upgrade
    @fwrite($sock, 'EHLO ' . $clientHost . "\r\n");
    $ehloTlsResp = readSmtp($sock);
    echo '<strong>Post-TLS EHLO:</strong><pre>' . htmlspecialchars($ehloTlsResp) . '</pre>';
} else {
    echo '<p>Skipping STARTTLS (SMTP_SECURE is not tls).</p>';
}
?>

<h2>6. AUTH LOGIN</h2>
<?php
@fwrite($sock, "AUTH LOGIN\r\n");
$r = readSmtp($sock);
echo '<pre>' . htmlspecialchars($r) . '</pre>';
if (!str_starts_with($r, '334')) {
    echo '<pre style="color:crimson;">❌ AUTH LOGIN not offered. Aborting.</pre>'; exit;
}

@fwrite($sock, base64_encode($user) . "\r\n");
$r = readSmtp($sock);
echo '<strong>Username:</strong><pre>' . htmlspecialchars($r) . '</pre>';
if (!str_starts_with($r, '334')) {
    echo '<pre style="color:crimson;">❌ Username rejected.</pre>'; exit;
}

@fwrite($sock, base64_encode($pass) . "\r\n");
$r = readSmtp($sock);
echo '<strong>Password:</strong><pre>' . htmlspecialchars($r) . '</pre>';

if (str_starts_with($r, '235')) {
    echo '<div class="ok">✅ Authentication successful.</div>';
} else {
    echo '<pre style="color:crimson;">❌ Authentication FAILED.

Common causes:
  • Gmail App Password is wrong or was revoked.
  • 2-Step Verification is NOT enabled on the Gmail account
    (App Passwords require 2SV to be on).
  • Sending account is locked / suspicious activity flagged — check
    Gmail → Security → Recent activity and authorise the login.
  • Confusing a Google account password with an App Password — they are
    16-character codes like "abcd efgh ijkl mnop" (ignore spaces).

Fix path:
  1. Go to https://myaccount.google.com/apppasswords
  2. Create a new App Password (select "Mail" / "Other: SHF Website")
  3. Copy the 16-character code (no spaces)
  4. Paste it as SMTP_PASS in config.php</pre>';
    @fwrite($sock, "QUIT\r\n"); fclose($sock); exit;
}
?>

<h2>7. Send a real test email</h2>
<?php
$to = $mailTo ?: $user;
@fwrite($sock, 'MAIL FROM:<' . $user . ">\r\n");
echo '<pre>MAIL FROM: ' . htmlspecialchars(readSmtp($sock)) . '</pre>';

@fwrite($sock, 'RCPT TO:<' . $to . ">\r\n");
echo '<pre>RCPT TO: ' . htmlspecialchars(readSmtp($sock)) . '</pre>';

@fwrite($sock, "DATA\r\n");
echo '<pre>DATA: ' . htmlspecialchars(readSmtp($sock)) . '</pre>';

$body  = "From: SHF SMTP Test <{$user}>\r\n";
$body .= "To: {$to}\r\n";
$body .= "Subject: SHF SMTP test — " . date('d M Y H:i:s') . "\r\n";
$body .= "Date: " . date('r') . "\r\n";
$body .= "MIME-Version: 1.0\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
$body .= "\r\n";
$body .= "If you received this, your SMTP pipeline is fully working.\r\n";
$body .= "Sent from _smtp-test.php on " . ($_SERVER['HTTP_HOST'] ?? 'server') . " at " . date('c') . ".\r\n";
$body .= "\r\n.\r\n";

@fwrite($sock, $body);
$r = readSmtp($sock);
echo '<pre>' . htmlspecialchars($r) . '</pre>';

if (str_starts_with($r, '250')) {
    echo '<div class="ok">✅ Test email accepted for delivery → ' . htmlspecialchars($to) . '</div>';
    echo '<p>Check the inbox (and spam folder) of ' . htmlspecialchars($to) . ' within the next minute.</p>';
} else {
    echo '<pre style="color:crimson;">❌ Message rejected after DATA.</pre>';
}

@fwrite($sock, "QUIT\r\n"); readSmtp($sock); fclose($sock);
?>

<h2>8. Alternative ports to try if 587 is blocked</h2>
<?php
$portTests = [25, 465, 587, 2525];
foreach ($portTests as $p) {
    $t0 = microtime(true);
    $testSock = @stream_socket_client(($p === 465 ? 'ssl://' : '') . $host . ':' . $p, $no, $str, 5);
    $t = round((microtime(true) - $t0) * 1000);
    if ($testSock) {
        echo "<div style=\"color:green;\">✅ Port {$p} — OPEN (connected in {$t}ms)</div>";
        fclose($testSock);
    } else {
        echo "<div style=\"color:#999;\">⚠️ Port {$p} — blocked/unavailable ({$str})</div>";
    }
}
?>

<h2>9. Recent PHP error log tail</h2>
<pre><?php
$log = ini_get('error_log');
echo 'error_log path: ' . htmlspecialchars($log ?: 'system default') . "\n\n";
if ($log && is_readable($log)) {
    $content = @file_get_contents($log);
    if ($content) {
        $lines = array_slice(explode("\n", $content), -50);
        echo htmlspecialchars(implode("\n", $lines));
    }
} else {
    echo '(log unreadable from here — check cPanel → Errors)';
}
?></pre>

</body>
</html>
