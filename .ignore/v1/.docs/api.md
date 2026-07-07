# API Endpoints

The application exposes a small set of API endpoints used primarily by the PWA/offline mode. Endpoints are split between true API routes (no auth) and session-authenticated web routes prefixed with `/api`.

## Public Endpoints (No Authentication)

Defined in `routes/api.php`. No middleware — accessible without login. Used by the service worker and `ConfigLoader` for offline caching.

### GET `/api/config/public`

Returns the full application config JSON merged with all bank charges. Used by the PWA to cache config in IndexedDB for offline quotation generation.

**Controller:** `ConfigApiController@public`

**Request:** No parameters.

**Response (200):**
```json
{
  "companyName": "...",
  "companyAddress": "...",
  "companyPhone": "...",
  "companyEmail": "...",
  "banks": ["Bank A", "Bank B"],
  "tenures": [5, 10, 15, 20, 25, 30],
  "iomCharges": "...",
  "gstPercent": 18,
  "ourServices": "...",
  "documents_en": { ... },
  "documents_gu": { ... },
  "bankCharges": [
    {
      "bank_name": "Bank A",
      "pf": 0.5,
      "admin": 0,
      "stamp_notary": 500,
      "registration_fee": 0,
      "advocate": 3000,
      "tc": 0,
      "extra1_name": "",
      "extra1_amt": 0,
      "extra2_name": "",
      "extra2_amt": 0
    }
  ]
}
```

**Errors:** Returns whatever `ConfigService::load()` returns. No explicit error handling — failures will produce a 500 with Laravel's default error response.

---

### GET `/api/notes`

Returns the saved "additional notes" text from the `app_settings` table.

**Controller:** `NotesApiController@get`

**Request:** No parameters.

**Response (200):**
```json
{
  "success": true,
  "notes": "Some additional notes text..."
}
```

If the database is unavailable, returns `{"success": true, "notes": ""}` (graceful degradation for offline).

---

## Authenticated Endpoints (Session Auth)

Defined in `routes/web.php` inside the `auth` + `active` middleware group. Require a valid Laravel session (CSRF token + login). Used by the PWA sync system when the device comes back online.

### POST `/api/sync`

Batch-syncs offline-created quotations to the server. Each quotation is processed through `QuotationService::generate()` and logged via `ActivityLog` with `source: offline_sync`.

**Controller:** `SyncApiController@sync`

**Route name:** `api.sync`

**Request:**
```json
{
  "quotations": [
    {
      "customerName": "John Doe",
      "loanAmount": 1000000,
      "preparedByName": "Agent Name",
      "preparedByMobile": "9876543210",
      "...other quotation fields..."
    }
  ]
}
```

- `preparedByName` / `preparedByMobile`: Auto-filled from the authenticated user if not provided.
- The payload format matches what `QuotationService::generate()` expects (same as the web quotation form).

**Response (200):**
```json
{
  "success": true,
  "results": [
    {
      "index": 0,
      "success": true,
      "filename": "quotation_20260413_123456.pdf",
      "error": null
    },
    {
      "index": 1,
      "success": false,
      "filename": null,
      "error": "Validation failed: loan amount required"
    }
  ]
}
```

**Error responses:**
- `400` — Empty quotations array: `{"error": "No quotations to sync"}`
- `419` — CSRF token expired (client should reload page for a fresh token)
- `401` / `302` — Session expired (user must re-authenticate)

---

### POST `/api/notes`

Saves additional notes text to the `app_settings` table.

**Controller:** `NotesApiController@save`

**Route name:** `api.notes.save`

**Request:**
```json
{
  "notes": "Updated additional notes text"
}
```

**Response (200):**
```json
{
  "success": true
}
```

**Error response (500):**
```json
{
  "error": "Failed to save notes: <exception message>"
}
```

---

## Authentication Summary

| Endpoint | Auth | CSRF | Used By |
|----------|------|------|---------|
| `GET /api/config/public` | None | No | Service worker, ConfigLoader |
| `GET /api/notes` | None | No | ConfigLoader / offline cache |
| `POST /api/sync` | Session (login required) | Yes | OfflineManager.syncAll() |
| `POST /api/notes` | Session (login required) | Yes | Notes save form |

## Error Handling Patterns

- **Public endpoints** silently degrade: catch exceptions and return empty/default data so the PWA can function offline.
- **Authenticated endpoints** return structured JSON errors with appropriate HTTP status codes.
- The client-side `OfflineManager.syncAll()` handles `419` (CSRF expired), `401`/`302` (auth expired), and generic server errors with bilingual (English/Gujarati) user messages and custom events (`offlineSync`, `offlineSyncError`).
