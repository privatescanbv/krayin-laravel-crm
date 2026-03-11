<?php

namespace App\Services;

use App\Enums\KeyCloakClient;
use App\Support\KeycloakConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class KeycloakTokenExchangeService
{
    public function impersonate(string $keycloakUserId): string
    {
        $realm = KeycloakConfig::realm();
        $tokenUrl = KeycloakConfig::internalUrl(
            "/realms/{$realm}/protocol/openid-connect/token"
        );

        $clientId = KeyCloakClient::PATIENT->clientId();
        $clientSecret = config('services.portal.patient.secret');

        // Step 1: obtain a service account token via client credentials
        // scope=openid is required so the exchanged token also inherits openid scope
        $serviceTokenResponse = Http::asForm()->post($tokenUrl, [
            'grant_type'    => 'client_credentials',
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'scope'         => 'openid',
        ]);

        if (! $serviceTokenResponse->successful()) {
            Log::error('Keycloak client_credentials grant failed', [
                'status' => $serviceTokenResponse->status(),
                'body'   => $serviceTokenResponse->body(),
            ]);
            throw new RuntimeException('Kon geen service account token verkrijgen: '.$serviceTokenResponse->status());
        }

        $serviceToken = $serviceTokenResponse->json('access_token');

        if (empty($serviceToken)) {
            throw new RuntimeException('Keycloak gaf geen service account token terug.');
        }

        // Step 2: token exchange — impersonate the target user
        // scope=openid is required so the resulting token can be validated via the userinfo endpoint
        $response = Http::asForm()->post($tokenUrl, [
            'grant_type'           => 'urn:ietf:params:oauth:grant-type:token-exchange',
            'client_id'            => $clientId,
            'client_secret'        => $clientSecret,
            'subject_token'        => $serviceToken,
            'subject_token_type'   => 'urn:ietf:params:oauth:token-type:access_token',
            'requested_subject'    => $keycloakUserId,
            'requested_token_type' => 'urn:ietf:params:oauth:token-type:access_token',
            'scope'                => 'openid',
        ]);

        if (! $response->successful()) {
            Log::error('Keycloak token exchange failed', [
                'keycloak_user_id' => $keycloakUserId,
                'status'           => $response->status(),
                'body'             => $response->body(),
            ]);
            throw new RuntimeException('Keycloak token exchange mislukt: '.$response->status());
        }

        $token = $response->json('access_token');

        if (empty($token)) {
            throw new RuntimeException('Keycloak gaf geen access token terug bij token exchange.');
        }

        return $token;
    }
}
