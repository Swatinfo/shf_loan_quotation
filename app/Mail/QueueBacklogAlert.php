<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Operational alert sent when the queue backlog crosses the threshold
 * (Illuminate\Queue\Events\QueueBusy, raised by the scheduled `queue:monitor`).
 *
 * Deliberately does NOT implement ShouldQueue — it must send synchronously so it
 * never adds a job to the very queue that is already backed up.
 */
class QueueBacklogAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $connectionName,
        public string $queueName,
        public int $size,
        public int $threshold,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[SHF] Queue backlog high — {$this->size} jobs pending on {$this->connectionName}:{$this->queueName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->bodyHtml(),
        );
    }

    private function bodyHtml(): string
    {
        $app = e((string) config('app.name', 'SHF'));
        $url = e((string) config('app.url', ''));
        $queue = e("{$this->connectionName}:{$this->queueName}");
        $checks = "pgrep -af 'artisan queue:work'      # is a worker alive?\n"
            ."php artisan tinker --execute='echo DB::table(\"jobs\")->count();'   # backlog\n"
            .'php artisan queue:restart           # force a clean restart';

        return implode('', [
            "<p><strong>{$app} queue backlog alert</strong></p>",
            "<p>The <code>{$queue}</code> queue has <strong>{$this->size}</strong> pending jobs (threshold {$this->threshold}).</p>",
            '<p>This usually means the queue worker is not draining. FCM pushes and Web Push '
                .'notifications are delivered through this queue, so they may be delayed until it is processed.</p>',
            '<p><strong>Check on the server:</strong></p>',
            '<pre>'.e($checks).'</pre>',
            "<p>App: {$url}</p>",
        ]);
    }
}
