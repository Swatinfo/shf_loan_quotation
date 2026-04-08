<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValuationDetail extends Model
{
    use HasAuditColumns;
    const TYPE_PROPERTY = 'property';
    const TYPE_VEHICLE = 'vehicle';
    const TYPE_BUSINESS = 'business';

    const TYPES = [
        self::TYPE_PROPERTY => 'Property',
        self::TYPE_VEHICLE => 'Vehicle',
        self::TYPE_BUSINESS => 'Business',
    ];

    const PROPERTY_TYPES = [
        'residential' => 'Residential',
        'commercial' => 'Commercial',
        'industrial' => 'Industrial',
        'land' => 'Land',
        'mixed' => 'Mixed Use',
    ];

    protected $fillable = [
        'loan_id', 'valuation_type', 'property_address', 'property_type', 'property_area',
        'market_value', 'government_value', 'valuation_date',
        'valuator_name', 'valuator_report_number', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'market_value' => 'integer',
            'government_value' => 'integer',
            'valuation_date' => 'date',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }
}
