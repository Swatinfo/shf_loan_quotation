# Coding Feedback & Preferences

Consolidated rules from past sessions. Follow these in all future work.

## Frontend Stack
- **Bootstrap 5.3 + jQuery 3.7** — local vendor files, no build step, NO Tailwind/Alpine
- **`shf-` prefix** for all custom CSS classes
- Custom CSS in `public/css/shf.css`, JS in `public/js/shf-app.js`

## Responsive Design
- **Navbar**: `navbar-expand-lg` (992px), not `-sm` (576px) — all nav visibility: `d-lg-*`
- **Filters**: `col-6 col-md-auto` pattern — pair on mobile, auto-width on desktop
- **Tables 5+ columns**: Dual layout — desktop table (`d-none d-md-block`) + mobile card (`d-md-none`)

## CSS Rules
- **Tables**: Bootstrap built-in classes (`table`, `table-hover`) — no custom dark gradient headers
- **Dates**: Bootstrap Datepicker (local vendor) — never native `<input type="date">`
- **`btn-accent-outline`**: Uses `color: var(--accent)` not `var(--white)` — white text invisible on light backgrounds

## Config / Settings
- **JSON casts**: When model has `'array'` cast, pass raw array — never manually `json_encode` (double-encoding bug)
- **Tag inputs**: Auto-add pending values on form submit — users expect Save to capture typed-but-not-added values
- **Settings tabs**: All doc type tabs must render inputs on page load, not just the active tab (prevents silent data loss)

## Views
- **Always** `@extends`/`@section` pattern — never Blade component wrappers (`<x-app-layout>`)
- Update CLAUDE.md and reference docs in the same change as view architecture updates

## Testing
- Auth/Profile tests (Breeze defaults) have pre-existing failures due to `EnsureUserIsActive` middleware + disabled registration — do NOT debug during unrelated work
