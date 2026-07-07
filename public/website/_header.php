<?php
/**
 * SHF marketing site — shared navbar.
 * Requires _bootstrap.php to have set $pageSlug earlier in the page.
 *
 * Usage:   <?php include __DIR__ . '/_header.php'; ?>
 *
 * Active-link highlighting is driven by $pageSlug so each page
 * automatically marks the right nav item as current.
 */

$__slug = $pageSlug ?? '';

/** Helper: echo ' class="active"' when the given slug matches */
$act = static function ($target) use ($__slug): string {
    $target = is_array($target) ? $target : [$target];
    return in_array($__slug, $target, true) ? ' class="active"' : '';
};

$__servicesActive = in_array($__slug, [
    'services', 'home-loan', 'loan-against-property', 'business-loan',
    'personal-loan', 'project-finance', 'balance-transfer',
    'working-capital', 'machinery-finance', 'invoice-discounting',
    'gst-overdraft', 'msme-loan', 'dropline-overdraft',
], true);
?>
<header class="site-header">
    <div class="container nav">
        <a href="/" class="nav-brand">
            <img src="assets/img/logo.png" alt="SHF — Shreenathji Home Finance">
        </a>
        <ul class="nav-menu">
            <li><a href="/"<?= $__slug === 'index' ? ' class="active"' : '' ?>>Home</a></li>
            <li><a href="about"<?= $act('about') ?>>About</a></li>
            <li class="nav-has-dropdown<?= $__servicesActive ? ' parent-active' : '' ?>">
                <a href="services"<?= $__servicesActive ? ' class="active"' : '' ?>>Services
                    <svg class="nav-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
                <ul class="nav-dropdown">
                    <li class="nav-dropdown-heading">Core Products</li>
                    <li><a href="home-loan"<?= $act('home-loan') ?>>Home Loan</a></li>
                    <li><a href="loan-against-property"<?= $act('loan-against-property') ?>>Loan Against Property</a></li>
                    <li><a href="business-loan"<?= $act('business-loan') ?>>Business Loan</a></li>
                    <li><a href="personal-loan"<?= $act('personal-loan') ?>>Personal Loan</a></li>
                    <li><a href="project-finance"<?= $act('project-finance') ?>>Project &amp; Developer Finance</a></li>
                    <li><a href="balance-transfer"<?= $act('balance-transfer') ?>>Balance Transfer</a></li>
                    <li class="nav-dropdown-heading">Specialised Finance</li>
                    <li><a href="working-capital"<?= $act('working-capital') ?>>Working Capital Finance</a></li>
                    <li><a href="machinery-finance"<?= $act('machinery-finance') ?>>Machinery &amp; Equipment Finance</a></li>
                    <li><a href="invoice-discounting"<?= $act('invoice-discounting') ?>>Invoice Discounting</a></li>
                    <li><a href="gst-overdraft"<?= $act('gst-overdraft') ?>>GST-Based Overdraft</a></li>
                    <li><a href="msme-loan"<?= $act('msme-loan') ?>>MSME / CGTMSE Loan</a></li>
                    <li><a href="dropline-overdraft"<?= $act('dropline-overdraft') ?>>Dropline Overdraft</a></li>
                </ul>
            </li>
            <li><a href="faq"<?= $act('faq') ?>>FAQs</a></li>
            <li><a href="contact"<?= $act('contact') ?>>Contact</a></li>
        </ul>
        <div class="nav-cta">
            <a href="<?= $__slug === 'contact' ? 'tel:+919099089072' : 'contact' ?>" class="btn btn-accent btn-sm"><?= $__slug === 'contact' ? 'Call Now' : 'Get Started' ?></a>
            <button class="nav-toggle" aria-label="Toggle navigation">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18M3 12h18M3 18h18" stroke-linecap="round" />
                </svg>
            </button>
        </div>
    </div>
</header>
