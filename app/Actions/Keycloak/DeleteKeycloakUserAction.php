<?php

namespace App\Actions\Keycloak;

use App\Services\KeycloakService;
use Illuminate\Support\Facades\Log;

class DeleteKeycloakUserAction
{
    public function __construct(
        protected KeycloakService $keycloakService
    ) {}

    /**
     * Execute the action to delete a user from Keycloak.
     *
     * @param  string  $keycloakUserId  Keycloak user ID
     * @return array{success: bool, message?: string}
     */
    public function execute(string $keycloakUserId): array
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

        // Delete user
        $deleted = $this->keycloakService->deleteUser($keycloakUserId, $accessToken);

        if (! $deleted) {
            return [
                'success' => false,
                'message' => 'Kon gebruiker niet verwijderen uit Keycloak.',
            ];
        }

        Log::info('User deleted from Keycloak', [
            'keycloak_user_id' => $keycloakUserId,
            'email'            => $existingUser['email'] ?? null,
        ]);

        return [
            'success' => true,
        ];
    }
}
