<?php

/**
 * SHF marketing site — page bootstrap.
 * Included at the top of every .php page. Exposes:
 *   $assetVersion  — cache-bust token for CSS/JS/favicon URLs
 *   $pageSlug      — current filename (no extension) — inferred if unset
 *   $pageSeo       — per-page SEO data (from _seo-pages.php)
 *   Site-wide SEO constants ($siteUrl, $siteName, etc.)
 */

/* ---------- Asset cache-bust version ---------- */
$__shfAssetVersionFile = __DIR__.'/asset-version.txt';
if (is_file($__shfAssetVersionFile) && is_readable($__shfAssetVersionFile)) {
    $assetVersion = trim((string) @file_get_contents($__shfAssetVersionFile));
}
if (empty($assetVersion)) {
    /* Fallback: derive from CSS mtime (stable across requests → browsers
       still cache). Last-resort fallback is a random per-process token. */
    $cssPath = __DIR__.'/assets/css/site.css';
    if (is_file($cssPath)) {
        $assetVersion = substr(dechex((int) filemtime($cssPath)), -10);
    } else {
        try {
            $assetVersion = bin2hex(random_bytes(5));
        } catch (Throwable $e) {
            $assetVersion = substr(str_replace('.', '', uniqid('', true)), -10);
        }
    }
}
unset($__shfAssetVersionFile);

/* ---------- Site-wide SEO data ---------- */
$siteUrl = 'https://shfworld.com';
$siteBasePath = '';                                 // empty = site served at domain root (e.g. shfworld.com/contact)
// set to '/website' only if the site is served from a subfolder
$siteName = 'Shreenathji Home Finance';
$siteShortName = 'SHF';
$siteTagline = 'Shaping Happiness Forever';
$siteLogo = $siteUrl.$siteBasePath.'/assets/img/logo.png';
$siteDefaultImage = $siteUrl.$siteBasePath.'/assets/img/logo.png';
$sitePhoneSales = '+91-99747-89089';
$sitePhoneCare = '+91-90990-89072';
$siteEmail = 'info@shfworld.com';
$siteStreet = 'Office No 911, R K Prime, Silver Height';
$siteLocality = 'Rajkot';
$siteRegion = 'Gujarat';
$sitePostalCode = '360004';
$siteCountry = 'IN';
$siteLatitude = '22.2735';
$siteLongitude = '70.7894';
$siteFoundingYear = '2015';
$siteOpeningHours = 'Mo-Sa 10:00-19:00';
$siteSocial = [
    'https://www.facebook.com/ShreenathjiHomeFinance/',
    'https://www.instagram.com/shreenathjihomefinance/',
];

/* Aggregate rating — replace with real Google-Business-Profile numbers.
   Google may display these as review-stars in rich results if sufficient
   external review signals exist for the business. */
$aggregateRating = [
    'ratingValue' => '5.0',
    'reviewCount' => '23',
    'bestRating' => '5',
    'worstRating' => '4.5',
];

/* Link to the business' Google reviews page — fill this in when available.
   Put the "share review" URL from Google Business Profile here. */
$googleReviewsUrl = 'https://g.page/r/CRpLaceholderReviewsUrl/review';

/* ---------- Detect current page slug ---------- */
if (! isset($pageSlug) || $pageSlug === '') {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
    $pageSlug = $script !== '' ? $script : 'index';
}

/* ---------- Load per-page SEO config ---------- */
$__seoAll = require __DIR__.'/_seo-pages.php';
$pageSeo = $__seoAll[$pageSlug] ?? $__seoAll['index'];
unset($__seoAll);

/* ---------- Derived URLs ---------- */
$canonicalPath = $pageSlug === 'index' ? '/' : '/'.$pageSlug;
$canonicalUrl = $siteUrl.$siteBasePath.$canonicalPath;

/* ---------- Config-driven flags (Turnstile + search-engine indexing) ---------- */
$turnstileSiteKey = '';
$rankSite         = 1;            // default: indexable
$abTestEnabled    = false;
$__cfg = @include __DIR__.'/config.php';
if (is_array($__cfg)) {
    if (!empty($__cfg['TURNSTILE_SITE_KEY'])) {
        $turnstileSiteKey = (string) $__cfg['TURNSTILE_SITE_KEY'];
    }
    if (array_key_exists('RANK_SITE', $__cfg)) {
        $rankSite = (int) $__cfg['RANK_SITE'];
    }
    $abTestEnabled = (bool) ($__cfg['AB_TEST_ENABLED'] ?? false);
}
unset($__cfg);

/* Emit X-Robots-Tag header when indexing is disabled — catches assets + any
   non-HTML response where meta tags don't apply. Only call if headers aren't
   already sent (pages that output before this is reached will no-op). */
if ($rankSite !== 1 && !headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow, noarchive', true);
}
