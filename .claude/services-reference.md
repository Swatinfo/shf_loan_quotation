# Services Reference

All 13 services + 1 trait in `app/Services/` and `app/Traits/`.

---

## ConfigService

Manages application configuration stored in `app_config` table with `config/app-defaults.php` fallback.

### Methods
| Method | Signature | Purpose |
|--------|-----------|---------|
| `load()` | `(): array` | Load config from DB (key='main'), merge with defaults. Returns full config. |
| `save()` | `(array $config): void` | Persist config to AppConfig table |
| `reset()` | `(): array` | Reset to defaults and persist |
| `get()` | `(string $key, $default = null)` | Dot-notation access (e.g., 'iomCharges.thresholdAmount') |
| `updateSection()` | `(string $section, $value): array` | Update specific section, returns full config |
| `updateMany()` | `(array $updates): array` | Batch update multiple keys |

### Key behavior
- Sequential arrays from DB replace defaults entirely (deletions respected)
- Merges via `array_replace_recursive` with special handling for indexed arrays
- Caches merged config in memory during request

---

## DisbursementService

Processes loan disbursement transactions.

### Methods
| Method | Signature | Purpose |
|--------|-----------|---------|
| `processDisbursement()` | `(LoanDetail $loan, array $data): DisbursementDetail` | Create/update disbursement. Fund_transfer skips OTC; cheque proceeds to OTC. Completes disbursement stage. |

### Business logic
- Wraps in DB transaction
- `bank_account_number` collected for both fund_transfer and cheque types
- Completes 'disbursement' stage via LoanStageService
- Fund transfer: skips OTC stage, may complete loan
- Cheque: loan proceeds to OTC clearance
- Logs activity, notifies if loan completed

**Dependencies:** LoanStageService, NotificationService

---

## LoanConversionService

Converts quotations to loans or creates loans directly.

### Methods
| Method | Signature | Purpose |
|--------|-----------|---------|
| `convertFromQuotation()` | `(Quotation $quotation, int $bankIndex, array $extra = []): LoanDetail` | Convert quotation → loan. Auto-completes inquiry + doc_selection, auto-assigns doc_collection. |
| `createDirectLoan()` | `(array $data): LoanDetail` | Create loan without quotation. current_stage = 'inquiry'. |

### Conversion flow
1. Validate quotation not already converted
2. Create Customer record
3. Create LoanDetail (status=active, current_stage=document_collection)
4. Copy documents from quotation via LoanDocumentService
5. Initialize all stages, auto-complete first 2
6. Auto-assign document_collection to best-fit user
7. Set due_date = now + 7 days

**Dependencies:** LoanStageService, LoanDocumentService

---

## LoanDocumentService

Manages loan documents: population, status tracking, file uploads.

### Methods
| Method | Signature | Purpose |
|--------|-----------|---------|
| `populateFromQuotation()` | `(LoanDetail $loan, Quotation $quotation): void` | Copy docs from quotation |
| `populateFromDefaults()` | `(LoanDetail $loan): void` | Load docs from config by customer_type |
| `updateStatus()` | `(LoanDocument $doc, string $status, int $userId, ?string $rejectedReason): void` | Update doc status (pending/received/rejected) |
| `getProgress()` | `(LoanDetail $loan): array` | Returns: total, resolved, received, rejected, pending, percentage |
| `allRequiredResolved()` | `(LoanDetail $loan): bool` | Check if all required docs resolved |
| `addDocument()` | `(LoanDetail $loan, string $nameEn, ?string $nameGu, bool $required): LoanDocument` | Add custom document |
| `removeDocument()` | `(LoanDocument $doc): void` | Delete document + file |
| `uploadFile()` | `(LoanDocument $doc, UploadedFile $file, int $userId): LoanDocument` | Upload file, auto-mark received if pending |
| `deleteFile()` | `(LoanDocument $doc): void` | Delete file only, keep document record |

### File storage
- Path: `storage/app/loan-documents/{loan_id}/{document_id}_{timestamp}.{ext}`
- Auto-status on upload: pending → received

**Dependencies:** ConfigService

---

## LoanStageService

Core workflow engine managing loan stage lifecycle.

### Static Methods
| Method | Purpose |
|--------|---------|
| `getStageRoleEligibility(stageKey)` | Get eligible roles for a stage from Stage.default_role |
| `getAllStageRoleEligibility()` | Map of [stage_key => roles[]] |

### Query Methods
| Method | Purpose |
|--------|---------|
| `getOrderedStages()` | All enabled main stages in order |
| `getStageByKey(key)` | Retrieve Stage by key |
| `getSubStages(parentKey)` | Sub-stages of parent |
| `isParallelStage(key)` | Check if parallel |
| `getMainStageKeys()` | Array of enabled main stage keys |
| `getLoanStageStatus(loan)` | All stage assignments sorted by sequence |

### Initialization
| Method | Purpose |
|--------|---------|
| `initializeStages(loan)` | Create 14+ stage assignments and LoanProgress |
| `autoCompleteStages(loan, stageKeys)` | Auto-complete specific stages |

### Stage Transitions
| Method | Purpose |
|--------|---------|
| `updateStageStatus(loan, stageKey, newStatus, userId)` | Update status with validation. Blocks if pending queries. Triggers handleStageCompletion. |
| `revertStageIfIncomplete(loan, stageKey, isStillComplete)` | Soft-revert completed stage to in_progress if data no longer meets criteria |
| `handleStageCompletion(loan, completedStageKey)` | Post-completion: parallel sequencing, OTC skip, next stage advancement, auto-assignment |
| `getNextStage(currentStageKey)` | Next main stage key in sequence |
| `canStartStage(loan, stageKey)` | Validate dependencies met |

### Assignment
| Method | Purpose |
|--------|---------|
| `assignStage(loan, stageKey, userId)` | Assign stage to user |
| `skipStage(loan, stageKey, userId)` | Mark stage as skipped |
| `autoAssignStage(loan, stageKey)` | Auto-assign to best-fit user |
| `autoAssignParallelSubStages(loan)` | Auto-assign app_number only; others wait |
| `assignNextStage(loan, nextKey)` | Start next stage (in_progress) and auto-assign. Extracted from handleStageCompletion for reuse. |
| `findBestAssignee(stageKey, branchId, bankId, productId, loanCreatorId, advisorId)` | Priority: ProductStage config → advisor → bank default → role+branch → any role |

### Transfer & Rejection
| Method | Purpose |
|--------|---------|
| `transferStage(loan, stageKey, toUserId, reason)` | Transfer with StageTransfer history |
| `rejectLoan(loan, stageKey, reason, userId)` | Set loan to rejected status |

### Parallel Processing
| Method | Purpose |
|--------|---------|
| `checkParallelCompletion(loan)` | Check all subs complete → advance to rate_pf via assignNextStage() |
| `getParallelSubStages(loan)` | Get parallel sub-stages with relations |

### Progress
| Method | Purpose |
|--------|---------|
| `recalculateProgress(loan)` | Recalculate percentage and workflow snapshot |

### Key parallel flow
1. parallel_processing starts → app_number auto-assigned
2. app_number completes → bsm_osv starts
3. bsm_osv completes → legal_verification + technical_valuation start
4. All subs complete → rate_pf auto-advances

### Key business rules
- Fund_transfer disbursement skips OTC, completes loan
- Cheque disbursement → OTC clearance
- rate_pf requires is_sanctioned = true
- Docket expected date from app_number notes
- Sanction letter notes require `tenure_months`; EMI cannot exceed sanctioned amount (validated server-side in `saveNotes()` and `getSanctionRequiredFields()`)
- Rate & PF Phase 3 has a `complete` action in `ratePfAction()` that calls `updateStageStatus($loan, 'rate_pf', 'completed')`

---

## LoanTimelineService

Builds complete loan lifecycle timeline.

### Methods
| Method | Signature | Purpose |
|--------|-----------|---------|
| `getTimeline()` | `(LoanDetail $loan): Collection` | Merge all events sorted by date |

### Timeline entry types
quotation_created, converted, loan_created, stage_started, stage_completed, stage_skipped, transfer, query_raised, query_response, remark, rejected, disbursement, completed

---

## NotificationService

In-app notification management.

### Methods
| Method | Purpose |
|--------|---------|
| `notify(userId, title, message, type, loanId, stageKey, link)` | Create generic notification |
| `notifyStageAssignment(loan, stageKey, userId)` | Notify of stage assignment |
| `notifyStageCompleted(loan, stageKey)` | Notify creator + advisor |
| `notifyLoanCompleted(loan)` | Notify creator + advisor |
| `markRead(notification)` | Mark single as read |
| `markAllRead(userId)` | Mark all as read |
| `getUnreadCount(userId)` | Count of unread |

---

## NumberToWordsService

Number formatting for Indian financial system.

### Methods
| Method | Purpose |
|--------|---------|
| `toEnglish(int $num)` | "Twelve Lakh Thirty Four Thousand Rupees" |
| `toGujarati(int $num)` | Gujarati equivalent |
| `toBilingual(int $num)` | "English / Gujarati" |
| `formatIndianNumber($num)` | "12,34,567" |
| `formatCurrency($num)` | "₹ 12,34,567" |

---

## PdfGenerationService

Generates quotation comparison PDFs.

### Methods
| Method | Purpose |
|--------|---------|
| `generate(array $data)` | Generate PDF. Three-tier: microservice flag → Chrome headless → microservice fallback. Returns ['success', 'filename', 'path'] or ['error'] |
| `renderHtml(array $data)` | Render complete HTML with embedded fonts, multi-page layout |
| `getTypeLabel(string $type)` | Bilingual customer type label |

### PDF generation strategy
1. If `app.pdf_use_microservice` → microservice only
2. Try Chrome headless `--print-to-pdf`
3. Fallback to microservice (cURL to configurable URL)

### Storage
- `storage/app/pdfs/Loan_Proposal_{customerName}_{date}_{time}.pdf`
- Temp HTML in `storage/app/tmp/`

### Config
- `app.pdf_use_microservice`, `app.pdf_service_url`, `app.pdf_service_key`, `app.chrome_path`

---

## PermissionService

3-tier permission resolution system.

### Methods
| Method | Purpose |
|--------|---------|
| `userHasPermission(user, slug)` | Resolution: super_admin bypass → user override → role grant |
| `userRolesHavePermission(user, slug)` | Check role-level only |
| `getUserPermissions(user)` | All permissions as [slug => bool] |
| `getGroupedPermissions()` | Permissions grouped by group |
| `clearUserCache(user)` | Clear user cache |
| `clearRoleCache()` | Clear role cache |
| `clearAllCaches()` | Clear all |

### Caching
- 5-minute TTL per user/role combination
- Keys: `user_perms:{id}`, `user_role_ids:{id}`, `role_perms:{ids}`

---

## QuotationService

Validates input, generates PDF, saves quotation to DB.

### Methods
| Method | Signature | Purpose |
|--------|-----------|---------|
| `generate()` | `(array $input, int $userId): array` | Full pipeline: validate → compute charges/EMI → generate PDF → save DB |

### Validation
- customerName, customerType: required
- loanAmount: required, > 0, ≤ 1 lakh crore
- banks: required array with roiMin/roiMax > 0, ≤ 30%

### Processing flow
1. Load config (tenures, services, company info)
2. Build template data with charges/EMI per bank per tenure
3. Generate PDF via PdfGenerationService
4. Save Quotation + QuotationBank + QuotationEmi + QuotationDocument in transaction
5. Update bank_charges table
6. Log activity

**Dependencies:** ConfigService, PdfGenerationService

---

## RemarkService

Manages loan stage remarks.

### Methods
| Method | Purpose |
|--------|---------|
| `addRemark(loanId, userId, remark, stageKey)` | Create remark with optional stage context |
| `getRemarks(loanId, stageKey)` | Get remarks, optionally filtered by stage |

---

## StageQueryService

Two-way query system on loan stages.

### Methods
| Method | Purpose |
|--------|---------|
| `raiseQuery(assignment, queryText, userId)` | Create query, notify stage assignee |
| `respondToQuery(query, responseText, userId)` | Add response, mark as 'responded', notify raiser |
| `resolveQuery(query, userId)` | Mark resolved with timestamp |
| `getQueriesForStage(assignment)` | Get all queries with responses |

---

## HasAuditColumns Trait

Auto-fills audit columns on model events.

| Event | Action |
|-------|--------|
| creating | Set `updated_by` if column exists and user authenticated |
| updating | Set `updated_by` if column exists and user authenticated |
| deleting | Set `deleted_by` on soft delete if column exists and user authenticated |

Uses `Schema::hasColumn()` for defensive checking. Uses `saveQuietly()` on delete to avoid triggering update events.

---

## ReportController

Turnaround time reporting with role-based data scoping.

### Methods
| Method | Purpose |
|--------|---------|
| `turnaround()` | Render turnaround report page with filters (bank, product, branch, user, date range) |
| `turnaroundData()` | JSON endpoint for report data: overall TAT and stage-wise TAT |
| `getUserScope(user)` | Determine scope: 'all' (super_admin/admin), 'branch' (BM/BDH), 'self' (others) |
| `applyRoleScope(query, user, scope)` | Apply visibility filters to loan query based on user's scope |

### Data scoping
- **super_admin / admin** → all loans
- **branch_manager / bdh** → loans from user's branches
- **Others** → own loans (created_by or assigned_advisor)

### Period presets
Current Month (default), Last Month, Current Quarter, Last Quarter, Current Year, Last Year, All Time, Custom Range
