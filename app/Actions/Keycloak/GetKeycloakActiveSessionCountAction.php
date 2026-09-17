<?php

namespace App\Actions\Keycloak;

use App\Enums\KeyCloakClient;
use App\Services\Keycloak\KeycloakService;
use Exception;
use Illuminate\Support\Facades\Log;

class GetKeycloakActiveSessionCountAction
{
    public function __construct(
        protected KeycloakService $keycloakService,
    ) {}

    /**
     * Execute the action to get the active session count for a Keycloak client.
     *
     * @return array{success: bool, count?: int, message?: string}
     */
    public function execute(KeyCloakClient $keyCloakClient): array
    {
        $accessToken = $this->keycloakService->getAdminToken();

        if (! $accessToken) {
            return [
                'success' => false,
                'message' => 'Kon niet authenticeren met Keycloak admin.',
            ];
        }

        try {
            $count = $this->keycloakService->getClientSessionCount(
                $keyCloakClient->clientId(),
                $this->keycloakService->getRealm(),
                $accessToken
            );
        } catch (Exception $e) {
            Log::error('Exception getting Keycloak client session count', [
                'client_id' => $keyCloakClient->clientId(),
                'error'     => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Fout bij ophalen sessie-aantal: '.$e->getMessage(),
            ];
        }

        if ($count === null) {
            return [
                'success' => false,
                'message' => "Kon sessie-aantal voor client {$keyCloakClient->clientId()} niet ophalen.",
            ];
        }

        return [
            'success' => true,
            'count'   => $count,
        ];
    }
}
