# Services Reference

## QuotationService (`app/Services/QuotationService.php`)

### `generate(array $input, int $userId): array`
Main entry point for creating a quotation.

**Input validation**:
- customerName: required, non-empty
- customerType: required
- loanAmount: required, > 0, <= 1,000,000,000,000 (1 lakh crore)
- banks: required array, each must have bankName, roiMin (0-30), roiMax (>= roiMin)
- selectedTenures: validated against configured tenures
- documents: optional array

**Flow**:
1. Validates all inputs
2. Processes bank data (charges, EMI calculations)
3. Calls `PdfGenerationService::generate()` for PDF
4. Wraps DB save in transaction (quotation → banks → EMI entries → documents)
5. Updates `bank_charges` reference table
6. Returns `['success' => true, 'quotation' => Quotation]` or `['success' => false, 'error' => '...']`

---

## PdfGenerationService (`app/Services/PdfGenerationService.php`)

### `generate(array $data): array`
Generates PDF from quotation data.

**Strategy** (three-tier, OS-agnostic):
1. If `PDF_USE_MICROSERVICE=true` → force microservice (cURL POST to `PDF_SERVICE_URL` with `PDF_SERVICE_KEY`)
2. Else if Chrome available → Chrome headless via `exec()` — saves HTML to tmp, runs Chrome `--print-to-pdf`
   - Auto-detects Chrome at common paths (Windows/Linux/macOS) or uses `CHROME_PATH` env
3. Else → microservice fallback

**Output**: `['success' => true, 'filename' => '...', 'path' => '...']`

### `renderHtml(array $data): string`
Renders complete HTML document:
- Embeds Noto Sans Gujarati font as base64
- Embeds company logo as base64
- Multi-page layout with fixed header/footer
- EMI comparison tables (one per selected tenure)
- Bank charges comparison table
- Required documents checklist (bilingual)
- Additional notes section

---

## ConfigService (`app/Services/ConfigService.php`)

### Key methods:
- `load()`: Loads from `app_config` table or initializes from `config/app-defaults.php`
- `get(string $key, $default)`: Dot-notation access (e.g., `get('company.name')`)
- `updateSection(string $section, $value)`: Update one section
- `updateMany(array $updates)`: Update multiple sections
- `reset()`: Reset to defaults from `config/app-defaults.php`

### Config sections (flat top-level keys):
`companyName`, `companyAddress`, `companyPhone`, `companyEmail`, `banks`, `tenures`, `documents_en`, `documents_gu`, `iomCharges`, `gstPercent`, `ourServices`

**Note**: Config uses flat keys (e.g., `companyName` not `company.name`). Dot-notation only works for nested sections like `get('iomCharges.thresholdAmount')`.

---

## PermissionService (`app/Services/PermissionService.php`)

### Resolution order (3-tier):
1. **Super Admin**: If user `hasRole('super_admin')` → always `true`
2. **User Override**: Check `user_permissions` for explicit grant/deny
3. **Role Permissions**: Check if ANY of the user's roles (via `role_permission` pivot) grants the permission

Uses unified `roles` / `role_user` / `role_permission` tables — no separate system role vs task role.

### Public Methods:
- `userHasPermission(User $user, string $slug): bool` — main permission check
- `userRolesHavePermission(User $user, string $slug): bool` — checks all user's roles via pivot
- `getUserPermissions(User $user): array` — all permissions merged [slug → bool]
- `getGroupedPermissions(): array` — all permissions grouped by 'group' field
- `clearUserCache(User $user)` — clears user override + role ID caches
- `clearRoleCache()` — clears all role permission caches
- `clearAllCaches()` — clears everything

### Caching:
- User overrides: cached 5 minutes, key `user_perms:{userId}`
- User role IDs: cached 5 minutes, key `user_role_ids:{userId}`
- Role permissions: cached 5 minutes, key `role_perms:{roleIds}` (sorted, comma-joined)
- Clear: `clearUserCache($user)`, `clearRoleCache()`, `clearAllCaches()`

---

## NumberToWordsService (`app/Services/NumberToWordsService.php`)

Indian numbering system (Crore/Lakh/Thousand):
- `toEnglish(int $num)`: "One Crore Twenty Five Lakh"
- `toGujarati(int $num)`: Gujarati script equivalent
- `toBilingual(int $num)`: "English / Gujarati"
- `formatIndianNumber(int $num)`: "1,25,00,000"
- `formatCurrency(int $num)`: "₹ 1,25,00,000"

---

## LoanStageService (`app/Services/LoanStageService.php`) — Phase 1+5

### Stage Role Methods
- `getStageRoleEligibility(string $stageKey): array` — reads eligible role slugs from `stages.default_role` JSON column
- `getAllStageRoleEligibility(): array` — returns all stage_key → role[] mappings

### Query Methods
- `getOrderedStages()`: All main stages ordered by sequence
- `getStageByKey(string $key)`: Find by stage_key
- `getSubStages(string $parentKey)`: Child stages of parallel parent
- `isParallelStage(string $key)`: Is it parallel parent or sub-stage
- `getMainStageKeys()`: Ordered array of main stage keys

### Initialization
- `initializeStages(LoanDetail $loan)`: Creates 14 base stage_assignments + loan_progress
- `autoCompleteStages(LoanDetail $loan, array $stageKeys)`: Mark stages as completed (used on conversion)

### Transitions
- `updateStageStatus(LoanDetail $loan, string $stageKey, string $newStatus, ?int $userId)`: Validates transition, blocks if pending queries, auto-advances
- `getNextStage(string $currentKey)`: Next main stage key
- `canStartStage(LoanDetail $loan, string $stageKey)`: Previous must be completed/skipped

### Assignment
- `assignStage(LoanDetail $loan, string $stageKey, int $userId)`: Manual assign
- `autoAssignStage(LoanDetail $loan, string $stageKey)`: Auto-assign best-fit user (role+bank+branch)
- `autoAssignParallelSubStages(LoanDetail $loan)`: Auto-assign all 4 parallel sub-stages
- `findBestAssignee(string $stageKey, ?int $branchId, ?int $bankId, ?int $productId, ?int $loanCreatorId)`: Priority: ProductStage user → bank default → bank+branch → other eligible roles
- `skipStage(LoanDetail $loan, string $stageKey, ?int $userId)`: Skip stage

### Transfer & Rejection
- `transferStage(LoanDetail $loan, string $stageKey, int $toUserId, ?string $reason)`: Transfer with history
- `rejectLoan(LoanDetail $loan, string $stageKey, string $reason, ?int $userId)`: Reject entire loan (terminal)

### Parallel Processing
- `checkParallelCompletion(LoanDetail $loan)`: All sub-stages done? Auto-complete parent + advance
- `getParallelSubStages(LoanDetail $loan)`: Get sub-stage assignments with stage+assignee
- `startSingleParallelSubStage(LoanDetail $loan, string $stageKey)`: Start one specific sub-stage (used for 4a→4b)
- `startRemainingParallelSubStages(LoanDetail $loan)`: Start all pending parallel sub-stages (used when 4b completes → 4c/4d/4e)

**Sub-stage ordering:** 4a → 4b → [4c, 4d, 4e in parallel]. Rate & PF requires `loan.is_sanctioned = true`.

### Progress
- `recalculateProgress(LoanDetail $loan)`: Update loan_progress (count, percentage, snapshot)
- `getLoanStageStatus(LoanDetail $loan)`: All assignments ordered by sequence

---

## LoanConversionService (`app/Services/LoanConversionService.php`) — Phase 2

### `convertFromQuotation(Quotation $quotation, int $bankIndex, array $extra = []): LoanDetail`
Creates loan from quotation: copies customer data + selected bank, populates documents, initializes stages, auto-completes stages 1-2, auto-assigns stage 3.

### `createDirectLoan(array $data): LoanDetail`
Creates loan directly: generates loan number, populates documents from config defaults, initializes stages.

---

## LoanDocumentService (`app/Services/LoanDocumentService.php`) — Phase 4

- `populateFromQuotation(LoanDetail $loan, Quotation $quotation)`: Copy quotation docs to loan
- `populateFromDefaults(LoanDetail $loan)`: From config/app-defaults.php by customer_type
- `updateStatus(LoanDocument $doc, string $status, int $userId, ?string $rejectedReason)`: Change status (pending/received/rejected/waived)
- `getProgress(LoanDetail $loan)`: Returns {total, resolved, received, rejected, pending, percentage}
- `allRequiredResolved(LoanDetail $loan)`: All required docs received or waived
- `addDocument(LoanDetail $loan, string $nameEn, ?string $nameGu, bool $required)`: Add custom doc
- `removeDocument(LoanDocument $doc)`: Delete doc with ActivityLog

---

## StageQueryService (`app/Services/StageQueryService.php`) — Phase 5

- `raiseQuery(StageAssignment $assignment, string $queryText, int $userId)`: Create pending query (blocks stage completion)
- `respondToQuery(StageQuery $query, string $responseText, int $userId)`: Add response, mark query as responded
- `resolveQuery(StageQuery $query, int $userId)`: Mark query as resolved (unblocks stage)
- `getQueriesForStage(StageAssignment $assignment)`: All queries with responses

---

## NotificationService (`app/Services/NotificationService.php`) — Phase 6b

- `notify(int $userId, string $title, string $message, string $type, ?int $loanId, ?string $stageKey, ?string $link)`: Create notification
- `notifyStageAssignment(LoanDetail $loan, string $stageKey, int $assignedUserId)`: Notify user of stage assignment
- `notifyStageCompleted(LoanDetail $loan, string $stageKey)`: Notify creator/advisor of stage completion
- `notifyLoanCompleted(LoanDetail $loan)`: Notify on loan completion
- `markRead(ShfNotification $notification)`: Mark single as read
- `markAllRead(int $userId)`: Mark all as read for user
- `getUnreadCount(int $userId)`: Count unread

---

## RemarkService (`app/Services/RemarkService.php`) — Phase 6b

- `addRemark(int $loanId, int $userId, string $remark, ?string $stageKey)`: Add remark with ActivityLog
- `getRemarks(int $loanId, ?string $stageKey)`: Get remarks for loan, optionally filtered by stage

---

## DisbursementService (`app/Services/DisbursementService.php`) — Phase 7a

- `processDisbursement(LoanDetail $loan, array $data)`: Create/update disbursement, complete loan if not OTC pending
- `clearOtc(DisbursementDetail $disbursement)`: Clear OTC, complete loan

---

## LoanTimelineService (`app/Services/LoanTimelineService.php`) — Phase 10

- `getTimeline(LoanDetail $loan)`: Builds complete lifecycle timeline merging quotation creation, loan creation, stage start/complete, transfers, queries, remarks, disbursement, rejection, completion. Returns sorted collection of timeline entries.
