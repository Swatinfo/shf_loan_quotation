# Loans

Full loan lifecycle management. CRUD, status changes, visibility scoping, valuations, disbursement, remarks. For the workflow engine itself (stages, phases, transfers) see `workflow-developer.md`.

## Routes & permissions

Complete list: `.claude/routes-reference.md`. Summary:

| Area | Route prefix | Permissions |
|---|---|---|
| CRUD | `/loans` | `view_loans`, `create_loan`, `edit_loan`, `delete_loan` |
| Status | `/loans/{id}/status` | `edit_loan` |
| Stages | `/loans/{id}/stages/...` | `manage_loan_stages`, `transfer_loan_stages`, `skip_loan_stages` |
| Documents | `/loans/{id}/documents` | `manage_loan_documents`, `upload_loan_documents`, `download_loan_documents`, `delete_loan_files` |
| Valuation | `/loans/{id}/valuation` | `manage_loan_stages` |
| Disbursement | `/loans/{id}/disbursement` | `manage_loan_stages` |
| Remarks | `/loans/{id}/remarks` | `view_loans`, `add_remarks` |
| Timeline | `/loans/{id}/timeline` | `view_loans` |
| Transfers history | `/loans/{id}/transfers` | `view_loans` |

## Model

`LoanDetail` — see `.claude/database-schema.md` and `models.md`. Key scopes and accessors:

- `scopeVisibleTo(User)` — visibility rules (see below)
- `scopeActive()` — `status = 'active'`
- `formattedAmount` — `₹ X,XX,XXX` (requested/applied amount, `loan_amount`)
- `formattedSanctionedAmount`, `formattedDisbursedAmount` — Indian-formatted from the `sanctioned_amount` / `disbursed_amount` columns, or `null` when unset. Shown as separate **Sanctioned** and **Disbursed** columns in the loans list and dashboard loans widget (`—` when empty). Columns are populated at write time (docket login / disbursement), not parsed from stage notes.
- `statusLabel`, `statusColor`, `customerTypeLabel`
- `currentStageName`, `currentOwner` (advisor/creator), `currentTaskOwners` (collection of every active-stage assignee — all in-progress parallel sub-stage owners during `parallel_processing`), `currentTaskOwner` (first of those), `timeWithCurrentOwner`, `totalLoanTime`
- `stageBadgeHtml` — HTML badge(s) for the current stage; expands parallel sub-stages with role suffix
- `isBasicEditLocked` — true once the `app_number` stage is completed (basic details become read-only)

Static:
- `LoanDetail::generateLoanNumber()` → `SHF-YYYYMM-NNNN` (zero-padded incrementing)
- `LoanDetail::userRoleSlug($user)` → primary workflow role slug (priority: bank_employee → office_employee → bdh → branch_manager → loan_advisor)
- `LoanDetail::roleSuffix($slug)` → "Bank Review", "Office", "BDH", "SHF"
- `LoanDetail::stageBadgeClass($stageKey)` → CSS class for stage badge

## Visibility scope

`LoanDetail::scopeVisibleTo($query, $user)`:

1. If user has `view_all_loans` permission → sees everything
2. Else OR-union:
   - Own loans (`created_by = user` OR `assigned_advisor = user`)
   - Loans where the user has any `StageAssignment` (currently assigned to a stage)
   - If `branch_manager` or `bdh`: loans in their branches (via `user_branches` pivot)
   - If `bank_employee` or `office_employee`: loans where they appear in `stage_transfers` history (transferred_from or transferred_to)

The DataTable endpoint (`/loans/data`) applies this scope before filtering/pagination.

## Loan status lifecycle

Statuses (from `LoanDetail::STATUS_*` constants):

- `active` — default; workflow stages actionable
- `on_hold` — paused; stages read-only, basic info read-only
- `cancelled` — terminal (soft); can be reactivated by super_admin / admin / branch_manager / bdh
- `rejected` — terminal (from `sanction_decision` or explicit stage rejection); includes `rejected_stage` + `rejection_reason`
- `completed` — terminal success; set by stage flow (OTC clearance complete, or fund-transfer disbursement which skips OTC)

### Status transitions (`LoanController::updateStatus`)

- `active` → `on_hold` (any editor)
- `active` → `cancelled` (super_admin/admin/branch_manager/bdh only)
- `on_hold` → `active`
- `cancelled` → `active` (reactivate, same elevated permission set)
- `rejected` → `active` (reactivate — clears `rejected_*` fields, restores rejected stages to in_progress, recalculates progress)

Every status change sets `status_reason`, `status_changed_at`, `status_changed_by` and is logged to `activity_logs`.

## Create flow

### Via quotation conversion

- Permission: `convert_to_loan`
- UI: `/quotations/{id}/convert`
- Controller: `LoanConversionController@convert`
- Service: `LoanConversionService::convertFromQuotation($q, $bankIndex, $extra)`
- Result: new loan starting at `current_stage = document_collection` (inquiry + document_selection auto-completed)

### Directly (no quotation)

- Permission: `create_loan` + `canCreateLoans()` (super_admin, admin, or any advisor-eligible role)
- UI: `/loans/create`
- Controller: `LoanController@store`
- Service: `LoanConversionService::createDirectLoan($data)`
- Result: new loan starting at `current_stage = inquiry`

### Common steps (both paths)

`LoanConversionService` runs inside a DB transaction:

1. Create/reuse `Customer` record
2. Create `LoanDetail` (fillable from form + some defaults)
3. `LoanDetail::generateLoanNumber()`
4. `LoanStageService::buildWorkflowSnapshot()` frozen into `loan_details.workflow_config`
5. Populate documents (from quotation or config defaults)
6. `LoanStageService::initializeStages()` — creates all `stage_assignments` + `loan_progress`
7. Auto-complete initial stages (quotation conversion only)
8. Auto-assign the first active stage
9. Log `ActivityLog` (loan_created / convert_to_loan)

## Edit flow

`LoanController@update`:
- Permission `edit_loan` + visibility check
- Blocks if `isBasicEditLocked()` (app_number stage completed)
- Same validation as create
- Updates bank_name from bank_id
- Tracks changed fields in `ActivityLog`

## Delete

`LoanController@destroy`:
- Permission: `delete_loan`
- Clears `loan_id` on any linked quotations (they become convertible again)
- Soft-deletes the loan
- Response: `{ success, redirect }`

## Show page

`/loans/{id}` — main loan detail.

Rendered sections (conditional on state):

1. **Ownership & time banner** — current advisor + total loan time
2. **Customer & loan info** (collapsible, closed)
3. **Current stage card** — linked to `/loans/{id}/stages`; progress bar; active query warning if any
4. **Parallel processing sub-stages** — when `current_stage = parallel_processing`, inline list with role suffixes
5. **Documents summary** — progress bar, inline list; on the `document_collection` stage it's prominent + links to `/loans/{id}/documents`
6. **Source quotation** (if converted)
7. **Notes** (if any)
8. **Remarks** — collapsible, AJAX-loaded via `GET /loans/{id}/remarks`, add via `POST`
9. **Status alert boxes** — rejected/on_hold/cancelled/completed banners

Status dropdown: Put on hold / Cancel / Reactivate (permissions-gated).

## Timeline

`/loans/{id}/timeline` — rendered via `LoanTimelineService::getTimeline($loan)` merging:

- Quotation created + converted (if from quotation)
- Loan created (direct)
- Each stage started / completed / skipped / rejected
- Transfers (from → to with reason)
- Queries raised + responses
- Remarks
- Loan rejected
- Disbursement processed
- Loan completed

Each entry: `{type, date, title, description, user, icon, color}`. Ordered by date.

## Documents

See also `workflow-developer.md` (document collection stage) and `.claude/services-reference.md` (`LoanDocumentService`).

### Model

`LoanDocument` — one row per required document. Statuses: `pending` / `received` / `rejected` / `waived`. File-storage fields: `file_path`, `file_name`, `file_size`, `file_mime`, `uploaded_by`, `uploaded_at`.

### Stage interaction

On the `document_collection` stage:
- Stage auto-advances when `LoanDocumentService::allRequiredResolved()` returns true
- Stage soft-reverts if the stage was completed but someone edits docs backward (e.g., marks one received → pending again)

### File storage

`storage/app/loan-documents/{loanId}/{docId}_{timestamp}.{ext}`. Mime + size checked on upload. Max 10 MB. Allowed: pdf, jpg, jpeg, png, webp, doc, docx, xls, xlsx.

## Valuation

`LoanValuationController`.

- `GET /loans/{id}/valuation` — form
- `GET /loans/{id}/valuation-map` — map view (Leaflet.js)
- `POST /loans/{id}/valuation` — upsert
- `GET /api/search-location?q=` — OSM Nominatim forward geocode
- `GET /api/reverse-geocode?lat=&lng=` — OSM reverse

Computes: `land_valuation = land_area × land_rate`, same for construction, `final_valuation = sum`, `market_value = final_valuation`.

**Auto-completes** the `technical_valuation` stage once a valuation exists (pending/in_progress → completed).

**Locked** when loan status ≠ active/on_hold.

## Disbursement

`LoanDisbursementController` + `DisbursementService`. Multi-entry: a disbursement is a list of **tranches** saved over time; the form stays editable until the stage completes.

- `GET /loans/{id}/disbursement` — form (shows Sanctioned / Disbursed-so-far / Remaining strip)
- `POST /loans/{id}/disbursement` — save entries; total mirrored to `loan_details.disbursed_amount` on every save
- `POST /loans/{id}/disbursement/complete` — "Mark as Fully Disbursed" (manual completion when the final total is intentionally below the target)

### Entries (per tranche)

`{disbursement_date, method, product_id/product_name, loan_account_number, amount}` + cheque fields (`cheque_name/number/date`) when `method=cheque`. Method is per entry — mixed NEFT + cheque tranches are allowed. Product dropdown lists only the loan's bank's active products (server-enforced).

Each tranche is also mirrored into the **`disbursement_entries` table** (json entry stores the row's PK as `row_id`): edits update the row in place, removed entries are soft-deleted with `deleted_by`/`deleted_at`, and `is_active` follows the loan status (cancelled/rejected/on_hold → 0, active/completed → 1 — automatic via `LoanDetail` status hook). The json stays the display/read path; the table is for querying (future payout calculation) and audit.

### Completion

- Stage **auto-completes** when entry total ≥ target (`sanctioned_amount` → docket/sanction notes → `loan_amount`); partial totals leave the stage open.
- All entries `fund_transfer` — **skips OTC stage**, marks loan `completed`
- Any `cheque` entry — opens `otc_clearance` stage (cheque handover tracking; OTC lists only the cheque entries)

### Validation (per entry)

- `disbursement_date` required (d/m/Y → stored Y-m-d)
- `method` in fund_transfer|cheque; `amount` numeric ≥1; `loan_account_number` max 50
- `product_id` required, must belong to the loan's bank (name snapshotted into the entry)
- Cheque entries: `cheque_name`, `cheque_number`, `cheque_date` required

**Locked** when loan status ≠ active/on_hold **or the disbursement stage is completed** (entries become read-only).

## Remarks

- `GET /loans/{id}/remarks?stage_key=` — JSON list
- `POST /loans/{id}/remarks` — add (body: `remark`, optional `stage_key`)

`stage_key` nullable → general remark. `Remark::scopeForStage()` returns remarks for that stage OR general (null). `RemarkService::addRemark()` logs a truncated preview.

## Loan listing (DataTable)

`GET /loans/data` — server-side DataTables. Filter fields:

- `status` (default "active"), `customer_type`, `bank_id`, `branch_id`, `role` (admin/mgr only — filters by who currently owns the loan)
- `product_id` — plain `where` on `loan_details.product_id` (loans with null product drop out when active). UI: `lxProduct` select, visible to all roles; options are all active products labeled "Product — Bank" (bank employees with a `task_bank_id` get only their bank's products). Selecting a Bank narrows the product options client-side via `data-bank-id` (stale selection is cleared; Clear resets the cascade).
- `user` (admin/mgr only — `lxUser` dropdown of all active users) — filters by the **current task owner**: loans where the user is the assignee of the current-stage assignment, OR (only while `current_stage = parallel_processing`) the assignee of any in-progress sub-stage. Matches the Task Owner column exactly, including loans with multiple active parallel owners.
- `stage` — top-level stages match `loan_details.current_stage`. **Sub-stage values** (e.g. `bsm_osv`) are detected via `Stage.parent_stage_key` and matched against `stage_assignments.stage_key` with `status = in_progress`. This lets you filter loans currently sitting at a parallel sub-stage even though `current_stage` always shows `parallel_processing` for the parent.
- `docket` — consolidated filter with two flavors:
  - **Date-range options** (`overdue / due_today / due_soon / due_15 / due_month / custom`) operate on an **effective docket date** computed inline as `COALESCE(loan_details.expected_docket_date, app_number.notes.custom_docket_date, today + app_number.notes.docket_days_offset)`. Pre-sanction loans surface using their tentative `today + offset` so users can plan ahead; the authoritative column itself is only written when `sanction` completes (`LoanStageService::handleStageCompletion`).
  - **Commitment-type options** (`s_plus_1 / s_plus_2 / s_plus_3`) match `JSON_UNQUOTE(JSON_EXTRACT(app_number.notes, '$.docket_days_offset'))` against `'1' / '2' / '3'`. Date-agnostic — finds all S+N commitments regardless of where the loan is in the workflow.
  - `custom` pairs with `docket_date` (yyyy-mm-dd) to filter on effective date ≤ that date.
- `date_from` / `date_to`

Search: `loan_number`, `customer_name`, `bank_name`, `customer_phone`, `customer_email`. UI exposes this via the always-visible search input in the results-card header (debounced 250 ms, sent as the standard `search[value]` DataTables param).

**Index page stage dropdown**: non-bank-employees see top-level stages plus `bsm_osv` (a parallel sub-stage). Bank employees see their fixed set (`bsm_osv`, `rate_pf`, `sanction`, `legal_verification`, `esign`). Add another sub-stage to the non-bank-employee dropdown by extending the `orWhere('stage_key', 'bsm_osv')` clause in `LoanController::index` — the backend `whereHas` already handles any sub-stage generically.

Results include formatted amount, docket urgency badges, stage badge (with role suffix), **Owner** (`current_owner` = advisor/creator + time-with-owner), **Task Owner** (`current_task_owners` joined by `, ` → plain text `task_owner_info`; lists every active-stage assignee, i.e. all in-progress parallel sub-stage owners during `parallel_processing`), status, actions (edit/delete per permission).

## Branch manager / BDH notes

- `view_all_loans` is **not** the default for BM/BDH. They see branch loans via the `scopeVisibleTo` OR-union (user_branches pivot).
- Only **super_admin** or users explicitly granted `view_all_loans` see cross-branch data.

## Reports (2026-07-07)

Three report pages, all role-gated (no permission slug) to super_admin/admin/**bdh** (all
branches) and branch_manager (own branches, re-applied server-side); other roles 403.
The old Turnaround Time report was removed — its corrected TAT math lives on as the
Completed-view TAT column in the Pipeline report.

### Loan Pipeline (`/reports/pipeline`)

`ReportController::pipeline/pipelineData`. Clickable status chips (All/Active/On Hold/
Completed/Rejected/Cancelled, count + ₹). Status-adaptive table (default Active):

- **Active**: per-loan stage lines — in-progress (owner, days in stage, days with owner
  from `stage_transfers`, ⚠ open-query count) + **pending sub-stages inside an active
  parallel block** ("queued Nd" from the parent block's `started_at`; unassigned or
  pre-assigned name). Pending future MAIN stages and the parallel container itself are
  excluded. Sorted most-stuck first; aging colors ≤7 / 8–14 / >14 days.
- **On Hold**: reason + since. **Completed**: sanctioned/disbursed ₹ + stage-based TAT.
  **Rejected**: stage/reason/by/date. **Cancelled**: reason/date.
- **Workload tab** (`?tab=workload`): in-progress stages of active loans grouped by
  holder — held, oldest, avg, stuck>7d, stage breakdown.
- Filters: status, bank, product, branch, user (COALESCE advisor/creator), period on
  `created_at`, stage (incl. sub-stages), stuck ≥ N days.

### Management Summary (`/reports/management`)

`ReportController::management/managementData`. Branch + period filters. Four sections:
funnel (quotations → converted → sanctioned → disbursed; counts, ₹, step %, avg days
between milestones), 12-month trend (created/sanctioned/disbursed with CSS mini-bars),
branch & advisor scoreboard (created/active/completed/rejection %/avg TAT/disbursed ₹/
stuck>14d; branch rows expand to advisors), exceptions digest (stages >14d, queries >7d,
holds >30d — thresholds are `ReportController` constants; exceptions ignore the period
filter deliberately: they are current-state).

### Loan Report (`/reports/loans`)

Standalone report of sanctioned/disbursed loans (`ReportController::loanReport/loanReportData`).

- **Access (role-gated, no permission slug)**: super_admin, admin, **bdh** → all branches;
  branch_manager → own `user_branches` branches (re-applied server-side in the data endpoint,
  forged `branch_id` values cannot leak); all other roles → 403 on both endpoints.
- **Status select** (required, default `Sanctioned`, no "All"): `sanctioned` →
  `loan_details.sanctioned_amount IS NOT NULL`; `disbursed` → `disbursed_amount IS NOT NULL`.
- **Filters**: bank, product, branch, user (`COALESCE(assigned_advisor, created_by)`),
  period/date range on loan `created_at` (same presets as the other report pages).
- **Columns**: Loan #, Customer, Bank/Product, Branch, Advisor, Loan Amount, Sanctioned ₹,
  Disbursed ₹, Sanctioned On / Disbursed On (completed `sanction`/`disbursement`
  stage-assignment `completed_at`), Status badge. Totals strip: count + ₹ sums (Indian format).
- Frontend: `newtheme/reports/loan-report.blade.php` + `public/newtheme/pages/loan-report.{css,js}`
  (fetch + inline loader, not the global overlay). Nav: header Reports dropdown + bottom-nav
  (role-gated entry). Tests: `tests/Feature/LoanReportTest.php`.

## See also

- `workflow-developer.md` — stage engine internals
- `workflow-guide.md` — user-facing stage walkthrough
- `user-assignment.md` — how users get bound to stages
- `.claude/services-reference.md` — `LoanConversionService`, `LoanStageService`, `LoanDocumentService`, `DisbursementService`, `LoanTimelineService`, `RemarkService`
