<?php

namespace App\Services\Mail;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Obtains and caches Microsoft Graph OAuth2 access tokens per configured mailbox.
 *
 * Each mailbox may use its own Azure AD tenant and application credentials.
 *
 * Tokens are cached in the shared cache, not just in this process: the mail sync runs as a fresh
 * artisan process every minute, so a per-process cache means a brand new TLS handshake to
 * login.microsoftonline.com every single run - roughly 1400 per mailbox per day for a token that is
 * valid for an hour. Every one of those is a chance to hit a connection timeout.
 */
class MicrosoftGraphTokenService
{
    /**
     * Renew this many seconds before the token actually expires.
     */
    private const int EXPIRY_MARGIN = 60;

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

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $response = Http::asForm()
            // Fail fast and try again rather than sitting out the default 10s connect timeout:
            // the failures we see are TLS handshakes that never complete, and those are usually
            // gone on the next attempt.
            ->connectTimeout(5)
            ->timeout(10)
            // Only connection failures are retried. A rejected client_credentials grant is
            // permanent, so retrying it just triples the load and delays the real error.
            ->retry(2, 250, fn ($e) => $e instanceof ConnectionException, throw: false)
            ->post(
                "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token",
                [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'scope'         => 'https://graph.microsoft.com/.default',
                    'grant_type'    => 'client_credentials',
                ]
            );

        if (! $response->successful()) {
            throw new Exception("Failed to get access token for mailbox [$mailboxKey]: ".$response->body());
        }

        $token = (string) $response->json('access_token');
        $ttl = max((int) $response->json('expires_in', 3600) - self::EXPIRY_MARGIN, 60);

        Cache::put($cacheKey, $token, $ttl);

        return $token;
    }

    /**
     * Forget the cached token so the next call fetches a fresh one, e.g. after Graph rejected it
     * mid-flight (see MicrosoftGraphMailTransport).
     */
    public function clearToken(?string $mailboxKey = null): void
    {
        $mailboxKeys = $mailboxKey === null ? array_keys(MailboxConfig::all()) : [$mailboxKey];

        foreach ($mailboxKeys as $key) {
            $credentials = MailboxConfig::graphCredentials($key);

            Cache::forget($this->cacheKey(
                $credentials['mailbox_key'],
                $credentials['tenant_id'],
                $credentials['client_id'],
                $credentials['client_secret'],
            ));
        }
    }

    private function cacheKey(string $mailboxKey, string $tenantId, string $clientId, string $clientSecret): string
    {
        return implode(':', [
            'graph_token',
            $mailboxKey,
            $tenantId,
            $clientId,
            hash('sha256', $clientSecret),
        ]);
    }
}
