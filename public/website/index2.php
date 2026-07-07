<?php require __DIR__.'/_bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description"
        content="Shreenathji Home Finance (SHF) — Shaping Happiness Forever. Home loan, loan against property, business loan and balance transfer experts. Compare offers from 15+ leading banks and NBFCs with zero consulting fees.">
    <title>SHF — Shreenathji Home Finance | Shaping Happiness Forever</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon/favicon-32x32.png?v=<?= $assetVersion ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon/favicon-16x16.png?v=<?= $assetVersion ?>">
    <link rel="shortcut icon" href="assets/favicon/favicon.ico?v=<?= $assetVersion ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon/apple-touch-icon.png?v=<?= $assetVersion ?>">
    <link rel="manifest" href="assets/favicon/site.webmanifest?v=<?= $assetVersion ?>">
    <meta name="theme-color" content="#f15a29">
    <link rel="stylesheet" href="assets/css/site.css?v=<?= $assetVersion ?>">
    <?php include __DIR__.'/_seo-head.php'; ?>
</head>

<body>

    <?php include __DIR__.'/_header.php'; ?>

    <?php if (! empty($abTestEnabled)) { ?>
    <!-- ============ A/B testing switcher (only when AB_TEST_ENABLED = true) ============
         Critical styles inlined so the pill renders correctly even if site.css
         is stale. The .ab-switcher rules in site.css add hover + mobile styles. -->
    <a href="/" class="ab-switcher" title="Switch to Original Design (Variant A)"
       style="position:fixed;top:90px;right:20px;z-index:999;display:inline-flex;align-items:center;gap:10px;padding:8px 16px 8px 14px;max-width:calc(100vw - 40px);background:#ffffff;border:1px solid #e5e7eb;border-radius:999px;box-shadow:0 12px 28px rgba(58,53,54,.18);font-family:'Jost',system-ui,-apple-system,sans-serif;font-weight:600;font-size:.78rem;line-height:1.2;color:#3a3536;text-decoration:none;white-space:nowrap;">
        <span class="ab-switcher-label" style="display:inline-flex;align-items:center;padding-right:10px;border-right:1px solid #e5e7eb;color:#f15a29;text-transform:uppercase;letter-spacing:.06em;font-size:.68rem;">Variant B · New</span>
        <span class="ab-switcher-action" style="display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true" style="flex-shrink:0;">
                <path d="M3 12h18M13 5l8 7-8 7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            View A
        </span>
    </a>
    <?php } ?>

    <!-- ============ Hero split (content + rotating visual) ============ -->
    <section class="hero-v2" id="heroSlider">
        <div class="container hero-v2-grid">

            <!-- Left: static content (always visible) -->
            <div class="hero-v2-content">
                <div class="brand-tagline brand-tagline--hero">Shaping Happiness Forever</div>
                <span class="eyebrow">SHF — Your Trusted Lending Partner</span>
                <h1 class="hero-v2-title">
                    The Right Loan for Your <span class="typed-word" data-word-index="0">
                        <span class="tw-item is-active">Dream Home</span>
                        <span class="tw-item">Growing Business</span>
                        <span class="tw-item">Valuable Property</span>
                    </span>
                </h1>
                <p class="hero-v2-lead">
                    <strong>Shreenathji Home Finance (SHF)</strong> structures the right credit for your goal —
                    comparing offers from <strong>15+ leading banks and NBFCs</strong>, with a dedicated
                    advisor, transparent charges and <strong>zero consulting fees</strong>.
                </p>

                <ul class="hero-v2-usps">
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>No Hidden Charges</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>Dedicated Advisor</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>Bilingual Support</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                            <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>Digital Workflow</li>
                </ul>

                <div class="hero-ctas">
                    <a href="contact" class="btn btn-accent btn-lg">Get a Free Quote</a>
                    <a href="#emi" class="btn btn-outline btn-lg">Calculate EMI</a>
                </div>

                <!-- Quick-stat inline row -->
                <div class="hero-v2-stats">
                    <div class="hs"><span class="hs-num">15<span class="plus">+</span></span><span
                            class="hs-lbl">Lenders</span></div>
                    <div class="hs-sep"></div>
                    <div class="hs"><span class="hs-num">500<span class="plus">+</span></span><span
                            class="hs-lbl">Customers</span></div>
                    <div class="hs-sep"></div>
                    <div class="hs"><span class="hs-num">₹200 Cr<span class="plus">+</span></span><span
                            class="hs-lbl">Disbursed</span></div>
                    <div class="hs-sep"></div>
                    <div class="hs"><span class="hs-num">10<span class="plus">+</span></span><span
                            class="hs-lbl">Years</span></div>
                </div>
            </div>

            <!-- Right: rotating visual card -->
            <div class="hero-v2-visual" aria-label="Loan products carousel">
                <div class="hero-v2-card">
                    <!-- decorative header badge -->
                    <div class="hv-card-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path
                                d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"
                                stroke-linejoin="round" />
                        </svg>
                        <span><?= htmlspecialchars($aggregateRating['ratingValue']) ?> / 5 from <?= htmlspecialchars($aggregateRating['reviewCount']) ?>+ reviews</span>
                    </div>

                    <div class="hv-slide-stack">
                        <div class="hv-slide is-active" data-slide-theme="home">
                            <img src="assets/img/slides/slide-home.svg?v=<?= $assetVersion ?>"
                                alt="Home Loan illustration">
                            <div class="hv-slide-caption">
                                <span class="hv-slide-kicker">Product Spotlight</span>
                                <h3>Home Loan</h3>
                                <p>Buy, build or renovate — long-tenure options, tax benefits under Section 80C &amp;
                                    24(b).</p>
                                <a href="home-loan" class="hv-slide-link">Explore Home Loan →</a>
                            </div>
                        </div>
                        <div class="hv-slide" data-slide-theme="business">
                            <img src="assets/img/slides/slide-business.svg?v=<?= $assetVersion ?>"
                                alt="Business Loan illustration">
                            <div class="hv-slide-caption">
                                <span class="hv-slide-kicker">Product Spotlight</span>
                                <h3>Business Loan</h3>
                                <p>Unsecured or secured — working capital, MSME / CGTMSE, GST-based OD. Fast
                                    decisioning.</p>
                                <a href="business-loan" class="hv-slide-link">Explore Business Loan →</a>
                            </div>
                        </div>
                        <div class="hv-slide" data-slide-theme="property">
                            <img src="assets/img/slides/slide-property.svg?v=<?= $assetVersion ?>"
                                alt="Property finance illustration">
                            <div class="hv-slide-caption">
                                <span class="hv-slide-kicker">Product Spotlight</span>
                                <h3>Property &amp; Balance Transfer</h3>
                                <p>Release property equity or switch an existing loan to a better rate — top-up
                                    available.</p>
                                <a href="loan-against-property" class="hv-slide-link">Explore LAP →</a>
                            </div>
                        </div>
                    </div>

                    <!-- dots + arrows — inline styles so CSS cache can't break them -->
                    <?php
                      $arrowStyle = "width:36px;height:36px;padding:0;border-radius:50%;border:2px solid #f15a29;background:#f15a29;color:#fff;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;box-shadow:0 2px 6px rgba(241,90,41,0.25);line-height:0;";
                      $dotBase    = "width:10px;height:10px;padding:0;margin:0;border:0;border-radius:50%;background:rgba(58,53,54,0.25);cursor:pointer;flex-shrink:0;line-height:0;font-size:0;display:inline-block;";
                      $dotActive  = "width:28px;height:10px;padding:0;margin:0;border:0;border-radius:999px;background:#f15a29;cursor:pointer;flex-shrink:0;line-height:0;font-size:0;display:inline-block;box-shadow:0 2px 6px rgba(241,90,41,0.35);";
                    ?>
                    <div class="hv-controls" style="display:flex;align-items:center;justify-content:center;gap:14px;padding:14px 28px 18px;background:#fff;border-top:1px solid #e5e7eb;">
                        <button class="hv-arrow hv-prev" aria-label="Previous slide" style="<?= $arrowStyle ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" style="display:block;">
                                <polyline points="15 18 9 12 15 6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                        <div class="hv-dots" style="display:flex;align-items:center;gap:10px;flex:0 0 auto;">
                            <button class="hv-dot is-active" data-slide="0" aria-label="Home Loan" style="<?= $dotActive ?>"></button>
                            <button class="hv-dot" data-slide="1" aria-label="Business Loan" style="<?= $dotBase ?>"></button>
                            <button class="hv-dot" data-slide="2" aria-label="Property Loan" style="<?= $dotBase ?>"></button>
                        </div>
                        <button class="hv-arrow hv-next" aria-label="Next slide" style="<?= $arrowStyle ?>">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" style="display:block;">
                                <polyline points="9 18 15 12 9 6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                    <div class="hv-progress"><span class="hv-progress-fill"></span></div>
                </div>
            </div>

        </div>
    </section>

    <!-- ============ Trust row under hero ============ -->
    <section class="section-tight trust-row-wrap">
        <div class="container trust-row">
            <div class="trust-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                </svg><span>No Hidden Charges</span></div>
            <div class="trust-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                </svg><span>Zero Consulting Fees</span></div>
            <div class="trust-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                </svg><span>Dedicated Advisor</span></div>
            <div class="trust-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                </svg><span>Bilingual Support</span></div>
            <div class="trust-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                </svg><span>15+ Lender Panel</span></div>
        </div>
    </section>

    <!-- ============ Stats ============ -->
    <section class="stats-strip">
        <div class="container stats-grid">
            <div>
                <div class="stat-value">10+</div>
                <div class="stat-label">Years Experience</div>
            </div>
            <div>
                <div class="stat-value">500+</div>
                <div class="stat-label">Loans Disbursed</div>
            </div>
            <div>
                <div class="stat-value">15+</div>
                <div class="stat-label">Lenders Compared</div>
            </div>
            <div>
                <div class="stat-value">₹200 Cr+</div>
                <div class="stat-label">Total Funded</div>
            </div>
        </div>
    </section>

    <!-- ============ About preview ============ -->
    <section class="section">
        <div class="container about-grid">
            <div class="about-img-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                    <path d="M3 9.5L12 3l9 6.5V21H3V9.5z" stroke-linejoin="round" />
                    <path d="M9 21v-6h6v6" stroke-linejoin="round" />
                    <path d="M12 3v2M3 9.5h18" stroke-linecap="round" />
                </svg>
            </div>
            <div>
                <span class="eyebrow">About Shreenathji Home Finance (SHF)</span>
                <h2>Helping families and businesses fund what matters — the honest way.</h2>
                <p>
                    <strong>SHF</strong> serves customers across Gujarat and beyond, headquartered at R K Prime near
                    Nana Mava Circle,
                    Rajkot. We've built our reputation on one simple idea: customers deserve clear advice and a
                    transparent process. We work alongside you from the first enquiry to final disbursement,
                    comparing offers across leading banks to secure the best fit for your needs.
                </p>
                <ul class="about-highlights">
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>Dedicated advisor assigned to every customer</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>English &amp; Gujarati support throughout the journey</li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>Digital document collection — no branch visits required</li>
                </ul>
                <a href="about" class="btn btn-outline">Learn More About Us</a>
            </div>
        </div>
    </section>

    <!-- ============ Services preview ============ -->
    <section class="section section-soft">
        <div class="container">
            <div class="text-center">
                <span class="eyebrow">What We Offer</span>
                <h2 class="section-title">Finance Solutions Tailored for You</h2>
                <p class="section-lead">From buying your first home to expanding your business — we structure the right
                    loan across our lender panel of 15+ leading banks and NBFCs.</p>
            </div>
            <div class="service-grid">
                <div class="service-card">
                    <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke-linejoin="round" />
                            <path d="M9 22V12h6v10" stroke-linejoin="round" />
                        </svg></div>
                    <h3>Home Loan</h3>
                    <p>Buy, build or renovate — with long-tenure options and competitive rates, matched to your profile.
                    </p>
                    <ul class="service-features">
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>As per eligibility &amp; credit score</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>Long-tenure options available</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>Transparent, comparable offers</li>
                    </ul>
                    <a href="home-loan" class="service-link">Learn more →</a>
                </div>
                <div class="service-card">
                    <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <rect x="3" y="7" width="18" height="13" rx="2" />
                            <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                        </svg></div>
                    <h3>Loan Against Property</h3>
                    <p>Unlock the hidden value in your residential or commercial property.</p>
                    <ul class="service-features">
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>Residential &amp; commercial property accepted</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>Any end-use flexibility</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>Competitive rates</li>
                    </ul>
                    <a href="loan-against-property" class="service-link">Learn more →</a>
                </div>
                <div class="service-card">
                    <div class="service-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"
                                stroke-linecap="round" />
                        </svg></div>
                    <h3>Business Loan</h3>
                    <p>Fuel your growth — working capital, expansion or equipment finance.</p>
                    <ul class="service-features">
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>Proprietor / LLP / Pvt Ltd</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>Minimal documentation</li>
                        <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>Quick disbursal</li>
                    </ul>
                    <a href="business-loan" class="service-link">Learn more →</a>
                </div>
            </div>
            <p class="text-center" style="margin-top:34px;">
                <a href="services" class="btn btn-outline">View All Services</a>
            </p>
        </div>
    </section>

    <!-- ============ Why Choose Us ============ -->
    <section class="section">
        <div class="container">
            <div class="text-center">
                <span class="eyebrow">Why Choose SHF</span>
                <h2 class="section-title">Built on trust, powered by technology.</h2>
                <p class="section-lead">We combine the personal touch of a local advisor with the efficiency of a
                    digital loan platform.</p>
            </div>
            <div class="feature-grid">
                <div class="feature-tile">
                    <div class="icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <path d="M9 9h6v6H9z" stroke-linejoin="round" />
                        </svg></div>
                    <h4>Multi-Lender Comparison</h4>
                    <p>Compare EMIs, rates and charges across 15+ leading banks and NBFCs, side-by-side.</p>
                </div>
                <div class="feature-tile">
                    <div class="icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M12 7v5l3 2" />
                        </svg></div>
                    <h4>Fast Processing</h4>
                    <p>Digital workflow and document tracking cut turnaround from weeks to days.</p>
                </div>
                <div class="feature-tile">
                    <div class="icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13A4 4 0 0 1 16 11" />
                        </svg></div>
                    <h4>Dedicated Advisor</h4>
                    <p>A single point of contact who knows your file end-to-end. No switching desks mid-process.</p>
                </div>
                <div class="feature-tile">
                    <div class="icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke-linejoin="round" />
                        </svg></div>
                    <h4>Transparent Process</h4>
                    <p>Every charge, every document, every step spelled out. No surprises at the finish line.</p>
                </div>
                <div class="feature-tile">
                    <div class="icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path
                                d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"
                                stroke-linejoin="round" />
                        </svg></div>
                    <h4>Bilingual Support</h4>
                    <p>English and Gujarati — paperwork, calls and explanations in whichever you prefer.</p>
                </div>
                <div class="feature-tile">
                    <div class="icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="3" width="20" height="14" rx="2" />
                            <path d="M8 21h8M12 17v4" />
                        </svg></div>
                    <h4>Digital Workflow</h4>
                    <p>Share documents online, track stage progress, and get notified the moment something changes.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ Lending network ============ -->
    <section class="section section-soft section-sm">
        <div class="container">
            <div class="text-center">
                <span class="eyebrow">Why SHF, Not a Single Bank</span>
                <h2 class="section-title">The Right Lender for You — Not Just Any Lender</h2>
                <p class="section-lead">Walk into one bank and you'll hear about one product. Walk in with SHF and
                    you'll see side-by-side offers from a broad panel of lenders tailored to your profile — so the
                    choice is yours, not ours.</p>
            </div>
            <div class="network-grid">
                <div class="network-tile">
                    <div class="network-num">15+</div>
                    <div class="network-lbl">Lender Panel</div>
                </div>
                <div class="network-tile">
                    <div class="network-num">50+</div>
                    <div class="network-lbl">Loan Variants</div>
                </div>
                <div class="network-tile">
                    <div class="network-num">₹0</div>
                    <div class="network-lbl">Advisory Fees</div>
                </div>
                <div class="network-tile">
                    <div class="network-num">Same Day</div>
                    <div class="network-lbl">We Call You Back</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ Process ============ -->
    <section class="section">
        <div class="container">
            <div class="text-center">
                <span class="eyebrow">How It Works</span>
                <h2 class="section-title">Five Simple Steps to Your Loan</h2>
                <p class="section-lead">From your first call to disbursement — we keep every step clear and predictable.
                </p>
            </div>
            <div class="process-wrap process-wrap--5">
                <div class="process-step">
                    <div class="process-num">1</div>
                    <h4>Tell Us What You Need</h4>
                    <p>A quick conversation about the property, your profile and what you want to achieve.</p>
                </div>
                <div class="process-step">
                    <div class="process-num">2</div>
                    <h4>Paperwork, Digitally</h4>
                    <p>Upload everything through our portal — no trips to the branch, no printed copies chasing you.</p>
                </div>
                <div class="process-step">
                    <div class="process-num">3</div>
                    <h4>Credit Scan &amp; Clean-up</h4>
                    <p>We pull your credit profile, flag issues early and shortlist the lenders most likely to say yes.
                    </p>
                </div>
                <div class="process-step">
                    <div class="process-num">4</div>
                    <h4>Field &amp; Legal Checks</h4>
                    <p>Valuations, legal opinions and address verifications — coordinated end-to-end so you aren't on
                        calls.</p>
                </div>
                <div class="process-step">
                    <div class="process-num">5</div>
                    <h4>Sanction &amp; Handover</h4>
                    <p>Paperwork signed, funds released, loan account active. We stay on the line post-disbursement too.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ EMI calculator ============ -->
    <section class="section section-soft" id="emi">
        <div class="container">
            <div class="text-center">
                <span class="eyebrow">Plan Ahead</span>
                <h2 class="section-title">EMI Calculator</h2>
                <p class="section-lead">Move the sliders to see how your monthly EMI, total interest and payment change.
                </p>
            </div>
            <div class="emi-wrap" id="emi-calculator">
                <div class="emi-controls">
                    <div class="ctrl">
                        <div class="ctrl-head"><label for="emi-amount">Loan Amount</label><span class="ctrl-value"
                                id="emi-amount-val">₹ 25,00,000</span></div>
                        <input type="range" id="emi-amount" class="slider" min="100000" max="50000000" step="50000"
                            value="2500000">
                        <div class="ctrl-range"><span>₹ 1 Lakh</span><span>₹ 5 Crore</span></div>
                    </div>
                    <div class="ctrl">
                        <div class="ctrl-head"><label for="emi-rate">Interest Rate (p.a.)</label><span
                                class="ctrl-value" id="emi-rate-val">8.50%</span></div>
                        <input type="range" id="emi-rate" class="slider" min="6" max="15" step="0.05" value="8.5">
                        <div class="ctrl-range"><span>6%</span><span>15%</span></div>
                    </div>
                    <div class="ctrl">
                        <div class="ctrl-head"><label for="emi-tenure">Tenure</label><span class="ctrl-value"
                                id="emi-tenure-val">20 Years</span></div>
                        <input type="range" id="emi-tenure" class="slider" min="1" max="30" step="1" value="20">
                        <div class="ctrl-range"><span>1 Year</span><span>30 Years</span></div>
                    </div>
                </div>
                <div class="emi-result">
                    <div class="result-label">Monthly EMI</div>
                    <div class="result-value" id="emi-out-monthly">₹ 0</div>
                    <div class="emi-breakdown">
                        <div>
                            <div class="lbl">Total Interest</div>
                            <div class="val" id="emi-out-interest">₹ 0</div>
                        </div>
                        <div>
                            <div class="lbl">Total Payment</div>
                            <div class="val" id="emi-out-total">₹ 0</div>
                        </div>
                    </div>
                    <div class="mt-4"><a href="contact" class="btn btn-accent" style="width:100%;">Get Your Personalised
                            Quote</a></div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ Testimonials + Google reviews ============ -->
    <section class="section">
        <div class="container">
            <div class="text-center">
                <span class="eyebrow">Customer Stories</span>
                <h2 class="section-title">What Our Customers Say</h2>
            </div>

            <?php
      $fullStars = (int) floor((float) $aggregateRating['ratingValue']);
$halfStar = ((float) $aggregateRating['ratingValue'] - $fullStars) >= 0.25
              && ((float) $aggregateRating['ratingValue'] - $fullStars) < 0.75;
$emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
?>
            <div class="reviews-card">
                <div class="reviews-google-logo">
                    <span class="g" aria-hidden="true"></span>
                    <span>Google Reviews</span>
                </div>
                <div class="reviews-stats">
                    <span class="reviews-rating"><?= htmlspecialchars($aggregateRating['ratingValue']) ?></span>
                    <span class="reviews-stars"
                        aria-label="<?= htmlspecialchars($aggregateRating['ratingValue']) ?> out of 5 stars">
                        <?php for ($i = 0; $i < $fullStars; $i++) { ?>
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                        </svg>
                        <?php } ?>
                        <?php if ($halfStar) { ?>
                        <svg viewBox="0 0 24 24" fill="none">
                            <defs>
                                <linearGradient id="hg2">
                                    <stop offset="50%" stop-color="#FBBC05" />
                                    <stop offset="50%" stop-color="#e6e7e8" />
                                </linearGradient>
                            </defs>
                            <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" fill="url(#hg2)" />
                        </svg>
                        <?php } ?>
                        <?php for ($i = 0; $i < $emptyStars; $i++) { ?>
                        <svg viewBox="0 0 24 24" fill="#e6e7e8">
                            <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                        </svg>
                        <?php } ?>
                    </span>
                    <div class="reviews-count">Based on <?= htmlspecialchars($aggregateRating['reviewCount']) ?>+
                        verified customer reviews</div>
                </div>
                <a href="<?= htmlspecialchars($googleReviewsUrl) ?>" target="_blank" rel="noopener"
                    class="reviews-cta">Read on Google &rarr;</a>
            </div>

            <div class="quote-grid">
                <div class="quote-card">
                    <span class="quote-mark">"</span>
                    <p class="quote-text">Exceptional service! They made the loan process smooth and stress-free. From
                        start to finish, the team provided unparalleled support and guidance, making people's dreams a
                        reality with their seamless loan process.</p>
                    <div class="quote-person">
                        <div class="quote-avatar">DN</div>
                        <div>
                            <div class="quote-name">Dhyey Nathwani</div>
                            <div class="quote-title">
                                <span style="display:inline-flex;gap:1px;color:#FBBC05;vertical-align:0.1em;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                </span>
                                &nbsp;Google Review
                            </div>
                        </div>
                    </div>
                </div>
                <div class="quote-card">
                    <span class="quote-mark">"</span>
                    <p class="quote-text">Best home loan service. Mr. Hardik Nasit did an excellent job — he knows what
                        he's doing and paid close attention to my case. They gave me my loan quickly and without any
                        problems. I strongly recommend Shreenathji for anyone who needs a home loan.</p>
                    <div class="quote-person">
                        <div class="quote-avatar">AS</div>
                        <div>
                            <div class="quote-name">Ajayraj Shihora</div>
                            <div class="quote-title">
                                <span style="display:inline-flex;gap:1px;color:#FBBC05;vertical-align:0.1em;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                </span>
                                &nbsp;Google Review
                            </div>
                        </div>
                    </div>
                </div>
                <div class="quote-card">
                    <span class="quote-mark">"</span>
                    <p class="quote-text">Most satisfying work for home loans and mortgage loans. SHF handled everything
                        professionally — great experience from enquiry to disbursement.</p>
                    <div class="quote-person">
                        <div class="quote-avatar">TA</div>
                        <div>
                            <div class="quote-name">Tejas Ajudiya</div>
                            <div class="quote-title">
                                <span style="display:inline-flex;gap:1px;color:#FBBC05;vertical-align:0.1em;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" />
                                    </svg>
                                </span>
                                &nbsp;Google Review
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ Who we help ============ -->
    <section class="section section-soft">
        <div class="container">
            <div class="text-center">
                <span class="eyebrow">People We Work With</span>
                <h2 class="section-title">Every Profile, a Custom Path</h2>
                <p class="section-lead">No two borrowers are the same — and we don't pretend otherwise. Here's how we
                    approach the profiles that walk through our door most often.</p>
            </div>
            <div class="feature-grid">
                <div class="feature-tile">
                    <div class="icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke-linejoin="round" />
                            <path d="M9 22V12h6v10" stroke-linejoin="round" />
                        </svg></div>
                    <h4>First-Time Buyers</h4>
                    <p>Nervous about the paperwork? We'll walk you through it twice if needed — and make sure you
                        understand the EMI before you sign.</p>
                </div>
                <div class="feature-tile">
                    <div class="icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13A4 4 0 0 1 16 11" />
                        </svg></div>
                    <h4>Self-Employed &amp; Business Owners</h4>
                    <p>GST returns, bank statements, cash-based income — we know how to structure the file so your true
                        earning power is visible to the lender.</p>
                </div>
                <div class="feature-tile">
                    <div class="icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round" />
                        </svg></div>
                    <h4>Imperfect Credit Histories</h4>
                    <p>One missed EMI three years ago shouldn't close every door. We help you clean up the file and find
                        lenders who weigh the full picture.</p>
                </div>
                <div class="feature-tile">
                    <div class="icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 2L2 7l10 5 10-5-10-5z" stroke-linejoin="round" />
                            <path d="M2 17l10 5 10-5M2 12l10 5 10-5" stroke-linejoin="round" />
                        </svg></div>
                    <h4>Balance Transfer Seekers</h4>
                    <p>Paying more than current rates warrant? We run the numbers honestly and only recommend a switch
                        when the math genuinely works for you.</p>
                </div>
                <div class="feature-tile">
                    <div class="icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="7" width="18" height="13" rx="2" />
                            <path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                        </svg></div>
                    <h4>Growing Businesses</h4>
                    <p>Loan against your shop, warehouse or factory — used for expansion, working capital, or
                        consolidating expensive borrowing.</p>
                </div>
                <div class="feature-tile">
                    <div class="icon-box"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path
                                d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"
                                stroke-linejoin="round" />
                        </svg></div>
                    <h4>Doorstep Service, Anywhere</h4>
                    <p>Can't make it to our office? We'll come to your home or workplace across Gujarat — document
                        pickup, explanations, signatures, the lot.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ CTA strip ============ -->
    <section class="cta-strip">
        <div class="container">
            <div>
                <h2>Ready to start your loan journey?</h2>
                <p>Talk to an SHF advisor today — no obligation, no paperwork upfront.</p>
            </div>
            <a href="contact" class="btn btn-lg">Get a Free Consultation</a>
        </div>
    </section>

    <?php include __DIR__.'/_footer.php'; ?>
</body>

</html>