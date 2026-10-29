# Services Reference

14 services in `app/Services/`. Orchestrate domain logic; called from controllers, never from views. Services are constructor-injected via Laravel's container (no explicit binding).

## ConfigService

Reads/writes `app_config` table (key `main`) and merges with `config/app-defaults.php`.

| Method | Signature | Notes |
|---|---|---|
| `load` | `(): array` | Returns merged config; seeds from defaults if row missing |
| `save` | `(array $config): void` | Upserts `app_config.main` |
| `reset` | `(): array` | Overwrites DB with `config('app-defaults')` |
| `get` | `(string $key, $default = null)` | Dot-notation read (e.g., `iomCharges.fixedCharge`) |
| `updateSection` | `(string $section, $value): array` | Dot-notation write + save |
| `updateMany` | `(array $updates): array` | Batch dot-notation writes + single save |

### Merge behavior

`mergeWithDefaults()` uses `array_replace_recursive($defaults, $loaded)`, then `replaceSequentialArrays()` walks the merged tree and **entirely replaces any sequential (indexed) array** with the DB value. Result:

- **Assoc arrays** (e.g., `iomCharges.*`): merged per key. New default keys appear even if not in DB.
- **Sequential arrays** (e.g., `banks`, `documents_en.proprietor`, `tenures`): replaced from DB, so UI deletions are respected.

### Double-encode pitfall

`AppConfig.config_json` is cast to `array`. Always pass raw arrays to `updateSection`/`updateMany`; never `json_encode()` yourself — the cast handles serialization.

---

## PermissionService

3-tier resolution for `User->hasPermission($slug)`:

1. `$user->hasRole('super_admin')` → `true`
2. User-specific override in `user_permissions` → `grant`/`deny`
3. Any `role_permission` row across user's roles → `true`; else `false`

| Method | Signature | Notes |
|---|---|---|
| `userHasPermission` | `(User, string): bool` | Main entry |
| `userRolesHavePermission` | `(User, string): bool` | Only checks role-level |
| `getUserPermissions` | `(User): array` | `[slug => bool]` for all permissions |
| `getGroupedPermissions` | `(): array` | `[group => [Permission,...]]` |
| `allSlugs` | `(): array` | Returns all permission slugs; cached 1h; used by `Gate::before` so `@can('slug')` / `$user->can('slug')` resolve through this service |
| `clearUserCache` | `(User): void` | Call after user roles or overrides change |
| `clearRoleCache` | `(): void` | Call after any role_permission change |
| `clearAllCaches` | `(): void` | After bulk edits or permission schema change; also forgets `all_permission_slugs` |

### Cache

| Key | TTL | Populated by |
|---|---|---|
| `user_perms:{userId}` | 300s (5 min) | `getUserOverride()` — maps slug → grant/deny for that user |
| `user_role_ids:{userId}` | 300s (5 min) | `getUserRoleIds()` — role IDs for a user |
| `role_perms:{sortedRoleIds}` | 300s (5 min) | `getRolePermissionSlugs()` — unique slugs across the comma-joined, sorted role-id set |
| `all_permission_slugs` | 3600s (1 hour) | `allSlugs()` — all permission slugs; returns `[]` quietly on table error (mid-migration safe) |

---

## QuotationService

Constructor: `ConfigService`, `PdfGenerationService`.

### `generate(array $input, int $userId): array`

Validates input, renders PDF, persists Quotation + QuotationBank + QuotationEmi + QuotationDocument, updates `bank_charges` for latest reference.

Validation rules (inline in service):
- `customerName`, `customerType`, `loanAmount` required
- `loanAmount` ≤ 10^12
- `banks[]` required array
- Per bank: `roiMin`, `roiMax` in (0, 30], `roiMin ≤ roiMax`

Additional accepted inputs (optional, passed through to template/persistence):
- `location_id`, `branch_id` — persisted on the Quotation row
- `selectedTenures` — int array; intersected with config `tenures` before use
- `ourServices` — free-text; defaults to config `ourServices` if absent
- `preparedByName`, `preparedByMobile` — persisted on the Quotation row

Return shapes:
- Success: `['success' => true, 'quotation' => Quotation]`
- Validation/error: `['error' => string]`
- PDF generated but DB failed: `['success' => false, 'error' => string, 'filename' => string]` (PDF still usable)

### `update(Quotation $quotation, array $input): array` (2026-05-07)

Same input shape as `generate()`. Replaces `quotation_banks` (cascades to `quotation_emi`) + `quotation_documents` wholesale, regenerates the PDF, deletes the previous cached file, returns `['success' => true, 'quotation' => Quotation]` or an `error` array. Re-checks `is_converted` inside the transaction to defend against the form being submitted *after* someone converted the quotation. Caller must enforce `Quotation::isEditableBy()` before invocation.

### `private updateBankCharges(array $banks): void`

Upserts `bank_charges` by `bank_name` with the last-used charge values for future pre-fill.

### Internal helpers

- `validateInput(array): ?string` — single source of truth for the validation rules above; reused by `generate()` and `update()`.
- `buildTemplateDataFromInput(array): array` — assembles the PDF payload + persists the **full** doc list (excluded rows included). Filters excluded docs into a separate `documents` array passed to the PDF service so they never render.
- `normaliseDocuments(array): array` — accepts both new shape `[{en, gu, excluded}]` and legacy `[{en, gu}]` (treated as included).
- `renderPdfOrSkip(array): array` — respects `app.skip_pdf_generation` dev flag.
- `persistBanksEmisDocuments(Quotation, array): void` — bulk insert helper used by both create + update.
- `cleanupOldPdf(?string $oldPath, ?string $oldFilename, ?string $newFilename): void` — unlinks the previous file when a quotation gets a new PDF or has its cache cleared.

---

## PdfGenerationService

Three-tier fallback:

1. If `app.pdf_use_microservice=true` → microservice only
2. Else try Chrome headless (if `isChromeAvailable()`) → fallback to microservice on failure
3. Else microservice only

| Method | Signature | Notes |
|---|---|---|
| `generate` | `(array $data): array` | Renders HTML via `renderHtml()`, writes it to `storage/app/tmp/pdf_{uniqid}.html`, produces PDF at `storage/app/pdfs/Loan_Proposal_{Name}_{date}_{time}.pdf`. Returns `['success' => true, 'filename' => ..., 'path' => ...]` or `['error' => string]`. |
| `renderHtml` | `(array $data): string` | Builds the full bilingual HTML document (fonts, colors, charges, EMI comparison, documents, notes). Called internally by `generate()`; also usable standalone for previews. |
| `getTypeLabel` | `(string $type): string` *(static)* | Bilingual customer-type label (e.g. `proprietor` → `Proprietor / માલિકી`). Returns the raw key if unknown. |

Config keys read:
- `app.pdf_use_microservice`
- `app.chrome_path` (auto-detected from common Win/Linux/macOS paths if empty)
- `app.pdf_service_url` (default `http://127.0.0.1:3000/pdf`)
- `app.pdf_service_key` (sent as `X-API-Key` if set)

Chrome command flags: `--headless --disable-gpu --no-sandbox --run-all-compositor-stages-before-draw --print-to-pdf=... --no-pdf-header-footer --user-data-dir=...`. Temp user-data dir is cleaned after each run.

---

## NumberToWordsService

Static-style helpers for Indian numbering + bilingual words.

| Method | Signature |
|---|---|
| `toEnglish` | `(int): string` — "Twelve Lakh ... Rupees" |
| `toGujarati` | `(int): string` — "... રૂપિયા" |
| `toBilingual` | `(int): string` — "English / Gujarati" |
| `formatIndianNumber` | `($num): string` — "12,34,567" |
| `formatCurrency` | `($num): string` — "₹ 12,34,567" |

---

## XlsxExportService

Self-contained `.xlsx` writer built on PHP's `ZipArchive` — **no composer
spreadsheet package**. Used by `ReportController@pipelineExport` /
`@loanReportExport` (2026-07-08). Single sheet, inline strings (no
sharedStrings part), bold header row, optional bold footer rows.

### `download(string $filename, array $headers, iterable $rows, array $columnTypes = [], array $footerRows = [], string $sheetName = 'Report'): BinaryFileResponse`

- `$rows`: arrays of cell values; `null`/`''` → empty cell.
- `$columnTypes`: column index → `TYPE_*` constant (default `TYPE_STRING`):
  - `TYPE_NUMBER` — raw numeric cell, `#,##0` display (amounts, day counts)
  - `TYPE_DECIMAL` — numeric cell, general format (keeps decimals, e.g. averages)
  - `TYPE_DATE` — real Excel date serial from a `Y-m-d(...)` string, shown `dd/mm/yyyy` (UTC-midnight parse, never TZ-shifted)
  - `TYPE_WRAP` — multi-line text, wrapped + top-aligned, wide column (stage lines)
- Escapes with `ENT_XML1` + strips XML-invalid control chars (prevents Excel
  "repair" prompts); sheet name sanitized to Excel's 31-char/no-`\/:*?[]` rule.
- Writes to a temp file, returns `response()->download(...)->deleteFileAfterSend(true)`.

---

## CustomerService

`app/Services/CustomerService.php` — customer identity by PAN + per-loan KYC snapshots. Master is created once per PAN and never updated; each loan gets a `customer_kyc_details` row. See `.docs/customers.md`.

- `normalizePan(?string): ?string`
- `resolveMasterByPan(array $kyc): Customer` — reuse by PAN or create master once
- `recordKyc(Customer, array $kyc, array $context): CustomerKycDetail`
- `captureForLoan(array $kyc, array $context): CustomerKycDetail` — resolve + record
- `syncLoanKyc(LoanDetail, array $kyc): CustomerKycDetail` — edit: update snapshot in place if PAN unchanged, else new master/snapshot
- `latestKycForPan(?string): ?CustomerKycDetail` — autofill lookup

## LoanPipelineBreakdownService

`app/Services/LoanPipelineBreakdownService.php` — feeds the dashboard "Stage status
breakdown" block (see `.docs/dashboard.md`). No constructor deps.

- `build(User $requester, string $period='30', ?int $userId=null, ?string $from=null, ?string $to=null): array` — `{range, blocks[]}`. One block per allowed scope (`own`/`branch`/`all`), or a single "Selected: <name>" block when a valid `userId` is picked. Each block → sections (sanction/technical/legal/disbursement) → bucket tiles `{key,label,count,amount,url}`.
- `loanIdsFor(User, string $scope, ?int $userId, string $period, string $section, string $bucket, ?string $from=null, ?string $to=null): array` — exact loan IDs for one bucket; powers the loan-list click-through (`LoanController@loanData` `whereIn`).
- `allowedScopes(User)`, `canFilterByUser(User)`, `userOptions(User)` — role/permission-driven UI metadata.
- **Classification** is single-pass in PHP by **precedence** (first match wins) so buckets are mutually exclusive within a section. Query bucket = active (`pending`/`responded`) `StageQuery` on that `stage_key`; Completed/Rejected beat Query. OTC bucket absorbs every completed loan; a `partial_disbursed` loan (fully disbursed but OTC pending, or still partial) has entries and is not completed, so it classifies under the **Cheque/Transfer Entry** bucket until it completes — no classifier change was needed for per-entry OTC (status-agnostic load + entries-based buckets). **Amounts**: `loan_amount` except Disbursement (Spill/Logged-in = `sanctioned_amount`; Cheque/Transfer + OTC = summed active `disbursement_entries`).
- Scope + selected-user **re-authorised server-side** (`view_all_loans` → `all`; BM/BDH → own+branch and only their branch users). Cohort = `created_at` window (preset days, all-time, or custom from/to). 60s per-block cache.

## LoanConversionService

Constructor: `LoanStageService`, `LoanDocumentService`, `CustomerService`.

### `convertFromQuotation(Quotation, int $bankIndex, array $extra = []): LoanDetail`

The transaction runs through `runWithLoanNumberRetry()` — retries up to 3× on a `loan_number` unique-constraint collision (concurrent conversions), so both succeed instead of one 500-ing.

Inside DB transaction:
1. Re-check the already-converted guard under `lockForUpdate()` (blocks double-submit conversion)
2. Resolve customer by PAN via `CustomerService::resolveMasterByPan` (reuse or create once — never updates an existing master), then `recordKyc()` and link `customer_kyc_details_id`
3. Build `LoanDetail` (status=active, current_stage=document_collection); sets `original_loan_amount = loan_amount` (as-applied snapshot; `createDirectLoan` does the same)
4. `generateLoanNumber()` → `SHF-YYYYMM-NNNN` (monthly max compared NUMERICALLY incl. `withTrashed()`; string sort would break past 9999)
5. Freeze `workflow_config` via `LoanStageService::buildWorkflowSnapshot()`
6. Populate documents via `LoanDocumentService::populateFromQuotation`
7. `initializeStages` → all stage_assignments
8. `autoCompleteStages(['inquiry','document_selection'])`
9. Auto-assign `document_collection` stage
10. Log `convert_quotation_to_loan` activity

### `createDirectLoan(array $data): LoanDetail`

Similar flow but starts at `inquiry`; documents pulled from `ConfigService` defaults by customer type.

---

## LoanStageService (workflow engine)

No injected deps. Talks directly to Stage, StageAssignment, StageTransfer, BankStageConfig, ProductStage, Bank, User, Branch, LoanProgress.

### Role resolution

| Method | Purpose |
|---|---|
| `getStageRoleEligibility(string): array` (static) | Reads `Stage.default_role` |
| `getAllStageRoleEligibility(): array` (static) | Cached map of all stages |
| `resolveStageRole(string, ?int $bankId): string` | bank override → stage default → `task_owner` |
| `resolvePhaseRole(string, int $phaseIndex, ?int $bankId): string` | bank override → `Stage.sub_actions[i].role` → `task_owner` |
| `buildWorkflowSnapshot(?bankId, ?productId, ?branchId, ?locationId): array` | Returns nested `{stage_key: {role, default_user_id, phases: {idx: {role, default_user_id}}}}`, frozen at loan creation |
| `getLoanStageRole(LoanDetail, string): string` | Reads from frozen snapshot, falls back to live |
| `getLoanPhaseRole(LoanDetail, string, int): string` | Same, for phases |
| `findUserForRole(string, LoanDetail, string, ?int $phaseIndex = null): ?int` | Snapshot default → role-specific resolution (task_owner → advisor/creator; bank_employee → bank default for city; office_employee → branch default) |

### Stage queries

`getOrderedStages()`, `getStageByKey($key)`, `getSubStages($parentKey)`, `isParallelStage($key)`, `getMainStageKeys()`.

### Initialization

- `initializeStages(LoanDetail)` — creates all `stage_assignments` + `loan_progress`
- `autoCompleteStages(LoanDetail, array $keys)` — bulk-completes given stages; used on conversion

### Transitions

- `updateStageStatus(LoanDetail, string, string, ?int $userId): StageAssignment` — validates via `StageAssignment::canTransitionTo()`, blocks on pending queries, runs `handleStageCompletion()` post-update
- `revertStageIfIncomplete(LoanDetail, string, bool $isStillComplete): bool` — soft-revert when collected data becomes incomplete; reverts subsequent stages too
- `getNextStage(string): ?string` — next main stage by sequence_order
- `canStartStage(LoanDetail, string): bool` — prerequisite checker — behavior branches on `app.open_rate_pf_parallel`; see Feature flag subsection below.
- `checkParallelCompletion(LoanDetail): bool` — marks `parallel_processing` parent complete when all its sub-stages are `completed`/`skipped`. Flag-off: auto-advances to `rate_pf` (assigns + starts it). Flag-on: calls `advanceToSanctionIfReady()`. Recalculates progress.
- `getParallelSubStages(LoanDetail): Collection` — returns all sub-stage assignments of `parallel_processing` with eager-loaded `stage` and `assignee`.
- `getLoanStageStatus(LoanDetail): Collection` — returns every `StageAssignment` for the loan with eager-loaded `stage`/`assignee`, sorted by `stage.sequence_order` (then `stage.id`). Used by stage UI to render in workflow order.

### `handleStageCompletion(LoanDetail, string)` (protected)

Orchestration logic:
- **app_number** done → start `bsm_osv` only
- **bsm_osv** done → start remaining parallel subs (legal_verification, technical_valuation, sanction_decision); if `config('app.open_rate_pf_parallel')` is truthy, also call `openRatePfInParallel()`
- All parallel subs done → mark `parallel_processing` complete; flag off → advance to `rate_pf`; flag on → call `advanceToSanctionIfReady()`
- **rate_pf** done (flag on only) → intercepted at top; call `advanceToSanctionIfReady()` and return
- **sanction** done → compute `expected_docket_date` from app_number stage notes (custom_docket_date OR docket_days_offset)
- **disbursement** done → normal sequential advance opens `otc_clearance`, **then calls `DisbursementService::syncDisbursementState($loan->refresh())`** (2026-10-15) so a loan whose tranches are already all OTC-settled completes immediately instead of stranding open (the generic complete / reset-re-advance path otherwise never re-invokes the resolver). Loan completion + per-entry OTC settlement are owned by `syncDisbursementState`, NOT here. The resolver is wrapped by a per-loan static re-entrancy guard (`syncDisbursementState` → private `resolveDisbursementState`) so this nested call can't double-fire the completion notification.
- **otc_clearance** done → no loan-completion side effect here (see `syncDisbursementState`).
- Sequential advance + auto-assign next stage otherwise

### Feature flag: `open_rate_pf_parallel`

| Method | Purpose |
|---|---|
| `usesParallelRatePf(): bool` (private) | Reads `config('app.open_rate_pf_parallel')` |
| `openRatePfInParallel(LoanDetail): void` (public) | After bsm_osv completes (flag on), marks `rate_pf` in_progress, auto-assigns via `getLoanStageRole` + `findUserForRole`, writes StageTransfer row |
| `advanceToSanctionIfReady(LoanDetail): void` (public) | Opens `sanction` only when BOTH `parallel_processing` and `rate_pf` are completed/skipped. Called from `handleStageCompletion('rate_pf')` and `checkParallelCompletion()` |

`canStartStage()` branches on the flag: `rate_pf` opens after `bsm_osv` when on (not gated by `is_sanctioned`); `sanction` gate requires both `parallel_processing` and `rate_pf` complete when on. Legacy behavior preserved when off.

### Assignment & transfer

- `assignStage(LoanDetail, string, int $userId)` — manual assign
- `skipStage(LoanDetail, string, ?int $userId)` — marks skipped
- `autoAssignStage(LoanDetail, string): ?StageAssignment` — uses `findBestAssignee()`
- `autoAssignParallelSubStages(LoanDetail)` — only starts `app_number` first; rest wait
- `findBestAssignee(stageKey, branchId, bankId, productId, creatorId, advisorId): ?int` — priority: product_stage_users → advisor → bank default per city → bank employee per branch → any bank employee → default OE for branch → creator → fallback role match
- `transferStage(LoanDetail, string, int $toUserId, ?string $reason)` — updates assignment, creates StageTransfer. **Throws `RuntimeException` if the stage has an open query (`hasPendingQueries()`)** — a stage cannot be handed off until its query is resolved (mirrors the completion guard; single chokepoint for the generic transfer, all multi-phase `send_*` actions, and escalation). Controllers return a clean 422.

### Config propagation (2026-08-31)

Pushes admin stage/task-owner config edits onto in-flight loans instead of only new ones.

- `propagateConfigToEligibleLoans(?int $productId, ?int $bankId = null): array` — for each **eligible** loan (`where('status', '!=', STATUS_COMPLETED)` — i.e. everything except completed: active, on_hold, rejected, cancelled, disbursed-but-open all qualify; scoped by product, else bank, else all): rebuild `workflow_config` via `buildWorkflowSnapshot`, then re-point every **in_progress** stage owner that is **still on its old auto-resolved default** to the new default (phase-aware). Manual transfers are preserved (skip when current assignee ≠ old default). Chunked (100). Returns `{loans_processed, stages_reassigned}`. Called from `WorkflowConfigController::saveProductStages` (product-scoped), `LoanSettingsController::saveMasterStages` (all eligible), and `propagateConfigToAllEligibleLoans` (Sync Settings).
- `propagateConfigToAllEligibleLoans(): array` — loops every `Product`, calls the above per product. Returns `{products, loans_processed, stages_reassigned}`. Backs the **"Sync Settings"** button (`WorkflowConfigController::syncStageConfig`).
- Private helpers: `resyncLoanAssignments(LoanDetail): int` (per-loan engine — computes OLD assignees before rebuild, NEW after), `resyncPhaseIndex(StageAssignment): ?int` (0-based sub_actions index from phase notes; `-1` = indeterminate → skip), `reassignForConfigChange(LoanDetail, StageAssignment, int)` (sets `assigned_to`, writes StageTransfer `auto` with real old→new, re-points open queries, logs `config_reassign_stage`, notifies new owner in try/catch).

### Rejection

`rejectLoan(LoanDetail, string $stageKey, string $reason, ?int $userId): LoanDetail` — rejects only the named stage: sets loan status=rejected, writes `rejected_at`/`rejected_by`/`rejected_stage`/`rejection_reason`, closes that one stage assignment (saves `previous_status` then sets `status=rejected`). Sibling rejection for parallel-mode flows lives in `LoanStageController::sanctionDecisionAction` (lines 835-845), which bulk-updates pending/in_progress stages and saves `previous_status` for reactivation.

### Progress

`recalculateProgress(LoanDetail): LoanProgress` — rebuilds counts + workflow_snapshot. Uses `LoanProgress::firstOrCreate` (not `$loan->progress ?? create`) so repeated calls in one request (e.g. completing two stages then re-syncing) can't double-insert against a cached-null relation and hit the `loan_progress.loan_id` unique index.

### Stage reset

`resetToStage(LoanDetail, string $stageKey, ?int $phase = null, ?string $variant = null): array` — rewinds a loan to `$stageKey`: target → `in_progress` (assignee via `resolveResetUsers`), all later stages → `pending`, re-opens `parallel_processing` when the target is a sub-stage, clears dependent data (disbursement/valuation rows, `application_number`, `expected_docket_date`, `is_sanctioned`, `disbursed_amount`, each only when the target is at/before its producing stage), then `recalculateProgress`. Status resets to `active` (reverting `partial_disbursed`); the disbursement row + its `disbursement_entries` (incl. per-entry OTC) + `completion_intent` are cascade-deleted. Phased stages default to entry phase 1. Returns log lines. Destructive/irreversible. Shared by the `loan:set-stage` command and the permission-gated `LoanStageController@resetStage` web action (`User::canResetLoanStages()` → `hasPermission('reset_loan_stages')`).

`resolveResetUsers(LoanDetail): array` — `{task_owner, bank_employee, office_employee, branch_manager, bdh}` default user IDs for the loan (product-stage → bank/branch default → any active fallback).

---

## LoanDocumentService

Constructor: `ConfigService`.

| Method | Purpose |
|---|---|
| `populateFromQuotation(LoanDetail, Quotation)` | Copies quotation documents as pending |
| `populateFromDefaults(LoanDetail)` | Reads config `documents_en` / `documents_gu` by customer_type |
| `updateStatus(LoanDocument, string $status, int $userId, ?string $rejectedReason)` | pending / received / rejected / waived; sets received_date/by when received |
| `getProgress(LoanDetail): array` | `{total, resolved, received, rejected, pending, percentage}` |
| `allRequiredResolved(LoanDetail): bool` | Gate for auto-completing document_collection stage |
| `addDocument(LoanDetail, string $en, ?string $gu, bool $required = true): LoanDocument` | Adds custom doc with next sort_order |
| `removeDocument(LoanDocument)` | Deletes file too |
| `uploadFile(LoanDocument, UploadedFile, int $userId): LoanDocument` | Stores under `loan-documents/{loanId}/` via `FileUploadService::hashedFilename($file)` (random hash + ext) on the `local` disk; records `file_path`, `file_name` (original), `file_size`, `file_mime`, `uploaded_by`, `uploaded_at`; auto-marks document received if still pending |
| `deleteFile(LoanDocument)` | Removes file only; keeps record |

---

## DisbursementService

Constructor: `LoanStageService`.

**Model (per-entry OTC + `partial_disbursed`, 2026-10-05).** Disbursement is multi-tranche over time. **Only cheque tranches carry an OTC handover** — `otc_status` ∈ `pending` (default) | `cleared` (with `otc_handover_date`/`otc_cleared_by`/`otc_cleared_at`/`otc_remarks`) | `skipped`. A **fund-transfer (NEFT/RTGS) tranche has no instrument to hand over**, so `otcAttrs()` forces its `otc_status` to `skipped` on save and sets its **`otc_handover_date` = `transfer_date` (fallback `disbursement_date`)** — i.e. NEFT's settlement date = its transfer date — while `DisbursementEntry::isOtcSettled()` always returns true for non-cheque methods (a NEFT never blocks `otc_clearance` / completion). The disbursement form offers **Skip only for NEFT** and **Pending/Cleared only for cheque** (no Skip for cheques). **`otc_handover_date` is the settlement-date basis** used by the **payout engine** + **reconcile**. The **reports** (loan report, management) show BOTH: **Disbursed** = all money out (windowed by `disbursement_date`) and **Settled** = the OTC-settled portion (`Σ amount where otc_handover_date NOT NULL`); the gap (Disbursed − Settled) = **money awaiting OTC clearance**. Dashboard "Stage status breakdown" buckets: **Awaiting OTC Clearance** (partial loan with an unsettled cheque — its tile **amount = only the still-pending money**; the loan's already-settled tranches fold into Partially Completed), **Partially Completed** (settled money, more to disburse), **OTC Cleared** (done); Total Disbursed = sum of the three = all disbursed money (reconciles with the report's Disbursed, and Awaiting OTC = the report's Pending OTC exactly). The Pipeline report + export carry a **Settled (OTC)** column beside Disbursed too. A tranche is **settled** when cleared or skipped (cheques) or always (fund transfers). The loan is monotonic: `active` → `partial_disbursed` (first entry) → `completed` (fully disbursed AND every active tranche settled). **Over-disbursement is allowed** (cumulative may exceed the sanctioned target; only the 1e11 per-entry sanity cap applies). `disbursement_details.completion_intent` (`open`|`full`) latches an explicit "fully disbursed" declaration.

### `processDisbursement(LoanDetail, array $data): DisbursementDetail`

`$data = ['entries' => [...tranches...], 'notes' => ?string]` — each tranche: `disbursement_date` (Y-m-d), `method` (fund_transfer|cheque), `product_id` + `product_name` (snapshotted by controller), `loan_account_number`, `amount` (net transferred), per-tranche charges `pf_amount` / `admin_charges` / `insurance_amount` (default 0), cheque fields on cheque tranches (`cheque_name`/`cheque_number`/`cheque_date`), `transfer_date` (Y-m-d) on **NEFT** tranches (the transfer-done date; controller defaults it to `disbursement_date` when blank, null for cheques), and per-entry OTC (`otc_status`, `otc_handover_date` (Y-m-d), `otc_remarks`). The disbursement form defaults a NEFT row's OTC to **skipped** (and the Transfer Date to the entry Date); the backend `otcAttrs()` enforces skipped for every non-cheque tranche regardless. Inside DB transaction:
1. Upsert `disbursement_details` with `entries` + derived legacy columns (`disbursement_type` = 'cheque' if any cheque entry, `disbursement_date` = latest entry date, `amount_disbursed` = **gross** (net + pf + admin; insurance excluded), header `pf_amount`/`admin_charges`/`insurance_amount` = the **one-time** charge values (header-level, taken from the top-level payload — not per tranche), `bank_account_number` = first entry's account). **Charge lock (2026-10-09):** when `!$allowReopen` and the existing header is `chargesLocked()` (any entry OTC-settled — cleared cheque or auto-skipped NEFT), the posted pf/admin/insurance are **ignored and the stored values retained** (so charges attach to the first disbursement only and can't be re-edited on later partial saves; the super-admin correction tool passes `allowReopen: true` to bypass). The controller surfaces the same flag as `$chargesLocked` to render the three charge inputs readonly.
1b. `syncEntryRows()` — mirror tranches into `disbursement_entries` (incl. per-entry OTC via `otcAttrs()`, which preserves an existing clear timestamp/author when the status stays `cleared`): posted `row_id` → update in place; missing/foreign → insert; live rows absent → soft delete. `is_active` from loan status. row_ids written back into json.
2. Mirror the **gross** total to `loan_details.disbursed_amount` on EVERY save.
3. `syncDisbursementState()` resolves status + stage completion (no more auto-complete-at-target here).
4. Log activity (`process_disbursement`; props: `loan_number`, `type`, `amount`, `entry_count`, `loan_status`).

### `syncDisbursementState(LoanDetail, bool $allowReopen = false): void`

**Single authority** for the disbursement/OTC lifecycle; called after every mutation (save, per-entry OTC, mark-full). **Completed is terminal — returns immediately (never downgrades/reopens).** Then: `cumulative` = Σ active tranche net amounts (used only for started/zero detection); **`gross` = cumulative + Σ pf_amount + Σ admin_charges** (insurance excluded); `moneyDone` = `gross ≥ disbursementTarget()` OR `completion_intent === full`; `allSettled` = every active tranche settled (cheques cleared/skipped; fund transfers always). Resolution (**2026-10-28**): `cumulative==0` → revert `partial_*`→`active`; otherwise **always complete the `disbursement` stage** (partial or full; opens `otc_clearance`); `!allSettled` → **reopen `otc_clearance` if it was completed** (new pending cheque; re-notifies its assignee via `notifyStageAssignment`) / keep it in progress, loan `partial_disbursed`; `allSettled` → complete `otc_clearance`, then `!moneyDone` → loan **`partial_completed`**, `moneyDone` → `completed` + notify. Completing a stage routes through `updateStageStatus` (query-block + transition rules honored), so an unresolved query leaves the loan partial. **Guard:** after attempting to close `disbursement`, if it did NOT reach `completed` (e.g. an open query blocked it), it returns early WITHOUT advancing `otc_clearance`/loan status — avoids a loan showing `completed` while `disbursement` is still `in_progress`.

### `recordEntryOtc(DisbursementEntry, string $status, ?string $handoverDate, ?string $remarks): void`

Sets one tranche's OTC state (cleared/skipped/pending), **also mirrors it into the matching `disbursement_details.entries` json item (by `row_id`)** so the stage cards don't show a stale "Pending" (2026-10-28), then re-runs `syncDisbursementState`. Exposed as POST `loans.disbursement.entry.otc` (used by the disbursement page and the OTC stage panel). Logs `record_entry_otc`.

### `markFullyDisbursed(LoanDetail): void`

Latches `completion_intent = full` (intentional under-disbursement, below target), **sets `loan_amount` = `savedGross()` (Σ entry rows + PF + admin)** keeping `original_loan_amount` (backfilled when null) and logging `change_loan_amount` (source `mark_fully_disbursed`), then syncs. The loan still completes only once every tranche is OTC-settled. Requires saved entries. Logs `mark_fully_disbursed`. Exposed as POST `loans.disbursement.complete`. `savedGross(DisbursementDetail): int` is public.

### `disbursementTarget(LoanDetail): int`

Fully-disbursed threshold: `loan_details.sanctioned_amount` → docket notes `sanctioned_amount` → sanction notes → `loan_amount`.

> **Sanctioned/disbursed amount columns**: `loan_details.sanctioned_amount` and `disbursed_amount` are real columns kept in sync at write time — sanctioned via `LoanStageController::saveNotes()` (docket stage; sanction stage fills only when empty), disbursed via `processDisbursement` above. Listings read the columns directly instead of parsing `stage_assignments.notes` JSON.

---

## FileUploadService

Central upload validation + filename sanitization. All methods are `public static`; no constructor. Returns sanitized hashed filenames so client-supplied names never touch disk.

| Method | Signature | Notes |
|---|---|---|
| `rules` | `(bool $required = true): array` | Returns Laravel validator rules: `['required'\|'nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp', 'mimetypes:application/pdf,image/jpeg,image/png,image/webp']`. Both `mimes` (extension) and `mimetypes` (content) checks are enforced — extension alone is spoofable. |
| `messages` | `(): array` | Custom error messages for `file.mimes` / `file.mimetypes` / `file.max`. |
| `hashedFilename` | `(UploadedFile $file): string` | Returns `"{bin2hex(random_bytes(16))}.{ext}"`. Extension is lowercased and whitelist-checked; unknown extensions collapse to `bin`. Does **not** write the file — caller uses `$file->storeAs($dir, $name, 'local')`. |

### Constants

- `MAX_SIZE_KB = 10240` (10 MB)
- `ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp']`
- `ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']`

### Storage

Consumers (e.g. `LoanDocumentService::uploadFile`) persist to the `local` disk, which resolves to `storage/app/private/` under the Laravel 11+ default filesystem config. The service itself is storage-agnostic — it only vends filenames + validation rules.

---

## StageQueryService

| Method | Purpose |
|---|---|
| `raiseQuery(StageAssignment, string $text, int $userId): StageQuery` | Creates query (status=pending), persists `assigned_to_user_id`, fans out notifications to recipient + advisor (deduped, raiser skipped) |
| `resolveQueryRecipient(LoanDetail, StageAssignment, int $raiserId): ?int` | Pure routing helper — returns the user id to assign the query to |
| `respondToQuery(StageQuery, string $text, int $userId): QueryResponse` | Appends response, sets query status=responded; notifies raiser |
| `resolveQuery(StageQuery, int $userId): StageQuery` | status=resolved + timestamps; logs `resolve_query` activity; notifies the raiser when someone else resolves (try/catch-wrapped) |
| `getQueriesForStage(StageAssignment): Collection` | All queries for a stage assignment |

### Query routing rules (2026-05-07)

Queries are escalated to internal SHF roles only — never routed to a `bank_employee`, even when bank_employee currently owns the active phase.

- Default recipient = `loan.assigned_advisor` (fallback `loan.created_by`).
- Bank-side raiser hitting an office-side assignment → if `StageAssignment.assigned_to` user holds the `office_employee` role, recipient = that user. Fallback to advisor if no office_employee is currently attached.
- Self-raise (raiser is advisor) is allowed silently.
- Notification fan-out: notify recipient + `loan.assigned_advisor`. Dedupe if same user. Skip the raiser themselves so users aren't pinged about their own actions. Wrapped in try/catch so Web Push failures never bubble up (lessons.md 2026-04-18 rule).
- Persisted on the row (`stage_queries.assigned_to_user_id`, indexed with `status`) so dashboards can filter "assigned to me" without re-resolving.

Pending/responded queries **block stage completion** via a check inside `LoanStageService::updateStageStatus()`.

### Query resolve authorization (2026-07-07)

A non-resolved query (`pending` or `responded` — response not required) can be resolved by:

- the **raiser** (`stage_queries.raised_by`), or
- the **current assignee** of the query's stage (`StageAssignment.assigned_to`), or
- **admin / super_admin**.

Enforced in `LoanStageController::resolveQuery()` (403 otherwise; 422 if already resolved) and mirrored by the Resolve button conditions in `loans/_stages-body.blade.php` (sub-stage + main-stage sites). The current-assignee-can-resolve rule lets whoever holds the stage close an open query. **A stage with an open query cannot be transferred or handed off** (`transferStage()` throws; see above) — the assignee must resolve the query first, both to complete AND to transfer.

---

## RemarkService

| Method | Purpose |
|---|---|
| `addRemark(int $loanId, int $userId, string $remark, ?string $stageKey = null): Remark` | Logs activity w/ preview |
| `getRemarks(int $loanId, ?string $stageKey = null): Collection` | If stageKey set, filters `stage_key = $key OR NULL` (general + stage) |

---

## NotificationService

| Method | Purpose |
|---|---|
| `notify(int $userId, string $title, string $msg, string $type='info', ?int $loanId, ?string $stageKey, ?string $link): ShfNotification` | Generic. Auto-fallback: if `$link` is null and `$loanId` is passed, the link is resolved to `route('loans.stages', $loanId)` (wrapped in try/catch — stays null if route is unavailable). Callers can still pass an explicit `$link` to override (e.g. general-tasks point to the task page). |
| `notifyStageAssignment(LoanDetail, string $stageKey, int $userId): ShfNotification` | Title `"Stage Assigned"`, message `"You have been assigned to '{stageName}' for Loan #{loan_number} ({customer_name})"`, type `assignment`, link `route('loans.stages', $loan)`. `{stageName}` is `Stage.stage_name_en` (falls back to `stageKey`). |
| `notifyStageCompleted(LoanDetail, string): void` | Sent to creator + advisor (excluding current user) |
| `notifyLoanCompleted(LoanDetail): void` | Same audience |
| `markRead(ShfNotification): void` | |
| `markAllRead(int $userId): void` | |
| `getUnreadCount(int $userId): int` | |

UI polls `/api/notifications/count` every 60s (see `layouts/app.blade.php`).

### Push delivery on notification create

`ShfNotification::booted()` → `created()` fans a new in-app notification out to native push channels, each wrapped in try/catch + `Log::warning` so a push failure never bubbles into the request that created the row:
1. **Web Push** — `$user->notify(new ShfPushNotification($notification))` (browser/PWA). **Skipped when the user has a registered FCM device token** (i.e. the native app is installed) so they aren't notified twice on one device. Toggle with `config('app.prefer_native_push')` (env `PREFER_NATIVE_PUSH`, default true) — set false to always send both.
2. **FCM** — dispatches the queued `SendFcmPush` job (only when `FcmService::isConfigured()`), so the outbound FCM HTTP calls run on the queue worker, never in the web request. The job (`tries=3`, `backoff=10`) reloads the notification by id and calls `FcmService::sendForNotification()`. Requires a running `queue:work` (prod uses the `database` driver; tests run `sync`).

## FcmService

Sends Firebase Cloud Messaging (FCM v1) pushes to a user's registered devices (`device_tokens`). Authenticates to the FCM v1 HTTP API by minting a short-lived OAuth2 access token from the service-account key via a signed JWT (RS256, `openssl_sign`) — no external SDK. All paths are best-effort (log, never throw).

| Method | Purpose |
|---|---|
| `isConfigured(): bool` | True when `services.fcm.credentials` file exists + `services.fcm.project_id` set. |
| `sendForNotification(ShfNotification): void` | No-op if unconfigured or recipient has no devices. Loops the user's `DeviceToken`s and sends one FCM v1 message each. |

Per-device message: `notification{title,body}`, `android.notification.channel_id = shf_sound_<sound>` (the device's `sound` preset; `shf_default` if unknown), `apns.payload.aps.sound = <resource>.caf`, and `data{url,sound,title,body}` (url = `notification->link` ?? `/dashboard`). Sound keys map smooth/cyan/luster/mario/classic → resource names (matches the Flutter app). The OAuth token is cached (`fcm_access_token`, ~55 min; only successful tokens cached). A 404/`UNREGISTERED`/`INVALID_ARGUMENT` response prunes the dead `DeviceToken` row.

Config: `config/services.php` → `fcm.project_id` (default `shfworld-loans`), `fcm.credentials` (default `storage/app/firebase/service-account.json`, gitignored). The native bridge (`native-bridge.js`) registers tokens via `POST /api/device/register` → `DeviceTokenController`.

`sendForNotification()` returns diagnostics `{configured, devices, token_ok, results[]}` (each result `{token, status, ok, pruned, error}`); the queued job ignores it. **Debug command** `php artisan fcm:test --user=<id>` sends directly through the service (bypassing the queue) and prints the per-device FCM HTTP status/error — use it to diagnose on the server. `notifications:test --user=<id>` instead exercises the full create→queue→send path.

### Daily reminders

`reminders:send-daily --when=morning|evening` (Artisan command `SendDailyReminders`) iterates users with pending work for today (morning, scheduled 08:00) or tomorrow (evening, scheduled 20:00):
- DVR follow-ups: `follow_up_needed=true`, `is_follow_up_done=false`, `follow_up_date = targetDate`, grouped by `user_id`
- General tasks: `status IN (pending, in_progress)`, `due_date = targetDate`, grouped by `assigned_to`

Users with zero in both buckets get no notification. Each recipient gets one in-app `ShfNotification` with a combined count ("You have N DVR follow-ups and M tasks due today/tomorrow.").

---

## LoanTimelineService

### `getTimeline(LoanDetail): Collection`

Merges 9+ event types into a single chronological collection (each entry: `{type, date, title, description, user, icon, color}`):
- `quotation_created` (if converted)
- `converted` (if from quotation — "Converted to Loan")
- `loan_created` (if direct)
- `stage_started` / `stage_completed` / `stage_skipped` (from `stage_assignments`)
- `transfer` (from `stage_transfers`)
- `query_raised` / `query_response` (from `stage_queries` + their responses)
- `remark` (from `remarks`)
- `rejected` (if loan status=rejected — "Loan Rejected")
- `disbursement` (if disbursement row exists — "Disbursement Processed")
- `completed` (if loan status=completed — "Loan Completed")

---

## Conventions

- **Transactions**: `convertFromQuotation`, `createDirectLoan`, `processDisbursement`, `generate` (DB-save phase) — wrapped in `DB::transaction`. Loan creation goes through `runWithLoanNumberRetry()` (retries on `loan_number` collisions).
- **Create-failure contract**: `QuotationService::generate` returns `['success'=>true,'quotation'=>…]` only when the row persists; on any failure it returns `['success'=>false,'error'=>…]`. `QuotationController::generate` returns **422 on any non-success** — never a success response for an unsaved quotation (a rendered PDF alone is not a saved quotation).
- **Activity logs**: services log via `ActivityLog::log($action, $subject, $properties)` after write.
- **Notifications**: sent inside the same request; no queue.
- **Cache invalidation**: `PermissionService` caches are the only service-level cache; `Role::clearAdvisorCache()` for advisor-eligible lookups.
- **Validation**: services trust inputs validated by controllers; `QuotationService::generate` is the only exception — it re-validates because it's also called by the offline sync API.

## DisbursementDataService (super_admin correction tool)

`exportData()` → one row per active disbursed `disbursement_entries` tranche: keys `Loan ID`, `Entry ID`, refs (`Loan Number`/`Application Number`/`Customer`), editable `Bank`, `Product`, `Sanctioned Amount`, `Method`, `Amount`, `Cheque No`, `Disbursement Date`, `OTC Clearance` (Yes/No/Skip), `OTC Clearance Date`.

`preview($rows)` / `apply($rows, $actor)` share `plan()`: group rows by loan (keyed by Entry ID), validate, build the FULL entries payload (every active entry preserved so none soft-delete), apply edits, resolve bank (loan-level, rows must agree) + product (within bank) + OTC mapping (Yes→cleared+date, Skip→skipped, No/blank→pending). `apply()` commits per loan in a transaction: loan-level updates (bank_id/product_id/sanctioned_amount) then `DisbursementService::processDisbursement()` to rewrite JSON + mirror + amounts and re-resolve status/stages. Unknown/inactive Entry IDs and inconsistent banks are skipped + reported; completed loans keep their terminal status (processDisbursement guard). Logs `import_disbursement_data`. Columns include per-tranche **PF Amount / Admin Charges / Insurance Amount** and an **Action** (Keep/Delete — delete excludes the tranche so `processDisbursement` soft-deletes it; blocked when the tranche is payout-finalized — `payout_run_id` (new aggregate runs) **or** legacy `loan_payout_id`). A **Transfer Date** column (NEFT settlement date): on import, NEFT rows set `transfer_date` (defaulting to Disbursement Date) and `otc_handover_date = transfer_date` with `otc_status=skipped`; cheque rows use OTC Clearance = Yes + OTC Clearance Date → `otc_handover_date`. Loan-level **Loan Advisor** + **Payout User** columns (2026-10-07, written on each loan's first row; blank = keep) resolve by exact `LOWER(name)` — not-found/ambiguous keeps the existing value + reports an error — and update `assigned_advisor` / `payout_user_id`. **Payout User** additionally rejects any name whose user holds a payout-ineligible role (`User::PAYOUT_INELIGIBLE_ROLES` = super_admin / admin / bank_employee / office_employee), keeping the existing value + reporting an error. A read-only **Payout Run** column shows the finalized run # per tranche (ignored on import). Date columns (Disbursement Date / OTC Clearance Date) export as plain `d-m-Y` **text** (not Excel date serials) so they read back as `07-10-2026` in every viewer and round-trip cleanly; `parseDate` also accepts `Y-m-d` / `d/m/Y` / serials on import. `Loan ID` / `Entry ID` export as real numbers (`TYPE_DECIMAL`, General format — no comma grouping, no "number stored as text" flag). Each loan's **first tranche row** is visually marked via `exportData()['section_rows']` (0-based ordinals) passed to `XlsxExportService::download($...,$sectionStartRows)`, which draws an accent top border + light fill across the full row (purely cosmetic — the importer ignores cell styles, so it never affects reading). `apply()` calls `processDisbursement(..., allowReopen: true)` so status is re-resolved from the corrected totals INCLUDING downgrading a wrongly-completed loan (reopens disbursement/OTC stages). `preview()` returns a structured before/after per field (`preview_entries[].fields[] = {label, old, new, changed}` + `loan_diff` for Bank/Product/Sanctioned/Status + a predicted status) that the screen renders with changed cells in red.

## PayoutRunService (aggregate payout engine — 2026-10-07 redesign)

The system of record for payouts. Payouts are computed over a date range, **scoped to one bank and a chosen set of products** (both compulsory in the UI). Controller: `PayoutRunController` (`payouts.runs`, `payouts.runs.finalize`, `payouts.runs.show`); views `newtheme/payouts/runs.blade.php` + `run-show.blade.php` + partial `_product-filter.blade.php` (Select2 multi-select, bank-dependent).

- `previewRun(string $from, string $to, ?int $bankId = null, array $productIds = []): array` → `computeRun()`. Scope = active `disbursement_entries` with `payout_run_id IS NULL`, **`otc_handover_date ∈ [from,to]`** (the **settlement date**: NEFT = transfer date via `otcAttrs()`, cheque = cleared date; un-cleared cheques are excluded until cleared), **and the loan's `bank_id`/`product_id`** (when given). (eager-loads `loan.product.bank`, `loan.payoutUser.roles`, `loan.disbursement`.)
- `finalizeRun(string $from, string $to, User $actor, ?int $bankId = null, array $productIds = []): PayoutRun` — in a `DB::transaction`, writes `payout_runs` (incl. `bank_id`) + `payout_run_products` + `payout_run_lines` + `payout_run_users`, stamps coverage (`disbursement_entries.payout_run_id`/`paid_amount_counted`, `disbursement_details.pf_payout_run_id`/`insurance_payout_run_id`), logs `finalize_payout_run`. **Bank + ≥1 product required** (`PayoutRunController` validates `bank_id` exists + `product_ids` required|array|min:1).
- **Idempotent + non-destructive subset runs**: because coverage is per-tranche (`payout_run_id IS NULL`), a run for a subset of products stamps only its own tranches; a later run for a different/single product picks up only its still-uncovered tranches and **never touches other products' data**.
- **Amount-based products**: per-product in-range **volume** = Σ `amount` (all users) **+ each loan's one-time PF + admin AS ENTERED** (`PayoutService::oneTimeChargesBase`, once per loan with its first paid tranche — entries are ordered by transaction date; stamped `disbursement_details.charges_payout_run_id` on finalize, 2026-10-28) → `matchSlab($version, $volume)` picks the tier; each user's `commission = applyRate(tierRate, userVolume, cap)`. `paid_amount_counted` = tranche + carried charges.
- **PF-based products**: excluded from volume, **no admin**. `pf_base = PayoutService::pfBase()` = `exGst(pf_amount, pf_gst)` — PF entered brought back to excluding GST (matches the bank's `pf_amount`), one-time per loan via `pfLoansSeen`, aggregated → PF slab → rate; `pf_payout` to the loan's payout user only.
- **Insurance** (2026-10-28: **information only**): `round(insurance × rates['insurance'])` shown per user/loan as "Insurance payout (info only)", once per loan (`insLoansSeen`, still stamped), **not added to the total**.
- **Per user**: `total = Σcommission + Σpf_payout`; `tds = round(total × rates['tds'])`; `net = max(0, total − tds)`. No GST maths anywhere. Connector payout user → the slab's connector rate. `max_payout` caps each (user×product); `-1`/null = uncapped.
- `matchSlab($version, int $base)` — smallest `high_amount ≥ base` (fallback largest), matching the old engine. `applyRate($type,$value,$base,?$cap)` — percent `round(base×value/100)` or fixed, `min` with a positive cap. Rates/version resolved **as of the range END date** via `PayoutConfigService`. Snapshots everything onto the run tables so reports reproduce stable figures after config changes.
- **Base amounts surfaced (2026-10-19)**: `computeRun()` returns `loan_lines` (one per loan×user×product: `volume` = disbursed, `pf_base` = PF ex-GST counted, `insurance_base`, `settlement_date`, `payout` = gross pre-cap share) and the per-user rollup carries `total_volume`/`total_pf_base`/`total_insurance_base` next to the payout totals. The preview shows a **"Loans considered" card grouped user → product** plus the base columns on the per-user table; `PayoutRunController::show()` re-derives the same for a finalized run (loan list from stamped entries + the run's gst snapshot; per-user bases from the stored lines).

**Reconcile product scope (`PayoutController`)**: `ourEntries(int $bankId, string $from, string $to, array $productIds = [])` filters by the loan's `product_id` too (and now selects `loan_number` + `application_number`); `reconcile()` requires `bank_id` + `product_ids` (array, min:1). The screen reuses the same `_product-filter.blade.php` Select2.

**A/c → Stages links (2026-10-28):** reconcile + payout-run views render A/c numbers and Loan #s via `payouts/_acc-link.blade.php` (link to `loans.stages`, new tab); `PayoutRunController::show()` loan lines include `loan_id`.

**Reconcile product check + one-click correction (2026-10-28)** — supersedes the column list below: `COLUMNS` = `application_number, loan_acc_no, customer_name, product, cheque_number, disbursement_date, disbursed_amount, pf_amount, insurance_amount`. **`loan_amount` renamed `disbursed_amount`** (an old file's `loan_amount` header is still read as `disbursed_amount`). **`product` is a check, not a match key**: `productCheck($excelName, $ourProduct, $bankProducts)` → `ok` (bank name = our product's name / code / `bank_aliases`, via `Product::matchesBankName()` — upper-cased, letters+digits only) | `mismatch` (= exactly one OTHER product of the same bank; `target_id`/`target_name`) | `unknown` (none of the bank's products) | `blank` (empty / old file, not checked). Mismatch/unknown set `flags.product` and count as a discrepancy; result has `product_issue_count`. `dbIdentifierIndex($bankId)` is now **bank-wide (every product)** and carries `product_id`, so a loan logged under a non-selected product is found: `mismatchReason()` category **`other_product`** ("Logged under X — not one of the selected products"), placed after `difference`/`status`. Each `excel_in_db` row carries `product` too. **`changeLoanProduct(LoanDetail)`** (`POST payouts/reconcile/loans/{loan}/product`, `finalize_payout`, JSON): new product must be the same bank and differ; **refused (422)** when any tranche has `payout_run_id`/`loan_payout_id`, a `loan_payouts` row exists, or the header has `pf_/insurance_/charges_payout_run_id`; in a transaction updates `loan_details.product_id`, active `disbursement_entries.product_id/product_name`, and the `disbursement_details.entries` JSON; logs `change_loan_product` (`source: reconcile`, old/new product). Workflow stages are not rebuilt. **Kept upload**: `reconcile()` stores a fresh upload at `storage/app/private/reconcile-uploads/{userId}_{uuid}.xlsx` (`keepUpload()`, files older than 24h purged on each upload) and returns `upload_token`; posting `upload_token` instead of `file` re-runs on it (`keptUploadPath()`; missing → redirect "expired, upload again"). The page's hidden `#rcRerunForm` is auto-submitted after a successful product change so tiers/payouts refresh. View partial `payouts/_product-check.blade.php`.

**Reconcile rules (2026-10-28)** — supersede the column list + amount flag below: `COLUMNS` = `application_number, loan_acc_no, customer_name, cheque_number, disbursement_date, loan_amount, pf_amount, insurance_amount` (**`admin_charges` removed**; an old file carrying it is read fine, column ignored). Carrier = each loan's **first settled active tranche overall** (`carrierEntryIds()`), so a later period never re-counts one-time parts. Compared figure (`loanSide()['amount']`): normal product = in-window Σ tranches + PF + admin (as entered; only when the carrier is in the window) vs Excel `loan_amount`; **PF-based** = PF excluding GST (carrier only) vs Excel `pf_amount`, **±₹1 = match** (`PF_TOLERANCE`); `$ex['_compare']` carries the Excel figure used. **Commission uses the Payout Run tier (2026-10-28, user decision):** `productTiers()` sums every in-window loan's payout base per product (all users, matched or not) and picks the slab via the now-public `PayoutRunService::matchSlab()` on the version in force at the window END date (rates likewise as of the end date); `loanCalc()` = tier rate × the loan's base (entries + PF + admin, or PF excl. GST) — identical to the run's per-loan `payout` (pre-cap; the run caps per user × product). Result carries `tiers` (shown as a "Product tiers" card). The old per-tranche `entryCalc()` (slab from each tranche alone) is removed. Then `PayoutService::breakdown($commission, $insurance, $rates)` — **no GST deduction**, insurance payout info only, TDS on the commission total. `dbIdentifierIndex()` reports the same compared figure for the mismatch table.

**Reconcile columns + matching (2026-10-19, loan-grain rewrite)**: `PayoutController::COLUMNS` (the download-template / upload contract) = `loan_acc_no, customer_name, cheque_number, disbursement_date, loan_amount, pf_amount, admin_charges, insurance_amount, application_number`. **Both `loan_acc_no` and `application_number` are identifiers** — the uploaded row is indexed under both, and a loan matches on either its A/c number(s) OR its application number (then name), from either side; the exists-in-DB lookup for unmatched rows also checks the row's application number. The bank files **one row per loan**; we store **one row per tranche** — so `reconcile()` reconciles at the **loan grain**:
- **Groups** our in-window active tranches by loan (`loanSide()` → distinct A/c #s, Σ disbursed, tranche count/amounts, distinct settlement dates, DB insurance).
- **Matches by IDENTIFIER ONLY** — each loan's A/c # → `application_number` → customer name (normalized via `normId()`/`normName()`; `normId` keeps only `[A-Z0-9]` so stray spaces/hyphens/typos still match, and treats empty/all-zero as "no id"). Amount + date are **no longer part of the match** — they're compared *after* and shown side-by-side. First unconsumed Excel row wins. This fixes the old bug where any amount/date difference split one loan into both "DB-only" and "Excel-only".
- **Per matched loan**: `loanCalc()` sums the per-tranche `entryCalc()` breakdowns (one-time PF/Admin/Insurance ride the carrier = earliest-settled tranche; uploaded insurance overrides DB on the carrier). Flags: `amount` (Σ disbursed ≠ Excel `loan_amount`), `date` (unreadable, or Excel date ∉ our settlement dates), `insurance` (Excel ≠ DB). `has_discrepancy` = any flag; result carries `discrepancy_count` + `date_error_count`.
- **Dates**: `parseFlexibleDate()` accepts dd-mm-yyyy, dd/mm/yyyy, dd.mm.yyyy, dd,mm,yyyy, d-m-yyyy, yyyy-mm-dd, 2-digit years, and Excel serials (any separator run → dash; day-first preferred). Unparseable non-empty values are flagged (`_date_error`) and surfaced, never silently dropped.
- **Buckets** are loan-grain: Matched (with per-row discrepancy highlight), DB-only (loan groups absent from the file), Excel-only (rows matching no loan).
- **Both-sides display**: the Matched table shows Loan Amount / Date / Insurance as the Excel value with our **DB:** value on a second line in the *same cell* (via `<br>`), highlighting the cell + row on mismatch. The Matched table + both summaries carry **Totals** footers; the summaries now include a **Total Admin** column.

**Payout settlement date (2026-10-19, revised)**: the window + per-tranche rate-as-of for payout/reconcile/reports bucket on `DisbursementEntry::TXN_DATE_SQL` = `DATE(COALESCE(otc_handover_date, disbursement_date))` (helper `DisbursementEntry::transactionDate()`) — the **OTC handover / settlement date** (cheque cleared date, or the NEFT transfer date stamped onto `otc_handover_date`), falling back to the disbursement date only for tranches not yet settled. This is what a **bank statement reflects** (money leaving the bank), so reconcile/payout match the statement's period; e.g. a cheque disbursed 14/08 but cleared 27/08 lands in an Aug-16→Sep-15 window. Reconcile + Payout Runs additionally keep `whereNotNull('otc_handover_date')` (settled money only); reports use the COALESCE as-is so un-cleared cheques still appear (dated by disbursement). Reconcile's displayed "settlement" dates + the date-mismatch flag, `PayoutRunService` `loan_lines.settlement_date`, and `PayoutRunController::show()` all use it. The Payout Report keys on `loan_payouts.finalized_at` and is unchanged.

**Reconcile "exists in DB" vs "not in DB" (2026-10-19)**: `reconcile()` result splits the unmatched bank rows into `excel_in_db` (the identifier — A/c / application # / name — **does** exist in our DB for this bank+products, via `dbIdentifierIndex()` over ALL active tranches regardless of date; shown as a *mismatch, not payable* section with both sides) and `excel_only` (**truly** not in our DB — identifier found nowhere). So "not in DB" now means genuinely missing, not merely out-of-period. The DB-only + exists-in-DB-mismatch tables show the **Product** too (`dbIdentifierIndex` carries `product_name`; db_only reads `loan.product`). Each `excel_in_db` row carries a **`category`** + **`reason`** (`mismatchReason()`): `difference` ("Already reconciled under another account number of the same loan" — the loan matched via a different A/c; the matching is intentionally NOT changed, these are shown as a Difference), `status` (the loan is **cancelled / on_hold / rejected** — `LoanDetail::PAYOUT_EXCLUDED_STATUSES`; badge shows the status label), `pending` ("Cheque not cleared yet (OTC pending)"), `outside` ("Settled outside the selected period (…)"), or `other`. The blade renders a colored badge per category.

**Payout excludes cancelled / on-hold / rejected loans (2026-10-19)**: `LoanDetail::PAYOUT_EXCLUDED_STATUSES = [cancelled, on_hold, rejected]` is excluded from the payable set in **both** engines — `PayoutController::ourEntries` (reconcile) and `PayoutRunService` (Payout Runs) add `whereHas('loan', whereNotIn('status', …))`, so those loans are never matched/paid. In reconcile they're surfaced via the `status` category (not as "not in DB"). `dbIdentifierIndex()` is built **loan-first** — it lists all loans for the bank(+products) of ANY status (via `LoanDetail`), then enriches each with tranche data — so even a loan with **no disbursement entries** (e.g. an on-hold/cancelled loan the bank lists by application number) is recognised as "exists in our DB" and shown with its status instead of "not in DB". Accounts come from any tranche; amounts/settlement/pending only from active tranches.

**Export filenames (2026-10-19):** the reconcile template downloads as `payout-reconciliation-template-<d-m-Y_H-i-s>.xlsx` and the payout report export as `payout-report-<Ymd>-<Ymd>-<His>.xlsx` — each carries a time-of-download stamp (HH-MM-SS). `dbIdentifierIndex` `settlement_dates` are the **real** `otc_handover_date` values (NOT the `transactionDate()` disbursement fallback), plus a `pending` flag — so the mismatch table shows the true clearance status ("Pending (not cleared)" when none), not a misleading in-range disbursement date.

**Payout Runs calculate = POST (2026-10-19)**: the preview/Calculate form posts to `payouts.runs.calculate` (POST `/payouts/runs` → `PayoutRunController::index`, same method, reads GET or POST) so bank/products/dates stay out of the URL. The GET `payouts.runs` route stays for the landing page + the reconcile deep-link (prefills from query).

**Reports/Dashboard disbursed date basis (2026-10-19)**: the DISBURSED figures in the Management **funnel/trend**, the **Loan Report** (rows + totals/export) and the **Dashboard stage breakdown** window/bucket on the same settlement date (`ReportController::txnDateExpr()` = `DATE(COALESCE(de.otc_handover_date, de.disbursement_date))`; `LoanPipelineBreakdownService` uses `DisbursementEntry::transactionDate()`) instead of plain `disbursement_date`, consistent with payouts/reconcile. Un-cleared cheque tranches (null handover) fall back to the disbursement date, so they still appear (and the breakdown's "awaiting OTC" bucket keeps working). Amounts are unchanged (already GROSS via `grossTrancheExpr()` / `windowedTrancheAmount()`; `grossTrancheExpr()`'s first-tranche pick orders by the settlement date too). `applyFilters()` accepts a raw-expression date column (detected by `(`) and windows it via `whereRaw`. The pipeline report + scoreboard stay on `created_at` (cohort/current-state views, by design).

**Disbursement readonly display (2026-10-19)**: the disbursement table on the stages page (`_stages-body.blade.php`) and the readonly detail (`_stage-readonly-detail.blade.php`) show **Cheque Date / Handover Date / OTC Status** columns (status badge via `DisbursementEntry::otcStatusBadge($status,$method)`; `DisbursementDetail::entryList()` already carries `cheque_date`/`otc_handover_date`/`otc_status`).

**Identifiers on screen**: Reconcile tables and the Payout Runs "Loans considered" / run-show per-loan lists show internal **Loan #**, **App #** (`loan_details.application_number`), and the loan's distinct **A/c #(s)**; the Payout Report + its xlsx add **App #** next to Loan # (no A/c column — report is loan/cycle-level, A/c # is per tranche). `PayoutRunService` `loan_lines` + `PayoutRunController::show()` carry `application_number` + `account_numbers[]`.

## PayoutService (shared helpers only — trimmed 2026-10-07)

After the redesign this holds only the utilities the disbursement form + verify-only reconcile need; the per-loan finalize engine was removed.
- `payoutRates(?CarbonImmutable): array` → `{pf_gst, admin_gst, tds, insurance}` (decimals) in force on a date — used by the disbursement form to back-calculate GST-inclusive PF/Admin.
- `breakdown(int $commission, int $pfIncl, int $adminIncl, int $insurance, ?array $rates): array` — per-entry net breakdown (PF/Admin GST back-calc deducted, insurance added, TDS on positive gross, net floored at 0). Used only by the **reconcile** screen's informational per-entry display.
- `computeAmount(string $type, float $value, int $base, ?int $maxCap): int` — percent/fixed rate with optional cap.
- **Removed** (retired with the per-loan path): `previewFinalize`, `finalizePayout`, `finalizeEntries`, `computeGroups`, `writePayout`, `matchSlab`, `matchSlabIn`, `cycleFor`, `cycleForDays`. The `loans.payout.finalize` route + `LoanPayoutController` and the `payouts.reconcile.finalize` route + `PayoutController::reconcileFinalize` are gone. Reconcile is now **verify-only** (match our entries vs the bank statement) with a "Run Payout for this period →" button that opens `payouts.runs` pre-filled; `payouts.report` is read-only history of legacy `loan_payouts`.
