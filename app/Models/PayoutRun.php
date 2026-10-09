<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayoutRun extends Model
{
    const STATUS_DRAFT = 'draft';

    const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'from_date', 'to_date', 'status', 'notes',
        'insurance_rate', 'tds_rate', 'gst_rate',
        'total_gross', 'total_tds', 'total_net',
        'finalized_by', 'finalized_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'insurance_rate' => 'float',
            'tds_rate' => 'float',
            'gst_rate' => 'float',
            'total_gross' => 'integer',
            'total_tds' => 'integer',
            'total_net' => 'integer',
            'finalized_at' => 'datetime',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(PayoutRunProduct::class, 'run_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayoutRunLine::class, 'run_id');
    }

    public function userTotals(): HasMany
    {
        return $this->hasMany(PayoutRunUser::class, 'run_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
