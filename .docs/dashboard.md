# Dashboard

## Overview

Multi-tab dashboard showing quotations, loans, tasks, and DVR data. Default tab selected based on what needs attention.

## Controller: `DashboardController@index`

Computes stats for each section:
- Quotation stats (if user has quotation permissions)
- Loan stats (if user has loan access)
- Personal task stats
- DVR stats

## Tabs

| Tab | Data Source | Visibility |
|-----|------------|------------|
| Quotations | DashboardController@quotationData | create_quotation or view_own/all_quotations |
| Loans | DashboardController@dashboardLoanData | view_loans |
| Loan Tasks | DashboardController@taskData | manage_loan_stages |
| Personal Tasks | Always visible | All users |
| DVR | DashboardController@dvrData | view_dvr |

## Default Tab Selection

Priority-based (checks actual data counts, not just permissions):
1. Overdue personal tasks → Personal Tasks tab
2. Pending loan tasks → Loan Tasks tab
3. Pending personal tasks → Personal Tasks tab
4. Active loans → Loans tab
5. Unconverted quotations → Quotations tab
6. Personal tasks (fallback) → Personal Tasks tab

## DataTable Endpoints

All use server-side processing with search, filter, and pagination.

### quotationData
- Filters: loan_status (not_converted, active, completed, rejected, converted, all)
- Returns: customer, type, amount, bank count, date, conversion status

### dashboardLoanData
- Returns: loan number, customer, bank, stage, status, amount

### taskData
- Returns: loan stage assignments with status and assignee info

### dvrData
- Returns: visit date, contact, purpose, outcome, follow-up status

## Stat Cards

Dashboard shows stat cards per tab:
- **Quotations:** total, this month, converted, unconverted
- **Loans:** total, active, completed, this month
- **Tasks:** pending, in progress, overdue, completed
- **DVR:** total visits, pending follow-ups, overdue follow-ups

## Quick Actions

- Create Quotation button
- Create Task modal (opens directly on dashboard)
- Create DVR entry

## Activity Feed

Recent activity entries on the dashboard (limited to user-relevant items).
