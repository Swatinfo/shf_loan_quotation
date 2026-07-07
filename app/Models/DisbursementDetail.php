<?php

namespace App\Models;

use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'bank_account_number', 'cheques', 'entries', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_disbursed' => 'integer',
            'disbursement_date' => 'date',
            'cheques' => 'array',
            'entries' => 'array',
        ];
    }

    /**
     * Tranche entries with legacy fallback (rows saved before the multi-entry migration).
     *
     * @return array<int, array<string, mixed>>
     */
    public function entryList(): array
    {
        if (! empty($this->entries)) {
            return $this->entries;
        }

        if ($this->disbursement_type === self::TYPE_CHEQUE && ! empty($this->cheques)) {
            return array_map(fn (array $chq): array => [
                'disbursement_date' => $this->disbursement_date?->toDateString(),
                'method' => self::TYPE_CHEQUE,
                'product_id' => null,
                'product_name' => null,
                'loan_account_number' => $this->bank_account_number,
                'amount' => (int) ($chq['cheque_amount'] ?? 0),
                'cheque_name' => $chq['cheque_name'] ?? null,
                'cheque_number' => $chq['cheque_number'] ?? null,
                'cheque_date' => $chq['cheque_date'] ?? null,
            ], $this->cheques);
        }

        if ($this->amount_disbursed) {
            return [[
                'disbursement_date' => $this->disbursement_date?->toDateString(),
                'method' => $this->disbursement_type ?: self::TYPE_FUND_TRANSFER,
                'product_id' => null,
                'product_name' => null,
                'loan_account_number' => $this->bank_account_number,
                'amount' => (int) $this->amount_disbursed,
            ]];
        }

        return [];
    }

    public function entryTotal(): int
    {
        return (int) array_sum(array_column($this->entryList(), 'amount'));
    }

    public function hasChequeEntries(): bool
    {
        return collect($this->entryList())->contains(fn (array $entry) => ($entry['method'] ?? null) === self::TYPE_CHEQUE);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }

    /**
     * Normalized mirror rows of the `entries` json (named to avoid colliding
     * with the `entries` attribute cast).
     */
    public function entryRows(): HasMany
    {
        return $this->hasMany(DisbursementEntry::class, 'disbursement_detail_id');
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
