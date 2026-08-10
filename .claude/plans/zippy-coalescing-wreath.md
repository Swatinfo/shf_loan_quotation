# Export to Excel — Loan Pipeline & Loan Report

## Context

The Loan Pipeline (`reports.pipeline`) and Loan Report (`reports.loans`) pages currently render on-screen only — there is no way to take the data into Excel for sharing/analysis. We will add an **Export** button to both pages that downloads a true `.xlsx` file honoring the exact filters, status/tab selection, permission gate (`view_reports`), and server-side scope currently applied to the on-screen data.

**User-approved decisions:**
- **True .xlsx with no new composer package** — a small in-house writer using PHP's built-in `ZipArchive` (an .xlsx is a zip of XML parts). Amounts as real numeric cells, dates as real date cells.
- **Pipeline exports the active tab** — Loans tab or Workload-by-User tab.
- **Stage details flattened into one text column** (one line per open stage, wrapped cell), one row per loan.

Key constraint discovered in exploration: the JSON endpoints return **display-formatted** values (`₹ 12,34,567` via `NumberToWordsService::formatCurrency`, `d/m/Y` strings), so exports need raw values — hence a small behavior-neutral refactor of `ReportController` to separate raw row collection from formatting.

## Hard guarantees (user requirements)

1. **Export always applies the currently applied filters.** The Export button builds its querystring from the same `getFilters()` the table refresh uses (status/tab, period dates, bank, product, branch, user, stage, stuck-days), and the server re-applies `applyFilters()` + `applyScope()` + the status/stage_key/stuck_days logic through the same shared private methods as the JSON endpoints — the export can never diverge from what the screen queries.
2. **Export always contains ALL matching records, never just the visible page.** The export runs its own full server-side query with **no `limit`/`paginate`/`take`** — it does not read what's rendered in the DOM. (Today these two pages happen to render all rows client-side with no pagination, but the export is independent of that by design: if row pagination is ever added to the page or the data endpoint, the export endpoint remains unpaginated and still returns the complete filtered set.) A dedicated test creates more rows than a typical page size and asserts every one appears in the sheet.

## Step 1 — New service `app/Services/XlsxExportService.php`

Self-contained OOXML writer (ZipArchive only, no dependencies).

```php
final class XlsxExportService
{
    public const TYPE_STRING = 'string';
    public const TYPE_NUMBER = 'number';
    public const TYPE_DATE   = 'date';

    /**
     * @param array<int,string>               $headers     Row-1 labels (bold)
     * @param iterable<int,array<int,mixed>>  $rows        Cell values; null/'' => empty cell
     * @param array<int,string>               $columnTypes col index => TYPE_* (default string)
     * @param array<int,array<int,mixed>>     $footerRows  Bold totals rows appended last
     */
    public function download(string $filename, array $headers, iterable $rows,
        array $columnTypes = [], array $footerRows = [], string $sheetName = 'Report'): BinaryFileResponse
}
```

- Zip parts: `[Content_Types].xml`, `_rels/.rels`, `xl/workbook.xml`, `xl/_rels/workbook.xml.rels`, `xl/styles.xml`, `xl/worksheets/sheet1.xml`. No BOM anywhere.
- **Strings**: inline strings (`t="inlineStr"`, `<t xml:space="preserve">`) — no sharedStrings.xml needed. Escape with `htmlspecialchars($s, ENT_XML1|ENT_QUOTES, 'UTF-8')` + strip XML-invalid control chars (`/[\x00-\x08\x0B\x0C\x0E-\x1F]/u`) — the main defense against Excel "repair" prompts.
- **Numbers**: raw value in `<v>`, style with builtin numFmt 3 (`#,##0`). (Indian lakh/crore grouping isn't expressible in standard OOXML format codes — plain `#,##0` accepted.) Cast `(int)`/`(float)` before writing so SQLite string bindings don't leak.
- **Dates**: real serials — `intdiv(strtotime($ymd.' 00:00:00 UTC'), 86400) + 25569`, custom numFmt 164 `dd/mm/yyyy`. Accepts `Y-m-d`/`Y-m-d H:i:s`/null.
- **Multi-line stage cell**: literal `\n` inside `<t xml:space="preserve">` + wrapText style.
- Fixed `cellXfs`: 0 default, 1 bold, 2 number, 3 date, 4 wrapText, 5 bold+number (footer).
- `<cols>` widths: per-type defaults (12 numbers/dates, 20 strings, 50 stage column).
- Write to `tempnam()`, then `response()->download($path, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend(true)` — same pattern as `QuotationController@download` (line ~321).

## Step 2 — Behavior-neutral refactor of `app/Http/Controllers/ReportController.php`

Extract private methods that return **raw** rows; the existing JSON methods format on top (output must stay byte-identical — existing tests prove it):

- `pipelineRawRows(Request $request, array $scope): array` — current lines ~55–159 body: base query + filters/scope + summary source query untouched; row map returns raw `loan_amount`/`sanctioned_amount`/`disbursed_amount` as `?int`, `status_since`/`rejected_at` as `Y-m-d` (or raw), keeps `id`, `stage_lines[]` (already raw). Returns `['rows' => Collection, 'summaryBase' => Builder, 'queued_parallel' => int]` — **`queued_parallel` computed exactly where it is today** (from `$linesByLoan`, pre stage_key/stuck filters) to avoid a regression. Stage_key/stuck_days filtering + stuck-first sort stay inside so export matches screen.
- `workloadRows(Request $request, array $scope): Collection` — current `workloadData()` body (lines ~173–202) returning `$data`; `workloadData()` becomes a thin JSON wrapper (rows are already raw).
- `loanReportRows(Request $request, array $scope): Collection` — current lines ~513–544 of `loanReportData()` (query build + filters + scope + order), returns the stdClass rows.
- `pipelineData()` / `loanReportData()` re-map raw rows to the current formatted JSON shape (formatCurrency, `d/m/Y`, `stages_url` from `id`, em-dash fallbacks).

**Gate check**: run `php artisan test --compact tests/Feature/PipelineReportTest.php tests/Feature/LoanReportTest.php` after this step — must be green before continuing.

## Step 3 — Export controller methods

```php
public function pipelineExport(Request $request, XlsxExportService $xlsx): BinaryFileResponse
public function loanReportExport(Request $request, XlsxExportService $xlsx): BinaryFileResponse
private function flattenStageLines($lines): string
```

Both open with `$user = $this->authorizeReports(); $scope = $this->reportScope($user);` — identical gate + scope as the data endpoints; filters come through the shared privates (`applyFilters`/`applyScope` are never bypassed).

`pipelineExport` branches on `tab=workload` like `pipelineData`. Filenames: `loan-pipeline-{status}-YYYY-MM-DD.xlsx`, `workload-by-user-YYYY-MM-DD.xlsx`, `loan-report-{sanctioned|disbursed}-YYYY-MM-DD.xlsx` (via `now()->format('Y-m-d')`).

`flattenStageLines` — newline-separated, one line per open stage:
- in progress: `{stage_name} — {owner|—} — {days_in_stage}d` + ` ({days_with_owner}d with owner)` when different + ` [{n} open queries]` when > 0
- pending: `{stage_name} — {owner|unassigned} — queued {queued_days}d`

### Column layouts

**Pipeline (sheet "Pipeline")** — one unified superset (19 cols), empty cells where not applicable; avoids per-status header logic and matches the `all` chip anyway:

Loan # · Customer · Bank / Product · Branch · Advisor · Loan Amount(N) · Age days(N) · Status · Current Stage(s) (wrap) · Max Stage Days(N) · Sanctioned(N) · Disbursed(N) · TAT days(N) · Status Reason · Status Since(D) · Rejected At Stage · Rejection Reason · Rejected By · Rejected On(D)

**Workload (sheet "Workload")** — User · Stages Held(N) · Oldest days(N) · Average days(N) · Stuck > 7d(N) · Stages

**Loan Report (sheet "Loan Report")** — Loan # · Customer · Bank / Product · Branch · Advisor · Loan Amount(N) · Sanctioned(N) · Disbursed(N) · Sanctioned On(D) · Disbursed On(D) · Status; plus one bold footer row: `Totals ({count} loans)` + raw sanctioned/disbursed sums.

## Step 4 — Routes (`routes/web.php`, next to the existing report routes ~line 219–224, same auth group)

```php
Route::get('/reports/pipeline/export', [ReportController::class, 'pipelineExport'])->name('reports.pipeline.export');
Route::get('/reports/loans/export', [ReportController::class, 'loanReportExport'])->name('reports.loans.export');
```

GET + querystring so the browser downloads by navigation; gating stays in-controller like the other report routes.

## Step 5 — Frontend

- `resources/views/newtheme/reports/pipeline.blade.php`: add an **Export** button (`id="plExport"`, plain `btn` class matching `plClear`, download SVG icon) in the filter card's `.card-hd .actions`; add `exportUrl: @json(route('reports.pipeline.export'))` to `window.__PL`.
- `resources/views/newtheme/reports/loan-report.blade.php`: same (`id="lrExport"`, `window.__LR.exportUrl`).
- `public/newtheme/pages/pipeline.js` / `loan-report.js`: click handler → `window.location = URLS.exportUrl + '?' + buildQuery(getFilters())`. `getFilters()` already carries tab/status/stage/stuck/date/bank/product/branch/user, so the export always matches the screen. No AJAX, no `SHF.loader` (download navigation doesn't unload the page).
- **Version bump (required — existing `public/` files change)**: new timestamp in `SHF_VERSION` (`.env` + `.env.example`) and `SHF_SW_VERSION` (`public/sw.js:9`).

## Step 6 — Tests: new `tests/Feature/ReportExportTest.php`

Copy `setUp` role seeding + `makeUser`/`makeLoan` helpers from `LoanReportTest`. Shared helper `sheetXml(TestResponse)` opens `getFile()->getPathname()` with ZipArchive, returns `xl/worksheets/sheet1.xml`, asserts `simplexml_load_string` succeeds (well-formed guard).

Cases:
1. 403 without `view_reports` on both export routes; guest → login redirect.
2. Loan report export: 200, xlsx MIME, `Content-Disposition` has `loan-report-sanctioned-…xlsx`.
3. Raw numeric amounts: sheet contains `<v>900000</v>`, contains loan number as inline string, does **not** contain `₹`.
4. Totals footer row present with correct raw sum + `Totals (2 loans)` label.
5. `?status=disbursed` excludes sanctioned-only loans.
6. Forged scope: BM in branch A + `?branch_id={B}` → branch-B loan absent (mirrors existing scope tests).
7. Pipeline export flattens stage lines: stage name + owner name appear in one cell.
8. Pipeline `?status=completed` excludes active loans; `?stuck_days=999` → no data rows.
9. `?tab=workload` → holder name in sheet, filename `workload-by-user-`.
10. Empty result (far-future `date_from`) → still a valid xlsx with header row.
11. Date serial correctness: expected value computed in-test with the same UTC epoch formula.
12. **All records exported regardless of pagination/visible rows**: create 60+ matching loans, assert the sheet contains every loan number (row count in XML = 60 + header) for both the loan report and pipeline exports.

Then rerun `PipelineReportTest` + `LoanReportTest` (refactor neutrality) and the full suite.

## Step 7 — Formatting, docs sync, bookkeeping

- `vendor/bin/pint --dirty --format agent`
- `.claude/routes-reference.md` — add the two export routes to the Reports table.
- `.claude/services-reference.md` — add `XlsxExportService` entry.
- `.docs/loans.md` (reports section) — note Export buttons + raw-value semantics.
- `tasks/todo.md` — track task; `tasks/lessons.md` if anything new emerges.
- Manual check: open one downloaded file in Excel/LibreOffice — no repair prompt, amounts right-aligned numeric, dates sortable, stage cell wraps.

## Verification

1. `php artisan test --compact tests/Feature/ReportExportTest.php tests/Feature/PipelineReportTest.php tests/Feature/LoanReportTest.php`
2. Browser: log in, Pipeline → set filters + switch chips/tabs → Export → file matches screen data; repeat on Loan Report with Sanctioned/Disbursed.
3. Log in as a branch manager: export only contains own-branch loans even with a forged `branch_id` in the URL.

## Risks

- **Excel repair prompt** → ENT_XML1 escaping + control-char strip + no BOM + well-formedness assertion in every test.
- **`queued_parallel` regression** → computed at its current location on the pre-stage-filter line set, returned alongside raw rows.
- **`deleteFileAfterSend` in feature tests** → read the temp file path immediately inside the same test via `getFile()->getPathname()`.
- **Size** → temp-file approach bounded by the same unpaginated dataset the JSON endpoints already load; no streaming needed.
