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

    protected $fillable = [
        'loan_id', 'disbursement_detail_id', 'disbursement_date', 'method',
        'product_id', 'product_name', 'loan_account_number', 'amount',
        'cheque_name', 'cheque_number', 'cheque_date', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'disbursement_date' => 'date',
            'amount' => 'integer',
            'is_active' => 'boolean',
        ];
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
}
