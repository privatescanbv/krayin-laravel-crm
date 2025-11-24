<?php

namespace Tests\Feature\Keycloak\Helpers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class KeycloakHttpHelpers
{
    /**
     * Create HTTP fake for admin token.
     */
    public static function fakeAdminToken(?string $token = null, int $status = 200): void
    {
        // Use provided token, or default test token
        $token = $token ?? 'test-access-token';

        // Get both external and internal base URLs from config
        $baseUrl = Config::get('services.keycloak.base_url_external');
        $internalBaseUrl = Config::get('services.keycloak.base_url_internal', 'http://test-keycloak-docker.local:9999');

        // Remove http:// prefix if present for URL matching
        $baseUrlPattern = str_replace(['http://', 'https://'], '', $baseUrl);
        $internalUrlPattern = str_replace(['http://', 'https://'], '', $internalBaseUrl);

        // Mock both URLs since KeycloakService uses internal URL for API calls
        // Also use wildcard pattern to catch any keycloak URL that might be configured
        Http::fake([
            $baseUrlPattern.'/realms/master/protocol/openid-connect/token' => Http::response(
                $status === 200 ? ['access_token' => $token] : ['error' => 'invalid_grant'],
                $status
            ),
            $internalUrlPattern.'/realms/master/protocol/openid-connect/token' => Http::response(
                $status === 200 ? ['access_token' => $token] : ['error' => 'invalid_grant'],
                $status
            ),
            // Wildcard pattern to catch any keycloak URL (fallback)
            '*keycloak*realms/master/protocol/openid-connect/token' => Http::response(
                $status === 200 ? ['access_token' => $token] : ['error' => 'invalid_grant'],
                $status
            ),
        ]);
    }

    /**
     * Create HTTP fake callback for user operations.
     * Handles: email check (GET with email query), create (POST), update (PUT), delete (DELETE), password reset (PUT /reset-password), get by ID (GET without query).
     *
     * @param  array  $config  Configuration array:
     *                         - 'email_checks' => ['email@example.com' => response_data or null for empty]
     *                         - 'user_by_id' => ['user-id' => response_data]
     *                         - 'create_responses' => ['user-id-1', 'user-id-2'] (sequential) or ['email@example.com' => 'user-id'] (associative by email)
     *                         - 'update_success' => true/false
     *                         - 'delete_success' => true/false
     *                         - 'password_reset_success' => true/false
     */
    public static function fakeUserOperations(array $config = []): void
    {
        $emailChecks = $config['email_checks'] ?? [];
        $userById = $config['user_by_id'] ?? [];
        $createResponses = $config['create_responses'] ?? [];
        $updateSuccess = $config['update_success'] ?? true;
        $deleteSuccess = $config['delete_success'] ?? true;
        $passwordResetSuccess = $config['password_reset_success'] ?? true;

        // Reset create count for each test
        $createCount = 0;

        // Get both external and internal base URLs from config
        $baseUrl = Config::get('services.keycloak.base_url_external');
        $internalBaseUrl = Config::get('services.keycloak.base_url_internal');

        // Remove http:// prefix if present for URL matching
        $baseUrlPattern = str_replace(['http://', 'https://'], '', $baseUrl);
        $internalUrlPattern = str_replace(['http://', 'https://'], '', $internalBaseUrl);

        // Create a callback function for handling user operations
        $userOperationsCallback = function ($request) use (
            $emailChecks,
            $userById,
            $createResponses,
            $updateSuccess,
            $deleteSuccess,
            $passwordResetSuccess,
            $baseUrl,
            &$createCount
        ) {
            $url = $request->url();
            $method = $request->method();

            // Email check request (GET with email query parameter)
            if ($method === 'GET') {
                $parsedUrl = parse_url($url);
                if (isset($parsedUrl['query'])) {
                    parse_str($parsedUrl['query'], $queryParams);
                    if (isset($queryParams['email']) && isset($emailChecks[$queryParams['email']])) {
                        $response = $emailChecks[$queryParams['email']];
                        // Normalize response: if null, return empty array; if single item, wrap in array
                        $normalizedResponse = $response === null ? [] : (is_array($response) && isset($response[0]) ? $response : [$response]);

                        return Http::response($normalizedResponse, 200);
                    }
                }
                // Get user by ID (GET without query parameters)
                foreach ($userById as $userId => $userData) {
                    if (str_contains($url, "/users/{$userId}") && ! str_contains($url, '?')) {
                        return Http::response($userData, 200);
                    }
                }
            }

            // Password reset request (PUT with /reset-password)
            if ($method === 'PUT' && str_contains($url, '/reset-password')) {
                return Http::response('', $passwordResetSuccess ? 204 : 400);
            }

            // Update user (PUT without /reset-password)
            if ($method === 'PUT' && ! str_contains($url, '/reset-password')) {
                return Http::response('', $updateSuccess ? 204 : 400);
            }

            // Create user request (POST)
            if ($method === 'POST') {
                if (! empty($createResponses)) {
                    // Check if createResponses is associative (email => userId) or sequential (array of userIds)
                    $isAssociative = ! array_is_list($createResponses);

                    if ($isAssociative) {
                        // Use email from request body to get the correct userId
                        $body = $request->body();
                        $bodyData = json_decode($body, true);
                        $email = $bodyData['email'] ?? null;

                        if ($email && isset($createResponses[$email])) {
                            $userId = $createResponses[$email];

                            return Http::response('', 201, [
                                'Location' => "{$baseUrl}/admin/realms/crm/users/{$userId}",
                            ]);
                        }
                    } else {
                        // Sequential array - use by index
                        $userId = $createResponses[$createCount] ?? $createResponses[0];
                        $createCount++;

                        return Http::response('', 201, [
                            'Location' => "{$baseUrl}/admin/realms/crm/users/{$userId}",
                        ]);
                    }
                }
                // Default: return generic ID
                $createCount++;

                return Http::response('', 201, [
                    'Location' => "{$baseUrl}/admin/realms/crm/users/new-user-{$createCount}",
                ]);
            }

            // Delete user (DELETE)
            if ($method === 'DELETE') {
                return Http::response('', $deleteSuccess ? 204 : 400);
            }

            return Http::response('', 404);
        };

        // Mock both URLs since KeycloakService uses internal URL for admin operations
        // Also use wildcard pattern to catch any keycloak URL that might be configured
        Http::fake([
            $baseUrlPattern.'/admin/realms/crm/users*'     => $userOperationsCallback,
            $internalUrlPattern.'/admin/realms/crm/users*' => $userOperationsCallback,
            // Wildcard pattern to catch any keycloak URL (fallback)
            '*keycloak*/admin/realms/crm/users*' => $userOperationsCallback,
        ]);
    }

    /**
     * Setup Keycloak config for tests.
     * Uses non-existent test URLs to prevent accidental real HTTP requests if mocks fail.
     *
     * @param  array  $overrides  Optional config overrides
     */
    public static function setupConfig(array $overrides = []): void
    {
        // Use non-existent test URLs to prevent accidental real HTTP requests
        Config::set('services.keycloak.base_url_external', $overrides['base_url_external'] ?? 'http://test-keycloak.local:9999');
        Config::set('services.keycloak.base_url_internal', $overrides['base_url_internal'] ?? 'http://test-keycloak-docker.local:9999');
        Config::set('services.keycloak.realm', $overrides['realm'] ?? 'crm');
        Config::set('services.keycloak.client_id', $overrides['client_id'] ?? 'crm-app');
        Config::set('services.keycloak.admin_username', $overrides['admin_username'] ?? 'admin');
        Config::set('services.keycloak.admin_password', $overrides['admin_password'] ?? 'test-password');

        if (isset($overrides['client_secret'])) {
            Config::set('services.keycloak.client_secret', $overrides['client_secret']);
        }

        if (isset($overrides['default_role_id'])) {
            Config::set('services.keycloak.default_role_id', $overrides['default_role_id']);
        }
    }

    /**
     * Isolate test by disabling Keycloak during user creation.
     */
    public static function isolateTest(): void
    {
        Config::set('services.keycloak.client_id', null);
    }

    /**
     * Re-enable Keycloak configuration after user creation.
     */
    public static function enableKeycloak(): void
    {
        self::setupConfig(['default_role_id' => 1]);
    }
}
