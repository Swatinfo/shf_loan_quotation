# Loan Workflow Guide (End-User)

This guide explains the loan lifecycle in Shreenathji Home Finance, from initial inquiry through final completion.

## Overview

Every loan follows a structured workflow of **11 main stages** (plus 5 parallel sub-stages). Each stage must be completed before the next one begins, with some stages running concurrently to speed up processing. The system automatically advances through stages, assigns responsible users, and tracks progress in real time.

**Loan statuses:** Active, Completed, Rejected, Cancelled, On Hold

## Stage-by-Stage Walkthrough

### Stage 1: Inquiry

The loan journey begins here. A loan advisor or branch manager creates the loan record with basic customer and property information.

- **Who:** Loan Advisor, Branch Manager, BDO
- **What happens:** Customer details, loan amount, property info, and bank selection are captured.
- **Triggers next:** Automatically advances to Document Selection once completed.

### Stage 2: Document Selection

The required documents for this loan are identified based on the customer type (proprietor, partnership/LLP, private limited, salaried).

- **Who:** Loan Advisor, Branch Manager, BDO
- **What happens:** The system presents document categories. The user selects which documents are needed.
- **Triggers next:** Advances to Document Collection.

### Stage 3: Document Collection

All selected documents are gathered from the customer.

- **Who:** Loan Advisor, Branch Manager, BDO
- **What happens:** Documents are uploaded and tracked. Each required document must be marked as collected or resolved (waived/not applicable).
- **Completion condition:** All required documents must be resolved before this stage can be completed.
- **Triggers next:** Advances to Parallel Processing.

### Stage 4: Parallel Processing (Parent Stage)

This is a container stage that holds 5 sub-stages. It is not worked on directly -- instead, it tracks whether all its sub-stages are done.

The sub-stages run in a **partially sequential, partially parallel** order:

#### Sub-stage 4a: Application Number

Runs first. The application is registered with the bank and an application number is obtained.

- **Who:** Loan Advisor or Bank Employee
- **Required fields:** Application Number, Docket Timeline (days offset or custom date)
- **Auto-completes** when application number and docket timeline are filled.
- **When done:** Automatically starts BSM/OSV (4b).

#### Sub-stage 4b: BSM/OSV (Bank Site Meeting / On-Site Verification)

Runs after Application Number completes. The bank visits the property/customer site.

- **Who:** Bank Employee
- **When done:** Automatically starts all remaining sub-stages (4c, 4d, 4e) in parallel.

#### Sub-stage 4c: Legal Verification

Runs in parallel with 4d and 4e after BSM/OSV completes. Legal documents and property title are verified.

- **Who:** Loan Advisor (phase 1) then Bank Employee (phase 2) then back to Loan Advisor (phase 3)
- **Multi-phase flow:**
  1. Advisor suggests a legal advisor and sends to bank employee
  2. Bank employee initiates legal verification
  3. Returns to task owner for completion
- **Auto-completes** when phase 3 is reached.

#### Sub-stage 4d: Technical Valuation

Runs in parallel. A technical assessment of the property is carried out.

- **Who:** Office Employee, Loan Advisor
- **Completion condition:** A valuation record with a final valuation amount must exist.

#### Sub-stage 4e: Sanction Decision

A go/no-go decision on whether to proceed with the loan.

- **Who:** Loan Advisor (initial), escalatable to Branch Manager or BDO
- **Actions available:**
  - **Approve** -- marks the loan as sanctioned and completes this sub-stage
  - **Escalate to BM/BDO** -- transfers the decision to a senior role (remarks required)
  - **Reject** -- rejects the entire loan (only BM/BDO/Admin can reject; reason required, minimum 10 characters)

**Parallel Processing completes** when all enabled sub-stages are completed or skipped.

### Stage 5: Rate & PF (Processing Fee)

Interest rate and processing fee details are finalized with the bank.

- **Who:** Loan Advisor (fills initial details) then Bank Employee (reviews/confirms) then back to Loan Advisor
- **Pre-condition:** The loan must be sanctioned (sanction decision approved) AND at least one parallel sub-stage must be completed.
- **Required fields:** Interest Rate, Repo Rate, Bank Margin, Rate Offered Date, Valid Until, Bank Reference, Processing Fee, Admin Charges, PF GST, Total PF
- **Multi-phase flow:**
  1. Task owner fills rate and fee details
  2. Sends to bank employee for review
  3. Bank employee returns to task owner for final confirmation

### Stage 6: Sanction

The formal sanction letter is obtained from the bank.

- **Who:** Loan Advisor then Bank Employee then back to Loan Advisor
- **Multi-phase flow:**
  1. Task owner prepares and sends for sanction letter generation
  2. Bank employee generates the sanction letter
  3. Returns to task owner with the letter
- **Required fields (phase 3):** Sanction Date, Sanctioned Amount, EMI Amount
- **Side effect:** When completed, calculates the expected docket date from the Application Number stage's docket timeline settings.

### Stage 7: Docket

The loan docket (physical file) is prepared and submitted to the bank.

- **Who:** Loan Advisor then Office Employee then back to Loan Advisor
- **Required field:** Login Date
- **Multi-phase flow:**
  1. Task owner fills login date and docket details
  2. Sends to office employee for docket login at the bank
  3. Office employee completes login and returns to task owner
  4. Task owner completes the stage

### Stage 8: KFS (Key Fact Statement)

The Key Fact Statement is reviewed and confirmed.

- **Who:** Loan Advisor, Branch Manager, BDO
- **No mandatory fields** -- completes when the user marks it done.

### Stage 9: E-Sign & eNACH

Digital signing and electronic mandate setup.

- **Who:** Loan Advisor then Bank Employee then Loan Advisor then Bank Employee
- **Multi-phase flow (4 phases):**
  1. Advisor sends to bank employee for E-Sign & eNACH generation
  2. Bank employee generates and sends back to advisor
  3. Advisor completes the process with the customer
  4. Bank employee confirms final completion

### Stage 10: Disbursement

The loan amount is released to the customer.

- **Who:** Loan Advisor, Bank Employee
- **Key behavior:**
  - If disbursement type is **Fund Transfer**: OTC stage is automatically skipped and the loan is marked as completed.
  - If disbursement type is **Cheque**: The workflow continues to OTC Clearance.

### Stage 11: OTC Clearance (Over-The-Counter)

For cheque-based disbursements, this stage tracks the cheque handover and clearance.

- **Who:** Office Employee, Loan Advisor
- **Required field:** Handover Date
- **When completed:** The entire loan is marked as **Completed** and a completion notification is sent.

## Parallel Processing Explained

Stages 4a through 4e do not all run at the same time. The flow is:

1. **Application Number (4a)** runs alone first
2. When 4a completes, **BSM/OSV (4b)** starts alone
3. When 4b completes, **Legal Verification (4c)**, **Technical Valuation (4d)**, and **Sanction Decision (4e)** all start simultaneously
4. The parent "Parallel Processing" stage completes only when ALL sub-stages are done

This design ensures the bank has an application number and has visited the site before legal, technical, and sanction work begins, while allowing those three to proceed concurrently.

## Query System

Queries allow users to raise questions or request clarification on any stage. They act as blockers to prevent premature stage completion.

### How Queries Work

1. **Raise a query:** Any user with access to the loan can raise a query on a specific stage. The stage assignee receives a notification.
2. **Respond:** The stage assignee (or another user) responds to the query.
3. **Resolve:** Only the user who raised the query can mark it as resolved.

### Blocking Behavior

- A stage **cannot be completed** while it has unresolved queries (status: pending or responded).
- All queries must be resolved before the stage can advance.
- The system shows the count of unresolved queries when blocking completion.

### Query Statuses

| Status | Meaning |
|--------|---------|
| Pending | Query raised, awaiting response |
| Responded | A response has been given, but the raiser has not yet confirmed resolution |
| Resolved | The raiser confirmed the issue is resolved |

## Transfer Mechanics

Stages can be transferred between users, either automatically or manually.

### Auto-Transfer

When a stage advances, the system automatically assigns the next stage to the best-fit user based on:
1. Product-specific stage assignments (branch/city/state/product default)
2. Bank's default employee
3. Bank employee matched by bank + branch
4. Bank employee matched by bank (any branch)
5. Default office employee for the branch
6. Loan creator (for advisor/BM/BDO roles)
7. Any user with the eligible role in the same branch
8. Any user with the eligible role

### Manual Transfer

Users can manually transfer a stage to another user. A reason can (optionally) be provided. The transfer is recorded in the transfer history with timestamp, from/to users, and reason.

### Multi-Phase Transfers

Several stages use a multi-phase pattern where work bounces between roles (e.g., advisor to bank employee and back). These are handled via dedicated action buttons (e.g., "Send to Bank", "Return to Owner") that automatically transfer and update phase tracking.

## Stage Skipping

Stages can be skipped if:
- The user has the `skip_loan_stages` permission
- The product configuration allows skipping for that stage (`allow_skip` on `ProductStage`)

When a stage is skipped:
- It is marked as "Skipped" (shown in yellow/warning)
- The workflow advances to the next stage as if it were completed
- Skipped stages count as done for progress calculation

## Rejection Flow

A loan can be rejected from any stage, but the formal rejection path is through the **Sanction Decision** sub-stage.

### Rejection via Sanction Decision
- Only Branch Manager, BDO, Admin, or Super Admin can reject
- A rejection reason of at least 10 characters is required
- All pending/in-progress stages are immediately marked as rejected
- The loan status changes to "Rejected"

### Rejection via Any Stage
- The "Reject" action is available on any in-progress stage
- Requires a reason (up to 2000 characters)
- Sets the loan's `rejected_stage` field to identify where rejection occurred
- The loan status changes to "Rejected"

## Status Reference

### Loan Statuses
| Status | Description |
|--------|-------------|
| Active | Loan is being processed through the workflow |
| Completed | All stages done, loan fully disbursed |
| Rejected | Loan was rejected (reason and stage recorded) |
| Cancelled | Loan was cancelled by the user |
| On Hold | Loan processing is paused |

### Stage Statuses
| Status | Color | Description |
|--------|-------|-------------|
| Pending | Gray | Stage has not started yet |
| In Progress | Blue | Stage is actively being worked on |
| Completed | Green | Stage finished successfully |
| Rejected | Red | Stage/loan was rejected at this point |
| Skipped | Yellow | Stage was intentionally bypassed |

### Allowed Status Transitions
- **Pending** can go to: In Progress, Skipped
- **In Progress** can go to: Completed, Rejected
- **Rejected** can go to: In Progress (to re-open)
- **Completed** and **Skipped** are terminal -- no further transitions

## Progress Tracking

- Progress is calculated as a percentage of **main stages** completed (sub-stages contribute through their parent)
- The progress bar and percentage update in real time after each stage action
- A workflow snapshot is stored with each progress update for audit purposes
