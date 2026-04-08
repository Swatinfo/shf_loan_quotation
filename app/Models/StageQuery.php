<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StageQuery extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_RESPONDED = 'responded';
    const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'stage_assignment_id', 'loan_id', 'stage_key',
        'query_text', 'raised_by', 'status', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function stageAssignment(): BelongsTo
    {
        return $this->belongsTo(StageAssignment::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }

    public function raisedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QueryResponse::class, 'stage_query_id');
    }

    // Scopes

    public function scopePending($query): void
    {
        $query->where('status', 'pending');
    }

    public function scopeActive($query): void
    {
        $query->whereIn('status', ['pending', 'responded']);
    }

    public function scopeResolved($query): void
    {
        $query->where('status', 'resolved');
    }
}
