# Task Tracker

Current and completed tasks. Updated as work progresses.

---

## In Progress

*(none)*

---

## Completed

### Fix offline sync showing "synced" but not saving to database
When going offline and coming back online, the sync showed "synced" toast but records were never persisted to the database.

**Root cause**: `QuotationService::generate()` returned `success: true` even when DB transaction failed (catch block at line 213). `SyncApiController` trusted this flag and told the frontend to delete queued items from IndexedDB — permanent data loss.

**Changes**:
- [x] `QuotationService` catch block: Changed from `success: true` to `success: false` with error message (keeps filename for online flow fallback)
- [x] `SyncApiController`: Added secondary check — requires `quotation` to be non-null for `success: true`
- [x] `SyncApiController`: Added auto-fill `preparedByName`/`preparedByMobile` from auth user (matching QuotationController)
- [x] `SyncApiController`: Added `ActivityLog::log()` on successful sync (with `source: offline_sync`)
- [x] `QuotationController`: Handles new `success: false` + `filename` case — still returns PDF to online user even if DB save fails
- [x] Created 9 tests in `tests/Feature/SyncApiTest.php` — all pass (auth, empty, success, DB failure, auto-fill, activity log, batch partial failures, validation error)
- [x] Pint clean

### Fix slow PDF generation on Linux
PDF generation hard-coded `PHP_OS_FAMILY === 'Windows'` to decide strategy. On Linux, it always used the slow microservice path even when Chrome was installed.

**Changes**:
- [x] Refactored `PdfGenerationService::generate()` with three-tier strategy: force microservice → Chrome headless → microservice fallback
- [x] Added `isChromeAvailable()`, `generateWithChrome()`, `generateWithMicroservice()` private methods
- [x] Linux Chrome path uses `escapeshellarg()` for security (was unquoted)
- [x] Added `CURLOPT_CONNECTTIMEOUT=5` (fail fast) + increased `CURLOPT_TIMEOUT` from 30→60
- [x] Added `pdf_use_microservice` config key + `PDF_USE_MICROSERVICE` env var
- [x] Updated `.env.example` with new option
- [x] Created 12 tests in `tests/Feature/PdfGenerationServiceTest.php` — all pass
- [x] Pint clean, updated services-reference.md

### Fix DataTables offline — show cached data instead of blocking "offline" screen
Previous fix showed a blocking "You are offline" message that hid the entire dashboard. App should remain usable offline since it's a PWA.

**Fix plan**:
- [x] Service worker: skip `/dashboard/quotation-data` so it doesn't serve cached HTML for JSON endpoint
- [x] Bump SW cache version to v12 for update propagation
- [x] DataTables: replace `ajax` object with function — cache response in localStorage, serve cached data when offline
- [x] Mobile cards: same caching pattern — cache on success, serve cached on error
- [x] Replace blocking `showOfflineState()` with subtle yellow "Offline — showing cached data" indicator
- [x] Auto-reload when back online (removes indicator, fetches fresh data)
- [x] Tests pass (15/15), Pint clean

---

## Completed

- [x] Phase 3: Remove x-app-layout / x-guest-layout — switch to @extends/@section
  - Updated `layouts/app.blade.php` (`@hasSection`/`@yield`)
  - Updated `layouts/guest.blade.php` (`@yield`)
  - Converted 10 authenticated views
  - Converted 6 auth views
  - Deleted `AppLayout.php` and `GuestLayout.php` component classes
  - Updated CLAUDE.md, MEMORY.md documentation
  - Verified: zero remaining component references, Pint clean, no new test failures

- [x] Fix mobile responsiveness across all pages
  - **Navigation**: Changed `navbar-expand-sm` → `navbar-expand-lg` so hamburger menu shows on phones+tablets (<992px)
  - **Navigation**: Updated desktop nav visibility from `d-sm-*` → `d-lg-*` breakpoints
  - **Dashboard filters**: Changed from `col-sm-*` → `col-6 col-md-*` so fields pair up on mobile, full row on desktop
  - **Dashboard table**: Added mobile card layout (`d-md-none`) with clean card UI, desktop table hidden on mobile (`d-none d-md-block`)
  - **Users table**: Added mobile card layout with user info, badges, and action links
  - **Activity log filters**: Changed from `col-sm-auto` → `col-6 col-md-auto` for better mobile stacking
  - **Activity log table**: Added mobile card layout with badge, user, details
  - **btn-accent-outline**: Fixed color from `var(--white)` to `var(--accent)` so it's visible on light backgrounds
  - **CSS**: Added `shf-table-mobile` class for generic table-to-card mobile conversion
  - **CSS**: Added `btn-accent-outline-white` variant for dark background usage

---

### Remove auto-download PDFs on offline sync
- [x] Removed `window.open()` auto-download loop from `offlineSync` handler in `quotations/create.blade.php`
- [x] Updated toast to say "synced! Download from dashboard" instead of "synced & downloaded"
- [x] Sync still creates quotations + PDFs server-side, user downloads manually

### Bootstrap Tables + Datepicker Migration
- [x] Downloaded Bootstrap Datepicker 1.10.0 to `public/vendor/datepicker/`
- [x] Converted `shf-table` → Bootstrap `table table-hover` in 6 views
- [x] Replaced `shf-table` CSS with Bootstrap table overrides in `shf.css`
- [x] Converted 4 date inputs → Bootstrap Datepicker (dashboard + activity-log)
- [x] Added datepicker CSS/JS globally in `layouts/app.blade.php`
- [x] Updated DataTables CSS for Bootstrap table integration
- [x] All 15 tests pass, Pint clean

### Dashboard DataTables AJAX Implementation
- [x] Download DataTables 2.2.2 + Bootstrap 5 integration files to `public/vendor/datatables/`
- [x] Add `@stack('styles')` to `layouts/app.blade.php` `<head>` (after shf.css)
- [x] Add `GET /dashboard/quotation-data` route in `routes/web.php`
- [x] Add `quotationData()` AJAX endpoint to `DashboardController`
- [x] Simplify `index()` in `DashboardController` — stats + users + permissions only
- [x] Update `destroy()` in `QuotationController` to return JSON when `expectsJson()`
- [x] Rewrite `dashboard.blade.php` with DataTables, mobile cards, AJAX delete, confirm modal
- [x] Add DataTables CSS overrides to `public/css/shf.css`
- [x] Write 15 feature tests in `tests/Feature/DashboardDataTableTest.php` — all pass
- [x] Run Pint (clean) + tests (15 passed, 65 assertions)
- [x] Update routes-reference.md
