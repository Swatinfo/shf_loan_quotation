<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageTransfer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stage_assignment_id', 'loan_id', 'stage_key',
        'transferred_from', 'transferred_to', 'reason', 'transfer_type',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
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

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_from');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_to');
    }
}
