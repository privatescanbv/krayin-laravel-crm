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

    /**
     * Return a valid access token for the given mailbox key.
     *
     * @throws Exception when credentials are missing or the token request fails
     */
    public function getAccessToken(?string $mailboxKey = null): string
    {
        $credentials = MailboxConfig::graphCredentials($mailboxKey);
        $mailboxKey = $credentials['mailbox_key'];
        $tenantId = $credentials['tenant_id'];
        $clientId = $credentials['client_id'];
        $clientSecret = $credentials['client_secret'];
        $cacheKey = $this->cacheKey($mailboxKey, $tenantId, $clientId, $clientSecret);

        if (
            isset($this->accessTokens[$cacheKey], $this->expiresAt[$cacheKey])
            && time() < $this->expiresAt[$cacheKey]
        ) {
            return $this->accessTokens[$cacheKey];
        }

        try {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'scope'         => 'https://graph.microsoft.com/.default',
                    'grant_type'    => 'client_credentials',
                ]
            );

            if (! $response->successful()) {
                throw new Exception("Failed to get access token for mailbox [{$mailboxKey}]: ".$response->body());
            }

            $this->accessTokens[$cacheKey] = $response->json('access_token');
            $expiresIn = (int) $response->json('expires_in', 3600);
            $this->expiresAt[$cacheKey] = time() + $expiresIn - 60;

            return $this->accessTokens[$cacheKey];
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

        if ($mailboxKey === null) {
            throw new Exception("No mailbox configured for address [{$address}]");
        }

        return $this->getAccessToken($mailboxKey);
    }

    public function clearToken(?string $mailboxKey = null): void
    {
        if ($mailboxKey === null) {
            $this->accessTokens = [];
            $this->expiresAt = [];

            return;
        }

        foreach (array_keys($this->accessTokens) as $cacheKey) {
            if (str_starts_with($cacheKey, $mailboxKey.':')) {
                unset($this->accessTokens[$cacheKey], $this->expiresAt[$cacheKey]);
            }
        }
    }

    private function cacheKey(string $mailboxKey, string $tenantId, string $clientId, string $clientSecret): string
    {
        return implode(':', [
            $mailboxKey,
            $tenantId,
            $clientId,
            hash('sha256', $clientSecret),
        ]);
    }
}
