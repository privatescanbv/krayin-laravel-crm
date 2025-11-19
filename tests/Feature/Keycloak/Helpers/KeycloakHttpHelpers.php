<?php

namespace Tests\Feature\Keycloak\Helpers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class KeycloakHttpHelpers
{
    /**
     * Create HTTP fake for admin token.
     */
    public static function fakeAdminToken(?string $token = 'test-access-token', int $status = 200): void
    {
        Http::fake([
            'host.docker.internal:8085/realms/master/protocol/openid-connect/token' => Http::response(
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
     *                         - 'create_responses' => ['user-id-1', 'user-id-2'] (sequence of user IDs)
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

        static $createCount = 0;

        Http::fake([
            'host.docker.internal:8085/admin/realms/crm/users*' => function ($request) use (
                $emailChecks,
                $userById,
                $createResponses,
                $updateSuccess,
                $deleteSuccess,
                $passwordResetSuccess,
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
                        $userId = $createResponses[$createCount] ?? $createResponses[0];
                        $createCount++;

                        return Http::response('', 201, [
                            'Location' => "http://host.docker.internal:8085/admin/realms/crm/users/{$userId}",
                        ]);
                    }
                    // Default: return generic ID
                    $createCount++;

                    return Http::response('', 201, [
                        'Location' => "http://host.docker.internal:8085/admin/realms/crm/users/new-user-{$createCount}",
                    ]);
                }

                // Delete user (DELETE)
                if ($method === 'DELETE') {
                    return Http::response('', $deleteSuccess ? 204 : 400);
                }

                return Http::response('', 404);
            },
        ]);
    }

    /**
     * Setup Keycloak config for tests.
     *
     * @param  array  $overrides  Optional config overrides
     */
    public static function setupConfig(array $overrides = []): void
    {
        Config::set('services.keycloak.base_url', $overrides['base_url'] ?? 'http://localhost:8085');
        Config::set('services.keycloak.base_url_internal', $overrides['base_url_internal'] ?? 'http://host.docker.internal:8085');
        Config::set('services.keycloak.realm', $overrides['realm'] ?? 'crm');
        Config::set('services.keycloak.client_id', $overrides['client_id'] ?? 'crm-app');

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
