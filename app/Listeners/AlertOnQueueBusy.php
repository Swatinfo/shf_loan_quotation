<?php

namespace App\Listeners;

use App\Mail\QueueBacklogAlert;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails an operator when the queue backlog crosses the threshold.
 *
 * Fired by `Illuminate\Queue\Events\QueueBusy`, which the scheduled
 * `queue:monitor` command raises every 5 min when pending jobs exceed the
 * configured `--max`. The mail is sent synchronously (QueueBacklogAlert is not
 * ShouldQueue) so it never enqueues work onto the already-busy queue. A cache
 * cooldown stops a sustained backlog from emailing every 5 minutes.
 */
class AlertOnQueueBusy
{
    /** Minutes to suppress repeat emails for the same connection:queue. */
    private const COOLDOWN_MINUTES = 30;

    public function handle(QueueBusy $event): void
    {
        Log::warning('Queue backlog high', [
            'connection' => $event->connection,
            'queue' => $event->queue,
            'size' => $event->size,
        ]);

        $email = config('app.queue_alert_email');
        if (! is_string($email) || $email === '') {
            return;
        }

        // One email per connection:queue per cooldown window.
        $cacheKey = "queue_busy_alert:{$event->connection}:{$event->queue}";
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, now()->addMinutes(self::COOLDOWN_MINUTES));

        try {
            Mail::to($email)->send(new QueueBacklogAlert(
                (string) $event->connection,
                (string) $event->queue,
                (int) $event->size,
                50,
            ));
        } catch (\Throwable $e) {
            // Never let an alert failure bubble into the queue:monitor run.
            Log::warning('Queue backlog alert email failed', ['error' => $e->getMessage()]);
        }
    }
}
