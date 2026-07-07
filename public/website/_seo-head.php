<?php
/**
 * SHF marketing site — <head> SEO block.
 * Included right before </head> on every page. Outputs:
 *   - Canonical link + hreflang
 *   - Keywords, author, robots, geo-targeting
 *   - Open Graph tags (Facebook, LinkedIn)
 *   - Twitter Card tags
 *
 * Expects these variables to be defined by _bootstrap.php:
 *   $pageSeo, $canonicalUrl, $siteName, $siteShortName, $siteDefaultImage
 */

$__ogType   = $pageSeo['ogType']   ?? 'website';
$__ogTitle  = $pageSeo['title']    ?? ($siteName . ' — ' . $siteTagline);
$__ogDesc   = $pageSeo['description'] ?? '';
$__keywords = $pageSeo['keywords'] ?? '';
$__robots   = $pageSeo['robots']   ?? 'index, follow, max-image-preview:large, max-snippet:-1';
$__ogImage  = $pageSeo['image']    ?? $siteDefaultImage;

/* Global override: if RANK_SITE !== 1 in config.php, force noindex on every
 * page regardless of the per-page setting. Protects staging/dev from being
 * accidentally indexed. */
if (($rankSite ?? 1) !== 1) {
    $__robots = 'noindex, nofollow, noarchive, nosnippet';
}

$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
?>
<!-- ============ SEO ============ -->
<link rel="canonical" href="<?= $e($canonicalUrl) ?>">
<link rel="alternate" hreflang="en-in" href="<?= $e($canonicalUrl) ?>">
<link rel="alternate" hreflang="x-default" href="<?= $e($canonicalUrl) ?>">
<meta name="keywords" content="<?= $e($__keywords) ?>">
<meta name="author" content="<?= $e($siteName) ?>">
<meta name="robots" content="<?= $e($__robots) ?>">
<meta name="googlebot" content="<?= $e($__robots) ?>">
<meta name="language" content="English">
<meta name="rating" content="General">
<meta name="revisit-after" content="7 days">

<!-- Geo targeting -->
<meta name="geo.region" content="IN-GJ">
<meta name="geo.placename" content="<?= $e($siteLocality) ?>">
<meta name="geo.position" content="<?= $e($siteLatitude . ';' . $siteLongitude) ?>">
<meta name="ICBM" content="<?= $e($siteLatitude . ', ' . $siteLongitude) ?>">

<!-- Open Graph / Facebook -->
<meta property="og:site_name" content="<?= $e($siteName . ' (' . $siteShortName . ')') ?>">
<meta property="og:type" content="<?= $e($__ogType) ?>">
<meta property="og:title" content="<?= $e($__ogTitle) ?>">
<meta property="og:description" content="<?= $e($__ogDesc) ?>">
<meta property="og:url" content="<?= $e($canonicalUrl) ?>">
<meta property="og:image" content="<?= $e($__ogImage) ?>">
<meta property="og:image:alt" content="<?= $e($siteName . ' — ' . $siteTagline) ?>">
<meta property="og:locale" content="en_IN">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $e($__ogTitle) ?>">
<meta name="twitter:description" content="<?= $e($__ogDesc) ?>">
<meta name="twitter:image" content="<?= $e($__ogImage) ?>">
<meta name="twitter:image:alt" content="<?= $e($siteName) ?>">
