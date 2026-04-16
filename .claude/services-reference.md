# Services Reference

## QuotationService (`app/Services/QuotationService.php`)

### `generate(array $input, int $userId): array`
Main entry point for creating a quotation.

**Input validation**:
- customerName: required, non-empty
- customerType: required
- loanAmount: required, > 0, <= 1,000,000,000,000 (1 lakh crore)
- banks: required array, each must have bankName, roiMin (0-30), roiMax (>= roiMin)
- selectedTenures: validated against configured tenures
- documents: optional array

**Flow**:
1. Validates all inputs
2. Processes bank data (charges, EMI calculations)
3. Calls `PdfGenerationService::generate()` for PDF
4. Wraps DB save in transaction (quotation → banks → EMI entries → documents)
5. Updates `bank_charges` reference table
6. Returns `['success' => true, 'quotation' => Quotation]` or `['success' => false, 'error' => '...']`

---

## PdfGenerationService (`app/Services/PdfGenerationService.php`)

### `generate(array $data): array`
Generates PDF from quotation data.

**Strategy** (three-tier, OS-agnostic):
1. **`PDF_USE_MICROSERVICE=true`** → microservice only (escape hatch for containerized Chrome setups)
2. **Chrome available** → Chrome headless directly (fastest). If Chrome fails, falls back to microservice (if configured)
3. **Chrome not available** → microservice via cURL to `PDF_SERVICE_URL` with `PDF_SERVICE_KEY`

**Private helper methods**:
- `isChromeAvailable(): bool` — checks `exec()` not disabled + Chrome binary exists (uses `command -v` on Linux for bare names)
- `generateWithChrome(string $tmpHtml, string $filepath): ?array` — runs Chrome headless, uses `escapeshellarg()` on Linux
- `generateWithMicroservice(string $html, string $filepath): ?array` — cURL POST with `CURLOPT_CONNECTTIMEOUT=5`, `CURLOPT_TIMEOUT=60`

**Config**: `app.pdf_use_microservice` (bool, default `false`) — set `PDF_USE_MICROSERVICE=true` in `.env` to force microservice mode

**Output**: `['success' => true, 'filename' => '...', 'path' => '...']` or `['error' => '...']`

### `renderHtml(array $data): string`
Renders complete HTML document:
- Embeds Noto Sans Gujarati font as base64
- Embeds company logo as base64
- Multi-page layout with fixed header/footer
- EMI comparison tables (one per selected tenure)
- Bank charges comparison table
- Required documents checklist (bilingual)
- Additional notes section

---

## ConfigService (`app/Services/ConfigService.php`)

### Key methods:
- `load()`: Loads from `app_config` table or initializes from `config/app-defaults.php`
- `get(string $key, $default)`: Dot-notation access (e.g., `get('company.name')`)
- `updateSection(string $section, $value)`: Update one section
- `updateMany(array $updates)`: Update multiple sections
- `reset()`: Reset to defaults from `config/app-defaults.php`

### Config sections:
`company`, `banks`, `tenures`, `documents`, `iomCharges`, `gstPercent`, `ourServices`

---

## PermissionService (`app/Services/PermissionService.php`)

### Resolution order:
1. **Super Admin**: Always returns `true` (bypass)
2. **User Override**: Check `user_permissions` for explicit grant/deny
3. **Role Default**: Check `role_permissions` for role-level permission

### Caching:
- User overrides: cached 5 minutes, key `user_permissions_{userId}`
- Role permissions: cached 5 minutes, key `role_permissions_{role}`
- Clear: `clearUserCache($user)`, `clearRoleCache($role)`, `clearAllCaches()`

---

## NumberToWordsService (`app/Services/NumberToWordsService.php`)

Indian numbering system (Crore/Lakh/Thousand):
- `toEnglish(int $num)`: "One Crore Twenty Five Lakh"
- `toGujarati(int $num)`: Gujarati script equivalent
- `toBilingual(int $num)`: "English / Gujarati"
- `formatIndianNumber(int $num)`: "1,25,00,000"
- `formatCurrency(int $num)`: "₹ 1,25,00,000"
