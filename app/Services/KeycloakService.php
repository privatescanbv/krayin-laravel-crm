<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class KeycloakService
{
    /**
     * Get the base URL for browser redirects (external).
     */
    public function getBaseUrl(): string
    {
        $baseUrl = config('services.keycloak.base_url', 'http://localhost:8085');

        // Ensure we're using external URL (not internal)
        if (strpos($baseUrl, 'keycloak:') !== false) {
            $baseUrl = str_replace('keycloak:', 'localhost:', $baseUrl);
        }

        return $baseUrl;
    }

    /**
     * Get the internal base URL for server-to-server calls.
     */
    public function getInternalBaseUrl(): string
    {
        return config('services.keycloak.base_url_internal', 'http://host.docker.internal:8085');
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
        $baseUrl = $this->getInternalBaseUrl();
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
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Exception getting Keycloak admin token', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get realm admin API URL.
     */
    public function getRealmAdminUrl(): string
    {
        return $this->getInternalBaseUrl().'/admin/realms/'.$this->getRealm();
    }

    /**
     * Get realms admin API URL (for listing/creating realms).
     */
    public function getRealmsAdminUrl(): string
    {
        return $this->getInternalBaseUrl().'/admin/realms';
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

        $url = $this->getInternalBaseUrl().'/admin/realms/'.$realmName;

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

        $url = $this->getInternalBaseUrl().'/admin/realms/'.$realmName.'/clients?clientId='.urlencode($clientId);

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

        $url = $this->getInternalBaseUrl().'/admin/realms/'.$realmName.'/clients';

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

        $url = $this->getInternalBaseUrl().'/admin/realms/'.$realmName.'/clients/'.$client['id'];

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
    public function getUserViaSocialite(): \Laravel\Socialite\Two\User
    {
        $baseUrl = $this->getBaseUrl();
        $realm = $this->getRealm();
        $internalBaseUrl = $this->getInternalBaseUrl();

        return Socialite::driver('keycloak')
            ->setBaseUrl($baseUrl)
            ->setInternalBaseUrl($internalBaseUrl)
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
}
