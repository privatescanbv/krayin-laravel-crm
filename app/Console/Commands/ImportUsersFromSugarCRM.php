<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\User\Models\Role;
use Webkul\User\Models\User;

class ImportUsersFromSugarCRM extends AbstractSugarCRMImport
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:users
                            {--connection=sugarcrm : Database connection name}
                            {--table=users : Source table name}
                            {--limit=100 : Number of records to import}
                            {--user-ids=* : Specific user IDs to import (ignores limit)}
                            {--dry-run : Show what would be imported without actually importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import users from SugarCRM database';

    /**
     * The default role ID to assign to imported users
     *
     * @var int
     */
    private $defaultRoleId;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = $this->option('connection');
        $table = $this->option('table');
        $limit = (int) $this->option('limit');
        $userIds = (array) $this->option('user-ids');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Starting user import from SugarCRM...');
        $this->info("Connection: {$connection}");
        $this->info("Table: {$table}");
        if (! empty($userIds)) {
            $this->info('User IDs: '.implode(', ', $userIds));
        } else {
            $this->info("Limit: {$limit}");
        }
        $this->info('Dry run: '.($dryRun ? 'Yes' : 'No'));

        try {
            return $this->executeImport($dryRun, function () use ($connection, $table, $limit, $userIds, $dryRun) {
                // Start import run tracking
                if (! $dryRun) {
                    $this->startImportRun('users');
                }

                // Test connection
                $this->testConnection($connection);

                // Look up the default role ID
                $this->initializeDefaultRole();

                // Get records from SugarCRM
                $query = DB::connection($connection)
                    ->table($table)
                    ->select([
                        'id',
                        'user_name',
                        'first_name',
                        'last_name',
                        'status',
                        'title',
                        'department',
                        'phone_home',
                        'phone_mobile',
                        'phone_work',
                        'phone_other',
                        'phone_fax',
                        'address_street',
                        'address_city',
                        'address_state',
                        'address_country',
                        'address_postalcode',
                        'date_entered',
                        'date_modified',
                        'created_by',
                        'modified_user_id',
                        'is_admin',
                        'description',
                        'employee_status',
                        'portal_only',
                        'show_on_employees',
                    ])
                    ->where('deleted', 0); // Filter out deleted users

                // Apply filtering if specific user IDs are provided
                if (! empty($userIds)) {
                    $query->whereIn('id', $userIds);
                } else {
                    $query->limit($limit);
                }

                $sugarUsers = $query->get();

                $this->info("Found {$sugarUsers->count()} users to import");

                if ($sugarUsers->isEmpty()) {
                    $this->warn('No users found to import.');

                    return;
                }

                $importedCount = 0;
                $updatedCount = 0;
                $skippedCount = 0;
                $errorCount = 0;

                foreach ($sugarUsers as $sugarUser) {
                    try {
                        $result = $this->importUser($sugarUser, $dryRun);

                        switch ($result) {
                            case 'imported':
                                $importedCount++;
                                break;
                            case 'updated':
                                $updatedCount++;
                                break;
                            case 'skipped':
                                $skippedCount++;
                                break;
                        }
                    } catch (Exception $e) {
                        $errorCount++;
                        $this->logError("Error importing user {$sugarUser->id}: {$e->getMessage()}", [
                            'sugar_user_id' => $sugarUser->id,
                            'user_name'     => $sugarUser->user_name,
                        ]);
                    }
                }

                // Show summary
                $this->info("\n".str_repeat('=', 50));
                $this->info('IMPORT SUMMARY');
                $this->info(str_repeat('=', 50));
                $this->info("Users imported: {$importedCount}");
                $this->info("Users updated: {$updatedCount}");
                $this->info("Users skipped: {$skippedCount}");
                $this->info("Errors: {$errorCount}");
                $this->info('Total processed: '.($importedCount + $updatedCount + $skippedCount + $errorCount));

                // Complete import run tracking
                $this->completeImportRun([
                    'processed' => $importedCount + $updatedCount + $skippedCount + $errorCount,
                    'imported'  => $importedCount + $updatedCount,
                    'skipped'   => $skippedCount,
                    'errored'   => $errorCount,
                ]);
            });
        } catch (Exception $e) {
            $this->logError('Import failed: '.$e->getMessage(), ['connection' => $connection]);

            return 1;
        }
    }

    /**
     * Import a single user from SugarCRM
     *
     * @param  object  $sugarUser  The SugarCRM user record
     * @param  bool  $dryRun  Whether this is a dry run
     * @return string 'imported', 'updated', or 'skipped'
     */
    private function importUser($sugarUser, bool $dryRun): string
    {
        // Map SugarCRM data to our user structure
        $userData = $this->mapUserData($sugarUser);

        if ($dryRun) {
            $fullName = trim("{$userData['first_name']} {$userData['last_name']}");
            $this->info("DRY RUN: Would import user: {$fullName} ({$userData['email']})");

            return 'imported';
        }

        // Check if user already exists by external_id
        $existingUser = User::where('external_id', $sugarUser->id)->first();

        if ($existingUser) {
            // don't update password and email for existing users
            unset($userData['password']);
            unset($userData['email']);
            // Update existing user
            $existingUser->update($userData);
            $fullName = trim("{$userData['first_name']} {$userData['last_name']}");
            $this->info("Updated user by external_id: {$fullName}");

            return 'updated';
        }

        // Check if user exists by first_name and last_name match
        $existingUserByName = User::where('first_name', $userData['first_name'])
            ->where('last_name', $userData['last_name'])
            ->first();
        if ($existingUserByName) {
            // don't update password and email for existing users
            unset($userData['password']);
            unset($userData['email']);
            // Synchronize the existing user with SugarCRM data
            $existingUserByName->update($userData);
            $fullName = trim("{$userData['first_name']} {$userData['last_name']}");
            $this->info("Synchronized existing user by name: {$fullName}");

            return 'updated';
        }

        // Create new user with timestamps from SugarCRM
        $timestamps = [
            'created_at' => $this->parseSugarDate($sugarUser->date_entered),
            'updated_at' => $this->parseSugarDate($sugarUser->date_modified),
        ];

        $this->createEntityWithTimestamps(User::class, $userData, $timestamps);

        return 'imported';
    }

    /**
     * Map SugarCRM user data to our user structure
     *
     * @param  object  $sugarUser  The SugarCRM user record
     * @return array The mapped user data
     */
    private function mapUserData($sugarUser): array
    {
        // Get first_name and last_name from SugarCRM
        $firstName = trim($sugarUser->first_name ?? '');
        $lastName = trim($sugarUser->last_name ?? '');

        // Fallback to username if no names provided
        if (empty($firstName) && empty($lastName)) {
            $parts = explode('.', $sugarUser->user_name ?? 'Unknown User');
            $firstName = ucfirst($parts[0] ?? 'Unknown');
            $lastName = ucfirst($parts[1] ?? 'User');
        }

        // Generate email if not available (using user_name as base)
        $email = $this->generateEmailFromUserName($sugarUser->user_name);

        // Map status - 'Active' in SugarCRM means active (status = 1)
        $status = (strtolower($sugarUser->status ?? '') === 'active') ? 1 : 0;

        return [
            'external_id'     => $sugarUser->id,
            'first_name'      => $firstName,
            'last_name'       => $lastName,
            'email'           => $email,
            'status'          => $status,
            'role_id'         => $this->determineRoleId($sugarUser),
            'view_permission' => $this->determineViewPermission($sugarUser),
            // Generate a random password since we don't import passwords from SugarCRM
            'password' => bcrypt(Str::random(16)),
        ];
    }

    /**
     * Generate email from username
     */
    private function generateEmailFromUserName(?string $userName): string
    {
        if (empty($userName)) {
            return 'user'.time().'@imported.local';
        }

        // If username already looks like an email, use it
        if (filter_var($userName, FILTER_VALIDATE_EMAIL)) {
            return $userName;
        }

        // Otherwise, create email from username
        return $userName.'@imported.local';
    }

    /**
     * Initialize the default role ID (administrator: Beheerder/Administrator from RoleSeeder, or id 1).
     *
     * @throws Exception
     */
    private function initializeDefaultRole(): void
    {
        $role = Role::where('name', 'Beheerder')->first()
            ?? Role::where('name', 'Administrator')->first()
            ?? Role::find(1);

        if (! $role) {
            throw new Exception(
                'No administrator role found. Run `php artisan db:seed` (RoleSeeder). '.
                'Expected role name "Beheerder" or "Administrator", or roles.id = 1.'
            );
        }

        $this->defaultRoleId = $role->id;
        $this->info("Using role: {$role->name} (ID: {$role->id})");
    }

    /**
     * Determine role ID based on SugarCRM user data
     *
     * @param  object  $sugarUser
     */
    private function determineRoleId($sugarUser): int
    {
        // For now, all users get the default 'Beheerder' role
        // This can be modified later based on requirements
        return $this->defaultRoleId;
    }

    /**
     * Determine view permission based on SugarCRM user data
     *
     * @param  object  $sugarUser
     */
    private function determineViewPermission($sugarUser): string
    {
        // For now, all users get global permission with the 'Beheerder' role
        // This can be modified later based on requirements
        return 'global';
    }
}
