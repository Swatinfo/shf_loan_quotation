<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationEmi extends Model
{
    protected $table = 'quotation_emi';

    protected $fillable = [
        'quotation_bank_id',
        'tenure_years',
        'monthly_emi',
        'total_interest',
        'total_payment',
    ];

    protected $casts = [
        'tenure_years' => 'integer',
        'monthly_emi' => 'integer',
        'total_interest' => 'integer',
        'total_payment' => 'integer',
    ];

    public function quotationBank(): BelongsTo
    {
        return $this->belongsTo(QuotationBank::class);
    }
}
