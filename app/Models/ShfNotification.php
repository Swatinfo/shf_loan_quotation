<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShfNotification extends Model
{
    protected $table = 'shf_notifications';

    const TYPE_INFO = 'info';
    const TYPE_SUCCESS = 'success';
    const TYPE_WARNING = 'warning';
    const TYPE_ERROR = 'error';
    const TYPE_STAGE_UPDATE = 'stage_update';
    const TYPE_ASSIGNMENT = 'assignment';

    protected $fillable = [
        'user_id', 'title', 'message', 'type', 'is_read',
        'loan_id', 'stage_key', 'link',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(LoanDetail::class, 'loan_id');
    }

    // Scopes

    public function scopeUnread($query): void
    {
        $query->where('is_read', false);
    }

    public function scopeForUser($query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $limit = 50): void
    {
        $query->latest()->limit($limit);
    }
}
