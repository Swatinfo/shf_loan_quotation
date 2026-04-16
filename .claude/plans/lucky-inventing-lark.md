# PDF Branding Permission — With/Without SHF Branding + HTML Preview

## Context

Currently all PDF downloads include SHF branding (logo, company name, tagline in header). The user wants:
1. **Two download options** controlled by permissions — branded vs plain (no logo/company name)
2. **HTML preview** for super_admin — view the raw HTML that gets sent to Chrome, for both branded and unbranded variants

---

## Step 1: Add 2 new permissions + migration

**File**: `config/permissions.php` — add to Quotations group:
- `download_pdf_branded` — Download PDF with SHF branding
- `download_pdf_plain` — Download PDF without SHF branding

**File**: `database/migrations/XXXX_add_branding_pdf_permissions.php`
- Seed 2 permissions into `permissions` table
- Grant both to roles that currently have `download_pdf` (via `role_permission` pivot)

## Step 2: Modify PdfGenerationService — branding toggle

**File**: `app/Services/PdfGenerationService.php`

- `generate(array $data)`: read `$data['branded']` (default `true`)
- `renderHtml(array $data)`: when `branded = false`:
  - Skip logo `<img>` and fallback SHF text (~line 478-483)
  - Skip "Our Services" in footer
  - Keep PDF title, date, contact info, and all content pages
- New public method: `renderHtmlPreview(array $data): string` — returns the rendered HTML string without converting to PDF (for super_admin preview). Just calls `renderHtml()` and returns the result.

## Step 3: Modify QuotationController — branded param + HTML preview

**File**: `app/Http/Controllers/QuotationController.php`

### Download method changes:
- Read `$request->query('branded', '1')` — `1` = branded, `0` = plain
- Permission check: `branded=1` requires `download_pdf_branded`, `branded=0` requires `download_pdf_plain`
- For branded: use existing cached PDF (current behavior)
- For plain: always regenerate without branding (plain downloads are less frequent)
- Pass `branded` flag through to `regeneratePdf()` and `PdfGenerationService::generate()`

### New HTML preview endpoint (super_admin only):
```php
public function previewHtml(Quotation $quotation, Request $request)
```
- Only accessible by super_admin (abort 403 otherwise)
- Read `?branded=1|0` query param
- Build template data (same as `regeneratePdf()`)
- Call `PdfGenerationService::renderHtml()` with branded flag
- Return HTML response directly (browser renders it)

## Step 4: Add route for HTML preview

**File**: `routes/web.php`

```php
Route::get('/quotations/{quotation}/preview-html', [QuotationController::class, 'previewHtml'])
    ->name('quotations.preview-html');
```

Inside the existing auth middleware group. No permission middleware — controller checks `isSuperAdmin()`.

## Step 5: Update quotation show page — 2 download buttons + HTML preview

**File**: `resources/views/quotations/show.blade.php`

Replace single "Download PDF" button with:

```blade
@if(auth()->user()->hasPermission('download_pdf_branded'))
    <a href="{{ route('quotations.download', [$quotation, 'branded' => 1]) }}" class="btn-accent btn-accent-sm">
        Download Branded PDF
    </a>
@endif
@if(auth()->user()->hasPermission('download_pdf_plain'))
    <a href="{{ route('quotations.download', [$quotation, 'branded' => 0]) }}" class="btn-accent btn-accent-sm" style="background:...">
        Download Plain PDF
    </a>
@endif
@if(auth()->user()->isSuperAdmin())
    <div class="dropdown d-inline-block">
        <button class="btn-accent btn-accent-sm dropdown-toggle" data-bs-toggle="dropdown">Preview HTML</button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="{{ route('quotations.preview-html', [$quotation, 'branded' => 1]) }}" target="_blank">Branded HTML</a></li>
            <li><a class="dropdown-item" href="{{ route('quotations.preview-html', [$quotation, 'branded' => 0]) }}" target="_blank">Plain HTML</a></li>
        </ul>
    </div>
@endif
```

## Step 6: Update dashboard listing — 2 download icons

**File**: `app/Http/Controllers/DashboardController.php`

In quotation AJAX response, replace single `download_url` with:
```php
'download_branded_url' => $canDownloadBranded ? route('quotations.download', [$q->id, 'branded' => 1]) : null,
'download_plain_url'   => $canDownloadPlain ? route('quotations.download', [$q->id, 'branded' => 0]) : null,
```

**File**: `resources/views/dashboard.blade.php`

Update actions render (~line 1154 desktop, ~line 1397 mobile):
- Branded download: green icon (current styling)
- Plain download: secondary/gray icon, different tooltip

Pass permissions to JS:
```php
'download_pdf_branded' => $user->hasPermission('download_pdf_branded'),
'download_pdf_plain'   => $user->hasPermission('download_pdf_plain'),
```

## Step 7: Update route middleware

**File**: `routes/web.php`

Keep existing `permission:download_pdf` on the download route group. In controller, additionally check the specific branding permission. This way existing users with `download_pdf` can still reach the route, but only see buttons for permissions they have.

---

## Files Modified

| File | Change |
|------|--------|
| `config/permissions.php` | +2 permissions in Quotations group |
| `database/migrations/XXXX_add_branding_pdf_permissions.php` | Seed + role grants |
| `app/Services/PdfGenerationService.php` | Branding toggle in `renderHtml()`, expose HTML preview |
| `app/Http/Controllers/QuotationController.php` | `branded` param in download, new `previewHtml()` method |
| `app/Http/Controllers/DashboardController.php` | Two download URLs in AJAX |
| `resources/views/quotations/show.blade.php` | Two download buttons + HTML preview dropdown |
| `resources/views/dashboard.blade.php` | Two download icons in listing |
| `routes/web.php` | Add preview-html route |

## Verification

1. User with `download_pdf_branded` only → sees branded button only, PDF has logo
2. User with `download_pdf_plain` only → sees plain button only, PDF has no logo/company
3. User with both → sees both buttons
4. User with neither → sees no download buttons
5. Super admin → sees both buttons + "Preview HTML" dropdown with 2 options
6. Preview HTML opens in new tab, shows raw HTML that Chrome would render
7. Non-super-admin hitting preview-html route → 403
8. Dashboard listing shows correct icons per user permissions
9. Plain PDF: no logo, no company name, no "Our Services" footer — all other content intact
