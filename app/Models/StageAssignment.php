<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StageAssignment extends Model
{
    use HasAuditColumns;
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REJECTED = 'rejected';
    const STATUS_SKIPPED = 'skipped';

    const STATUSES = ['pending', 'in_progress', 'completed', 'rejected', 'skipped'];

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

    protected $fillable = [
        'loan_id', 'stage_key', 'assigned_to', 'status', 'priority',
        'started_at', 'completed_at', 'completed_by',
        'is_parallel_stage', 'parent_stage_key', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_parallel_stage' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // Relationships

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'stage_key', 'stage_key');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(StageTransfer::class, 'stage_assignment_id');
    }

    public function queries(): HasMany
    {
        return $this->hasMany(StageQuery::class, 'stage_assignment_id');
    }

    public function activeQueries(): HasMany
    {
        return $this->hasMany(StageQuery::class, 'stage_assignment_id')
            ->whereIn('status', ['pending', 'responded']);
    }

    // Helpers

    public function isActionable(): bool
    {
        return in_array($this->status, ['pending', 'in_progress']);
    }

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

    public function hasPendingQueries(): bool
    {
        return $this->queries()->whereIn('status', ['pending', 'responded'])->exists();
    }

    public function getNotesData(): array
    {
        if (! $this->notes) {
            return [];
        }

        $decoded = json_decode($this->notes, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function mergeNotesData(array $data): void
    {
        $existing = $this->getNotesData();
        $this->update(['notes' => json_encode(array_merge($existing, $data))]);
    }

    // Scopes

    public function scopePending($query): void
    {
        $query->where('status', 'pending');
    }

    public function scopeInProgress($query): void
    {
        $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query): void
    {
        $query->where('status', 'completed');
    }

    public function scopeForUser($query, int $userId): void
    {
        $query->where('assigned_to', $userId);
    }

    public function scopeMainStages($query): void
    {
        $query->where('is_parallel_stage', false)->whereNull('parent_stage_key');
    }

    public function scopeSubStagesOf($query, string $parentKey): void
    {
        $query->where('parent_stage_key', $parentKey);
    }
}
