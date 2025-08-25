<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckTakeoverPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:check-takeover {email?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and fix takeover permissions for a user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email') ?? 'linda@privatescan.nl';

        $this->info("Checking permissions for user: {$email}");

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email {$email} not found!");

            return 1;
        }

        $this->info("User found: {$user->name} (ID: {$user->id})");

        // Check current permissions
        $canTakeoverBouncer = bouncer()->hasPermission('activities.takeover');
        $canTakeoverUser = $user->can('activities.takeover');

        $this->info('Current permissions:');
        $this->info("- bouncer()->hasPermission('activities.takeover'): ".($canTakeoverBouncer ? 'true' : 'false'));
        $this->info("- user->can('activities.takeover'): ".($canTakeoverUser ? 'true' : 'false'));

        // Show role
        $role = $user->role;
        $this->info('User role: '.($role ? $role->name : 'None'));

        if (! $role) {
            $this->warn('User has no role assigned!');
        } else {
            $this->info('Role permissions: '.implode(', ', $role->permissions ?? []));
        }

        // Check if activities.takeover permission exists using DB directly
        $permissionExists = DB::table('abilities')->where('name', 'activities.takeover')->exists();
        $this->info("Permission 'activities.takeover' exists in database: ".($permissionExists ? 'true' : 'false'));

        if (! $permissionExists) {
            $this->warn("Permission 'activities.takeover' does not exist in database!");
            $this->info('Creating permission...');

            DB::table('abilities')->insert([
                'name'       => 'activities.takeover',
                'title'      => 'Takeover Activities',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->info('Permission created successfully!');
        }

        // Check if user has the permission
        if (! $canTakeoverBouncer) {
            $this->warn('User does not have takeover permission!');

            if ($this->confirm('Do you want to assign the takeover permission to this user?')) {
                bouncer()->allow($user)->to('activities.takeover');
                $this->info('Permission assigned successfully!');

                // Verify
                $newCanTakeover = bouncer()->hasPermission('activities.takeover');
                $this->info('New permission check: '.($newCanTakeover ? 'true' : 'false'));
            }
        } else {
            $this->info('User already has takeover permission!');
        }

        return 0;
    }
}
