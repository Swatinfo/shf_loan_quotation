<?php

namespace App\Models;

use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Normalized mirror of one tranche from `disbursement_details.entries` (json).
 * The JSON is the form's source of truth; these rows exist for querying
 * (future payout calculation) and audit (updated_by / deleted_by / is_active).
 */
class DisbursementEntry extends Model
{
    use HasAuditColumns, SoftDeletes;

    const METHOD_FUND_TRANSFER = 'fund_transfer';

    const METHOD_CHEQUE = 'cheque';

    const OTC_PENDING = 'pending';

    const OTC_CLEARED = 'cleared';

    const OTC_SKIPPED = 'skipped';

    /** OTC states that count as settled (no further handover needed). */
    const OTC_SETTLED = [self::OTC_CLEARED, self::OTC_SKIPPED];

    protected $fillable = [
        'loan_id', 'disbursement_detail_id', 'disbursement_date', 'method',
        'product_id', 'product_name', 'loan_account_number', 'amount',
        'cheque_name', 'cheque_number', 'cheque_date', 'transfer_date', 'is_active',
        'otc_status', 'otc_handover_date', 'otc_cleared_by', 'otc_cleared_at', 'otc_remarks',
        'loan_payout_id', 'payout_finalized_at',
        'payout_run_id', 'paid_amount_counted',
    ];

    protected function casts(): array
    {
        return [
            'disbursement_date' => 'date',
            'transfer_date' => 'date',
            'amount' => 'integer',
            'is_active' => 'boolean',
            'otc_handover_date' => 'date',
            'otc_cleared_at' => 'datetime',
            'payout_finalized_at' => 'datetime',
            'payout_run_id' => 'integer',
            'paid_amount_counted' => 'integer',
        ];
    }

    /** Whether this tranche has already been counted in a finalized payout. */
    public function isPayoutFinalized(): bool
    {
        return $this->loan_payout_id !== null;
    }

    public function loanPayout(): BelongsTo
    {
        return $this->belongsTo(LoanPayout::class, 'loan_payout_id');
    }

    /**
     * Whether this tranche's OTC handover is settled (cleared or skipped).
     */
    public function isOtcSettled(): bool
    {
        // Only cheques need a physical over-the-counter handover; a fund transfer
        // (NEFT/RTGS) is always settled and never blocks otc_clearance / completion.
        if ($this->method !== self::METHOD_CHEQUE) {
            return true;
        }

        return in_array($this->otc_status, self::OTC_SETTLED, true);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }

    public function disbursement(): BelongsTo
    {
        return $this->belongsTo(DisbursementDetail::class, 'disbursement_detail_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function otcClearedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'otc_cleared_by');
    }
}
