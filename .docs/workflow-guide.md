# Shreenathji Home Finance - Loan Management Workflow Guide

## Table of Contents

1. [System Overview](#system-overview)
2. [User Roles](#user-roles)
3. [Quotation Workflow](#quotation-workflow)
4. [Loan Creation](#loan-creation)
5. [Loan Number Format](#loan-number-format)
6. [Complete Loan Stage Flow](#complete-loan-stage-flow)
7. [Stage Details](#stage-details)
8. [Permissions Quick Reference](#permissions-quick-reference)
9. [Notifications](#notifications)
10. [Frequently Asked Questions](#frequently-asked-questions)

---

## System Overview

Shreenathji Home Finance (SHF) is a bilingual (English/Gujarati) loan management platform for comparing bank loan products and managing the full loan lifecycle from inquiry to disbursement.

The system has two main modules:

1. **Quotation System** - Create bank comparison PDFs with EMI calculations, processing charges, and required documents across multiple banks and tenures.

2. **Loan Task Management** - Track a loan through an 11-stage workflow from initial inquiry to final disbursement, with document collection, stage assignments, parallel processing, and lifecycle tracking.

```
+-------------------+       Convert        +-------------------+
|                   | ------------------->  |                   |
|    QUOTATION      |   (select a bank)     |    LOAN TASK      |
|                   |                       |                   |
| - Compare banks   |                       | - 11-stage flow   |
| - EMI tables      |       OR              | - Assignments     |
| - PDF generation  |                       | - Documents       |
+-------------------+       Direct          | - Disbursement    |
                      ---- Create ------->  +-------------------+
```

---

## User Roles

### System Roles (login accounts)

| Role | Description |
|------|-------------|
| **Super Admin** | Full access to everything. Cannot be restricted. |
| **Admin** | Full access to most features. Can manage users, settings, and permissions. |
| **Staff** | Day-to-day operations. Can create quotations and manage assigned loans. |

### Task Roles (loan assignments)

Each user also has a **task role** that determines which loan stages they can be assigned to:

| Task Role | Typical Responsibilities |
|-----------|------------------------|
| **Branch Manager** | Oversees loans at a branch level |
| **Loan Advisor** | Primary loan handler, manages documents, coordinates stages |
| **Bank Employee** | Handles bank-side approvals (BSM/OSV, sanction, e-sign) |
| **Office Employee** | Handles valuations, docket review, OTC clearance |

---

## Quotation Workflow

A quotation compares loan offers from multiple banks for a customer.

### Step-by-Step

```
Step 1: Open "New Quotation" from the menu

Step 2: Fill Customer Details
   - Customer Name
   - Customer Type: Proprietor / Partnership-LLP / Pvt Ltd / Salaried

Step 3: Select Documents
   - Document checklist shown based on customer type
   - Check the documents the customer needs to provide
   - Documents shown in English and Gujarati

Step 4: Enter Loan Details
   - Loan Amount (in Indian Rupees)
   - Select Banks to compare
   - Enter ROI (Rate of Interest) range for each bank
   - Enter bank charges (processing fee, admin charges, etc.)
   - Select Tenures to compare (e.g., 5 years, 10 years, 15 years)

Step 5: Add Notes (optional)
   - Any additional terms or conditions

Step 6: Click "Generate PDF"
   - System calculates EMI for each bank x tenure combination
   - Generates a bilingual comparison PDF
   - Saves quotation to database
   - PDF available for download
```

### Quotation PDF Contents

The generated PDF includes:
- Company header with logo
- Customer details
- EMI comparison tables (one per tenure)
- Bank charges comparison
- Required documents checklist (English + Gujarati)
- Additional notes
- Prepared by information

---

## Loan Creation

There are two ways to create a loan:

### Option A: Convert from Quotation

```
Quotation List --> Click "Convert to Loan" --> Select a Bank --> Loan Created
```

When converting:
- Customer details are copied from the quotation
- The selected bank becomes the loan's bank
- Documents from the quotation are carried over
- Stages 1 (Inquiry) and 2 (Document Selection) are auto-completed
- Stage 3 (Document Collection) is auto-assigned to the loan advisor

### Option B: Create Directly

```
Loan List --> Click "New Loan" --> Fill Details --> Loan Created
```

When creating directly:
- Enter customer name, type, bank, branch, product, loan amount
- Default documents are populated based on customer type
- All stages start from the beginning

---

## Loan Number Format

Every loan gets a unique number:

```
SHF-YYYYmm-NNNN

Examples:
  SHF-202604-0001  (first loan of April 2026)
  SHF-202604-0002  (second loan of April 2026)
  SHF-202605-0001  (first loan of May 2026)
```

---

## Complete Loan Stage Flow

```
  +--------------------+
  | Stage 1: Inquiry   |  (auto-completed on conversion)
  +--------+-----------+
           |
  +--------v-----------+
  | Stage 2: Document  |  (auto-completed on conversion)
  |   Selection        |
  +--------+-----------+
           |
  +--------v-----------+
  | Stage 3: Document  |  Loan Advisor collects all
  |   Collection       |  required documents
  +--------+-----------+
           |
  +--------v-----------+
  | Stage 4: Parallel  |  Container for 5 sub-stages
  |   Processing       |  running simultaneously
  |                    |
  | +----------------+ |
  | | 4a: App Number | |  <<< MUST complete first
  | +-------+--------+ |
  |         |          |
  |    (unlocks all)   |
  |    |   |   |   |   |
  | +--v-+ | +-v-+ |  |
  | |4b  | | |4d | |  |
  | |BSM/| | |Tech| |  |
  | |OSV | | |Val.| |  |
  | +----+ | +----+ |  |
  |   +----v--+ +---v+ |
  |   |4c     | |4e  | |
  |   |Legal  | |Prop| |
  |   |Verif. | |Val.| |
  |   +-------+ +----+ |
  |                    |
  | All must complete  |
  +--------+-----------+
           |
  +--------v-----------+
  | Stage 5: Rate & PF |  Interest rate and
  |   Request          |  processing fee negotiation
  +--------+-----------+
           |
  +--------v-----------+
  | Stage 6: Sanction  |  Bank issues sanction letter
  |   Letter           |
  +--------+-----------+
           |
  +--------v-----------+
  | Stage 7: Docket    |  Loan docket submitted
  |   Login            |  to the bank
  +--------+-----------+
           |
  +--------v-----------+
  | Stage 8: KFS       |  Key Fact Statement
  |   Generation       |  generated
  +--------+-----------+
           |
  +--------v-----------+
  | Stage 9: E-Sign    |  Electronic signing and
  |   & eNACH          |  auto-debit mandate
  +--------+-----------+
           |
  +--------v-----------+
  | Stage 10:          |  Loan amount disbursed
  |   Disbursement     |  (Fund Transfer or Cheque)
  +--------+-----------+
           |
  +--------v-----------+
  | Stage 11: OTC      |  Cheque only - handover
  |   Clearance        |  and clearance
  +--------+-----------+
           |
     LOAN COMPLETED
```

---

## Stage Details

### Stage 1: Inquiry

- **Status**: Auto-completed when a quotation is converted to a loan
- **Purpose**: Records that a customer inquiry was received
- **Action needed**: None (automatic)

---

### Stage 2: Document Selection

- **Status**: Auto-completed when a quotation is converted to a loan
- **Purpose**: Documents to collect have been identified based on customer type
- **Action needed**: None (automatic)

---

### Stage 3: Document Collection

- **Assigned to**: Loan Advisor
- **Purpose**: Collect all required documents from the customer

**What you do:**

1. Open the loan and go to the Documents tab
2. For each document in the list:
   - Mark as **Received** when the customer provides it
   - Mark as **Rejected** if the document is not acceptable (provide reason)
   - Mark as **Waived** if the document is not needed for this case
3. Upload document files as attachments
4. You can add custom documents if needed

**Completion rule**: All required documents must be either Received or Waived before the stage can be completed.

```
Document Status Flow:

  Pending --> Received  (customer provided it)
         |
         --> Rejected   (not acceptable, needs resubmission)
         |
         --> Waived     (not required for this case)
```

---

### Stage 4: Parallel Processing

This is a **container stage** with 5 sub-stages that can run simultaneously. However, the Application Number sub-stage (4a) must complete first before the others unlock.

```
                    +------------------+
                    | 4a: App Number   |
                    | (MUST be first)  |
                    +--------+---------+
                             |
              completes and unlocks all below
           +--------+--------+--------+--------+
           |        |        |        |        |
     +-----v--+ +---v----+ +v------+ +v------+
     |4b: BSM/| |4c:Legal| |4d:Tech| |4e:Prop|
     |OSV     | |Verif.  | |Valuat.| |Valuat.|
     +--------+ +--------+ +-------+ +-------+
           |        |        |        |
           +--------+--------+--------+
                             |
                  All complete = Stage 4 done
```

#### Sub-stage 4a: Application Number

- **Assigned to**: Loan Advisor
- **Purpose**: Record the bank application number and set the docket timeline

**What you do:**

1. Enter the **Application Number** (required) given by the bank
2. Select a **Docket Timeline** - when the docket is expected after sanction:
   - S+1: 1 day after sanction
   - S+2: 2 days after sanction
   - S+3: 3 days after sanction
   - Custom Date: Enter a specific date
3. Click **Complete**

**What happens next**: When completed, the remaining 4 sub-stages automatically unlock and get auto-assigned to the appropriate users based on their task roles.

---

#### Sub-stage 4b: BSM/OSV Approval

- **Auto-assigned to**: Bank Employee
- **Purpose**: Bank employee verifies BSM (Branch Sales Manager) / OSV (On-Site Verification) approval

**What you do:**

1. Perform BSM/OSV verification with the bank
2. Click **Complete** when verification is done

---

#### Sub-stage 4c: Legal Verification

- **Purpose**: Coordinate legal verification between task owner and bank

This stage has a **3-phase flow** with handoffs between the task owner and bank employee:

```
Phase 1 (Task Owner):
  Enter suggested legal advisor name
  --> Click "Send to Bank"

Phase 2 (Bank Employee):
  Review the suggested legal advisor
  Confirm or change the name
  --> Click "Initiate Legal Verification"

Phase 3 (Task Owner):
  Legal verification is initiated
  Can reassign if needed
  --> Click "Complete" when done
```

---

#### Sub-stage 4d: Technical Valuation

- **Assigned to**: Office Employee
- **Purpose**: Assess the technical value of the property

**What you do:**

1. Open the **Valuation Form**
2. Fill in:
   - **Property Type**: Choose from 6 options
   - **Property Address**
   - **Location**: Latitude/Longitude (with map picker)
   - **Land Area** and **Rate per unit** (value auto-calculated)
   - **Construction Area** and **Rate per unit** (value auto-calculated)
   - **Final Valuation** = Land Value + Construction Value (auto-calculated)
3. Click **Save**

The stage auto-completes when the form is saved.

---

#### Sub-stage 4e: Property Valuation (Optional)

- **Assigned to**: Office Employee
- **Purpose**: Secondary property valuation (only required for certain loan products)
- **Form**: Same as Technical Valuation

**Note**: This sub-stage may not appear for all loans. It depends on the product configuration.

---

### Stage 5: Rate & PF Request

- **Purpose**: Negotiate the interest rate and processing fee with the bank

This stage has a **3-phase flow**:

```
Phase 1 (Task Owner):
  Fill ALL fields:
  - Interest Rate
  - Repo Rate
  - Bank Margin
  - Rate Offered Date
  - Valid Until date
  - Bank Reference
  - Processing Fee
  - Admin Charges
  - PF GST
  - Total PF
  - Special Conditions
  --> Click "Send to Bank"

Phase 2 (Bank Employee):
  Review all fields
  Edit any values as needed
  --> Click "Save & Return to Task Owner"

Phase 3 (Task Owner):
  Review bank's changes
  (Original values shown as hints below each field)
  Edit if needed
  --> Click "Complete"
```

---

### Stage 6: Sanction Letter

- **Purpose**: Bank issues the official sanction letter

This stage has a **3-phase flow**:

```
Phase 1 (Task Owner):
  --> Click "Send for Sanction Letter Generation"
  (Transfers to bank employee)

Phase 2 (Bank Employee):
  Generate the sanction letter
  --> Click "Sanction Letter Generated"
  (Transfers back to task owner)

Phase 3 (Task Owner):
  Fill in sanction details:
  - Sanction Date
  - Sanctioned Amount
  - Sanctioned Rate (pre-filled from Rate & PF stage, read-only)
  - EMI Amount
  - Conditions (if any)
  --> Click "Complete"
```

**Note**: The Sanction Date automatically calculates the **Expected Docket Date** based on the docket timeline set in Stage 4a (e.g., Sanction Date + 2 days for S+2).

---

### Stage 7: Docket Login

- **Purpose**: Submit the loan docket to the bank

Shows a banner with the **Expected Docket Date** and days remaining (or overdue).

This stage has a **3-phase flow**:

```
Phase 1 (Task Owner):
  Enter Login Date (defaults to today)
  --> Click "Save & Send to Office Employee"

Phase 2 (Office Employee):
  Review the docket submission
  --> Click "Complete"
  (Transfers back to task owner)

Phase 3 (Task Owner):
  --> Click "Complete"
```

---

### Stage 8: KFS Generation

- **Purpose**: Key Fact Statement (KFS) generated by the bank
- **Action**: The eligible user clicks **Complete** when the KFS is generated
- **Next**: The system auto-assigns Stage 9 (E-Sign) to the bank employee

---

### Stage 9: E-Sign & eNACH

- **Purpose**: Complete electronic signing and eNACH (electronic auto-debit mandate)

This stage has a **3-phase flow**:

```
Phase 1 (Bank Employee):
  Generate E-Sign & eNACH documents
  --> Click "E-Sign & eNACH Generated"
  (Transfers to task owner)

Phase 2 (Task Owner):
  Complete E-Sign & eNACH with the customer
  --> Click "E-Sign & eNACH Completed with Customer"
  (Transfers to bank employee)

Phase 3 (Bank Employee):
  Confirm completion
  --> Click "Complete"
```

---

### Stage 10: Disbursement

- **Purpose**: Disburse the loan amount to the customer

There are **two disbursement methods**:

```
Option A: Fund Transfer
+----------------------------------+
| Enter:                           |
|   - Loan Account Number         |
|   - Disbursement Amount         |
|   - Disbursement Date           |
| --> Save                        |
| LOAN COMPLETES IMMEDIATELY      |
| (Stage 11 OTC is skipped)       |
+----------------------------------+

Option B: Cheque
+----------------------------------+
| Enter:                           |
|   - Disbursement Amount         |
|   - Disbursement Date           |
|   - Add Cheques:                |
|     - Cheque Number             |
|     - Cheque Date               |
|     - Cheque Amount             |
|   (Total cheques must be        |
|    <= disbursement amount)      |
| --> Save                        |
| ADVANCES TO STAGE 11 (OTC)     |
+----------------------------------+
```

---

### Stage 11: OTC Clearance (Cheque Disbursement Only)

- **Purpose**: Track cheque handover and clearance
- **Appears only when**: Disbursement was done via cheque

**What you do:**

1. Review the cheque list table
2. Enter the **Handover Date**
3. Click **Complete**

**After completion**: The loan is marked as **Completed**.

**Note**: This stage can be assigned to an office employee who enters the handover date.

---

## Permissions Quick Reference

### Who Can Do What

| Action | Required Permission | Who Has It (Default) |
|--------|-------------------|---------------------|
| View loan list | `view_loans` | Admin, Staff |
| View all loans (not just own) | `view_all_loans` | Admin |
| Create a new loan | `create_loan` | Admin, Staff |
| Convert quotation to loan | `convert_to_loan` | Admin, Staff |
| Edit loan details | `edit_loan` | Admin |
| Delete a loan | `delete_loan` | Admin |
| Manage loan documents | `manage_loan_documents` | Admin, Staff |
| Manage stage status | `manage_loan_stages` | Admin, Staff |
| Skip a stage | `skip_loan_stages` | Admin |
| Add remarks | `add_remarks` | Admin, Staff |
| Configure workflow settings | `manage_workflow_config` | Admin |

### Who Can Create Loans

Only users with the `create_loan` permission AND one of these task roles can create loans:
- Branch Manager
- Loan Advisor
- Office Employee
- Admin
- Super Admin

**Super Admin** always has ALL permissions regardless of settings.

---

## Notifications

The system sends in-app notifications for key events:

| Event | Who Gets Notified |
|-------|------------------|
| Stage assigned to you | The assigned user |
| A stage is completed | Loan creator and loan advisor |
| Loan fully completed | Loan creator and loan advisor |
| Query raised on a stage | The stage's assigned user |
| Stage transferred to you | The receiving user |

**Notification bell**: The bell icon in the top menu shows unread notification count. It refreshes every 60 seconds automatically.

---

## Additional Features

### Queries (Two-Way Communication)

Any stage can have queries raised against it. Queries block stage completion until resolved.

```
Query Flow:

  User raises a query on a stage
  --> Stage BLOCKED (cannot complete)
  --> Assigned user sees the query
  --> Assigned user responds
  --> Original user resolves the query
  --> Stage UNBLOCKED (can complete again)
```

### Remarks

Users can add remarks/notes to any loan or specific stage. All remarks are visible in the loan timeline.

### Stage Skipping

Users with the `skip_loan_stages` permission (Admin by default) can skip stages that are not applicable. Skipped stages are marked differently in the progress tracker.

### Stage Transfer

Any assigned stage can be transferred to a different user with an optional reason. The transfer is recorded in the loan timeline.

### Loan Rejection

A loan can be rejected at any stage. Rejection is a terminal action - the loan cannot proceed further after rejection. A reason must be provided.

### Timeline

Every loan has a complete timeline view showing all events in chronological order:
- Quotation creation (if converted)
- Loan creation
- Stage start and completion times
- Transfers between users
- Queries raised and resolved
- Remarks added
- Disbursement details
- Final completion or rejection

---

## Frequently Asked Questions

**Q: Can I go back to a previous stage?**
A: No. Stages move forward only. If there is an issue, use the Query feature to communicate or add a Remark.

**Q: What happens if a required document is missing?**
A: Stage 3 (Document Collection) cannot be completed until all required documents are either Received or Waived. Mark documents as Waived only if they are genuinely not needed.

**Q: Why are some parallel sub-stages locked?**
A: Sub-stages 4b, 4c, 4d, and 4e remain locked until sub-stage 4a (Application Number) is completed. This is because the application number is needed before bank verifications can begin.

**Q: What is the difference between Fund Transfer and Cheque disbursement?**
A: Fund Transfer completes the loan immediately (no OTC stage needed). Cheque disbursement requires an additional OTC Clearance stage (Stage 11) to track cheque handover.

**Q: Who assigns stages to users?**
A: Most stages are auto-assigned based on the user's task role, bank, and branch. Admins can manually reassign or transfer stages.

**Q: Can I change the docket timeline after setting it?**
A: The docket timeline is set in Stage 4a (Application Number). Once completed, it determines the expected docket date calculated from the sanction date in Stage 6.

**Q: What does the Indian currency format look like?**
A: The system uses the Indian numbering system: 1,25,00,000 (one crore twenty-five lakh). All amounts are displayed with the Rupee symbol: Rs. 1,25,00,000.
