# Quotations

Quotations are the core feature of SHF — bilingual loan comparison proposals that compare EMI, charges, and required documents across multiple banks. A quotation generates a PDF and can later be converted into a loan task.

## Creation Flow

The create form (`quotations/create.blade.php`) has 5 sections:

1. **Location** — mandatory city selection (filtered by user's assigned locations; admin/super_admin see all)
2. **Customer Information** — customer name + customer type (proprietor, partnership_llp, pvt_ltd, salaried, all)
3. **Required Documents** — auto-populated based on customer type, bilingual (EN + GU)
4. **Loan Details** — loan amount, tenure selection (from config, default 5/10/15/20 years), prepared-by info
5. **Bank Selection & EMI** — add multiple banks with ROI range, charges, and auto-calculated EMI
6. **Additional Notes** — free-text notes, "Our Services" text

### Bank Selection
- Banks are loaded from `banks` table (active only) and filtered by selected location via `bank_location` pivot
- Each bank entry includes: ROI min/max, processing fee (PF), admin charge, stamp/notary, registration fee, advocate fees, IOM charge, TC report, plus 2 custom extra charge fields
- Bank charges from previous quotations are persisted in `bank_charges` table via `BankCharge::updateOrCreate()` for auto-fill

### EMI Calculation
EMI is calculated client-side using the standard **reducing balance formula**:

```
EMI = P * r * (1 + r)^n / ((1 + r)^n - 1)
```

Where:
- `P` = principal (loan amount)
- `r` = monthly interest rate (annual ROI / 12 / 100)
- `n` = total months (tenure years * 12)

Derived values:
- `totalPayment = EMI * n`
- `totalInterest = totalPayment - P`

EMI is computed for each selected tenure and each bank, using the bank's max ROI.

### Document Selection
Documents are loaded from config based on customer type. Each document has bilingual names (English + Gujarati). The `customerType` value determines which document set is shown. Type `all` shows the union of partnership_llp and pvt_ltd documents.

## Validation Rules

Validation is performed in `QuotationService::generate()` (inline, not Form Request):

| Field | Rule |
|-------|------|
| `customerName` | Required, non-empty string |
| `customerType` | Required, non-empty string |
| `loanAmount` | Required, integer > 0, max 1,00,00,00,00,000 (1 lakh crore) |
| `banks` | Required, non-empty array |
| `banks[].roiMin` | Required, > 0, <= 30 |
| `banks[].roiMax` | Required, > 0, <= 30, >= roiMin |

## Database Persistence

Quotation data is saved in a DB transaction across 4 tables:

### `quotations` (parent)
| Column | Type | Notes |
|--------|------|-------|
| `user_id` | FK | Creator |
| `loan_id` | FK, nullable | Set when converted to loan |
| `customer_name` | string | |
| `customer_type` | string | proprietor, partnership_llp, pvt_ltd, salaried, all |
| `loan_amount` | integer | |
| `pdf_filename` | string | e.g. `Loan_Proposal_John_2026-04-13_14_30_00.pdf` |
| `pdf_path` | string | Full filesystem path |
| `additional_notes` | text, nullable | |
| `prepared_by_name` | string, nullable | Auto-filled from auth user if empty |
| `prepared_by_mobile` | string, nullable | Auto-filled from auth user if empty |
| `selected_tenures` | JSON (cast to array) | e.g. [5, 10, 15, 20] |
| `location_id` | FK, nullable | City location |

Uses `SoftDeletes` and `HasAuditColumns` traits.

### `quotation_banks` (one per bank compared)
| Column | Type | Notes |
|--------|------|-------|
| `quotation_id` | FK | |
| `bank_name` | string | |
| `roi_min` | decimal(2) | |
| `roi_max` | decimal(2) | |
| `pf_charge` | decimal(2) | Stored as percentage |
| `admin_charge` | integer | Base amount |
| `stamp_notary` | integer | |
| `registration_fee` | integer | |
| `advocate_fees` | integer | |
| `iom_charge` | integer | |
| `tc_report` | integer | |
| `extra1_name` / `extra1_amount` | string/integer | Custom charge 1 |
| `extra2_name` / `extra2_amount` | string/integer | Custom charge 2 |
| `total_charges` | integer | Sum of all charges |

### `quotation_emi` (one per bank per tenure)
| Column | Type |
|--------|------|
| `quotation_bank_id` | FK |
| `tenure_years` | integer |
| `monthly_emi` | integer |
| `total_interest` | integer |
| `total_payment` | integer |

### `quotation_documents` (one per document)
| Column | Type |
|--------|------|
| `quotation_id` | FK |
| `document_name_en` | string |
| `document_name_gu` | string |

## Generation Flow

1. Client submits form via AJAX to `POST /quotations/generate`
2. `QuotationController::generate()` auto-fills prepared-by from auth user
3. `QuotationService::generate()` validates input, builds template data
4. Tenures are intersected: client-selected tenures filtered against config-allowed tenures
5. `PdfGenerationService::generate()` renders HTML and converts to PDF (see `.docs/pdf-generation.md`)
6. DB transaction saves quotation + banks + EMIs + documents
7. `BankCharge::updateOrCreate()` caches latest charges per bank name
8. Returns JSON with `filename`, `id`, and optional `warning` if DB save failed (PDF still returned)

## PDF Download

`QuotationController::download()` uses a three-step fallback:
1. Look up `storage/app/pdfs/{pdf_filename}`; fallback to `pdf_path`
2. Auto-find PDF on disk by glob matching `Loan_Proposal_{customerName}_{date}*`
3. Regenerate PDF from stored quotation data as last resort

## Quotation-to-Loan Conversion

Handled by `LoanConversionController` + `LoanConversionService`.

### Convert Form (`quotations/convert.blade.php`)
Shows a summary of the quotation, then collects:
- **Bank selection** — radio buttons for each compared bank (with ROI and charges shown)
- **Branch** — required, from `branches` table
- **Product** — required, from `products` table (filtered by selected bank)
- **Customer phone** — required
- **Customer email** — optional
- **Date of birth** — required, dd/mm/yyyy format (Bootstrap Datepicker)
- **PAN card number** — required, validated regex `ABCDE1234F`
- **Assigned advisor** — user with workflow role
- **Notes** — optional

### Conversion Validation
```php
'bank_index'      => 'required|integer|min:0',
'branch_id'       => 'required|exists:branches,id',
'product_id'      => 'required|exists:products,id',
'customer_phone'  => 'required|string|max:20',
'customer_email'  => 'nullable|email|max:255',
'date_of_birth'   => 'required|date_format:d/m/Y',
'pan_number'      => 'required|string|size:10|regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/i',
'assigned_advisor' => 'required|exists:users,id',
'notes'           => 'nullable|string|max:5000',
```

### What Conversion Does
- Creates a `LoanDetail` record linked to the quotation
- Sets `quotation.loan_id` to the new loan
- Copies customer info, selected bank, documents from quotation
- Loan enters the workflow at the Inquiry stage

## Deletion

- `DELETE /quotations/{quotation}` via `QuotationController::destroy()`
- Blocked if quotation has been converted to a loan (`loan_id` is set)
- Deletes PDF files from disk + soft-deletes the DB record
- Logs `delete_quotation` activity

## Permissions

| Action | Permission Required | Route Middleware |
|--------|-------------------|------------------|
| Create quotation | `create_quotation` | `permission:create_quotation` |
| View own quotation | (any authenticated user) | — |
| View all quotations | `view_all_quotations` | checked in controller |
| Download PDF | `download_pdf` | checked in view |
| Delete quotation | `delete_quotations` | `permission:delete_quotations` |
| Convert to loan | `convert_to_loan` | `permission:convert_to_loan` |

## Model Relationships

```
Quotation
  ├── belongsTo User (creator)
  ├── belongsTo LoanDetail (via loan_id, nullable)
  ├── belongsTo Location
  ├── hasMany QuotationBank
  │     └── hasMany QuotationEmi
  └── hasMany QuotationDocument
```

## Key Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/QuotationController.php` | CRUD + PDF download |
| `app/Http/Controllers/LoanConversionController.php` | Quotation-to-loan conversion |
| `app/Services/QuotationService.php` | Validation, PDF trigger, DB persistence |
| `app/Services/LoanConversionService.php` | Conversion business logic |
| `app/Services/PdfGenerationService.php` | HTML template + Chrome/microservice PDF |
| `app/Models/Quotation.php` | Parent model (SoftDeletes, HasAuditColumns) |
| `app/Models/QuotationBank.php` | Bank comparison data |
| `app/Models/QuotationEmi.php` | EMI entries per bank per tenure |
| `app/Models/QuotationDocument.php` | Required documents (bilingual) |
| `app/Models/BankCharge.php` | Cached bank charges for auto-fill |
| `resources/views/quotations/create.blade.php` | Creation form |
| `resources/views/quotations/show.blade.php` | Detail view |
| `resources/views/quotations/convert.blade.php` | Conversion form |
