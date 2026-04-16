# Stage E: Stage Workflow Engine

## Overview

Implements the core workflow engine — stage assignments, status transitions, sequential advancement, progress tracking, and the stage management UI. This is the backbone that powers the 10-stage loan processing workflow.

## Dependencies

- Stage A (stages table, LoanStageService)
- Stage B (loan_details)
- Stage C (LoanController, loan views)
- Stage D (LoanDocumentService — for document completion check)

---

## Migrations

### Migration 1: `create_stage_assignments_table`

**File**: `database/migrations/xxxx_xx_xx_create_stage_assignments_table.php`

**Table**: `stage_assignments`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_id | FK → loan_details | no | | cascade on delete |
| stage_key | string | no | | references stages.stage_key |
| assigned_to | FK → users | yes | null | set null on delete |
| status | string | no | 'pending' | values: pending, in_progress, completed, rejected, skipped |
| priority | string | no | 'normal' | values: low, normal, high, urgent |
| started_at | timestamp | yes | null | when status changed to in_progress |
| completed_at | timestamp | yes | null | when status changed to completed/rejected/skipped |
| completed_by | FK → users | yes | null | set null on delete |
| is_parallel_stage | boolean | no | false | true for sub-stages of stage 4 |
| parent_stage_key | string | yes | null | 'parallel_processing' for sub-stages |
| notes | text | yes | null | stage-specific data, can store JSON for stages 5-9 |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**:
- unique composite on `(loan_id, stage_key)` — one assignment per stage per loan
- index on `loan_id`
- index on `stage_key`
- index on `assigned_to`
- index on `status`
- index on `parent_stage_key`

**Validation constraints** (enforced in service):
- `status` must be one of: `pending`, `in_progress`, `completed`, `rejected`, `skipped`
- `priority` must be one of: `low`, `normal`, `high`, `urgent`

---

### Migration 2: `create_loan_progress_table`

**File**: `database/migrations/xxxx_xx_xx_create_loan_progress_table.php`

**Table**: `loan_progress`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| loan_id | FK → loan_details | no | | cascade on delete, unique |
| total_stages | integer | no | 10 | total main stages for this loan |
| completed_stages | integer | no | 0 | number of completed main stages |
| overall_percentage | decimal(5,2) | no | 0.00 | 0.00 to 100.00 |
| estimated_completion | date | yes | null | estimated completion date |
| workflow_snapshot | text | yes | null | JSON snapshot of all stage statuses |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: unique on `loan_id`

---

### Migration 3: `create_stage_queries_table`

**File**: `database/migrations/xxxx_xx_xx_create_stage_queries_table.php`

**Table**: `stage_queries` — Two-way query/response system within stages. Queries **block** stage completion.

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| stage_assignment_id | FK → stage_assignments | no | | cascade on delete |
| loan_id | FK → loan_details | no | | cascade on delete (denormalized) |
| stage_key | string | no | | denormalized for display |
| query_text | text | no | | the question/issue raised |
| raised_by | FK → users | no | | who raised the query |
| status | string | no | 'pending' | values: pending, responded, resolved |
| resolved_at | timestamp | yes | null | when query was resolved |
| resolved_by | FK → users | yes | null | who resolved it |
| created_at | timestamp | yes | | |
| updated_at | timestamp | yes | | |

**Indexes**: index on `stage_assignment_id`; index on `loan_id`; index on `status`; composite on `(stage_assignment_id, status)`

---

### Migration 4: `create_query_responses_table`

**File**: `database/migrations/xxxx_xx_xx_create_query_responses_table.php`

**Table**: `query_responses` — Responses to stage queries (conversation thread).

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| stage_query_id | FK → stage_queries | no | | cascade on delete |
| response_text | text | no | | the response content |
| responded_by | FK → users | no | | who responded |
| created_at | timestamp | yes | | |

**Indexes**: index on `stage_query_id`

---

**Query System Business Rules**:
- A stage **cannot be completed/approved** while it has queries with status `pending` or `responded` (only `resolved` allows completion)
- A query transitions: `pending` → `responded` (when any response added) → `resolved` (when raiser resolves)
- Multiple responses allowed per query (conversation thread)
- After resolving, user can "Raise Follow-up" which creates a NEW query on the same stage
- Queries generate notifications to the relevant user (assignee or query raiser)
- Both parallel and sequential stages support queries
- Query ID format: `Q{timestamp}` (auto-generated)

**Query UI Behavior on Stage Card**:
- **Active (pending) query**: Yellow banner shows query text, who raised it, when. "Respond to Query" button visible to assignee. Complete/Approve button DISABLED with tooltip "Resolve pending query first"
- **Responded query**: Blue banner shows response text. Two buttons: "Resolve & Approve" and "Raise Follow-up"
- **Resolved query**: Gray, collapsed. No action buttons.
- **Multiple queries**: Each shown as separate banner, most recent first

---

## Models

### StageAssignment

**File**: `app/Models/StageAssignment.php`

**Table**: `stage_assignments`

**Fillable**: `loan_id`, `stage_key`, `assigned_to`, `status`, `priority`, `started_at`, `completed_at`, `completed_by`, `is_parallel_stage`, `parent_stage_key`, `notes`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `is_parallel_stage` | boolean |
| `started_at` | datetime |
| `completed_at` | datetime |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `assignee()` | BelongsTo | User | `assigned_to` |
| `completedByUser()` | BelongsTo | User | `completed_by` |
| `stage()` | BelongsTo | Stage | `stage_key` (local) → `stage_key` (foreign) |

**Note on `stage()` relationship**: Uses a non-standard FK pairing:
```php
public function stage(): BelongsTo
{
    return $this->belongsTo(Stage::class, 'stage_key', 'stage_key');
}
```

**Scopes**:
| Scope | Query |
|-------|-------|
| `scopePending($q)` | `$q->where('status', 'pending')` |
| `scopeInProgress($q)` | `$q->where('status', 'in_progress')` |
| `scopeCompleted($q)` | `$q->where('status', 'completed')` |
| `scopeForUser($q, $userId)` | `$q->where('assigned_to', $userId)` |
| `scopeMainStages($q)` | `$q->where('is_parallel_stage', false)->whereNull('parent_stage_key')` |
| `scopeSubStagesOf($q, $parentKey)` | `$q->where('parent_stage_key', $parentKey)` |

**Status Constants**:
```php
const STATUS_PENDING = 'pending';
const STATUS_IN_PROGRESS = 'in_progress';
const STATUS_COMPLETED = 'completed';
const STATUS_REJECTED = 'rejected';
const STATUS_SKIPPED = 'skipped';

const STATUSES = [
    self::STATUS_PENDING,
    self::STATUS_IN_PROGRESS,
    self::STATUS_COMPLETED,
    self::STATUS_REJECTED,
    self::STATUS_SKIPPED,
];

const STATUS_LABELS = [
    'pending' => ['label' => 'Pending', 'color' => 'secondary'],
    'in_progress' => ['label' => 'In Progress', 'color' => 'primary'],
    'completed' => ['label' => 'Completed', 'color' => 'success'],
    'rejected' => ['label' => 'Rejected', 'color' => 'danger'],
    'skipped' => ['label' => 'Skipped', 'color' => 'warning'],
];

const PRIORITY_LABELS = [
    'low' => ['label' => 'Low', 'color' => 'secondary'],
    'normal' => ['label' => 'Normal', 'color' => 'info'],
    'high' => ['label' => 'High', 'color' => 'warning'],
    'urgent' => ['label' => 'Urgent', 'color' => 'danger'],
];
```

**Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `isActionable()` | bool | Can this stage be worked on? (pending or in_progress) |
| `canTransitionTo(string $status)` | bool | Is the transition valid? |

**Valid Transitions**:
```
pending → in_progress, skipped
in_progress → completed, rejected
rejected → in_progress (retry)
skipped → (terminal)
completed → (terminal)
```

```php
public function canTransitionTo(string $newStatus): bool
{
    $allowed = [
        'pending' => ['in_progress', 'skipped'],
        'in_progress' => ['completed', 'rejected'],
        'rejected' => ['in_progress'],
        'skipped' => [],
        'completed' => [],
    ];

    return in_array($newStatus, $allowed[$this->status] ?? []);
}
```

---

### LoanProgress

**File**: `app/Models/LoanProgress.php`

**Table**: `loan_progress`

**Fillable**: `loan_id`, `total_stages`, `completed_stages`, `overall_percentage`, `estimated_completion`, `workflow_snapshot`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `total_stages` | integer |
| `completed_stages` | integer |
| `overall_percentage` | decimal:2 |
| `estimated_completion` | date |
| `workflow_snapshot` | array |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `loan()` | BelongsTo | LoanDetail | `loan_id` |

---

### StageQuery

**File**: `app/Models/StageQuery.php`

**Table**: `stage_queries`

**Fillable**: `stage_assignment_id`, `loan_id`, `stage_key`, `query_text`, `raised_by`, `status`, `resolved_at`, `resolved_by`

**Casts**:
| Attribute | Cast |
|-----------|------|
| `resolved_at` | datetime |

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `stageAssignment()` | BelongsTo | StageAssignment | `stage_assignment_id` |
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `raisedByUser()` | BelongsTo | User | `raised_by` |
| `resolvedByUser()` | BelongsTo | User | `resolved_by` |
| `responses()` | HasMany | QueryResponse | `stage_query_id` |

**Scopes**:
| Scope | Query |
|-------|-------|
| `scopePending($q)` | `$q->where('status', 'pending')` |
| `scopeResponded($q)` | `$q->where('status', 'responded')` |
| `scopeResolved($q)` | `$q->where('status', 'resolved')` |
| `scopeActive($q)` | `$q->whereIn('status', ['pending', 'responded'])` |

**Constants**:
```php
const STATUS_PENDING = 'pending';
const STATUS_RESPONDED = 'responded';
const STATUS_RESOLVED = 'resolved';
```

---

### QueryResponse

**File**: `app/Models/QueryResponse.php`

**Table**: `query_responses`

**Fillable**: `stage_query_id`, `response_text`, `responded_by`

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `query()` | BelongsTo | StageQuery | `stage_query_id` |
| `respondedByUser()` | BelongsTo | User | `responded_by` |

---

### StageAssignment Model — Add Query Relationships

```php
public function queries(): HasMany
{
    return $this->hasMany(StageQuery::class, 'stage_assignment_id');
}

public function activeQueries(): HasMany
{
    return $this->hasMany(StageQuery::class, 'stage_assignment_id')->active();
}

public function hasPendingQueries(): bool
{
    return $this->queries()->whereIn('status', ['pending', 'responded'])->exists();
}
```

---

### LoanDetail Model Additions

**File**: `app/Models/LoanDetail.php` (modify)

**New Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `stageAssignments()` | HasMany | StageAssignment | `loan_id` |
| `progress()` | HasOne | LoanProgress | `loan_id` |
| `stageQueries()` | HasMany | StageQuery | `loan_id` |

**New Methods**:
| Method | Returns | Description |
|--------|---------|-------------|
| `currentStageAssignment()` | ?StageAssignment | `$this->stageAssignments()->where('stage_key', $this->current_stage)->first()` |
| `getStageAssignment(string $key)` | ?StageAssignment | `$this->stageAssignments()->where('stage_key', $key)->first()` |

**Update `scopeVisibleTo`** — extend to include stage assignments:
```php
public function scopeVisibleTo($query, User $user): void
{
    if ($user->hasPermission('view_all_loans')) return;

    $query->where(function ($q) use ($user) {
        $q->where('created_by', $user->id)
          ->orWhere('assigned_advisor', $user->id)
          ->orWhereHas('stageAssignments', fn($sq) => $sq->where('assigned_to', $user->id));
    });
}
```

---

## Service: LoanStageService (Extended)

**File**: `app/Services/LoanStageService.php` (extend from Stage A)

### New Constructor

```php
public function __construct(
    private LoanDocumentService $documentService,
) {}
```

### New Methods

#### `initializeStages(LoanDetail $loan): void`

**Purpose**: Creates stage_assignment rows for a new loan and a loan_progress record.

```php
public function initializeStages(LoanDetail $loan): void
{
    $stages = Stage::orderBy('sequence_order')->get();
    $mainCount = 0;

    foreach ($stages as $stage) {
        $isParallel = $stage->parent_stage_key !== null;

        StageAssignment::create([
            'loan_id' => $loan->id,
            'stage_key' => $stage->stage_key,
            'status' => 'pending',
            'priority' => 'normal',
            'is_parallel_stage' => $isParallel,
            'parent_stage_key' => $stage->parent_stage_key,
        ]);

        // Count main stages (not sub-stages) for progress tracking
        if (!$isParallel && $stage->parent_stage_key === null) {
            $mainCount++;
        }
    }

    LoanProgress::create([
        'loan_id' => $loan->id,
        'total_stages' => $mainCount, // Typically 10
        'completed_stages' => 0,
        'overall_percentage' => 0,
    ]);
}
```

**Called from**: `LoanConversionService` after creating loan.

---

#### `autoCompleteStages(LoanDetail $loan, array $stageKeys): void`

**Purpose**: Mark specific stages as completed (used when converting from quotation — stages 1 and 2 are auto-done).

```php
public function autoCompleteStages(LoanDetail $loan, array $stageKeys): void
{
    foreach ($stageKeys as $key) {
        $assignment = $loan->stageAssignments()->where('stage_key', $key)->first();
        if ($assignment) {
            $assignment->update([
                'status' => 'completed',
                'started_at' => now(),
                'completed_at' => now(),
                'completed_by' => auth()->id(),
            ]);
        }
    }

    $this->recalculateProgress($loan);
}
```

**Called from**: `LoanConversionService::convertFromQuotation()`:
```php
$this->stageService->initializeStages($loan);
$this->stageService->autoCompleteStages($loan, ['inquiry', 'document_selection']);
```

---

#### `updateStageStatus(LoanDetail $loan, string $stageKey, string $newStatus, ?int $userId = null): StageAssignment`

**Purpose**: Core transition method. Validates transition, updates status, auto-advances to next stage.

```php
public function updateStageStatus(LoanDetail $loan, string $stageKey, string $newStatus, ?int $userId = null): StageAssignment
{
    $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->firstOrFail();

    if (!$assignment->canTransitionTo($newStatus)) {
        throw new \RuntimeException(
            "Cannot transition stage '{$stageKey}' from '{$assignment->status}' to '{$newStatus}'"
        );
    }

    $updateData = ['status' => $newStatus];

    if ($newStatus === 'in_progress' && !$assignment->started_at) {
        $updateData['started_at'] = now();
    }

    if (in_array($newStatus, ['completed', 'rejected', 'skipped'])) {
        $updateData['completed_at'] = now();
        $updateData['completed_by'] = $userId ?? auth()->id();
    }

    $assignment->update($updateData);

    // Log activity
    ActivityLog::log('update_stage_status', $assignment, [
        'loan_number' => $loan->loan_number,
        'stage_key' => $stageKey,
        'old_status' => $assignment->getOriginal('status'),
        'new_status' => $newStatus,
    ]);

    // Handle post-transition logic
    if ($newStatus === 'completed') {
        $this->handleStageCompletion($loan, $stageKey);
    }

    $this->recalculateProgress($loan);

    return $assignment->fresh();
}
```

---

#### `handleStageCompletion(LoanDetail $loan, string $completedStageKey): void`

**Purpose**: After a stage completes, auto-advance to the next stage.

```php
protected function handleStageCompletion(LoanDetail $loan, string $completedStageKey): void
{
    // If this is a parallel sub-stage, check parallel completion (Stage F)
    $assignment = $loan->stageAssignments()->where('stage_key', $completedStageKey)->first();
    if ($assignment && $assignment->is_parallel_stage) {
        $this->checkParallelCompletion($loan);
        return;
    }

    // For sequential stages, advance to next
    $nextKey = $this->getNextStage($completedStageKey);
    if ($nextKey) {
        $loan->update(['current_stage' => $nextKey]);
    }

    // If disbursement completed, mark loan as completed
    if ($completedStageKey === 'disbursement') {
        $loan->update(['status' => LoanDetail::STATUS_COMPLETED]);
    }
}
```

---

#### `getNextStage(string $currentStageKey): ?string`

```php
public function getNextStage(string $currentStageKey): ?string
{
    $mainKeys = $this->getMainStageKeys();
    $currentIndex = array_search($currentStageKey, $mainKeys);

    if ($currentIndex === false || $currentIndex >= count($mainKeys) - 1) {
        return null; // Last stage or not found
    }

    return $mainKeys[$currentIndex + 1];
}
```

**Stage key order**: inquiry → document_selection → document_collection → parallel_processing → rate_pf → sanction → docket → kfs → esign → disbursement

---

#### `assignStage(LoanDetail $loan, string $stageKey, int $userId): StageAssignment`

```php
public function assignStage(LoanDetail $loan, string $stageKey, int $userId): StageAssignment
{
    $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->firstOrFail();

    $assignment->update(['assigned_to' => $userId]);

    ActivityLog::log('assign_stage', $assignment, [
        'loan_number' => $loan->loan_number,
        'stage_key' => $stageKey,
        'assigned_to_name' => User::find($userId)?->name,
    ]);

    return $assignment->fresh();
}
```

---

#### `skipStage(LoanDetail $loan, string $stageKey, ?int $userId = null): StageAssignment`

```php
public function skipStage(LoanDetail $loan, string $stageKey, ?int $userId = null): StageAssignment
{
    return $this->updateStageStatus($loan, $stageKey, 'skipped', $userId);
}
```

---

#### `canStartStage(LoanDetail $loan, string $stageKey): bool`

```php
public function canStartStage(LoanDetail $loan, string $stageKey): bool
{
    $stage = $this->getStageByKey($stageKey);
    if (!$stage) return false;

    // Sub-stages can always start if parent is current
    if ($stage->parent_stage_key) {
        $parentAssignment = $loan->getStageAssignment($stage->parent_stage_key);
        return $parentAssignment && in_array($parentAssignment->status, ['pending', 'in_progress']);
    }

    // First stage can always start
    $mainKeys = $this->getMainStageKeys();
    $index = array_search($stageKey, $mainKeys);
    if ($index === 0) return true;

    // Other stages: previous must be completed or skipped
    $prevKey = $mainKeys[$index - 1];
    $prevAssignment = $loan->getStageAssignment($prevKey);

    return $prevAssignment && in_array($prevAssignment->status, ['completed', 'skipped']);
}
```

---

#### `recalculateProgress(LoanDetail $loan): LoanProgress`

```php
public function recalculateProgress(LoanDetail $loan): LoanProgress
{
    $progress = $loan->progress ?? LoanProgress::create([
        'loan_id' => $loan->id,
        'total_stages' => 10,
    ]);

    // Count main stages (not sub-stages)
    $mainAssignments = $loan->stageAssignments()
        ->mainStages()
        ->get();

    $total = $mainAssignments->count();
    $completed = $mainAssignments->whereIn('status', ['completed', 'skipped'])->count();
    $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

    // Build workflow snapshot
    $snapshot = $loan->stageAssignments()->with('stage')->get()->map(fn($sa) => [
        'stage_key' => $sa->stage_key,
        'status' => $sa->status,
        'assigned_to' => $sa->assigned_to,
    ])->toArray();

    $progress->update([
        'total_stages' => $total,
        'completed_stages' => $completed,
        'overall_percentage' => $percentage,
        'workflow_snapshot' => $snapshot,
    ]);

    return $progress->fresh();
}
```

---

#### `getLoanStageStatus(LoanDetail $loan): Collection`

```php
public function getLoanStageStatus(LoanDetail $loan): Collection
{
    return $loan->stageAssignments()
        ->with(['stage', 'assignee'])
        ->get()
        ->sortBy(fn($sa) => $sa->stage?->sequence_order ?? 999);
}
```

---

### Query Blocking in `updateStageStatus()`

The `updateStageStatus()` method MUST check for pending queries before allowing completion:

```php
// In updateStageStatus(), before allowing 'completed' transition:
if ($newStatus === 'completed') {
    if ($assignment->hasPendingQueries()) {
        throw new \RuntimeException(
            "Cannot complete stage '{$stageKey}' — there are unresolved queries. Resolve all queries first."
        );
    }
}
```

---

### Rejection Flow in `LoanStageService`

#### `rejectLoan(LoanDetail $loan, string $stageKey, string $reason, ?int $userId = null): LoanDetail`

**Purpose**: Rejecting a stage rejects the ENTIRE LOAN (not just the stage). This matches shf_task behavior.

```php
public function rejectLoan(LoanDetail $loan, string $stageKey, string $reason, ?int $userId = null): LoanDetail
{
    $userId = $userId ?? auth()->id();

    $loan->update([
        'status' => LoanDetail::STATUS_REJECTED,
        'rejected_at' => now(),
        'rejected_by' => $userId,
        'rejected_stage' => $stageKey,
        'rejection_reason' => $reason,
    ]);

    // Mark the stage as rejected
    $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->first();
    if ($assignment) {
        $assignment->update([
            'status' => 'rejected',
            'completed_at' => now(),
            'completed_by' => $userId,
        ]);
    }

    ActivityLog::log('reject_loan', $loan, [
        'loan_number' => $loan->loan_number,
        'rejected_stage' => $stageKey,
        'reason' => $reason,
    ]);

    // Notify loan creator and advisor
    app(NotificationService::class)->notify(
        $loan->created_by,
        'Loan Rejected',
        "Loan #{$loan->loan_number} was rejected at stage '{$stageKey}'. Reason: {$reason}",
        'error', $loan->id, $stageKey, route('loans.show', $loan),
    );

    return $loan->fresh();
}
```

**Business rule**: Rejected loans are terminal — they cannot be reactivated. A new loan must be created.

---

## Service: StageQueryService

**File**: `app/Services/StageQueryService.php`

### Methods

#### `raiseQuery(StageAssignment $assignment, string $queryText, int $userId): StageQuery`

```php
public function raiseQuery(StageAssignment $assignment, string $queryText, int $userId): StageQuery
{
    $query = StageQuery::create([
        'stage_assignment_id' => $assignment->id,
        'loan_id' => $assignment->loan_id,
        'stage_key' => $assignment->stage_key,
        'query_text' => trim($queryText),
        'raised_by' => $userId,
        'status' => 'pending',
    ]);

    // Notify the stage assignee (if different from raiser)
    $notifyUserId = $assignment->assigned_to !== $userId ? $assignment->assigned_to : $assignment->loan->created_by;
    if ($notifyUserId) {
        app(NotificationService::class)->notify(
            $notifyUserId,
            'Query Raised',
            "Query on '{$assignment->stage?->stage_name_en}' for Loan #{$assignment->loan->loan_number}: " . Str::limit($queryText, 80),
            'warning', $assignment->loan_id, $assignment->stage_key, route('loans.stages', $assignment->loan_id),
        );
    }

    ActivityLog::log('raise_query', $query, [
        'loan_number' => $assignment->loan->loan_number,
        'stage_key' => $assignment->stage_key,
        'preview' => Str::limit($queryText, 100),
    ]);

    return $query;
}
```

#### `respondToQuery(StageQuery $query, string $responseText, int $userId): QueryResponse`

```php
public function respondToQuery(StageQuery $query, string $responseText, int $userId): QueryResponse
{
    $response = QueryResponse::create([
        'stage_query_id' => $query->id,
        'response_text' => trim($responseText),
        'responded_by' => $userId,
    ]);

    $query->update(['status' => 'responded']);

    // Notify the query raiser
    app(NotificationService::class)->notify(
        $query->raised_by,
        'Query Response',
        "Response to your query on '{$query->stage_key}': " . Str::limit($responseText, 80),
        'info', $query->loan_id, $query->stage_key, route('loans.stages', $query->loan_id),
    );

    return $response;
}
```

#### `resolveQuery(StageQuery $query, int $userId): StageQuery`

```php
public function resolveQuery(StageQuery $query, int $userId): StageQuery
{
    $query->update([
        'status' => 'resolved',
        'resolved_at' => now(),
        'resolved_by' => $userId,
    ]);

    return $query->fresh();
}
```

#### `getQueriesForStage(StageAssignment $assignment): Collection`

```php
public function getQueriesForStage(StageAssignment $assignment): Collection
{
    return $assignment->queries()->with(['raisedByUser', 'responses.respondedByUser'])->latest()->get();
}
```

#### `hasPendingQueries(StageAssignment $assignment): bool`

```php
public function hasPendingQueries(StageAssignment $assignment): bool
{
    return $assignment->hasPendingQueries();
}
```

---

## Controller: LoanStageController

**File**: `app/Http/Controllers/LoanStageController.php`

### Constructor

```php
public function __construct(
    private LoanStageService $stageService,
) {}
```

### Actions

#### `index(LoanDetail $loan)` — `GET /loans/{loan}/stages`

**Permission**: `view_loans`

```php
public function index(LoanDetail $loan)
{
    $this->authorizeView($loan);

    $stageAssignments = $this->stageService->getLoanStageStatus($loan);
    $mainStages = $stageAssignments->filter(fn($sa) => !$sa->is_parallel_stage && $sa->parent_stage_key === null);
    $subStages = $stageAssignments->filter(fn($sa) => $sa->is_parallel_stage || $sa->parent_stage_key !== null);
    $progress = $loan->progress;
    $assignableUsers = User::whereNotNull('task_role')->where('is_active', true)->orderBy('name')->get();

    return view('loans.stages', compact('loan', 'mainStages', 'subStages', 'progress', 'assignableUsers'));
}
```

---

#### `updateStatus(Request $request, LoanDetail $loan, string $stageKey)` — `POST /loans/{loan}/stages/{stageKey}/status`

**Permission**: `manage_loan_stages`

```php
public function updateStatus(Request $request, LoanDetail $loan, string $stageKey): JsonResponse
{
    $validated = $request->validate([
        'status' => 'required|in:in_progress,completed,rejected,skipped',
    ]);

    // Skip requires separate permission
    if ($validated['status'] === 'skipped' && !auth()->user()->hasPermission('skip_loan_stages')) {
        return response()->json(['error' => 'You do not have permission to skip stages'], 403);
    }

    try {
        $assignment = $this->stageService->updateStageStatus(
            $loan, $stageKey, $validated['status'], auth()->id()
        );

        $loan->refresh();
        $progress = $this->stageService->recalculateProgress($loan);

        return response()->json([
            'success' => true,
            'assignment' => [
                'stage_key' => $assignment->stage_key,
                'status' => $assignment->status,
                'started_at' => $assignment->started_at?->format('d M Y H:i'),
                'completed_at' => $assignment->completed_at?->format('d M Y H:i'),
            ],
            'current_stage' => $loan->current_stage,
            'progress' => [
                'completed' => $progress->completed_stages,
                'total' => $progress->total_stages,
                'percentage' => $progress->overall_percentage,
            ],
        ]);
    } catch (\RuntimeException $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
}
```

---

#### `assign(Request $request, LoanDetail $loan, string $stageKey)` — `POST /loans/{loan}/stages/{stageKey}/assign`

**Permission**: `manage_loan_stages`

```php
public function assign(Request $request, LoanDetail $loan, string $stageKey): JsonResponse
{
    $validated = $request->validate([
        'user_id' => 'required|exists:users,id',
    ]);

    $assignment = $this->stageService->assignStage($loan, $stageKey, $validated['user_id']);

    return response()->json([
        'success' => true,
        'assigned_to' => $assignment->assignee?->name,
    ]);
}
```

---

#### `skip(LoanDetail $loan, string $stageKey)` — `POST /loans/{loan}/stages/{stageKey}/skip`

**Permission**: `skip_loan_stages`

```php
public function skip(LoanDetail $loan, string $stageKey): JsonResponse
{
    try {
        $assignment = $this->stageService->skipStage($loan, $stageKey, auth()->id());

        $loan->refresh();
        $progress = $this->stageService->recalculateProgress($loan);

        return response()->json([
            'success' => true,
            'assignment' => [...],
            'current_stage' => $loan->current_stage,
            'progress' => [...],
        ]);
    } catch (\RuntimeException $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
}
```

---

### Additional Controller Actions

#### `reject(Request $request, LoanDetail $loan, string $stageKey)` — `POST /loans/{loan}/stages/{stageKey}/reject`

**Permission**: `manage_loan_stages`

```php
public function reject(Request $request, LoanDetail $loan, string $stageKey): JsonResponse
{
    $validated = $request->validate([
        'reason' => 'required|string|max:2000',
    ]);

    $loan = $this->stageService->rejectLoan($loan, $stageKey, $validated['reason']);

    return response()->json([
        'success' => true,
        'message' => 'Loan rejected',
        'loan_status' => $loan->status,
    ]);
}
```

#### `raiseQuery(Request $request, LoanDetail $loan, string $stageKey)` — `POST /loans/{loan}/stages/{stageKey}/query`

**Permission**: `manage_loan_stages`

```php
public function raiseQuery(Request $request, LoanDetail $loan, string $stageKey): JsonResponse
{
    $validated = $request->validate([
        'query_text' => 'required|string|max:5000',
    ]);

    $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->firstOrFail();
    $query = app(StageQueryService::class)->raiseQuery($assignment, $validated['query_text'], auth()->id());

    return response()->json(['success' => true, 'query' => $query->load('raisedByUser')]);
}
```

#### `respondToQuery(Request $request, StageQuery $query)` — `POST /loans/queries/{query}/respond`

**Permission**: `manage_loan_stages`

```php
public function respondToQuery(Request $request, StageQuery $query): JsonResponse
{
    $validated = $request->validate([
        'response_text' => 'required|string|max:5000',
    ]);

    $response = app(StageQueryService::class)->respondToQuery($query, $validated['response_text'], auth()->id());

    return response()->json(['success' => true, 'response' => $response->load('respondedByUser')]);
}
```

#### `resolveQuery(StageQuery $query)` — `POST /loans/queries/{query}/resolve`

**Permission**: `manage_loan_stages`

```php
public function resolveQuery(StageQuery $query): JsonResponse
{
    $query = app(StageQueryService::class)->resolveQuery($query, auth()->id());

    return response()->json(['success' => true]);
}
```

---

## Routes

**File**: `routes/web.php` (add to loan group)

```php
// Stage workflow
Route::middleware(['auth', 'active'])->group(function () {
    Route::middleware('permission:view_loans')->group(function () {
        Route::get('/loans/{loan}/stages', [LoanStageController::class, 'index'])
            ->name('loans.stages');
    });

    Route::middleware('permission:manage_loan_stages')->group(function () {
        Route::post('/loans/{loan}/stages/{stageKey}/status', [LoanStageController::class, 'updateStatus'])
            ->name('loans.stages.status');
        Route::post('/loans/{loan}/stages/{stageKey}/assign', [LoanStageController::class, 'assign'])
            ->name('loans.stages.assign');
        Route::post('/loans/{loan}/stages/{stageKey}/reject', [LoanStageController::class, 'reject'])
            ->name('loans.stages.reject');
        Route::post('/loans/{loan}/stages/{stageKey}/query', [LoanStageController::class, 'raiseQuery'])
            ->name('loans.stages.query');
        Route::post('/loans/queries/{query}/respond', [LoanStageController::class, 'respondToQuery'])
            ->name('loans.queries.respond');
        Route::post('/loans/queries/{query}/resolve', [LoanStageController::class, 'resolveQuery'])
            ->name('loans.queries.resolve');
    });

    Route::middleware('permission:skip_loan_stages')->group(function () {
        Route::post('/loans/{loan}/stages/{stageKey}/skip', [LoanStageController::class, 'skip'])
            ->name('loans.stages.skip');
    });
});
```

---

## Views

### `resources/views/loans/stages.blade.php`

**Extends**: `layouts.app`

**Layout**:
```
┌──────────────────────────────────────────────────────────┐
│ Loan Stages — #SHF-202604-0001          ← Back to Loan  │
├──────────────────────────────────────────────────────────┤
│ Progress Bar (horizontal):                                │
│ ●────●────●────◐────○────○────○────○────○────○          │
│ 1    2    3    4    5    6    7    8    9    10           │
│ Done Done Done Active                                     │
│                                                           │
│ 30% complete (3/10 stages)                               │
├──────────────────────────────────────────────────────────┤
│ Stage Cards (expandable):                                 │
│                                                           │
│ ✅ Stage 1: Loan Inquiry              Completed          │
│    Started: 05 Apr · Completed: 05 Apr                   │
│                                                           │
│ ✅ Stage 2: Document Selection        Completed          │
│    Started: 05 Apr · Completed: 05 Apr                   │
│                                                           │
│ 🔵 Stage 3: Document Collection      In Progress        │
│    Assigned to: Staff User                                │
│    [View Documents] [Complete ✓] [Skip ⏭]               │
│                                                           │
│ ⬜ Stage 4: Parallel Processing       Pending            │
│    (4 sub-stages — expanded in Stage F)                  │
│    [Assign ▼]                                            │
│                                                           │
│ ⬜ Stage 5: Rate & PF Request         Pending (locked)   │
│    Cannot start until Stage 4 completes                  │
│    ...                                                    │
│                                                           │
│ ⬜ Stage 10: Disbursement             Pending (locked)   │
│    Decision: Fund Transfer or Cheque                     │
└──────────────────────────────────────────────────────────┘
```

---

### `resources/views/loans/partials/progress-bar.blade.php`

**Included via**: `@include('loans.partials.progress-bar', ['loan' => $loan, 'mainStages' => $mainStages])`

**HTML structure**:
```html
<div class="shf-progress-track d-flex align-items-center mb-4">
    @foreach($mainStages as $assignment)
        <div class="shf-progress-dot shf-stage-{{ $assignment->status }} {{ $loan->current_stage === $assignment->stage_key ? 'shf-stage-current' : '' }}">
            <span class="shf-progress-dot-number">{{ $loop->iteration }}</span>
        </div>
        @if(!$loop->last)
            <div class="shf-progress-line shf-stage-{{ $assignment->status }}"></div>
        @endif
    @endforeach
</div>
```

---

### `resources/views/loans/partials/stage-card.blade.php`

**Included via**: `@include('loans.partials.stage-card', ['assignment' => $assignment, 'loan' => $loan, 'assignableUsers' => $assignableUsers])`

**Receives**: `$assignment` (StageAssignment with loaded stage and assignee)

**HTML structure**:
```html
<div class="card mb-3 shf-stage-card shf-stage-{{ $assignment->status }}" id="stage-{{ $assignment->stage_key }}">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <span class="badge bg-{{ StageAssignment::STATUS_LABELS[$assignment->status]['color'] }}">
                {{ StageAssignment::STATUS_LABELS[$assignment->status]['label'] }}
            </span>
            <strong class="ms-2">{{ $assignment->stage?->stage_name_en }}</strong>
            @if($assignment->stage?->stage_name_gu)
                <small class="text-muted ms-1">({{ $assignment->stage->stage_name_gu }})</small>
            @endif
        </div>
        <div>
            @if($assignment->assignee)
                <small class="text-muted">Assigned: {{ $assignment->assignee->name }}</small>
            @endif
        </div>
    </div>
    <div class="card-body">
        {{-- Timing info --}}
        @if($assignment->started_at)
            <small class="text-muted">Started: {{ $assignment->started_at->format('d M Y H:i') }}</small>
        @endif
        @if($assignment->completed_at)
            <small class="text-muted ms-3">Completed: {{ $assignment->completed_at->format('d M Y H:i') }}</small>
        @endif

        {{-- Actions --}}
        <div class="mt-3">
            @if($assignment->status === 'pending' && $canStart)
                <button class="btn btn-sm btn-primary shf-stage-action" data-action="in_progress">
                    Start Stage
                </button>
            @endif

            @if($assignment->status === 'in_progress')
                <button class="btn btn-sm btn-success shf-stage-action" data-action="completed">
                    Complete
                </button>
                <button class="btn btn-sm btn-outline-danger shf-stage-action" data-action="rejected">
                    Reject
                </button>
            @endif

            @if(in_array($assignment->status, ['pending', 'in_progress']) && auth()->user()->hasPermission('skip_loan_stages'))
                <button class="btn btn-sm btn-outline-warning shf-stage-skip" data-stage="{{ $assignment->stage_key }}">
                    Skip
                </button>
            @endif

            {{-- Assign dropdown --}}
            @if(in_array($assignment->status, ['pending', 'in_progress']))
                <select class="form-select form-select-sm d-inline-block w-auto ms-2 shf-stage-assign"
                        data-stage="{{ $assignment->stage_key }}">
                    <option value="">Assign to...</option>
                    @foreach($assignableUsers as $u)
                        <option value="{{ $u->id }}" {{ $assignment->assigned_to === $u->id ? 'selected' : '' }}>
                            {{ $u->name }} ({{ $u->task_role_label }})
                        </option>
                    @endforeach
                </select>
            @endif
        </div>
    </div>
</div>
```

---

## Permissions

**Add to `config/permissions.php`** under `'Loans'` group:

```php
['slug' => 'manage_loan_stages', 'name' => 'Manage Loan Stages', 'description' => 'Update stage status and assignments'],
['slug' => 'skip_loan_stages', 'name' => 'Skip Loan Stages', 'description' => 'Skip stages in loan workflow'],
```

**Role defaults**:
| Permission | Admin | Staff |
|------------|-------|-------|
| `manage_loan_stages` | yes | yes |
| `skip_loan_stages` | yes | no |

---

## Update LoanConversionService (Integration)

After Stage E, update `LoanConversionService::convertFromQuotation()`:

```php
// After creating loan and populating documents:
$this->stageService->initializeStages($loan);
$this->stageService->autoCompleteStages($loan, ['inquiry', 'document_selection']);
```

And `createDirectLoan()`:
```php
// After creating loan and populating documents:
$this->stageService->initializeStages($loan);
```

---

## Verification

```bash
php artisan migrate                              # stage_assignments + loan_progress tables
php artisan db:seed --class=PermissionSeeder     # manage_loan_stages, skip_loan_stages
php artisan serve

# Test flow:
# 1. Convert quotation → stages initialized, 1-2 auto-completed, current_stage = document_collection
# 2. View /loans/{id}/stages → progress bar shows 2/10, stage cards visible
# 3. Start stage 3 → status changes to in_progress
# 4. Complete stage 3 → auto-advances to stage 4 (parallel_processing)
# 5. Assign a user to a stage → name appears on card
# 6. Skip a stage → marked skipped, advances to next
# 7. Try starting stage 5 when stage 4 not done → blocked (canStartStage = false)
# 8. Progress recalculates after each action
```

---

## Files Created/Modified

| Action | File |
|--------|------|
| Create | `database/migrations/xxxx_create_stage_assignments_table.php` |
| Create | `database/migrations/xxxx_create_loan_progress_table.php` |
| Create | `app/Models/StageAssignment.php` |
| Create | `app/Models/LoanProgress.php` |
| Create | `app/Http/Controllers/LoanStageController.php` |
| Create | `resources/views/loans/stages.blade.php` |
| Create | `resources/views/loans/partials/progress-bar.blade.php` |
| Create | `resources/views/loans/partials/stage-card.blade.php` |
| Modify | `app/Models/LoanDetail.php` (add stageAssignments, progress, visibility scope) |
| Modify | `app/Services/LoanStageService.php` (add all workflow methods) |
| Modify | `app/Services/LoanConversionService.php` (call initializeStages + autoComplete) |
| Modify | `config/permissions.php` (add manage_loan_stages, skip_loan_stages) |
| Modify | `routes/web.php` (add stage routes) |
