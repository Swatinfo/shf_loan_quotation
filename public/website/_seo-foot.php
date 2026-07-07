<?php
/**
 * SHF marketing site — end-of-body JSON-LD block.
 * Outputs schema.org structured data so Google (and other search engines)
 * understand the site, rank it better, and potentially display rich results
 * like star ratings, breadcrumbs, and FAQ accordions on SERPs.
 *
 * Included right before </body> on every page.
 *
 * Emits:
 *   - FinancialService / LocalBusiness (site-wide — same on every page)
 *   - BreadcrumbList (per page, when breadcrumb is set)
 *   - Service / Product (for loan-product pages)
 *   - FAQPage (for faq page)
 *   - WebSite with SearchAction (on home page)
 */

$je = fn($s) => json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

/* =========== 1. Organization / FinancialService (always) =========== */
$organization = [
    '@context'      => 'https://schema.org',
    '@type'         => 'FinancialService',
    '@id'           => $siteUrl . '#organization',
    'name'          => $siteName,
    'alternateName' => $siteShortName,
    'url'           => $siteUrl,
    'logo'          => $siteLogo,
    'image'         => $siteLogo,
    'slogan'        => $siteTagline,
    'description'   => 'Shreenathji Home Finance (SHF) is a loan advisory firm in Rajkot, Gujarat, offering home loans, loans against property, business loans, personal loans, project finance and balance-transfer services across a panel of 15+ leading banks and NBFCs.',
    'foundingDate'  => $siteFoundingYear,
    'address'       => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => $siteStreet,
        'addressLocality' => $siteLocality,
        'addressRegion'   => $siteRegion,
        'postalCode'      => $sitePostalCode,
        'addressCountry'  => $siteCountry,
    ],
    'geo'           => [
        '@type'     => 'GeoCoordinates',
        'latitude'  => $siteLatitude,
        'longitude' => $siteLongitude,
    ],
    'telephone'     => $sitePhoneSales,
    'email'         => $siteEmail,
    'contactPoint'  => [
        [
            '@type'             => 'ContactPoint',
            'telephone'         => $sitePhoneSales,
            'contactType'       => 'sales',
            'areaServed'        => 'IN',
            'availableLanguage' => ['English', 'Gujarati', 'Hindi'],
        ],
        [
            '@type'             => 'ContactPoint',
            'telephone'         => $sitePhoneCare,
            'contactType'       => 'customer service',
            'areaServed'        => 'IN',
            'availableLanguage' => ['English', 'Gujarati', 'Hindi'],
        ],
    ],
    'openingHours'  => $siteOpeningHours,
    'priceRange'    => '₹₹',
    'areaServed'    => [
        ['@type' => 'State', 'name' => 'Gujarat'],
        ['@type' => 'Country', 'name' => 'India'],
    ],
    'serviceType'   => [
        'Home Loan',
        'Loan Against Property',
        'Business Loan',
        'Personal Loan',
        'Project Finance',
        'Balance Transfer',
        'Working Capital Finance',
        'Machinery Finance',
        'MSME / CGTMSE Loan',
    ],
    'sameAs'        => $siteSocial,
    'aggregateRating' => [
        '@type'       => 'AggregateRating',
        'ratingValue' => $aggregateRating['ratingValue'],
        'reviewCount' => $aggregateRating['reviewCount'],
        'bestRating'  => $aggregateRating['bestRating'],
        'worstRating' => $aggregateRating['worstRating'],
    ],
];

echo "\n" . '<script type="application/ld+json">' . $je($organization) . '</script>' . "\n";

/* =========== 2. WebSite schema (only on home page) =========== */
if ($pageSlug === 'index') {
    $website = [
        '@context'      => 'https://schema.org',
        '@type'         => 'WebSite',
        '@id'           => $siteUrl . '#website',
        'url'           => $siteUrl,
        'name'          => $siteName,
        'description'   => $pageSeo['description'] ?? '',
        'publisher'     => ['@id' => $siteUrl . '#organization'],
        'inLanguage'    => 'en-IN',
    ];
    echo '<script type="application/ld+json">' . $je($website) . '</script>' . "\n";
}

/* =========== 3. BreadcrumbList (when page has a breadcrumb) =========== */
if (!empty($pageSeo['breadcrumb'])) {
    $items = [
        [
            '@type'    => 'ListItem',
            'position' => 1,
            'name'     => 'Home',
            'item'     => $siteUrl . $siteBasePath . '/',
        ],
    ];
    $position = 2;
    foreach ($pageSeo['breadcrumb'] as [$label, $slug]) {
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position++,
            'name'     => $label,
            'item'     => $siteUrl . $siteBasePath . '/' . $slug,
        ];
    }
    $breadcrumb = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    ];
    echo '<script type="application/ld+json">' . $je($breadcrumb) . '</script>' . "\n";
}

/* =========== 4. Service / Product schema (for loan pages) =========== */
if (($pageSeo['schemaType'] ?? '') === 'LoanProduct' && !empty($pageSeo['productName'])) {
    $service = [
        '@context'    => 'https://schema.org',
        '@type'       => 'FinancialProduct',
        'name'        => $pageSeo['productName'],
        'description' => $pageSeo['description'] ?? '',
        'url'         => $canonicalUrl,
        'provider'    => ['@id' => $siteUrl . '#organization'],
        'category'    => 'Loan',
        'areaServed'  => [
            ['@type' => 'State', 'name' => 'Gujarat'],
            ['@type' => 'Country', 'name' => 'India'],
        ],
        'aggregateRating' => [
            '@type'       => 'AggregateRating',
            'ratingValue' => $aggregateRating['ratingValue'],
            'reviewCount' => $aggregateRating['reviewCount'],
            'bestRating'  => $aggregateRating['bestRating'],
            'worstRating' => $aggregateRating['worstRating'],
        ],
    ];
    echo '<script type="application/ld+json">' . $je($service) . '</script>' . "\n";
}

/* =========== 5. FAQPage schema (for faq.php) =========== */
if ($pageSlug === 'faq') {
    $faqItems = [
        ['Is SHF a bank? Do you lend the money yourselves?', 'No. SHF is a loan advisory and facilitation firm. The actual loan is sanctioned and disbursed by the bank or NBFC you choose — we structure the application, compare offers, negotiate on your behalf and handle the paperwork end-to-end.'],
        ['Do I pay SHF a consulting fee?', 'Our initial consultation, eligibility assessment and bank-comparison quotation are free of charge. If a service-specific fee applies in your case, we disclose it in writing before you engage us.'],
        ['How long does it take from enquiry to disbursement?', 'It depends on product and file readiness. A personal loan can move in 48–72 hours. A home loan typically sanctions in 7–14 working days from complete documentation, with disbursement shortly after.'],
        ['My CIBIL score is low. Can SHF still help?', 'Often, yes. A low score narrows the pool of lenders but does not automatically disqualify. We review your CIBIL report, flag reportable errors, suggest quick clean-ups, and shortlist lenders who weigh the full picture.'],
        ['What documents will I need to submit?', 'It varies by profile and product. Salaried applicants need PAN, Aadhaar, recent salary slips, Form 16 and bank statements. Self-employed applicants additionally provide ITRs, audited financials and GST/Udyam registrations.'],
        ['What interest rate will I get?', 'Your rate depends on your credit score, income profile, loan amount, tenure, product type and the specific lender\'s current pricing. Because we compare offers across a panel of lenders, you see the real options for your profile.'],
        ['Is there a prepayment penalty?', 'For floating-rate home loans to individual borrowers, RBI guidelines prohibit prepayment penalties. For fixed-rate or non-individual loans, bank-specific policy applies — we flag any penalty clause during the quotation stage.'],
        ['Is my information safe with SHF?', 'Yes. We apply encrypted document transmission, controlled access, activity logging, and share data with partner lenders only with your express consent.'],
    ];
    $mainEntity = [];
    foreach ($faqItems as [$q, $a]) {
        $mainEntity[] = [
            '@type'          => 'Question',
            'name'           => $q,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a],
        ];
    }
    $faqSchema = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => $mainEntity,
    ];
    echo '<script type="application/ld+json">' . $je($faqSchema) . '</script>' . "\n";
}
?>
