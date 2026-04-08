<?php

namespace App\Models;

use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
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
        'residential_bunglow' => 'Residential Bunglow',
        'residential_flat' => 'Residential Flat',
        'commercial' => 'Commercial',
        'industrial' => 'Industrial',
        'land' => 'Land',
        'mixed' => 'Mix Used',
    ];

    protected $fillable = [
        'loan_id', 'valuation_type', 'property_address', 'property_type',
        'latitude', 'longitude',
        'land_area', 'land_rate', 'land_valuation',
        'construction_area', 'construction_rate', 'construction_valuation',
        'final_valuation', 'market_value', 'government_value',
        'valuation_date', 'valuator_name', 'valuator_report_number', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'land_rate' => 'decimal:2',
            'land_valuation' => 'integer',
            'construction_rate' => 'decimal:2',
            'construction_valuation' => 'integer',
            'final_valuation' => 'integer',
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
