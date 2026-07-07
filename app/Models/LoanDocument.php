<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanDocument extends Model
{
    use HasAuditColumns;
    const STATUS_PENDING = 'pending';
    const STATUS_RECEIVED = 'received';
    const STATUS_REJECTED = 'rejected';
    const STATUS_WAIVED = 'waived';

    const STATUSES = ['pending', 'received', 'rejected', 'waived'];

    const STATUS_LABELS = [
        'pending' => ['label' => 'Pending', 'color' => 'secondary'],
        'received' => ['label' => 'Received', 'color' => 'success'],
        'rejected' => ['label' => 'Rejected', 'color' => 'danger'],
        'waived' => ['label' => 'Waived', 'color' => 'warning'],
    ];

    protected $fillable = [
        'loan_id', 'document_name_en', 'document_name_gu', 'is_required',
        'status', 'received_date', 'received_by', 'rejected_reason', 'notes', 'sort_order',
        'file_path', 'file_name', 'file_size', 'file_mime', 'uploaded_by', 'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'received_date' => 'date',
            'sort_order' => 'integer',
            'file_size' => 'integer',
            'uploaded_at' => 'datetime',
        ];
    }

    // Relationships

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function uploadedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Helpers

    public function hasFile(): bool
    {
        return $this->file_path !== null;
    }

    public function formattedFileSize(): string
    {
        $bytes = $this->file_size ?? 0;
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }

    public function isReceived(): bool
    {
        return $this->status === self::STATUS_RECEIVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isResolved(): bool
    {
        return in_array($this->status, [self::STATUS_RECEIVED, self::STATUS_WAIVED]);
    }

    // Scopes

    public function scopeRequired($query): void
    {
        $query->where('is_required', true);
    }

    public function scopeReceived($query): void
    {
        $query->where('status', self::STATUS_RECEIVED);
    }

    public function scopePending($query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    public function scopeRejected($query): void
    {
        $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeResolved($query): void
    {
        $query->whereIn('status', [self::STATUS_RECEIVED, self::STATUS_WAIVED]);
    }

    public function scopeUnresolved($query): void
    {
        $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_REJECTED]);
    }
}
