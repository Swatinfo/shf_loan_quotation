# Quotation System

## Overview

The core feature of the application. Users create loan proposal quotations that compare multiple banks' ROIs, EMIs, and charges. Each quotation generates a bilingual PDF and stores all data in the database.

## Flow

```
User fills form → POST /quotations/generate (JSON)
  → QuotationController@generate()
    → QuotationService::generate()
      → Validates inputs
      → Processes bank charges + EMI calculations
      → PdfGenerationService::generate() → PDF file
      → DB transaction: save quotation + banks + EMIs + documents
      → Update bank_charges reference table
    → Returns JSON { success, filename, id }
```

## Controller: QuotationController

**File**: `app/Http/Controllers/QuotationController.php`

**Constructor Dependencies**: `ConfigService`, `QuotationService`

### Create Form (`GET /quotations/create`)

**Permission**: `create_quotation`

- Loads app config via `ConfigService::load()`
- Passes `$config` and `$user` to `quotations.create` view
- View renders a multi-section form:
  1. Customer Information (name, type)
  2. Documents checklist (dynamic per customer type)
  3. Loan Amount input
  4. Bank selection + ROI/charges per bank
  5. Tenure selection
  6. Additional notes
  7. Generate button

### Generate PDF (`POST /quotations/generate`)

**Permission**: `generate_pdf`

**Request Format**: JSON POST

**Auto-populated fields**:
- `preparedByName` ← `auth()->user()->name`
- `preparedByMobile` ← `auth()->user()->phone`

**Process**:
1. Merges auth user data into request input
2. Calls `QuotationService::generate($input, auth()->id())`
3. On success: logs activity, returns JSON `{ success: true, filename, id, warning? }`
4. On failure without filename: returns 422 `{ success: false, error }`
5. On failure with filename (PDF created but DB failed): returns JSON with error + filename

**Activity Log**: `create_quotation` with properties `{ customer_name, loan_amount, filename }`

### Show Quotation (`GET /quotations/{quotation}`)

**Permission**: `auth` (all authenticated users)

**Authorization Logic**:
- If user owns the quotation → allowed
- If user has `view_all_quotations` permission → allowed
- Otherwise → 403

**Data Loaded**:
- Quotation with eager-loaded: `banks.emiEntries`, `documents`, `user`
- Config loaded for display context

### Download PDF (`GET /quotations/{quotation}/download`)

**Permission**: `download_pdf`

**File Resolution** (in order):
1. `$quotation->pdf_path` (absolute path stored in DB)
2. `storage_path('app/pdfs/' . $quotation->pdf_filename)` (default storage location)
3. If neither exists → 404

**Response**: Streamed file download with `application/pdf` content type

### Download by Filename (`GET /download-pdf?filename=...`)

**Permission**: `download_pdf`

Legacy endpoint for JS compatibility. Uses `basename()` to sanitize the filename parameter.

### Delete Quotation (`DELETE /quotations/{quotation}`)

**Permission**: `delete_quotations`

**Authorization**: Same as show (own or view_all)

**Process**:
1. Deletes PDF file from `pdf_path` and `storage/app/pdfs/`
2. Deletes quotation record (cascades to banks, EMIs, documents)
3. Logs activity: `delete_quotation`
4. Returns JSON (if AJAX) or redirect with success message

## Service: QuotationService

**File**: `app/Services/QuotationService.php`

### generate(array $input, int $userId): array

**Input Validation**:
| Field | Validation |
|-------|-----------|
| `customerName` | Required, non-empty, trimmed |
| `customerType` | Required, non-empty |
| `loanAmount` | Required, > 0, <= 1,000,000,000,000 (1 lakh crore) |
| `banks` | Required, non-empty array |
| `banks[].bankName` | Required per bank |
| `banks[].roiMin` | Required, > 0, <= 30 |
| `banks[].roiMax` | Required, >= roiMin, <= 30 |
| `selectedTenures` | Optional, validated against config tenures |
| `documents` | Optional array |
| `additionalNotes` | Optional, trimmed |

**Bank Charge Processing** (per bank):
```
For each bank:
  - pf (processing fee percentage)
  - pfPercent (boolean: is PF a percentage?)
  - admin / adminBase (admin charge, may be base amount)
  - stamp_notary (stamp & notary fees)
  - registration_fee
  - advocate_fees
  - iom_charge (calculated from config thresholds)
  - tc_report
  - extra1_name / extra1_amount (custom charge 1)
  - extra2_name / extra2_amount (custom charge 2)
  - total_charges (sum of all above)
```

**IOM Charge Calculation**:
```
If loanAmount <= thresholdAmount:
    iom = fixedCharge
Else:
    iom = fixedCharge + (loanAmount - thresholdAmount) * percentageAbove / 100
```

**EMI Calculation** (per bank, per tenure):
```
Monthly rate = ROI / 12 / 100  (uses average of roiMin and roiMax)
Months = tenure_years * 12
EMI = Principal * rate * (1+rate)^months / ((1+rate)^months - 1)
Total payment = EMI * months
Total interest = Total payment - Principal
```

**Database Transaction**:
1. Creates `Quotation` record
2. For each bank → creates `QuotationBank` record
3. For each bank × tenure → creates `QuotationEmi` record
4. For each document → creates `QuotationDocument` record
5. Updates `BankCharge` reference table via `updateOrCreate`

**Return Values**:
- Success: `['success' => true, 'quotation' => Quotation]`
- Validation error: `['error' => 'message']`
- PDF error with filename: `['success' => false, 'error' => '...', 'filename' => '...']`

## Database Tables

### quotations
| Column | Type | Purpose |
|--------|------|---------|
| loan_id | FK → loan_details (nullable) | Back-reference when converted to loan |
| customer_name | string | Customer's name |
| customer_type | string | proprietor, partnership_llp, pvt_ltd, salaried, all |
| loan_amount | unsigned bigint | Loan amount in INR |
| pdf_filename | string (nullable) | Generated PDF filename |
| pdf_path | string (nullable) | Absolute path to PDF file |
| additional_notes | text (nullable) | Free-text notes |
| prepared_by_name | string (nullable) | Creator's name |
| prepared_by_mobile | string (nullable) | Creator's phone |
| selected_tenures | JSON | Array of tenure years, e.g. [5, 10, 15, 20] |

### quotation_banks
| Column | Type | Purpose |
|--------|------|---------|
| bank_name | string | Name of the bank |
| roi_min | decimal(5,2) | Minimum ROI % |
| roi_max | decimal(5,2) | Maximum ROI % |
| pf_charge | decimal(5,2) | Processing fee % |
| admin_charge | unsigned bigint | Admin charge amount |
| stamp_notary | unsigned bigint | Stamp & notary |
| registration_fee | unsigned bigint | Registration fee |
| advocate_fees | unsigned bigint | Advocate fees |
| iom_charge | unsigned bigint | IOM stamp paper charge |
| tc_report | unsigned bigint | TC report charge |
| extra1_name/amount | string/unsigned bigint | Custom charge 1 |
| extra2_name/amount | string/unsigned bigint | Custom charge 2 |
| total_charges | unsigned bigint | Sum of all charges |

### quotation_emi (NOTE: table name is `quotation_emi`, not `quotation_emis`)
| Column | Type | Purpose |
|--------|------|---------|
| tenure_years | integer | Tenure in years |
| monthly_emi | unsigned bigint | Monthly EMI amount |
| total_interest | unsigned bigint | Total interest payable |
| total_payment | unsigned bigint | Total amount payable |

### quotation_documents
| Column | Type | Purpose |
|--------|------|---------|
| document_name_en | string | Document name in English |
| document_name_gu | string | Document name in Gujarati |

## Views

### quotations/create.blade.php
Multi-section form with numbered steps:
1. Customer Information (name + type dropdown with bilingual labels)
2. Required Documents (dynamic checklist based on customer type, bilingual)
3. Loan Amount (with Indian formatting preview)
4. Bank Details (add/remove banks, each with ROI min/max + all charge fields)
5. Tenure Selection (chip/pill buttons from config)
6. Additional Notes (textarea)
7. Generate button → AJAX POST → redirects to show page on success

### quotations/show.blade.php
- Customer details card
- Loan amount with formatted display
- Bank comparison tables
- EMI tables by tenure
- Charges comparison table
- Required documents list
- Download PDF button (if file exists + permission)
- Delete button (if permission)
- **Convert to Loan** button (if `convert_to_loan` permission and not already converted)
- **View Loan** link (if already converted, links to loan show page)

### quotations/convert.blade.php
3-step conversion form (converts a quotation into a loan task):
1. Summary — shows quotation details (customer, amount, banks)
2. Select Bank — radio card selection for which bank to create the loan with
3. Review & Convert — branch/product/advisor selection, dependent dropdowns
- Permission: `convert_to_loan`
- Uses `LoanConversionService::convertFromQuotation()`
- Auto-initializes stages, copies documents, auto-completes stages 1-2
