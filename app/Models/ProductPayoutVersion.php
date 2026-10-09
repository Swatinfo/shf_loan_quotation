<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One effective-dated version of a product's payout config (PF flag, cap, cycle
 * days) and its slab set. The version in force for a date is the one with the
 * greatest effective_from on/before that date.
 */
class ProductPayoutVersion extends Model
{
    protected $fillable = [
        'product_id', 'effective_from', 'is_pf_based', 'max_payout_amount',
        'payout_cycle_start_day', 'payout_cycle_end_day', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'is_pf_based' => 'boolean',
            'max_payout_amount' => 'decimal:2',
            'payout_cycle_start_day' => 'integer',
            'payout_cycle_end_day' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function payoutSlabs(): HasMany
    {
        return $this->hasMany(ProductPayoutSlab::class, 'version_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
