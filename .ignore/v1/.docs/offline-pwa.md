# Offline / PWA

The application is a Progressive Web App that supports offline quotation creation and automatic sync when connectivity is restored.

## Key Files

| File | Purpose |
|------|---------|
| `public/sw.js` | Service worker (also duplicated at project root `sw.js`) |
| `public/manifest.json` | PWA manifest |
| `public/js/offline-manager.js` | IndexedDB storage + sync queue + network status UI |
| `public/js/config-loader.js` | Config fetcher with IndexedDB fallback |
| `public/js/pdf-renderer.js` | Client-side PDF generation for offline use |
| `resources/views/layouts/app.blade.php` | Registers the service worker |

## PWA Manifest

File: `public/manifest.json`

| Property | Value |
|----------|-------|
| `name` | SHF World |
| `short_name` | SHF World |
| `display` | standalone |
| `start_url` | /dashboard |
| `theme_color` | #3a3536 (brand dark gray) |
| `background_color` | #f8f8f8 (brand light gray) |
| Icons | 192x192 + 512x512, both `any` and `maskable` purpose |

## Service Worker Registration

Registered in `resources/views/layouts/app.blade.php`:

```js
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js');
}
```

The SW is served from the web root (`public/sw.js`) to ensure its scope covers the entire application.

## Cache Versioning

- **Version variable:** `SHF_SW_VERSION` (timestamp format, e.g. `'20260413100342'`)
- **Two caches:**
  - `shf-static-{version}` — pre-cached static assets
  - `shf-dynamic-{version}` — runtime-cached pages, API responses, PDFs
- **Cache cleanup:** On activate, all caches not matching the current version are deleted.
- **Update:** Changing `SHF_SW_VERSION` triggers a new install, which replaces old caches. Uses `skipWaiting()` + `clients.claim()` for immediate activation.

## Pre-Cached Static Assets

Cached during the `install` event (Cache-First thereafter):

- `/css/shf.css`
- `/images/logo3.png`, `/images/background.png`, `/images/Shree-4.png`
- `/images/icon-192x192.png`, `/images/icon-512x512.png`
- `/fonts/NotoSansGujarati-Regular.ttf`, `/fonts/NotoSansGujarati-Bold.ttf`
- `/manifest.json`

Individual asset failures during install are caught and logged (non-blocking).

## Caching Strategies

| Content Type | Strategy | Details |
|--------------|----------|---------|
| Static assets (CSS, JS, fonts, images) | **Cache-First** | Serve from cache; fetch and cache on miss |
| HTML pages (Laravel routes) | **Network-First** | Fetch from network, cache 200 responses; serve cache on network failure |
| `/api/config/public` | **Network-First** | Cache successful responses; return `{}` if both fail |
| PDF downloads (`/quotations/download-file`, `/quotations/{id}/download`) | **Network-First** | Cache on success; return 503 plain text if unavailable offline |
| `/dashboard/quotation-data` | **Pass-through** | Skipped by SW entirely; client-side handles offline |
| Non-GET requests | **Pass-through** | All POST/PUT/DELETE bypass the service worker |
| Cross-origin requests | **Pass-through** | Only same-origin requests are intercepted |

### Offline Fallback Chain (HTML pages)

1. Try network
2. Try cache match for the exact URL
3. Try cached `/dashboard` page
4. Return a minimal "You are offline" HTML page (inline, branded)

## IndexedDB Storage

Managed by `OfflineManager` in `public/js/offline-manager.js`.

**Database:** `shf_offline` (version 2)

### Object Stores

| Store | Key | Purpose |
|-------|-----|---------|
| `cached_config` | `keyPath: 'key'` (always `'main'`) | Cached app config from `/api/config/public` |
| `cached_charges` | `keyPath: 'key'` (always `'main'`) | Cached bank charges |
| `pending_quotations` | `autoIncrement` | Offline-created quotations awaiting sync |
| `pending_config_changes` | `autoIncrement` | Offline config edits awaiting sync |
| `cached_notes` | `keyPath: 'key'` (always `'additional_notes'`) | Cached additional notes text |

Each cached item includes a `timestamp` field for freshness tracking.

### OfflineManager API

**Config cache:**
- `cacheConfig(configObj)` / `getCachedConfig()` — store/retrieve full config
- `cacheCharges(chargesObj)` / `getCachedCharges()` — store/retrieve bank charges
- `cacheNotes(notes)` / `getCachedNotes()` — store/retrieve additional notes

**Quotation queue:**
- `queueQuotation(payload)` — add a quotation to the offline queue
- `getPendingQuotations()` — get all pending quotations
- `getPendingQuotationsWithKeys()` — get all with IndexedDB keys (for selective deletion)
- `deleteQuotationByKey(key)` — remove a single synced quotation
- `clearPendingQuotations()` — clear the entire queue

**Config change queue:**
- `queueConfigChange(payload)` / `getPendingConfigChanges()` / `clearPendingConfigChanges()`

## Offline Sync Queue

### Automatic Sync Trigger

`OfflineManager.setupNetworkListeners()` listens for the browser `online` event. When connectivity returns:

1. UI shows "Syncing data..." banner (bilingual)
2. `syncAll()` runs:
   - Reads all pending quotations from IndexedDB
   - POSTs them as a batch to `POST /api/sync` with CSRF token
   - On success: deletes only successfully synced items from IndexedDB (partial success supported)
   - Clears any pending config changes
3. UI updates to "Back online" (auto-hides after 3s) or "Sync failed" (auto-hides after 8s)

### Sync Error Handling

| HTTP Status | Meaning | Client Behavior |
|-------------|---------|-----------------|
| 200 | Success | Delete synced items, dispatch `offlineSync` event |
| 419 | CSRF expired | Show "Session expired -- reload page" message |
| 401 / 302 | Auth expired | Show "Please log in again to sync" message |
| Other | Server error | Show "Sync failed -- data is safe, will retry" message |
| Network error | No connectivity | Show "Check your connection" message |

All error messages are bilingual (English / Gujarati). Events dispatched: `offlineSync` (success) and `offlineSyncError` (failure) on `window`, with detail payloads for UI consumption.

### Data Safety

- Pending quotations are only deleted from IndexedDB after confirmed server-side success (per-item granularity).
- On partial batch failure, only successful items are removed; failed items remain queued for retry.
- Network errors leave all data intact in IndexedDB.

## Config Loader

`ConfigLoader` in `public/js/config-loader.js` handles config fetching with offline fallback:

1. Fetch `/api/config/public` (cache-busted with `?t=timestamp`)
2. Merge with `CONFIG_DEFAULTS` (client-side defaults)
3. Cache merged result to IndexedDB via `OfflineManager.cacheConfig()`
4. On fetch failure: try IndexedDB cached config, then fall back to `CONFIG_DEFAULTS`

## Offline PDF Generation

`pdf-renderer.js` provides client-side PDF generation when the server is unreachable:
- Builds the quotation HTML template in-browser (mirrors the server-side PHP template)
- Desktop/Android: opens a new tab and triggers the browser print dialog (Save as PDF)
- iOS/PWA: downloads as an HTML file (iOS does not support `window.print()` in standalone mode)

## Network Status UI

The `offlineBanner` element (in the layout) shows bilingual status messages:

| State | Banner Style | Message |
|-------|-------------|---------|
| Offline | Orange/warning | "You are offline / tamE Offline chho" |
| Syncing | Blue/info | "Syncing data... / DeTa sync thI rahyo chhe..." |
| Back online | Green/success | "Back online / pachha Online" (auto-hide 3s) |
| Sync error | Orange/warning | Error-specific message (auto-hide 8s) |
