<?php

namespace App\Models;

use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasAuditColumns, SoftDeletes;

    protected $fillable = [
        'customer_name',
        'mobile',
        'email',
        'date_of_birth',
        'pan_number',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    public function loans(): HasMany
    {
        return $this->hasMany(LoanDetail::class, 'customer_id');
    }
}
