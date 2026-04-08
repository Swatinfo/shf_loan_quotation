<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductStage extends Model
{
    use HasAuditColumns;
    protected $fillable = [
        'product_id', 'stage_id', 'is_enabled', 'default_assignee_role',
        'default_user_id', 'auto_skip', 'allow_skip', 'sort_order', 'sub_actions_override',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'auto_skip' => 'boolean',
            'allow_skip' => 'boolean',
            'sort_order' => 'integer',
            'sub_actions_override' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function defaultUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_user_id');
    }

    public function branchUsers(): HasMany
    {
        return $this->hasMany(ProductStageUser::class);
    }

    /**
     * Get the assigned user for a specific branch.
     */
    public function getUserForBranch(?int $branchId): ?int
    {
        if (! $branchId) {
            return $this->default_user_id;
        }

        $branchAssignment = $this->branchUsers()->where('branch_id', $branchId)->first();

        return $branchAssignment?->user_id ?? $this->default_user_id;
    }

    /**
     * Get the best assigned user considering location hierarchy.
     * Priority: branch → city → state → product default
     */
    public function getUserForLocation(?int $branchId, ?int $cityId, ?int $stateId): ?int
    {
        // 1. Branch-specific
        if ($branchId) {
            $match = $this->branchUsers()->where('branch_id', $branchId)->where('is_default', true)->first();
            if ($match) {
                return $match->user_id;
            }
        }

        // 2. City-level
        if ($cityId) {
            $match = $this->branchUsers()->whereNull('branch_id')->where('location_id', $cityId)->where('is_default', true)->first();
            if ($match) {
                return $match->user_id;
            }
        }

        // 3. State-level
        if ($stateId) {
            $match = $this->branchUsers()->whereNull('branch_id')->where('location_id', $stateId)->where('is_default', true)->first();
            if ($match) {
                return $match->user_id;
            }
        }

        // 4. Product default
        return $this->default_user_id;
    }
}
