<?php

namespace App\Actions\Keycloak;

use App\Services\KeycloakService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetKeycloakClientSecretAction
{
    public function __construct(
        protected KeycloakService $keycloakService
    ) {}

    /**
     * Execute the action to get the client secret for a Keycloak client.
     *
     * @param  string|null  $clientId  The client ID (default: from config)
     * @return array{success: bool, secret?: string, env_key?: string, message?: string}
     */
    public function execute(?string $clientId = null): array
    {
        $clientId = $clientId ?? $this->keycloakService->getClientId();
        $realmName = $this->keycloakService->getRealm();

        if (empty($clientId)) {
            return [
                'success' => false,
                'message' => 'Client ID is required. Either provide it as an argument or set KEYCLOAK_CLIENT_ID in .env',
            ];
        }

        // Get admin token
        $accessToken = $this->keycloakService->getAdminToken();

        if (! $accessToken) {
            return [
                'success' => false,
                'message' => 'Kon niet authenticeren met Keycloak admin. Check KEYCLOAK_ADMIN en KEYCLOAK_ADMIN_PASSWORD.',
            ];
        }

        // Get client
        $client = $this->keycloakService->getClientById($clientId, $realmName, $accessToken);

        if (! $client) {
            return [
                'success' => false,
                'message' => "Client {$clientId} niet gevonden in realm {$realmName}.",
            ];
        }

        // Get client secret
        $clientSecretUrl = $this->keycloakService->getDockerServiceUrl()
            .'/admin/realms/'.$realmName
            .'/clients/'.$client['id'].'/client-secret';

        try {
            $response = Http::withToken($accessToken)->get($clientSecretUrl);

            if ($response->successful()) {
                $secret = $response->json('value');

                if ($secret) {
                    // Determine env key based on client ID
                    $envKey = $clientId === 'forms-app' ? 'FORMS_KEYCLOAK_CLIENT_SECRET' : 'KEYCLOAK_CLIENT_SECRET';

                    Log::info('Keycloak client secret retrieved', [
                        'client_id' => $clientId,
                        'realm'     => $realmName,
                    ]);

                    return [
                        'success'   => true,
                        'secret'    => $secret,
                        'env_key'   => $envKey,
                        'client_id' => $clientId,
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Client secret is leeg of niet gevonden.',
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'message' => 'Kon client secret niet ophalen: '.$response->status().' - '.$response->body(),
                ];
            }
        } catch (Exception $e) {
            Log::error('Exception getting Keycloak client secret', [
                'client_id' => $clientId,
                'error'     => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Fout bij ophalen client secret: '.$e->getMessage(),
            ];
        }
    }
}
