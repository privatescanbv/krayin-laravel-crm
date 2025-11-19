<?php

namespace App\Socialite;

use Exception;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class KeycloakProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The base URL (for browser redirects).
     */
    protected string $baseUrl;

    protected string $realm;

    /**
     * {@inheritdoc}
     */
    public function __construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle = [])
    {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle);

        $this->baseUrl = config('services.keycloak.base_url', 'http://localhost:8085');
        $this->realm = config('services.keycloak.realm', 'crm');
    }

    /**
     * Set the base URL.
     *
     * @param  string  $baseUrl
     * @return $this
     */
    public function setBaseUrl($baseUrl): static
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    /**
     * Set the realm.
     *
     * @param  string  $realm
     * @return $this
     */
    public function setRealm($realm): static
    {
        $this->realm = $realm;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenResponse($code)
    {
        $tokenUrl = $this->getTokenUrl();
        $tokenFields = $this->getTokenFields($code);

        try {
            $response = parent::getAccessTokenResponse($code);

            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null;
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;

            // Parse error from response body if available
            $errorDescription = null;
            if ($responseBody) {
                $errorData = json_decode($responseBody, true);
                $errorDescription = $errorData['error_description'] ?? $errorData['error'] ?? null;
            }

            Log::error('Keycloak token exchange failed', [
                'error'             => $e->getMessage(),
                'error_description' => $errorDescription,
                'token_url'         => $tokenUrl,
                'client_id'         => $this->clientId,
                'redirect_uri'      => $tokenFields['redirect_uri'] ?? null,
                'status_code'       => $statusCode,
                'response_body'     => $responseBody,
                'realm'             => $this->getRealm(),
                'possible_cause'    => $statusCode === 401
                    ? ($errorDescription && strpos(strtolower($errorDescription), 'redirect') !== false
                        ? 'Redirect URI mismatch - check Valid Redirect URIs in Keycloak client settings'
                        : 'Invalid client credentials - check KEYCLOAK_CLIENT_SECRET in .env')
                    : ($statusCode === 400
                        ? 'Invalid authorization code or request parameters'
                        : 'Keycloak server error'),
            ]);

            throw $e;
        } catch (Exception $e) {
            Log::error('Keycloak token exchange error', [
                'error'          => $e->getMessage(),
                'token_url'      => $tokenUrl,
                'client_id'      => $this->clientId,
                'error_class'    => get_class($e),
                'possible_cause' => 'Network error or Keycloak service unavailable - check if Keycloak is running',
            ]);

            throw $e;
        }
    }

    /**
     * Get the default scopes.
     */
    public function getScopes(): array
    {
        return ['openid'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(
            $this->getBaseUrl().'/realms/'.$this->getRealm().'/protocol/openid-connect/auth',
            $state
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl(): string
    {
        return $this->getDockerServiceUrl().'/realms/'.$this->getRealm().'/protocol/openid-connect/token';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        // Decode token to get issuer URL and realm
        $tokenParts = explode('.', $token);
        $issuerUrl = null;
        $tokenRealm = null;
        $issuerBaseUrl = null;
        $dockerServiceUrl = $this->getDockerServiceUrl();
        $baseUrl = $this->getBaseUrl();
        $hostHeader = null;

        if (count($tokenParts) === 3) {
            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
            $issuerUrl = $payload['iss'] ?? null;

            // Extract realm from issuer URL (e.g., http://localhost:8085/realms/crm -> crm)
            if ($issuerUrl) {
                if (preg_match('#/realms/([^/]+)#', $issuerUrl, $matches)) {
                    $tokenRealm = $matches[1];
                }
            }
        }

        // Keycloak validates tokens by checking if the userinfo endpoint URL matches the issuer.
        // We must use the issuer URL for the userinfo call to ensure token validation works.
        if ($issuerUrl && $tokenRealm) {
            // Extract base URL from issuer (e.g., https://sso.dev.privatescan.nl/realms/crm -> https://sso.dev.privatescan.nl)
            $issuerBaseUrl = preg_replace('#/realms/.*#', '', $issuerUrl);

            // Parse issuer URL to determine if we need to use internal Docker URL
            $issuerScheme = parse_url($issuerBaseUrl, PHP_URL_SCHEME);
            $issuerHost = parse_url($issuerBaseUrl, PHP_URL_HOST);
            $issuerPort = parse_url($issuerBaseUrl, PHP_URL_PORT);

            // If issuer is HTTPS and we're in Docker, we need to call via internal Docker service
            // but Keycloak will validate based on the issuer URL, so we need to ensure the call
            // goes to the same host as the issuer. However, from within Docker, we can't call
            // the external HTTPS URL directly. We need to use the Docker service URL but ensure
            // Keycloak accepts it by matching the issuer.

            // For production: if issuer is HTTPS (external), we need to use the issuer URL directly
            // This will go through Traefik/proxy to reach Keycloak
            if ($issuerScheme === 'https' && $issuerHost !== 'localhost' && $issuerHost !== '127.0.0.1') {
                // Use issuer URL directly - this will go through the reverse proxy
                $userinfoUrl = $issuerBaseUrl.'/realms/'.$tokenRealm.'/protocol/openid-connect/userinfo';
                $hostHeader = null;
            } else {
                // For local development: use Docker service URL or issuer URL directly
                if ($dockerServiceUrl !== $baseUrl && $issuerScheme === 'http') {
                    // Use Docker service URL for internal calls
                    $userinfoUrl = $dockerServiceUrl.'/realms/'.$tokenRealm.'/protocol/openid-connect/userinfo';
                    // Set Host header to match issuer for token validation
                    $hostHeader = $issuerHost.($issuerPort ? ':'.$issuerPort : '');
                } else {
                    // Direct call (no Docker networking), use issuer URL directly
                    $userinfoUrl = $issuerBaseUrl.'/realms/'.$tokenRealm.'/protocol/openid-connect/userinfo';
                    $hostHeader = null;
                }
            }
        } else {
            $userinfoUrl = $dockerServiceUrl.'/realms/'.$this->getRealm().'/protocol/openid-connect/userinfo';
            $hostHeader = null;
        }

        try {
            $headers = [
                'Authorization' => 'Bearer '.$token,
                'Accept'        => 'application/json',
            ];

            // Add Host header to match issuer if needed
            if ($hostHeader) {
                $headers['Host'] = $hostHeader;
            }

            $response = $this->getHttpClient()->get(
                $userinfoUrl,
                ['headers' => $headers]
            );

            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null;
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;

            Log::error('Keycloak userinfo call failed', [
                'error'          => $e->getMessage(),
                'userinfo_url'   => $userinfoUrl,
                'issuer_url'     => $issuerUrl,
                'token_realm'    => $tokenRealm,
                'host_header'    => $hostHeader ?? null,
                'status_code'    => $statusCode,
                'response_body'  => $responseBody,
                'possible_cause' => $statusCode === 401
                    ? 'Token validation failed - issuer mismatch or token expired'
                    : ($statusCode === 404 ? 'Userinfo endpoint not found' : 'Unknown error'),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Keycloak userinfo call error', [
                'error'        => $e->getMessage(),
                'userinfo_url' => $userinfoUrl,
                'issuer_url'   => $issuerUrl,
                'error_class'  => get_class($e),
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id'         => $user['sub'] ?? null,
            'email'      => $user['email'] ?? null,
            'name'       => $user['name'] ?? ($user['preferred_username'] ?? null),
            'username'   => $user['preferred_username'] ?? null,
            'first_name' => $user['given_name'] ?? null,
            'last_name'  => $user['family_name'] ?? null,
        ]);
    }

    protected function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    protected function getRealm(): string
    {
        return $this->realm;
    }

    /**
     * Get the Docker service URL (for server-to-server calls).
     * Ensures host.docker.internal is not used (doesn't work on Linux).
     * Uses KeycloakService to centralize the logic.
     */
    protected function getDockerServiceUrl(): string
    {
        // Use KeycloakService to get Docker service URL (centralized logic)
        $keycloakService = app(\App\Services\KeycloakService::class);

        return $keycloakService->getDockerServiceUrl();
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code): array
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }
}
