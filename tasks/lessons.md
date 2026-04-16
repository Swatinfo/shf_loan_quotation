# Lessons Learned

Patterns and corrections captured during development. Review at session start.

---

## Layout & Views
- **2026-02-27**: Migrated from Blade component slots (`<x-app-layout>`, `{{ $slot }}`) to `@extends`/`@section` pattern. Always use `@extends('layouts.app')` or `@extends('layouts.guest')` — never Blade component wrappers.
- **2026-02-27**: When updating view architecture, always update CLAUDE.md + MEMORY.md in the same change. Don't forget documentation sync.

## Frontend Stack
- **2026-02-27**: Frontend is Bootstrap 5.3 + jQuery 3.7 (local vendor files), NOT Tailwind/Alpine. All CSS classes use `shf-` prefix. Custom CSS in `public/css/shf.css`, JS in `public/js/shf-app.js`.

## Responsive Design
- **2026-02-27**: Use `navbar-expand-lg` (992px) not `navbar-expand-sm` (576px) — sm is too small for anything with 5+ nav items. All nav visibility classes must match: `d-lg-*` not `d-sm-*`.
- **2026-02-27**: Filter forms should use `col-6 col-md-auto` pattern — fields pair on mobile, auto-width on desktop. Never `col-sm-auto` for 4+ filter fields.
- **2026-02-27**: Tables with 5+ columns need dual layout: desktop table (`d-none d-md-block`) + mobile card layout (`d-md-none`). Card layout is far better than horizontal scroll on phones.
- **2026-02-27**: `btn-accent-outline` should use `color: var(--accent)` not `var(--white)` — white text is invisible on light backgrounds. Use `btn-accent-outline-white` variant on dark backgrounds.

## Workflow
- **2026-02-27**: ALWAYS write the plan to `tasks/todo.md` BEFORE starting implementation — not just show it to the user. The plan in todo.md IS the plan of record.
- **2026-02-27**: Update `tasks/todo.md` progress (check items) as EACH step completes — not all at once after the entire task is done. The user should be able to see live progress.
- **2026-02-27**: Use Bootstrap's built-in table classes (`table`, `table-hover`, etc.) for all tables — not custom `shf-table` with dark gradient headers. Keep it clean, no shadow backgrounds on tables.
- **2026-02-27**: Use Bootstrap Datepicker (local vendor files) for all date inputs — not native `<input type="date">`.

## Testing
- **2026-02-27**: Auth and Profile tests (Breeze defaults) have pre-existing failures due to `EnsureUserIsActive` middleware and disabled registration. These are NOT caused by view changes — don't waste time debugging them during unrelated work.
- **2026-02-27**: When testing `PdfGenerationService`, mock `renderHtml()` to return simple HTML — the template requires a complex data structure that's irrelevant to strategy/generation tests. Use `createPartialMock(PdfGenerationService::class, ['renderHtml'])`.

## Architecture
- **2026-02-27**: Don't hard-code OS checks (`PHP_OS_FAMILY`) as the only strategy selector. Use capability detection (is Chrome available? is exec() enabled?) and config overrides (`PDF_USE_MICROSERVICE`) for flexibility. The same binary (Chrome headless) works on both Windows and Linux.
- **2026-02-28**: Never return `success: true` from a service when the DB save fails. A failed DB transaction means failure — even if the PDF was generated. Returning false success causes offline sync to delete queued items from IndexedDB, losing data permanently. Return `success: false` with both `error` and `filename` so callers can decide how to handle partial success.
- **2026-02-28**: Sync endpoints (`SyncApiController`) must mirror the same logic as the online controller (`QuotationController`): auto-fill fields, log activity, validate response thoroughly (check `quotation` is not null, not just `success` flag).
