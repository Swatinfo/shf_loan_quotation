<?php

namespace App\Console\Commands;

use App\Models\ShfNotification;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Console\Command;

class FcmTestCommand extends Command
{
    protected $signature = 'fcm:test
        {--user= : User ID to push to (defaults to all super_admins)}
        {--title=FCM Test : Notification title}
        {--message= : Notification body (defaults to a timestamped message)}';

    protected $description = 'Send a push DIRECTLY through FcmService (bypassing the queue) and print the per-device FCM response — for debugging.';

    public function handle(FcmService $fcm): int
    {
        if (! $fcm->isConfigured()) {
            $this->error('FCM is not configured. Check services.fcm.credentials path + service-account file.');
            $this->line('  credentials: '.(string) config('services.fcm.credentials'));
            $this->line('  project_id : '.(string) config('services.fcm.project_id'));

            return self::FAILURE;
        }

        $query = User::query()->where('is_active', true);
        if ($userId = $this->option('user')) {
            $query->where('id', (int) $userId);
        } else {
            $query->whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'));
        }
        $users = $query->get();

        if ($users->isEmpty()) {
            $this->error($userId ? "No active user with ID {$userId}." : 'No active super_admin users found.');

            return self::FAILURE;
        }

        $title = (string) $this->option('title');
        $message = (string) ($this->option('message')
            ?: 'Direct FCM test sent at '.now()->format('Y-m-d H:i:s').'.');

        foreach ($users as $user) {
            $this->line('');
            $this->info("User #{$user->id} ({$user->email})");

            // Unsaved notification: no bell row, no queue, no double-send via the
            // model hook — just exercises FcmService directly.
            $notification = new ShfNotification([
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
                'link' => '/dashboard',
            ]);
            $notification->user_id = $user->id;

            $result = $fcm->sendForNotification($notification);

            $this->line('  devices registered: '.$result['devices']);
            $this->line('  access token minted: '.($result['token_ok'] ? 'yes' : 'NO'));

            if ($result['devices'] === 0) {
                $this->warn('  → No device tokens. Open the app + log in so the bridge registers a token.');

                continue;
            }
            if (! $result['token_ok']) {
                $this->error('  → Could not mint an OAuth token. Check the service-account key + outbound HTTPS to oauth2.googleapis.com.');

                continue;
            }

            foreach ($result['results'] as $r) {
                $tokenPreview = substr((string) $r['token'], 0, 18).'…';
                if ($r['ok']) {
                    $this->info("  ✓ {$tokenPreview}  HTTP {$r['status']}  delivered to FCM");
                } else {
                    $pruned = $r['pruned'] ? ' (pruned dead token)' : '';
                    $this->error("  ✗ {$tokenPreview}  HTTP {$r['status']}  {$r['error']}{$pruned}");
                }
            }
        }

        $this->line('');
        $this->info('Done. A delivered push appears on the device when the app is backgrounded/closed.');

        return self::SUCCESS;
    }
}
