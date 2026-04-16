<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// We only use Graph
// Schedule::command('inbound-emails:process')->everyMinute()->withoutOverlapping();
Schedule::command('emails:sync-graph')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('activities:release-overdue')->hourly();
Schedule::command('activities:sync-statuses')->hourly();
Schedule::command('duplicates:refresh-cache --clear')->hourly();
Schedule::command('emails:cleanup-logs')->daily();
Schedule::command('emails:cleanup-graph-inbox')->daily();
Schedule::command('patient:send-notification-email')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('afb:send-daily')->dailyAt('06:00')->withoutOverlapping();
