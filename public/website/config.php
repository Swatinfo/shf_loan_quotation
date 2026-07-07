<?php

/**
 * Shreenathji Home Finance — Contact form SMTP config
 * Fill in your Gmail SMTP credentials below.
 *
 * Gmail setup steps:
 *   1. Enable 2-Step Verification on the Gmail account that will send mail:
 *      https://myaccount.google.com/security
 *   2. Create an App Password (16-character code):
 *      https://myaccount.google.com/apppasswords
 *   3. Paste the Gmail address into SMTP_USER and the App Password into SMTP_PASS.
 *   4. Set MAIL_TO to the inbox that should receive contact-form submissions.
 */

return [
    'SMTP_HOST' => 'smtp.gmail.com',
    'SMTP_PORT' => 465,            // 465 = SSL (implicit TLS), 587 = STARTTLS
    'SMTP_SECURE' => 'ssl',        // 'ssl' or 'tls' — must match the port above
    'SMTP_USER' => 'info@shfworld.com',             // e.g. yourname@gmail.com
    'SMTP_PASS' => 'uhmaiitfgqndjwmy',             // 16-char Gmail App Password (no spaces)

    'MAIL_FROM_NAME' => 'SHF Website',
    'MAIL_FROM_EMAIL' => '',       // usually the same as SMTP_USER

    'MAIL_TO' => 'info@shfworld.com',   // where contact-form submissions are delivered
    'MAIL_TO_NAME' => 'Shreenathji Home Finance',

    'MAIL_SUBJECT_PREFIX' => '[SHF Website] ',

    /* Flip to true to surface the underlying SMTP error in the browser
     * response when a send fails. Useful when debugging deployment issues.
     * Set back to false before going live. */
    'SMTP_DEBUG' => false,

    /* ---------- Cloudflare Turnstile (anti-spam CAPTCHA) ---------- */
    /* Get these at: https://dash.cloudflare.com → Turnstile → Add site.
       Leave blank to disable Turnstile (falls back to existing honeypot etc.). */
    // 0x4AAAAAADCcqosOnab5HRp6
    'TURNSTILE_SITE_KEY' => '',
    // 0x4AAAAAADCcqveP6wymT3oyL8IGY1EJM64
    'TURNSTILE_SECRET_KEY' => '',

    /* ---------- Contact-form rate limits ----------
       How many submissions can come from the same IP / email / phone within
       the given time window before the form starts rejecting them.
       Time windows are in SECONDS.
         3600  = 1 hour
         86400 = 24 hours
       Tune higher if you get legitimate repeat customers; lower if you see
       spam patterns sneaking through. Set MAX = 0 to disable that limit. */
    'RATE_LIMIT_IP_MAX' => 5,
    'RATE_LIMIT_IP_WINDOW' => 3600,    //  5 submissions per 1 hour per IP
    'RATE_LIMIT_EMAIL_MAX' => 3,
    'RATE_LIMIT_EMAIL_WINDOW' => 86400,   //  3 submissions per 24 hours per email
    'RATE_LIMIT_PHONE_MAX' => 3,
    'RATE_LIMIT_PHONE_WINDOW' => 86400,   //  3 submissions per 24 hours per phone

    /* ---------- A/B landing-page test ----------
       true  — A/B test is LIVE: each visitor is randomly assigned variant A
               (original index hero) or variant B (new rotating-hero index2),
               persisted in a 7-day cookie. The small A/B switcher pill also
               appears on index2 so you can jump between variants.
       false — A/B test is OFF: every visitor gets the original index.php.
               Switcher is hidden. /index2 is still reachable directly. */
    'AB_TEST_ENABLED' => true,

    /* ---------- Search-engine indexing ----------
       1 — Allow search engines to crawl & index the site (production).
       0 — Block all indexing (development / staging). Emits `noindex,nofollow`
           meta tag on every page, serves a Disallow-all robots.txt, and
           adds an `X-Robots-Tag: noindex, nofollow` response header. */
    'RANK_SITE' => 0,
];
