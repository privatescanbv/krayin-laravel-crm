<?php

namespace App\Actions\Keycloak;

use App\Enums\KeycloakRoles;
use App\Services\Keycloak\KeycloakService;
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
     * @param  string|null  $role  Optional realm role to assign (defaults to employee)
     * @return array{success: bool, keycloak_user_id?: string, message?: string}
     */
    public function execute(array $userData, string $password, bool $temporary = false, ?string $role = null): array
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

        $roleAssigned = null;
        if ($role) {
            $roleAssigned = $this->keycloakService->assignRoleToUser($keycloakUserId, $role, $accessToken);
        } else {
            // Default to employee role if no role specified
            $roleAssigned = $this->keycloakService->assignRoleToUser($keycloakUserId, KeycloakRoles::Employee->value, $accessToken);

            if (! $roleAssigned) {
                Log::warning('Failed to assign role to newly created Keycloak user', [
                    'keycloak_user_id' => $keycloakUserId,
                    'email'            => $userData['email'] ?? null,
                    'role'             => $role,
                ]);
                // User is created but role assignment failed - still return success but log warning
            }
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
