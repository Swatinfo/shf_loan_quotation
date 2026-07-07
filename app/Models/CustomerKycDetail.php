<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Per-loan KYC snapshot captured at loan creation/edit. The linked Customer is
 * the identity anchor (by PAN); this row holds the details as entered for one
 * loan and is what the loan displays.
 */
class CustomerKycDetail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'loan_id',
        'quotation_id',
        'customer_name',
        'mobile',
        'email',
        'date_of_birth',
        'pan_number',
        'source',
        'captured_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
