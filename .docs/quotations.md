# Quotations

Comparison quotations across multiple banks, with EMI calculations, per-bank charges, and bilingual required-documents list. Output is a PDF; records are persisted for retrieval and later conversion into a loan.

## Routes

See `.claude/routes-reference.md`. Key:

- `GET /quotations` — listing page (`quotations.index`); access if user has any of `view_own_quotations`, `view_all_quotations`, `create_quotation`. Page header carries the `+ New Quotation` button (gated `create_quotation`). Uses the existing `dashboard.quotation-data` endpoint.
- `GET /quotations/create` — form (permission: `create_quotation`)
- `POST /quotations/generate` — create + render + save (permission: `generate_pdf`)
- `GET /quotations/{id}` — show (visibility: `Quotation::isVisibleTo($user)` — see "Visibility" below)
- `GET /quotations/{id}/download?branded=1|0` — PDF download (permissions: `download_pdf` + branded/plain variant if configured)
- `GET /quotations/{id}/preview-html?branded=1|0` — HTML preview (super_admin only, debugging)
- `DELETE /quotations/{id}` — delete (permission: `delete_quotations`)
- `POST /quotations/{id}/hold` — put on hold + auto-create follow-up DVR (permission: `hold_quotation`)
- `POST /quotations/{id}/cancel` — cancel (terminal) (permission: `cancel_quotation`)
- `POST /quotations/{id}/resume` — resume from on-hold (permission: `resume_quotation`)
- `GET /quotations/{id}/convert`, `POST /quotations/{id}/convert` — conversion flow (permission: `convert_to_loan`)

Show page accepts `?action=hold` or `?action=cancel` — the corresponding modal auto-opens (used by dashboard shortcut buttons).

## Visibility

`Quotation::scopeVisibleTo($user)` (mirrors `LoanDetail`):

1. `view_all_quotations` → see everything (admin / super_admin)
2. Own (`user_id === $user->id`)
3. `branch_manager` / `bdh` → also any quotation where `branch_id` is in `$user->branches()->pluck('branches.id')`

`isVisibleTo($user)` is the single-record helper used by controller auth checks (`show`, `download`, `destroy`, `authorizeMutation`, `LoanConversionController@showConvertForm`). Dashboard list + stats use `Quotation::visibleTo($user)`.

**`branch_id` on create**: `QuotationService::generate()` falls back to `User::find($userId)?->default_branch_id` if the form did not supply one, so every quotation has a branch and scope queries never drop rows.

## Controller

`QuotationController`, constructor-injects `ConfigService` + `QuotationService` + `NotificationService`.

## Data model

- **`quotations`** — header row with customer + loan amount + prepared-by info + `selected_tenures` (JSON array) + `pdf_filename`/`pdf_path` + optional `loan_id` back-link (set when converted). Also `referral_name` + `referral_type` (config-driven vocab `quotationReferralTypes`, see "Referral" below).
- **`quotation_banks`** — one row per selected bank per quotation: ROI range, per-bank charges (PF %, admin, stamp/notary, registration fee, advocate, IOM charge, TC, two configurable extras).
- **`quotation_emi`** — one row per (bank × tenure): monthly EMI, total interest, total payment.
- **`quotation_documents`** — bilingual document list (EN + GU) per quotation.
- **`customers`** — not created on quotation creation (quotation customer data is stored inline on the `quotations` row). Customer identity is established only at **loan creation**, keyed by PAN, with a per-loan `customer_kyc_details` snapshot. Quotations are NOT linked to customers. See `.docs/customers.md`.

See `.claude/database-schema.md` for full column list.

## Create flow — `QuotationService::generate(array $input, int $userId)`

All heavy lifting is in `app/Services/QuotationService.php`. Controller calls once with the form payload.

### Input validation

Throws `['error' => ...]` on fail:
- `customerName`, `customerType`, `loanAmount` required
- `loanAmount` ≤ 10^12 (1 lakh crore hard cap)
- `banks[]` required, non-empty array
- Per bank: `roiMin`, `roiMax` in (0, 30], `roiMin ≤ roiMax`

### Steps

1. Load config via `ConfigService::load()` (company info, tenures, gst, etc.)
2. Filter `$input['selectedTenures']` to keep only values that exist in config tenures
3. Build the **template data** — customer info, loan info, date, company info, per-bank breakdown:
   - Calculate EMI by tenure (standard reducing-balance formula, rounded to rupees)
   - Extract + validate charges per bank
4. Call `PdfGenerationService::generate($templateData)` — see `pdf-generation.md`
5. DB transaction:
   - Create `Quotation`
   - For each bank: create `QuotationBank`, then `QuotationEmi` per tenure
   - For each document pair: create `QuotationDocument`
6. Call `updateBankCharges($banks)` — upserts `bank_charges` by bank_name with the latest values for future pre-fill
7. Return `['success' => true, 'quotation' => $quotation]`

### Partial-success case

If PDF generation succeeds but DB save fails, the service returns `['success' => false, 'error' => ..., 'filename' => 'Loan_Proposal_*.pdf']`. The controller surfaces this so the user still has the PDF path.

## EMI calculation

Standard reducing-balance monthly EMI: `P × r × (1+r)^n / ((1+r)^n − 1)` where:

- `P` = loan amount
- `r` = monthly rate = `(roiMin + roiMax)/2 / 12 / 100` (midpoint of the rate range, converted to monthly)
- `n` = tenure in months (years × 12)

Calculated client-side on `/quotations/create` for preview; re-calculated server-side in `QuotationService::generate` for the canonical values stored in `quotation_emi`.

## IOM charge (config-driven)

IOM stamp-paper charge depends on loan amount:

- If `loanAmount <= iomCharges.thresholdAmount` → `iomCharges.fixedCharge`
- Else → `loanAmount × iomCharges.percentageAbove / 100`

Thresholds stored in `config/app-defaults.php` and editable via `/settings` (Charges tab).

## PDF variants

Two variants (gated by distinct permissions `download_pdf_branded` / `download_pdf_plain`):

- **Branded** — full SHF branding, logo, company info header/footer. Cached on disk in `storage/app/pdfs/` and path stored on the quotation for re-download.
- **Plain** — stripped branding (for sharing with banks directly). Always regenerated, not cached.

`QuotationController::download`:
- Plain: `regeneratePdf($q, false)` every time
- Branded: use cached path/filename if present, else regenerate and persist

`QuotationController::downloadByFilename` exists for legacy `/download-pdf?file=...` URLs.

## Currency formatting

- Display: Indian format `₹ X,XX,XXX` via `SHF.formatIndianNumber()` (JS) and `LoanDetail::formattedAmount` / `NumberToWordsService::formatCurrency` (PHP)
- Words: `SHF.bilingualAmountWords(num)` → `"Twelve Lakh Rupees / બાર લાખ રૂપિયા"`
- Form inputs: `.shf-amount-wrap` with visible `.shf-amount-input` (formatted) + hidden `.shf-amount-raw` (integer)

## Conversion to loan

Controller: `LoanConversionController`.

- `GET /quotations/{id}/convert` — shows the convert form (blocks if already converted)
- `POST /quotations/{id}/convert` — validation:
  - `bank_index` required int ≥0 (index into the quotation's banks)
  - `product_id`, `customer_phone`, `date_of_birth` (d/m/Y), `pan_number` (regex `[A-Z]{5}[0-9]{4}[A-Z]` uppercased), `assigned_advisor` — required
  - `customer_email` nullable email, `notes` nullable
- Calls `LoanConversionService::convertFromQuotation(Quotation, int $bankIndex, array $extra)`

See `loans.md` and `workflow-developer.md` for the conversion side-effects.

## UI surfaces

- `/quotations/create` — tabbed form (location/branch → customer → banks → loan details → documents)
- `/quotations/{id}` — detail page with per-bank comparison + PDF download buttons + convert button
- `/quotations/{id}/convert` — conversion form (pre-filled from quotation + user profile)

Quotations listing is on the **dashboard** — there's no standalone `/quotations` index. The dashboard has "Pending Quotations" and similar tabs (see `dashboard.md`).

## Editing a quotation (2026-05-07)

Quotations are editable until they are converted or cancelled.

### Routes (all gated by `permission:edit_quotation`)

- `GET /quotations/{quotation}/edit` — `quotations.edit` — full create-form clone, prefilled from the saved quotation via `window.SHF_QUOTATION_PREFILL` injected from the blade.
- `PUT /quotations/{quotation}` — `quotations.update` — same validation surface as `generate()`. Replaces banks/EMIs/documents wholesale, regenerates the cached PDF, redirects to show.

### Editor authority — `Quotation::isEditableBy(User $user): bool`

Returns true iff **all** of:
1. `$this->is_converted === false` (no `loan_id`)
2. `$this->status !== STATUS_CANCELLED`
3. The user passes one of:
   - `super_admin` role — always
   - `edit_quotation` permission AND `user_id === $user->id` (creator)
   - `edit_quotation` permission AND `branch_manager` / `bdh` role AND quotation's `branch_id` is in `$user->branches()`
   - `edit_quotation` permission AND `view_all_quotations` permission (admin convenience)

Conversion gate is absolute — even super_admin can't edit a converted quotation. Same rule as `destroy()` and `LoanConversionController::convert()`.

### Service contract

`QuotationService::update(Quotation $q, array $input): array` mirrors `generate()`:
- Same `validateInput()` helper.
- Re-runs `buildTemplateDataFromInput()`.
- Inside transaction: re-checks `is_converted` (last-write-wins on a stale form; throws if someone converted between page load and submit), updates header columns, deletes existing `quotation_banks` (cascades to `quotation_emi`) + `quotation_documents`, calls `persistBanksEmisDocuments()` to rebuild from the new payload.
- Outside transaction: deletes the previous PDF artifact via `cleanupOldPdf()`, calls `updateBankCharges()` so the next quotation auto-fills with the latest charges.

## Document strike-out (2026-05-07)

The full master document list for the customer type is persisted on every quotation. Each row carries `is_excluded` (bool) and `sequence` (uint).

- **Create form** — checkboxes; uncheck applies a `.shf-doc-struck` class so the row stays visible with line-through styling. `getDocumentRows()` posts every row with its `excluded` flag.
- **Edit form** — prefills the doc grid with the saved excluded flags so the operator sees their previous strike-outs.
- **Show page** — read-only: renders all rows including struck ones (line-through). No toggle button — strike-off is set during create or edit only.
- **PDF render** — `buildTemplateDataFromInput()` filters `excluded === true` before passing `documents` to `PdfGenerationService`. The internal `documentsAll` key carries every row for persistence; the `documents` key (used by the PDF) carries only included ones. Excluded docs **never appear** in the rendered PDF.
- **Loan conversion ignores `is_excluded`** — `LoanDocumentService::populateFromQuotation()` iterates every `quotation_documents` row regardless of the strike-out flag and creates each as `loan_documents.is_required = true`. Strike-out is cosmetic on the quotation PDF only. The loan team must still collect every doc that was on the master list for actual disbursement, so no information is lost when a quotation is converted. Locked in by `tests/Feature/LoanConversionDocumentTest.php`.

## Referral (2026-05-29)

Captures who referred the customer. Two nullable columns on `quotations`:

- `referral_name` — free-text name of the referrer.
- `referral_type` — config-driven category key from `quotationReferralTypes` (`app_config.main`, defaults in `config/app-defaults.php`: existing_customer, walk_in, dsa, builder, ca, staff, other). Editable at `/settings` → "Quotation Referral" tab (`settings.quotation-referral-types`). Label resolved via `Quotation::referral_type_label` accessor.

Captured on the create/edit form (Section 1 — Customer Information), flows through `QuotationService::generate()`/`update()` via `referralName`/`referralType` input keys, prefilled on edit via `SHF_QUOTATION_PREFILL`. Shown on the quotation show page (Customer & Loan Details). Not printed on the PDF. Dashboard quotations list shows a "Ref: …" sub-line under the customer name and includes `referral_name` in the free-text search. Locked by `tests/Feature/QuotationReferralTest.php`.

## Status lifecycle (hold / cancel / resume)

Each quotation has a `status` column (`active` / `on_hold` / `cancelled`) that's independent of conversion (`loan_id`).

- **Hold** (`POST /quotations/{id}/hold`) — requires `reason_key` (from config `quotationHoldReasons`), optional `note`, required `follow_up_date` (d/m/Y, future). Sets `status=on_hold`, stores reason/note/date + `held_by` + `held_at`. Auto-creates a `DailyVisitReport` linked via `quotation_id` with `purpose=follow_up`, `contact_type=existing_customer`, and the supplied follow-up date. Also fires a notification to the original `user_id` (creator) if someone else performed the action.
- **Cancel** (`POST /quotations/{id}/cancel`) — requires `reason_key` (from config `quotationCancelReasons`), optional `note`. Terminal: cancelled quotations cannot be resumed or converted. Blocks conversion via `LoanConversionController::convert()`.
- **Resume** (`POST /quotations/{id}/resume`) — only from `on_hold`. Clears all `hold_*` columns, sets `status=active`.

**Reason vocab** is config-driven (same pattern as DVR contact types): `quotationHoldReasons` and `quotationCancelReasons` in `app_config.main`, editable at `/settings` → "Quotation Reasons" tab, defaults in `config/app-defaults.php`. Each reason has a `group` field (e.g. `Documents`, `Rate / Pricing`, `Customer`) — the show-page modals render these as HTML `<optgroup>` blocks so long dropdowns stay scannable. Missing/blank `group` falls back to `Other`.

**Dashboard filter** — the Quotations tab has a status filter (`Active + On Hold` by default, plus `Active`, `On Hold`, `Cancelled`, `All`). Held and cancelled rows get coloured backgrounds.

## Soft delete / conversion gate

- `Quotation` uses `SoftDeletes` + `HasAuditColumns`.
- `destroy()`: blocks if `isConverted()` (quotation has `loan_id`).
- Linked `loan_details.quotation_id` is set to null on loan delete, so the quotation becomes convertible again.
- `LoanConversionController` also blocks conversion if the quotation is `cancelled`.

## Offline generation

PWA caches quotation form state in IndexedDB. `public/js/pdf-renderer.js` can generate a client-side PDF via `window.print()` when the user is offline. On reconnect, `/api/sync` flushes pending quotations to the server. See `offline-pwa.md`.

## Surface checklist (before touching the quotation flow)

1. Read `pdf-generation.md` for template + fallback logic
2. Read `.claude/services-reference.md` for `QuotationService` and `PdfGenerationService` method signatures
3. Don't bypass `ConfigService` for config values
4. Don't calculate charges inline — reuse `QuotationService`'s computations; if you need new charge logic, add it to the service
