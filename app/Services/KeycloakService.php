<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User;

class KeycloakService
{
    /**
     * Get the base URL for browser redirects (external).
     */
    public function getExternalBaseUrl(): string
    {
        return config('services.keycloak.base_url_external');
    }

    /**
     * Get the base URL for container to sync config (internal).
     */
    public function getInternalBaseUrl(): string
    {
        return config('services.keycloak.base_url_internal');
    }

    /**
     * Get the realm name.
     */
    public function getRealm(): string
    {
        return config('services.keycloak.realm', 'master');
    }

    /**
     * Get the client ID.
     */
    public function getClientId(): string
    {
        return config('services.keycloak.client_id');
    }

    /**
     * Get admin access token for API calls.
     */
    public function getAdminToken(): ?string
    {
        $tokenUrl = $this->resolveKeycloakUrl('/realms/master/protocol/openid-connect/token');

        $adminUsername = config('services.keycloak.admin_username', 'admin');
        $adminPassword = config('services.keycloak.admin_password', 'c8f4b3a8e69');

        try {
            $response = Http::asForm()->post($tokenUrl, [
                'grant_type' => 'password',
                'client_id'  => 'admin-cli',
                'username'   => $adminUsername,
                'password'   => $adminPassword,
            ]);

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('Failed to get Keycloak admin token', [
                'status'         => $response->status(),
                'body'           => $response->body(),
                'token_url'      => $tokenUrl,
                'admin_username' => $adminUsername,
                'possible_cause' => $response->status() === 401
                    ? 'Invalid admin credentials - check KEYCLOAK_ADMIN and KEYCLOAK_ADMIN_PASSWORD'
                    : 'Keycloak admin endpoint error',
            ]);

            return null;
        } catch (ClientException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null;
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;

            Log::error('Keycloak admin authentication failed', [
                'error'          => $e->getMessage(),
                'token_url'      => $tokenUrl,
                'admin_username' => $adminUsername,
                'status_code'    => $statusCode,
                'response_body'  => $responseBody,
                'possible_cause' => $statusCode === 401
                    ? 'Invalid admin credentials - check KEYCLOAK_ADMIN and KEYCLOAK_ADMIN_PASSWORD in .env'
                    : ($statusCode === 404 ? 'Keycloak admin endpoint not found - check KEYCLOAK_BASE_URL_INTERNAL' : 'Connection or configuration error'),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Keycloak admin authentication error', [
                'error'          => $e->getMessage(),
                'token_url'      => $tokenUrl,
                'error_class'    => get_class($e),
                'possible_cause' => 'Network error or Keycloak service unavailable - check if Keycloak is running',
            ]);

            return null;
        }
    }

    /**
     * Get realm admin API URL.
     */
    public function getRealmAdminUrl(): string
    {
        return $this->resolveKeycloakUrl('/admin/realms/'.$this->getRealm());
    }

    /**
     * Get realms admin API URL (for listing/creating realms).
     */
    public function getRealmsAdminUrl(): string
    {
        return $this->resolveKeycloakUrl('/admin/realms');
    }

    /**
     * Check if a realm exists.
     */
    public function realmExists(string $realmName, ?string $accessToken = null): bool
    {
        $url = $this->resolveKeycloakUrl('/admin/realms/'.$realmName);
        $response = $this->makeRequest('GET', $url, $accessToken);

        return $response?->successful() ?? false;
    }

    /**
     * Create a realm in Keycloak.
     */
    public function createRealm(string $realmName, array $realmData = [], ?string $accessToken = null): bool
    {
        $url = $this->getRealmsAdminUrl();
        $realmData = array_merge([
            'realm'       => $realmName,
            'enabled'     => true,
            'displayName' => ucfirst($realmName),
        ], $realmData);

        $response = $this->makeRequest('POST', $url, $accessToken, $realmData, true);

        if ($response?->successful()) {
            Log::info('Realm created in Keycloak', ['realm' => $realmName]);

            return true;
        }

        if ($response) {
            Log::error('Failed to create realm in Keycloak', [
                'status'     => $response->status(),
                'body'       => $response->body(),
                'realm'      => $realmName,
                'realm_data' => $realmData,
            ]);
        }

        return false;
    }

    /**
     * Get client by ID from a realm.
     */
    public function getClientById(string $clientId, string $realmName, ?string $accessToken = null): ?array
    {
        $url = $this->resolveKeycloakUrl('/admin/realms/'.$realmName.'/clients?clientId='.urlencode($clientId));
        $response = $this->makeRequest('GET', $url, $accessToken);

        if ($response?->successful()) {
            $clients = $response->json();

            return ! empty($clients) ? $clients[0] : null;
        }

        return null;
    }

    /**
     * Create a client in Keycloak.
     */
    public function createClient(string $realmName, array $clientData, ?string $accessToken = null): bool
    {
        $url = $this->resolveKeycloakUrl('/admin/realms/'.$realmName.'/clients');
        $response = $this->makeRequest('POST', $url, $accessToken, $clientData);

        if ($response?->successful()) {
            Log::info('Client created in Keycloak', [
                'client_id' => $clientData['clientId'] ?? null,
                'realm'     => $realmName,
            ]);

            return true;
        }

        if ($response) {
            Log::error('Failed to create client in Keycloak', [
                'status'      => $response->status(),
                'body'        => $response->body(),
                'realm'       => $realmName,
                'client_data' => $clientData,
            ]);
        }

        return false;
    }

    /**
     * Update a client in Keycloak.
     */
    public function updateClient(string $realmName, string $clientId, array $clientData, ?string $accessToken = null): bool
    {
        $client = $this->getClientById($clientId, $realmName, $accessToken);

        if (! $client) {
            return false;
        }

        $url = $this->resolveKeycloakUrl('/admin/realms/'.$realmName.'/clients/'.$client['id']);
        $response = $this->makeRequest('PUT', $url, $accessToken, $clientData);

        if ($response?->successful()) {
            Log::info('Client updated in Keycloak', [
                'client_id' => $clientId,
                'realm'     => $realmName,
            ]);

            return true;
        }

        if ($response) {
            Log::error('Failed to update client in Keycloak', [
                'status'      => $response->status(),
                'body'        => $response->body(),
                'realm'       => $realmName,
                'client_id'   => $clientId,
                'client_data' => $clientData,
            ]);
        }

        return false;
    }

    /**
     * Get user by email from Keycloak.
     */
    public function getUserByEmail(string $email, ?string $accessToken = null): ?array
    {
        $url = $this->resolveKeycloakUrl('/admin/realms/'.$this->getRealm().'/users?email='.urlencode($email));
        $response = $this->makeRequest('GET', $url, $accessToken);

        if ($response?->successful()) {
            $users = $response->json();

            return ! empty($users) ? $users[0] : null;
        }

        return null;
    }

    /**
     * Create user in Keycloak.
     */
    public function createUser(array $userData, ?string $accessToken = null): ?string
    {
        $url = $this->resolveKeycloakUrl('/admin/realms/'.$this->getRealm().'/users');

        // Log user data being sent to Keycloak for debugging
        Log::info('Creating user in Keycloak', [
            'username'  => $userData['username'] ?? null,
            'email'     => $userData['email'] ?? null,
            'firstName' => $userData['firstName'] ?? null,
            'lastName'  => $userData['lastName'] ?? null,
        ]);

        $response = $this->makeRequest('POST', $url, $accessToken, $userData);

        if ($response?->successful()) {
            $location = $response->header('Location');
            $userId = $location ? basename($location) : null;

            if ($userId) {
                Log::info('User created successfully in Keycloak', [
                    'keycloak_user_id' => $userId,
                    'username'         => $userData['username'] ?? null,
                    'email'            => $userData['email'] ?? null,
                ]);
            }

            return $userId;
        }

        if ($response) {
            Log::error('Failed to create user in Keycloak', [
                'status'    => $response->status(),
                'body'      => $response->body(),
                'user_data' => $userData,
            ]);
        }

        return null;
    }

    /**
     * Set password for a Keycloak user.
     */
    public function setUserPassword(string $userId, string $password, bool $temporary = false, ?string $accessToken = null): bool
    {
        $url = $this->resolveKeycloakUrl('/admin/realms/'.$this->getRealm().'/users/'.$userId.'/reset-password');
        $response = $this->makeRequest('PUT', $url, $accessToken, [
            'type'      => 'password',
            'value'     => $password,
            'temporary' => $temporary,
        ]);

        return $response?->successful() ?? false;
    }

    /**
     * Get Keycloak user via Socialite.
     */
    public function getUserViaSocialite(): User
    {
        return $this->getSocialiteDriver()->user();
    }

    /**
     * Get redirect URL for Keycloak authentication.
     */
    public function getRedirectUrl(): RedirectResponse
    {
        Log::info('Keycloak redirect', [
            'base_url'                 => $this->getExternalBaseUrl(),
            'realm'                    => $this->getRealm(),
            'config_base_url_external' => config('services.keycloak.base_url_external'),
            'session_id'               => session()->getId(),
        ]);

        return $this->getSocialiteDriver()
            ->scopes(['openid'])
            ->redirect();
    }

    /**
     * Decode logout token from backchannel logout.
     */
    public function decodeLogoutToken(string $logoutToken): ?array
    {
        try {
            $tokenParts = explode('.', $logoutToken);
            if (count($tokenParts) !== 3) {
                return null;
            }

            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);

            return [
                'session_id' => $payload['sid'] ?? null,
                'issuer'     => $payload['iss'] ?? null,
                'subject'    => $payload['sub'] ?? null,
                'payload'    => $payload,
            ];
        } catch (Exception $e) {
            Log::error('Failed to decode logout token', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get logout URL (for browser redirects, uses external URL).
     */
    public function getLogoutUrl(): string
    {
        $baseUrl = rtrim($this->getExternalBaseUrl(), '/');
        $path = '/realms/'.$this->getRealm().'/protocol/openid-connect/logout';

        return $baseUrl.$path;
    }

    /**
     * Update user in Keycloak.
     */
    public function updateUser(string $userId, array $userData, ?string $accessToken = null): bool
    {
        $url = $this->resolveKeycloakUrl('/admin/realms/'.$this->getRealm().'/users/'.$userId);
        $response = $this->makeRequest('PUT', $url, $accessToken, $userData);

        if ($response && ! $response->successful()) {
            Log::error('Failed to update user in Keycloak', [
                'status'    => $response->status(),
                'body'      => $response->body(),
                'user_id'   => $userId,
                'user_data' => $userData,
            ]);
        }

        return $response?->successful() ?? false;
    }

    /**
     * Delete user from Keycloak.
     */
    public function deleteUser(string $userId, ?string $accessToken = null): bool
    {
        $url = $this->resolveKeycloakUrl('/admin/realms/'.$this->getRealm().'/users/'.$userId);
        $response = $this->makeRequest('DELETE', $url, $accessToken);

        if ($response && ! $response->successful()) {
            Log::error('Failed to delete user from Keycloak', [
                'status'  => $response->status(),
                'body'    => $response->body(),
                'user_id' => $userId,
            ]);
        }

        return $response?->successful() ?? false;
    }

    /**
     * Get user by ID from Keycloak.
     */
    public function getUserById(string $userId, ?string $accessToken = null): ?array
    {
        $url = $this->resolveKeycloakUrl('/admin/realms/'.$this->getRealm().'/users/'.$userId);
        $response = $this->makeRequest('GET', $url, $accessToken);

        return $response?->successful() ? $response->json() : null;
    }

    /**
     * Get a realm role by name.
     */
    public function getRoleByName(string $roleName, ?string $accessToken = null): ?array
    {
        $token = $this->getOrResolveToken($accessToken);

        if (! $token) {
            Log::error('Kon geen admin token verkrijgen om rol te zoeken.');

            return null;
        }

        $url = $this->resolveKeycloakUrl('/admin/realms/'.$this->getRealm().'/roles/'.urlencode($roleName));
        $response = $this->makeRequest('GET', $url, $token);

        if ($response?->successful()) {
            return $response->json();
        }

        if ($response) {
            Log::warning('Failed to get role by name from Keycloak', [
                'role_name' => $roleName,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
        }

        return null;
    }

    /**
     * Create a realm role.
     */
    public function createRole(string $roleName, array $roleData = [], ?string $accessToken = null): bool
    {
        $token = $this->getOrResolveToken($accessToken);

        if (! $token) {
            Log::error('Kon geen admin token verkrijgen om rol aan te maken.');

            return false;
        }

        $url = $this->resolveKeycloakUrl('/admin/realms/'.$this->getRealm().'/roles');
        $roleData = array_merge([
            'name'        => $roleName,
            'description' => $roleData['description'] ?? '',
        ], $roleData);

        $response = $this->makeRequest('POST', $url, $token, $roleData, true);

        if ($response?->successful()) {
            Log::info('Role created in Keycloak', ['role_name' => $roleName]);

            return true;
        }

        if ($response) {
            Log::error('Failed to create role in Keycloak', [
                'role_name' => $roleName,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
        }

        return false;
    }

    /**
     * Assign a realm role to a user.
     */
    public function assignRoleToUser(string $userId, string $roleName, ?string $accessToken = null): bool
    {
        $token = $this->getOrResolveToken($accessToken);

        if (! $token) {
            Log::error('Kon geen admin token verkrijgen om rol toe te wijzen.');

            return false;
        }

        $role = $this->getRoleByName($roleName, $token);

        if (! $role) {
            Log::error('Role not found in Keycloak', [
                'role_name' => $roleName,
                'user_id'   => $userId,
            ]);

            return false;
        }

        $url = $this->resolveKeycloakUrl('/admin/realms/'.$this->getRealm().'/users/'.$userId.'/role-mappings/realm');
        $response = $this->makeRequest('POST', $url, $token, [[
            'id'   => $role['id'] ?? null,
            'name' => $role['name'] ?? $roleName,
        ]], true);

        if ($response?->successful()) {
            Log::info('Role assigned to user in Keycloak', [
                'role_name' => $roleName,
                'user_id'   => $userId,
            ]);

            return true;
        }

        if ($response) {
            Log::error('Failed to assign role to user in Keycloak', [
                'role_name' => $roleName,
                'user_id'   => $userId,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
        }

        return false;
    }

    /**
     * Resolve a Keycloak URL by appending a path to the internal base URL.
     */
    private function resolveKeycloakUrl(string $path): string
    {
        $baseUrl = rtrim($this->getInternalBaseUrl(), '/');
        $path = ltrim($path, '/');

        return $baseUrl.'/'.$path;
    }

    /**
     * Get or resolve access token.
     */
    private function getOrResolveToken(?string $accessToken): ?string
    {
        return $accessToken ?? $this->getAdminToken();
    }

    /**
     * Make an authenticated HTTP request to Keycloak.
     */
    private function makeRequest(string $method, string $url, ?string $accessToken, array $data = [], bool $asJson = false): ?\Illuminate\Http\Client\Response
    {
        $token = $this->getOrResolveToken($accessToken);

        if (! $token) {
            return null;
        }

        try {
            $request = Http::withToken($token);

            if ($asJson) {
                $request = $request->asJson();
            }

            return match ($method) {
                'GET'    => $request->get($url),
                'POST'   => $request->post($url, $data),
                'PUT'    => $request->put($url, $data),
                'DELETE' => $request->delete($url),
                default  => null,
            };
        } catch (Exception $e) {
            Log::error("Keycloak {$method} request failed", [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Setup Socialite driver.
     * Constructor already sets baseUrl and realm from config.
     */
    private function getSocialiteDriver()
    {
        return Socialite::driver('keycloak');
    }
}
