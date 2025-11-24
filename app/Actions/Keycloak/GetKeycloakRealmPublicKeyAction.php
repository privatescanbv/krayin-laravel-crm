<?php

namespace App\Actions\Keycloak;

use App\Services\KeycloakService;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GetKeycloakRealmPublicKeyAction
{
    public function __construct(
        protected KeycloakService $keycloakService
    ) {}

    /**
     * Execute the action to get the realm public key from Keycloak.
     *
     * @return array{success: bool, public_key?: string, realm?: string, message?: string}
     */
    public function execute(): array
    {
        $realm = $this->keycloakService->getRealm();

        if (empty($realm)) {
            return [
                'success' => false,
                'message' => 'KEYCLOAK_REALM is niet geconfigureerd in .env.',
            ];
        }

        $realmUrl = rtrim($this->keycloakService->getInternalBaseUrl(), '/').'/realms/'.$realm;

        try {
            $response = Http::get($realmUrl);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'message' => 'Kon realm public key niet ophalen: '.$response->status().' - '.$response->body(),
                ];
            }

            $publicKey = $response->json('public_key');

            if (empty($publicKey)) {
                return [
                    'success' => false,
                    'message' => 'Realm public key niet gevonden in Keycloak response.',
                ];
            }

            Log::info('Keycloak realm public key retrieved', [
                'realm' => $realm,
            ]);

            return [
                'success'    => true,
                'public_key' => $publicKey,
                'realm'      => $realm,
            ];
        } catch (Exception $e) {
            Log::error('Exception getting Keycloak realm public key', [
                'realm' => $realm,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Fout bij ophalen realm public key: '.$e->getMessage(),
            ];
        }
    }
}
