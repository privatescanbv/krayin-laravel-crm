<?php

namespace App\Actions\Keycloak;

use App\Services\Keycloak\KeycloakService;
use Illuminate\Support\Facades\Log;

class SetKeycloakUserPasswordAction
{
    public function __construct(
        protected KeycloakService $keycloakService
    ) {}

    /**
     * Execute the action to set password for a Keycloak user.
     *
     * @return array{success: bool, message?: string, keycloak_user_id?: string}
     */
    public function execute(string $email, string $password, bool $temporary = false): array
    {
        $accessToken = $this->keycloakService->getAdminToken();

        if (! $accessToken) {
            return [
                'success' => false,
                'message' => 'Kon niet authenticeren met Keycloak admin.',
            ];
        }

        // Find user in Keycloak
        $keycloakUser = $this->keycloakService->getUserByEmail($email, $accessToken);

        if (! $keycloakUser) {
            return [
                'success' => false,
                'message' => "Gebruiker {$email} niet gevonden in Keycloak.",
            ];
        }

        // Set password
        $passwordSet = $this->keycloakService->setUserPassword(
            $keycloakUser['id'],
            $password,
            $temporary,
            $accessToken
        );

        if (! $passwordSet) {
            return [
                'success'          => false,
                'message'          => "Kon wachtwoord niet instellen voor gebruiker: {$email}",
                'keycloak_user_id' => $keycloakUser['id'],
            ];
        }

        Log::info('Password set for Keycloak user', [
            'email'            => $email,
            'keycloak_user_id' => $keycloakUser['id'],
            'temporary'        => $temporary,
        ]);

        return [
            'success'          => true,
            'keycloak_user_id' => $keycloakUser['id'],
        ];
    }
}
