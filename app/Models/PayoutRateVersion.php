<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One effective-dated version of a global payout rate (admin_gst, pf_gst,
 * user_tds, user_insurance). The version in force for a date is the one with the
 * greatest effective_from on/before that date.
 */
class PayoutRateVersion extends Model
{
    const KEYS = ['admin_gst', 'pf_gst', 'user_tds', 'user_insurance'];

    protected $fillable = ['rate_key', 'value', 'calc', 'effective_from', 'created_by'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'calc' => 'decimal:4',
            'effective_from' => 'date',
        ];
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
