# PDF Generation

## Overview

The system generates bilingual comparison PDFs for loan quotations. Three-tier generation strategy with client-side offline fallback.

## Server-Side: `PdfGenerationService`

### Generation Strategy (priority order)

1. **Microservice-only mode** — if `app.pdf_use_microservice` is true, skip Chrome
2. **Chrome headless** — render HTML → `--print-to-pdf` (fastest, no external dependency needed)
3. **Microservice fallback** — cURL POST to configurable endpoint

### Chrome Path Detection

Auto-detects per OS:
- **Windows:** `C:\Program Files\Google\Chrome\Application\chrome.exe` and `C:\Program Files (x86)\...`
- **Linux:** `google-chrome`, `google-chrome-stable`, `chromium-browser`, `chromium`
- **macOS:** `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`
- Override via `app.chrome_path` env variable

### Microservice Config
- `app.pdf_service_url` — endpoint (default: `http://127.0.0.1:3000/pdf`)
- `app.pdf_service_key` — API key sent as `X-API-Key` header

### File Storage
- Output: `storage/app/pdfs/Loan_Proposal_{customerName}_{date}_{time}.pdf`
- Temp HTML: `storage/app/tmp/` (auto-cleaned)

## HTML Template (`renderHtml()`)

### Structure
- Full HTML5 document with embedded CSS (no external dependencies)
- Embedded fonts: Jost, Archivo, NotoSansGujarati (base64 woff2/ttf)
- A4 page size with print margins

### Pages
1. **Documents page** — customer details, prepared-by info, numbered document list (bilingual)
2. **EMI comparison pages** — one page per 2 tenure columns, all banks as rows
3. **Charges comparison page** — all banks side by side with charge breakdown
4. **Additional notes** — if provided

### Template Data
```php
[
    'customerName', 'customerType', 'loanAmount', 'date',
    'companyPhone', 'companyEmail',
    'tenures' => [5, 10, 15, 20],
    'banks' => [
        ['name', 'roiMin', 'roiMax', 'charges' => [...], 'emiByTenure' => [...]]
    ],
    'documents' => [['en' => '...', 'gu' => '...']],
    'additionalNotes', 'ourServices',
    'preparedByName', 'preparedByMobile'
]
```

### Features
- Fixed header on every page (logo, customer info)
- Fixed footer (services disclaimer)
- Bilingual amount in words (English / Gujarati)
- Indian number formatting (lakh/crore comma system)
- Branded vs plain toggle (removes SHF logo/info)
- Dynamic column sizing for 1-6 banks

## Client-Side: `pdf-renderer.js`

### `PdfRenderer.renderHtml(data, logoBase64)`
- Mirrors server template exactly for offline use
- Returns HTML string

### `PdfRenderer.generateOfflinePdf(payload, config, logoBase64)`
- Builds data from offline cache
- Opens HTML in new tab → triggers print dialog
- iOS fallback: downloads HTML file (iOS lacks print support)
- Popup blocker fallback: downloads HTML instead

## Download Endpoints

| Route | Purpose |
|-------|---------|
| `quotations/{quotation}/download` | Download PDF by quotation ID |
| `download-pdf?file={filename}` | Download PDF by filename |
| `quotations/{quotation}/preview-html` | Preview as HTML (super_admin) |
