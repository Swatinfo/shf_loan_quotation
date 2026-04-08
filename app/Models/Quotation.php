<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAuditColumns;

class Quotation extends Model
{
    use HasAuditColumns, SoftDeletes;
    protected $fillable = [
        'user_id',
        'loan_id',
        'customer_name',
        'customer_type',
        'loan_amount',
        'pdf_filename',
        'pdf_path',
        'additional_notes',
        'prepared_by_name',
        'prepared_by_mobile',
        'selected_tenures',
        'location_id',
    ];

    protected $casts = [
        'loan_amount' => 'integer',
        'selected_tenures' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function banks(): HasMany
    {
        return $this->hasMany(QuotationBank::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(QuotationDocument::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function getIsConvertedAttribute(): bool
    {
        return $this->loan_id !== null;
    }

    public function getFormattedAmountAttribute(): string
    {
        return '₹ ' . $this->formatIndianNumber($this->loan_amount);
    }

    public function getTypeLabel(): string
    {
        return match ($this->customer_type) {
            'proprietor' => 'Proprietor / પ્રોપ્રાઇટર',
            'partnership_llp' => 'Partnership / LLP / પાર્ટનરશિપ / LLP',
            'pvt_ltd' => 'PVT LTD / પ્રાઇવેટ લિમિટેડ',
            'salaried' => 'Salaried / પગારદાર',
            'all' => 'All Types / બધા પ્રકાર',
            default => $this->customer_type,
        };
    }

    private function formatIndianNumber($num): string
    {
        $num = (int) $num;
        if ($num < 1000) return (string) $num;

        $result = '';
        $lastThree = $num % 1000;
        $remaining = (int) ($num / 1000);

        if ($remaining > 0) {
            $remainStr = (string) $remaining;
            $len = strlen($remainStr);
            $groups = [];
            $i = $len;
            while ($i > 0) {
                $start = max(0, $i - 2);
                $groups[] = substr($remainStr, $start, $i - $start);
                $i = $start;
            }
            $result = implode(',', array_reverse($groups)) . ',';
        }

        $result .= str_pad($lastThree, 3, '0', STR_PAD_LEFT);
        return ltrim($result, '0,') ?: '0';
    }
}
