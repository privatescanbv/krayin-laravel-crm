<?php

namespace App\Actions\Keycloak;

use App\Services\Keycloak\KeycloakService;
use Illuminate\Support\Facades\Log;

class UpdateKeycloakUserAction
{
    public function __construct(
        protected KeycloakService $keycloakService
    ) {}

    /**
     * Execute the action to update a Keycloak user.
     *
     * @param  string  $keycloakUserId  Keycloak user ID
     * @param  array  $userData  User data to update (email, firstName, lastName, enabled, etc.)
     * @param  string|null  $password  New password (optional, but recommended)
     * @param  bool  $temporary  Whether password is temporary (only used if password is provided)
     * @return array{success: bool, message?: string}
     */
    public function execute(string $keycloakUserId, array $userData, ?string $password = null, bool $temporary = false): array
    {
        $accessToken = $this->keycloakService->getAdminToken();

        if (! $accessToken) {
            return [
                'success' => false,
                'message' => 'Kon niet authenticeren met Keycloak admin.',
            ];
        }

        // Check if user exists
        $existingUser = $this->keycloakService->getUserById($keycloakUserId, $accessToken);
        if (! $existingUser) {
            return [
                'success' => false,
                'message' => "Gebruiker met ID {$keycloakUserId} niet gevonden in Keycloak.",
            ];
        }

        // Update user data (if any)
        if (! empty($userData)) {
            $updated = $this->keycloakService->updateUser($keycloakUserId, $userData, $accessToken);

            if (! $updated) {
                return [
                    'success' => false,
                    'message' => 'Kon gebruiker niet updaten in Keycloak.',
                ];
            }
        }

        // Update password if provided
        if ($password !== null) {
            $passwordSet = $this->keycloakService->setUserPassword(
                $keycloakUserId,
                $password,
                $temporary,
                $accessToken
            );

            if (! $passwordSet) {
                Log::warning('Failed to set password for updated Keycloak user', [
                    'keycloak_user_id' => $keycloakUserId,
                ]);
                // User is updated but password failed - still return success but log warning
            }
        }

        Log::info('User updated in Keycloak', [
            'keycloak_user_id' => $keycloakUserId,
            'updated_fields'   => array_keys($userData),
            'password_updated' => $password !== null,
        ]);

        return [
            'success' => true,
        ];
    }
}
