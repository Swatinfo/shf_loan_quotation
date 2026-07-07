# Stage Transfer & Auto-Assignment System

## Overview

When a stage completes, the system **automatically assigns** the next stage to the best-fit user based on role, bank, and branch. Users can also **transfer** a stage mid-work to another eligible user with tracked history. This document covers both mechanisms.

## Dependencies

- Stage A (users with task_role, task_bank_id, branches)
- Stage E (stage_assignments, LoanStageService)
- Role Integration (STAGE_ROLE_ELIGIBILITY, eligibleForStage scope)

---

## 1. Auto-Assignment on Stage Advance

### How It Works

```
Stage N completed by User A
  ↓
System determines next stage key (N+1)
  ↓
Look up eligible roles: STAGE_ROLE_ELIGIBILITY[N+1]
  ↓
Find best user matching:
  1. Role matches eligible roles
  2. Bank matches loan's bank (for bank_employee)
  3. Branch matches loan's branch (for branch-based roles)
  4. User is active
  ↓
Auto-assign found user to stage N+1
  ↓
Send notification to assigned user
  ↓
If no user found → stage left unassigned (admin must assign manually)
```

### User Selection Priority

When multiple users match, pick the best one in this order:

```php
/**
 * Find the best user to auto-assign for a stage.
 *
 * Priority:
 * 1. User in loan's branch with matching role + bank (most specific)
 * 2. User in loan's branch with matching role (branch match)
 * 3. Any user with matching role + bank (role + bank match)
 * 4. Any user with matching role (role match only)
 * 5. null (no match — leave unassigned)
 */
public function findBestAssignee(string $stageKey, ?int $branchId, ?int $bankId): ?int
```

### Implementation in LoanStageService

#### Updated `handleStageCompletion()` — add auto-assignment

```php
protected function handleStageCompletion(LoanDetail $loan, string $completedStageKey): void
{
    $assignment = $loan->stageAssignments()->where('stage_key', $completedStageKey)->first();

    // Parallel sub-stage → check parallel completion
    if ($assignment && ($assignment->is_parallel_stage || $assignment->parent_stage_key !== null)) {
        $this->checkParallelCompletion($loan);
        return;
    }

    // Sequential → advance to next stage
    $nextKey = $this->getNextStage($completedStageKey);
    if ($nextKey) {
        $loan->update(['current_stage' => $nextKey]);

        // AUTO-ASSIGN the next stage
        $this->autoAssignStage($loan, $nextKey);
    }

    // If disbursement completed → loan completed
    if ($completedStageKey === 'disbursement') {
        $loan->update(['status' => LoanDetail::STATUS_COMPLETED]);
        app(NotificationService::class)->notifyLoanCompleted($loan);
    }
}
```

#### New method: `autoAssignStage()`

```php
/**
 * Auto-assign a stage to the best-fit user.
 * Called when advancing to a new stage.
 */
public function autoAssignStage(LoanDetail $loan, string $stageKey): ?StageAssignment
{
    $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->first();
    if (!$assignment) return null;

    // Don't re-assign if already assigned
    if ($assignment->assigned_to) return $assignment;

    // Find best user
    $userId = $this->findBestAssignee($stageKey, $loan->branch_id, $loan->bank_id);

    if ($userId) {
        $assignment->update(['assigned_to' => $userId]);

        // Notify the assigned user
        app(NotificationService::class)->notifyStageAssignment($loan, $stageKey, $userId);

        ActivityLog::log('auto_assign_stage', $assignment, [
            'loan_number' => $loan->loan_number,
            'stage_key' => $stageKey,
            'assigned_to_name' => User::find($userId)?->name,
            'method' => 'auto',
        ]);
    }

    return $assignment->fresh();
}
```

#### New method: `findBestAssignee()`

```php
/**
 * Find the best user to assign to a stage based on role, bank, and branch.
 *
 * Priority order:
 * 1. Matching role + matching bank (for bank_employee) + in loan's branch
 * 2. Matching role + in loan's branch
 * 3. Matching role + matching bank (for bank_employee)
 * 4. Any user with matching role
 * 5. null (no match)
 */
public function findBestAssignee(string $stageKey, ?int $branchId, ?int $bankId): ?int
{
    $eligibleRoles = self::STAGE_ROLE_ELIGIBILITY[$stageKey] ?? [];
    if (empty($eligibleRoles)) return null;

    // Build base query — active users with eligible roles
    $baseQuery = User::where('is_active', true)
        ->whereIn('task_role', $eligibleRoles);

    // If bank_employee is an eligible role and we have a bank, handle bank matching
    $hasBankRole = in_array('bank_employee', $eligibleRoles);

    if ($hasBankRole && $bankId) {
        // Priority 1: bank_employee matching bank + in branch
        if ($branchId) {
            $user = (clone $baseQuery)
                ->where('task_role', 'bank_employee')
                ->where('task_bank_id', $bankId)
                ->whereHas('branches', fn($q) => $q->where('branches.id', $branchId))
                ->first();
            if ($user) return $user->id;
        }

        // Priority 2: non-bank roles in branch
        $otherRoles = array_diff($eligibleRoles, ['bank_employee']);
        if ($branchId && !empty($otherRoles)) {
            $user = (clone $baseQuery)
                ->whereIn('task_role', $otherRoles)
                ->whereHas('branches', fn($q) => $q->where('branches.id', $branchId))
                ->first();
            if ($user) return $user->id;
        }

        // Priority 3: bank_employee matching bank (any branch)
        $user = (clone $baseQuery)
            ->where('task_role', 'bank_employee')
            ->where('task_bank_id', $bankId)
            ->first();
        if ($user) return $user->id;

        // Priority 4: any eligible non-bank role
        if (!empty($otherRoles)) {
            $user = (clone $baseQuery)
                ->whereIn('task_role', $otherRoles)
                ->first();
            if ($user) return $user->id;
        }

        return null;
    }

    // No bank_employee role involved — simpler matching
    // Priority 1: in loan's branch
    if ($branchId) {
        $user = (clone $baseQuery)
            ->whereHas('branches', fn($q) => $q->where('branches.id', $branchId))
            ->first();
        if ($user) return $user->id;
    }

    // Priority 2: any matching role
    return $baseQuery->first()?->id;
}
```

#### Auto-assign for parallel sub-stages

When `parallel_processing` stage starts (becomes current), auto-assign all 4 sub-stages:

```php
/**
 * When parallel processing becomes current, auto-assign all sub-stages.
 */
public function autoAssignParallelSubStages(LoanDetail $loan): void
{
    $subStages = $loan->stageAssignments()
        ->where('parent_stage_key', 'parallel_processing')
        ->whereNull('assigned_to')
        ->get();

    foreach ($subStages as $assignment) {
        $userId = $this->findBestAssignee(
            $assignment->stage_key,
            $loan->branch_id,
            $loan->bank_id,
        );

        if ($userId) {
            $assignment->update(['assigned_to' => $userId]);
            app(NotificationService::class)->notifyStageAssignment($loan, $assignment->stage_key, $userId);
        }
    }
}
```

Called from `handleStageCompletion()` when the stage before parallel_processing completes:
```php
if ($nextKey === 'parallel_processing') {
    // Also set parallel_processing itself to in_progress
    $parentAssignment = $loan->stageAssignments()
        ->where('stage_key', 'parallel_processing')->first();
    if ($parentAssignment) {
        $parentAssignment->update(['status' => 'in_progress', 'started_at' => now()]);
    }
    $this->autoAssignParallelSubStages($loan);
}
```

---

## 2. Mid-Stage Transfer (Reassignment with History)

### What It Is

A user working on a stage can **transfer** it to another eligible user. This is different from the initial assignment:
- The original assignee initiated the transfer
- A reason is recorded
- Both users are notified
- Transfer history is tracked

### Use Cases

- Loan Advisor starts Rate/PF request, transfers to Bank Employee for response
- Bank Employee needs to hand off Legal Verification to a different bank employee
- Branch Manager reassigns a stuck valuation to another office employee
- Admin transfers any stage to resolve bottlenecks

### Migration: `create_stage_transfers_table`

**File**: `database/migrations/xxxx_xx_xx_create_stage_transfers_table.php`

**Table**: `stage_transfers`

| Column | Type | Nullable | Default | Notes |
|--------|------|----------|---------|-------|
| id | bigint PK | no | auto | |
| stage_assignment_id | FK → stage_assignments | no | | cascade on delete |
| loan_id | FK → loan_details | no | | cascade on delete (denormalized for queries) |
| stage_key | string | no | | denormalized for display |
| transferred_from | FK → users | no | | who initiated the transfer |
| transferred_to | FK → users | no | | who received the transfer |
| reason | text | yes | null | optional reason for transfer |
| transfer_type | string | no | 'manual' | values: manual, auto (auto = system auto-assignment) |
| created_at | timestamp | yes | | |

**Indexes**:
- index on `stage_assignment_id`
- index on `loan_id`
- index on `transferred_from`
- index on `transferred_to`
- index on `created_at`

**Note**: This table is append-only (audit log of transfers). No updates or deletes.

---

### Model: StageTransfer

**File**: `app/Models/StageTransfer.php`

**Table**: `stage_transfers`

**Fillable**: `stage_assignment_id`, `loan_id`, `stage_key`, `transferred_from`, `transferred_to`, `reason`, `transfer_type`

**Relationships**:
| Method | Type | Related | FK |
|--------|------|---------|-----|
| `stageAssignment()` | BelongsTo | StageAssignment | `stage_assignment_id` |
| `loan()` | BelongsTo | LoanDetail | `loan_id` |
| `fromUser()` | BelongsTo | User | `transferred_from` |
| `toUser()` | BelongsTo | User | `transferred_to` |

**Scopes**:
| Scope | Query |
|-------|-------|
| `scopeForLoan($q, $loanId)` | `$q->where('loan_id', $loanId)` |
| `scopeManual($q)` | `$q->where('transfer_type', 'manual')` |

---

### Model Additions

**StageAssignment** — add relationship:
```php
public function transfers(): HasMany
{
    return $this->hasMany(StageTransfer::class, 'stage_assignment_id');
}

public function latestTransfer(): HasOne
{
    return $this->hasOne(StageTransfer::class, 'stage_assignment_id')->latest();
}
```

**LoanDetail** — add relationship:
```php
public function stageTransfers(): HasMany
{
    return $this->hasMany(StageTransfer::class, 'loan_id');
}
```

---

### Service: LoanStageService — Transfer Method

```php
/**
 * Transfer a stage to another user.
 *
 * @param LoanDetail $loan
 * @param string $stageKey
 * @param int $toUserId  The user to transfer to
 * @param ?string $reason  Optional reason for transfer
 * @param int|null $fromUserId  Who is transferring (default: auth user)
 * @return StageAssignment
 */
public function transferStage(
    LoanDetail $loan,
    string $stageKey,
    int $toUserId,
    ?string $reason = null,
    ?int $fromUserId = null,
): StageAssignment {
    $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->firstOrFail();
    $fromUserId = $fromUserId ?? auth()->id();
    $previousAssignee = $assignment->assigned_to;

    // Validate target user is eligible for this stage
    $eligibleRoles = self::STAGE_ROLE_ELIGIBILITY[$stageKey] ?? [];
    $toUser = User::findOrFail($toUserId);

    if (!in_array($toUser->task_role, $eligibleRoles)) {
        throw new \RuntimeException(
            "User '{$toUser->name}' with role '{$toUser->task_role}' is not eligible for stage '{$stageKey}'"
        );
    }

    // For bank_employee stages, validate bank matches
    if ($toUser->task_role === 'bank_employee' && $loan->bank_id) {
        if ($toUser->task_bank_id !== $loan->bank_id) {
            throw new \RuntimeException(
                "Bank employee '{$toUser->name}' is not associated with this loan's bank"
            );
        }
    }

    // Update assignment
    $assignment->update(['assigned_to' => $toUserId]);

    // Record transfer
    StageTransfer::create([
        'stage_assignment_id' => $assignment->id,
        'loan_id' => $loan->id,
        'stage_key' => $stageKey,
        'transferred_from' => $fromUserId,
        'transferred_to' => $toUserId,
        'reason' => $reason,
        'transfer_type' => 'manual',
    ]);

    // Log activity
    ActivityLog::log('transfer_stage', $assignment, [
        'loan_number' => $loan->loan_number,
        'stage_key' => $stageKey,
        'from_user' => User::find($fromUserId)?->name,
        'to_user' => $toUser->name,
        'reason' => $reason,
    ]);

    // Notify the new assignee
    app(NotificationService::class)->notify(
        $toUserId,
        'Stage Transferred to You',
        "'{$assignment->stage?->stage_name_en}' for Loan #{$loan->loan_number} has been transferred to you"
            . ($reason ? ". Reason: {$reason}" : ''),
        'assignment',
        $loan->id,
        $stageKey,
        route('loans.stages', $loan),
    );

    // Notify the previous assignee (if different from initiator)
    if ($previousAssignee && $previousAssignee !== $fromUserId) {
        app(NotificationService::class)->notify(
            $previousAssignee,
            'Stage Transferred Away',
            "'{$assignment->stage?->stage_name_en}' for Loan #{$loan->loan_number} has been reassigned to {$toUser->name}",
            'stage_update',
            $loan->id,
            $stageKey,
            route('loans.stages', $loan),
        );
    }

    return $assignment->fresh();
}
```

#### Also record auto-assignments as transfers

Update `autoAssignStage()` to record in stage_transfers:

```php
// Inside autoAssignStage(), after updating assignment:
if ($userId) {
    $assignment->update(['assigned_to' => $userId]);

    StageTransfer::create([
        'stage_assignment_id' => $assignment->id,
        'loan_id' => $loan->id,
        'stage_key' => $stageKey,
        'transferred_from' => auth()->id() ?? $loan->created_by,
        'transferred_to' => $userId,
        'reason' => 'Auto-assigned on stage advance',
        'transfer_type' => 'auto',
    ]);

    app(NotificationService::class)->notifyStageAssignment($loan, $stageKey, $userId);
}
```

---

### Controller: LoanStageController — Transfer Action

```php
/**
 * Transfer a stage to another user.
 * POST /loans/{loan}/stages/{stageKey}/transfer
 */
public function transfer(Request $request, LoanDetail $loan, string $stageKey): JsonResponse
{
    $validated = $request->validate([
        'user_id' => 'required|exists:users,id',
        'reason' => 'nullable|string|max:1000',
    ]);

    try {
        $assignment = $this->stageService->transferStage(
            $loan,
            $stageKey,
            (int) $validated['user_id'],
            $validated['reason'] ?? null,
        );

        return response()->json([
            'success' => true,
            'assigned_to' => $assignment->assignee?->name,
            'message' => "Stage transferred to {$assignment->assignee?->name}",
        ]);
    } catch (\RuntimeException $e) {
        return response()->json(['error' => $e->getMessage()], 422);
    }
}
```

### Route

```php
Route::middleware('permission:manage_loan_stages')->group(function () {
    Route::post('/loans/{loan}/stages/{stageKey}/transfer', [LoanStageController::class, 'transfer'])
        ->name('loans.stages.transfer');
});
```

---

### UI: Transfer Button on Stage Card

In `loans/partials/stage-card.blade.php`, add a transfer button when stage is in_progress:

```blade
@if($assignment->status === 'in_progress' && $assignment->assigned_to)
    <button class="btn btn-sm btn-outline-secondary shf-stage-transfer"
            data-stage="{{ $assignment->stage_key }}"
            data-loan-id="{{ $loan->id }}"
            data-bs-toggle="modal"
            data-bs-target="#transferModal">
        <i class="bi bi-arrow-left-right"></i> Transfer
    </button>
@endif
```

### Transfer Modal

```html
<div class="modal fade" id="transferModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Transfer Stage</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Transfer to</label>
                    <select class="form-select" id="transferUserId">
                        <!-- Populated dynamically with eligible users -->
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason (optional)</label>
                    <textarea class="form-control" id="transferReason" rows="3"
                              placeholder="Why are you transferring this stage?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn shf-btn-accent" id="confirmTransfer">Transfer</button>
            </div>
        </div>
    </div>
</div>
```

### JS for Transfer

```javascript
// In shf-loans.js
let transferStageKey = null;
let transferLoanId = null;

$(document).on('click', '.shf-stage-transfer', function() {
    transferStageKey = $(this).data('stage');
    transferLoanId = $(this).data('loan-id');

    // Load eligible users for this stage
    // Using the eligibleForStage data passed from server or a separate endpoint
    const $select = $('#transferUserId');
    $select.empty().append('<option value="">Select user...</option>');

    // The eligible users are pre-rendered as data attributes or fetched via AJAX
    const eligibleUsers = $(this).closest('.shf-stage-card').data('eligible-users');
    if (eligibleUsers) {
        eligibleUsers.forEach(u => {
            $select.append(`<option value="${u.id}">${u.name} (${u.task_role_label})</option>`);
        });
    }
});

$('#confirmTransfer').on('click', function() {
    const userId = $('#transferUserId').val();
    const reason = $('#transferReason').val();

    if (!userId) {
        SHFLoans.showToast('Please select a user', 'warning');
        return;
    }

    $.ajax({
        url: `/loans/${transferLoanId}/stages/${transferStageKey}/transfer`,
        method: 'POST',
        data: {
            _token: SHFLoans.csrfToken,
            user_id: userId,
            reason: reason,
        },
        success(response) {
            if (response.success) {
                $('#transferModal').modal('hide');
                SHFLoans.showToast(response.message, 'success');
                location.reload();
            }
        },
        error(xhr) { SHFLoans.showError(xhr); },
    });
});
```

---

### Transfer History in Stage Card

Show transfer history within each stage card:

```blade
{{-- Transfer history --}}
@if($assignment->transfers->isNotEmpty())
    <div class="mt-2">
        <small class="text-muted fw-bold">Transfer History:</small>
        @foreach($assignment->transfers->sortByDesc('created_at')->take(3) as $transfer)
            <div class="shf-remark-item py-1">
                <small class="text-muted">
                    {{ $transfer->created_at->diffForHumans() }} —
                    {{ $transfer->fromUser->name }} → {{ $transfer->toUser->name }}
                    @if($transfer->transfer_type === 'auto')
                        <span class="badge bg-info badge-sm">Auto</span>
                    @endif
                    @if($transfer->reason)
                        <br><em>"{{ Str::limit($transfer->reason, 80) }}"</em>
                    @endif
                </small>
            </div>
        @endforeach
    </div>
@endif
```

---

## 3. Loan-Level Transfer History View

A full transfer timeline accessible from the loan show page:

### Route
```php
Route::get('/loans/{loan}/transfers', [LoanStageController::class, 'transferHistory'])
    ->name('loans.transfers');
```

### Controller
```php
public function transferHistory(LoanDetail $loan)
{
    $transfers = $loan->stageTransfers()
        ->with(['fromUser', 'toUser', 'stageAssignment.stage'])
        ->latest()
        ->get();

    return view('loans.transfers', compact('loan', 'transfers'));
}
```

### View: `loans/transfers.blade.php`

```
┌──────────────────────────────────────────────────────┐
│ Transfer History — Loan #SHF-202604-0001             │
│ ← Back to Loan                                       │
├──────────────────────────────────────────────────────┤
│ 06 Apr 2026, 14:30 — Rate & PF Request              │
│ Admin User → HDFC Employee (Amit)          [Manual]  │
│ "Needs bank approval for special rate"               │
├──────────────────────────────────────────────────────┤
│ 06 Apr 2026, 12:00 — Document Collection             │
│ System → Loan Advisor (Ramesh)             [Auto]    │
│ "Auto-assigned on stage advance"                     │
├──────────────────────────────────────────────────────┤
│ 05 Apr 2026, 16:45 — BSM/OSV Approval               │
│ System → HDFC Employee (Amit)              [Auto]    │
│ "Auto-assigned on stage advance"                     │
└──────────────────────────────────────────────────────┘
```

---

## 4. Stage Flow Examples with Auto-Assignment

### Example: Quotation → Loan → Disbursement

```
1. Quotation converted to Loan by Admin
   → Stages 1-2 auto-completed
   → Stage 3 (Document Collection) auto-assigned to Loan Advisor in Rajkot branch

2. Loan Advisor completes Document Collection
   → Stage 4 (Parallel Processing) starts
   → Sub-stages auto-assigned:
     - app_number → same Loan Advisor
     - bsm_osv → HDFC Bank Employee (matched by loan.bank_id = HDFC)
     - legal_verification → Legal Advisor in Rajkot branch
     - technical_valuation → Office Employee in Rajkot branch

3. All 4 parallel sub-stages complete
   → Stage 5 (Rate/PF) auto-assigned to Loan Advisor
   → Loan Advisor fills request, then TRANSFERS to Bank Employee
   → Bank Employee fills response, completes stage

4. Stage 6 (Sanction) auto-assigned to Bank Employee (same bank)
   → Continues through stages 7-10 with auto-assignment
```

### Example: Bank Employee Transfer for Rate/PF

```
Stage 5: Rate & PF Request
  1. Auto-assigned to Loan Advisor (Ramesh)           [auto]
  2. Ramesh fills interest rate request form
  3. Ramesh clicks "Transfer" → selects HDFC's Amit    [manual]
     Reason: "Requesting rate approval for 8.5%"
  4. Amit receives notification: "Stage transferred to you"
  5. Amit reviews, fills PF details
  6. Amit completes the stage
  → Stage 6 auto-assigned to Amit (bank employee, same bank)
```

---

## Summary: What This Adds to the Plan

### New Table
- `stage_transfers` — append-only transfer audit log

### New Model
- `StageTransfer` — with fromUser, toUser, stageAssignment, loan relationships

### Service Changes (LoanStageService)
- `autoAssignStage()` — auto-assign on stage advance
- `autoAssignParallelSubStages()` — auto-assign all 4 sub-stages
- `findBestAssignee()` — priority-based user matching (role + bank + branch)
- `transferStage()` — manual transfer with history + validation + notifications

### Controller Changes (LoanStageController)
- `transfer()` action — `POST /loans/{loan}/stages/{stageKey}/transfer`
- `transferHistory()` action — `GET /loans/{loan}/transfers`

### UI Changes
- Transfer button on in_progress stage cards
- Transfer modal with user dropdown + reason textarea
- Transfer history display in stage cards (last 3 transfers)
- Full transfer history page

### Activity Log Actions
- `auto_assign_stage` — system auto-assigned a user
- `transfer_stage` — manual transfer with from/to/reason

---

## Where This Fits in the Stage Plan

This work spans **Stage E** (core workflow) and **Stage H** (notifications). Recommended implementation:

- **Stage E**: Add `stage_transfers` migration, `StageTransfer` model, `autoAssignStage()`, `findBestAssignee()`, `transferStage()`, transfer route/controller action
- **Stage F**: Add `autoAssignParallelSubStages()`
- **Stage H**: Notification integration for transfers
- **Stage J**: Transfer history view, transfer button UI, JS

---

## Files Created/Modified

| Action | File |
|--------|------|
| Create | `database/migrations/xxxx_create_stage_transfers_table.php` |
| Create | `app/Models/StageTransfer.php` |
| Create | `resources/views/loans/transfers.blade.php` |
| Modify | `app/Services/LoanStageService.php` (autoAssign, findBestAssignee, transferStage) |
| Modify | `app/Http/Controllers/LoanStageController.php` (transfer, transferHistory actions) |
| Modify | `app/Models/StageAssignment.php` (transfers relationship) |
| Modify | `app/Models/LoanDetail.php` (stageTransfers relationship) |
| Modify | `resources/views/loans/partials/stage-card.blade.php` (transfer button + history) |
| Modify | `resources/views/loans/stages.blade.php` (transfer modal) |
| Modify | `public/js/shf-loans.js` (transfer JS) |
| Modify | `routes/web.php` (transfer + history routes) |
