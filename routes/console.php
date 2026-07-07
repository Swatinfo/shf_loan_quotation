<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Self-healing queue worker (FCM pushes + Web Push + notifications) ───────
// No systemd / supervisor needed — just one `* * * * * php artisan schedule:run`
// crontab line (see notification_setup.md). ShfNotification::created queues a
// SendFcmPush job; THIS worker is what actually delivers the push. If it isn't
// running, no FCM/Web Push goes out and the jobs table piles up.
//
// AUTO START / RESTART: everyMinute() + withoutOverlapping() makes the scheduler
// re-check every minute. If a worker is already alive the tick is skipped (never
// two at once); if it exited or was killed, the next tick launches a fresh one.
// That relaunch IS the restart — a crashed worker is back within ~1 minute with
// nothing to babysit. Flags:
//   --max-time=300        worker recycles every 5 min (frees memory + picks up
//                         new code), exits cleanly, next tick relaunches it.
//   --sleep=1             ~1s pickup latency while the queue is idle.
//   withoutOverlapping(6) single worker at a time. The 6-min mutex TTL sits just
//                         above --max-time so a healthy worker is never cloned,
//                         yet a HARD-killed worker (reboot / OOM — lock never
//                         released) self-heals within ~6 min instead of the
//                         default 24h.
//   runInBackground()     the long-lived worker doesn't block the scheduled
//                         commands below.
Schedule::command('queue:work --tries=3 --timeout=120 --max-time=300 --sleep=1')
    ->everyMinute()
    ->withoutOverlapping(6)
    ->runInBackground();

// Auto-mark unread notifications older than 48h as read. Runs hourly.
Schedule::command('notifications:mark-stale-read --hours=48')->hourly();

// Daily reminders — DVR follow-ups + task due dates.
// 08:00 = due today; 20:00 = due tomorrow (heads-up).
Schedule::command('reminders:send-daily --when=morning')->dailyAt('08:00');
Schedule::command('reminders:send-daily --when=evening')->dailyAt('20:00');

// Observability — fires Laravel's QueueBusy event if the backlog exceeds 50,
// so a stuck queue alerts you instead of piling up silently. Optional.
Schedule::command('queue:monitor database:default --max=50')->everyFiveMinutes();
