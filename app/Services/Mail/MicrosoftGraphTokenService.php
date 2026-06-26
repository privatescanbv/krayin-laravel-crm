<?php

namespace App\Services\Mail;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Obtains and caches Microsoft Graph OAuth2 access tokens per configured mailbox.
 *
 * Each mailbox may use its own Azure AD tenant and application credentials.
 * Tokens are cached in-memory for the lifetime of the current PHP process.
 */
class MicrosoftGraphTokenService
{
    /** @var array<string, string> */
    private array $accessTokens = [];

    /** @var array<string, int> */
    private array $expiresAt = [];

    private static function shouldRetryWithAlternateSecret(string $errorBody): bool
    {
        return str_contains($errorBody, 'invalid_client')
            || str_contains($errorBody, 'AADSTS7000215');
    }

    /**
     * Return a valid access token for the given mailbox key.
     *
     * @throws Exception when credentials are missing or the token request fails
     */
    public function getAccessToken(?string $mailboxKey = null): string
    {
        $cacheKey = $this->cacheKey($mailboxKey);

        if (
            isset($this->accessTokens[$cacheKey], $this->expiresAt[$cacheKey])
            && time() < $this->expiresAt[$cacheKey]
        ) {
            return $this->accessTokens[$cacheKey];
        }

        $credentials = MailboxConfig::graphCredentials($mailboxKey);
        $tenantId = $credentials['tenant_id'];
        $clientId = $credentials['client_id'];
        $clientSecrets = MailboxConfig::clientSecretsForMailbox($mailboxKey);

        if (! $tenantId || ! $clientId || $clientSecrets === []) {
            throw new Exception('Microsoft Graph credentials not configured for mailbox: '.($mailboxKey ?? 'default'));
        }

        try {
            $lastErrorBody = null;

            foreach ($clientSecrets as $index => $clientSecret) {
                $response = Http::asForm()->post(
                    "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                    [
                        'client_id'     => $clientId,
                        'client_secret' => $clientSecret,
                        'scope'         => 'https://graph.microsoft.com/.default',
                        'grant_type'    => 'client_credentials',
                    ]
                );

                if ($response->successful()) {
                    $this->accessTokens[$cacheKey] = $response->json('access_token');
                    $expiresIn = (int) $response->json('expires_in', 3600);
                    $this->expiresAt[$cacheKey] = time() + $expiresIn - 60;

                    return $this->accessTokens[$cacheKey];
                }

                $lastErrorBody = $response->body();

                $hasAlternateSecret = $index < count($clientSecrets) - 1;

                if (! $hasAlternateSecret || ! self::shouldRetryWithAlternateSecret($lastErrorBody)) {
                    break;
                }
            }

            throw new Exception('Failed to get access token: '.($lastErrorBody ?? 'unknown error'));
        } catch (Exception $e) {
            Log::error('Failed to get Microsoft Graph access token', [
                'mailbox_key' => $mailboxKey,
                'error'       => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getAccessTokenForAddress(string $address): string
    {
        $mailboxKey = MailboxConfig::resolveKeyByAddress($address);

        return $this->getAccessToken($mailboxKey);
    }

    public function clearToken(?string $mailboxKey = null): void
    {
        if ($mailboxKey === null) {
            $this->accessTokens = [];
            $this->expiresAt = [];

            return;
        }

        $cacheKey = $this->cacheKey($mailboxKey);
        unset($this->accessTokens[$cacheKey], $this->expiresAt[$cacheKey]);
    }

    private function cacheKey(?string $mailboxKey): string
    {
        return $mailboxKey ?? MailboxConfig::defaultKey() ?? '_default';
    }
}
