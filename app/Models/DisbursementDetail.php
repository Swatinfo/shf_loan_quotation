<?php

namespace App\Models;

use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisbursementDetail extends Model
{
    use HasAuditColumns;

    const TYPE_FUND_TRANSFER = 'fund_transfer';

    const TYPE_CHEQUE = 'cheque';

    const TYPES = [
        self::TYPE_FUND_TRANSFER => 'Fund Transfer (NEFT/RTGS)',
        self::TYPE_CHEQUE => 'Cheque',
    ];

    protected $fillable = [
        'loan_id', 'disbursement_type', 'disbursement_date', 'amount_disbursed',
        'bank_account_number', 'cheques', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_disbursed' => 'integer',
            'disbursement_date' => 'date',
            'cheques' => 'array',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }

    public function otcClearedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'otc_cleared_by');
    }

    public function isComplete(): bool
    {
        return true;
    }
}
