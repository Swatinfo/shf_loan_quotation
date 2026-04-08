<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProgress extends Model
{
    protected $table = 'loan_progress';

    protected $fillable = [
        'loan_id', 'total_stages', 'completed_stages',
        'overall_percentage', 'estimated_completion', 'workflow_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'total_stages' => 'integer',
            'completed_stages' => 'integer',
            'overall_percentage' => 'decimal:2',
            'estimated_completion' => 'date',
            'workflow_snapshot' => 'array',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }
}
