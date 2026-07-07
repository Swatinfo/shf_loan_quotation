<?php

namespace App\Models;

use App\Jobs\SendFcmPush;
use App\Notifications\ShfPushNotification;
use App\Services\FcmService;
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

    protected static function booted(): void
    {
        static::created(function (self $notification): void {
            // Native OS notification via Web Push (out-of-tab / PWA closed).
            // Fires only if the recipient has an active push subscription.
            //
            // In-tab live updates are handled by the bell-badge poll
            // (newtheme/layouts/app.blade.php), which also plays the chime when
            // the unread count rises — no WebSocket/broadcast dependency.
            if ($user = $notification->user) {
                try {
                    $user->notify(new ShfPushNotification($notification));
                } catch (\Throwable $e) {
                    \Log::warning('Web push failed', [
                        'notification_id' => $notification->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Native OS notification via FCM (Flutter app, even when closed).
            // Queued so the outbound FCM HTTP calls never block the request that
            // created the notification. No-op unless FCM is configured and the
            // recipient has a registered device. Dispatch is guarded + wrapped so
            // a queue hiccup can't bubble into the originating request.
            try {
                if (app(FcmService::class)->isConfigured()) {
                    SendFcmPush::dispatch($notification->id);
                }
            } catch (\Throwable $e) {
                \Log::warning('FCM push dispatch failed', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
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
