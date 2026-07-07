<?php
/**
 * SHF marketing site — dynamic robots.txt.
 *
 * Output depends on the RANK_SITE flag in config.php:
 *   1 → normal Allow-all + Disallow internal paths + Sitemap reference
 *   0 → Disallow everything (staging / dev mode)
 *
 * Reached via .htaccess rewrite:  /robots.txt  →  /robots.php
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$config   = @include __DIR__ . '/config.php';
$rankSite = is_array($config) && array_key_exists('RANK_SITE', $config)
    ? (int) $config['RANK_SITE']
    : 1;

if ($rankSite !== 1) {
    // Indexing disabled — block all crawlers from everything.
    echo "User-agent: *\n";
    echo "Disallow: /\n";
    echo "\n";
    echo "# Indexing is currently disabled via RANK_SITE=0 in config.php\n";
    exit;
}

$host = $_SERVER['HTTP_HOST'] ?? 'shfworld.com';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
       || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        ? 'https' : 'http';

echo "User-agent: *\n";
echo "Allow: /\n";
echo "\n";
echo "# Block internal / sensitive files\n";
echo "Disallow: /config.php\n";
echo "Disallow: /_bootstrap.php\n";
echo "Disallow: /_seo-pages.php\n";
echo "Disallow: /_seo-head.php\n";
echo "Disallow: /_seo-foot.php\n";
echo "Disallow: /_header.php\n";
echo "Disallow: /_footer.php\n";
echo "Disallow: /_diagnose.php\n";
echo "Disallow: /_smtp-test.php\n";
echo "Disallow: /_disposable-email-domains.txt\n";
echo "Disallow: /asset-version.txt\n";
echo "Disallow: /cache-bust.php\n";
echo "Disallow: /cache-bust\n";
echo "Disallow: /contact-submit.php\n";
echo "Disallow: /contact-submit\n";
echo "Disallow: /_rate-cache/\n";
echo "\n";
echo "Allow: /assets/\n";
echo "\n";
echo "Sitemap: {$scheme}://{$host}/sitemap.xml\n";
