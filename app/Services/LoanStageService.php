<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Bank;
use App\Models\LoanDetail;
use App\Models\LoanProgress;
use App\Models\ProductStage;
use App\Models\Stage;
use App\Models\StageAssignment;
use App\Models\StageQuery;
use App\Models\StageTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class LoanStageService
{
    /**
     * Get eligible roles for a stage from the database.
     */
    public static function getStageRoleEligibility(string $stageKey): array
    {
        $stage = Stage::where('stage_key', $stageKey)->first();

        return $stage?->default_role ?? [];
    }

    /**
     * Get all stage role eligibility as a map [stage_key => roles[]] from the database.
     */
    public static function getAllStageRoleEligibility(): array
    {
        return Stage::whereNotNull('default_role')
            ->pluck('default_role', 'stage_key')
            ->toArray();
    }

    // ── Query Methods (from Stage A) ──

    public function getOrderedStages(): Collection
    {
        return Stage::enabled()->mainStages()->get();
    }

    public function getStageByKey(string $key): ?Stage
    {
        return Stage::where('stage_key', $key)->first();
    }

    public function getSubStages(string $parentKey): Collection
    {
        return Stage::subStagesOf($parentKey)->get();
    }

    public function isParallelStage(string $key): bool
    {
        $stage = $this->getStageByKey($key);

        return $stage && ($stage->is_parallel || $stage->parent_stage_key !== null);
    }

    public function getMainStageKeys(): array
    {
        return Stage::enabled()->mainStages()->pluck('stage_key')->toArray();
    }

    // ── Initialization ──

    /**
     * Create stage assignments + loan progress for a new loan.
     * Uses only the base 14 stages (optional stages added via product_stages in Stage I).
     */
    public function initializeStages(LoanDetail $loan): void
    {
        $baseStageKeys = [
            'inquiry', 'document_selection', 'document_collection',
            'parallel_processing', 'app_number', 'bsm_osv', 'legal_verification', 'technical_valuation',
            'rate_pf', 'sanction', 'docket', 'kfs', 'esign', 'disbursement', 'otc_clearance',
        ];

        $stages = Stage::whereIn('stage_key', $baseStageKeys)->where('is_enabled', true)->get();
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

            if (! $isParallel && $stage->parent_stage_key === null) {
                $mainCount++;
            }
        }

        LoanProgress::create([
            'loan_id' => $loan->id,
            'total_stages' => $mainCount,
            'completed_stages' => 0,
            'overall_percentage' => 0,
        ]);
    }

    /**
     * Auto-complete specific stages (used when converting from quotation).
     */
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

    // ── Stage Transitions ──

    /**
     * Update a stage's status with validation and auto-advancement.
     */
    public function updateStageStatus(LoanDetail $loan, string $stageKey, string $newStatus, ?int $userId = null): StageAssignment
    {
        $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->firstOrFail();

        if (! $assignment->canTransitionTo($newStatus)) {
            throw new \RuntimeException(
                "Cannot transition stage '{$stageKey}' from '{$assignment->status}' to '{$newStatus}'"
            );
        }

        // Block completion if pending queries
        if ($newStatus === 'completed' && $assignment->hasPendingQueries()) {
            throw new \RuntimeException(
                "Cannot complete stage '{$stageKey}' — there are unresolved queries."
            );
        }

        $oldStatus = $assignment->status;
        $updateData = ['status' => $newStatus];

        if ($newStatus === 'in_progress' && ! $assignment->started_at) {
            $updateData['started_at'] = now();
        }

        if (in_array($newStatus, ['completed', 'rejected', 'skipped'])) {
            $updateData['completed_at'] = now();
            $updateData['completed_by'] = $userId ?? auth()->id();
        }

        $assignment->update($updateData);

        ActivityLog::log('update_stage_status', $assignment, [
            'loan_number' => $loan->loan_number,
            'stage_key' => $stageKey,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        if (in_array($newStatus, ['completed', 'skipped'])) {
            $this->handleStageCompletion($loan, $stageKey);
        }

        $this->recalculateProgress($loan);
        $loan->touch();

        return $assignment->fresh();
    }

    /**
     * Handle post-completion logic: auto-advance to next stage.
     */
    protected function handleStageCompletion(LoanDetail $loan, string $completedStageKey): void
    {
        $assignment = $loan->stageAssignments()->where('stage_key', $completedStageKey)->first();

        // Parallel sub-stage → check parallel completion
        if ($assignment && ($assignment->is_parallel_stage || $assignment->parent_stage_key !== null)) {
            $this->checkParallelCompletion($loan);

            return;
        }

        // Sequential → advance to next and auto-start (skip stages not in this loan)
        $nextKey = $this->getNextStage($completedStageKey);
        while ($nextKey && ! $loan->stageAssignments()->where('stage_key', $nextKey)->exists()) {
            $nextKey = $this->getNextStage($nextKey);
        }
        if ($nextKey) {
            $loan->update(['current_stage' => $nextKey]);
            $this->autoAssignStage($loan, $nextKey);

            // If entering parallel processing, auto-start parent + sub-stages
            if ($nextKey === 'parallel_processing') {
                $parentAssignment = $loan->stageAssignments()->where('stage_key', 'parallel_processing')->first();
                if ($parentAssignment) {
                    $parentAssignment->update(['status' => 'in_progress', 'started_at' => now()]);
                }
                $this->autoAssignParallelSubStages($loan);
            } else {
                // Auto-start the next stage
                $nextAssignment = $loan->stageAssignments()->where('stage_key', $nextKey)->first();
                if ($nextAssignment && $nextAssignment->status === 'pending') {
                    $nextAssignment->update(['status' => 'in_progress', 'started_at' => now()]);
                }
            }
        }

        // Fund transfer: skip OTC stage and complete loan
        if ($completedStageKey === 'disbursement') {
            $disbursement = $loan->disbursement;
            if ($disbursement && $disbursement->disbursement_type === 'fund_transfer') {
                // Skip OTC stage if it exists
                $otcAssignment = $loan->stageAssignments()->where('stage_key', 'otc_clearance')->first();
                if ($otcAssignment && $otcAssignment->status !== 'completed') {
                    $otcAssignment->update(['status' => 'skipped', 'completed_at' => now(), 'completed_by' => auth()->id()]);
                }
                $loan->update(['status' => LoanDetail::STATUS_COMPLETED]);
            }
            // Cheque: auto-advances to otc_clearance via normal flow
        }

        // OTC clearance completes the loan
        if ($completedStageKey === 'otc_clearance') {
            $loan->update(['status' => LoanDetail::STATUS_COMPLETED]);
            app(NotificationService::class)->notifyLoanCompleted($loan);
        }
    }

    /**
     * Get the next main stage key after the given one.
     */
    public function getNextStage(string $currentStageKey): ?string
    {
        $mainKeys = $this->getMainStageKeys();
        $currentIndex = array_search($currentStageKey, $mainKeys);

        if ($currentIndex === false || $currentIndex >= count($mainKeys) - 1) {
            return null;
        }

        return $mainKeys[$currentIndex + 1];
    }

    /**
     * Can the given stage be started?
     */
    public function canStartStage(LoanDetail $loan, string $stageKey): bool
    {
        $stage = $this->getStageByKey($stageKey);
        if (! $stage) {
            return false;
        }

        // Sub-stages can start when parent is current or in_progress
        if ($stage->parent_stage_key) {
            $parentAssignment = $loan->getStageAssignment($stage->parent_stage_key);

            return $parentAssignment && in_array($parentAssignment->status, ['in_progress']);
        }

        $mainKeys = $this->getMainStageKeys();
        $index = array_search($stageKey, $mainKeys);
        if ($index === 0) {
            return true;
        }

        // Rate & PF can start once ANY parallel sub-stage is completed
        if ($stageKey === 'rate_pf') {
            $anySubCompleted = $loan->stageAssignments()
                ->where('parent_stage_key', 'parallel_processing')
                ->where('status', 'completed')
                ->exists();

            return $anySubCompleted;
        }

        // Find the actual previous stage that exists in this loan's assignments
        $prevKey = null;
        for ($i = $index - 1; $i >= 0; $i--) {
            $candidate = $mainKeys[$i];
            if ($loan->getStageAssignment($candidate)) {
                $prevKey = $candidate;
                break;
            }
        }

        if (! $prevKey) {
            return false;
        }

        $prevAssignment = $loan->getStageAssignment($prevKey);
        $prevCompleted = $prevAssignment && in_array($prevAssignment->status, ['completed', 'skipped']);

        if (! $prevCompleted) {
            return false;
        }

        // If previous stage is rate_pf or parallel_processing, also require parallel_processing to be fully completed
        if (in_array($prevKey, ['rate_pf', 'parallel_processing'])) {
            $parallelAssignment = $loan->getStageAssignment('parallel_processing');

            return $parallelAssignment && in_array($parallelAssignment->status, ['completed', 'skipped']);
        }

        return true;
    }

    // ── Assignment ──

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

    public function skipStage(LoanDetail $loan, string $stageKey, ?int $userId = null): StageAssignment
    {
        return $this->updateStageStatus($loan, $stageKey, 'skipped', $userId);
    }

    /**
     * Auto-assign a stage to the best-fit user.
     */
    public function autoAssignStage(LoanDetail $loan, string $stageKey): ?StageAssignment
    {
        // Parallel processing parent is just a label — never assign
        if ($stageKey === 'parallel_processing') {
            return $loan->stageAssignments()->where('stage_key', $stageKey)->first();
        }

        $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->first();
        if (! $assignment || $assignment->assigned_to) {
            return $assignment;
        }

        $userId = $this->findBestAssignee($stageKey, $loan->branch_id, $loan->bank_id, $loan->product_id, $loan->created_by);
        if (! $userId) {
            return $assignment;
        }

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

        ActivityLog::log('auto_assign_stage', $assignment, [
            'loan_number' => $loan->loan_number,
            'stage_key' => $stageKey,
            'assigned_to_name' => User::find($userId)?->name,
        ]);

        return $assignment->fresh();
    }

    /**
     * Auto-assign all parallel sub-stages.
     */
    public function autoAssignParallelSubStages(LoanDetail $loan): void
    {
        $subStages = $loan->stageAssignments()
            ->where('parent_stage_key', 'parallel_processing')
            ->whereNull('assigned_to')
            ->get();

        foreach ($subStages as $assignment) {
            $userId = $this->findBestAssignee($assignment->stage_key, $loan->branch_id, $loan->bank_id, $loan->product_id, $loan->created_by);
            $updateData = ['status' => 'in_progress', 'started_at' => now()];
            if ($userId) {
                $updateData['assigned_to'] = $userId;
            }
            $assignment->update($updateData);
        }
    }

    /**
     * Find the best user for a stage.
     * Priority: product stage specific user → bank default employee → role+bank+branch match
     */
    public function findBestAssignee(string $stageKey, ?int $branchId, ?int $bankId, ?int $productId = null, ?int $loanCreatorId = null): ?int
    {
        // Priority -1: Product stage has a location/branch-specific or default user assigned
        if ($productId) {
            $stage = $this->getStageByKey($stageKey);
            if ($stage) {
                $productStage = ProductStage::where('product_id', $productId)
                    ->where('stage_id', $stage->id)
                    ->first();
                if ($productStage) {
                    // Resolve location from branch
                    $branch = $branchId ? \App\Models\Branch::with('location.parent')->find($branchId) : null;
                    $cityId = $branch?->location_id;
                    $stateId = $branch?->location?->parent_id;

                    // Check branch → city → state → product default
                    $assignedUserId = $productStage->getUserForLocation($branchId, $cityId, $stateId);
                    if ($assignedUserId) {
                        $user = User::where('id', $assignedUserId)->where('is_active', true)->first();
                        if ($user) {
                            return $user->id;
                        }
                    }
                }
            }
        }

        $eligibleRoles = self::getStageRoleEligibility($stageKey);
        if (empty($eligibleRoles)) {
            return null;
        }

        $baseQuery = User::where('is_active', true)->whereIn('task_role', $eligibleRoles);
        $hasBankRole = in_array('bank_employee', $eligibleRoles);

        if ($hasBankRole && $bankId) {
            // Priority 0: Bank's default employee (if set and active)
            $bank = Bank::find($bankId);
            if ($bank?->default_employee_id) {
                $defaultEmp = User::where('id', $bank->default_employee_id)->where('is_active', true)->first();
                if ($defaultEmp) {
                    return $defaultEmp->id;
                }
            }

            // Priority 1: Bank employee from pivot table + in branch
            if ($branchId) {
                $user = User::where('is_active', true)
                    ->whereHas('employerBanks', fn ($q) => $q->where('banks.id', $bankId))
                    ->whereHas('branches', fn ($q) => $q->where('branches.id', $branchId))->first();
                if ($user) {
                    return $user->id;
                }
            }

            // Priority 2: Bank employee from pivot table (any branch)
            $user = User::where('is_active', true)
                ->whereHas('employerBanks', fn ($q) => $q->where('banks.id', $bankId))->first();
            if ($user) {
                return $user->id;
            }

            // Other eligible roles in branch
            $otherRoles = array_diff($eligibleRoles, ['bank_employee']);
            if ($branchId && ! empty($otherRoles)) {
                $user = (clone $baseQuery)->whereIn('task_role', $otherRoles)
                    ->whereHas('branches', fn ($q) => $q->where('branches.id', $branchId))->first();
                if ($user) {
                    return $user->id;
                }
            }
        }

        // Priority 0b: Default office employee for the branch (from user_branches pivot)
        if (in_array('office_employee', $eligibleRoles) && $branchId) {
            $defaultOE = User::where('is_active', true)
                ->where('task_role', 'office_employee')
                ->whereHas('branches', fn ($q) => $q->where('branches.id', $branchId)->where('user_branches.is_default_office_employee', true))
                ->first();
            if ($defaultOE) {
                return $defaultOE->id;
            }
        }

        // Priority for loan_advisor/branch_manager: default to loan creator
        if ($loanCreatorId && (in_array('loan_advisor', $eligibleRoles) || in_array('branch_manager', $eligibleRoles))) {
            $creator = User::where('id', $loanCreatorId)->where('is_active', true)->first();
            if ($creator && in_array($creator->task_role, $eligibleRoles)) {
                return $creator->id;
            }
        }

        // Branch-based match
        if ($branchId) {
            $user = (clone $baseQuery)->whereHas('branches', fn ($q) => $q->where('branches.id', $branchId))->first();
            if ($user) {
                return $user->id;
            }
        }

        // Any matching role
        return $baseQuery->first()?->id;
    }

    // ── Transfer ──

    /**
     * Transfer a stage to another user with history tracking.
     */
    public function transferStage(LoanDetail $loan, string $stageKey, int $toUserId, ?string $reason = null): StageAssignment
    {
        $assignment = $loan->stageAssignments()->where('stage_key', $stageKey)->firstOrFail();
        $fromUserId = auth()->id();

        $assignment->update(['assigned_to' => $toUserId]);

        StageTransfer::create([
            'stage_assignment_id' => $assignment->id,
            'loan_id' => $loan->id,
            'stage_key' => $stageKey,
            'transferred_from' => $fromUserId,
            'transferred_to' => $toUserId,
            'reason' => $reason,
            'transfer_type' => 'manual',
        ]);

        // Reassign open queries on this stage to the new user
        StageQuery::where('loan_id', $loan->id)
            ->where('stage_key', $stageKey)
            ->whereIn('status', ['pending', 'responded'])
            ->update(['stage_assignment_id' => $assignment->id]);

        ActivityLog::log('transfer_stage', $assignment, [
            'loan_number' => $loan->loan_number,
            'stage_key' => $stageKey,
            'from_user' => User::find($fromUserId)?->name,
            'to_user' => User::find($toUserId)?->name,
            'reason' => $reason,
        ]);

        $loan->touch();

        return $assignment->fresh();
    }

    // ── Rejection ──

    /**
     * Reject the entire loan from a stage.
     */
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

        return $loan->fresh();
    }

    // ── Parallel Processing ──

    /**
     * Check if all parallel sub-stages are complete.
     */
    public function checkParallelCompletion(LoanDetail $loan): bool
    {
        $subStages = $loan->stageAssignments()->subStagesOf('parallel_processing')->get();

        $allDone = $subStages->every(fn ($sa) => in_array($sa->status, ['completed', 'skipped']));

        if ($allDone) {
            $parent = $loan->stageAssignments()->where('stage_key', 'parallel_processing')->first();
            if ($parent && $parent->status !== 'completed') {
                $parent->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'completed_by' => auth()->id(),
                ]);

                $nextKey = $this->getNextStage('parallel_processing');
                while ($nextKey && ! $loan->stageAssignments()->where('stage_key', $nextKey)->exists()) {
                    $nextKey = $this->getNextStage($nextKey);
                }
                if ($nextKey) {
                    $loan->update(['current_stage' => $nextKey]);
                    $this->autoAssignStage($loan, $nextKey);

                    // Auto-start the next stage
                    $nextAssignment = $loan->stageAssignments()->where('stage_key', $nextKey)->first();
                    if ($nextAssignment && $nextAssignment->status === 'pending') {
                        $nextAssignment->update(['status' => 'in_progress', 'started_at' => now()]);
                    }
                }
            }

            $this->recalculateProgress($loan);

            return true;
        }

        return false;
    }

    public function getParallelSubStages(LoanDetail $loan): Collection
    {
        return $loan->stageAssignments()
            ->subStagesOf('parallel_processing')
            ->with(['stage', 'assignee'])
            ->get();
    }

    // ── Progress ──

    /**
     * Recalculate loan progress percentages.
     */
    public function recalculateProgress(LoanDetail $loan): LoanProgress
    {
        $progress = $loan->progress ?? LoanProgress::create([
            'loan_id' => $loan->id,
            'total_stages' => 10,
        ]);

        $mainAssignments = $loan->stageAssignments()->mainStages()->get();
        $total = $mainAssignments->count();
        $completed = $mainAssignments->whereIn('status', ['completed', 'skipped'])->count();
        $percentage = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        $snapshot = $loan->stageAssignments()->get()->map(fn ($sa) => [
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

    /**
     * Get all stage assignments for a loan, ordered by sequence.
     */
    public function getLoanStageStatus(LoanDetail $loan): Collection
    {
        return $loan->stageAssignments()
            ->with(['stage', 'assignee'])
            ->get()
            ->sortBy(fn ($sa) => $sa->stage?->sequence_order ?? 999);
    }
}
