<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One (user × product) breakdown line within a payout run. */
class PayoutRunLine extends Model
{
    protected $fillable = [
        'run_id', 'payout_run_product_id', 'payout_user_id', 'role_context',
        'base_amount', 'rate_applied', 'commission',
        'pf_base', 'pf_payout', 'insurance_base', 'insurance_rate', 'insurance_payout', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'integer',
            'rate_applied' => 'float',
            'commission' => 'integer',
            'pf_base' => 'integer',
            'pf_payout' => 'integer',
            'insurance_base' => 'integer',
            'insurance_rate' => 'float',
            'insurance_payout' => 'integer',
            'line_total' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayoutRun::class, 'run_id');
    }

    public function runProduct(): BelongsTo
    {
        return $this->belongsTo(PayoutRunProduct::class, 'payout_run_product_id');
    }

    public function payoutUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payout_user_id');
    }
}
