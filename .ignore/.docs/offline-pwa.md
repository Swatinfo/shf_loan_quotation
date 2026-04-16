# Offline & PWA System

## Overview

The application functions as a Progressive Web App (PWA) with full offline quotation creation capability. When offline, users can create quotations using cached configuration and client-side PDF rendering. When connectivity returns, pending quotations are synced to the server.

## Architecture

```
sw.js (Service Worker)           → Caches static assets for offline access
manifest.json                    → PWA install manifest
public/js/offline-manager.js     → IndexedDB management + sync queue
public/js/config-loader.js       → Config fetching + caching
public/js/pdf-renderer.js        → Client-side PDF generation
public/js/config-defaults.js     → Hardcoded fallback config
public/js/config-translations.js → Bilingual labels for offline PDFs
```

## Service Worker (`sw.js`)

**Location**: Project root (`/sw.js`)

**Registration**: In `layouts/app.blade.php`:
```javascript
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js');
}
```

**Caching Strategy**:
- Caches static assets (CSS, JS, fonts, images) on install
- Serves cached assets when offline
- Network-first for API calls, cache-first for static assets

## PWA Manifest (`manifest.json`)

**Location**: Project root

Defines the app for "Add to Home Screen":
- App name, short name, description
- Icons (various sizes)
- Display mode: standalone
- Theme color: #3a3536
- Background color: #f8f8f8

## IndexedDB Storage

### offline-manager.js

**Database Name**: Configured in the OfflineManager class

**Object Stores** (5 total):

| Store | Purpose | Key |
|-------|---------|-----|
| `cached_config` | Cached API config response | config key |
| `cached_charges` | Cached bank charges | bank name |
| `pending_quotations` | Quotations created offline, awaiting sync | auto-increment |
| `pending_config_changes` | Config changes made offline | auto-increment |
| `cached_notes` | Cached additional notes | notes key |

### Offline Quotation Flow

1. User creates quotation while offline
2. Quotation data saved to `pending_quotations` IndexedDB store
3. Client-side PDF generated via `pdf-renderer.js` (window.print or HTML download on iOS)
4. Offline banner shows count of pending quotations
5. When `navigator.onLine` becomes `true`:
   - OfflineManager triggers sync
   - Sends `POST /api/sync` with all pending quotations
   - Server processes each independently via `QuotationService::generate()`
   - Successfully synced items removed from IndexedDB
   - Failed items remain for retry

### Config Loading Flow

```
config-loader.js:
  1. Try: fetch GET /api/config/public
     → Success: save to IndexedDB, use response
  2. Catch (offline): read from IndexedDB cached_config
     → Found: use cached data
  3. Fallback: use config-defaults.js hardcoded values

All paths: merge with defaults, sort tenures
```

## Sync Mechanism

### Network Detection

```javascript
window.addEventListener('online', () => offlineManager.sync());
window.addEventListener('offline', () => offlineManager.showOfflineBanner());
```

### Sync Request

**Endpoint**: `POST /api/sync`

**Headers**:
- `Content-Type: application/json`
- `X-CSRF-TOKEN: {token from meta tag}`
- `Accept: application/json`

**Body**:
```json
{
    "quotations": [
        { /* quotation 1 data */ },
        { /* quotation 2 data */ }
    ]
}
```

### Error Handling

| HTTP Status | Action |
|-------------|--------|
| 200 | Parse results, remove successful items from IndexedDB |
| 419 | CSRF token expired — attempt to refresh token |
| 401, 302 | Session expired — redirect to login page |
| Network error | Keep items in IndexedDB for next retry |

**Selective Deletion**: Only successfully synced items are removed from IndexedDB. Failed items remain for the next sync attempt. This prevents data loss on partial failures.

## Offline UI Elements

### Offline Banner

Fixed-position banner at bottom of page:

| State | Color | Message |
|-------|-------|---------|
| Offline | Red/Orange | "You are offline. X quotations pending sync." |
| Syncing | Orange | "Syncing X quotations..." |
| Online (after sync) | Green | "All quotations synced!" (auto-dismiss) |

### PWA Install Banner

- Shown when `beforeinstallprompt` event fires
- 24-hour cooldown stored in `localStorage`
- Dismissable with "Not now" button
- "Install" button triggers native install prompt

## Client-Side PDF Rendering

**File**: `public/js/pdf-renderer.js`

Mirrors the server-side `PdfGenerationService::renderHtml()`:

**Sections Generated**:
1. Header with logo and title (bilingual)
2. Customer details (name, type, amount in words EN/GU)
3. Company contact info
4. Required documents list (bilingual)
5. EMI comparison tables by tenure
6. Charges comparison table
7. Additional notes
8. Prepared by info

**Output Methods**:
- **Desktop/Android**: `Blob URL + window.print()` → browser's "Save as PDF"
- **iOS**: Downloads `.html` file (iOS doesn't support `window.print()` from blob URLs)

**Font Handling**: Gujarati font (NotoSansGujarati) embedded as base64 in the HTML template

**Number Formatting**: Uses functions from `config-translations.js`:
- `numberToEnglishWords()` — Indian numbering (Crore/Lakh)
- `numberToGujaratiWords()` — Gujarati script
- Indian comma formatting (X,XX,XXX)

## Important Notes

1. **No auto-download on sync**: PDFs are NOT automatically downloaded when offline quotations are synced. The user must manually download from the quotation detail page. (This was a deliberate design decision — see `tasks/lessons.md`.)

2. **DataTables offline**: When offline, the dashboard shows cached data instead of displaying a blocking "offline" screen. (Fixed in previous task.)

3. **All vendor files are local**: Bootstrap, jQuery, DataTables, fonts — nothing depends on external CDNs, enabling true offline functionality.

4. **CSRF token management**: The offline manager handles CSRF token refresh for session-authenticated API calls. Expired tokens (419 response) trigger a token refresh attempt before retrying.
