<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAuditColumns;

class Branch extends Model
{
    use HasAuditColumns, SoftDeletes;

    protected $fillable = ['name', 'code', 'address', 'city', 'phone', 'is_active', 'manager_id', 'location_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branches');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    // Scopes

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
