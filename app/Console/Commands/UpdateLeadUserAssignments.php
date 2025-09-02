<?php

namespace App\Console\Commands;

use App\Models\Department;
use Illuminate\Console\Command;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\User;

class UpdateLeadUserAssignments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:update-user-assignments {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update user assignments for leads based on their department';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('Running in dry-run mode - no changes will be made');
        }

        // Find leads without user_id or with user_id = null
        $leadsWithoutUser = Lead::whereNull('user_id')->with('department')->get();
        
        $this->info("Found {$leadsWithoutUser->count()} leads without user assignment");
        
        if ($leadsWithoutUser->isEmpty()) {
            $this->info('No leads need user assignment updates');
            return;
        }

        $updatedCount = 0;
        $errors = [];

        foreach ($leadsWithoutUser as $lead) {
            try {
                $userId = $this->mapUserForLead($lead);
                
                if ($dryRun) {
                    $this->line("Would assign lead {$lead->id} (department: " . ($lead->department ? $lead->department->name : 'none') . ") to user {$userId}");
                } else {
                    $lead->update(['user_id' => $userId]);
                    $this->line("Assigned lead {$lead->id} to user {$userId}");
                }
                
                $updatedCount++;
            } catch (\Exception $e) {
                $errors[] = "Failed to update lead {$lead->id}: " . $e->getMessage();
            }
        }

        if (!$dryRun) {
            $this->info("Successfully updated {$updatedCount} leads");
        } else {
            $this->info("Would update {$updatedCount} leads");
        }

        if (!empty($errors)) {
            $this->error('Errors encountered:');
            foreach ($errors as $error) {
                $this->error($error);
            }
        }
    }

    /**
     * Map a lead to an appropriate user based on department
     */
    private function mapUserForLead(Lead $lead): int
    {
        if (!$lead->department) {
            return 1; // Default fallback
        }

        if ($lead->department->name === 'Hernia') {
            // Find users in the 'hernia' group
            $users = User::whereHas('groups', function($query) {
                $query->where('name', 'hernia');
            })->pluck('id')->toArray();
            
            if (!empty($users)) {
                return $users[0];
            }
        } else {
            // Find users in the 'privatescan' group
            $users = User::whereHas('groups', function($query) {
                $query->where('name', 'privatescan');
            })->pluck('id')->toArray();
            
            if (!empty($users)) {
                return $users[0];
            }
        }
        
        // Fallback to user ID 1
        return 1;
    }
}