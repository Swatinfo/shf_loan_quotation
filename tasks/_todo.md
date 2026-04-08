# Task Tracker

Current and completed tasks. Updated as work progresses.

---

## In Progress

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
