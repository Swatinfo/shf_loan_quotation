# PDF Generation System

## Overview

The PDF system renders bilingual (English/Gujarati) loan proposal documents. It builds a complete HTML page server-side with embedded fonts and images, then converts it to PDF using Chrome headless or a microservice fallback.

## Service: PdfGenerationService

**File**: `app/Services/PdfGenerationService.php` (689 lines)

## Generation Strategy (3-tier)

```
1. If config('app.pdf_use_microservice') === true
   → Use microservice ONLY (escape hatch for environments without Chrome)

2. If Chrome is available (exec() enabled + binary found)
   → Use Chrome headless (fastest, preferred)

3. Fallback
   → Use microservice (cURL to external service)
```

### generate(array $data): array

**Input**: Full quotation data array (customerName, banks, documents, tenures, etc.)

**Process**:
1. Creates directories: `storage/app/pdfs/`, `storage/app/tmp/`
2. Generates filename: `Loan_Proposal_{sanitizedName}_{date}_{time}.pdf`
3. Calls `renderHtml($data)` to build the HTML
4. Attempts PDF conversion via the 3-tier strategy above
5. Cleans up temp HTML file

**Return**:
- Success: `['success' => true, 'filename' => '...', 'path' => '/absolute/path/to/file.pdf']`
- Failure: `['error' => 'Error description']`

## Chrome Headless (Any OS — Primary)

### isChromeAvailable(): bool

Checks:
1. `exec()` function exists and is not disabled in `php.ini`
2. Chrome binary can be located via `getChromePath()`
3. On non-Windows: verifies binary exists via `command -v`

### getChromePath(): string

**Resolution order**:
1. `config('app.chrome_path')` (env var `CHROME_PATH`)
2. Windows auto-detect:
   - `C:\Program Files\Google\Chrome\Application\chrome.exe`
   - `C:\Program Files (x86)\Google\Chrome\Application\chrome.exe`
3. Linux/macOS auto-detect:
   - `/usr/bin/google-chrome`
   - `/usr/bin/google-chrome-stable`
   - `/usr/bin/chromium-browser`
   - `/usr/bin/chromium`
   - `/snap/bin/chromium`
4. Fallback: `chrome` (Windows) or `google-chrome` (Linux/macOS)

### generateWithChrome(string $tmpHtml, string $filepath): ?array

**Chrome flags**:
```
--headless --disable-gpu --no-sandbox --disable-software-rasterizer
--run-all-compositor-stages-before-draw --print-to-pdf={path}
--no-pdf-header-footer {tmpHtml}
```

- Windows: standard `cmd` formatting
- Linux/macOS: `escapeshellarg()` for all paths
- Validates file was created and has non-zero size
- Logs errors with exit code and command output

## Microservice Fallback (Linux)

### generateWithMicroservice(string $html, string $filepath): ?array

**Config**:
- `config('app.pdf_service_url')` → default `http://127.0.0.1:3000/pdf`
- `config('app.pdf_service_key')` → optional API key

**Request**:
- POST with JSON body: `{ "html": "<full HTML string>" }`
- Headers: `Content-Type: application/json`, optional `X-API-Key`
- Timeouts: connect 5s, total 60s

**Validation**: HTTP 200 + non-empty response body → writes to file

## HTML Rendering

### renderHtml(array $data): string

Builds a complete standalone HTML document (689 lines of rendering logic).

**Embedded Resources** (base64-encoded):
- `NotoSansGujarati-Regular.ttf` — Gujarati text
- `NotoSansGujarati-Bold.ttf` — Gujarati headings
- `public/images/logo3.png` — Company logo (with fallback embedded logo)

**Bilingual Labels** (`$labels` property):
```php
$labels = [
    'pdfTitle'     => 'Loan Proposal / લોન દરખાસ્ત',
    'customer'     => 'Customer / ગ્રાહક',
    'type'         => 'Type / પ્રકાર',
    'loanAmount'   => 'Loan Amount / લોન રકમ',
    'roi'          => 'Rate of Interest / વ્યાજ દર',
    'emiComparison'=> 'EMI Comparison / EMI સરખામણી',
    'chargesComparison' => 'Charges Comparison / ચાર્જ સરખામણી',
    // ... more labels
];
```

**Document Sections** (in order):
1. **Header**: Logo + "Loan Proposal / લોન દરખાસ્ત" title
2. **Customer Details**: Name, type (bilingual), loan amount (formatted + words in EN/GU), date
3. **Company Contact**: Phone + email from config
4. **Required Documents**: Bilingual checklist (English / Gujarati per document)
5. **Bank Details Section**: Bank names with ROI ranges
6. **EMI Comparison Tables**: One table per tenure year, columns = banks, rows = EMI/Interest/Total
7. **Charges Comparison Table**: All charge types as rows, banks as columns (zero-charge rows hidden)
8. **Additional Notes**: Free text (if provided)
9. **Prepared By**: Name + mobile (if provided)

**Styling**:
- Color scheme: Primary dark `#6b6868`, Accent `#f15a29`, Accent tint `#fef0eb`
- Dynamic column widths based on number of banks
- Font size: 10pt base
- Page breaks between major sections
- Alternating row backgrounds in tables
- Total rows highlighted with accent color

**Number Formatting**:
- All currency values: `NumberToWordsService::formatCurrency()` → `₹ X,XX,XXX`
- Loan amount in words: `NumberToWordsService::toBilingual()` → "English / ગુજરાતી"

**Customer Type Labels** (static method `getTypeLabel`):
| Type | Label |
|------|-------|
| `proprietor` | Proprietor / માલિકી |
| `partnership_llp` | Partnership / LLP / ભાગીદારી / LLP |
| `pvt_ltd` | Private Limited / પ્રાઇવેટ લિમિટેડ |
| `salaried` | Salaried / પગારદાર |
| `all` | All Types / તમામ પ્રકાર |

## File Storage

- **Directory**: `storage/app/pdfs/`
- **Filename pattern**: `Loan_Proposal_{CustomerName}_{ddMMyyyy}_{HHmmss}.pdf`
- **Temp HTML**: `storage/app/tmp/` (cleaned up after generation)
- **DB fields**: `quotations.pdf_filename` (filename only), `quotations.pdf_path` (absolute path)

## Client-Side PDF (Offline)

The file `public/js/pdf-renderer.js` mirrors the server-side rendering for offline use:
- Generates the same HTML template structure
- Uses `Blob URL + window.print()` for "Save as PDF"
- iOS fallback: downloads `.html` file (iOS doesn't support `window.print()`)
- Same bilingual labels and formatting from `config-translations.js`

## Troubleshooting

| Issue | Cause | Fix |
|-------|-------|-----|
| PDF generation fails silently | `exec()` disabled in php.ini | Enable exec() or use microservice |
| Chrome not found | Non-standard install path | Set `CHROME_PATH` env variable |
| Gujarati text missing | Font files not found | Check `public/fonts/` directory |
| Empty PDF file | Chrome crash during render | Check Chrome version compatibility |
| Microservice timeout | Service not running or slow | Check `PDF_SERVICE_URL` and service logs |
