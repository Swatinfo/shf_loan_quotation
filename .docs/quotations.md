# Quotations

## Overview

Quotations compare loan offers across multiple banks for a customer, generating a bilingual (English/Gujarati) PDF with EMI calculations, charges breakdown, and required documents.

## Models

- **Quotation** — main record with customer info, loan amount, PDF path
- **QuotationBank** — per-bank charges and ROI
- **QuotationEmi** — EMI calculations per bank per tenure
- **QuotationDocument** — bilingual document list

## Creation Flow

### Controller: `QuotationController`
- `create()` — loads config, user's locations, banks with location mapping, user's branches
- `generate()` — validates input, calls `QuotationService::generate()`, returns JSON with filename

### Service: `QuotationService::generate()`

1. **Validate input:**
   - customerName, customerType: required
   - loanAmount: required, > 0, max 1 lakh crore
   - banks array: required, each with roiMin/roiMax > 0 and ≤ 30%, roiMin ≤ roiMax

2. **Build template data:**
   - Load config (tenures, company info, services)
   - Resolve tenures from selectedTenures or config defaults
   - Calculate charges per bank (PF %, admin, stamp/notary, registration, advocate, IOM, TC, extras)
   - Calculate EMI per bank per tenure (standard reducing balance formula)
   - Collect documents (bilingual)

3. **Generate PDF** via `PdfGenerationService::generate()`

4. **Save to DB** in transaction:
   - Quotation record
   - QuotationBank records with charges
   - QuotationEmi records per tenure per bank
   - QuotationDocument records

5. **Update bank_charges** table for future reference

## Charges Calculation

- **PF (Processing Fee):** percentage of loan amount
- **IOM charges:** threshold-based — fixed charge below threshold, percentage above (configurable)
- **GST:** configurable percentage (default 18%) applied to PF
- **Other charges:** admin, stamp/notary, registration fee, advocate, TC report, up to 2 custom extras

## Customer Types

| Key | English | Gujarati |
|-----|---------|----------|
| proprietor | Proprietor | માલિકી |
| partnership_llp | Partnership/LLP | ભાગીદારી/LLP |
| pvt_ltd | Pvt. Ltd. | પ્રા. લિ. |
| salaried | Salaried | પગારદાર |

Each type has different document requirements defined in `config/app-defaults.php`.

## View: `quotations/create.blade.php`

Multi-step form with:
- Customer name, type selection, loan amount with Indian formatting
- Location and branch selection
- Bank selection with ROI and charge inputs per bank
- Document list auto-loaded by customer type (can add custom docs)
- Additional notes textarea
- Prepared-by name and mobile (auto-filled from auth user)
- Tenure selection chips

## View: `quotations/show.blade.php`

- Customer & loan details
- Banks with EMI comparison tables
- Charges breakdown per bank
- Document list
- Download buttons (branded/plain PDF) based on permissions
- Convert to Loan button (if not already converted)
- Delete button

## Quotation → Loan Conversion

See `loans.md` for conversion flow via `LoanConversionController`.

## Permissions

| Slug | Description |
|------|-------------|
| create_quotation | Create new quotations |
| generate_pdf | Generate PDF |
| view_own_quotations | View own quotations |
| view_all_quotations | View all quotations |
| delete_quotations | Delete quotations |
| download_pdf | Download PDF |
| download_pdf_branded | Download branded PDF |
| download_pdf_plain | Download plain PDF |
| convert_to_loan | Convert quotation to loan |

## Location Filtering

- Quotations can be tied to a location (city/state) and branch
- Banks are filtered by location via `bank_location` pivot
- Users see locations assigned to them (admin/super_admin see all)
