<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One finalized payout event for a loan. Records the disbursed increment it was
 * computed on, the slab + rate used, the computed amount, the bank cycle window,
 * and who/when. The disbursement_entries it covered point back via loan_payout_id.
 */
class LoanPayout extends Model
{
    const SOURCE_MANUAL = 'manual';

    const SOURCE_RECONCILIATION = 'reconciliation';

    protected $fillable = [
        'loan_id', 'payout_user_id', 'role_context', 'basis_amount', 'slab_id',
        'payout_type', 'payout_value', 'payout_amount',
        'pf_base_amount', 'gst_amount', 'insurance_base_amount', 'insurance_payout_amount',
        'tds_amount', 'net_payout_amount', 'pf_gst_rate', 'tds_rate', 'insurance_rate',
        'cycle_start', 'cycle_end', 'finalized_at', 'finalized_by', 'source', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'basis_amount' => 'integer',
            'payout_value' => 'decimal:2',
            'payout_amount' => 'integer',
            'pf_base_amount' => 'integer',
            'gst_amount' => 'integer',
            'insurance_base_amount' => 'integer',
            'insurance_payout_amount' => 'integer',
            'tds_amount' => 'integer',
            'net_payout_amount' => 'integer',
            'pf_gst_rate' => 'decimal:4',
            'tds_rate' => 'decimal:4',
            'insurance_rate' => 'decimal:4',
            'cycle_start' => 'date',
            'cycle_end' => 'date',
            'finalized_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }

    public function payoutUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payout_user_id');
    }

    public function finalizedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function slab(): BelongsTo
    {
        return $this->belongsTo(ProductPayoutSlab::class, 'slab_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DisbursementEntry::class, 'loan_payout_id');
    }
}
