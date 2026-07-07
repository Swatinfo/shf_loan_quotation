<?php

namespace Tests\Feature;

use App\Mail\QueueBacklogAlert;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QueueBusyAlertTest extends TestCase
{
    public function test_queue_busy_event_emails_the_configured_address(): void
    {
        Mail::fake();
        Config::set('app.queue_alert_email', 'ops@example.com');

        event(new QueueBusy('database', 'default', 75));

        Mail::assertSent(QueueBacklogAlert::class, function (QueueBacklogAlert $mail) {
            return $mail->hasTo('ops@example.com')
                && $mail->size === 75
                && $mail->queueName === 'default';
        });
    }

    public function test_repeat_spikes_are_throttled_within_the_cooldown(): void
    {
        Mail::fake();
        Config::set('app.queue_alert_email', 'ops@example.com');

        event(new QueueBusy('database', 'default', 75));
        event(new QueueBusy('database', 'default', 90));

        // Cooldown keyed on connection:queue suppresses the second email.
        Mail::assertSent(QueueBacklogAlert::class, 1);
    }

    public function test_no_email_when_alert_address_is_empty(): void
    {
        Mail::fake();
        Config::set('app.queue_alert_email', '');

        event(new QueueBusy('database', 'default', 75));

        Mail::assertNothingSent();
    }

    public function test_distinct_queues_each_alert(): void
    {
        Mail::fake();
        Config::set('app.queue_alert_email', 'ops@example.com');
        Cache::flush();

        event(new QueueBusy('database', 'default', 75));
        event(new QueueBusy('database', 'emails', 75));

        Mail::assertSent(QueueBacklogAlert::class, 2);
    }
}
