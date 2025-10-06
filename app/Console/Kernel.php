<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('inbound-emails:process')->everyMinute();
        $schedule->command('emails:sync-graph')->everyFiveMinutes();
        $schedule->command('activities:release-overdue')->hourly();

        // Keep activity statuses in sync each hour
        $schedule->command('activities:sync-statuses')->hourly();

        // Refresh duplicate caches (leads & persons) every hour
        $schedule->command('duplicates:refresh-cache --clear')->hourly();

        // Clean up old email logs daily
        $schedule->command('emails:cleanup-logs')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
