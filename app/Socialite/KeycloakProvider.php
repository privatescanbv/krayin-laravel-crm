<?php

namespace App\Socialite;

use App\Support\KeycloakConfig;
use Exception;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class KeycloakProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The base URL (for browser redirects only).
     */
    protected string $baseUrl;

    protected string $realm;

    /**
     * {@inheritdoc}
     */
    public function __construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle = [])
    {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle);

        // Houd de properties aan voor backwards compatibility, maar lees waardes
        // centraal via KeycloakConfig zodat configuratie op één plek staat.
        $this->baseUrl = KeycloakConfig::externalBaseUrl();
        $this->realm = KeycloakConfig::realm();
    }

    /**
     * Set the base URL (for browser redirects).
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
        } catch (ClientException $e) {
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
     * Token exchange happens server-side, so use internal URL.
     */
    protected function getTokenUrl(): string
    {
        return $this->resolveInternalKeycloakUrl('/realms/'.$this->getRealm().'/protocol/openid-connect/token');
    }

    /**
     * {@inheritdoc}
     * API call from server - always use internal URL.
     */
    protected function getUserByToken($token)
    {
        $userinfoUrl = $this->resolveInternalKeycloakUrl('/realms/'.$this->getRealm().'/protocol/openid-connect/userinfo');

        try {
            $response = $this->getHttpClient()->get($userinfoUrl, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Accept'        => 'application/json',
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            $responseBody = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : null;
            $statusCode = $e->getResponse() ? $e->getResponse()->getStatusCode() : null;

            Log::error('Keycloak userinfo call failed', [
                'error'          => $e->getMessage(),
                'userinfo_url'   => $userinfoUrl,
                'status_code'    => $statusCode,
                'response_body'  => $responseBody,
                'possible_cause' => $statusCode === 401
                    ? 'Token validation failed - token expired or invalid'
                    : ($statusCode === 404 ? 'Userinfo endpoint not found' : 'Unknown error'),
            ]);

            throw $e;
        } catch (Exception $e) {
            Log::error('Keycloak userinfo call error', [
                'error'        => $e->getMessage(),
                'userinfo_url' => $userinfoUrl,
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
     * {@inheritdoc}
     */
    protected function getTokenFields($code): array
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * Resolve internal Keycloak URL for API calls.
     */
    private function resolveInternalKeycloakUrl(string $path): string
    {
        return KeycloakConfig::internalUrl($path);
    }
}
