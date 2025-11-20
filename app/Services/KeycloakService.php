<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User;

class KeycloakService
{
    /**
     * Get the base URL for browser redirects (external).
     */
    public function getBaseUrl(): string
    {
        return config('services.keycloak.base_url', 'http://keycloak.local:8080');
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
        $baseUrl = $this->getBaseUrl();
        $tokenUrl = $baseUrl.'/realms/master/protocol/openid-connect/token';

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
        } catch (\GuzzleHttp\Exception\ClientException $e) {
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
                    : ($statusCode === 404 ? 'Keycloak admin endpoint not found - check KEYCLOAK_DOCKER_SERVICE_URL' : 'Connection or configuration error'),
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
        return $this->getBaseUrl().'/admin/realms/'.$this->getRealm();
    }

    /**
     * Get realms admin API URL (for listing/creating realms).
     */
    public function getRealmsAdminUrl(): string
    {
        return $this->getBaseUrl().'/admin/realms';
    }

    /**
     * Check if a realm exists.
     */
    public function realmExists(string $realmName, ?string $accessToken = null): bool
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            return false;
        }

        $url = $this->getBaseUrl().'/admin/realms/'.$realmName;

        try {
            $response = Http::withToken($accessToken)->get($url);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to check if realm exists', [
                'realm' => $realmName,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create a realm in Keycloak.
     */
    public function createRealm(string $realmName, array $realmData = [], ?string $accessToken = null): bool
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            return false;
        }

        $url = $this->getRealmsAdminUrl();

        $defaultRealmData = [
            'realm'       => $realmName,
            'enabled'     => true,
            'displayName' => ucfirst($realmName),
        ];

        $realmData = array_merge($defaultRealmData, $realmData);

        try {
            $response = Http::asJson()
                ->withToken($accessToken)
                ->post($url, $realmData);

            if ($response->successful()) {
                Log::info('Realm created in Keycloak', [
                    'realm' => $realmName,
                ]);

                return true;
            }

            Log::error('Failed to create realm in Keycloak', [
                'status'     => $response->status(),
                'body'       => $response->body(),
                'realm'      => $realmName,
                'realm_data' => $realmData,
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Exception creating realm in Keycloak', [
                'error' => $e->getMessage(),
                'realm' => $realmName,
            ]);

            return false;
        }
    }

    /**
     * Get client by ID from a realm.
     */
    public function getClientById(string $clientId, string $realmName, ?string $accessToken = null): ?array
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            return null;
        }

        $url = $this->getBaseUrl().'/admin/realms/'.$realmName.'/clients?clientId='.urlencode($clientId);

        try {
            $response = Http::withToken($accessToken)->get($url);

            if ($response->successful()) {
                $clients = $response->json();

                return count($clients) > 0 ? $clients[0] : null;
            }

            return null;
        } catch (Exception $e) {
            Log::error('Failed to get client from Keycloak', [
                'client_id' => $clientId,
                'realm'     => $realmName,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create a client in Keycloak.
     */
    public function createClient(string $realmName, array $clientData, ?string $accessToken = null): bool
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            return false;
        }

        $url = $this->getBaseUrl().'/admin/realms/'.$realmName.'/clients';

        try {
            $response = Http::withToken($accessToken)->post($url, $clientData);

            if ($response->successful()) {
                Log::info('Client created in Keycloak', [
                    'client_id' => $clientData['clientId'] ?? null,
                    'realm'     => $realmName,
                ]);

                return true;
            }

            Log::error('Failed to create client in Keycloak', [
                'status'      => $response->status(),
                'body'        => $response->body(),
                'realm'       => $realmName,
                'client_data' => $clientData,
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Exception creating client in Keycloak', [
                'error' => $e->getMessage(),
                'realm' => $realmName,
            ]);

            return false;
        }
    }

    /**
     * Update a client in Keycloak.
     */
    public function updateClient(string $realmName, string $clientId, array $clientData, ?string $accessToken = null): bool
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            return false;
        }

        // First get the client UUID
        $client = $this->getClientById($clientId, $realmName, $accessToken);

        if (! $client) {
            return false;
        }

        $url = $this->getBaseUrl().'/admin/realms/'.$realmName.'/clients/'.$client['id'];

        try {
            $response = Http::withToken($accessToken)->put($url, $clientData);

            if ($response->successful()) {
                Log::info('Client updated in Keycloak', [
                    'client_id' => $clientId,
                    'realm'     => $realmName,
                ]);

                return true;
            }

            Log::error('Failed to update client in Keycloak', [
                'status'      => $response->status(),
                'body'        => $response->body(),
                'realm'       => $realmName,
                'client_id'   => $clientId,
                'client_data' => $clientData,
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Exception updating client in Keycloak', [
                'error'     => $e->getMessage(),
                'realm'     => $realmName,
                'client_id' => $clientId,
            ]);

            return false;
        }
    }

    /**
     * Get user by email from Keycloak.
     */
    public function getUserByEmail(string $email, ?string $accessToken = null): ?array
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            return null;
        }

        $url = $this->getRealmAdminUrl().'/users?email='.urlencode($email);

        try {
            $response = Http::withToken($accessToken)->get($url);

            if ($response->successful()) {
                $users = $response->json();

                return count($users) > 0 ? $users[0] : null;
            }

            return null;
        } catch (Exception $e) {
            Log::error('Failed to get user from Keycloak', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create user in Keycloak.
     */
    public function createUser(array $userData, ?string $accessToken = null): ?string
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            return null;
        }

        $url = $this->getRealmAdminUrl().'/users';

        try {
            $response = Http::withToken($accessToken)->post($url, $userData);

            if ($response->successful()) {
                // Get the created user ID from Location header
                $location = $response->header('Location');

                return $location ? basename($location) : null;
            }

            Log::error('Failed to create user in Keycloak', [
                'status'    => $response->status(),
                'body'      => $response->body(),
                'user_data' => $userData,
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Exception creating user in Keycloak', [
                'error'     => $e->getMessage(),
                'user_data' => $userData,
            ]);

            return null;
        }
    }

    /**
     * Set password for a Keycloak user.
     */
    public function setUserPassword(string $userId, string $password, bool $temporary = false, ?string $accessToken = null): bool
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            return false;
        }

        $url = $this->getRealmAdminUrl().'/users/'.$userId.'/reset-password';

        try {
            $response = Http::withToken($accessToken)->put($url, [
                'type'      => 'password',
                'value'     => $password,
                'temporary' => $temporary,
            ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Failed to set password for Keycloak user', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get Keycloak user via Socialite.
     */
    public function getUserViaSocialite(): User
    {
        $baseUrl = $this->getBaseUrl();
        $realm = $this->getRealm();

        return Socialite::driver('keycloak')
            ->setBaseUrl($baseUrl)
            ->setRealm($realm)
            ->user();
    }

    /**
     * Get redirect URL for Keycloak authentication.
     */
    public function getRedirectUrl(): \Illuminate\Http\RedirectResponse
    {
        $baseUrl = $this->getBaseUrl();
        $realm = $this->getRealm();

        Log::info('Keycloak redirect', [
            'base_url'        => $baseUrl,
            'realm'           => $realm,
            'config_base_url' => config('services.keycloak.base_url'),
            'session_id'      => session()->getId(),
        ]);

        return Socialite::driver('keycloak')
            ->setBaseUrl($baseUrl)
            ->setRealm($realm)
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
     * Get logout URL.
     */
    public function getLogoutUrl(): string
    {
        return $this->getBaseUrl().'/realms/'.$this->getRealm().'/protocol/openid-connect/logout';
    }

    /**
     * Update user in Keycloak.
     */
    public function updateUser(string $userId, array $userData, ?string $accessToken = null): bool
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            return false;
        }

        $url = $this->getRealmAdminUrl().'/users/'.$userId;

        try {
            $response = Http::withToken($accessToken)->put($url, $userData);

            if (! $response->successful()) {
                Log::error('Failed to update user in Keycloak', [
                    'status'    => $response->status(),
                    'body'      => $response->body(),
                    'user_id'   => $userId,
                    'user_data' => $userData,
                ]);
            }

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Exception updating user in Keycloak', [
                'error'     => $e->getMessage(),
                'user_id'   => $userId,
                'user_data' => $userData,
            ]);

            return false;
        }
    }

    /**
     * Delete user from Keycloak.
     */
    public function deleteUser(string $userId, ?string $accessToken = null): bool
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            return false;
        }

        $url = $this->getRealmAdminUrl().'/users/'.$userId;

        try {
            $response = Http::withToken($accessToken)->delete($url);

            if (! $response->successful()) {
                Log::error('Failed to delete user from Keycloak', [
                    'status'  => $response->status(),
                    'body'    => $response->body(),
                    'user_id' => $userId,
                ]);
            }

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Exception deleting user from Keycloak', [
                'error'   => $e->getMessage(),
                'user_id' => $userId,
            ]);

            return false;
        }
    }

    /**
     * Get user by ID from Keycloak.
     */
    public function getUserById(string $userId, ?string $accessToken = null): ?array
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            return null;
        }

        $url = $this->getRealmAdminUrl().'/users/'.$userId;

        try {
            $response = Http::withToken($accessToken)->get($url);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (Exception $e) {
            Log::error('Failed to get user by ID from Keycloak', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get a realm role by name.
     */
    public function getRoleByName(string $roleName, ?string $accessToken = null): ?array
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            Log::error('Kon geen admin token verkrijgen om rol te zoeken.');

            return null;
        }

        $url = $this->getRealmAdminUrl().'/roles/'.urlencode($roleName);

        try {
            $response = Http::withToken($accessToken)->get($url);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::warning('Failed to get role by name from Keycloak', [
                    'role_name' => $roleName,
                    'status'    => $response->status(),
                    'body'      => $response->body(),
                ]);
            }

            return null;
        } catch (Exception $e) {
            Log::error('Exception getting role by name from Keycloak', [
                'role_name' => $roleName,
                'error'     => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create a realm role.
     */
    public function createRole(string $roleName, array $roleData = [], ?string $accessToken = null): bool
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            Log::error('Kon geen admin token verkrijgen om rol aan te maken.');

            return false;
        }

        $url = $this->getRealmAdminUrl().'/roles';

        $defaultRoleData = [
            'name'        => $roleName,
            'description' => $roleData['description'] ?? '',
        ];

        $roleData = array_merge($defaultRoleData, $roleData);

        try {
            $response = Http::asJson()
                ->withToken($accessToken)
                ->post($url, $roleData);

            if ($response->successful()) {
                Log::info('Role created in Keycloak', [
                    'role_name' => $roleName,
                ]);

                return true;
            } else {
                Log::error('Failed to create role in Keycloak', [
                    'role_name' => $roleName,
                    'status'    => $response->status(),
                    'body'      => $response->body(),
                ]);
            }

            return false;
        } catch (Exception $e) {
            Log::error('Exception creating role in Keycloak', [
                'role_name' => $roleName,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Assign a realm role to a user.
     */
    public function assignRoleToUser(string $userId, string $roleName, ?string $accessToken = null): bool
    {
        $accessToken = $accessToken ?? $this->getAdminToken();

        if (! $accessToken) {
            Log::error('Kon geen admin token verkrijgen om rol toe te wijzen.');

            return false;
        }

        // Get the role first
        $role = $this->getRoleByName($roleName, $accessToken);

        if (! $role) {
            Log::error('Role not found in Keycloak', [
                'role_name' => $roleName,
                'user_id'   => $userId,
            ]);

            return false;
        }

        // Prepare role representation for assignment (only id and name are required)
        $roleRepresentation = [
            'id'   => $role['id'] ?? null,
            'name' => $role['name'] ?? $roleName,
        ];

        $url = $this->getRealmAdminUrl().'/users/'.$userId.'/role-mappings/realm';

        try {
            $response = Http::asJson()
                ->withToken($accessToken)
                ->post($url, [$roleRepresentation]);

            if ($response->successful()) {
                Log::info('Role assigned to user in Keycloak', [
                    'role_name' => $roleName,
                    'user_id'   => $userId,
                ]);

                return true;
            } else {
                Log::error('Failed to assign role to user in Keycloak', [
                    'role_name' => $roleName,
                    'user_id'   => $userId,
                    'status'    => $response->status(),
                    'body'      => $response->body(),
                ]);
            }

            return false;
        } catch (Exception $e) {
            Log::error('Exception assigning role to user in Keycloak', [
                'role_name' => $roleName,
                'user_id'   => $userId,
                'error'     => $e->getMessage(),
            ]);

            return false;
        }
    }
}
