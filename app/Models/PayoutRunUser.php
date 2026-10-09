<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The payable per user within a payout run (per-user roll-up with TDS). */
class PayoutRunUser extends Model
{
    protected $fillable = [
        'run_id', 'payout_user_id', 'role_context',
        'total_commission', 'total_pf_payout', 'total_insurance_payout',
        'total_payout', 'tds_rate', 'tds_amount', 'net_payout',
    ];

    protected function casts(): array
    {
        return [
            'total_commission' => 'integer',
            'total_pf_payout' => 'integer',
            'total_insurance_payout' => 'integer',
            'total_payout' => 'integer',
            'tds_rate' => 'float',
            'tds_amount' => 'integer',
            'net_payout' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayoutRun::class, 'run_id');
    }

    public function payoutUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payout_user_id');
    }
}
