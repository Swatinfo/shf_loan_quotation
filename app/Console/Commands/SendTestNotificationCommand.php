<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendTestNotificationCommand extends Command
{
    protected $signature = 'notifications:test
        {--user= : Optional user ID to notify instead of all super_admins}
        {--title=Test Notification : Notification title}
        {--message= : Notification message (defaults to a timestamped test message)}
        {--type=info : Notification type (info, success, warning, assignment, etc.)}';

    protected $description = 'Send a test notification to the super_admin user(s) (or a specific --user ID)';

    public function __construct(private NotificationService $notifications)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $userOption = $this->option('user');

        $query = User::query()->where('is_active', true);
        if ($userOption) {
            $query->where('id', (int) $userOption);
        } else {
            $query->whereHas('roles', fn ($q) => $q->where('slug', 'super_admin'));
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->error($userOption
                ? "No active user found with ID {$userOption}."
                : 'No active super_admin users found.');

            return self::FAILURE;
        }

        $title = (string) $this->option('title');
        $message = (string) ($this->option('message')
            ?: 'This is a test notification sent at '.now()->format('Y-m-d H:i:s').'.');
        $type = (string) $this->option('type');

        foreach ($users as $user) {
            $this->notifications->notify(
                $user->id,
                $title,
                $message,
                $type,
                null,
                null,
                route('notifications.index'),
            );
            $this->info("Sent to: {$user->name} (#{$user->id}, {$user->email})");
        }

        $this->info('Test notification dispatched to '.$users->count().' user(s).');

        return self::SUCCESS;
    }
}
