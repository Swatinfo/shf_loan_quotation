# Loans

## Overview

Loan management with full lifecycle tracking — from quotation conversion through disbursement and OTC clearance.

## Model: `LoanDetail`

### Statuses
| Status | Color | Description |
|--------|-------|-------------|
| active | blue | Currently being processed |
| completed | green | All stages done, disbursed |
| rejected | red | Rejected at any stage |
| cancelled | gray | Cancelled by user |
| on_hold | yellow | Temporarily paused |

### Key Fields
- `loan_number` — auto-generated SHF-YYYYMM-XXXX format
- `current_stage` — tracks active workflow position
- `is_sanctioned` — set by sanction_decision stage
- `assigned_advisor` — primary advisor on the loan
- `assigned_bank_employee` — bank-side contact
- `expected_docket_date` — calculated from app_number notes

### Customer Types
| Key | Label (EN/GU) |
|-----|---------------|
| proprietor | Proprietor / માલિકી |
| partnership_llp | Partnership/LLP / ભાગીદારી/LLP |
| pvt_ltd | Pvt. Ltd. / પ્રા. લિ. |
| salaried | Salaried / પગારદાર |

## Creation Routes

### From Quotation (Conversion)
1. User clicks "Convert to Loan" on quotation show page
2. `LoanConversionController@showConvertForm` — shows form with bank, branch, product, customer details
3. `LoanConversionController@convert` — calls `LoanConversionService::convertFromQuotation()`
4. Creates Customer, LoanDetail, copies documents, initializes stages
5. Auto-completes inquiry + document_selection, auto-assigns document_collection
6. Redirects to new loan

### Direct Creation
1. `LoanController@create` — form with bank, product, customer, branch
2. `LoanController@store` — calls `LoanConversionService::createDirectLoan()`
3. current_stage starts at 'inquiry'

## Visibility (`scopeVisibleTo`)

Controls which loans a user can see:
1. **view_all_loans** permission → sees everything
2. **Own loans** → created_by = user OR assigned_advisor = user
3. **Stage assigned** → user has any StageAssignment on the loan
4. **Branch-based** → branch_manager/bdh see loans in their branches (via user_branches)
5. **Transfer history** → bank_employee/office_employee see loans they appear in stage_transfers

## Loan Data (DataTable)

`LoanController@loanData` — server-side DataTable with filters:
- status, customer_type, bank_id, branch_id, date range, stage, role
- Docket filters: overdue, due_today, due_soon, no_date
- Search: loan_number, customer_name, phone, email

## Status Management

Loans are managed via status transitions (reject, on_hold, cancel) from the loan show page. The status change modal captures a reason. Active statuses: active, completed, rejected, cancelled, on_hold.

## Edit Restrictions

`LoanDetail::isBasicEditLocked()` — basic details locked after document_collection stage is completed.

## Permissions

| Slug | Description |
|------|-------------|
| view_loans | View loan list |
| view_all_loans | View all loans across users/branches |
| create_loan | Create loans directly |
| edit_loan | Edit loan details |
| delete_loan | Delete loans |
| convert_to_loan | Convert quotation to loan |

## Loan Duration on Listing

The loan listing displays total processing time via `LoanDetail::getTotalLoanTimeAttribute()` accessor in the created_at column. Shows human-readable duration (e.g., "45 days") from loan creation to current time or completion.

## Related Features
- **Turnaround Time Report** → `ReportController@turnaround` with Overall TAT and Stage-wise TAT tabs
- **Stages/Workflow** → see `workflow-developer.md`
- **Documents** → managed by `LoanDocumentService`
- **Valuation** → `LoanValuationController` with map integration
- **Disbursement** → `LoanDisbursementController`
- **Remarks** → `LoanRemarkController`
- **Timeline** → `LoanTimelineService`
