<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationDocument extends Model
{
    protected $fillable = [
        'quotation_id',
        'document_name_en',
        'document_name_gu',
        'is_excluded',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'is_excluded' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }
}
