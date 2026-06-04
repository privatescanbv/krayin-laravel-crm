<?php

namespace App\Console\Commands;

use App\Mail\FailedJobsCriticalAlert;
use App\Mail\FailedJobsWarningAlert;
use Illuminate\Console\Command;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MonitorFailedJobs extends Command
{
    private const int CACHE_TTL_MINUTES = 12 * 60;

    protected $signature = 'queue:monitor-failed-jobs';

    protected $description = 'Monitor the failed_jobs table and send alerts when thresholds are exceeded.';

    public function handle(): int
    {
        $alertEmail = (string) config('failed_jobs.alert_email', '');

        if (empty($alertEmail)) {
            $this->info('Failed jobs monitoring disabled (FAILED_JOBS_ALERT_EMAIL not set).');

            return Command::SUCCESS;
        }

        $recipients = array_values(array_filter(array_map('trim', explode(',', $alertEmail))));

        $count = (int) DB::table('failed_jobs')->count();
        $warningThresh = (int) config('failed_jobs.warning_threshold', 1);
        $criticalThresh = (int) config('failed_jobs.critical_threshold', 10);

        Log::info('Failed jobs monitor: checked failed_jobs count.', [
            'count'              => $count,
            'warning_threshold'  => $warningThresh,
            'critical_threshold' => $criticalThresh,
        ]);

        $this->info("Failed jobs: {$count} (warning ≥ {$warningThresh}, critical ≥ {$criticalThresh})");

        if ($count >= $criticalThresh) {
            Cache::forget('failed_jobs.alert.warning');
            $this->sendAlert('critical', $count, $recipients, fn () => new FailedJobsCriticalAlert($count));
        } elseif ($count >= $warningThresh) {
            Cache::forget('failed_jobs.alert.critical');
            $this->sendAlert('warning', $count, $recipients, fn () => new FailedJobsWarningAlert($count));
        } else {
            Cache::forget('failed_jobs.alert.warning');
            Cache::forget('failed_jobs.alert.critical');

            $this->info('Failed jobs count is below all thresholds – no alert needed.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param  \Closure(): Mailable  $mailableFactory
     */
    private function sendAlert(string $level, int $count, array $recipients, \Closure $mailableFactory): void
    {
        $cacheKey = "failed_jobs.alert.{$level}";

        if (Cache::has($cacheKey)) {
            $this->info("Failed jobs [{$level}] alert already sent within the last ".self::CACHE_TTL_MINUTES.' minutes – skipping.');

            return;
        }

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send($mailableFactory());
        }

        Cache::put($cacheKey, true, now()->addMinutes(self::CACHE_TTL_MINUTES));

        Log::warning("Failed jobs [{$level}] alert sent.", [
            'count'      => $count,
            'level'      => $level,
            'recipients' => $recipients,
        ]);

        $this->info("Failed jobs [{$level}] alert sent to: ".implode(', ', $recipients));
    }
}
