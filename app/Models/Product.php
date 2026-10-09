<?php

namespace App\Models;

use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasAuditColumns, SoftDeletes;

    protected $fillable = [
        'bank_id', 'name', 'code', 'is_active', 'is_pf_based', 'max_payout_amount',
        'payout_cycle_start_day', 'payout_cycle_end_day', 'current_payout_version_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_pf_based' => 'boolean',
            'max_payout_amount' => 'decimal:2',
            'payout_cycle_start_day' => 'integer',
            'payout_cycle_end_day' => 'integer',
        ];
    }

    // Relationships

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * The CURRENT slab set — the slabs of the version in force today, resolved
     * through `current_payout_version_id`. Historical versions keep their own
     * slabs (via version_id); temporal lookups go through PayoutConfigService.
     */
    public function payoutSlabs(): HasMany
    {
        return $this->hasMany(ProductPayoutSlab::class, 'version_id', 'current_payout_version_id')->orderBy('low_amount');
    }

    public function payoutVersions(): HasMany
    {
        return $this->hasMany(ProductPayoutVersion::class)->orderByDesc('effective_from');
    }

    /** The payout version in force today (latest effective_from on/before now). */
    public function currentPayoutVersion(): HasOne
    {
        return $this->hasOne(ProductPayoutVersion::class)
            ->whereDate('effective_from', '<=', now())
            ->orderByDesc('effective_from');
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
