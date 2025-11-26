<?php

namespace App\Actions\Keycloak;

use App\Services\Keycloak\KeycloakService;
use Illuminate\Support\Facades\Log;
use Webkul\Installer\Database\Seeders\User\UserSeeder;
use Webkul\User\Repositories\UserRepository;

class SyncUsersToKeycloakAction
{
    public function __construct(
        protected KeycloakService $keycloakService,
        protected UserRepository $userRepository,
        protected AddKeycloakUserAction $addKeycloakUserAction
    ) {}

    /**
     * Execute the sync action.
     * Uses CRM user passwords - cannot sync users without passwords.
     *
     * @return array{success: bool, synced: int, skipped: int, errors: int, message?: string}
     */
    public function execute(bool $dryRun = false): array
    {

        $accessToken = $this->keycloakService->getAdminToken();

        if (! $accessToken) {
            return [
                'success' => false,
                'synced'  => 0,
                'skipped' => 0,
                'errors'  => 0,
                'message' => 'Kon niet authenticeren met Keycloak admin.',
            ];
        }

        // Get all active users from CRM
        $users = $this->userRepository->findWhere(['status' => 1]);
        $synced = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($users as $user) {
            if (empty($user->email)) {
                Log::warning('Skipping user without email', ['user_id' => $user->id]);
                $skipped++;

                continue;
            }

            // Check if user already exists in Keycloak
            $keycloakUser = $this->keycloakService->getUserByEmail($user->email, $accessToken);

            if ($keycloakUser) {
                // User exists, update keycloak_user_id if needed
                if (empty($user->keycloak_user_id)) {
                    if (! $dryRun) {
                        $this->userRepository->update(['keycloak_user_id' => $keycloakUser['id']], $user->id);
                        Log::info('Updated keycloak_user_id for existing Keycloak user', [
                            'user_id'     => $user->id,
                            'email'       => $user->email,
                            'keycloak_id' => $keycloakUser['id'],
                        ]);
                    }
                }

                // Assign default role "medewerker" to existing user
                if (! $dryRun) {
                    $this->assignDefaultRole($keycloakUser['id'], $accessToken);
                }

                $synced++;

                continue;
            }

            // Get password from UserSeeder
            $password = $this->getPasswordFromSeeder($user->email);

            if (empty($password)) {
                Log::warning('Skipping user - password not found in UserSeeder', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                ]);
                $skipped++;

                continue;
            }

            // Prepare user data
            $userData = [
                'username'      => $user->email,
                'email'         => $user->email,
                'firstName'     => $user->first_name ?? '',
                'lastName'      => $user->last_name ?? '',
                'enabled'       => $user->status == 1,
                'emailVerified' => true,
            ];

            if ($dryRun) {
                Log::info('Would create user in Keycloak (dry-run)', [
                    'email' => $user->email,
                ]);
                $synced++;

                continue;
            }

            // Use AddKeycloakUserAction to create user with password from seeder
            $result = $this->addKeycloakUserAction->execute($userData, $password, false);

            if (! $result['success']) {
                Log::error('Failed to sync user to Keycloak', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
                $errors++;

                continue;
            }

            // Update keycloak_user_id in CRM
            $this->userRepository->update(['keycloak_user_id' => $result['keycloak_user_id']], $user->id);

            // Assign default role "medewerker" to new user
            // Note: AddKeycloakUserAction already assigns the role, but we ensure it here too
            $this->assignDefaultRole($result['keycloak_user_id'], $accessToken);

            Log::info('User synced to Keycloak', [
                'user_id'     => $user->id,
                'email'       => $user->email,
                'keycloak_id' => $result['keycloak_user_id'],
            ]);

            $synced++;
        }

        return [
            'success' => true,
            'synced'  => $synced,
            'skipped' => $skipped,
            'errors'  => $errors,
        ];
    }

    /**
     * Get password from UserSeeder for a given email.
     */
    protected function getPasswordFromSeeder(string $email): ?string
    {
        return UserSeeder::getPasswordForEmail($email);
    }

    /**
     * Assign default role "medewerker" to a Keycloak user.
     */
    protected function assignDefaultRole(string $keycloakUserId, ?string $accessToken = null): void
    {
        try {
            $roleAssigned = $this->keycloakService->assignRoleToUser($keycloakUserId, 'medewerker', $accessToken);

            if (! $roleAssigned) {
                Log::warning('Failed to assign role to user in Keycloak during sync', [
                    'keycloak_user_id' => $keycloakUserId,
                    'role'             => 'medewerker',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while assigning role to user in Keycloak during sync', [
                'keycloak_user_id' => $keycloakUserId,
                'role'             => 'medewerker',
                'error'            => $e->getMessage(),
            ]);
        }
    }
}
