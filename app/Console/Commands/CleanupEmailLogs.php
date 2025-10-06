<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use Carbon\Carbon;
use Illuminate\Console\Command as IlluminateCommand;
use Illuminate\Support\Facades\Log;

class CleanupEmailLogs extends IlluminateCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:cleanup-logs {--days= : Number of days to keep logs (default from config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old email logs older than specified days';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $days = $this->option('days') ?: config('mail.log_retention_days', 7);

        if (! is_numeric($days) || $days < 0) {
            $this->error('Days must be a positive number.');

            return IlluminateCommand::FAILURE;
        }

        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Cleaning up email logs older than {$days} days (before {$cutoffDate->format('Y-m-d H:i:s')})...");

        // Count logs to be deleted
        $logsToDelete = EmailLog::where('created_at', '<', $cutoffDate)->count();

        if ($logsToDelete === 0) {
            $this->info('No email logs found to delete.');

            return IlluminateCommand::SUCCESS;
        }

        $this->info("Found {$logsToDelete} email logs to delete.");

        if ($this->confirm("Are you sure you want to delete {$logsToDelete} email logs?")) {
            // Delete logs in batches to avoid memory issues
            $deletedCount = 0;
            $batchSize = 1000;

            do {
                $deleted = EmailLog::where('created_at', '<', $cutoffDate)
                    ->limit($batchSize)
                    ->delete();

                $deletedCount += $deleted;

                if ($deleted > 0) {
                    $this->info("Deleted {$deleted} logs... (Total: {$deletedCount})");
                }

            } while ($deleted > 0);

            $this->info("Successfully deleted {$deletedCount} email logs older than {$days} days.");

            // Log the cleanup action
            Log::info('Email logs cleanup completed', [
                'deleted_count'  => $deletedCount,
                'retention_days' => $days,
                'cutoff_date'    => $cutoffDate->toDateTimeString(),
            ]);

        } else {
            $this->info('Cleanup cancelled.');
        }

        return IlluminateCommand::SUCCESS;
    }
}
