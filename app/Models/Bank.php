<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use HasAuditColumns, SoftDeletes;

    protected $fillable = ['name', 'code', 'is_active', 'default_employee_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Relationships

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function defaultEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_employee_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bank_employees')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'bank_location')->withTimestamps();
    }

    // Scopes

    public function scopeActive($query): void
    {
        $query->where('is_active', true);
    }
}
