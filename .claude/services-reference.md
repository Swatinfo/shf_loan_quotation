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

## CustomerService

`app/Services/CustomerService.php` — customer identity by PAN + per-loan KYC snapshots. Master is created once per PAN and never updated; each loan gets a `customer_kyc_details` row. See `.docs/customers.md`.

- `normalizePan(?string): ?string`
- `resolveMasterByPan(array $kyc): Customer` — reuse by PAN or create master once
- `recordKyc(Customer, array $kyc, array $context): CustomerKycDetail`
- `captureForLoan(array $kyc, array $context): CustomerKycDetail` — resolve + record
- `syncLoanKyc(LoanDetail, array $kyc): CustomerKycDetail` — edit: update snapshot in place if PAN unchanged, else new master/snapshot
- `latestKycForPan(?string): ?CustomerKycDetail` — autofill lookup

## LoanConversionService

Constructor: `LoanStageService`, `LoanDocumentService`, `CustomerService`.

### `convertFromQuotation(Quotation, int $bankIndex, array $extra = []): LoanDetail`

Inside DB transaction:
1. Guard if already converted
2. Resolve customer by PAN via `CustomerService::resolveMasterByPan` (reuse or create once — never updates an existing master), then `recordKyc()` and link `customer_kyc_details_id`
3. Build `LoanDetail` (status=active, current_stage=document_collection)
4. `generateLoanNumber()` → `SHF-YYYYMM-NNNN`
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
- **disbursement** (fund_transfer) → skip `otc_clearance`, mark loan `completed`
- **otc_clearance** done → mark loan `completed`
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
- `transferStage(LoanDetail, string, int $toUserId, ?string $reason)` — updates assignment, creates StageTransfer; open queries whose `assigned_to_user_id` was the outgoing assignee are re-pointed to the new assignee (advisor-routed queries untouched)

### Rejection

`rejectLoan(LoanDetail, string $stageKey, string $reason, ?int $userId): LoanDetail` — rejects only the named stage: sets loan status=rejected, writes `rejected_at`/`rejected_by`/`rejected_stage`/`rejection_reason`, closes that one stage assignment (saves `previous_status` then sets `status=rejected`). Sibling rejection for parallel-mode flows lives in `LoanStageController::sanctionDecisionAction` (lines 835-845), which bulk-updates pending/in_progress stages and saves `previous_status` for reactivation.

### Progress

`recalculateProgress(LoanDetail): LoanProgress` — rebuilds counts + workflow_snapshot.

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

### `processDisbursement(LoanDetail, array $data): DisbursementDetail`

`$data = ['entries' => [...tranches...], 'notes' => ?string]` — each tranche: `disbursement_date` (Y-m-d), `method` (fund_transfer|cheque), `product_id` + `product_name` (snapshotted by controller), `loan_account_number`, `amount`, cheque fields on cheque tranches. Inside DB transaction:
1. Upsert `disbursement_details` with `entries` + derived legacy columns (`disbursement_type` = 'cheque' if any cheque entry, `disbursement_date` = latest entry date, `amount_disbursed` = total, `bank_account_number` = first entry's account)
1b. `syncEntryRows()` — mirror tranches into `disbursement_entries`: posted `row_id` owned by this disbursement → update in place; missing/foreign `row_id` → insert; live rows absent from payload → soft delete (`deleted_by` stamped by HasAuditColumns). `is_active` set from loan status. Assigned row_ids written back into the json `entries`.
2. Mirror total to `loan_details.disbursed_amount` on EVERY save (queryable column used by listings)
3. Set the `disbursement` relation so `handleStageCompletion` can detect & skip OTC
4. **Auto-complete**: only if entry total ≥ `disbursementTarget()` AND stage is `in_progress` → mark `disbursement` stage completed (any-cheque → OTC opens; all-NEFT → OTC skipped + loan completed). Partial totals leave the stage open for future tranches.
5. Log activity (action: `process_disbursement`; properties: `loan_number`, `type`, `amount`, `entry_count`, `stage_completed`)
6. If loan completed, notify creator + advisor

### `markFullyDisbursed(LoanDetail): void`

Manual completion for intentional under-disbursement (total below target). Requires saved entries; completes the stage via the same flow. Logs `mark_fully_disbursed`. Exposed as POST `loans.disbursement.complete`.

### `disbursementTarget(LoanDetail): int`

Auto-complete threshold: `loan_details.sanctioned_amount` → docket notes `sanctioned_amount` → sanction notes → `loan_amount`.

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

Enforced in `LoanStageController::resolveQuery()` (403 otherwise; 422 if already resolved) and mirrored by the Resolve button conditions in `loans/_stages-body.blade.php` (sub-stage + main-stage sites). This fixes the escalation deadlock where the raiser transfers the stage to BM/BDH and never returns (loan-104 incident). On `transferStage()`, open queries whose `assigned_to_user_id` is the outgoing assignee follow the handoff to the new assignee; queries routed to the advisor keep their recipient.

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

- **Transactions**: `convertFromQuotation`, `createDirectLoan`, `processDisbursement`, `generate` (DB-save phase) — wrapped in `DB::transaction`.
- **Activity logs**: services log via `ActivityLog::log($action, $subject, $properties)` after write.
- **Notifications**: sent inside the same request; no queue.
- **Cache invalidation**: `PermissionService` caches are the only service-level cache; `Role::clearAdvisorCache()` for advisor-eligible lookups.
- **Validation**: services trust inputs validated by controllers; `QuotationService::generate` is the only exception — it re-validates because it's also called by the offline sync API.
