<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->alias([
            'resolve.hospital' => \App\Modules\Admin\Middleware\ResolveHospital::class,
            'role'             => \App\Modules\Admin\Middleware\CheckRole::class,
            'ability'          => \App\Modules\Auth\Middleware\CheckTokenAbility::class,
            'admin'            => \App\Http\Middleware\EnsureAdmin::class,
            'super_admin'      => \App\Http\Middleware\EnsureSuperAdmin::class,
            'module'           => \App\Http\Middleware\EnsureModuleEnabled::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Queue evaluation every minute
        $schedule->command('medos:evaluate-queues')->everyMinute()->withoutOverlapping();

        // Send appointment reminders every 15 minutes
        $schedule->command('medos:send-reminders')->everyFifteenMinutes();

        // Process follow-ups daily at 9 AM
        $schedule->command('medos:process-follow-ups')->dailyAt('09:00');

        // Re-engage patients weekly on Monday at 10 AM
        $schedule->command('medos:re-engage')->weeklyOn(1, '10:00');

        // Alert on asset warranties / AMC / CMC expiring soon — daily at 8 AM
        $schedule->command('medos:asset-warranty-alerts')->dailyAt('08:00');

        // Prune old Sanctum tokens daily
        $schedule->command('sanctum:prune-expired --hours=24')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
