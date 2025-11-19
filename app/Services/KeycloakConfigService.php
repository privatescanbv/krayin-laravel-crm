<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KeycloakConfigService
{
    private bool $extraLogging = false;

    public function __construct(
        protected KeycloakService $keycloakService
    ) {}

    /**
     * Sync Keycloak configuration (realm and client).
     * Idempotent: can be run multiple times safely.
     */
    public function syncConfig(): array
    {
        $results = [
            'realm_created'  => false,
            'realm_exists'   => false,
            'client_created' => false,
            'client_exists'  => false,
            'client_updated' => false,
            'client_secret'  => null,
            'errors'         => [],
        ];

        // Check if Keycloak is configured
        if (! $this->isKeycloakConfigured()) {
            Log::warning('Keycloak config sync skipped: Keycloak not configured');

            return $results;
        }

        $realmName = $this->keycloakService->getRealm();
        $clientId = $this->keycloakService->getClientId();
        $baseUrl = $this->keycloakService->getBaseUrl();

        if (empty($realmName) || empty($clientId)) {
            Log::warning('Keycloak config sync skipped: realm or client_id not configured');

            return $results;
        }

        // Get admin token
        $accessToken = $this->keycloakService->getAdminToken();

        if (! $accessToken) {
            $error = 'Kon niet authenticeren met Keycloak admin.';
            $results['errors'][] = $error;
            Log::error('Keycloak config sync failed', ['error' => $error]);

            return $results;
        }

        // Check and create realm
        if ($this->keycloakService->realmExists($realmName, $accessToken)) {
            $results['realm_exists'] = true;
        } else {
            // Create realm with default configuration
            // Note: frontendUrl will be set after realm creation via update
            $realmData = [];

            if ($this->keycloakService->createRealm($realmName, $realmData, $accessToken)) {
                // Set frontend URL to base URL (localhost) so tokens use localhost as issuer
                $realmUrl = $this->keycloakService->getDockerServiceUrl().'/admin/realms/'.$realmName;
                try {
                    Http::asJson()
                        ->withToken($accessToken)
                        ->put($realmUrl, ['frontendUrl' => $baseUrl]);
                    Log::info('Keycloak realm frontend URL set', [
                        'realm'        => $realmName,
                        'frontend_url' => $baseUrl,
                    ]);
                } catch (Exception $e) {
                    Log::warning('Failed to set frontend URL for realm', [
                        'realm' => $realmName,
                        'error' => $e->getMessage(),
                    ]);
                }
                $results['realm_created'] = true;
                Log::info('Keycloak realm created', ['realm' => $realmName]);
            } else {
                $error = "Kon realm {$realmName} niet aanmaken.";
                $results['errors'][] = $error;
                Log::error('Keycloak config sync failed', ['error' => $error]);

                return $results; // Can't continue without realm
            }
        }

        // Check and create/update client
        $client = $this->keycloakService->getClientById($clientId, $realmName, $accessToken);

        if ($client) {
            $results['client_exists'] = true;
            if ($this->extraLogging) {
                Log::info('Keycloak client exists', ['client_id' => $clientId, 'realm' => $realmName]);
            }

            // Update client configuration if needed
            $clientData = $this->getDefaultClientConfig();
            $needsUpdate = false;
            $updateData = [];

            // Check if redirect URIs need updating
            $currentRedirectUris = $client['redirectUris'] ?? [];
            $expectedRedirectUris = $clientData['redirectUris'] ?? [];

            if (array_diff($expectedRedirectUris, $currentRedirectUris) || array_diff($currentRedirectUris, $expectedRedirectUris)) {
                $updateData['redirectUris'] = $expectedRedirectUris;
                $needsUpdate = true;
            }

            // Check if post logout redirect URIs need updating
            $currentPostLogoutRedirectUris = $client['attributes']['post.logout.redirect.uris'] ?? '';
            $currentPostLogoutRedirectUrisArray = ! empty($currentPostLogoutRedirectUris)
                ? explode(',', $currentPostLogoutRedirectUris)
                : [];
            $expectedPostLogoutRedirectUris = $clientData['attributes']['post.logout.redirect.uris'] ?? '';

            if (array_diff($expectedPostLogoutRedirectUris ? explode(',', $expectedPostLogoutRedirectUris) : [], $currentPostLogoutRedirectUrisArray) ||
                array_diff($currentPostLogoutRedirectUrisArray, $expectedPostLogoutRedirectUris ? explode(',', $expectedPostLogoutRedirectUris) : [])) {
                if (! isset($updateData['attributes'])) {
                    $updateData['attributes'] = $client['attributes'] ?? [];
                }
                $updateData['attributes']['post.logout.redirect.uris'] = $expectedPostLogoutRedirectUris;
                $needsUpdate = true;
            }

            // Check if base URL needs updating
            if (($client['baseUrl'] ?? '') !== $clientData['baseUrl']) {
                $updateData['baseUrl'] = $clientData['baseUrl'];
                $needsUpdate = true;
            }

            // Check if root URL needs updating
            if (($client['rootUrl'] ?? '') !== $clientData['rootUrl']) {
                $updateData['rootUrl'] = $clientData['rootUrl'];
                $needsUpdate = true;
            }

            // Check if web origins need updating
            $currentWebOrigins = $client['webOrigins'] ?? [];
            $expectedWebOrigins = $clientData['webOrigins'] ?? [];
            if (array_diff($expectedWebOrigins, $currentWebOrigins) || array_diff($currentWebOrigins, $expectedWebOrigins)) {
                $updateData['webOrigins'] = $expectedWebOrigins;
                $needsUpdate = true;
            }

            // Check if home URL needs updating
            $currentHomeUrl = $client['attributes']['home.url'] ?? '';
            $expectedHomeUrl = $clientData['attributes']['home.url'] ?? '';
            if ($currentHomeUrl !== $expectedHomeUrl) {
                if (! isset($updateData['attributes'])) {
                    $updateData['attributes'] = $client['attributes'] ?? [];
                }
                $updateData['attributes']['home.url'] = $expectedHomeUrl;
                $needsUpdate = true;
            }

            // Check if backchannel logout URL needs updating
            $currentBackchannelLogoutUrl = $client['attributes']['backchannel.logout.url'] ?? '';
            $expectedBackchannelLogoutUrl = $clientData['attributes']['backchannel.logout.url'] ?? '';
            if ($currentBackchannelLogoutUrl !== $expectedBackchannelLogoutUrl) {
                if (! isset($updateData['attributes'])) {
                    $updateData['attributes'] = $client['attributes'] ?? [];
                }
                $updateData['attributes']['backchannel.logout.url'] = $expectedBackchannelLogoutUrl;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                if ($this->keycloakService->updateClient($realmName, $clientId, $updateData, $accessToken)) {
                    $results['client_updated'] = true;
                    Log::info('Keycloak client updated', ['client_id' => $clientId, 'realm' => $realmName]);
                } else {
                    $error = "Kon client {$clientId} niet updaten.";
                    $results['errors'][] = $error;
                    Log::error('Keycloak config sync failed', ['error' => $error]);
                }
            }
        } else {
            // Create client with default configuration
            $clientData = $this->getDefaultClientConfig();
            $clientData['clientId'] = $clientId;

            if ($this->keycloakService->createClient($realmName, $clientData, $accessToken)) {
                $results['client_created'] = true;
                Log::info('Keycloak client created', ['client_id' => $clientId, 'realm' => $realmName]);

                // After creating, we need to get the client secret
                // This requires an additional API call to get the client details
                $createdClient = $this->keycloakService->getClientById($clientId, $realmName, $accessToken);

                if ($createdClient) {
                    // Get client secret
                    $clientSecretUrl = $this->keycloakService->getDockerServiceUrl()
                        .'/admin/realms/'.$realmName
                        .'/clients/'.$createdClient['id'].'/client-secret';

                    try {
                        $secretResponse = Http::withToken($accessToken)->get($clientSecretUrl);

                        if ($secretResponse->successful()) {
                            $secret = $secretResponse->json('value');
                            $results['client_secret'] = $secret;
                            Log::info('Keycloak client secret retrieved', [
                                'client_id' => $clientId,
                                'realm'     => $realmName,
                            ]);
                            // Note: The secret should be stored in .env, but we can't modify that automatically
                            // Log a warning to remind the user
                            Log::warning('Keycloak client secret generated. Please update KEYCLOAK_CLIENT_SECRET in .env', [
                                'client_id' => $clientId,
                                'secret'    => $secret,
                            ]);
                        }
                    } catch (Exception $e) {
                        Log::warning('Could not retrieve client secret', [
                            'error'     => $e->getMessage(),
                            'client_id' => $clientId,
                        ]);
                    }
                }
            } else {
                $error = "Kon client {$clientId} niet aanmaken.";
                $results['errors'][] = $error;
                Log::error('Keycloak config sync failed', ['error' => $error]);
            }
        }

        return $results;
    }

    /**
     * Get default client configuration.
     */
    protected function getDefaultClientConfig(): array
    {
        // Get app URL from config, ensure it has port if localhost
        $appUrl = config('app.url', 'http://localhost:8000');

        // Parse URL to ensure we have proper format
        $parsedUrl = parse_url($appUrl);
        if (! $parsedUrl) {
            $appUrl = 'http://localhost:8000';
            $parsedUrl = parse_url($appUrl);
        }

        // Reconstruct URL with port if missing
        $scheme = $parsedUrl['scheme'] ?? 'http';
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? null;

        // If no port and host is localhost, default to 8000
        if ($port === null && ($host === 'localhost' || $host === '127.0.0.1')) {
            $port = 8000;
        }

        // Reconstruct URL with port
        $appUrl = $scheme.'://'.$host.($port ? ':'.$port : '');

        // Build redirect URIs - include specific ones and wildcard pattern
        $redirectUris = [
            $appUrl.'/admin/auth/keycloak/callback',
            $appUrl.'/admin/auth/keycloak/logout-callback',
        ];

        // Add wildcard pattern for admin routes
        $redirectUris[] = '/admin/*';

        return [
            'enabled'                      => true,
            'protocol'                     => 'openid-connect',
            'publicClient'                 => false,
            'standardFlowEnabled'          => true,
            'implicitFlowEnabled'          => false,
            'directAccessGrantsEnabled'    => true,
            'serviceAccountsEnabled'       => false,
            'authorizationServicesEnabled' => false,
            'rootUrl'                      => $appUrl,
            'baseUrl'                      => $appUrl,
            'redirectUris'                 => $redirectUris,
            'webOrigins'                   => [$appUrl],
            'attributes'                   => [
                'post.logout.redirect.uris' => implode(',', [
                    $appUrl.'/admin/login',
                    $appUrl.'/admin/auth/keycloak/logout-callback',
                ]),
                'backchannel.logout.url'     => $appUrl.'/admin/auth/keycloak/backchannel-logout',
                'home.url'                   => $appUrl,
            ],
            // Note: Keycloak stores post.logout.redirect.uris as comma-separated string in attributes,
            // but the UI displays them as separate fields. This is correct and works properly.
        ];
    }

    /**
     * Check if Keycloak is configured.
     */
    protected function isKeycloakConfigured(): bool
    {
        return ! empty(Config::get('services.keycloak.client_id'));
    }
}
