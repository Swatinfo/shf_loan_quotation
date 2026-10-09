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

    protected $fillable = [
        'product_id', 'version_id', 'low_amount', 'high_amount', 'payout_type', 'payout_value',
        'connector_payout_type', 'connector_payout_value',
    ];

    protected function casts(): array
    {
        return [
            'low_amount' => 'integer',
            'high_amount' => 'integer',
            'payout_value' => 'float',
            'connector_payout_value' => 'float',
        ];
    }

    /**
     * Payout type + value for the given payout user's role: connectors earn the
     * connector rate, everyone else the standard rate.
     *
     * @return array{type: string, value: float}
     */
    public function rateFor(bool $isConnector): array
    {
        return $isConnector
            ? ['type' => $this->connector_payout_type, 'value' => (float) $this->connector_payout_value]
            : ['type' => $this->payout_type, 'value' => (float) $this->payout_value];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ProductPayoutVersion::class, 'version_id');
    }
}
