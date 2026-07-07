<?php
/**
 * SHF marketing site — per-page SEO metadata.
 *
 * Keyed by page slug (filename without .php). Each entry is used by
 * `_seo-head.php` (canonical, OG, Twitter) and `_seo-foot.php`
 * (JSON-LD structured data).
 *
 * Update the 'aggregateRating' block in _seo-foot.php with your real
 * Google-Business-Profile review count / average when you have it.
 */

return [
    'index' => [
        'title'       => 'SHF — Shreenathji Home Finance | Shaping Happiness Forever',
        'description' => 'Shreenathji Home Finance (SHF) — compare home loan, loan against property, business loan and balance transfer offers from 15+ leading banks and NBFCs. Zero consulting fees, dedicated advisor, serving Gujarat and beyond.',
        'keywords'    => 'shreenathji home finance, SHF, home loan rajkot, home loan gujarat, loan against property, business loan, personal loan, project finance, balance transfer, loan advisor rajkot, housing finance company, home loan comparison, MSME loan, CGTMSE, working capital loan',
        'ogType'      => 'website',
        'breadcrumb'  => [],
        'schemaType'  => 'WebSite',
    ],
    'about' => [
        'title'       => 'About SHF | Shreenathji Home Finance — Rajkot',
        'description' => 'Learn about Shreenathji Home Finance (SHF) — a Rajkot-based loan advisory firm with 10+ years helping families and businesses across Gujarat secure the right loan from leading banks and NBFCs.',
        'keywords'    => 'about shreenathji home finance, SHF company, loan advisor rajkot, home loan company gujarat, shf about us, shaping happiness forever',
        'ogType'      => 'website',
        'breadcrumb'  => [['About Us', 'about']],
        'schemaType'  => 'AboutPage',
    ],
    'services' => [
        'title'       => 'Loan Services | Home, Business, LAP, Personal — SHF',
        'description' => 'Explore SHF loan services — home loan, loan against property, business loan, personal loan, project finance, balance transfer, plus specialised finance for MSMEs, working capital and more.',
        'keywords'    => 'loan services rajkot, home loan, loan against property, business loan, personal loan, project finance, balance transfer, working capital, machinery finance, invoice discounting, MSME loan, CGTMSE, dropline overdraft, GST overdraft',
        'ogType'      => 'website',
        'breadcrumb'  => [['Services', 'services']],
        'schemaType'  => 'Service',
    ],
    'contact' => [
        'title'       => 'Contact SHF | Shreenathji Home Finance — Rajkot Office',
        'description' => 'Get in touch with Shreenathji Home Finance (SHF). Office at R K Prime, Silver Height, Near Nana Mava Circle, 150 Ft Ring Road, Rajkot. Sales: +91 99747 89089 | Customer Care: +91 90990 89072.',
        'keywords'    => 'contact shreenathji home finance, SHF rajkot, loan advisor contact, home loan enquiry rajkot, SHF phone number, SHF office address',
        'ogType'      => 'website',
        'breadcrumb'  => [['Contact Us', 'contact']],
        'schemaType'  => 'ContactPage',
    ],
    'faq' => [
        'title'       => 'FAQs | Home Loan, CIBIL, Documents — SHF',
        'description' => 'Honest, jargon-free answers to the most common questions about home loans, eligibility, CIBIL, documentation, balance transfer and more — from Shreenathji Home Finance (SHF).',
        'keywords'    => 'home loan FAQ, loan eligibility, CIBIL score home loan, home loan documents, balance transfer FAQ, SHF FAQ, loan advisor questions',
        'ogType'      => 'website',
        'breadcrumb'  => [['FAQs', 'faq']],
        'schemaType'  => 'FAQPage',
    ],
    'privacy' => [
        'title'       => 'Privacy Policy — SHF | Shreenathji Home Finance',
        'description' => 'How Shreenathji Home Finance (SHF) collects, uses and protects the personal and financial information you share with us.',
        'keywords'    => 'SHF privacy policy, data protection, information sharing policy',
        'ogType'      => 'website',
        'breadcrumb'  => [['Privacy Policy', 'privacy']],
        'schemaType'  => 'WebPage',
        'robots'      => 'noindex, follow',
    ],
    'terms' => [
        'title'       => 'Terms & Conditions — SHF | Shreenathji Home Finance',
        'description' => 'The rules governing your use of the Shreenathji Home Finance (SHF) website and our loan advisory services.',
        'keywords'    => 'SHF terms, terms and conditions, loan advisory terms',
        'ogType'      => 'website',
        'breadcrumb'  => [['Terms & Conditions', 'terms']],
        'schemaType'  => 'WebPage',
        'robots'      => 'noindex, follow',
    ],

    /* ============== Core Products ============== */
    'home-loan' => [
        'title'       => 'Home Loan in Rajkot, Gujarat — Apply Online | SHF',
        'description' => 'Get a home loan via SHF — compare offers from 15+ banks and NBFCs, long-tenure options, competitive rates. Serving families across Gujarat with zero consulting fees.',
        'keywords'    => 'home loan rajkot, home loan gujarat, housing finance rajkot, home loan comparison, home loan interest rate, home loan eligibility, best home loan, home loan for salaried, home loan for self employed, NRI home loan, first time home buyer loan, ready to move home loan, under construction home loan, plot and construction loan',
        'ogType'      => 'product',
        'breadcrumb'  => [['Services', 'services'], ['Home Loan', 'home-loan']],
        'schemaType'  => 'LoanProduct',
        'productName' => 'Home Loan',
    ],
    'loan-against-property' => [
        'title'       => 'Loan Against Property (LAP) in Rajkot | SHF',
        'description' => 'Unlock the equity in your residential, commercial or industrial property. LAP via Shreenathji Home Finance (SHF) — competitive rates, long tenure, end-use flexibility.',
        'keywords'    => 'loan against property rajkot, LAP rajkot, property loan gujarat, mortgage loan, commercial property loan, residential property loan, LAP balance transfer, top up loan against property',
        'ogType'      => 'product',
        'breadcrumb'  => [['Services', 'services'], ['Loan Against Property', 'loan-against-property']],
        'schemaType'  => 'LoanProduct',
        'productName' => 'Loan Against Property',
    ],
    'business-loan' => [
        'title'       => 'Business Loan in Rajkot — Unsecured & Secured | SHF',
        'description' => 'Business loans for proprietors, partnerships, LLPs and private limited companies. Secured and unsecured options, GST-based underwriting, fast turnaround via SHF.',
        'keywords'    => 'business loan rajkot, business loan gujarat, unsecured business loan, SME loan, proprietor loan, partnership loan, LLP loan, private limited loan, GST based business loan, working capital loan',
        'ogType'      => 'product',
        'breadcrumb'  => [['Services', 'services'], ['Business Loan', 'business-loan']],
        'schemaType'  => 'LoanProduct',
        'productName' => 'Business Loan',
    ],
    'personal-loan' => [
        'title'       => 'Personal Loan in Rajkot — Quick, Unsecured | SHF',
        'description' => 'Unsecured personal loans for weddings, medical, education or debt consolidation. Compare offers across digital and traditional lenders via Shreenathji Home Finance.',
        'keywords'    => 'personal loan rajkot, personal loan gujarat, unsecured loan, instant personal loan, salaried personal loan, self employed personal loan, debt consolidation loan, wedding loan, medical loan',
        'ogType'      => 'product',
        'breadcrumb'  => [['Services', 'services'], ['Personal Loan', 'personal-loan']],
        'schemaType'  => 'LoanProduct',
        'productName' => 'Personal Loan',
    ],
    'project-finance' => [
        'title'       => 'Project & Developer Finance in Gujarat | SHF',
        'description' => 'Structured real-estate project finance for developers — land acquisition, construction, inventory funding and approved-project finance with milestone-linked disbursement.',
        'keywords'    => 'project finance gujarat, developer finance, construction finance, real estate project loan, builder finance, RERA project loan, construction loan, land plus construction loan, project loan rajkot',
        'ogType'      => 'product',
        'breadcrumb'  => [['Services', 'services'], ['Project & Developer Finance', 'project-finance']],
        'schemaType'  => 'LoanProduct',
        'productName' => 'Project & Developer Finance',
    ],
    'balance-transfer' => [
        'title'       => 'Home Loan Balance Transfer | Save on EMI — SHF',
        'description' => 'Transfer your existing home loan, LAP or business loan to a better rate. Free cost-benefit analysis + top-up option via Shreenathji Home Finance (SHF).',
        'keywords'    => 'home loan balance transfer, LAP balance transfer, business loan balance transfer, top up loan, lower home loan EMI, reduce home loan interest, home loan refinance, balance transfer rajkot',
        'ogType'      => 'product',
        'breadcrumb'  => [['Services', 'services'], ['Balance Transfer', 'balance-transfer']],
        'schemaType'  => 'LoanProduct',
        'productName' => 'Balance Transfer',
    ],

    /* ============== Specialised Finance ============== */
    'working-capital' => [
        'title'       => 'Working Capital Finance | CC, OD, Bill Discount — SHF',
        'description' => 'Business liquidity via cash credit, overdrafts, bill discounting and short-term loans — sized to your sales cycle. Arranged by Shreenathji Home Finance.',
        'keywords'    => 'working capital loan rajkot, cash credit, overdraft, bill discounting, business liquidity, seasonal loan, short term business loan',
        'ogType'      => 'product',
        'breadcrumb'  => [['Services', 'services'], ['Working Capital Finance', 'working-capital']],
        'schemaType'  => 'LoanProduct',
        'productName' => 'Working Capital Finance',
    ],
    'machinery-finance' => [
        'title'       => 'Machinery & Equipment Finance | SHF',
        'description' => 'Finance new or used plant, machinery and equipment without tying up working capital. Asset-backed loans with tenure matched to productive life.',
        'keywords'    => 'machinery loan rajkot, equipment finance, plant machinery loan, industrial equipment loan, hypothecation loan, machinery lease, used machinery loan',
        'ogType'      => 'product',
        'breadcrumb'  => [['Services', 'services'], ['Machinery & Equipment Finance', 'machinery-finance']],
        'schemaType'  => 'LoanProduct',
        'productName' => 'Machinery & Equipment Finance',
    ],
    'invoice-discounting' => [
        'title'       => 'Invoice Discounting / Bill Finance | SHF',
        'description' => 'Convert unpaid customer invoices into cash upfront. With-recourse, without-recourse and TReDS options via Shreenathji Home Finance.',
        'keywords'    => 'invoice discounting rajkot, bill discounting, receivables finance, TReDS, invoice factoring, trade finance, invoice loan',
        'ogType'      => 'product',
        'breadcrumb'  => [['Services', 'services'], ['Invoice Discounting', 'invoice-discounting']],
        'schemaType'  => 'LoanProduct',
        'productName' => 'Invoice Discounting',
    ],
    'gst-overdraft' => [
        'title'       => 'GST-Based Overdraft for MSMEs | SHF',
        'description' => 'Unsecured overdraft limits calculated from your GST returns. Digital underwriting, no property collateral — built for compliant MSMEs.',
        'keywords'    => 'GST overdraft, GST based loan, MSME overdraft, digital business loan, unsecured OD, GST returns loan, MSME working capital',
        'ogType'      => 'product',
        'breadcrumb'  => [['Services', 'services'], ['GST-Based Overdraft', 'gst-overdraft']],
        'schemaType'  => 'LoanProduct',
        'productName' => 'GST-Based Overdraft',
    ],
    'msme-loan' => [
        'title'       => 'MSME Loan / CGTMSE Collateral-Free Credit | SHF',
        'description' => 'Collateral-free credit for micro and small enterprises under the CGTMSE scheme. Term loans and working capital for growing MSMEs via SHF.',
        'keywords'    => 'MSME loan rajkot, CGTMSE loan, collateral free loan, micro enterprise loan, small business loan, udyam registered loan, MSME working capital, MSME term loan',
        'ogType'      => 'product',
        'breadcrumb'  => [['Services', 'services'], ['MSME / CGTMSE Loan', 'msme-loan']],
        'schemaType'  => 'LoanProduct',
        'productName' => 'MSME / CGTMSE Loan',
    ],
    'dropline-overdraft' => [
        'title'       => 'Dropline Overdraft — Flexible Credit | SHF',
        'description' => 'A reducing overdraft limit that combines term-loan discipline with OD flexibility. Ideal for businesses with predictable revenue patterns.',
        'keywords'    => 'dropline overdraft, reducing OD, business overdraft, flexible business loan, dropline loan, secured OD rajkot',
        'ogType'      => 'product',
        'breadcrumb'  => [['Services', 'services'], ['Dropline Overdraft', 'dropline-overdraft']],
        'schemaType'  => 'LoanProduct',
        'productName' => 'Dropline Overdraft',
    ],
];
