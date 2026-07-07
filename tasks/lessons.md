# Lessons Learned

Patterns and corrections captured during development. Review at session start.

---

## Report SQL — driver portability + SQLite affinity (2026-07-07)

- **`DATEDIFF`/`TIMESTAMPDIFF` are MySQL-only** — any report SQL using them is untestable on the SQLite test connection. Pattern now in `ReportController::dayDiffSql()/hourDiffSql()`: branch on `DB::connection()->getDriverName()`, SQLite equivalent via `julianday()`. Any future raw date math must go through such helpers or tests can't cover it.
- **SQLite drops column affinity on expressions**: `WHERE COALESCE(ld.assigned_advisor, ld.created_by) IN ('2')` matches NOTHING on SQLite (integer 2 ≠ text '2' without column affinity), while the same filter on a bare column works. Cast request-sourced ids with `array_map('intval', ...)` before binding them against expressions. Cost 1 test-debug cycle.
- **Never use `loan_details.updated_at` as a completion timestamp** — it bumps on any later touch (bulk backfills, `$loan->touch()` in transferStage, edits). The turnaround report did and inflated TAT 3-4× (35 vs 8 days). Real completion = `MAX(stage_assignments.completed_at)` where status=completed (joinSub). `status_changed_at` is NULL on historic rows — don't rely on it.

## Global AJAX loader — jQuery vs fetch split (2026-07-07)

- **The codebase has a clean accidental convention: jQuery `$.post/$.ajax` = user actions, `fetch()` = background traffic** (notification badge poll, DataTables/list loads, offline sync, push registration). So the global loading overlay hooks `$(document).ajaxSend/ajaxComplete` and gets all ~48 user-action sites for free with zero false positives. Preserve this split: new background jQuery calls must pass `global: false`; new fetch-based *user actions* must call `SHF.loader.begin()/end()` in try/finally (guarded `if (window.SHF && SHF.loader)`).
- Loader design points that matter: reference counting (overlapping requests share one overlay), 250ms show-delay + ≥300ms min-visible (no flash/blink), 30s watchdog so a lost `end()` can't freeze the UI, `pageshow` bfcache reset, and on-demand DOM creation (2026-07-03 lesson: static helper markup can vanish from this app's DOM at load).
- Verified via the node-harness pattern (`global.window = globalThis` + Proxy jQuery stub + `new Function`): timers tested with real short sleeps — 15 assertions run in ~3s, no jsdom needed.

## Query resolve deadlock + non-transactional decisionAction (2026-07-07)

- **Raiser-only resolve deadlocks escalated stages** (loan-104 incident): a query blocks completion while `pending` OR `responded` (`hasPendingQueries()`), but resolve was gated to `raised_by` only — no admin bypass. After escalation (raiser → BM → BDH) the raiser leaves the stage and nobody can close the query. Fixed: resolvable by raiser OR current `StageAssignment.assigned_to` OR admin/super_admin, at any non-resolved status (no response required). Any future "only actor X may do Y" gate on a *blocking* record needs an escape hatch for the current holder + admin.
- **`decisionAction` approve was non-transactional** (FIXED 2026-07-07): it set `is_sanctioned=1` + merged `decision_action=approved` into notes BEFORE `updateStageStatus('completed')`, which throws on unresolved queries — leaving a half-approved loan (sanctioned flag set, stage still in_progress) and a 500 whose JSON has `message`, not `error`, so the Swal showed nothing useful. Fix: pre-check `hasPendingQueries()` + `canTransitionTo('completed')` and return 422 `{error}` BEFORE any mutation (same pattern `updateStatus` already used). Rule: any endpoint whose downstream service throws must either guard first or return the message under the `error` key — the stage-scripts Swal handlers read `responseJSON.error` (now with a `responseJSON.message` fallback on the decision handlers). Re-clicking Approve on an already-half-approved loan is idempotent and completes cleanly once the blocker clears.
- **`transferStage` "reassign open queries" was a no-op for ~2 months**: it updated `stage_assignment_id` to the value the rows already had, instead of `assigned_to_user_id`. A migration-era rename left the comment lying. Now: open queries whose recipient was the outgoing assignee follow the handoff. Watch for updates that "succeed" but set a column to its current value — tests only caught this once an assertion checked the recipient after transfer.
- **Queries raised under impersonation belong to the impersonated user** (`raised_by`) — the real actor is only in the activity log (`impersonator_id`). The impersonated user may never know they "raised" a blocking query. Consider surfacing awaiting-resolve queries on the dashboard.

## Real app DB is MySQL, tests are SQLite (2026-05-30)

- Despite CLAUDE.md saying SQLite, the **live app DB is MySQL** (`config('database.default') === 'mysql'`; the FlyEnv CLI has `pdo_mysql`, not `pdo_sqlite`). `php artisan migrate`/`tinker`/commands run against **MySQL**. The **PHPUnit suite** uses in-memory **SQLite** (phpunit.xml).
- Consequences for schema work: SQLite supports **partial/expression indexes** (`CREATE UNIQUE INDEX … WHERE …`), MySQL does **not**. And on MySQL a plain UNIQUE index **counts soft-deleted rows**, so SoftDeletes + unique columns collide — null the unique column on soft-deleted/merged rows, or branch by `Schema::getConnection()->getDriverName()`. The `add_unique_pan_index_to_customers` migration branches sqlite/pgsql (partial) vs mysql (plain unique + PAN nulled on merge).
- So: validate data migrations/commands with a real `--dry-run` against MySQL (e.g. `customers:backfill-kyc --dry-run`), not only via SQLite tests.

## Running tests locally — pdo_sqlite not in CLI PHP (2026-05-29)

- The phpunit suite uses in-memory SQLite, but the local CLI PHP (FlyEnv, `D:\FlyEnv-Data\env\php`) does **not** load `pdo_sqlite`/`sqlite3`, so `php artisan test` fails every DB test with `could not find driver`. This is environmental, not a code bug.
- `php -d extension=...` does **not** help `php artisan test` because it spawns a child PHP process that drops the `-d` flags. Run PHPUnit directly instead:
  `php -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll vendor/phpunit/phpunit/phpunit --filter=SomeTest`
- The DLLs already exist in `D:\FlyEnv-Data\env\php\ext\`; they're just not enabled in that ini. Permanent fix would be enabling them in `D:\FlyEnv-Data\env\php\php.ini`.
- Every `php`/`artisan` call also prints `PHP Warning: Module "mysqli" is already loaded` — harmless, filter with `| grep -v mysqli`.

---

## Global uppercase inputs + case-insensitive login (2026-05-09)

- **Auto-uppercase scope**: `shf-newtheme.js` (global) + `shf-app.js` (per-page) install a single delegated `input/change/blur` handler on `input, textarea` plus a defensive `submit`-time sweep. They guard each other via `window.__shfUppercaseInstalled` so pages that load both scripts (quotation create) don't double-bind. Skip set: `password, hidden, file, number, date, datetime-local, month, time, week, tel, color, range, checkbox, radio, button, submit, reset, image`, plus `.shf-amount-input/.shf-amount-raw/.shf-datepicker*` classes, plus `[data-no-uppercase]` (which walks ancestors so a whole form/section can opt out). Email is **included** — server side handles login normalization.
- **Caret preservation matters**: naively setting `el.value = upper` jumps the cursor to the end. The handler reads `selectionStart/End` before the swap and restores them after — wrapped in try/catch because some `<input type=...>` (number, date) throw on `.selectionStart`. `toUpperCase()` doesn't change ASCII length so positions stay valid; for pasted Unicode this might drift one or two chars, which is acceptable.
- **Email case at every auth choke point**: don't rely on database collation alone. `User::setEmailAttribute` lowercases on store, and `LoginRequest::authenticate`, `PasswordResetLinkController::store`, `NewPasswordController::store` all `Str::lower(trim(...))` the request email before validation/lookup. Any future auth surface (admin user-create, profile-edit, magic links) must follow the same pattern or it'll silently fail-to-match for users typed in uppercase.
- **Submit-time sweep is a real net**: programmatic `.value = ...` (autofill races, third-party widgets, hidden fields populated by other handlers) bypasses the `input` event. The form-level sweep on `submit` re-runs `applyUppercase` on every input/textarea before the request leaves the page. Without it, autofilled emails posted lowercase even though the user "saw" uppercase mid-form.

---

## Legal skip-bank + Original Document Verification (2026-05-07)

- **Legal Verification has a "Complete without sending to bank" path**: task owner / branch_manager / bdh of the loan's branch (and super_admin) can finish legal at Phase 1 without ever sending to bank_employee. Notes carry `legal_skipped_bank=true` + `legal_phase=completed_skip_bank`. The action is gated server-side via `LoanStageController::canSkipLegalBank()` and mirrored client-side via the same condition in the Phase 1 blade — bank_employees never see the button and would 403 anyway.
- **`original_document_verification` sub-stage opens after legal**: it's a sub-stage of `parallel_processing` (sequence_order=4 like its siblings), but excluded from `startRemainingParallelSubStages()` so it stays pending until legal completes. `handleStageCompletion('legal_verification')` either auto-completes ODV (when `legal_skipped_bank` is true) or moves it from pending→in_progress with auto-assignment.
- **State machine forbids `pending → completed`**: when auto-completing ODV, lift it to `in_progress` first then `completed`. Blindly calling `updateStageStatus('completed')` on a pending assignment throws `Cannot transition stage 'X' from 'pending' to 'completed'`. Same gotcha would apply to any future "auto-complete on trigger" hook.
- **`initializeStages` baseStageKeys is hardcoded** in `LoanStageService.php`. Adding a new stage row to the `stages` table is *not enough* — you must also append the key to that array, or `initializeStages` won't create a `stage_assignments` row for new loans. Caught this only via test feedback ("Attempt to read property status on null").
- **In-flight loans don't get the new stage**: matches the existing snapshot pattern. New loans created after the migration get `original_document_verification` automatically. Older loans already at later stages have a frozen `workflow_config` and no ODV assignment row — that's intentional and documented.
- **Tests using protected routes need permissions seeded explicitly**: the legal-action route is gated by `permission:manage_loan_stages`. With `RefreshDatabase`, the role-permission seeds DO run (via the unified-roles migration), but adding a new permission slug or testing a fresh role requires explicitly seeding `Permission::firstOrCreate` + `role_permission` insert in the test's `setUp()`. Don't assume role grants exist just because the role exists.

---

## Quotation document strike-out + edit flow (2026-05-07)

- **Persist the full master doc list, not just selected ones**: `quotation_documents.is_excluded` is the source of truth. `QuotationService::buildTemplateDataFromInput()` keeps two arrays — `documentsAll` (every row, persisted) and `documents` (only included, fed to the PDF). The PDF never sees excluded rows. The show/edit pages render all rows including struck ones with line-through styling, so the operator can see what was considered + rejected.
- **Toggle clears the cached branded PDF, not the row**: `QuotationService::toggleDocumentExclusion()` sets `quotations.pdf_filename` and `pdf_path` to null + unlinks the file on disk. The next download regenerates fresh. We never regenerate eagerly inside the toggle endpoint — keeps the AJAX call cheap.
- **`isEditableBy()` is the editor authority**: editable iff (not converted) AND (not cancelled) AND (super_admin OR `edit_quotation` perm + ownership/branch/view_all). Conversion gate is absolute — even super_admin can't edit a converted quotation. Mirrors the existing `destroy()` and `convert()` rules. Routes are double-gated: middleware on `permission:edit_quotation` + `abort_unless($q->isEditableBy(...))` in the controller.
- **Last-write-wins, but re-check inside the transaction**: `update()` runs `if ($quotation->fresh()->is_converted) throw` inside the DB transaction so a conversion that lands between the edit page load and submit aborts the update with a 422 instead of silently mutating a converted quotation. This is the cheapest concurrency defense — no optimistic locking column needed.
- **Edit form reuses the create form via injected JS prefill**: rather than duplicate ~250 lines of HTML, `edit.blade.php` mirrors the create-page body and injects `window.SHF_QUOTATION_PREFILL` + `window.SHF_QUOTATION_UPDATE_URL` ahead of `_create-script`. The script applies prefill in a single sweep at end of init, and the existing submit handler routes to PUT vs POST based on the URL global. Bank chips are simulated via `toggleBank()` calls in click order so `selectedBanks` matches the original index layout.
- **Blade `@json([...])` chokes on nested arrow-fn closures + array literals**: the directive's argument parser stops at the first matching `)` and reports "Unclosed `[`". Workaround: build the payload in a `@php` block first, then `@json($variable)`. This bit me on `edit.blade.php` — moving the array out of the directive's argument fixed it instantly.

---

## Stage query routing (2026-05-07)

- **Queries are escalated to internal SHF roles, never bank_employee**: a raised query routes to `loan.assigned_advisor` (fallback `loan.created_by`) by default. The only exception: a `bank_employee` raiser hitting an assignment whose `assigned_to` user holds the `office_employee` role — that gets routed to the office_employee. Even though `stage_assignments.assigned_to` may legitimately be a bank_employee mid-phase (legal P2, rate_pf P2, sanction P2, esign P2/P4), they are *never* the query recipient. Rationale: queries are escalations the SHF side owns, not work items handed off to the bank.
- **Recipient persisted, not implicit**: `stage_queries.assigned_to_user_id` is now a real column (nullable FK, indexed with `status`). Filled at create time by `StageQueryService::resolveQueryRecipient()`. Don't try to re-resolve from `stage_assignments.assigned_to` later — the assignment may have transferred since the query was raised. Always read the persisted column.
- **Bank employee on the assignment still passively sees the query**: the existing `assignment.queries()` relation is unchanged, so the bank_employee currently working the phase still sees the query thread on their stage view. They just don't get a bell ping. This is intentional — they have read context without being the action target.
- **Notification fan-out skips the raiser**: even with the "always notify the loan creator/advisor" rule, if the advisor IS the raiser, no self-notification fires. Matches the pattern in `NotificationService::notifyStageCompleted` (`->reject(fn ($id) => $id === auth()->id())`). The query row still saves and the activity log still records; only the redundant self-notification is suppressed.
- **Notification fan-out is wrapped in try/catch**: per the 2026-04-18 lesson, broadcast/push failures (Reverb down in dev) must never bubble up and 500 the query-raise request. The DB row is committed before any notify call.

---

## Branch-scoped visibility (2026-04-18)
- **All branch scopes must read the full `user_branches` list, not `default_branch_id`**: `$user->branches()->pluck('branches.id')` is the canonical pattern across `LoanDetail::scopeVisibleTo`, `Quotation::scopeVisibleTo`, `Customer::scopeVisibleTo`, and `DailyVisitReport::scopeVisibleTo`. This matches the multi-branch UX on the user edit page (`assigned_branches[]`). Do not use `default_branch_id` for scope — it's the create-time fallback only.
- **`view_all_*` permissions must be removed from branch-scoped roles**: giving `branch_manager`/`bdh` a `view_all_loans` or `view_all_quotations` short-circuits the scope before the branch check runs, making them see everything across branches. Admin keeps both bypasses.
- **Ensure `branch_id` on create, or scope rows drop**: `QuotationService::generate()` falls back to `User::find($userId)?->default_branch_id` because a null `branch_id` means a branch_manager can never see the record via their branch list (they'd have to be the creator). Same rule for any future branch-scoped module.
- **Customer scope uses `whereHas('loans')`**: `customers` has no `branch_id` column. Scope joins via `loans.branch_id` (option chosen over backfilling a column). Trade-off: a customer with zero loans is invisible to branch roles — acceptable because `Customer` rows are only created during loan conversion.
- **Admin DVR is participant, not supervisor**: the seeder grants `admin` `view_dvr + create_dvr + edit_dvr` (no `view_all_dvr`/`delete_dvr`) so admins who create DVRs see only their own. If you need a read-all admin, grant `view_all_dvr` as a user-level override, not by changing role defaults.

---

## Quotation hold/cancel design (2026-04-18)
- **Follow-up on hold → always DVR, never task**: DVR already has `quotation_id` FK + first-class `follow_up_date`/`follow_up_needed`/`is_follow_up_done` fields and a visit-chain model; tasks are for internal to-dos. Do not add a "create as task" option on quotation hold even though `general_tasks.quotation_id` exists.
- **Hold vs cancel lifecycle**: `status=cancelled` is terminal (no resume, no convert). `on_hold` → `active` via `/quotations/{id}/resume`. Conversion is blocked on cancelled quotations in `LoanConversionController`.
- **Reason vocab = config, not enum**: `quotationHoldReasons` / `quotationCancelReasons` stored as `[{key, label_en, label_gu}, …]` in `app_config.main`, editable at `/settings` → Quotation Reasons. Same pattern as `dvrContactTypes` / `dvrPurposes`. Controller validates `reason_key` using `in:` rule against the config's key list at request time.
- **Dashboard shortcut vs modal UX**: Dashboard Hold/Cancel buttons navigate to `/quotations/{id}?action=hold|cancel`; the show page auto-opens the corresponding modal. Keeps modal UI in one place and avoids duplicating big reason-lists across views. Resume is a simple SweetAlert confirm + POST (no form), OK on dashboard directly.
- **`.dvr-remove-btn` jQuery handler must check `listId` explicitly**: original DVR code had an `else` fallback. Adding another list (quotation hold reasons) broke it — misrouted deletes to DVR purposes. Always narrow `if/else if` when handlers are reused for new lists.
- **Reason lists use a `group` field for `<optgroup>` rendering**: `quotationHoldReasons` / `quotationCancelReasons` items each carry `{key, label_en, label_gu, group}`. The settings UI shows a group input per row (with `<datalist>` suggestions from existing groups) and renders the list grouped by `group`. The show-page modal renders options inside `<optgroup>` blocks. Missing/blank `group` → falls back to `Other`. If you add another grouped vocab later, reuse `renderReasonList()` — don't copy `renderDvrList()` which is flat.
- **Never let notification fan-out fail the primary request**: `ShfNotification::booted()` creates a row, then fans out to Reverb broadcast + Web Push. Both fan-outs are wrapped in `try/catch` + `Log::warning` because: (a) `NotificationBroadcast` uses `ShouldBroadcastNow` (synchronous HTTP to Reverb), and (b) Reverb is a separate process that may be down in dev or during restarts. If the broadcast throws, the DB row is already saved — letting the exception bubble would show a 500 for an operation that actually succeeded. Same rule applies to any future side-effect added to `booted()`.
- **`ConfigService::load()` self-heals top-level key drift**: the `app_config.main` row is seeded with the full `config/app-defaults.php` tree on first call. When new top-level keys are later added to defaults (e.g. `dvrContactTypes`, `quotationHoldReasons`), `load()` merges them in memory but also persists the merge back to DB via `save($merged)` if `array_diff_key($merged, $loaded)` is non-empty. Prevents silent DB drift where the merged read-path works but the DB row is missing keys. **Caveat**: the check is top-level only — if you add a *nested* assoc key (e.g. `iomCharges.newField`), self-heal won't catch it. For nested additions, add a one-off migration that calls `ConfigService::load()` → `save()`, or extend `load()` to walk nested assoc arrays.
- **`ConfigService` MUST bypass Laravel's config cache for `app-defaults.php`**: the defaults file is admin-editable reference data, not boot-time config. `ConfigService::defaults()` uses `require base_path('config/app-defaults.php')` instead of `config('app-defaults')`, so `php artisan config:cache` never freezes it. Never replace `$this->defaults()` back with `config('app-defaults')` — that reintroduces the stale-cache bug where a file edit on production wouldn't take effect until `config:clear`.

---

## Layout & Views
- **2026-02-27**: Migrated from Blade component slots (`<x-app-layout>`, `{{ $slot }}`) to `@extends`/`@section` pattern. Always use `@extends('layouts.app')` or `@extends('layouts.guest')` — never Blade component wrappers.
- **2026-02-27**: When updating view architecture, always update CLAUDE.md + MEMORY.md in the same change. Don't forget documentation sync.

## Frontend Stack
- **2026-02-27**: Frontend is Bootstrap 5.3 + jQuery 3.7 (local vendor files), NOT Tailwind/Alpine. All CSS classes use `shf-` prefix. Custom CSS in `public/css/shf.css`, JS in `public/js/shf-app.js`.

## Theme & CSS Variables
- **2026-04-14**: CSS variable `--dark` does NOT exist. Use `--primary-dark-solid` (#3a3536) for solid dark backgrounds, `--primary-dark` (semi-transparent), `--primary-dark-light` (lighter). Using `var(--dark)` causes transparent backgrounds making white text invisible.
- **2026-04-14**: Font classes: `font-display` = Jost (headings, modal titles, buttons), `font-body` = Archivo (body, forms). Always add `font-display` to modal titles and section headers.
- **2026-04-14**: Full variable palette: `--accent` (#f15a29), `--accent-warm` (#f47929), `--accent-light` (#f99d3e), `--accent-dim` (10% opacity), `--bg` (#f8f8f8), `--bg-alt` (#e6e7e8), `--text` (#1a1a1a), `--text-muted` (#6b7280), `--border` (#bcbec0), `--red` (#c0392b), `--green` (#27ae60).

## Modals & Dialogs
- **2026-04-14**: Modal header pattern: `background: var(--primary-dark-solid); color: #fff; border-radius: 12px 12px 0 0;` with `btn-close btn-close-white`. Never use plain Bootstrap modal header.
- **2026-04-14**: Modal footer buttons: Cancel = `btn-accent-outline btn-accent-sm`, Save/Submit = `btn-accent btn-accent-sm`. Never use `btn btn-secondary` or any Bootstrap default button classes in modals.
- **2026-04-14**: Modal titles must be bilingual (English / Gujarati) with `font-display` class. E.g., "Create New Task / નવું ટાસ્ક બનાવો", "Edit Task / ટાસ્ક સુધારો".
- **2026-04-14**: Modal centering is handled globally in `shf.css` via `.modal-dialog` flexbox. Do NOT add `modal-dialog-centered` class to individual modals — it's redundant.
- **2026-04-14**: Modal & SweetAlert backdrop uses branded orange-tinted gradient (`--primary-dark-solid` to `--accent` at 25%) defined in `shf.css`. Don't use plain gray/black backdrops.
- **2026-04-14**: Danger/delete buttons in modals: use `shf-btn-danger-alt` class, never inline `style="background:linear-gradient(135deg,#dc3545,#e85d6a);"`.

## SweetAlert (Swal)
- **2026-04-14**: Delete confirmation forms: add `shf-confirm-delete` class to the `<form>` — `shf-app.js` auto-handles the Swal.fire popup with `data-confirm-title` and `data-confirm-text` attributes.
- **2026-04-14**: Swal button color convention: orange `#f15a29` for confirmations, red `#dc2626` for destructive actions, gray `#6c757d` for cancel. Many existing calls are inconsistent (known debt).

## Buttons
- **2026-04-14**: Always use custom button classes: `btn-accent` / `btn-accent-outline` for actions, `btn-accent-sm` for size. Never Bootstrap defaults (`btn-primary`, `btn-secondary`, `btn-outline-secondary`, `btn-outline-light`, `btn-dark`).
- **2026-04-14**: On dark backgrounds (e.g., `shf-section-header`, `shf-page-header`), use `btn-accent-outline-white` — not `btn btn-outline-light`.
- **2026-04-14**: Danger buttons: use `shf-btn-danger-alt` class. Other semantic colors: `shf-btn-success` (green), `shf-btn-warning` (yellow), `shf-btn-gray` (gray).

## UI Debt Resolved (2026-04-14)
- All `var(--dark)` replaced with `var(--primary-dark-solid)` across all blade files
- `raiseQueryModal` standardized: dark header + bilingual title + accent buttons
- Inline danger gradient replaced with `shf-btn-danger-alt` class
- All Bootstrap button classes (`btn-outline-secondary`, `btn-outline-light`, `btn-outline-primary`, `btn-outline-danger`, `btn-outline-warning`, `btn-success`, `btn-dark`) replaced with custom `shf-*` / `btn-accent-*` classes across all blade views
- Redundant `modal-dialog-centered` removed (global CSS handles centering)
- Swal `confirmButtonColor` standardized: `#dc2626` (red) for destructive, `#f15a29` (orange) for confirmations. `cancelButtonColor: '#6c757d'` added where missing

## Responsive Design
- **2026-02-27**: Use `navbar-expand-lg` (992px) not `navbar-expand-sm` (576px) — sm is too small for anything with 5+ nav items. All nav visibility classes must match: `d-lg-*` not `d-sm-*`.
- **2026-02-27**: Filter forms should use `col-6 col-md-auto` pattern — fields pair on mobile, auto-width on desktop. Never `col-sm-auto` for 4+ filter fields.
- **2026-02-27**: Tables with 5+ columns need dual layout: desktop table (`d-none d-md-block`) + mobile card layout (`d-md-none`). Card layout is far better than horizontal scroll on phones.

## Tables & Date Inputs
- **2026-02-27**: Use Bootstrap's built-in table classes (`table`, `table-hover`, etc.) for all tables — not custom `shf-table` with dark gradient headers. Keep it clean, no shadow backgrounds on tables.
- **2026-02-27**: Use Bootstrap Datepicker (local vendor files, path: `vendor/datepicker/`) for all date inputs — not native `<input type="date">`.

## Workflow
- **2026-02-27**: ALWAYS write the plan to `tasks/todo.md` BEFORE starting implementation — not just show it to the user. The plan in todo.md IS the plan of record.
- **2026-02-27**: Update `tasks/todo.md` progress (check items) as EACH step completes — not all at once after the entire task is done. The user should be able to see live progress.

## Settings / Config
- **2026-03-12**: When Eloquent model has `'array'` cast on a JSON column, pass the raw array — don't manually `json_encode`. Double-encoding causes data to be stored as a JSON string inside a JSON string.
- **2026-03-12**: Settings forms with tag-based UI (banks, tenures, documents) must auto-add pending input values on form submit. Users expect typing a value and clicking "Save" to work — they shouldn't need to click "+ Add" first.
- **2026-03-12**: Settings documents form: all doc type tabs must render their inputs on page load, not just the active tab. Otherwise, only the active tab's data is included in the form submission and other types get silently lost.

## Workflow Config
- **2026-04-16**: Stage roles simplified to 3 categories: `task_owner`, `bank_employee`, `office_employee`. BM/BDH/LA are all "task_owner" (resolved from loan's assigned_advisor/created_by at runtime).
- **2026-04-16**: Workflow config is frozen at loan creation time (`loan_details.workflow_config` JSON). All phase transitions read from snapshot. Config changes only affect new loans.
- **2026-04-16**: Bank-wise overrides stored in `bank_stage_configs` table. Only rows where bank differs from master default. UI shows all banks always.
- **2026-04-16**: All multi-phase stages now have `sub_actions` in DB with `role` field per phase: legal_verification (3), technical_valuation (2), rate_pf (3), sanction (3), docket (2), esign (4).
- **2026-04-16**: `product_stage_users.phase_index` allows per-phase user assignment. null = stage-level, integer = phase-specific.
- **2026-04-18**: Normalized `rate_pf.sub_actions` from 2 entries (implicit phase 1) to 3 entries — one per runtime phase. Phase indices shifted: controller calls `getLoanPhaseRole($loan, 'rate_pf', $idx)` now pass `1` for phase 2 (was `0`) and `2` for phase 3 (was `1`). Migration `2026_04_18_100000_normalize_rate_pf_sub_actions` shifts `stages.sub_actions`, `bank_stage_configs.phase_roles`, `product_stage_users.phase_index`, and `loan_details.workflow_config.rate_pf.phases`. All multi-phase stages now share the same convention: one sub_actions entry per runtime phase.

## Documentation Sync
- **2026-04-07**: ALWAYS update reference docs (database-schema.md, routes-reference.md, services-reference.md, models.md, permissions.md) AS PART of each phase implementation — not deferred. Mark "Update reference docs" complete only after actually updating them.

## Testing
- **2026-02-27**: Auth and Profile tests (Breeze defaults) have pre-existing failures due to `EnsureUserIsActive` middleware and disabled registration. These are NOT caused by view changes — don't waste time debugging them during unrelated work.

## Feature Flags
- **2026-04-18**: `app.open_rate_pf_parallel` (env `OPEN_RATE_PF_PARALLEL`) controls whether `rate_pf` runs in parallel with the `parallel_processing` sub-stages and whether `sanction` waits for both. Helper: `LoanStageService::usesParallelRatePf()`. Always read via `config('app.*')`, never `env()` directly inside services (Laravel 12 rule). Flag does not invalidate `loan_details.workflow_config` snapshot; flips are safe at runtime after `config:clear`. Helpers `openRatePfInParallel` and `advanceToSanctionIfReady` are public; backfill for in-flight loans can be done via a tinker call like `app(LoanStageService::class)->openRatePfInParallel($loan)` or via a seed command.

## Notifications
- **2026-06-09**: **FCM (and Web Push) delivery depends entirely on the queue worker running.** `ShfNotification::created` (model hook) does two things: fires Web Push inline, and `SendFcmPush::dispatch($id)` onto the **database** queue. Nothing sends FCM synchronously — so if no `queue:work` is draining, *every* push (loan stage, query, task, and `reminders:send-daily`) silently piles up in the `jobs` table. We found 121 `SendFcmPush` jobs stuck across 8 days because the server had no `schedule:run` cron at all. Direct delivery works (`php artisan fcm:test` bypasses the queue → HTTP 200) — the gap is purely the queue not being drained. Diagnose with: `DB::table('jobs')->count()` (backlog), `DB::table('jobs')` payload `displayName` (which jobs), `failed_jobs` count (0 = never ran vs >0 = ran-and-threw), and `fcm:test` (proves credentials/devices independent of the queue).
- **2026-06-09**: **Self-healing queue worker from cron — no systemd.** `routes/console.php` runs a *persistent* `queue:work --max-time=300 --sleep=1` via `->everyMinute()->withoutOverlapping(6)->runInBackground()`. The `everyMinute + withoutOverlapping` combo is the auto-restart: a live worker holds the mutex so the tick is skipped (no duplicates); a dead/exited worker frees it so the next tick relaunches one (~1 min recovery). Critical gotcha: the **`withoutOverlapping($ttl)` minutes must be just ABOVE `--max-time` seconds-as-minutes** (6 min TTL vs 300s=5min max-time). Too short → a still-running worker's lock expires and a SECOND worker spawns (duplicates); default (no arg = 24h) → a hard-killed worker blocks restart for a day. `--max-time` makes the worker exit cleanly (releasing the mutex via `schedule:finish`) so the TTL only matters for hard kills (reboot/OOM). `runInBackground()` keeps the hour... er, 5-min-long worker from blocking the other scheduled commands. Needs exactly one crontab line: `* * * * * cd /path && php artisan schedule:run`. Verify the worker is alive with `pgrep -af 'artisan queue:work'`.
- **2026-06-01**: Removed Laravel Reverb / WebSocket broadcasting entirely. It was already dead on the frontend — the newtheme `layouts/app.blade.php` never loaded the Echo client (all Echo wiring lived only in `.ignore/old_code_backup/`), so the backend `NotificationBroadcast` (`ShouldBroadcastNow`) was firing synchronously into a down Reverb server on every notification create → caught + logged as warning. Removed: `app/Events/NotificationBroadcast.php`, `config/reverb.php`, the `reverb` connection in `config/broadcasting.php`, `laravel/reverb` composer dep, REVERB_* env vars (set `BROADCAST_CONNECTION=null`), and the obsolete `tests/Feature/NotificationBroadcastTest.php`. In-app bell now relies solely on the 60s badge poll + Web Push (unaffected — independent of broadcasting). The poll in `app.blade.php` now plays the in-app chime (`SHFPush.playChime`, honours mute + per-user preset in localStorage) when the unread count rises — Option B. Custom mp3 chimes only work while a tab is open; a fully-closed PWA gets the OS default sound (service workers can't play custom audio; `Notification.sound` is deprecated).

## Amount-in-words helpers exist in THREE copies — keep the crore recursion in sync (2026-07-04)

- `NumberToWordsService` (PHP), `shf-app.js` `SHF.numberToWordsEn/Gu`, and the guarded copies in `shf-newtheme.js` must stay behavior-identical. The PHP service recurses the crore segment (`innerDigitsEn/Gu`) but the JS copies passed the crore count through the 3-digit helper — so 20000000000 (2000 crore) rendered "undefined Hundred Crore Rupees / વીસ સો કરોડ રૂપિયા" (`ones[20]` is undefined; Gujarati "twenty hundred"). Fixed by porting a recursive `seg()` into both JS files. Any future words-logic change must touch all three files (and bump `SHF_VERSION`/`SHF_SW_VERSION` since two are public assets).
- Quick node harness for browser-IIFE JS: `global.window = globalThis` (so bare `SHF` references resolve like in a browser) + a Proxy-based jQuery stub + `new Function('$','jQuery','window','document', src)` — lets PHPUnit-less frontend helpers be asserted from the CLI.

---

## Service-worker stale assets — "works only after hard refresh" (2026-07-04)

- **Symptom on the server**: entering product payout slabs on `/loan-settings` only worked after a hard refresh, and broke again on the next normal visit. Root cause: `sw.js` served every `.css`/`.js` **cache-first keyed by full URL (including the `?v=` query)**, and neither `SHF_SW_VERSION` (sw.js) nor `SHF_VERSION` (`.env` → `config('app.shf_version')` → `?v=` on asset links) had been bumped since 2026-06-23 — before the payout feature's 654-line `loan-settings.css` rewrite shipped. Clients were pinned to the pre-deploy CSS forever; hard refresh bypasses the SW, which is why it "worked once".
- **Diagnostic shortcut**: "works after hard refresh, breaks again on normal reload" = client-side cache (SW or HTTP), never a server-side issue. Check `SHF_SW_VERSION` and `SHF_VERSION` dates against the deploy date first.
- **Fix (two layers)**: (1) `sw.js` static-asset strategy changed from cache-first to **stale-while-revalidate** — serves cache instantly but always refetches in background and updates the cache, so a missed version bump self-heals one reload later; (2) bumped both versions to `20260704103000`.
- **Deploy rule going forward**: any change to files under `public/` requires bumping BOTH `SHF_SW_VERSION` in `sw.js` and `SHF_VERSION` in the **server** `.env` (+ `php artisan config:clear`). The inline blade JS is always fresh (HTML is network-first) — it's the external CSS/JS that gets pinned.

---

## Loan-settings silent-failure debugging (2026-07-03, follow-up)

- **"Save does nothing" on /loan-settings had TWO silent-failure causes**: (1) the page rendered NO `session('success')`/`session('error')`/`$errors` feedback at all — server-side rejections (e.g. overlapping payout slabs) redirected back with a flash nobody displayed, and even successful saves showed nothing; (2) the tab-restore JS sent `$errors->any()` redirects to the *Banks* tab regardless of which form failed. Fixed: flash + errors banners at the top of `loan-settings/index.blade.php`, and an `old('bank_id') !== null → products` branch in the tab-restore. Any new tab/form on this page must post a distinguishing `old()` marker and be added to that restore chain.
- **Statically-rendered helper divs can vanish from this page's DOM at load** — `#productSlabError` was present in the server HTML but absent from the rendered DOM (remover unidentified; page is service-worker-controlled and multiple global sweepers run at boot). Robust pattern: (re)create feedback elements **on demand** from JS (`productSlabErrorEl()` inserts after `#productSlabList` if missing) instead of relying on markup surviving page init. Also never give custom error divs the `shf-validation-error` class — `SHF.validateForm` deletes ALL nodes with that class on every submit.
- **Debugging tip that cracked it**: compare `fetch(location.href)` (fresh server HTML) against `document.getElementById(...)` (live DOM) — instantly separates "server didn't render it" from "JS removed it". And read `list_network_requests` timelines carefully: preserved requests span navigations, so a POST in the list may belong to an earlier action.

---

## Product payout config + loan-settings page conventions (2026-07-03)

- **`/loan-settings` deliberately does NOT load Bootstrap CSS** — only the JS bundle (for `Collapse`). `loan-settings.css` shims `.row`/`.col-*`/`.d-flex` inside the `.loan-settings-nt` scope. Consequence: **Bootstrap modals would render unstyled on this page** — use the established edit pattern instead: an Edit button that populates the Add-form collapse via JS (`.shf-edit-bank`/`.shf-edit-branch`/`.shf-edit-product`), switches the form title/submit text, and opens the collapse. Cancel resets via the shared `.shf-form-cancel` handler — extend its `else if (formId === ...)` chain for any new form.
- **`settings/_workflow-body.blade.php` is dead code**: it references `settings.workflow.*` routes that no longer exist. Product/bank management lives only in `/loan-settings` (`loan-settings/_panes.blade.php` + `WorkflowConfigController`). Don't extend the dead file.
- **Product payout config is storage-only** (`is_pf_based`, `max_payout_amount`, `product_payout_slabs` table): the payout-to-loan-creator calculation is future scope. Slab ranges are intended against the **disbursed amount**; validation enforces high > low, percent ≤ 100, and non-overlapping ranges (sorted, inclusive bounds). Slabs are **replaced wholesale** on every product save (form = full state).
- **Product soft-delete keeps its payout slabs**: `products` uses SoftDeletes, so the `product_payout_slabs.product_id` FK cascade never fires on normal deletes — intentional, keeps config for potential restore. Only a hard delete cascades.
- Slab data attribute pattern: build the payload in `@php` first, then `data-slabs='@json($variable)'` + jQuery `.data('slabs')` auto-parses. Blade's `@json` hex-escapes quotes so single-quoted attributes are safe.
- **`@json(...)` with a closure inside its argument MIS-COMPILES SILENTLY** (bit us again 2026-07-03, same as the 2026-05-07 lesson): Blade's directive-argument parser stops at the wrong `)`, emitting invalid PHP. Crucially, this is NOT a Blade compile-time error — `Blade::compileString()` and `php artisan view:cache` both "succeed"; the `ParseError: Unclosed '[' does not match ')'` only explodes at RENDER time from the cached compiled file. So a green `view:cache` does not prove templates are runtime-safe. To actually verify a suspect blade: compile it and `php -l` the output (`php -l` on the file under `storage/framework/views/`). Rule stands: never put a closure/array-literal expression inside any Blade directive argument — assign in `@php`, pass the variable.

---

## disbursement_entries mirror table (2026-07-03)

- **`disbursement_entries` is a write-through mirror of the `entries` json, synced ONLY by `DisbursementService::syncEntryRows()`** — never insert/update rows directly, or the json `row_id` linkage drifts. The json remains the read path (`entryList()`); the table exists for future payout queries + audit.
- **Row identity via `row_id` round-trip**: each json entry stores its mirror row's PK; the form posts it back in a hidden input. Update-in-place vs soft-delete vs create is decided purely by row_id presence/ownership. A row_id not belonging to the current disbursement is treated as NEW (anti-hijack) — don't "fix" that into a findOrFail.
- **`is_active` follows loan status via `LoanDetail::booted()` updated-hook** (cancelled/rejected/on_hold → 0; anything else → 1), bulk query update so soft-deleted rows are untouched (SoftDeletingScope applies to the relation update). Note: query-builder status writes (`DB::table(...)->update`) bypass the hook — all current status writers go through Eloquent instances.
- **Relation naming trap**: `DisbursementDetail` has an `entries` JSON column cast, so the HasMany is `entryRows()` — an `entries()` relation would collide with the attribute and break the cast.

---

## Multi-entry disbursement tranches (2026-07-03)

- **`disbursement_details.entries` (json) is the source of truth**; legacy columns (`disbursement_type`, `disbursement_date`, `amount_disbursed`, `bank_account_number`) are *derived on every save* so old read sites keep working without changes — notably `LoanStageService::handleStageCompletion`'s OTC-skip check (`disbursement_type === 'fund_transfer'`) and `LoanTimelineService`. If you add entry-level data, keep the derivation in `DisbursementService::processDisbursement` in sync.
- **Method is per entry** (mixed NEFT + cheque tranches allowed). `disbursement_type` derives to `'cheque'` when ANY entry is a cheque → OTC opens; all-NEFT skips OTC and completes the loan.
- **The disbursement stage no longer completes on save.** Auto-complete fires only when the entry total ≥ `DisbursementService::disbursementTarget()` (sanctioned_amount column → docket notes → sanction notes → loan_amount) AND the assignment is `in_progress` (guards the pending→completed state-machine error). Under-disbursement is closed manually via `loans.disbursement.complete` ("Mark as Fully Disbursed").
- **Read old rows via `DisbursementDetail::entryList()`**, never raw `->cheques` — it maps legacy cheques/single-amount rows into the entry shape (amount key is `amount`, not `cheque_amount`). The OTC cheque table in `_stages-body` filters `entryList()` by `method === 'cheque'`.
- **Per-entry product dropdown is bank-scoped server-side**: `store()` validates `product_id` against the loan's bank's active products (`in:` rule) and snapshots `product_name` into the entry (consistent with the frozen-config pattern — product renames don't rewrite history).
- **Form lock rule changed**: the disbursement form is read-only once the stage completes (not just when the loan closes) — prevents editing tranches after OTC/completion.

---

## Listings / Cancelled-record leakage
- **2026-06-23**: **Cancelled/closed loans & quotations were leaking into "my work" listings.** `LoanDetail::scopeVisibleTo` and `Quotation::scopeVisibleTo` are permission/ownership scopes ONLY — they never filter status, so any list relying on `visibleTo()` (or on raw `StageAssignment::where('assigned_to',...)`) shows tasks/queries on cancelled loans. Fixes: (1) added `LoanDetail::CLOSED_STATUSES` (completed/rejected/cancelled) + `scopeOpen()` (`whereNotIn status CLOSED_STATUSES`); (2) added `whereHas('loan', fn($q)=>$q->open())` to the four `StageAssignment::where('assigned_to',$user->id)` "my tasks" sites in DashboardController (`taskData` ~244, KPI 'My Tasks' tile ~848, `newthemeTabCounts['my_tasks']` ~879, `newthemeMyLoanTasks` ~938) and to `newthemeOpenQueries` (`StageQuery::active()->whereHas('loan', open)`); (3) added `GeneralTask::scopeWithActiveLinks()` (`whereDoesntHave` cancelled loan/quotation — keeps unlinked tasks) applied to `GeneralTaskController::taskData`, `newthemePersonalTasks`, `newthemeTabCounts['personal_tasks']`, and the 'Tasks Overdue' KPI tile. Keep status filters at call sites — do NOT bake them into `scopeVisibleTo` (the loan list legitimately shows closed loans). Test: `tests/Feature/CancelledRecordListingExclusionTest.php`.

## Loan amounts — sanctioned/disbursed columns
- **2026-06-23**: **Promoted `sanctioned_amount` + `disbursed_amount` to real `loan_details` columns** (were JSON-only in `stage_assignments.notes`). Migration `2026_06_23_185610_*` adds both (unsignedBigInteger nullable) + backfills (sanctioned ← docket notes `sanctioned_amount`, sanction-stage fallback; disbursed ← `disbursement_details.amount_disbursed`). Write-time sync at two choke-points: `LoanStageController::saveNotes()` (docket always wins; sanction fills only when column null) and `DisbursementService::processDisbursement()`. Listings (`LoanController::loanData`, dashboard `newthemeLoans`) now read the columns and render **separate Sanctioned/Disbursed columns** (`—` when null) instead of stacking under Amount. **Latent bug found+fixed:** the old loans-list `DIS-₹` line read `disbursement` stage-notes `disbursed_amount`, a key **never written in production** → always blank; real value was always `disbursement_details.amount_disbursed`. Accessors `formatted_sanctioned_amount`/`formatted_disbursed_amount` return null when empty. Test: `tests/Feature/LoanSanctionedDisbursedColumnsTest.php`. NOTE: migration not yet run on the live DB — env reports APPLICATION IN PRODUCTION, so `php artisan migrate` was declined; must be run (`--force` / deploy pipeline) before the columns exist in prod.
