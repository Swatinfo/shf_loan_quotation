# Offline / PWA

The app is a Progressive Web App: installable, works offline for specific read-only + queued-write flows.

## Capabilities

- **Installable** — `manifest.json` exposes icons + start URL; browser offers install prompt on return visits.
- **Offline read** — static assets, cached PDFs, cached app config served from service worker cache.
- **Offline write (queued)** — quotation form submissions persist to IndexedDB, sync on reconnect.
- **Client-side PDF** — `pdf-renderer.js` can render a quotation PDF via `window.print()` when offline.

## Manifest — `public/manifest.json`

```json
{
  "name": "SHF World",
  "short_name": "SHF World",
  "description": "Shreenathji Home Finance — Loan Management",
  "start_url": "/dashboard",
  "display": "standalone",
  "background_color": "#f8f8f8",
  "theme_color": "#3a3536",
  "icons": [
    { "src": "/images/icon-192x192.png", "sizes": "192x192", "type": "image/png", "purpose": "any" },
    { "src": "/images/icon-512x512.png", "sizes": "512x512", "type": "image/png", "purpose": "any" },
    { "src": "/images/icon-192x192.png", "sizes": "192x192", "type": "image/png", "purpose": "maskable" },
    { "src": "/images/icon-512x512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable" }
  ]
}
```

Referenced from `layouts/app.blade.php` via `<link rel="manifest" href="/manifest.json">`. Apple-specific meta tags mirror the theme (status bar, app title).

## Service Worker — `public/sw.js`

Version string is a timestamp (e.g., `20260415220839`). On each deploy, bump to invalidate caches.

### Caches

- `shf-static-{VERSION}` — app shell: CSS, images, fonts, manifest
- `shf-dynamic-{VERSION}` — config responses and downloaded PDFs

### Strategies

1. **PDF downloads** (`/quotations/download-file`, `/quotations/{id}/download`) → **network-first**; cache on success. Fallback to cached PDF (offline). Allows access to previously downloaded files without connection.
2. **Public config** (`GET /api/config/public`) → network-first; cache on success. Offline clients read the last-known config.
3. **Dashboard AJAX** (`/dashboard/quotation-data`) → SW skips; client handles offline state.
4. **Static assets** — cache-first from the static cache.
5. **Non-GET requests** — SW does not intercept; browser handles.

### Lifecycle

- **install**: pre-cache static asset list, `self.skipWaiting()` to activate immediately
- **activate**: delete old versioned caches, `clients.claim()` so the new SW controls open pages
- **fetch**: route per strategy

Registration is inline in `layouts/app.blade.php`:

```js
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(err => console.warn(err));
}
```

## IndexedDB — `public/js/offline-manager.js`

Database: `shf_offline` version 2. Object stores:

| Store | Purpose |
|---|---|
| `cached_config` | Last fetched `/api/config/public` payload |
| `cached_charges` | Bank charges snapshot |
| `pending_quotations` | Quotations submitted while offline |
| `pending_config_changes` | (If any) — reserved |
| `cached_notes` | Additional notes |

### Public helpers

- `OfflineManager.openDB()` → Promise<IDBDatabase>
- `OfflineManager.getCsrfToken()` — reads `<meta name="csrf-token">`
- `OfflineManager.cacheConfig(obj)` / `getCachedConfig()`
- `OfflineManager.cacheCharges(obj)` / `getCachedCharges()`
- `OfflineManager.getPendingQuotations()` — list queued quotations
- `OfflineManager.setupNetworkListeners()` — binds `online`/`offline` events, toggles banner
- `OfflineManager.syncAll()` — POSTs all pending quotations to `/api/sync`, clears queue on success

### Network banner

`#offlineBanner` in the layout, shown only when relevant:

- `.offline` — red: "You are offline"
- `.syncing` — orange: "Syncing…"
- `.online` — green: "Back online, synced" (auto-hides after short delay)

### Auto-sync

Inline script in `layouts/app.blade.php`, on DOMContentLoaded:

```js
if (typeof OfflineManager !== 'undefined') {
  OfflineManager.setupNetworkListeners();
  if (navigator.onLine) {
    OfflineManager.getPendingQuotations().then(items => {
      if (items.length > 0) OfflineManager.syncAll();
    });
  }
}
```

## PDF renderer — `public/js/pdf-renderer.js`

Mirrors the server-side quotation template for offline generation:

- Bilingual layout with `@font-face` NotoSansGujarati fonts (TTF blob URLs created from base64)
- Uses CSS paged media for A4 layout
- Renders: customer info, per-bank sections, EMI tables, documents, charges
- Calls `window.print()` — user selects "Save as PDF" in the print dialog

Works fully offline. Users get a usable PDF even without any server round-trip.

## PWA install prompt

Inline handler in `layouts/app.blade.php`:

```js
(function() {
  let deferredPrompt = null;
  const banner = document.getElementById('installBanner');
  const installBtn = document.getElementById('installBtn');
  const dismissBtn = document.getElementById('installDismiss');

  // Skip if already installed (standalone mode) or in iOS standalone
  if (matchMedia('(display-mode: standalone)').matches || navigator.standalone) return;

  // 24-hour dismiss cooldown via localStorage
  const dismissed = localStorage.getItem('pwa-install-dismissed');
  if (dismissed && Date.now() - parseInt(dismissed) < 86400000) return;

  window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    deferredPrompt = e;
    banner.style.display = '';
  });

  installBtn.onclick = () => {
    deferredPrompt?.prompt();
    deferredPrompt?.userChoice.then(({outcome}) => {
      if (outcome === 'accepted') banner.style.display = 'none';
      deferredPrompt = null;
    });
  };

  dismissBtn.onclick = () => {
    banner.style.display = 'none';
    localStorage.setItem('pwa-install-dismissed', Date.now().toString());
  };

  window.addEventListener('appinstalled', () => {
    banner.style.display = 'none';
    deferredPrompt = null;
  });
})();
```

## Sync endpoint

- `POST /api/sync` — session-auth (CSRF required via meta token)
- Body: `{ quotations: [ {customerName, customerType, loanAmount, banks, ...}, ... ] }`
- Server: `Api\SyncApiController@sync` — loops, calls `QuotationService::generate()` per item
- Response: `{ success, results: [{index, success, filename, error}, ...] }`
- Failed items stay queued; successful items are removed by the client

## Public read endpoints

Two endpoints in `routes/api.php` are unauthenticated and cached by the SW:

- `GET /api/config/public` — config + bank charges (used for offline quotation builder)
- `GET /api/notes` — free-form app notes (offline draft pad)

These are safe to cache; they contain no user-specific data.

## Testing offline

1. Install the PWA from desktop Chrome (install icon in address bar)
2. DevTools → Application → Service Workers → check "Offline"
3. Try: open dashboard (works from cache), create quotation (queued, banner shows "offline")
4. Toggle back online → banner → "syncing" → "online"; queue should clear

## Troubleshooting

- **SW won't activate** — bump `VERSION` timestamp in `sw.js`, hard-reload. Check DevTools → Application → Service Workers for errors.
- **Cached old asset** — bump app version via `config('app.shf_version')` used in asset cache-busting `?v=...`
- **IndexedDB migration issues** — version bumps require an `onupgradeneeded` handler; current version is 2
- **iOS PWA quirks** — `beforeinstallprompt` doesn't fire on Safari. Users must "Add to Home Screen" manually.

## See also

- `api.md` — public API endpoints that power offline
- `pdf-generation.md` — server-side PDF counterpart
- `frontend.md` — layout inline scripts
