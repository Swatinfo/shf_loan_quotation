# Services Reference

All application services with method signatures, validation rules, and business logic.

---

## Original Services

### QuotationService
**File**: `app/Services/QuotationService.php`
**Dependencies**: `ConfigService`, `PdfGenerationService`, models: `BankCharge`, `Quotation`, `QuotationBank`, `QuotationDocument`, `QuotationEmi`, `ActivityLog`

#### Methods
- `generate(array $input, int $userId): array` — Validates input, builds template data, generates PDF, saves quotation + banks + EMIs + documents to DB in a transaction. Returns `['success' => true, 'quotation' => Quotation]` or `['error' => '...']`
  - Validation (inline):
    - `customerName` — required, non-empty string
    - `customerType` — required, non-empty string
    - `loanAmount` — required, > 0, max 1,000,000,000,000 (1 lakh crore)
    - `banks` — required, non-empty array
    - Per bank: `roiMin` and `roiMax` required > 0, max 30%, min <= max
  - Also accepts: `documents[]`, `additionalNotes`, `selectedTenures[]`, `ourServices`, `preparedByName`, `preparedByMobile`, `location_id`

#### Key Logic
- Loads tenures from config, filters by client-selected tenures if provided
- Generates PDF via `PdfGenerationService::generate()`
- Saves quotation, banks with charges, EMI data per tenure, and documents in a DB transaction
- After save, updates `bank_charges` table with latest charge data via `updateBankCharges()` (private)

---

### PdfGenerationService
**File**: `app/Services/PdfGenerationService.php`
**Dependencies**: None (standalone). Uses Chrome headless or PDF microservice.

#### Methods
- `generate(array $data): array` — Renders HTML template and converts to PDF. Returns `['success' => true, 'filename' => '...', 'path' => '...']` or `['error' => '...']`
- `renderHtml(array $data): string` — Renders the full HTML document for PDF conversion (public, can be used for preview)
- `static getTypeLabel(string $type): string` — Returns bilingual customer type label (e.g. `'proprietor'` -> `'Proprietor / ...'`)

#### Key Logic
- Three-tier PDF generation strategy:
  1. `PDF_USE_MICROSERVICE=true` -> microservice only (escape hatch)
  2. Chrome available -> Chrome headless directly (fastest)
  3. Chrome unavailable -> microservice fallback
- Cross-platform Chrome detection (Windows paths + Linux `command -v`)
- Bilingual labels stored in `$this->labels` array (English + Gujarati)
- HTML template includes: header, EMI comparison table, charges comparison table, documents list, additional notes, prepared-by section

---

### ConfigService
**File**: `app/Services/ConfigService.php`
**Dependencies**: `AppConfig` model, `config/app-defaults.php`

#### Methods
- `load(): array` — Load config from DB (`app_config` table, key `'main'`), or initialize from defaults if missing
- `save(array $config): void` — Save config array to DB (upserts `config_key = 'main'`)
- `reset(): array` — Reset config to defaults from `config/app-defaults.php`
- `get(string $key, $default = null): mixed` — Get a specific config value by dot-notation key
- `updateSection(string $section, $value): array` — Update a specific section of the config by key
- `updateMany(array $updates): array` — Update multiple config keys at once

#### Key Logic
- Merges loaded config with defaults via `array_replace_recursive` to ensure all keys exist
- Sequential arrays (lists) are replaced entirely from DB, not merged per-index, so deletions from defaults are respected
- Config stored as JSON in `app_config.config_json` with `config_key = 'main'`

---

### NumberToWordsService
**File**: `app/Services/NumberToWordsService.php`
**Dependencies**: None (standalone, all static methods)

#### Methods
- `static toEnglish(int $num): string` — Converts number to English words with Indian numbering (Crore, Lakh, Thousand) + "Rupees" suffix
- `static toGujarati(int $num): string` — Converts number to Gujarati words with Indian numbering + "..." suffix
- `static toBilingual(int $num): string` — Returns `"English / Gujarati"` combined string
- `static formatIndianNumber($num): string` — Formats number with Indian comma system (e.g. `12,34,567`)
- `static formatCurrency($num): string` — Returns `"Rs X,XX,XXX"` formatted string with non-breaking space

#### Key Logic
- Indian numbering system: Crore (10^7), Lakh (10^5), Thousand (10^3), Hundred
- Full Gujarati number words array (0-99) for accurate transliteration
- Zero handled as "Zero Rupees" / "..." respectively

---

### PermissionService
**File**: `app/Services/PermissionService.php`
**Dependencies**: `Permission`, `User`, `UserPermission` models, `Cache`

#### Methods
- `userHasPermission(User $user, string $slug): bool` — 3-tier permission check: super_admin bypass -> user override -> role permissions
- `userRolesHavePermission(User $user, string $slug): bool` — Check if any of user's roles has the permission
- `getUserPermissions(User $user): array` — Get all permissions for a user as `[slug => bool]` map
- `getGroupedPermissions(): array` — Get all permissions grouped by `group` field
- `clearUserCache(User $user): void` — Clear cached permissions for a specific user
- `clearRoleCache(): void` — Clear cached role permission combos
- `clearAllCaches(): void` — Clear all permission caches for all users and roles

#### Key Logic
- 3-tier resolution: (1) super_admin always true, (2) user-specific grant/deny override, (3) any role grants
- 5-minute cache TTL per user (`user_perms:{id}`, `user_role_ids:{id}`) and per role combo (`role_perms:{ids}`)
- User overrides: `UserPermission` with type `'grant'` or `'deny'`

---

## Loan System Services

### LoanConversionService
**File**: `app/Services/LoanConversionService.php`
**Dependencies**: `LoanStageService`, `LoanDocumentService`, models: `ActivityLog`, `Bank`, `Customer`, `LoanDetail`, `Quotation`

#### Methods
- `convertFromQuotation(Quotation $quotation, int $bankIndex, array $extra = []): LoanDetail` — Converts a quotation to a loan task, creating customer record, loan detail, documents, and stages in a transaction
  - `$extra` accepts: `customer_phone`, `customer_email`, `date_of_birth`, `pan_number`, `branch_id`, `product_id`, `assigned_advisor`, `notes`
- `createDirectLoan(array $data): LoanDetail` — Creates a loan directly without a quotation
  - `$data` accepts: `bank_id`, `product_id`, `branch_id`, `customer_name`, `customer_type`, `customer_phone`, `customer_email`, `loan_amount`, `assigned_advisor`, `notes`

#### Key Logic
- `convertFromQuotation`: Throws if quotation already converted. Auto-completes `inquiry` + `document_selection` stages. Auto-assigns `document_collection`. Sets `current_stage = 'document_collection'`
- `createDirectLoan`: Sets `current_stage = 'inquiry'`. Populates documents from config defaults
- Both methods: generate loan number via `LoanDetail::generateLoanNumber()`, set default due date to 7 days, log activity

---

### LoanStageService
**File**: `app/Services/LoanStageService.php`
**Dependencies**: models: `ActivityLog`, `Bank`, `LoanDetail`, `LoanProgress`, `ProductStage`, `Stage`, `StageAssignment`, `StageQuery`, `StageTransfer`, `User`; services: `NotificationService` (via `app()`)

#### Methods — Query
- `static getStageRoleEligibility(string $stageKey): array` — Get eligible roles for a stage from the `stages` table `default_role` column
- `static getAllStageRoleEligibility(): array` — Get all stage role eligibility as `[stage_key => roles[]]` map
- `getOrderedStages(): Collection` — Get all enabled main stages ordered by sequence
- `getStageByKey(string $key): ?Stage` — Find a stage by its key
- `getSubStages(string $parentKey): Collection` — Get sub-stages of a parent stage
- `isParallelStage(string $key): bool` — Check if a stage is parallel (has `is_parallel` or `parent_stage_key`)
- `getMainStageKeys(): array` — Get ordered array of enabled main stage keys

#### Methods — Initialization
- `initializeStages(LoanDetail $loan): void` — Create stage assignments + loan progress for a new loan (16 base stages)
- `autoCompleteStages(LoanDetail $loan, array $stageKeys): void` — Auto-complete specific stages (used when converting from quotation)

#### Methods — Stage Transitions
- `updateStageStatus(LoanDetail $loan, string $stageKey, string $newStatus, ?int $userId = null): StageAssignment` — Update stage status with validation, blocking on pending queries, auto-advancement
- `getNextStage(string $currentStageKey): ?string` — Get the next main stage key after the given one
- `canStartStage(LoanDetail $loan, string $stageKey): bool` — Check if a stage can be started (validates prerequisites)

#### Methods — Assignment
- `assignStage(LoanDetail $loan, string $stageKey, int $userId): StageAssignment` — Manually assign a stage to a user
- `skipStage(LoanDetail $loan, string $stageKey, ?int $userId = null): StageAssignment` — Skip a stage (delegates to `updateStageStatus` with `'skipped'`)
- `autoAssignStage(LoanDetail $loan, string $stageKey): ?StageAssignment` — Auto-assign a stage to the best-fit user
- `autoAssignParallelSubStages(LoanDetail $loan): void` — Auto-assign parallel sub-stages (only starts `app_number` first)
- `findBestAssignee(string $stageKey, ?int $branchId, ?int $bankId, ?int $productId = null, ?int $loanCreatorId = null): ?int` — Find the best user for a stage

#### Methods — Transfer
- `transferStage(LoanDetail $loan, string $stageKey, int $toUserId, ?string $reason = null): StageAssignment` — Transfer a stage to another user with history tracking

#### Methods — Soft Revert
- `revertStageIfIncomplete(LoanDetail $loan, string $stageKey, bool $isStillComplete): bool` — Soft-revert a completed stage if its data no longer meets completion criteria. Preserves all data (notes, assigned_to, timestamps), only changes status. Reverts next stage to pending. Returns true if revert occurred.

#### Methods — Rejection
- `rejectLoan(LoanDetail $loan, string $stageKey, string $reason, ?int $userId = null): LoanDetail` — Reject the entire loan from a stage

#### Methods — Parallel Processing
- `checkParallelCompletion(LoanDetail $loan): bool` — Check if all parallel sub-stages are complete; auto-advance if so
- `getParallelSubStages(LoanDetail $loan): Collection` — Get parallel sub-stages with stage and assignee relations

#### Methods — Progress
- `recalculateProgress(LoanDetail $loan): LoanProgress` — Recalculate loan progress percentages and workflow snapshot
- `getLoanStageStatus(LoanDetail $loan): Collection` — Get all stage assignments ordered by sequence

#### Constants (Base Stage Keys)
- 16 base stages initialized: `inquiry`, `document_selection`, `document_collection`, `parallel_processing`, `app_number`, `bsm_osv`, `legal_verification`, `technical_valuation`, `sanction_decision`, `rate_pf`, `sanction`, `docket`, `kfs`, `esign`, `disbursement`, `otc_clearance`

#### Key Logic
- **Parallel processing flow**: `app_number` -> `bsm_osv` -> `[legal_verification, technical_valuation, sanction_decision]` in parallel
- **Auto-advancement**: On stage completion, auto-advances to next stage, auto-assigns, and auto-starts
- **Pending queries block completion**: Cannot complete a stage with unresolved queries
- **Rate & PF prerequisites**: Requires `is_sanctioned = true` and at least one parallel sub-stage completed
- **Fund transfer disbursement**: Skips OTC stage and completes loan directly
- **OTC clearance completion**: Marks loan as completed and notifies
- **Sanction completion**: Calculates expected docket date from app_number notes
- **findBestAssignee priority**: Product stage user (location-aware) -> bank default employee -> bank employee (branch match) -> bank employee (any) -> default office employee -> loan creator (if advisor/manager/BDO) -> branch match -> any matching role
- **Transfer**: Reassigns open queries on the stage to the new assignee

---

### LoanDocumentService
**File**: `app/Services/LoanDocumentService.php`
**Dependencies**: `ConfigService`, models: `ActivityLog`, `LoanDetail`, `LoanDocument`, `Quotation`, `Storage`

#### Methods
- `populateFromQuotation(LoanDetail $loan, Quotation $quotation): void` — Copy documents from quotation to loan (all marked required, status pending)
- `populateFromDefaults(LoanDetail $loan): void` — Populate documents from config defaults based on customer type
- `updateStatus(LoanDocument $document, string $status, int $userId, ?string $rejectedReason = null): void` — Update document status (received/rejected/pending) with appropriate field resets
- `getProgress(LoanDetail $loan): array` — Get document collection progress: `['total', 'resolved', 'received', 'rejected', 'pending', 'percentage']`
- `allRequiredResolved(LoanDetail $loan): bool` — Check if all required documents are resolved (received or rejected)
- `addDocument(LoanDetail $loan, string $nameEn, ?string $nameGu, bool $required = true): LoanDocument` — Add a custom document to a loan
- `removeDocument(LoanDocument $document): void` — Remove a document (deletes uploaded file if exists)
- `uploadFile(LoanDocument $document, UploadedFile $file, int $userId): LoanDocument` — Upload a file for a document (replaces existing, auto-marks as received if pending)
- `deleteFile(LoanDocument $document): void` — Delete the uploaded file (keeps the document record)

#### Key Logic
- Documents populated from quotation retain `document_name_en` and `document_name_gu`
- Default documents loaded from config by customer type: `documents_en[type]` and `documents_gu[type]`
- File storage: `loan-documents/{loan_id}/{doc_id}_{timestamp}.{ext}` on `local` disk
- Auto-mark as received on file upload if status is `pending`
- Status transitions reset related fields (received_date, received_by, rejected_reason)

---

### StageQueryService
**File**: `app/Services/StageQueryService.php`
**Dependencies**: `NotificationService` (via `app()`), models: `ActivityLog`, `QueryResponse`, `StageAssignment`, `StageQuery`

#### Methods
- `raiseQuery(StageAssignment $assignment, string $queryText, int $userId): StageQuery` — Raise a query on a stage assignment. Notifies the stage assignee.
- `respondToQuery(StageQuery $query, string $responseText, int $userId): QueryResponse` — Respond to a query. Updates query status to `'responded'`. Notifies the raiser.
- `resolveQuery(StageQuery $query, int $userId): StageQuery` — Mark a query as resolved with timestamp
- `getQueriesForStage(StageAssignment $assignment): Collection` — Get all queries for a stage with responses, ordered latest first

#### Key Logic
- Query lifecycle: `pending` -> `responded` -> `resolved`
- Notifications sent on raise (to assignee) and respond (to raiser, if different user)
- Pending queries block stage completion (enforced in `LoanStageService::updateStageStatus`)

---

### NotificationService
**File**: `app/Services/NotificationService.php`
**Dependencies**: models: `LoanDetail`, `ShfNotification`, `Stage`, `User`

#### Methods
- `notify(int $userId, string $title, string $message, string $type = 'info', ?int $loanId = null, ?string $stageKey = null, ?string $link = null): ShfNotification` — Create a notification for a user
- `notifyStageAssignment(LoanDetail $loan, string $stageKey, int $assignedUserId): ShfNotification` — Notify user of stage assignment with loan details
- `notifyStageCompleted(LoanDetail $loan, string $stageKey): void` — Notify loan creator and advisor of stage completion (excludes current user)
- `notifyLoanCompleted(LoanDetail $loan): void` — Notify loan creator and advisor of loan completion
- `markRead(ShfNotification $notification): void` — Mark a single notification as read
- `markAllRead(int $userId): void` — Mark all notifications for a user as read
- `getUnreadCount(int $userId): int` — Get unread notification count for a user

#### Key Logic
- Notification types: `'info'`, `'warning'`, `'assignment'`, `'stage_update'`, `'success'`
- Stage name resolved from `stages` table (`stage_name_en`) for human-readable messages
- Completion notifications sent to both `created_by` and `assigned_advisor`, excluding the acting user

---

### RemarkService
**File**: `app/Services/RemarkService.php`
**Dependencies**: models: `ActivityLog`, `LoanDetail`, `Remark`

#### Methods
- `addRemark(int $loanId, int $userId, string $remark, ?string $stageKey = null): Remark` — Add a remark to a loan, optionally scoped to a stage
- `getRemarks(int $loanId, ?string $stageKey = null): Collection` — Get remarks for a loan. If stageKey provided, returns remarks for that stage + global remarks (null stage_key)

#### Key Logic
- Remarks can be global (no stage_key) or stage-specific
- When filtering by stage, includes both stage-specific and global remarks
- Activity log captures preview (first 100 chars)

---

### DisbursementService
**File**: `app/Services/DisbursementService.php`
**Dependencies**: `LoanStageService`, `NotificationService` (via `app()`), models: `ActivityLog`, `DisbursementDetail`, `LoanDetail`

#### Methods
- `processDisbursement(LoanDetail $loan, array $data): DisbursementDetail` — Process disbursement in a transaction: upserts disbursement record, completes disbursement stage, logs activity, notifies on loan completion

#### Key Logic
- Uses `updateOrCreate` on `loan_id` — supports both initial and updated disbursement
- Completing the disbursement stage triggers `handleStageCompletion` in `LoanStageService`, which:
  - For `fund_transfer`: skips OTC and completes the loan
  - For `cheque`: auto-advances to `otc_clearance`
- Checks if loan completed after stage update and sends notification

---

### LoanTimelineService
**File**: `app/Services/LoanTimelineService.php`
**Dependencies**: models: `LoanDetail`, `User`

#### Methods
- `getTimeline(LoanDetail $loan): Collection` — Build a complete lifecycle timeline for a loan, sorted chronologically

#### Key Logic
- Merges 8 event types into a single sorted timeline:
  1. **Quotation created** (if converted from quotation)
  2. **Loan created** (converted or direct)
  3. **Stage started/completed/skipped** (from stage assignments)
  4. **Transfers** (from stage transfers)
  5. **Queries raised + responses** (from stage queries)
  6. **Remarks** (global and stage-specific)
  7. **Rejection** (if loan rejected)
  8. **Disbursement** (if processed)
  9. **Completion** (if loan completed)
- Each entry has: `type`, `date`, `title`, `description`, `user`, `icon`, `color`
- Eager-loads all relationships to avoid N+1 queries
