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

    const INTENT_OPEN = 'open';

    const INTENT_FULL = 'full';

    protected $fillable = [
        'loan_id', 'disbursement_type', 'disbursement_date', 'amount_disbursed',
        'pf_amount', 'admin_charges', 'insurance_amount',
        'bank_account_number', 'cheques', 'entries', 'notes', 'completion_intent',
        'pf_payout_run_id', 'insurance_payout_run_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_disbursed' => 'integer',
            'pf_amount' => 'integer',
            'admin_charges' => 'integer',
            'insurance_amount' => 'integer',
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

    /** One-time processing fee (header-level, GST-inclusive). */
    public function pfTotal(): int
    {
        return (int) $this->pf_amount;
    }

    /** One-time admin charges (header-level, GST-inclusive). */
    public function adminTotal(): int
    {
        return (int) $this->admin_charges;
    }

    /** One-time insurance (header-level; no GST; excluded from gross). */
    public function insuranceTotal(): int
    {
        return (int) $this->insurance_amount;
    }

    /**
     * Overall ("gross") disbursed amount = net transfers + PF + admin charges
     * (both one-time, GST-inclusive). Insurance is excluded. This is the figure
     * the fully-disbursed check uses.
     */
    public function grossTotal(): int
    {
        return $this->entryTotal() + $this->pfTotal() + $this->adminTotal();
    }

    /**
     * One-time charge add-on (PF + admin, insurance excluded) — the amount that
     * rides the loan's first tranche in gross/period calculations.
     */
    public function chargeAddon(): int
    {
        return $this->pfTotal() + $this->adminTotal();
    }

    /**
     * Whether the one-time charges (PF / admin / insurance) are frozen. They
     * lock the moment any disbursement entry's OTC is settled (a cleared cheque,
     * or any fund transfer — auto-skipped at save), so they can only ever be set
     * on the first disbursement. The super-admin correction tool bypasses this.
     */
    public function chargesLocked(): bool
    {
        return $this->entryRows()
            ->whereIn('otc_status', DisbursementEntry::OTC_SETTLED)
            ->exists();
    }

    /** GST-exclusive base of a GST-inclusive amount at rate $r (e.g. 0.18). */
    public static function exGst(int $inclusive, float $r): int
    {
        return $r > 0 ? (int) round($inclusive / (1 + $r)) : $inclusive;
    }

    /** GST portion included in a GST-inclusive amount at rate $r. */
    public static function gstPortion(int $inclusive, float $r): int
    {
        return $inclusive - self::exGst($inclusive, $r);
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
