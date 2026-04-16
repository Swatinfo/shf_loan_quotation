<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Remark extends Model
{
    protected $fillable = ['loan_id', 'stage_key', 'user_id', 'remark'];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeForStage($query, string $key): void
    {
        $query->where('stage_key', $key);
    }

    public function scopeGeneral($query): void
    {
        $query->whereNull('stage_key');
    }
}
