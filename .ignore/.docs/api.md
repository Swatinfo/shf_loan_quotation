# API Endpoints

## Overview

The app exposes a mix of public API routes (no auth) and session-authenticated API routes. These support the PWA offline mode, configuration loading, and additional notes management.

## Public API Routes (`routes/api.php`)

No authentication required.

### GET /api/config/public

**Controller**: `Api\ConfigApiController@public`

**Purpose**: Returns full application config for the frontend (offline caching, quotation creation form).

**Response**:
```json
{
    "company": {
        "name": "Shreenathji Home Finance",
        "address": "...",
        "phone": "...",
        "email": "..."
    },
    "banks": ["HDFC", "ICICI", "Axis Bank", "Kotak Mahindra"],
    "tenures": [5, 10, 15, 20],
    "documents": {
        "en": {
            "proprietor": ["Doc 1", "Doc 2", ...],
            "partnership_llp": [...],
            "pvt_ltd": [...],
            "salaried": [...]
        },
        "gu": {
            "proprietor": ["Doc 1 GU", ...],
            ...
        }
    },
    "iomCharges": {
        "thresholdAmount": 10000000,
        "fixedCharge": 5500,
        "percentageAbove": 0.35
    },
    "gstPercent": 18,
    "ourServices": "Home Loan, LAP, ...",
    "bankCharges": [
        {
            "bank_name": "HDFC",
            "pf": "1.50",
            "admin": "5000",
            "stamp_notary": "3000",
            "registration_fee": "0",
            "advocate": "5000",
            "tc": "1500",
            "extra1_name": null,
            "extra1_amt": null,
            "extra2_name": null,
            "extra2_amt": null
        }
    ]
}
```

**Used By**: `public/js/config-loader.js` — fetches on page load, caches in IndexedDB for offline use.

### GET /api/notes

**Controller**: `Api\NotesApiController@get`

**Purpose**: Retrieve saved additional notes text.

**Response**:
```json
{
    "success": true,
    "notes": "Some additional notes text..."
}
```

**Database**: Reads from `app_settings` table where `setting_key = 'additional_notes'`.

**Error Handling**: Catches all exceptions silently, returns empty string on failure. This is intentional — the notes feature is non-critical and should not block the UI.

## Session-Auth API Routes (`routes/web.php`)

These require an active session (cookies). Used by the frontend for authenticated operations.

### POST /api/sync

**Controller**: `Api\SyncApiController@sync`

**Purpose**: Batch sync offline-created quotations when the device comes back online.

**Request**:
```json
{
    "quotations": [
        {
            "customerName": "John Doe",
            "customerType": "proprietor",
            "loanAmount": 5000000,
            "banks": [...],
            "documents": [...],
            "selectedTenures": [5, 10, 15, 20],
            "additionalNotes": "...",
            "preparedByName": "...",
            "preparedByMobile": "..."
        },
        ...
    ]
}
```

**Process** (per quotation):
1. Auto-fills `preparedByName` from `auth()->user()->name` if not provided
2. Auto-fills `preparedByMobile` from `auth()->user()->phone` if not provided
3. Calls `QuotationService::generate()` (same as online creation)
4. Logs activity: `create_quotation` with `source: 'offline_sync'`

**Response**:
```json
{
    "success": true,
    "results": [
        {
            "index": 0,
            "success": true,
            "filename": "Loan_Proposal_John_Doe_06042026_120000.pdf"
        },
        {
            "index": 1,
            "success": false,
            "error": "Customer name is required"
        }
    ]
}
```

**Error Handling**:
- Returns 400 if no quotations array provided
- Individual quotation failures don't block others — each is processed independently
- Results array maps to input array by index

### POST /api/notes

**Controller**: `Api\NotesApiController@save`

**Purpose**: Save additional notes text.

**Request**: `{ "notes": "Updated notes text..." }`

**Action**: Uses `DB::table('app_settings')->updateOrInsert(...)` with key `'additional_notes'`

**Response**: `{ "success": true }` or `{ "success": false, "error": "..." }`

## Frontend API Consumers

### config-loader.js
- Fetches `GET /api/config/public` on page load
- Caches response in IndexedDB `cached_config` store
- Falls back to IndexedDB cache → hardcoded defaults if offline

### offline-manager.js
- Monitors `navigator.onLine` events
- When online: syncs pending quotations via `POST /api/sync`
- Handles CSRF tokens (sends `X-CSRF-TOKEN` header)
- Handles auth expiry: 419 (CSRF mismatch) → refreshes token; 401/302 → redirects to login
- Selectively removes only successfully synced items from IndexedDB

## CSRF Protection

Session-auth API routes (`/api/sync`, `POST /api/notes`) require CSRF tokens:
- Token sourced from `<meta name="csrf-token">` in layout
- Sent as `X-CSRF-TOKEN` header in AJAX requests
- 419 response triggers token refresh attempt

## Rate Limiting

No explicit rate limiting is configured on API routes. The public endpoints (`/api/config/public`, `GET /api/notes`) are read-only and lightweight.
