<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Activity\Models\Activity;
use Carbon\Carbon;

class ReleaseOverdueActivities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activities:release-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release activities that have been assigned for more than 24 hours without completion';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $cutoffTime = Carbon::now()->subHours(24);
        
        $overdueActivities = Activity::whereNotNull('user_id')
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '<', $cutoffTime)
            ->where('is_done', false)
            ->get();

        $releasedCount = 0;
        
        foreach ($overdueActivities as $activity) {
            $activity->update([
                'user_id' => null,
                'assigned_at' => null,
            ]);
            $releasedCount++;
            
            $this->info("Released activity ID {$activity->id}: {$activity->title}");
        }

        $this->info("Released {$releasedCount} overdue activities.");
        
        return Command::SUCCESS;
    }
}