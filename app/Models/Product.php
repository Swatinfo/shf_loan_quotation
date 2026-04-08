<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAuditColumns;

class Product extends Model
{
    use HasAuditColumns, SoftDeletes;

    protected $fillable = ['bank_id', 'name', 'code', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
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
