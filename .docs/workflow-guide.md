# Loan Workflow Guide (User-Facing)

## Stage Overview

Every loan progresses through up to 16 stages (including parallel sub-stages):

### Sequential Stages
1. **Inquiry** — Initial loan inquiry recorded
2. **Document Selection** — Required documents identified
3. **Document Collection** — Documents gathered from customer

### Parallel Processing (stages run concurrently)
4. **Application Number** — Bank assigns application number
5. **BSM/OSV** — Bank site manager / on-site verification
6. **Legal Verification** (3-phase) — Legal checks on property
7. **Technical Valuation** — Property/asset valuation

### Decision & Post-Approval
8. **Sanction Decision** — Bank approves, escalates, or rejects
9. **Rate & PF** (3-phase) — Final rate and processing fee negotiation
10. **Sanction Letter** (3-phase) — Formal sanction letter issuance
11. **Docket Login** (3-phase) — Loan docket submitted to bank
12. **KFS** — Key Fact Statement generation
13. **E-Sign & eNACH** (4-phase) — Digital signing and mandate
14. **Disbursement** — Funds released
15. **OTC Clearance** — Over-the-counter cheque clearance (cheque disbursement only)

## Stage Statuses

| Status | Meaning |
|--------|---------|
| Pending | Not yet started |
| In Progress | Currently being worked on |
| Completed | Successfully finished |
| Rejected | Loan rejected at this stage |
| Skipped | Stage skipped (allowed stages only) |

## Multi-Phase Stages

Some stages involve handoffs between roles:

### Legal Verification (3 phases)
1. Loan Advisor → initiates legal check
2. Bank Employee → processes legal verification
3. Office Employee → completes legal documentation

### Rate & PF, Sanction Letter, Docket Login (3 phases each)
1. Loan Advisor → prepares and submits
2. Bank Employee → reviews and processes
3. Loan Advisor → confirms completion

### E-Sign & eNACH (4 phases)
1. Loan Advisor → prepares documents
2. Bank Employee → generates e-sign links
3. Loan Advisor → gets customer signatures
4. Bank Employee → confirms eNACH mandate

## Parallel Processing Flow

After Document Collection completes:
1. Application Number starts first
2. When Application Number completes → BSM/OSV starts
3. When BSM/OSV completes → Legal Verification + Technical Valuation start simultaneously
4. When ALL parallel stages complete → proceeds to Sanction Decision

## Queries

Any stage participant can raise a query (question) on a stage. Queries block stage completion until resolved. The query flow:
1. User raises query → assignee notified
2. Assignee responds → raiser notified
3. Raiser resolves query → stage can proceed

## Stage Transfer

A stage can be transferred to another eligible user with a reason. Transfer history is tracked.

## Loan Rejection

Branch Managers, BDH, and Admins can reject a loan at any stage with a reason. Rejection sets the loan status to 'rejected'.

## Disbursement Types

| Type | Flow |
|------|------|
| Fund Transfer | Disbursement → Loan Completed (OTC skipped) |
| Cheque | Disbursement → OTC Clearance → Loan Completed |

## Notifications

Users receive in-app notifications for:
- Stage assignments
- Stage completions
- Query raised/responded
- Loan completion
