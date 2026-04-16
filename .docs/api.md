# API Endpoints

## Public API (no auth required)

### GET /api/config/public
**Controller:** ConfigApiController@public

Returns public configuration for PWA offline mode:
- Company info (name, address, phone, email)
- Banks list
- Tenures
- Documents (EN/GU by customer type)
- IOM charges
- GST percentage
- Services description
- Bank charges

### GET /api/notes
**Controller:** NotesApiController@get

Returns saved additional notes from app_settings table.

Response: `{ success: true, notes: "..." }`

---

## Authenticated API

### POST /api/sync
**Controller:** SyncApiController@sync

Batch sync offline quotations. Processes array of quotation payloads.

Request body:
```json
{
    "quotations": [
        {
            "customerName": "...",
            "customerType": "...",
            "loanAmount": 1000000,
            "banks": [...],
            "documents": [...]
        }
    ]
}
```

Response:
```json
{
    "success": true,
    "results": [
        { "index": 0, "success": true, "filename": "..." },
        { "index": 1, "success": false, "error": "..." }
    ]
}
```

### POST /api/notes
**Controller:** NotesApiController@save

Save additional notes to app_settings table.

### GET /api/notifications/count
**Controller:** NotificationController@unreadCount

Returns unread notification count. Polled every 60 seconds from navbar.

Response: `{ count: 5 }`

### POST /notifications/{notification}/read
**Controller:** NotificationController@markRead

Mark single notification as read.

### POST /notifications/read-all
**Controller:** NotificationController@markAllRead

Mark all user's notifications as read.

### GET /api/impersonate/users
**Controller:** ImpersonateController@users

Search active users for impersonation. Query param: `q` (search term).

### GET /api/reverse-geocode
**Controller:** LoanValuationController@reverseGeocode

Get address from coordinates using OpenStreetMap Nominatim.

Query params: `lat`, `lng`

### GET /api/search-location
**Controller:** LoanValuationController@searchLocation

Search locations using OpenStreetMap Nominatim.

Query param: `q` (search term)

---

## AJAX Endpoints (return JSON, used by frontend)

These are web routes that return JSON responses:

| Route | Purpose |
|-------|---------|
| /dashboard/quotation-data | DataTable data for quotations |
| /dashboard/loan-data | DataTable data for loans |
| /dashboard/task-data | DataTable data for loan tasks |
| /dashboard/dvr-data | DataTable data for DVR |
| /users/data | DataTable data for users |
| /users/check-email | Email uniqueness check |
| /users/product-stage-holders | Product stage user assignments |
| /loans/data | DataTable data for loans |
| /general-tasks/data | DataTable data for tasks |
| /general-tasks/search-loans | Search loans for task linking |
| /dvr/data | DataTable data for DVR |
| /dvr/search-loans | Search loans for DVR linking |
| /dvr/search-quotations | Search quotations for DVR linking |
| /dvr/search-contacts | Search existing contacts |
| /roles/check-name | Role name uniqueness check |
| /activity-log/data | DataTable data for activity log |
