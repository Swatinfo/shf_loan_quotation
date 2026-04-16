# Models

33 Eloquent models in `app/Models/`.

## Core Models

### User
- **Table:** users
- **Traits:** HasFactory, Impersonate, Notifiable
- **Relationships:** creator (BelongsTo User), roles (BelongsToMany Role), userPermissions (HasMany), branches (BelongsToMany Branch), defaultBranch (BelongsTo Branch), taskBank (BelongsTo Bank), employerBanks (BelongsToMany Bank), locations (BelongsToMany Location)
- **Scopes:** advisorEligible
- **Key methods:** hasRole(), hasAnyRole(), isSuperAdmin(), isAdmin(), hasPermission(), canCreateLoans(), hasWorkflowRole(), canImpersonate(), canBeImpersonated()

### Role
- **Table:** roles
- **Relationships:** users (BelongsToMany), permissions (BelongsToMany)
- **Scopes:** advisorEligible, workflow
- **Key methods:** advisorEligibleSlugs() (static, cached 5m), gujaratiLabels() (static)

### Permission
- **Table:** permissions
- **Relationships:** roles (BelongsToMany), userPermissions (HasMany)

### UserPermission
- **Table:** user_permissions
- **Relationships:** user (BelongsTo), permission (BelongsTo)
- **Fields:** type (grant/deny)

## Configuration Models

### AppConfig
- **Table:** app_config
- **Casts:** config_json → array
- **Usage:** Key-value store with 'main' as primary config key

### BankCharge
- **Table:** bank_charges
- **Purpose:** Stores last-used bank charges for quotation defaults

## Location & Organization

### Location
- **Table:** locations
- **Self-referential:** parent (BelongsTo Location), children (HasMany Location)
- **Relationships:** users (BelongsToMany), products (BelongsToMany), branches (HasMany)
- **Scopes:** active, states, cities
- **Types:** 'state' (parent_id null) or 'city' (has parent)

### Bank
- **Table:** banks
- **Traits:** HasAuditColumns, SoftDeletes
- **Relationships:** products (HasMany), employees (BelongsToMany User via bank_employees), locations (BelongsToMany)
- **Scopes:** active
- **Key methods:** getDefaultEmployeeForCity(cityId)

### Branch
- **Table:** branches
- **Traits:** HasAuditColumns, SoftDeletes
- **Relationships:** location (BelongsTo), users (BelongsToMany), manager (BelongsTo User)
- **Scopes:** active

### Product
- **Table:** products
- **Traits:** HasAuditColumns, SoftDeletes
- **Relationships:** bank (BelongsTo), stages (BelongsToMany Stage via product_stages), productStages (HasMany), locations (BelongsToMany)
- **Scopes:** active

## Quotation Models

### Quotation
- **Table:** quotations
- **Traits:** HasAuditColumns, SoftDeletes
- **Casts:** selected_tenures → array
- **Relationships:** user (BelongsTo), banks (HasMany QuotationBank), documents (HasMany QuotationDocument), loan (BelongsTo LoanDetail), location (BelongsTo), branch (BelongsTo)
- **Accessors:** isConverted, formattedAmount

### QuotationBank
- **Relationships:** quotation (BelongsTo), emiEntries (HasMany QuotationEmi)

### QuotationEmi
- **Table:** quotation_emi
- **Relationships:** quotationBank (BelongsTo)

### QuotationDocument
- **Relationships:** quotation (BelongsTo)

## Loan Models

### LoanDetail
- **Table:** loan_details
- **Traits:** HasAuditColumns, SoftDeletes
- **Constants:** STATUS_ACTIVE/COMPLETED/REJECTED/CANCELLED/ON_HOLD, CUSTOMER_TYPE_LABELS
- **Relationships:** quotation, branch, bank, product, location, customer, creator, advisor, bankEmployee, documents, stageAssignments, progress, stageTransfers, stageQueries, remarks, valuationDetails, disbursement
- **Scopes:** active, visibleTo(user)
- **Key methods:** generateLoanNumber() (static), isBasicEditLocked(), canEditStage(), getEditableStageKey()
- **Accessors:** currentOwner, formattedAmount, statusLabel, statusColor, currentStageName, stageBadgeHtml

### Customer
- **Table:** customers
- **Traits:** HasAuditColumns, SoftDeletes
- **Relationships:** loans (HasMany LoanDetail)

### LoanDocument
- **Table:** loan_documents
- **Traits:** HasAuditColumns
- **Constants:** STATUS_PENDING/RECEIVED/REJECTED/WAIVED
- **Scopes:** required, received, pending, rejected, resolved, unresolved
- **Key methods:** hasFile(), formattedFileSize(), isReceived(), isPending()

### ValuationDetail
- **Table:** valuation_details
- **Traits:** HasAuditColumns
- **Constants:** TYPE_PROPERTY/VEHICLE/BUSINESS, PROPERTY_TYPES

### DisbursementDetail
- **Table:** disbursement_details
- **Traits:** HasAuditColumns
- **Constants:** TYPE_FUND_TRANSFER/TYPE_CHEQUE
- **Casts:** cheques → array

## Workflow Models

### Stage
- **Table:** stages
- **Casts:** default_role → array, sub_actions → array
- **Relationships:** children (HasMany Stage), parent (BelongsTo Stage)
- **Scopes:** enabled, mainStages, subStagesOf(parentKey)
- **Key methods:** isSubStage(), isParent(), isDecision()

### StageAssignment
- **Table:** stage_assignments
- **Traits:** HasAuditColumns
- **Constants:** STATUS_PENDING/IN_PROGRESS/COMPLETED/REJECTED/SKIPPED
- **Relationships:** loan, assignee, completedByUser, stage, transfers (HasMany), queries (HasMany), activeQueries
- **Scopes:** pending, inProgress, completed, forUser, mainStages, subStagesOf
- **Key methods:** isActionable(), canTransitionTo(), hasPendingQueries(), getNotesData(), mergeNotesData()

### ProductStage
- **Table:** product_stages
- **Traits:** HasAuditColumns
- **Casts:** sub_actions_override → array
- **Relationships:** product, stage, defaultUser, branchUsers (HasMany ProductStageUser)
- **Key methods:** getUserForBranch(branchId), getUserForLocation(branchId, cityId, stateId)

### ProductStageUser
- **Table:** product_stage_users
- **Relationships:** productStage, branch, user

### StageTransfer
- **Table:** stage_transfers (no updated_at, only created_at)
- **Relationships:** stageAssignment, loan, fromUser, toUser

### StageQuery
- **Table:** stage_queries
- **Constants:** STATUS_PENDING/RESPONDED/RESOLVED
- **Scopes:** pending, active, resolved
- **Relationships:** stageAssignment, loan, raisedByUser, resolvedByUser, responses (HasMany)

### QueryResponse
- **Table:** query_responses (no updated_at, only created_at)
- **Relationships:** stageQuery, respondedByUser

### LoanProgress
- **Table:** loan_progress
- **Casts:** workflow_snapshot → array

### Remark
- **Table:** remarks
- **Scopes:** forStage(key), general
- **Relationships:** loan, user

## Communication Models

### ShfNotification
- **Table:** shf_notifications
- **Constants:** TYPE_INFO/SUCCESS/WARNING/ERROR/STAGE_UPDATE/ASSIGNMENT
- **Scopes:** unread, forUser, recent
- **Relationships:** user, loan

### ActivityLog
- **Table:** activity_logs
- **Casts:** properties → array
- **Key methods:** log(action, subject, properties) — static factory

## Task Models

### GeneralTask
- **Table:** general_tasks
- **Constants:** STATUS_PENDING/IN_PROGRESS/COMPLETED/CANCELLED, PRIORITY_LOW/NORMAL/HIGH/URGENT
- **Scopes:** visibleTo(user), pending
- **Relationships:** creator, assignee, loan, comments (HasMany)
- **Key methods:** isVisibleTo(), isEditableBy(), isDeletableBy(), canChangeStatus(user) — returns true for creator OR assignee
- **Accessors:** statusBadgeHtml, priorityBadgeHtml, isOverdue

### GeneralTaskComment
- **Table:** general_task_comments
- **Relationships:** task (BelongsTo GeneralTask), user

## DVR Models

### DailyVisitReport
- **Table:** daily_visit_reports
- **Scopes:** visibleTo(user), pendingFollowUps, overdueFollowUps
- **Relationships:** user, branch, quotation, loan, parentVisit, followUpVisit
- **Key methods:** isVisibleTo(), isEditableBy(), isDeletableBy(), getVisitChain()
- **Accessors:** isOverdueFollowUp
