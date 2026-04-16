# Offline & PWA

## Overview

Progressive Web App with IndexedDB caching, service worker, and offline quotation generation with auto-sync.

## Service Worker

Registered in `layouts/app.blade.php`. Located at `/sw.js`.

## IndexedDB: `offline-manager.js`

### Database
- Name: `shf_offline`
- Version: 2

### Object Stores
| Store | Key | Purpose |
|-------|-----|---------|
| cached_config | 'key' | Config snapshots |
| cached_charges | 'key' | Bank charges cache |
| cached_notes | 'key' | Form notes |
| pending_quotations | autoIncrement | Quotations awaiting sync |
| pending_config_changes | autoIncrement | Config edits awaiting sync |

### Core Methods

| Method | Purpose |
|--------|---------|
| `openDB()` | Promise-based IndexedDB initialization |
| `cacheConfig(obj)` | Save config snapshot |
| `getCachedConfig()` | Retrieve cached config |
| `cacheCharges(obj)` | Cache bank charges |
| `getCachedCharges()` | Retrieve charges |
| `cacheNotes(text)` | Cache form notes |
| `getCachedNotes()` | Retrieve notes |
| `queueQuotation(payload)` | Queue quotation for sync |
| `getPendingQuotations()` | Get all pending |
| `getPendingQuotationsWithKeys()` | Get pending with IndexedDB keys |
| `deleteQuotationByKey(key)` | Delete synced item |

### Network Status

`setupNetworkListeners()` wires online/offline events:
- Online → auto-sync pending items
- Offline → show offline banner
- Page load → check status

### Status UI

`updateStatusUI(status)`:
- `'offline'` — red banner "You are offline"
- `'syncing'` — orange banner "Syncing..."
- `'sync-error'` — orange banner with error
- `'online'` — green banner (auto-hides)

### Sync Process (`syncAll()`)

1. Fetch pending quotations with keys
2. POST to `/api/sync` with CSRF token and payload array
3. Match results to pending items
4. Delete successfully synced items by key
5. Dispatch custom events

### Error Handling
- 419 → CSRF expired
- 401/302 → Auth expired
- Server error → show error banner

### Custom Events
- `offlineSync` — `{ quotationsSynced, quotationsFailed, results }`
- `offlineSyncError` — `{ error, message, pending }`

## Client-Side PDF: `pdf-renderer.js`

### `PdfRenderer.renderHtml(data, logoBase64)`
Generates full HTML matching server PDF template for offline use.

### `PdfRenderer.generateOfflinePdf(payload, config, logoBase64)`
1. Build template data from offline cache
2. Render HTML
3. Open in new tab → trigger print dialog
4. iOS fallback: download HTML file
5. Popup blocker fallback: download HTML

## Config Fallback Chain

1. Server API (`/api/config/public`)
2. IndexedDB cached config
3. Hardcoded defaults (`config-defaults.js`)

## PWA Install Banner

- Listens for `beforeinstallprompt` event
- Shows install banner at page bottom
- 24-hour cooldown after dismiss (localStorage)
- Auto-hides after install

## Manifest

`/manifest.json` — standard PWA manifest with app name, icons, theme colors.
