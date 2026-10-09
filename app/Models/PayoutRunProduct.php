<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The tier context for one product within a payout run (snapshot of the slab +
 * rate + aggregate used, so historical reports stay stable after config changes).
 */
class PayoutRunProduct extends Model
{
    protected $fillable = [
        'run_id', 'product_id', 'product_name', 'bank_id', 'is_pf_based',
        'aggregate_base', 'product_payout_version_id', 'slab_id', 'slab_low', 'slab_high',
        'tier_rate_type', 'tier_rate', 'connector_tier_rate', 'max_payout',
    ];

    protected function casts(): array
    {
        return [
            'is_pf_based' => 'boolean',
            'aggregate_base' => 'integer',
            'slab_low' => 'integer',
            'slab_high' => 'integer',
            'tier_rate' => 'float',
            'connector_tier_rate' => 'float',
            'max_payout' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayoutRun::class, 'run_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PayoutRunLine::class, 'payout_run_product_id');
    }
}
