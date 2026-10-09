# Plan: Project-wide font readability via one revertible override CSS

## Context

Across the app many components render text at **10–11.5px** (and some rem tokens below
`0.75rem`), which is hard to read — seen on the loan **stages** page (phase pills, the muted
"Can transfer to…" line, OTC sub-meta, helper notes) but present on nearly every page. Inventory:
**~306 `font-size` declarations below 12px across ~40 CSS files**, plus ~23 rem values below
`0.75rem`.

The user wants this fixed **project-wide** and, importantly, **isolated in a single new CSS file**
so it can be reverted by removing one include — rather than editing ~40 existing CSS files in place.

**Goal:** no app text below a **12px readable floor**, delivered as one override stylesheet loaded
last, trivially revertible.

## Loading model (confirmed)

- Global CSS (every page), in `resources/views/newtheme/layouts/app.blade.php` head (lines 18–24):
  `assets/shf.css`, `assets/shf-extras.css`, `assets/shf-workflow.css`, `assets/shf-modals.css`.
  These use **hardcoded px** and do NOT define the `--shf-text-*` tokens.
- Legacy `public/newtheme/css/shf.css` — defines the `--shf-text-*` **tokens** + `.shf-text-*`
  utilities; loaded only on some pages (stages, documents, settings, quotations show/convert,
  valuation, loan-settings) via their own `@push('page-styles')`.
- Per-page `public/newtheme/pages/*.css` — loaded at `@stack('page-styles')` (app.blade.php:181).

So the override must load **after** `@stack('page-styles')` to beat global + legacy + page CSS.

## Approach — one generated override file

Create **`public/newtheme/assets/shf-readability.css`** and link it **once**, immediately after
`@stack('page-styles')` (app.blade.php:181), so it cascades last:

```blade
@stack('page-styles')
{{-- Readability floor (12px min). Remove this one line to fully revert. --}}
<link rel="stylesheet" href="{{ asset('newtheme/assets/shf-readability.css') }}?v={{ $v }}">
```

The file contains two parts:

1. **Token re-declaration** — `:root { --shf-text-2xs: 0.75rem; }` (was 0.65rem/10.4px → 12px).
   `--shf-text-xs/sm/base` already ≥ 0.75rem, left as-is. This lifts every `.shf-text-2xs` utility
   and every `font-size: var(--shf-text-2xs)` usage in one shot (no per-selector work for those).
2. **Per-selector overrides** for the **hardcoded** sub-12px sizes, each `font-size: 12px !important;`
   under the SAME selector (and same `@media` context) as the source, so it wins by source order.

### How the file is generated (repeatable, not hand-written)

A throwaway script in `.scratch/` (run with `python -I`) scans the **loaded** CSS files and emits the
override, so it stays complete and consistent:

- **Scan:** `public/newtheme/css/shf.css`, `public/newtheme/assets/{shf,shf-extras,shf-workflow,shf-modals}.css`,
  `public/newtheme/pages/*.css`.
- **Exclude:** `shf_.css` (dead backup), `*_bkp*`, anything under `.ignore/`.
- For each rule whose `font-size` is a **numeric** value `< 12px` (or `< 0.75rem`), emit
  `«selector» { font-size: 12px !important; }` (rem→`0.75rem`), preserving its enclosing `@media`.
- **Skip:** `font-size: var(--…)` values (covered by the token bump) and any rule inside
  `@keyframes` / `@font-face` (their `%`/descriptor "selectors" aren't real selectors).
- Floor only: values already ≥ 12px / ≥ 0.75rem are never touched, minimizing layout shift.

The **script is throwaway**; only the generated `shf-readability.css` + the one `<link>` are kept.
Revert = delete that `<link>` line (and optionally the file). Source CSS files are never modified.

### Inline styles (separate, tiny)

3 blade spots use inline `style="…font-size:11.5px"` (external CSS can't target inline styles cleanly).
These will be bumped to `12px` directly in those 3 blades — listed at apply time. Minor and obvious;
noted here so "all text ≥ 12px" is actually true. (Everything else stays in the override file.)

### Bump `SHF_VERSION`

New CSS file → bump `SHF_VERSION` in `.env` + `php artisan config:clear` (cache-busting rule), so
the override is actually served.

## Files

- **New:** `public/newtheme/assets/shf-readability.css` (generated override).
- **Edit (1 line):** `resources/views/newtheme/layouts/app.blade.php` — add the `<link>` after line 181.
- **Edit (tiny):** up to 3 blade files with inline `font-size:11.5px` → `12px`.
- **Not modified:** every existing `*.css` (so revert is clean).

## Verification

- **Static:** the generated file parses (no stray braces); grep confirms it declares the expected
  number of overrides and that no scanned source rule < 12px lacks a matching override.
- **Visual (local app):** run `php artisan serve`, log in, and use Chrome DevTools MCP to screenshot
  representative pages — **stages** (the reported example), **dashboard**, **a settings tab**,
  **DVR/reports** — confirming phase pills, badges, table/meta text are now ≥ 12px and nothing
  overflows or wraps badly. Re-render the stages page to compare against the supplied PDF.
- If any component looks cramped at 12px (e.g., a dense badge), add a targeted exception in the same
  override file — still one file, still revertible.
