<?php

namespace App\Jobs;

use App\Models\ShfNotification;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Delivers an in-app notification to the recipient's native devices via FCM,
 * off the web request. Dispatched from ShfNotification::booted() so creating a
 * notification never blocks on outbound FCM HTTP calls.
 */
class SendFcmPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public int $notificationId) {}

    public function handle(FcmService $fcm): void
    {
        $notification = ShfNotification::find($this->notificationId);
        if ($notification) {
            $fcm->sendForNotification($notification);
        }
    }
}
