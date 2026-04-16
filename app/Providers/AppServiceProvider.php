<?php

namespace App\Providers;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lab404\Impersonate\Events\LeaveImpersonation;
use Lab404\Impersonate\Events\TakeImpersonation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(TakeImpersonation::class, function (TakeImpersonation $event): void {
            ActivityLog::log('impersonate_start', $event->impersonated, [
                'impersonator' => $event->impersonator->name,
                'impersonated' => $event->impersonated->name,
            ]);
        });

        Event::listen(LeaveImpersonation::class, function (LeaveImpersonation $event): void {
            ActivityLog::log('impersonate_end', $event->impersonated, [
                'impersonator' => $event->impersonator->name,
                'impersonated' => $event->impersonated->name,
            ]);
        });
    }
}
