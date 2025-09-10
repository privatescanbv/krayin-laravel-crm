<?php

namespace App\Console\Commands;

use App\Services\ActivityStatusService;
use Illuminate\Console\Command;
use Webkul\Activity\Models\Activity;

class SyncActivityStatuses extends Command
{
    protected $signature = 'activities:sync-statuses';

    protected $description = 'Synchronize activity statuses based on schedule dates';

    public function handle(): int
    {
        Activity::query()->chunkById(500, function ($activities) {
            foreach ($activities as $activity) {
                $computed = ActivityStatusService::computeStatus($activity->schedule_from, $activity->schedule_to, $activity->status);
                if ($computed->value !== ($activity->status?->value ?? null)) {
                    $activity->status = $computed;
                    $activity->save();
                }
            }
        });

        $this->info('Activity statuses synced.');

        return Command::SUCCESS;
    }
}
