<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisbursementDetail extends Model
{
    use HasAuditColumns;
    const TYPE_FUND_TRANSFER = 'fund_transfer';
    const TYPE_CHEQUE = 'cheque';
    const TYPE_DEMAND_DRAFT = 'demand_draft';

    const TYPES = [
        self::TYPE_FUND_TRANSFER => 'Fund Transfer (NEFT/RTGS)',
        self::TYPE_CHEQUE => 'Cheque',
        self::TYPE_DEMAND_DRAFT => 'Demand Draft',
    ];

    protected $fillable = [
        'loan_id', 'disbursement_type', 'disbursement_date', 'amount_disbursed',
        'bank_account_number', 'ifsc_code', 'cheque_number', 'cheque_date',
        'dd_number', 'dd_date', 'is_otc', 'otc_branch', 'otc_cleared',
        'otc_cleared_date', 'otc_cleared_by', 'reference_number', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_disbursed' => 'integer',
            'disbursement_date' => 'date',
            'cheque_date' => 'date',
            'dd_date' => 'date',
            'is_otc' => 'boolean',
            'otc_cleared' => 'boolean',
            'otc_cleared_date' => 'date',
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
        if ($this->disbursement_type === self::TYPE_FUND_TRANSFER || $this->disbursement_type === self::TYPE_DEMAND_DRAFT) {
            return true;
        }

        return ! $this->is_otc || $this->otc_cleared;
    }

    public function needsOtcClearance(): bool
    {
        return $this->is_otc && ! $this->otc_cleared;
    }
}
