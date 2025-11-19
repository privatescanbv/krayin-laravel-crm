<?php

namespace App\Socialite;

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

    /**
     * The internal base URL (for server-to-server calls).
     */
    protected ?string $internalBaseUrl;

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
     * Set the internal base URL.
     *
     * @param  string  $internalBaseUrl
     * @return $this
     */
    public function setInternalBaseUrl($internalBaseUrl): static
    {
        $this->internalBaseUrl = $internalBaseUrl;

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
        $response = parent::getAccessTokenResponse($code);

        // Log token response for debugging
        if (isset($response['access_token'])) {
            // Decode JWT token to see issuer
            $tokenParts = explode('.', $response['access_token']);
            if (count($tokenParts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $tokenParts[1])), true);
                Log::info('Keycloak token decoded', [
                    'issuer'     => $payload['iss'] ?? null,
                    'audience'   => $payload['aud'] ?? null,
                    'expires_at' => isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : null,
                ]);
            }
        }

        return $response;
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
        // Use internal URL for server-to-server token exchange
        $url = $this->getInternalBaseUrl().'/realms/'.$this->getRealm().'/protocol/openid-connect/token';
        Log::info('Keycloak getTokenUrl', [
            'token_url'         => $url,
            'internal_base_url' => $this->getInternalBaseUrl(),
            'base_url'          => $this->getBaseUrl(),
        ]);

        return $url;
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
        // In Docker environments, we use the Keycloak service name for internal calls
        // but set the Host header to match the issuer for proper token validation.
        if ($issuerUrl && $tokenRealm) {
            // Extract base URL from issuer (e.g., http://localhost:8085/realms/crm -> http://localhost:8085)
            $issuerBaseUrl = preg_replace('#/realms/.*#', '', $issuerUrl);
            $internalBaseUrl = $this->getInternalBaseUrl();
            $baseUrl = $this->getBaseUrl();

            // Extract host and port from issuer for Host header
            $issuerHost = parse_url($issuerBaseUrl, PHP_URL_HOST);
            $issuerPort = parse_url($issuerBaseUrl, PHP_URL_PORT);
            $hostHeader = $issuerHost.($issuerPort ? ':'.$issuerPort : '');

            // In Docker: use Keycloak service name for internal calls, but set Host header to match issuer
            if ($internalBaseUrl !== $baseUrl && strpos($issuerBaseUrl, 'localhost') !== false) {
                $userinfoBaseUrl = 'http://keycloak:8080';
            } else {
                $userinfoBaseUrl = $issuerBaseUrl;
                $hostHeader = null;
            }

            $userinfoUrl = $userinfoBaseUrl.'/realms/'.$tokenRealm.'/protocol/openid-connect/userinfo';
        } else {
            $userinfoUrl = $this->getInternalBaseUrl().'/realms/'.$this->getRealm().'/protocol/openid-connect/userinfo';
            $hostHeader = null;
        }

        Log::info('Keycloak getUserByToken', [
            'userinfo_url' => $userinfoUrl,
            'issuer_url'   => $issuerUrl,
            'token_realm'  => $tokenRealm,
            'host_header'  => $hostHeader ?? 'default',
        ]);

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
            Log::error('Keycloak getUserByToken failed', [
                'url'           => $userinfoUrl,
                'issuer_url'    => $issuerUrl,
                'token_realm'   => $tokenRealm,
                'status_code'   => $e->getResponse() ? $e->getResponse()->getStatusCode() : null,
                'response_body' => $responseBody,
                'error'         => $e->getMessage(),
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
     * Get the internal base URL (for server-to-server calls).
     */
    protected function getInternalBaseUrl(): string
    {
        // If internal URL is set, use it; otherwise fall back to regular baseUrl
        return $this->internalBaseUrl ?? $this->baseUrl;
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
