<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationBank extends Model
{
    protected $fillable = [
        'quotation_id',
        'bank_name',
        'roi_min',
        'roi_max',
        'pf_charge',
        'admin_charge',
        'stamp_notary',
        'registration_fee',
        'advocate_fees',
        'iom_charge',
        'tc_report',
        'extra1_name',
        'extra1_amount',
        'extra2_name',
        'extra2_amount',
        'total_charges',
    ];

    protected $casts = [
        'roi_min' => 'decimal:2',
        'roi_max' => 'decimal:2',
        'pf_charge' => 'decimal:2',
        'admin_charge' => 'integer',
        'stamp_notary' => 'integer',
        'registration_fee' => 'integer',
        'advocate_fees' => 'integer',
        'iom_charge' => 'integer',
        'tc_report' => 'integer',
        'extra1_amount' => 'integer',
        'extra2_amount' => 'integer',
        'total_charges' => 'integer',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function emiEntries(): HasMany
    {
        return $this->hasMany(QuotationEmi::class);
    }
}
