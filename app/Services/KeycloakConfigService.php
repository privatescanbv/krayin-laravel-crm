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
     * Sync Keycloak configuration (realm and clients).
     * Idempotent: can be run multiple times safely.
     */
    public function syncConfig(): array
    {
        $results = [
            'realm_created' => false,
            'realm_exists'  => false,
            'clients'       => [],
            'roles_created' => [],
            'errors'        => [],
        ];

        // Check if Keycloak is configured
        if (! $this->isKeycloakConfigured()) {
            Log::warning('Keycloak config sync skipped: Keycloak not configured');

            return $results;
        }

        $realmName = $this->keycloakService->getRealm();
        $baseUrl = $this->keycloakService->getBaseUrl();

        if (empty($realmName)) {
            Log::warning('Keycloak config sync skipped: realm not configured');

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

        // Get client configurations
        $clientConfigs = $this->getClientConfigs();

        // Sync each client
        foreach ($clientConfigs as $clientId => $clientConfig) {
            $clientResult = $this->syncClient($clientId, $clientConfig, $realmName, $accessToken);
            $results['clients'][$clientId] = $clientResult;

            if (! empty($clientResult['errors'])) {
                $results['errors'] = array_merge($results['errors'], $clientResult['errors']);
            }
        }

        // Sync roles
        $rolesResult = $this->syncRoles($accessToken);
        $results['roles_created'] = $rolesResult['created'];
        if (! empty($rolesResult['errors'])) {
            $results['errors'] = array_merge($results['errors'], $rolesResult['errors']);
        }

        return $results;
    }

    /**
     * Get client configurations for all clients.
     *
     * @return array<string, array> Array of client configurations keyed by client ID
     */
    protected function getClientConfigs(): array
    {
        $configs = [];

        // CRM client configuration
        $crmClientId = $this->keycloakService->getClientId();
        if (! empty($crmClientId)) {
            $appUrl = $this->normalizeUrl(config('app.url', 'http://localhost:8000'), 8000);
            $configs[$crmClientId] = [
                'base_url'                  => $appUrl,
                'redirect_uris'             => [
                    $appUrl.'/admin/auth/keycloak/callback',
                    $appUrl.'/admin/auth/keycloak/logout-callback',
                    '/admin/*', // Wildcard pattern
                ],
                'post_logout_redirect_uris' => [
                    $appUrl.'/admin/login',
                    $appUrl.'/admin/auth/keycloak/logout-callback',
                ],
                'backchannel_logout_url'    => $appUrl.'/admin/auth/keycloak/backchannel-logout',
                'home_url'                  => $appUrl,
                'secret_env_key'            => 'KEYCLOAK_CLIENT_SECRET',
                'secret_log_message'        => 'Keycloak client secret generated. Please update KEYCLOAK_CLIENT_SECRET in .env',
            ];
        }

        // Forms client configuration
        $formsUrl = $this->normalizeUrl(config('services.forms.frontend_url', 'http://localhost:8001'), 8001);
        $configs['forms-app'] = [
            'base_url'                  => $formsUrl,
            'redirect_uris'             => [
                $formsUrl.'/*',
            ],
            'post_logout_redirect_uris' => [
                $formsUrl.'/*',
            ],
            'backchannel_logout_url'    => '',
            'home_url'                  => $formsUrl,
            'secret_env_key'            => 'FORMS_KEYCLOAK_CLIENT_SECRET',
            'secret_log_message'        => 'Keycloak Forms client secret generated. Please update FORMS_KEYCLOAK_CLIENT_SECRET in Forms .env',
        ];
        // Note: backchannel_logout_url ->je Laravel app moet een endpoint hebben dat dit verwerkt.
        // De Vizir package levert dat NIET automatisch → je zou dit zelf moeten implementeren.

        return $configs;
    }

    /**
     * Sync a single client configuration.
     *
     * @param  string  $clientId  The client ID
     * @param  array  $config  Client configuration array with keys:
     *                         - base_url: Base URL for the client
     *                         - redirect_uris: Array of redirect URIs
     *                         - post_logout_redirect_uris: Array of post-logout redirect URIs
     *                         - backchannel_logout_url: Backchannel logout URL
     *                         - home_url: Home URL
     *                         - secret_env_key: Environment variable key for the secret (optional)
     *                         - secret_log_message: Log message for secret generation (optional)
     * @param  string  $realmName  The realm name
     * @param  string  $accessToken  Admin access token
     * @return array{created: bool, exists: bool, updated: bool, secret: string|null, errors: array}
     */
    protected function syncClient(string $clientId, array $config, string $realmName, string $accessToken): array
    {
        $results = [
            'created' => false,
            'exists'  => false,
            'updated' => false,
            'secret'  => null,
            'errors'  => [],
        ];

        $existingClient = $this->keycloakService->getClientById($clientId, $realmName, $accessToken);

        // Build expected client configuration
        $expectedConfig = $this->buildClientConfig($config);

        if ($existingClient) {
            $results['exists'] = true;

            if ($this->extraLogging) {
                Log::info('Keycloak client exists', ['client_id' => $clientId, 'realm' => $realmName]);
            }

            // Check if update is needed
            $updateData = $this->getClientUpdateData($existingClient, $expectedConfig);

            if (! empty($updateData)) {
                if ($this->keycloakService->updateClient($realmName, $clientId, $updateData, $accessToken)) {
                    $results['updated'] = true;
                    Log::info('Keycloak client updated', ['client_id' => $clientId, 'realm' => $realmName]);
                } else {
                    $error = "Kon client {$clientId} niet updaten.";
                    $results['errors'][] = $error;
                    Log::error('Keycloak client update failed', ['client_id' => $clientId, 'error' => $error]);
                }
            }

            // Always try to get the secret for existing clients (useful for production setup)
            $secretResult = $this->getClientSecret($clientId, $realmName, $accessToken);
            if ($secretResult['secret']) {
                $results['secret'] = $secretResult['secret'];
                $secretEnvKey = $config['secret_env_key'] ?? 'KEYCLOAK_CLIENT_SECRET';

                // Get configured secret from config
                $configuredSecret = $this->getConfiguredSecret($clientId, $secretEnvKey);

                // Compare secrets and log error if they don't match
                if ($configuredSecret !== null && $configuredSecret !== $secretResult['secret']) {
                    Log::error('Keycloak client secret mismatch', [
                        'client_id'          => $clientId,
                        'keycloak_secret'    => $secretResult['secret'],
                        'configured_secret'  => $configuredSecret,
                        'env_key'            => $secretEnvKey,
                        'message'            => "Client secret in Keycloak does not match {$secretEnvKey} in .env/config. Update {$secretEnvKey} to match Keycloak or regenerate the secret in Keycloak.",
                    ]);
                } else {
                    $secretLogMessage = $config['secret_log_message'] ?? "Keycloak client secret retrieved. Update {$secretEnvKey} in .env if needed";

                    // Log::info($secretLogMessage, [
                    //     'client_id' => $clientId,
                    //     'secret'    => $secretResult['secret'],
                    // ]);
                }
            }
        } else {
            // Create client
            $clientData = $expectedConfig;
            $clientData['clientId'] = $clientId;

            if ($this->keycloakService->createClient($realmName, $clientData, $accessToken)) {
                $results['created'] = true;
                Log::info('Keycloak client created', ['client_id' => $clientId, 'realm' => $realmName]);

                // Get client secret
                $secretResult = $this->getClientSecret($clientId, $realmName, $accessToken);
                $results['secret'] = $secretResult['secret'];

                if ($secretResult['secret']) {
                    $secretEnvKey = $config['secret_env_key'] ?? 'KEYCLOAK_CLIENT_SECRET';
                    $secretLogMessage = $config['secret_log_message'] ?? "Keycloak client secret generated. Please update {$secretEnvKey} in .env";

                    Log::warning($secretLogMessage, [
                        'client_id' => $clientId,
                        'secret'    => $secretResult['secret'],
                    ]);
                }
            } else {
                $error = "Kon client {$clientId} niet aanmaken.";
                $results['errors'][] = $error;
                Log::error('Keycloak client creation failed', ['client_id' => $clientId, 'error' => $error]);
            }
        }

        return $results;
    }

    /**
     * Build client configuration array from config parameters.
     *
     * @param  array  $config  Configuration array with keys:
     *                         - base_url: Base URL
     *                         - redirect_uris: Array of redirect URIs
     *                         - post_logout_redirect_uris: Array of post-logout redirect URIs
     *                         - backchannel_logout_url: Backchannel logout URL
     *                         - home_url: Home URL
     * @return array Client configuration for Keycloak API
     */
    protected function buildClientConfig(array $config): array
    {
        $baseUrl = $config['base_url'];
        $redirectUris = $config['redirect_uris'] ?? [];
        $postLogoutRedirectUris = $config['post_logout_redirect_uris'] ?? [];
        $backchannelLogoutUrl = $config['backchannel_logout_url'] ?? '';
        $homeUrl = $config['home_url'] ?? $baseUrl;

        return [
            'enabled'                      => true,
            'protocol'                     => 'openid-connect',
            'publicClient'                 => false,
            'standardFlowEnabled'          => true,
            'implicitFlowEnabled'          => false,
            'directAccessGrantsEnabled'    => true,
            'serviceAccountsEnabled'       => false,
            'authorizationServicesEnabled' => false,
            'rootUrl'                      => $baseUrl,
            'baseUrl'                      => $baseUrl,
            'redirectUris'                 => $redirectUris,
            'webOrigins'                   => [$baseUrl],
            'attributes'                   => [
                'post.logout.redirect.uris'  => implode(',', $postLogoutRedirectUris),
                'backchannel.logout.url'     => $backchannelLogoutUrl,
                'home.url'                   => $homeUrl,
            ],
        ];
    }

    /**
     * Get update data for a client by comparing existing and expected configuration.
     *
     * @param  array  $existingClient  Existing client data from Keycloak
     * @param  array  $expectedConfig  Expected client configuration
     * @return array Update data (empty if no update needed)
     */
    protected function getClientUpdateData(array $existingClient, array $expectedConfig): array
    {
        $updateData = [];

        // Check redirect URIs
        $currentRedirectUris = $existingClient['redirectUris'] ?? [];
        $expectedRedirectUris = $expectedConfig['redirectUris'] ?? [];
        if (array_diff($expectedRedirectUris, $currentRedirectUris) || array_diff($currentRedirectUris, $expectedRedirectUris)) {
            $updateData['redirectUris'] = $expectedRedirectUris;
        }

        // Check post logout redirect URIs
        $currentPostLogoutRedirectUris = $existingClient['attributes']['post.logout.redirect.uris'] ?? '';
        $currentPostLogoutRedirectUrisArray = ! empty($currentPostLogoutRedirectUris)
            ? explode(',', $currentPostLogoutRedirectUris)
            : [];
        $expectedPostLogoutRedirectUris = $expectedConfig['attributes']['post.logout.redirect.uris'] ?? '';
        $expectedPostLogoutRedirectUrisArray = ! empty($expectedPostLogoutRedirectUris)
            ? explode(',', $expectedPostLogoutRedirectUris)
            : [];

        if (array_diff($expectedPostLogoutRedirectUrisArray, $currentPostLogoutRedirectUrisArray) ||
            array_diff($currentPostLogoutRedirectUrisArray, $expectedPostLogoutRedirectUrisArray)) {
            if (! isset($updateData['attributes'])) {
                $updateData['attributes'] = $existingClient['attributes'] ?? [];
            }
            $updateData['attributes']['post.logout.redirect.uris'] = $expectedPostLogoutRedirectUris;
        }

        // Check base URL
        if (($existingClient['baseUrl'] ?? '') !== ($expectedConfig['baseUrl'] ?? '')) {
            $updateData['baseUrl'] = $expectedConfig['baseUrl'];
        }

        // Check root URL
        if (($existingClient['rootUrl'] ?? '') !== ($expectedConfig['rootUrl'] ?? '')) {
            $updateData['rootUrl'] = $expectedConfig['rootUrl'];
        }

        // Check web origins
        $currentWebOrigins = $existingClient['webOrigins'] ?? [];
        $expectedWebOrigins = $expectedConfig['webOrigins'] ?? [];
        if (array_diff($expectedWebOrigins, $currentWebOrigins) || array_diff($currentWebOrigins, $expectedWebOrigins)) {
            $updateData['webOrigins'] = $expectedWebOrigins;
        }

        // Check home URL
        $currentHomeUrl = $existingClient['attributes']['home.url'] ?? '';
        $expectedHomeUrl = $expectedConfig['attributes']['home.url'] ?? '';
        if ($currentHomeUrl !== $expectedHomeUrl) {
            if (! isset($updateData['attributes'])) {
                $updateData['attributes'] = $existingClient['attributes'] ?? [];
            }
            $updateData['attributes']['home.url'] = $expectedHomeUrl;
        }

        // Check backchannel logout URL
        $currentBackchannelLogoutUrl = $existingClient['attributes']['backchannel.logout.url'] ?? '';
        $expectedBackchannelLogoutUrl = $expectedConfig['attributes']['backchannel.logout.url'] ?? '';
        if ($currentBackchannelLogoutUrl !== $expectedBackchannelLogoutUrl) {
            if (! isset($updateData['attributes'])) {
                $updateData['attributes'] = $existingClient['attributes'] ?? [];
            }
            $updateData['attributes']['backchannel.logout.url'] = $expectedBackchannelLogoutUrl;
        }

        return $updateData;
    }

    /**
     * Get client secret from Keycloak.
     *
     * @param  string  $clientId  The client ID
     * @param  string  $realmName  The realm name
     * @param  string  $accessToken  Admin access token
     * @return array{secret: string|null, error: string|null}
     */
    protected function getClientSecret(string $clientId, string $realmName, string $accessToken): array
    {
        $createdClient = $this->keycloakService->getClientById($clientId, $realmName, $accessToken);

        if (! $createdClient) {
            return ['secret' => null, 'error' => 'Client not found after creation'];
        }

        $clientSecretUrl = $this->keycloakService->getDockerServiceUrl()
            .'/admin/realms/'.$realmName
            .'/clients/'.$createdClient['id'].'/client-secret';

        try {
            $secretResponse = Http::withToken($accessToken)->get($clientSecretUrl);

            if ($secretResponse->successful()) {
                $secret = $secretResponse->json('value');
                Log::info('Keycloak client secret retrieved', [
                    'client_id' => $clientId,
                    'realm'     => $realmName,
                ]);

                return ['secret' => $secret, 'error' => null];
            }

            return ['secret' => null, 'error' => 'Failed to retrieve secret: '.$secretResponse->status()];
        } catch (Exception $e) {
            Log::warning('Could not retrieve client secret', [
                'error'     => $e->getMessage(),
                'client_id' => $clientId,
            ]);

            return ['secret' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Normalize URL: ensure it has a port if localhost.
     *
     * @param  string  $url  URL to normalize
     * @param  int  $defaultPort  Default port for localhost
     * @return string Normalized URL
     */
    protected function normalizeUrl(string $url, int $defaultPort): string
    {
        $parsedUrl = parse_url($url);
        if (! $parsedUrl) {
            $url = "http://localhost:{$defaultPort}";
            $parsedUrl = parse_url($url);
        }

        $scheme = $parsedUrl['scheme'] ?? 'http';
        $host = $parsedUrl['host'] ?? 'localhost';
        $port = $parsedUrl['port'] ?? null;

        // If no port and host is localhost, use default port
        if ($port === null && ($host === 'localhost' || $host === '127.0.0.1')) {
            $port = $defaultPort;
        }

        return $scheme.'://'.$host.($port ? ':'.$port : '');
    }

    /**
     * Sync Keycloak realm roles.
     * Creates roles if they don't exist.
     */
    protected function syncRoles(?string $accessToken = null): array
    {
        $results = [
            'created' => [],
            'errors'  => [],
        ];

        $roles = [
            [
                'name'        => 'medewerker',
                'description' => 'Medewerker rol voor CRM gebruikers',
            ],
            [
                'name'        => 'patient',
                'description' => 'Patient rol',
            ],
        ];

        foreach ($roles as $roleData) {
            $roleName = $roleData['name'];

            // Check if role exists
            $existingRole = $this->keycloakService->getRoleByName($roleName, $accessToken);

            if (! $existingRole) {
                // Create role
                if ($this->keycloakService->createRole($roleName, $roleData, $accessToken)) {
                    $results['created'][] = $roleName;
                    Log::info('Keycloak role created', ['role' => $roleName]);
                } else {
                    $error = "Kon rol {$roleName} niet aanmaken.";
                    $results['errors'][] = $error;
                    Log::error('Keycloak role creation failed', ['role' => $roleName, 'error' => $error]);
                }
            }
        }

        return $results;
    }

    /**
     * Get configured secret from config or env.
     *
     * @param  string  $clientId  The client ID
     * @param  string  $envKey  The environment variable key
     * @return string|null The configured secret, or null if not set
     */
    protected function getConfiguredSecret(string $clientId, string $envKey): ?string
    {
        // For CRM client, get from config
        if ($clientId === $this->keycloakService->getClientId()) {
            return Config::get('services.keycloak.client_secret');
        }

        // For other clients (like forms-app), get from env directly
        // Forms doesn't have a config entry, so we check env directly
        // Use getenv() to avoid linter warnings for non-existent config keys
        if ($clientId === 'forms-app') {
            $formsSecret = getenv('FORMS_KEYCLOAK_CLIENT_SECRET');

            return $formsSecret ?: null;
        }

        // Fallback: try to get from env using the provided key
        $secret = getenv($envKey);

        return $secret ?: null;
    }

    /**
     * Check if Keycloak is configured.
     */
    protected function isKeycloakConfigured(): bool
    {
        return ! empty(Config::get('services.keycloak.client_id'));
    }
}
