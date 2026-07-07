<?php
/**
 * Pure-PHP SMTP contact form handler (Gmail SMTP, TLS).
 * No Composer / PHPMailer dependency — speaks SMTP directly via fsockopen.
 *
 * Expects POST fields: name, email, phone, loan_type, loan_amount, city, message
 * Returns JSON: { ok: bool, message: string }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(bool $ok, string $message, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.', 405);
}

$config = require __DIR__ . '/config.php';

if (empty($config['SMTP_USER']) || empty($config['SMTP_PASS'])) {
    respond(false, 'Mail service is not configured yet. Please call us directly.', 500);
}

/* ---------- Input sanitisation ---------- */
$name       = trim((string) ($_POST['name'] ?? ''));
$email      = trim((string) ($_POST['email'] ?? ''));
$phone      = trim((string) ($_POST['phone'] ?? ''));
$loanType   = trim((string) ($_POST['loan_type'] ?? ''));
$loanAmount = trim((string) ($_POST['loan_amount'] ?? ''));
$city       = trim((string) ($_POST['city'] ?? ''));
$message    = trim((string) ($_POST['message'] ?? ''));

/* ========================================================================
 * ANTI-SPAM & ABUSE PREVENTION
 * ==================================================================== */

$clientIp = $_SERVER['HTTP_CF_CONNECTING_IP']    // Cloudflare
         ?? $_SERVER['HTTP_X_FORWARDED_FOR']     // proxy / load balancer (first entry only)
         ?? $_SERVER['REMOTE_ADDR']
         ?? '0.0.0.0';
$clientIp = trim(explode(',', $clientIp)[0]);
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');

/* Silent-fail logger for spam events — doesn't alert the user, just logs. */
$logSpam = function (string $reason) use ($clientIp, $userAgent, $email): void {
    error_log(sprintf(
        '[SHF contact-spam] reason=%s ip=%s ua=%s email=%s',
        $reason,
        $clientIp,
        substr($userAgent, 0, 80),
        $email
    ));
};

/* --- 1. Honeypot (hidden fields bots tend to fill) --- */
if (!empty($_POST['website'] ?? '') || !empty($_POST['url'] ?? '') || !empty($_POST['fax'] ?? '')) {
    $logSpam('honeypot');
    // Fake success so bots think they succeeded and move on
    respond(true, 'Thank you. We will contact you shortly.');
}

/* --- 2. Minimum form-fill time (bots submit instantly) --- */
$formTs = (int) ($_POST['form_ts'] ?? 0);
$elapsed = time() - $formTs;
if ($formTs > 0 && $elapsed < 3) {
    $logSpam('too-fast (' . $elapsed . 's)');
    respond(true, 'Thank you. We will contact you shortly.');
}
/* Also reject if the timestamp is absurdly old (form sat open > 6 hrs) */
if ($formTs > 0 && $elapsed > 21600) {
    $logSpam('too-old');
    respond(false, 'This form has expired. Please reload the page and try again.', 419);
}

/* --- 3. Method / referer sanity --- */
$referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
$host    = (string) ($_SERVER['HTTP_HOST'] ?? '');
if ($referer !== '' && $host !== '' && strpos($referer, '://' . $host) === false
                                     && strpos($referer, '://www.' . $host) === false) {
    $logSpam('foreign-referer: ' . substr($referer, 0, 80));
    respond(false, 'Request blocked.', 403);
}

/* --- 4. Rate limits — IP (5/hr), email (3/24h), phone (3/24h) --- */
/* Stored inside the project folder so backups / snapshots capture it and
 * cache-clearing is trivial. Web access to this folder is blocked by .htaccess. */
$rateDir = __DIR__ . '/_rate-cache';
if (!is_dir($rateDir)) { @mkdir($rateDir, 0700, true); }

/** Generic file-based sliding-window rate-limit helper.
 *  Returns true if request should be blocked. */
$rateHit = function (string $bucket, string $key, int $max, int $windowSec) use ($rateDir): bool {
    $file = $rateDir . '/' . $bucket . '-' . hash('sha256', $key) . '.log';
    $recent = [];
    if (is_file($file)) {
        $recent = array_filter(
            array_map('intval', file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []),
            fn (int $t) => $t > time() - $windowSec
        );
    }
    if (count($recent) >= $max) {
        return true;
    }
    $recent[] = time();
    @file_put_contents($file, implode("\n", $recent));
    return false;
};

/* Read rate-limit thresholds from config (with sensible defaults).
 * Setting MAX = 0 for any bucket disables that limit entirely. */
$ipMax       = (int) ($config['RATE_LIMIT_IP_MAX']       ?? 5);
$ipWindow    = (int) ($config['RATE_LIMIT_IP_WINDOW']    ?? 3600);
$emailMax    = (int) ($config['RATE_LIMIT_EMAIL_MAX']    ?? 3);
$emailWindow = (int) ($config['RATE_LIMIT_EMAIL_WINDOW'] ?? 86400);
$phoneMax    = (int) ($config['RATE_LIMIT_PHONE_MAX']    ?? 3);
$phoneWindow = (int) ($config['RATE_LIMIT_PHONE_WINDOW'] ?? 86400);

if ($ipMax > 0 && $rateHit('ip', $clientIp, $ipMax, $ipWindow)) {
    $logSpam('rate-limit-ip');
    respond(false, 'Too many submissions from your network. Please try again in a little while.', 429);
}
if ($emailMax > 0 && !empty($email) && $rateHit('email', strtolower($email), $emailMax, $emailWindow)) {
    $logSpam('rate-limit-email');
    respond(false, 'You have already submitted several enquiries today. We will reach out shortly.', 429);
}
if ($phoneMax > 0 && !empty($phoneDigits = preg_replace('/\D+/', '', $phone)) && $rateHit('phone', $phoneDigits, $phoneMax, $phoneWindow)) {
    $logSpam('rate-limit-phone');
    respond(false, 'We have received multiple enquiries from this phone number. Please wait for our advisor to call you back.', 429);
}

$errors = [];
if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 80) {
    $errors[] = 'Valid name is required.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required.';
}
$phoneDigits = preg_replace('/\D+/', '', $phone);
if (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15) {
    $errors[] = 'Valid phone is required.';
}
if ($message === '' || mb_strlen($message) < 5 || mb_strlen($message) > 4000) {
    $errors[] = 'A brief but meaningful message is required.';
}
if ($errors) {
    respond(false, implode(' ', $errors), 422);
}

/* --- 5. Content-based spam filters --- */

/* Reject messages with too many URLs — classic SEO/link-spam pattern */
$urlCount = preg_match_all('~https?://|www\.~i', $message);
if ($urlCount > 2) {
    $logSpam('too-many-urls=' . $urlCount);
    respond(true, 'Thank you. We will contact you shortly.');
}

/* Reject common spam-keyword patterns (word-boundary match to avoid false positives) */
$spamPattern = '~\b(viagra|cialis|casino|crypto(?:currency)?|bitcoin|forex|seo\s+services|weight\s+loss|xxx|porn|escort|\$\$\$)\b~i';
if (preg_match($spamPattern, $message . ' ' . $name . ' ' . $city)) {
    $logSpam('keyword-match');
    respond(true, 'Thank you. We will contact you shortly.');
}

/* Reject messages that contain Cyrillic / CJK scripts (we serve English + Gujarati only) */
if (preg_match('/[\x{0400}-\x{04FF}\x{3000}-\x{9FFF}]/u', $message)) {
    $logSpam('non-latin-script');
    respond(true, 'Thank you. We will contact you shortly.');
}

/* Name should not be all one character or repetitive gibberish */
if (preg_match('~^(.)\1{3,}$~', $name)) {
    $logSpam('gibberish-name');
    respond(true, 'Thank you. We will contact you shortly.');
}

/* Obvious bot user-agents — reject if clearly automated */
if ($userAgent === '' || preg_match('~(curl|wget|python-requests|scrapy|bot(?!ify)|spider|crawler|phantomjs)~i', $userAgent)) {
    $logSpam('bot-user-agent: ' . substr($userAgent, 0, 60));
    respond(true, 'Thank you. We will contact you shortly.');
}

/* --- 6. MX record check on email domain --- */
$emailDomain = strtolower((string) substr(strrchr($email, '@'), 1));
if ($emailDomain === '' || !checkdnsrr($emailDomain, 'MX')) {
    $logSpam('no-mx-record: ' . $emailDomain);
    respond(false, 'That email domain does not seem to accept mail. Please double-check your address.', 422);
}

/* --- 7. Disposable / throwaway email block --- */
$disposableListFile = __DIR__ . '/_disposable-email-domains.txt';
if (is_file($disposableListFile)) {
    $lines = @file($disposableListFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $disposable = [];
    foreach ($lines as $l) {
        $l = trim($l);
        if ($l !== '' && !str_starts_with($l, '#')) {
            $disposable[strtolower($l)] = true;
        }
    }
    if (isset($disposable[$emailDomain])) {
        $logSpam('disposable-email: ' . $emailDomain);
        respond(false, 'Disposable / throwaway email addresses are not accepted. Please use your primary email.', 422);
    }
}

/* --- 8. Cloudflare Turnstile verification --- */
$turnstileSecret = trim((string) ($config['TURNSTILE_SECRET_KEY'] ?? ''));
if ($turnstileSecret !== '') {
    $turnstileToken = (string) ($_POST['cf-turnstile-response'] ?? '');
    if ($turnstileToken === '') {
        $logSpam('turnstile-missing-token');
        respond(false, 'Human verification failed. Please reload the page and try again.', 400);
    }
    $verifyPayload = http_build_query([
        'secret'   => $turnstileSecret,
        'response' => $turnstileToken,
        'remoteip' => $clientIp,
    ]);
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $verifyPayload,
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);
    $verifyResponse = @file_get_contents(
        'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        false,
        $ctx
    );
    $verifyData = $verifyResponse ? json_decode($verifyResponse, true) : null;
    if (!is_array($verifyData) || empty($verifyData['success'])) {
        $logSpam('turnstile-failed: ' . substr(json_encode($verifyData), 0, 120));
        respond(false, 'Human verification failed. Please refresh the page and try again.', 403);
    }
}

/* ---------- Build message ---------- */
$safe = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');

$subject = ($config['MAIL_SUBJECT_PREFIX'] ?? '') . 'New enquiry from ' . $name;

$submittedAt = date('d M Y, h:i A');

$htmlBody = '<html><body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#222;line-height:1.55;">'
    . '<div style="max-width:620px;margin:20px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,0.08);">'

    /* Header */
    . '<div style="background:linear-gradient(135deg,#f15a29 0%,#f47929 100%);padding:30px 32px;text-align:center;">'
    . '<h2 style="color:#fff;margin:0;font-size:22px;letter-spacing:0.5px;font-weight:700;">Shreenathji Home Finance (SHF)</h2>'
    . '<p style="color:rgba(255,255,255,0.95);margin:6px 0 0;font-size:12px;letter-spacing:2px;">— SHAPING HAPPINESS FOREVER —</p>'
    . '</div>'

    /* Alert banner */
    . '<div style="background:#fff4ef;border-left:4px solid #f15a29;padding:14px 24px;margin:0;">'
    . '<p style="margin:0;font-size:14px;color:#3a3536;"><strong style="color:#f15a29;">New Website Enquiry</strong> &nbsp;·&nbsp; ' . $submittedAt . '</p>'
    . '</div>'

    /* Body */
    . '<div style="padding:28px 32px;">'
    . '<p style="margin:0 0 18px;font-size:15px;">A new enquiry has come in through the SHF website contact form. Details below:</p>'

    . '<table cellpadding="10" cellspacing="0" style="border-collapse:collapse;width:100%;font-size:14px;border:1px solid #eee;">'
    . '<tr><td style="background:#f8f8f8;width:32%;border-bottom:1px solid #eee;"><strong>Name</strong></td><td style="border-bottom:1px solid #eee;">' . $safe($name) . '</td></tr>'
    . '<tr><td style="background:#f8f8f8;border-bottom:1px solid #eee;"><strong>Email</strong></td><td style="border-bottom:1px solid #eee;"><a href="mailto:' . $safe($email) . '" style="color:#f15a29;text-decoration:none;">' . $safe($email) . '</a></td></tr>'
    . '<tr><td style="background:#f8f8f8;border-bottom:1px solid #eee;"><strong>Phone</strong></td><td style="border-bottom:1px solid #eee;"><a href="tel:' . $safe($phone) . '" style="color:#f15a29;text-decoration:none;">' . $safe($phone) . '</a></td></tr>'
    . ($city !== ''       ? '<tr><td style="background:#f8f8f8;border-bottom:1px solid #eee;"><strong>City</strong></td><td style="border-bottom:1px solid #eee;">' . $safe($city) . '</td></tr>' : '')
    . ($loanType !== ''   ? '<tr><td style="background:#f8f8f8;border-bottom:1px solid #eee;"><strong>Loan Type</strong></td><td style="border-bottom:1px solid #eee;">' . $safe($loanType) . '</td></tr>' : '')
    . ($loanAmount !== '' ? '<tr><td style="background:#f8f8f8;border-bottom:1px solid #eee;"><strong>Loan Amount</strong></td><td style="border-bottom:1px solid #eee;">' . $safe($loanAmount) . '</td></tr>' : '')
    . '<tr><td style="background:#f8f8f8;vertical-align:top;"><strong>Message</strong></td><td>' . nl2br($safe($message)) . '</td></tr>'
    . '</table>'

    /* Quick action buttons */
    . '<div style="margin:24px 0 8px;text-align:center;">'
    . '<a href="tel:' . $safe($phone) . '" style="display:inline-block;background:#f15a29;color:#fff;text-decoration:none;padding:10px 22px;border-radius:999px;font-weight:600;font-size:13px;margin:4px 6px;">Call ' . $safe($name) . '</a>'
    . '<a href="https://wa.me/' . preg_replace('/\D+/', '', $phone) . '" style="display:inline-block;background:#25D366;color:#fff;text-decoration:none;padding:10px 22px;border-radius:999px;font-weight:600;font-size:13px;margin:4px 6px;">WhatsApp</a>'
    . '<a href="mailto:' . $safe($email) . '" style="display:inline-block;background:#3a3536;color:#fff;text-decoration:none;padding:10px 22px;border-radius:999px;font-weight:600;font-size:13px;margin:4px 6px;">Reply by Email</a>'
    . '</div>'

    . '<p style="color:#888;font-size:12px;margin:22px 0 0;text-align:center;">Respond within one business day per SHF policy.</p>'
    . '</div>'

    /* Footer */
    . '<div style="background:#1f1c1d;color:rgba(255,255,255,0.75);padding:22px 32px;font-size:12px;">'
    . '<p style="margin:0 0 10px;"><strong style="color:#fff;font-size:14px;">Shreenathji Home Finance</strong></p>'
    . '<p style="margin:0 0 6px;">Office No 911, R K Prime, Silver Height, Near Nana Mava Circle, 150 Ft Ring Road, Rajkot, Gujarat 360004</p>'
    . '<p style="margin:0 0 6px;"><strong style="color:#f99d3e;">Sales &amp; Enquiry:</strong> +91 99747 89089 &nbsp;&nbsp; <strong style="color:#f99d3e;">Customer Care:</strong> +91 90990 89072</p>'
    . '<p style="margin:0;">info@shfworld.com &nbsp;|&nbsp; www.shfworld.com</p>'
    . '</div>'

    . '</div>'
    . '</body></html>';

$textBody = "SHREENATHJI HOME FINANCE (SHF)\n"
    . "— Shaping Happiness Forever —\n"
    . "================================\n\n"
    . "NEW WEBSITE ENQUIRY · $submittedAt\n\n"
    . "Name:        $name\n"
    . "Email:       $email\n"
    . "Phone:       $phone\n"
    . ($city !== ''       ? "City:        $city\n"       : '')
    . ($loanType !== ''   ? "Loan Type:   $loanType\n"   : '')
    . ($loanAmount !== '' ? "Loan Amount: $loanAmount\n" : '')
    . "\nMessage:\n$message\n\n"
    . "--------------------------------\n"
    . "Respond within one business day per SHF policy.\n\n"
    . "Shreenathji Home Finance\n"
    . "Office No 911, R K Prime, Silver Height, Near Nana Mava Circle,\n"
    . "150 Ft Ring Road, Rajkot, Gujarat 360004\n"
    . "Sales & Enquiry: +91 99747 89089  |  Customer Care: +91 90990 89072\n"
    . "info@shfworld.com  |  www.shfworld.com\n";

/* ---------- Customer confirmation email ---------- */
$customerSubject = 'We received your enquiry — Shreenathji Home Finance (SHF)';

$customerHtmlBody = '<html><body style="font-family:Arial,sans-serif;color:#222;line-height:1.6;">'
    . '<div style="max-width:600px;margin:0 auto;">'
    . '<div style="background:linear-gradient(135deg,#f15a29 0%,#f47929 100%);padding:28px 30px;text-align:center;">'
    . '<h2 style="color:#fff;margin:0;font-family:Arial,sans-serif;font-size:22px;letter-spacing:0.5px;">Shreenathji Home Finance (SHF)</h2>'
    . '<p style="color:rgba(255,255,255,0.95);margin:6px 0 0;font-size:12px;letter-spacing:2px;">— SHAPING HAPPINESS FOREVER —</p>'
    . '</div>'
    . '<div style="background:#fff;padding:30px;border:1px solid #eee;border-top:0;">'
    . '<p>Hi ' . $safe($name) . ',</p>'
    . '<p>Thank you for reaching out to <strong>Shreenathji Home Finance (SHF)</strong>. We have received your enquiry and one of our advisors will get back to you within <strong>one business day</strong>.</p>'
    . '<p>For quick reference, here\'s a summary of what you shared with us:</p>'
    . '<table cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;margin:16px 0;">'
    . '<tr><td style="background:#f8f8f8;width:30%;"><strong>Name</strong></td><td>' . $safe($name) . '</td></tr>'
    . '<tr><td style="background:#f8f8f8;"><strong>Phone</strong></td><td>' . $safe($phone) . '</td></tr>'
    . ($city !== ''       ? '<tr><td style="background:#f8f8f8;"><strong>City</strong></td><td>' . $safe($city) . '</td></tr>' : '')
    . ($loanType !== ''   ? '<tr><td style="background:#f8f8f8;"><strong>Loan Type</strong></td><td>' . $safe($loanType) . '</td></tr>' : '')
    . ($loanAmount !== '' ? '<tr><td style="background:#f8f8f8;"><strong>Loan Amount</strong></td><td>' . $safe($loanAmount) . '</td></tr>' : '')
    . '<tr><td style="background:#f8f8f8;vertical-align:top;"><strong>Message</strong></td><td>' . nl2br($safe($message)) . '</td></tr>'
    . '</table>'
    . '<p>If your requirement is time-sensitive, feel free to call or WhatsApp us directly:</p>'
    . '<ul style="padding-left:20px;">'
    . '<li><strong>Sales &amp; Enquiry:</strong> <a href="tel:+919974789089" style="color:#f15a29;">+91 99747 89089</a></li>'
    . '<li><strong>Customer Care:</strong> <a href="tel:+919099089072" style="color:#f15a29;">+91 90990 89072</a></li>'
    . '<li><strong>WhatsApp:</strong> <a href="https://wa.me/919099089072" style="color:#f15a29;">Chat on WhatsApp</a></li>'
    . '</ul>'
    . '<p>Warm regards,<br><strong>Team SHF</strong></p>'
    . '</div>'
    . '<div style="background:#1f1c1d;color:rgba(255,255,255,0.7);padding:18px 30px;font-size:12px;">'
    . '<p style="margin:0 0 6px;"><strong style="color:#fff;">Shreenathji Home Finance</strong></p>'
    . '<p style="margin:0 0 4px;">Office No 911, R K Prime, Silver Height, Near Nana Mava Circle, 150 Ft Ring Road, Rajkot, Gujarat 360004</p>'
    . '<p style="margin:0;">www.shfworld.com &nbsp;|&nbsp; info@shfworld.com</p>'
    . '</div>'
    . '</div>'
    . '</body></html>';

$customerTextBody = "Hi $name,\n\n"
    . "Thank you for reaching out to Shreenathji Home Finance (SHF). We have received your enquiry "
    . "and one of our advisors will get back to you within one business day.\n\n"
    . "Summary of what you shared with us:\n"
    . "-----------------------------------\n"
    . "Name:        $name\n"
    . "Phone:       $phone\n"
    . ($city !== ''       ? "City:        $city\n"       : '')
    . ($loanType !== ''   ? "Loan Type:   $loanType\n"   : '')
    . ($loanAmount !== '' ? "Loan Amount: $loanAmount\n" : '')
    . "\nMessage:\n$message\n\n"
    . "For time-sensitive enquiries, reach us directly:\n"
    . "  Sales & Enquiry: +91 99747 89089\n"
    . "  Customer Care:   +91 90990 89072\n"
    . "  WhatsApp:        https://wa.me/919099089072\n\n"
    . "Warm regards,\n"
    . "Team SHF\n\n"
    . "Shreenathji Home Finance — Shaping Happiness Forever\n"
    . "Office No 911, R K Prime, Silver Height, Near Nana Mava Circle, 150 Ft Ring Road, Rajkot, Gujarat 360004\n";

/* ---------- Minimal SMTP client ---------- */
class SmtpClient
{
    private $socket;

    /** @var array{SMTP_HOST:string,SMTP_PORT:int,SMTP_SECURE:string,SMTP_USER:string,SMTP_PASS:string} $cfg */
    public function __construct(private array $cfg) {}

    public function send(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $textBody,
        string $htmlBody,
        ?string $replyTo = null,
        ?string $replyToName = null,
    ): void {
        $host = $this->cfg['SMTP_HOST'];
        $port = (int) $this->cfg['SMTP_PORT'];
        $secure = strtolower((string) $this->cfg['SMTP_SECURE']);

        $transport = $secure === 'ssl' ? 'ssl://' : '';
        $errno = 0; $errstr = '';
        $this->socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 15);
        if (!$this->socket) {
            throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
        }
        stream_set_timeout($this->socket, 15);

        $this->expect(220);
        $this->cmd('EHLO ' . $this->clientHostname(), 250);

        if ($secure === 'tls') {
            $this->cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Failed to enable TLS.');
            }
            $this->cmd('EHLO ' . $this->clientHostname(), 250);
        }

        $this->cmd('AUTH LOGIN', 334);
        $this->cmd(base64_encode($this->cfg['SMTP_USER']), 334);
        $this->cmd(base64_encode($this->cfg['SMTP_PASS']), 235);

        $this->cmd('MAIL FROM:<' . $fromEmail . '>', 250);
        $this->cmd('RCPT TO:<' . $toEmail . '>', 250);
        $this->cmd('DATA', 354);

        $boundary = 'shf-' . bin2hex(random_bytes(8));
        $date = date('r');
        $messageId = '<' . bin2hex(random_bytes(12)) . '@' . $this->clientHostname() . '>';

        $headers  = "From: " . $this->encodeName($fromName) . " <$fromEmail>\r\n";
        $headers .= "To: " . $this->encodeName($toName) . " <$toEmail>\r\n";
        if ($replyTo) {
            $headers .= 'Reply-To: ' . ($replyToName ? $this->encodeName($replyToName) . " <$replyTo>" : "<$replyTo>") . "\r\n";
        }
        $headers .= 'Subject: ' . $this->encodeHeader($subject) . "\r\n";
        $headers .= "Date: $date\r\n";
        $headers .= "Message-ID: $messageId\r\n";
        $headers .= "MIME-Version: 1.0\r\n";

        /* Deliverability / anti-spam-folder headers:
         * - X-Mailer identifies the sender software
         * - X-Priority 3 = normal (spammers often abuse High/Low)
         * - Auto-Submitted: auto-generated signals transactional nature
         * - Precedence: bulk-like classification
         * - X-Entity-Ref-ID + X-SHF-Source help Gmail/Outlook threading */
        $headers .= "X-Mailer: SHF-Website-Mailer/1.0\r\n";
        $headers .= "X-Priority: 3\r\n";
        $headers .= "X-SHF-Source: website-contact-form\r\n";
        $headers .= "Auto-Submitted: auto-generated\r\n";

        $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";

        $body  = "--$boundary\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody . "\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $htmlBody . "\r\n";
        $body .= "--$boundary--\r\n";

        // Dot-stuffing per RFC 5321
        $payload = preg_replace('/^\./m', '..', $headers . "\r\n" . $body);
        $this->write($payload . "\r\n.\r\n");
        $this->expect(250);

        $this->cmd('QUIT', 221);
        fclose($this->socket);
    }

    private function cmd(string $line, int $expected): void
    {
        $this->write($line . "\r\n");
        $this->expect($expected);
    }

    private function write(string $data): void
    {
        if (fwrite($this->socket, $data) === false) {
            throw new RuntimeException('SMTP write failed.');
        }
    }

    private function expect(int $code): string
    {
        $response = '';
        while (!feof($this->socket)) {
            $line = fgets($this->socket, 1024);
            if ($line === false) { break; }
            $response .= $line;
            // Continuation line (4th char is '-'); final line has a space.
            if (isset($line[3]) && $line[3] === ' ') { break; }
        }
        $actual = (int) substr(ltrim($response), 0, 3);
        if ($actual !== $code) {
            throw new RuntimeException("SMTP expected $code, got: " . trim($response));
        }
        return $response;
    }

    private function clientHostname(): string
    {
        return gethostname() ?: 'localhost';
    }

    private function encodeHeader(string $text): string
    {
        return preg_match('/[^\x20-\x7E]/', $text)
            ? '=?UTF-8?B?' . base64_encode($text) . '?='
            : $text;
    }

    private function encodeName(string $name): string
    {
        return preg_match('/[^\x20-\x7E]/', $name)
            ? $this->encodeHeader($name)
            : '"' . addslashes($name) . '"';
    }
}

$fromEmail = $config['MAIL_FROM_EMAIL'] !== '' ? $config['MAIL_FROM_EMAIL'] : $config['SMTP_USER'];
$fromName  = $config['MAIL_FROM_NAME'] ?? 'SHF Website';

/* ---- 1. Send admin notification (this is the critical one) ---- */
try {
    $adminClient = new SmtpClient($config);
    $adminClient->send(
        $fromEmail,
        $fromName,
        $config['MAIL_TO'],
        $config['MAIL_TO_NAME'] ?? '',
        $subject,
        $textBody,
        $htmlBody,
        $email,
        $name,
    );
} catch (Throwable $e) {
    /* Log detailed failure so you can actually debug the cause.
     * Look in cPanel → Errors OR tail ~/logs/error_log OR /var/log/apache2/error.log */
    error_log(sprintf(
        '[SHF Website Contact — admin] %s (at %s:%d)',
        $e->getMessage(),
        basename($e->getFile()),
        $e->getLine()
    ));

    /* Show the actual error when the config enables debug mode.
     * Flip SMTP_DEBUG to true in config.php temporarily to see it in browser. */
    $showDetail = !empty($config['SMTP_DEBUG']);
    $detail = $showDetail ? ' [' . $e->getMessage() . ']' : '';

    respond(false,
        'We could not send your message right now. Please call SHF at +91 99747 89089 (Sales) or +91 90990 89072 (Customer Care).' . $detail,
        500
    );
}

/* ---- 2. Send customer confirmation copy (best-effort; don't fail the request if this breaks) ---- */
try {
    $customerClient = new SmtpClient($config);
    $customerClient->send(
        $fromEmail,
        $fromName,
        $email,
        $name,
        $customerSubject,
        $customerTextBody,
        $customerHtmlBody,
        $config['MAIL_TO'],
        $config['MAIL_TO_NAME'] ?? 'Shreenathji Home Finance',
    );
} catch (Throwable $e) {
    error_log('[SHF Website Contact — customer copy] ' . $e->getMessage());
    // Intentionally not failing the user-facing response — admin already has the lead.
}

respond(true, 'Thank you! Your enquiry has been sent — a confirmation copy is on its way to your inbox. We will reach out to you within one business day.');
