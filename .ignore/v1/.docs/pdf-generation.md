# PDF Generation

PDF generation is handled by `PdfGenerationService`, which renders a complete HTML document and converts it to PDF via Chrome headless or a microservice fallback.

## Three-Tier Strategy

The service uses a prioritized fallback chain:

1. **Microservice-only mode** — if `PDF_USE_MICROSERVICE=true` in env, skip Chrome entirely and use the microservice
2. **Chrome headless** (default) — fastest path; uses locally installed Chrome/Chromium. If Chrome fails AND a microservice URL is configured, falls back to tier 3
3. **Microservice fallback** — HTTP POST to a PDF microservice when Chrome is unavailable or fails

### Decision flow:
```
config('app.pdf_use_microservice') == true?
  └─ YES → microservice only
  └─ NO  → isChromeAvailable()?
              └─ YES → Chrome headless → on failure, try microservice if URL configured
              └─ NO  → microservice fallback
```

## Chrome Detection

`isChromeAvailable()` checks:
1. `exec()` function exists and is not in `disable_functions`
2. Chrome binary is found at the configured or auto-detected path

`getChromePath()` resolution order:
1. `config('app.chrome_path')` — explicit path from `.env` (`CHROME_PATH`)
2. **Windows** auto-detect:
   - `C:\Program Files\Google\Chrome\Application\chrome.exe`
   - `C:\Program Files (x86)\Google\Chrome\Application\chrome.exe`
   - Falls back to bare `chrome`
3. **Linux/macOS** auto-detect:
   - `/usr/bin/google-chrome`
   - `/usr/bin/google-chrome-stable`
   - `/usr/bin/chromium-browser`
   - `/usr/bin/chromium`
   - `/snap/bin/chromium`
   - Falls back to bare `google-chrome`
4. On Linux, uses `command -v` to check PATH for bare names

## Chrome Command Building

Chrome is invoked with these flags:

```
chrome --headless --disable-gpu --no-sandbox --disable-software-rasterizer
       --run-all-compositor-stages-before-draw
       --user-data-dir={temp_profile}
       --print-to-pdf={output_path}
       --no-pdf-header-footer
       {input_html_file}
```

- A unique temporary profile directory (`shf_chrome_{uniqid}`) is created per run and cleaned up after
- On Windows, paths use backslashes and the command is quoted differently
- On Linux/macOS, all arguments are shell-escaped via `escapeshellarg()`

## Microservice

When using the microservice:
- **URL**: `config('app.pdf_service_url')`, default `http://127.0.0.1:3000/pdf`
- **Auth**: Optional `X-API-Key` header from `config('app.pdf_service_key')`
- **Payload**: `POST` with JSON body `{"html": "<full HTML>"}`
- **Timeouts**: connect 5s, total 60s
- **Response**: Raw PDF binary written directly to file

## Environment Configuration

| Env Variable | Config Key | Purpose |
|-------------|-----------|---------|
| `CHROME_PATH` | `app.chrome_path` | Explicit Chrome binary path |
| `PDF_USE_MICROSERVICE` | `app.pdf_use_microservice` | Force microservice-only mode |
| `PDF_SERVICE_URL` | `app.pdf_service_url` | Microservice endpoint URL |
| `PDF_SERVICE_KEY` | `app.pdf_service_key` | Microservice API key |

## HTML Template Rendering

`renderHtml(array $data)` builds a complete self-contained HTML document with all styles, fonts, and images embedded inline (no external dependencies).

### Template Data Structure

```php
[
    'customerName'    => string,
    'customerType'    => string,
    'loanAmount'      => int,
    'date'            => string,        // 'dd F Y' format
    'companyPhone'    => string,
    'companyEmail'    => string,
    'tenures'         => int[],         // e.g. [5, 10, 15, 20]
    'banks'           => [
        [
            'name'        => string,
            'roiMin'      => float,
            'roiMax'      => float,
            'charges'     => [pf, admin, stamp_notary, registration_fee, advocate, iom, tc, extra1Name, extra1Amt, extra2Name, extra2Amt, total],
            'emiByTenure' => [tenure => [emi, totalInterest, totalPayment]],
        ],
    ],
    'documents'       => [['en' => string, 'gu' => string]],
    'additionalNotes' => string,
    'ourServices'     => string,
    'preparedByName'  => string,
    'preparedByMobile'=> string,
]
```

### Bilingual Labels

All PDF labels are bilingual (English / Gujarati), defined in `$this->labels` at construction:
- Section headers: "EMI Comparison / EMI સરખામણી", "Charges Comparison / ચાર્જ સરખામણી", etc.
- Field labels: "Customer / ગ્રાહક", "Loan Amount / લોન રકમ", etc.
- Footer: "This is a system-generated proposal. / આ સિસ્ટમ દ્વારા જનરેટ કરેલ પ્રસ્તાવ છે."

### Gujarati Font Embedding

Gujarati text requires embedded fonts since Chrome headless may not have Gujarati font support:

- Font files: `public/fonts/NotoSansGujarati-Regular.ttf` and `NotoSansGujarati-Bold.ttf`
- Embedded as base64 `@font-face` declarations directly in the HTML `<style>` block
- Font family: `"NotoSansGujarati"` with normal and bold weights
- CSS font stack: `"NotoSansGujarati", "Noto Sans Gujarati", Arial, sans-serif`

### Logo Embedding

- Logo file: `public/images/logo3.png`
- Embedded as base64 `data:image/png;base64,...` in an `<img>` tag
- Fallback: text "SHF" + "SHREENATHJI HOME FINANCE" if logo file not found

### Currency Formatting

Uses `NumberToWordsService`:
- `formatCurrency()` — Indian comma format with rupee symbol (e.g., "₹ 12,50,000")
- `toBilingual()` — Amount in words in both English and Gujarati

## Page Layout

The PDF uses a fixed header/footer pattern via CSS `position: fixed` and a wrapper `<table>` with `<thead>/<tfoot>` for repeating header/footer across pages.

### Page Structure

1. **Fixed header** — company logo, PDF title ("LOAN PROPOSAL / લોન પ્રસ્તાવ"), date, contact info, accent bar
2. **Fixed footer** — "Our Services" text, system-generated disclaimer
3. **Content pages**:
   - **Page 1**: Customer details box (name, type, loan amount in words, prepared-by info) + Required Documents table
   - **Page 2+**: EMI comparison tables (2 tenure tables per page, each showing ROI, monthly EMI, total interest, total payment across all banks)
   - **Final page**: Charges comparison table (PF, admin, stamp/notary, registration, advocate, IOM, TC, extras, total) + Additional Notes (red callout box)

### Adaptive Column Widths

Column widths adjust based on bank count:
- 3 or fewer banks: description column 30%, bank columns share 70%
- 4+ banks: description column 25%, bank columns share 75%

### Brand Colors

| Variable | Value | Usage |
|----------|-------|-------|
| `$primaryDarkFill` | `#6b6868` | Table headers, footer background |
| `$accent` | `#f15a29` | EMI section headers, accent bars, logo fallback |
| `$accentTint` | `#fef0eb` | Customer box background, charges/docs header text |
| `$bg` | `#f8f8f8` | Alternating table rows |
| `$textColor` | `#1a1a1a` | Primary text |
| `$textMuted` | `#6b7280` | Labels, secondary text |
| `$borderColor` | `#bcbec0` | Table borders |

### Charge Rows

Charge rows are dynamically filtered — only rows where at least one bank has a non-zero value are shown. The 7 standard charge types are:

1. PF Charge
2. Admin Charges
3. Stamp Paper and Notary Charges
4. Registration Fee
5. Advocate Fees
6. IOM Stamp Paper Charges
7. TC Report Charges

Extra charges (`extra1`, `extra2`) are collected across all banks and displayed as additional rows.

## File Output

- **Directory**: `storage/app/pdfs/`
- **Filename pattern**: `Loan_Proposal_{SafeCustomerName}_{YYYY-MM-DD}_{HH_mm_ss}.pdf`
- **Temp HTML**: Written to `storage/app/tmp/pdf_{uniqid}.html`, deleted after Chrome conversion

## Error Handling

- Chrome failure: logs exit code + stderr output, falls back to microservice if configured
- Microservice failure: logs HTTP code + curl error
- Both fail: returns `['error' => '...']` array — caller returns 422 JSON response
- PDF generated but DB save fails: PDF is still returned to the user with a `warning` field

## Key Files

| File | Purpose |
|------|---------|
| `app/Services/PdfGenerationService.php` | HTML rendering + PDF conversion |
| `app/Services/NumberToWordsService.php` | Indian currency formatting + bilingual words |
| `public/fonts/NotoSansGujarati-Regular.ttf` | Gujarati regular font |
| `public/fonts/NotoSansGujarati-Bold.ttf` | Gujarati bold font |
| `public/images/logo3.png` | Company logo for PDF header |
