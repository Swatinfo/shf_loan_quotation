# Daily Visit Reports (DVR)

## Overview

Field activity tracking system for loan advisors and branch staff. Records customer visits with follow-up tracking and visit chains.

## Model: `DailyVisitReport`

### Fields
- visit_date, contact_name, contact_phone
- contact_type — configurable (from app-defaults)
- purpose — configurable (from app-defaults)
- notes, outcome
- follow_up_needed (boolean), follow_up_date, follow_up_notes, is_follow_up_done (boolean)
- parent_visit_id, follow_up_visit_id — for visit chain linking
- quotation_id, loan_id — optional linking to quotation/loan
- branch_id — branch association
- user_id — report creator

### Contact Types (configurable)
| Key | English | Gujarati |
|-----|---------|----------|
| existing_customer | Existing Customer | હાલના ગ્રાહક |
| new_customer | New Customer | નવા ગ્રાહક |
| ca | CA | CA |
| builder | Builder/Developer | બિલ્ડર/ડેવલપર |
| dsa | DSA/Connector | DSA/કનેક્ટર |
| other | Other | અન્ય |

### Visit Purposes (configurable)
| Key | English | Gujarati |
|-----|---------|----------|
| new_lead | New Lead | નવી લીડ |
| follow_up | Follow-up | ફોલો-અપ |
| document_collection | Document Collection | ડોક્યુમેન્ટ કલેક્શન |
| quotation_delivery | Quotation Delivery | ક્વોટેશન ડિલિવરી |
| payment | Payment/Disbursement | ચૂકવણી/વિતરણ |
| relationship | Relationship | સંબંધ |
| other | Other | અન્ય |

Contact types and purposes are configurable via Settings page (DVR tabs).

## Visibility (`scopeVisibleTo`)

1. **view_all_dvr** permission → sees everything
2. **BDH/branch_manager** → own visits + branch users' visits
3. **Others** → own visits only

## Controller: `DailyVisitReportController`

### Routes
| Method | URI | Permission |
|--------|-----|------------|
| GET | /dvr | view_dvr |
| GET | /dvr/data | view_dvr |
| GET | /dvr/{dvr} | view_dvr |
| POST | /dvr | create_dvr |
| PUT | /dvr/{dvr} | edit_dvr |
| PATCH | /dvr/{dvr}/follow-up-done | edit_dvr |
| DELETE | /dvr/{dvr} | delete_dvr |
| GET | /dvr/search-loans | view_dvr |
| GET | /dvr/search-quotations | view_dvr |
| GET | /dvr/search-contacts | view_dvr |

### DataTable Filters
- View: my_visits, my_branch, all
- Contact type, purpose
- Follow-up: active, pending, overdue, done, all
- Date range
- User filter (for admin/BDH)

## Follow-Up Tracking

- `follow_up_needed` — marks visit as needing follow-up
- `follow_up_date` — scheduled follow-up date
- `follow_up_notes` — notes for follow-up
- `is_follow_up_done` — marked when follow-up completed
- `markFollowUpDone()` endpoint to quickly mark done

### Scopes
- `pendingFollowUps` — follow_up_needed=true, is_follow_up_done=false
- `overdueFollowUps` — pending + follow_up_date < today

## Visit Chain

Linked visits via `parent_visit_id` / `follow_up_visit_id`:
- `getVisitChain()` — returns full chain from root to leaf
- Can chain multiple follow-up visits

## Linking

- **Quotation link:** `quotation_id` — search via `/dvr/search-quotations`
- **Loan link:** `loan_id` — search via `/dvr/search-loans`
- **Contact search:** `/dvr/search-contacts` — searches existing DVR contacts

## Permissions

| Slug | Description |
|------|-------------|
| view_dvr | View DVR reports |
| create_dvr | Create DVR |
| edit_dvr | Edit DVR |
| delete_dvr | Delete DVR |
| view_all_dvr | View all DVR across users |

## Views

| View | Purpose |
|------|---------|
| dvr/index.blade.php | DataTable list with filters and create form |
| dvr/show.blade.php | Visit detail with chain and follow-up info |
