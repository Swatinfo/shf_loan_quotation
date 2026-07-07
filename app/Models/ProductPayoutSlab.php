<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPayoutSlab extends Model
{
    const TYPE_AMOUNT = 'amount';

    const TYPE_PERCENT = 'percent';

    const TYPES = [
        self::TYPE_AMOUNT => 'Fixed ₹',
        self::TYPE_PERCENT => '%',
    ];

    protected $fillable = ['product_id', 'low_amount', 'high_amount', 'payout_type', 'payout_value'];

    protected function casts(): array
    {
        return [
            'low_amount' => 'integer',
            'high_amount' => 'integer',
            'payout_value' => 'float',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
