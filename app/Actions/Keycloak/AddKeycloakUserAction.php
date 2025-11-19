<?php

namespace App\Actions\Keycloak;

use App\Services\KeycloakService;
use Illuminate\Support\Facades\Log;

class AddKeycloakUserAction
{
    public function __construct(
        protected KeycloakService $keycloakService
    ) {}

    /**
     * Execute the action to add a user to Keycloak.
     *
     * @param  array  $userData  User data (email, firstName, lastName, etc.)
     * @param  string  $password  Password for the user (required)
     * @param  bool  $temporary  Whether password is temporary
     * @return array{success: bool, keycloak_user_id?: string, message?: string}
     */
    public function execute(array $userData, string $password, bool $temporary = false): array
    {
        $accessToken = $this->keycloakService->getAdminToken();

        if (! $accessToken) {
            return [
                'success' => false,
                'message' => 'Kon niet authenticeren met Keycloak admin.',
            ];
        }

        // Check if user already exists
        if (isset($userData['email'])) {
            $existingUser = $this->keycloakService->getUserByEmail($userData['email'], $accessToken);
            if ($existingUser) {
                return [
                    'success'          => false,
                    'message'          => "Gebruiker met email {$userData['email']} bestaat al in Keycloak.",
                    'keycloak_user_id' => $existingUser['id'],
                ];
            }
        }

        // Ensure required fields
        $userData['enabled'] = $userData['enabled'] ?? true;
        $userData['emailVerified'] = $userData['emailVerified'] ?? true;

        // Create user in Keycloak
        $keycloakUserId = $this->keycloakService->createUser($userData, $accessToken);

        if (! $keycloakUserId) {
            return [
                'success' => false,
                'message' => 'Kon gebruiker niet aanmaken in Keycloak.',
            ];
        }

        // Set password (always required)
        $passwordSet = $this->keycloakService->setUserPassword(
            $keycloakUserId,
            $password,
            $temporary,
            $accessToken
        );

        if (! $passwordSet) {
            Log::warning('Failed to set password for newly created Keycloak user', [
                'keycloak_user_id' => $keycloakUserId,
                'email'            => $userData['email'] ?? null,
            ]);
            // User is created but password failed - still return success but log warning
        }

        // Assign default role "medewerker" to all users
        $roleAssigned = $this->keycloakService->assignRoleToUser($keycloakUserId, 'medewerker', $accessToken);

        if (! $roleAssigned) {
            Log::warning('Failed to assign role to newly created Keycloak user', [
                'keycloak_user_id' => $keycloakUserId,
                'email'            => $userData['email'] ?? null,
                'role'             => 'medewerker',
            ]);
            // User is created but role assignment failed - still return success but log warning
        }

        Log::info('User added to Keycloak', [
            'keycloak_user_id' => $keycloakUserId,
            'email'            => $userData['email'] ?? null,
            'role_assigned'    => $roleAssigned,
        ]);

        return [
            'success'          => true,
            'keycloak_user_id' => $keycloakUserId,
        ];
    }
}
