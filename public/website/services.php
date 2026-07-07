<?php require __DIR__ . '/_bootstrap.php'; ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Shreenathji Home Finance (SHF) services: home loan, loan against property, business loan, personal loan and balance transfer — for proprietors, partnerships, pvt ltd and salaried customers.">
  <title>Services — SHF | Shreenathji Home Finance</title>
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

<header class="site-header">
  <div class="container nav">
    <a href="/" class="nav-brand"><img src="assets/img/logo.png" alt="Shreenathji Home Finance"></a>
    <ul class="nav-menu">
      <li><a href="/">Home</a></li>
      <li><a href="about">About</a></li>
      <li class="nav-has-dropdown">
        <a href="services" class="active">Services
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

<!-- ============ Page Header ============ -->
<section class="page-header">
  <div class="container">
    <div class="breadcrumb"><a href="/">Home</a><span>/</span>Services</div>
    <div class="brand-tagline brand-tagline--page">Shaping Happiness Forever</div>
    <h1>Our Services</h1>
    <p>From your first home to your next expansion — <strong>Shreenathji Home Finance (SHF)</strong> structures the right credit across a wide range of loan products, matched to your profile and sourced from our lender panel.</p>
  </div>
</section>

<!-- ============ Service Details ============ -->
<section class="section">
  <div class="container">
    <div class="text-center">
      <span class="eyebrow">Core Products</span>
      <h2 class="section-title">The Main Loans We Arrange</h2>
      <p class="section-lead">Six mainstream products covering the needs most families and businesses walk in with. Each one comes with a dedicated advisor from enquiry to disbursement.</p>
    </div>
    <div class="service-detail-grid">
      <div class="service-detail" id="home-loan">
        <div class="icon-circle">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke-linejoin="round"/><path d="M9 22V12h6v10" stroke-linejoin="round"/></svg>
        </div>
        <div>
          <h3>Home Loan</h3>
          <p>
            Purchase, construct or renovate a home with tailored loan structures.
            We help you compare offers across our network of leading banks and NBFCs
            and lock in the best rate for your profile.
          </p>
          <ul class="service-features">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Ready property, under-construction &amp; plot + construction</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Joint applicants &amp; co-borrower support</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Tax benefits under Section 80C &amp; 24(b)</li>
          </ul>
          <div class="meta">
            <div class="meta-item"><span class="lbl">Loan Amount</span><span class="val">As per eligibility</span></div>
            <div class="meta-item"><span class="lbl">Tenure</span><span class="val">Up to 30 yrs</span></div>
            <div class="meta-item"><span class="lbl">Interest</span><span class="val">Competitive rates</span></div>
          </div>
        </div>
      </div>

      <div class="service-detail" id="lap">
        <div class="icon-circle">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
        </div>
        <div>
          <h3>Loan Against Property</h3>
          <p>
            Release the equity in your residential, commercial or industrial
            property for any legitimate end-use — expansion, education,
            consolidation or working capital.
          </p>
          <ul class="service-features">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Residential &amp; commercial property accepted</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Balance transfer + top-up available</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Longer tenure than a business loan</li>
          </ul>
          <div class="meta">
            <div class="meta-item"><span class="lbl">Loan-to-Value</span><span class="val">Bank policy-linked</span></div>
            <div class="meta-item"><span class="lbl">Tenure</span><span class="val">Long-tenure available</span></div>
            <div class="meta-item"><span class="lbl">Interest</span><span class="val">Competitive rates</span></div>
          </div>
        </div>
      </div>

      <div class="service-detail" id="business-loan">
        <div class="icon-circle">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" stroke-linecap="round"/></svg>
        </div>
        <div>
          <h3>Business Loan</h3>
          <p>
            Working capital, inventory, expansion or new-equipment finance —
            structured for proprietors, partnerships/LLPs and private limited
            companies.
          </p>
          <ul class="service-features">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Unsecured &amp; secured options</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Quick turnaround with minimal paperwork</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>GST &amp; bank-statement based underwriting</li>
          </ul>
          <div class="meta">
            <div class="meta-item"><span class="lbl">Loan Amount</span><span class="val">As per eligibility</span></div>
            <div class="meta-item"><span class="lbl">Tenure</span><span class="val">Flexible</span></div>
            <div class="meta-item"><span class="lbl">Interest</span><span class="val">Profile-based</span></div>
          </div>
        </div>
      </div>

      <div class="service-detail" id="balance-transfer">
        <div class="icon-circle">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 1l4 4-4 4" stroke-linejoin="round"/><path d="M3 11V9a4 4 0 0 1 4-4h14" stroke-linejoin="round"/><path d="M7 23l-4-4 4-4" stroke-linejoin="round"/><path d="M21 13v2a4 4 0 0 1-4 4H3" stroke-linejoin="round"/></svg>
        </div>
        <div>
          <h3>Balance Transfer</h3>
          <p>
            Paying more than you should on an existing loan? We'll switch your
            outstanding to a better rate — often with a top-up amount thrown in.
          </p>
          <ul class="service-features">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Reduce your EMI immediately</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Top-up loan on the transferred amount</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Free cost-benefit analysis</li>
          </ul>
          <div class="meta">
            <div class="meta-item"><span class="lbl">Savings</span><span class="val">Up to 1.5% p.a.</span></div>
            <div class="meta-item"><span class="lbl">Processing</span><span class="val">7–10 days</span></div>
            <div class="meta-item"><span class="lbl">Top-up</span><span class="val">Available</span></div>
          </div>
        </div>
      </div>

      <div class="service-detail" id="personal-loan">
        <div class="icon-circle">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2" stroke-linejoin="round"/></svg>
        </div>
        <div>
          <h3>Personal Loan</h3>
          <p>
            Unsecured credit for the moments that can't wait — a wedding, a medical
            bill, an overseas course, or folding multiple high-interest dues into
            one comfortable EMI. Fast decisioning, minimal paperwork.
          </p>
          <ul class="service-features">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>No collateral required</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Income-based underwriting for salaried &amp; self-employed</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Disbursal possible within 48 hours</li>
          </ul>
          <div class="meta">
            <div class="meta-item"><span class="lbl">Loan Amount</span><span class="val">As per eligibility</span></div>
            <div class="meta-item"><span class="lbl">Tenure</span><span class="val">Short-to-medium term</span></div>
            <div class="meta-item"><span class="lbl">Interest</span><span class="val">Credit-score linked</span></div>
          </div>
        </div>
      </div>

      <div class="service-detail" id="project-loan">
        <div class="icon-circle">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 20V10l10-7 10 7v10" stroke-linejoin="round"/><path d="M6 20v-6h4v6M14 20v-6h4v6M2 20h20" stroke-linejoin="round"/></svg>
        </div>
        <div>
          <h3>Project &amp; Developer Finance</h3>
          <p>
            Structured funding for real-estate developers and builders — land
            acquisition, construction cost, and inventory finance for ongoing
            or new residential and commercial projects, released in milestone-linked tranches.
          </p>
          <ul class="service-features">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Land, construction &amp; unit-inventory finance</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Milestone-based draw-downs</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Tie-up with unit-level home loans for buyers</li>
          </ul>
          <div class="meta">
            <div class="meta-item"><span class="lbl">Loan Amount</span><span class="val">As per project size</span></div>
            <div class="meta-item"><span class="lbl">Tenure</span><span class="val">Project-aligned</span></div>
            <div class="meta-item"><span class="lbl">Structure</span><span class="val">Milestone-linked</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ Specialised finance ============ -->
<section class="section section-soft" id="specialised">
  <div class="container">
    <div class="text-center">
      <span class="eyebrow">Specialised Finance</span>
      <h2 class="section-title">Beyond the Mainstream Loans</h2>
      <p class="section-lead">Businesses rarely fit a single product. These are the niche facilities we structure when the standard menu doesn't quite cover it.</p>
    </div>
    <div class="feature-grid">
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" stroke-linecap="round"/></svg>
        </div>
        <h4>Working Capital Finance</h4>
        <p>Day-to-day liquidity through cash-credit limits, overdrafts and short-term loans sized to your sales cycle.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" stroke-linejoin="round"/></svg>
        </div>
        <h4>Machinery &amp; Equipment Finance</h4>
        <p>Finance new or used plant, machinery and IT equipment — often up to 100% of asset value without tying up your own cash.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M7 15h4" stroke-linecap="round"/></svg>
        </div>
        <h4>Invoice Discounting</h4>
        <p>Unlock cash sitting in your unpaid invoices. Draw funds against receivables and stop waiting out the payment cycle.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h10" stroke-linecap="round"/></svg>
        </div>
        <h4>GST-Based Overdraft</h4>
        <p>Overdraft limits calculated from your GST filings — no property collateral, drawn when needed, repaid as cash flows.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z" stroke-linejoin="round"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5" stroke-linejoin="round"/></svg>
        </div>
        <h4>MSME / CGTMSE Loan</h4>
        <p>Collateral-free credit under the Credit Guarantee Trust for Micro &amp; Small Enterprises — built for growing MSMEs.</p>
      </div>
      <div class="feature-tile">
        <div class="icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 17l6-6 4 4 8-8" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 7h7v7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <h4>Dropline Overdraft</h4>
        <p>A gradually reducing OD limit — cheaper than a term loan, flexible like a cash credit, ideal for predictable revenue.</p>
      </div>
    </div>
    <p class="text-center" style="margin-top:40px;color:var(--text-muted);">
      Don't see your exact requirement here? <a href="contact" style="font-weight:600;">Talk to an advisor</a> — chances are we've structured something similar before.
    </p>
  </div>
</section>

<!-- ============ Who we serve ============ -->
<section class="section section-soft">
  <div class="container">
    <div class="text-center">
      <span class="eyebrow">Customer Types</span>
      <h2 class="section-title">Built for Every Borrower Profile</h2>
      <p class="section-lead">Different profiles, different paperwork. Click a tab to see what you'll need.</p>
    </div>

    <div class="doc-tabs">
      <div class="tab-head">
        <button class="tab-btn active" data-tab="salaried">Salaried</button>
        <button class="tab-btn" data-tab="proprietor">Proprietor</button>
        <button class="tab-btn" data-tab="partnership">Partnership / LLP</button>
        <button class="tab-btn" data-tab="pvtltd">Pvt Ltd</button>
      </div>
      <div class="tab-body">
        <div class="tab-pane active" data-pane="salaried">
          <h3 style="margin-bottom:6px;">For Salaried Individuals</h3>
          <p style="color:var(--text-muted);margin-bottom:22px;">Employed professionals with verifiable income and stable employment.</p>
          <ul class="doc-list">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>PAN Card</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Aadhaar Card</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Salary Slips (Last 3 months)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Bank Statement (12 months)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>ITR (Last 2 years)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Form 16</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Employment / Appointment Letter</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>ID Card of Company</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Property Documents (if applicable)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Passport Size Photographs</li>
          </ul>
        </div>
        <div class="tab-pane" data-pane="proprietor">
          <h3 style="margin-bottom:6px;">For Proprietors</h3>
          <p style="color:var(--text-muted);margin-bottom:22px;">Single-owner businesses with business turnover and filed returns.</p>
          <ul class="doc-list">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>PAN Card of Proprietor</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Aadhaar Card of Proprietor</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Business Address Proof</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Bank Statement (12 months)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>ITR (Last 3 years)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>GST Registration Certificate</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Shop &amp; Establishment Certificate</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Udyam Registration Certificate</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Property Documents (if applicable)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Passport Size Photographs</li>
          </ul>
        </div>
        <div class="tab-pane" data-pane="partnership">
          <h3 style="margin-bottom:6px;">For Partnership / LLP</h3>
          <p style="color:var(--text-muted);margin-bottom:22px;">Registered partnerships and Limited Liability Partnerships.</p>
          <ul class="doc-list">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Partnership Deed / LLP Agreement</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>PAN Card of Firm / LLP</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>PAN Card of All Partners</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Aadhaar Card of All Partners</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Bank Statement (12 months)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>ITR of Firm (Last 3 years)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>ITR of Partners (Last 3 years)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>GST Registration Certificate</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Certificate of Incorporation (LLP)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Board Resolution / Authority Letter</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Business Address Proof</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Passport Size Photographs of All Partners</li>
          </ul>
        </div>
        <div class="tab-pane" data-pane="pvtltd">
          <h3 style="margin-bottom:6px;">For Private Limited Companies</h3>
          <p style="color:var(--text-muted);margin-bottom:22px;">Registered private limited companies with filed financials.</p>
          <ul class="doc-list">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Certificate of Incorporation</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Memorandum of Association (MOA)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Articles of Association (AOA)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>PAN Card of Company</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>PAN Card of All Directors</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Aadhaar Card of All Directors</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Board Resolution for Loan</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Bank Statement (12 months)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Audited Financials (Last 3 years)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>ITR of Company (Last 3 years)</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>GST Registration Certificate</li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12" stroke-linecap="round" stroke-linejoin="round"/></svg>Business Address Proof</li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============ Process ============ -->
<section class="section">
  <div class="container">
    <div class="text-center">
      <span class="eyebrow">Our Process</span>
      <h2 class="section-title">A Predictable, End-to-End Journey</h2>
      <p class="section-lead">We manage every stage so you don't have to juggle bankers, valuers and lawyers.</p>
    </div>
    <div class="process-wrap">
      <div class="process-step"><div class="process-num">1</div><h4>Free Consultation</h4><p>Understand your requirement, eligibility and the right product fit.</p></div>
      <div class="process-step"><div class="process-num">2</div><h4>Document Collection</h4><p>Digital upload with a checklist tailored to your customer type.</p></div>
      <div class="process-step"><div class="process-num">3</div><h4>Bank Processing</h4><p>Legal, technical and valuation runs — all coordinated by us.</p></div>
      <div class="process-step"><div class="process-num">4</div><h4>Disbursement</h4><p>Sanction letter, signing, disbursement and post-disbursement follow-up.</p></div>
    </div>
  </div>
</section>

<!-- ============ FAQ ============ -->
<section class="section section-soft">
  <div class="container">
    <div class="text-center">
      <span class="eyebrow">FAQs</span>
      <h2 class="section-title">Frequently Asked Questions</h2>
    </div>
    <div class="faq">
      <div class="faq-item">
        <button class="faq-q">
          <span>How long does a home-loan sanction usually take?</span>
          <svg class="plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          Typically 7–14 working days from the time we receive a complete document set — though simpler files can sanction in under a week. We'll give you a realistic ETA on day one.
        </div></div>
      </div>
      <div class="faq-item">
        <button class="faq-q">
          <span>What charges are involved beyond the interest rate?</span>
          <svg class="plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          Common charges include processing fee, legal &amp; technical valuation, CERSAI, stamp duty on the MOU and GST. We disclose every applicable charge upfront in your quotation so there are no last-minute surprises.
        </div></div>
      </div>
      <div class="faq-item">
        <button class="faq-q">
          <span>Can I apply with a co-borrower?</span>
          <svg class="plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          Yes — spouse, parents, siblings and children can be added as co-applicants. Adding a co-borrower often improves eligibility and can yield tax-benefit advantages for both parties.
        </div></div>
      </div>
      <div class="faq-item">
        <button class="faq-q">
          <span>Do you work with self-employed and business owners?</span>
          <svg class="plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          Absolutely. A large part of our book is proprietors, partnerships, LLPs and private limited companies. We tailor the paperwork and bank-fit to the profile.
        </div></div>
      </div>
      <div class="faq-item">
        <button class="faq-q">
          <span>Is there a prepayment penalty?</span>
          <svg class="plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          For home loans on floating rates, RBI guidelines prohibit prepayment penalties for individual borrowers. For fixed-rate or non-individual borrowings, bank policy applies — we'll flag it during the quotation stage.
        </div></div>
      </div>
      <div class="faq-item">
        <button class="faq-q">
          <span>Can I transfer my existing loan to a lower rate?</span>
          <svg class="plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
        </button>
        <div class="faq-a"><div class="faq-a-inner">
          Yes — our Balance Transfer service moves your outstanding to a new bank at a better rate, often with a top-up. We'll run a cost-benefit analysis before you commit.
        </div></div>
      </div>
    </div>
  </div>
</section>

<!-- ============ CTA ============ -->
<section class="cta-strip">
  <div class="container">
    <div>
      <h2>Not sure which product fits?</h2>
      <p>Let our advisor listen to your situation and recommend the right structure.</p>
    </div>
    <a href="contact" class="btn btn-lg">Talk to an Advisor</a>
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
