<?php

namespace App\Models;

use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasAuditColumns, SoftDeletes;

    protected $fillable = ['bank_id', 'name', 'code', 'is_active', 'is_pf_based', 'max_payout_amount'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_pf_based' => 'boolean',
            'max_payout_amount' => 'decimal:2',
        ];
    }

    // Relationships

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function payoutSlabs(): HasMany
    {
        return $this->hasMany(ProductPayoutSlab::class)->orderBy('low_amount');
    }

    public function stages(): BelongsToMany
    {
        return $this->belongsToMany(Stage::class, 'product_stages')
            ->withPivot('is_enabled', 'default_assignee_role', 'auto_skip', 'sort_order');
    }

    public function productStages(): HasMany
    {
        return $this->hasMany(ProductStage::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_product')->withTimestamps();
    }

    // Scopes

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
