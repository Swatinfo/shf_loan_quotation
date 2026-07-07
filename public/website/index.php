<?php require __DIR__ . '/_bootstrap.php'; ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Shreenathji Home Finance (SHF) — Shaping Happiness Forever. Home loan, loan against property, business loan and balance transfer experts. Compare offers from 15+ leading banks and NBFCs with zero consulting fees.">
  <title>SHF — Shreenathji Home Finance | Shaping Happiness Forever</title>
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon/favicon-32x32.png?v=<?= $assetVersion ?>">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon/favicon-16x16.png?v=<?= $assetVersion ?>">
  <link rel="shortcut icon" href="assets/favicon/favicon.ico?v=<?= $assetVersion ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon/apple-touch-icon.png?v=<?= $assetVersion ?>">
  <link rel="manifest" href="assets/favicon/site.webmanifest?v=<?= $assetVersion ?>">
  <meta name="theme-color" content="#f15a29">
  <link rel="stylesheet" href="assets/css/site.css?v=<?= $assetVersion ?>">
<?php include __DIR__ . '/_seo-head.php'; ?>
</head>
<body>

<?php if (!empty($abTestEnabled)): ?>
<!-- ============ A/B testing switcher (only when AB_TEST_ENABLED = true) ============
     Critical styles are inlined so the pill renders correctly even if site.css
     is stale / delayed. The external .ab-switcher rules in site.css add hover,
     transitions and mobile-specific positioning on top.                         -->
<a href="index2" class="ab-switcher" title="Switch to New Design (Variant B)"
   style="position:fixed;top:90px;right:20px;z-index:999;display:inline-flex;align-items:center;gap:10px;padding:8px 16px 8px 14px;max-width:calc(100vw - 40px);background:#ffffff;border:1px solid #e5e7eb;border-radius:999px;box-shadow:0 12px 28px rgba(58,53,54,.18);font-family:'Jost',system-ui,-apple-system,sans-serif;font-weight:600;font-size:.78rem;line-height:1.2;color:#3a3536;text-decoration:none;white-space:nowrap;">
  <span class="ab-switcher-label" style="display:inline-flex;align-items:center;padding-right:10px;border-right:1px solid #e5e7eb;color:#f15a29;text-transform:uppercase;letter-spacing:.06em;font-size:.68rem;">Variant A · Original</span>
  <span class="ab-switcher-action" style="display:inline-flex;align-items:center;gap:6px;">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true" style="flex-shrink:0;"><path d="M3 12h18M13 5l8 7-8 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
    View B
  </span>
</a>
<?php endif; ?>

<!-- ============ Header ============ -->
<header class="site-header">
  <div class="container nav">
    <a href="/" class="nav-brand">
      <img src="assets/img/logo.png" alt="Shreenathji Home Finance">
    </a>
    <ul class="nav-menu">
      <li><a href="/" class="active">Home</a></li>
      <li><a href="about">About</a></li>
      <li class="nav-has-dropdown">
        <a href="services">Services
          <svg class="nav-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
        <ul class="nav-dropdown">
          <li class="nav-dropdown-heading">Core Products</li>
          <li><a href="home-loan">Home Loan</a></li>
          <li><a href="loan-against-property">Loan Against Property</a></li>
          <li><a href="business-loan">Business Loan</a></li>
          <li><a href="personal-loan">Personal Loan</a></li>
          <li><a href="project-finance">Project &amp; Developer Finance</a></li>
          <li><a href="balance-transfer">Balance Transfer</a></li>
          <li class="nav-dropdown-heading">Specialised Finance</li>
          <li><a href="working-capital">Working Capital Finance</a></li>
          <li><a href="machinery-finance">Machinery &amp; Equipment Finance</a></li>
          <li><a href="invoice-discounting">Invoice Discounting</a></li>
          <li><a href="gst-overdraft">GST-Based Overdraft</a></li>
          <li><a href="msme-loan">MSME / CGTMSE Loan</a></li>
          <li><a href="dropline-overdraft">Dropline Overdraft</a></li>
        </ul>
      </li>
      <li><a href="faq">FAQs</a></li>
      <li><a href="contact">Contact</a></li>
    </ul>
    <div class="nav-cta">
      <a href="contact" class="btn btn-accent btn-sm">Get Started</a>
      <button class="nav-toggle" aria-label="Toggle navigation">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18" stroke-linecap="round"/></svg>
      </button>
    </div>
  </div>
</header>

<!-- ============ Hero ============ -->
<section class="hero">
  <div class="container hero-grid">
    <div>
      <div class="brand-tagline brand-tagline--hero">Shaping Happiness Forever</div>
      <span class="eyebrow">SHF — Your Trusted Lending Partner</span>
      <h1>Home, Property, Business — <span class="accent">All Financed Right.</span></h1>
      <p class="hero-lead">
        <strong>Shreenathji Home Finance (SHF)</strong> structures the right loan for your goal —
        whether that's buying a home, unlocking property equity, funding a growing business or
        switching to a better rate. One advisor, one digital workflow, side-by-side offers from
        a broad lender panel.
      </p>
      <div class="hero-ctas">
        <a href="contact" class="btn btn-accent btn-lg">Get a Free Quote</a>
        <a href="#emi" class="btn btn-outline btn-lg">Calculate EMI</a>
      </div>
      <div class="hero-badges">
        <div class="hero-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
          No Hidden Charges
        </div>
        <div class="hero-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Zero Consulting Fees
        </div>
        <div class="hero-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Bilingual Support
        </div>
      </div>
    </div>
    <div class="hero-visual">
      <div class="hero-card hero-card-main">
        <div class="hero-card-title">One Enquiry</div>
        <div class="hero-card-amount">15+</div>
        <p class="hero-card-sub">Lenders compared side-by-side. Your rate, amount and tenure depend on your profile, credit score and lender policy — we'll give you the full picture upfront.</p>
        <div class="hero-stat-row">
          <div>
            <div class="stat-num">Long</div>
            <div class="stat-lbl">Tenure Options</div>
          </div>
          <div>
            <div class="stat-num">₹0</div>
            <div class="stat-lbl">Advisory Fees</div>
          </div>
        </div>
      </div>
      <div class="hero-float hero-float-1">
        <span class="icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke-linejoin="round"/><path d="M9 22V12h6v10" stroke-linejoin="round"/></svg>
        </span>
        500+ Happy Customers
      </div>
      <div class="hero-float hero-float-2">
        <span class="icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z" stroke-linejoin="round"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5" stroke-linejoin="round"/></svg>
        </span>
        15+ Lender Panel
      </div>
    </div>
  </div>
</section>

<!-- ============ Stats ============ -->
<section class="stats-strip">
  <div class="container stats-grid">
    <div><div class="stat-value">10+</div><div class="stat-label">Years Experience</div></div>
    <div><div class="stat-value">500+</div><div class="stat-label">Loans Disbursed</div></div>
    <div><div class="stat-value">15+</div><div class="stat-label">Lenders Compared</div></div>
    <div><div class="stat-value">₹200 Cr+</div><div class="stat-label">Total Funded</div></div>
  </div>
</section>

<!-- ============ About preview ============ -->
<section class="section">
  <div class="container about-grid">
    <div class="about-img-wrap">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
        <path d="M3 9.5L12 3l9 6.5V21H3V9.5z" stroke-linejoin="round"/>
        <path d="M9 21v-6h6v6" stroke-linejoin="round"/>
        <path d="M12 3v2M3 9.5h18" stroke-linecap="round"/>
      </svg>
    </div>
    <div>
      <span class="eyebrow">About Shreenathji Home Finance (SHF)</span>
      <h2>Helping families and businesses fund what matters — the honest way.</h2>
      <p>
        <strong>SHF</strong> serves customers across Gujarat and beyond, headquartered at R K Prime near Nana Mava Circle,
        Rajkot. We've built our reputation on one simple idea: customers deserve clear advice and a
        transparent process. We work alongside you from the first enquiry to final disbursement,
        comparing offers across leading banks to secure the best fit for your needs.
      </p>
      <ul class="about-highlights">
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Dedicated advisor assigned to every customer
        </li>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
          English &amp; Gujarati support throughout the journey
        </li>
        <li>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Digital document collection — no branch visits required
        </li>
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
      <p class="section-lead">
        From buying your first home to expanding your business — we structure the right loan
        across our lender panel of 15+ leading banks and NBFCs.
      </p>
    </div>
    <div class="service-grid">
      <div class="service-card">
        <div class="service-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke-linejoin="round"/><path d="M9 22V12h6v10" stroke-linejoin="round"/></svg>
        </div>
        <h3>Home Loan</h3>
        <p>Buy, build or renovate — with long-tenure options and competitive rates, matched to your profile.</p>
        <ul class="service-features">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>As per eligibility &amp; credit score</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Long-tenure options available</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Transparent, comparable offers</li>
        </ul>
        <a href="home-loan" class="service-link">Learn more →</a>
      </div>
      <div class="service-card">
        <div class="service-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        </div>
        <h3>Loan Against Property</h3>
        <p>Unlock the hidden value in your residential or commercial property.</p>
        <ul class="service-features">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Up to 70% of property value</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Any end-use flexibility</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Competitive rates</li>
        </ul>
        <a href="loan-against-property" class="service-link">Learn more →</a>
      </div>
      <div class="service-card">
        <div class="service-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" stroke-linecap="round"/></svg>
        </div>
        <h3>Business Loan</h3>
        <p>Fuel your growth — working capital, expansion or equipment finance.</p>
        <ul class="service-features">
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Proprietor / LLP / Pvt Ltd</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Minimal documentation</li>
          <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Quick disbursal</li>
        </ul>
        <a href="business-loan" class="service-link">Learn more →</a>
      </div>
    </div>
  </div>
</section>

<!-- ============ Why Choose Us ============ -->
<section class="section">
  <div class="container">
    <div class="text-center">
      <span class="eyebrow">Why Choose SHF</span>
      <h2 class="section-title">Built on trust, powered by technology.</h2>
      <p class="section-lead">We combine the personal touch of a local advisor with the efficiency of a digital loan platform.</p>
    </div>
    <div class="feature-grid">
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6v6H9z" stroke-linejoin="round"/></svg>
        </div>
        <h4>Multi-Bank Comparison</h4>
        <p>Compare EMIs, rates and charges from 15+ leading banks and NBFCs side-by-side — no bias, no hidden fees.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        </div>
        <h4>Fast Processing</h4>
        <p>Digital workflow and document tracking cut turnaround from weeks to days.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13A4 4 0 0 1 16 11"/></svg>
        </div>
        <h4>Dedicated Advisor</h4>
        <p>A single point of contact who knows your file end-to-end. No switching desks mid-process.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke-linejoin="round"/></svg>
        </div>
        <h4>Transparent Process</h4>
        <p>Every charge, every document, every step spelled out. No surprises at the finish line.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" stroke-linejoin="round"/></svg>
        </div>
        <h4>Bilingual Support</h4>
        <p>English and Gujarati — paperwork, calls and explanations in whichever you prefer.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        </div>
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
      <p class="section-lead">Walk into one bank and you'll hear about one product. Walk in with SHF and you'll see side-by-side offers from a broad panel of lenders tailored to your profile — so the choice is yours, not ours.</p>
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
      <h2 class="section-title">Four Simple Steps to Your Loan</h2>
      <p class="section-lead">From your first call to disbursement — we keep every step clear and predictable.</p>
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
        <p>We pull your credit profile, flag issues early and shortlist the lenders most likely to say yes.</p>
      </div>
      <div class="process-step">
        <div class="process-num">4</div>
        <h4>Field &amp; Legal Checks</h4>
        <p>Valuations, legal opinions and address verifications — coordinated end-to-end so you aren't on calls.</p>
      </div>
      <div class="process-step">
        <div class="process-num">5</div>
        <h4>Sanction &amp; Handover</h4>
        <p>Paperwork signed, funds released, loan account active. We stay on the line post-disbursement too.</p>
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
      <p class="section-lead">Move the sliders to see how your monthly EMI, total interest and payment change.</p>
    </div>
    <div class="emi-wrap" id="emi-calculator">
      <div class="emi-controls">
        <div class="ctrl">
          <div class="ctrl-head">
            <label for="emi-amount">Loan Amount</label>
            <span class="ctrl-value" id="emi-amount-val">₹ 25,00,000</span>
          </div>
          <input type="range" id="emi-amount" class="slider" min="100000" max="50000000" step="50000" value="2500000">
          <div class="ctrl-range"><span>₹ 1 Lakh</span><span>₹ 5 Crore</span></div>
        </div>
        <div class="ctrl">
          <div class="ctrl-head">
            <label for="emi-rate">Interest Rate (p.a.)</label>
            <span class="ctrl-value" id="emi-rate-val">8.50%</span>
          </div>
          <input type="range" id="emi-rate" class="slider" min="6" max="15" step="0.05" value="8.5">
          <div class="ctrl-range"><span>6%</span><span>15%</span></div>
        </div>
        <div class="ctrl">
          <div class="ctrl-head">
            <label for="emi-tenure">Tenure</label>
            <span class="ctrl-value" id="emi-tenure-val">20 Years</span>
          </div>
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
        <div class="mt-4">
          <a href="contact" class="btn btn-accent" style="width:100%;">Get Your Personalised Quote</a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ Testimonials ============ -->
<section class="section">
  <div class="container">
    <div class="text-center">
      <span class="eyebrow">Customer Stories</span>
      <h2 class="section-title">What Our Customers Say</h2>
    </div>

    <?php
      $fullStars  = (int) floor((float) $aggregateRating['ratingValue']);
      $halfStar   = ((float) $aggregateRating['ratingValue'] - $fullStars) >= 0.25
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
        <span class="reviews-stars" aria-label="<?= htmlspecialchars($aggregateRating['ratingValue']) ?> out of 5 stars">
          <?php for ($i = 0; $i < $fullStars; $i++): ?>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
          <?php endfor; ?>
          <?php if ($halfStar): ?>
            <svg viewBox="0 0 24 24" fill="none"><defs><linearGradient id="hg"><stop offset="50%" stop-color="#FBBC05"/><stop offset="50%" stop-color="#e6e7e8"/></linearGradient></defs><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" fill="url(#hg)"/></svg>
          <?php endif; ?>
          <?php for ($i = 0; $i < $emptyStars; $i++): ?>
            <svg viewBox="0 0 24 24" fill="#e6e7e8"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
          <?php endfor; ?>
        </span>
        <div class="reviews-count">Based on <?= htmlspecialchars($aggregateRating['reviewCount']) ?>+ verified customer reviews</div>
      </div>
      <a href="<?= htmlspecialchars($googleReviewsUrl) ?>" target="_blank" rel="noopener" class="reviews-cta">Read on Google &rarr;</a>
    </div>

    <div class="quote-grid">
      <div class="quote-card">
        <span class="quote-mark">"</span>
        <p class="quote-text">Exceptional service! They made the loan process smooth and stress-free. From start to finish, the team provided unparalleled support and guidance, making people's dreams a reality with their seamless loan process.</p>
        <div class="quote-person">
          <div class="quote-avatar">DN</div>
          <div>
            <div class="quote-name">Dhyey Nathwani</div>
            <div class="quote-title">
              <span style="display:inline-flex;gap:1px;color:#FBBC05;vertical-align:0.1em;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
              </span>
              &nbsp;Google Review
            </div>
          </div>
        </div>
      </div>
      <div class="quote-card">
        <span class="quote-mark">"</span>
        <p class="quote-text">Best home loan service. Mr. Hardik Nasit did an excellent job — he knows what he's doing and paid close attention to my case. They gave me my loan quickly and without any problems. I strongly recommend Shreenathji for anyone who needs a home loan.</p>
        <div class="quote-person">
          <div class="quote-avatar">AS</div>
          <div>
            <div class="quote-name">Ajayraj Shihora</div>
            <div class="quote-title">
              <span style="display:inline-flex;gap:1px;color:#FBBC05;vertical-align:0.1em;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
              </span>
              &nbsp;Google Review
            </div>
          </div>
        </div>
      </div>
      <div class="quote-card">
        <span class="quote-mark">"</span>
        <p class="quote-text">Most satisfying work for home loans and mortgage loans. SHF handled everything professionally — great experience from enquiry to disbursement.</p>
        <div class="quote-person">
          <div class="quote-avatar">TA</div>
          <div>
            <div class="quote-name">Tejas Ajudiya</div>
            <div class="quote-title">
              <span style="display:inline-flex;gap:1px;color:#FBBC05;vertical-align:0.1em;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>
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
      <p class="section-lead">No two borrowers are the same — and we don't pretend otherwise. Here's how we approach the profiles that walk through our door most often.</p>
    </div>
    <div class="feature-grid">
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke-linejoin="round"/><path d="M9 22V12h6v10" stroke-linejoin="round"/></svg>
        </div>
        <h4>First-Time Buyers</h4>
        <p>Nervous about the paperwork? We'll walk you through it twice if needed — and make sure you understand the EMI before you sign.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13A4 4 0 0 1 16 11"/></svg>
        </div>
        <h4>Self-Employed &amp; Business Owners</h4>
        <p>GST returns, bank statements, cash-based income — we know how to structure the file so your true earning power is visible to the lender.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M9 12l2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h4>Imperfect Credit Histories</h4>
        <p>One missed EMI three years ago shouldn't close every door. We help you clean up the file and find lenders who weigh the full picture.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z" stroke-linejoin="round"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5" stroke-linejoin="round"/></svg>
        </div>
        <h4>Balance Transfer Seekers</h4>
        <p>Paying more than current rates warrant? We run the numbers honestly and only recommend a switch when the math genuinely works for you.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        </div>
        <h4>Growing Businesses</h4>
        <p>Loan against your shop, warehouse or factory — used for expansion, working capital, or consolidating expensive borrowing.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h4l2 5l-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" stroke-linejoin="round"/></svg>
        </div>
        <h4>Doorstep Service, Anywhere</h4>
        <p>Can't make it to our office? We'll come to your home or workplace across Gujarat — document pickup, explanations, signatures, the lot.</p>
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

<!-- ============ Footer ============ -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <img src="assets/img/logo.png" alt="SHF — Shreenathji Home Finance">
        <div class="brand-tagline brand-tagline--footer">Shaping Happiness Forever</div>
        <p><strong style="color:#fff;">Shreenathji Home Finance (SHF)</strong> — trusted lending advisors helping families and businesses across Gujarat and beyond with home, property, business and balance-transfer loans from leading banks and NBFCs.</p>
        <div class="social-row">
          <a href="https://www.facebook.com/ShreenathjiHomeFinance/" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg></a>
          <a href="https://www.instagram.com/shreenathjihomefinance/" target="_blank" rel="noopener" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg></a>
          <a href="https://wa.me/919099089072" target="_blank" rel="noopener" aria-label="WhatsApp Customer Care"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.76.46 3.45 1.32 4.95L2 22l5.25-1.37c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2zm0 18.15c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.26 8.26 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.82c0 4.53-3.69 8.23-8.22 8.23z"/></svg></a>
        </div>
      </div>
      <div class="footer-col">
        <h5>Quick Links</h5>
        <ul>
          <li><a href="/">Home</a></li>
          <li><a href="about">About Us</a></li>
          <li><a href="services">Services</a></li>
          <li><a href="contact">Contact</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Services</h5>
        <ul>
          <li><a href="home-loan">Home Loan</a></li>
          <li><a href="loan-against-property">Loan Against Property</a></li>
          <li><a href="business-loan">Business Loan</a></li>
          <li><a href="personal-loan">Personal Loan</a></li>
          <li><a href="project-finance">Project &amp; Developer Finance</a></li>
          <li><a href="balance-transfer">Balance Transfer</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h5>Reach Us</h5>
        <div class="footer-contact-line">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z" stroke-linejoin="round"/><circle cx="12" cy="10" r="3"/></svg>
          <span>Office No 911, R K Prime, Silver Height, Near Nana Mava Circle, 150 Ft Ring Road, Rajkot, Gujarat 360004</span>
        </div>
        <div class="footer-contact-line">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.37 1.9.72 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.35 1.85.59 2.81.72A2 2 0 0 1 22 16.92z" stroke-linejoin="round"/></svg>
          <div>
            <a href="tel:+919974789089">+91 99747 89089</a>
            <div style="font-size:0.75rem;color:rgba(255,255,255,0.45);margin-top:2px;">Sales &amp; Enquiry</div>
          </div>
        </div>
        <div class="footer-contact-line">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64A9 9 0 0 1 21 12m-3-5.36A5 5 0 0 1 19 12" stroke-linecap="round"/><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.37 1.9.72 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.35 1.85.59 2.81.72A2 2 0 0 1 22 16.92z" stroke-linejoin="round"/></svg>
          <div>
            <a href="tel:+919099089072">+91 90990 89072</a>
            <div style="font-size:0.75rem;color:rgba(255,255,255,0.45);margin-top:2px;">Customer Care</div>
          </div>
        </div>
        <div class="footer-contact-line">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
          <a href="mailto:info@shfworld.com">info@shfworld.com</a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <div>&copy; <span id="current-year"></span> Shreenathji Home Finance. All rights reserved.</div>
      <div><a href="privacy">Privacy Policy</a> &middot; <a href="terms">Terms of Service</a></div>
    </div>
  </div>
</footer>

<a href="https://wa.me/919099089072?text=Hi%20SHF%2C%20I%27d%20like%20to%20know%20more%20about%20your%20loan%20services." target="_blank" rel="noopener" class="wa-fab" aria-label="Chat on WhatsApp">
  <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.76.46 3.45 1.32 4.95L2 22l5.25-1.37c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2zm0 18.15c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.26 8.26 0 0 1-1.26-4.38c0-4.54 3.7-8.23 8.24-8.23 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.82c0 4.53-3.69 8.23-8.22 8.23zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.79.97-.15.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.23-1.47-1.38-1.72-.15-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.48-.41-.42-.56-.42h-.48c-.17 0-.43.06-.66.31-.23.25-.87.85-.87 2.07 0 1.22.89 2.4 1.01 2.56.12.17 1.75 2.67 4.23 3.74.59.25 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.68-1.18.21-.58.21-1.07.14-1.18-.06-.11-.22-.17-.46-.29z"/></svg>
</a>

<script src="assets/js/site.js?v=<?= $assetVersion ?>"></script>
<?php include __DIR__ . '/_seo-foot.php'; ?>
</body>
</html>
