<?php

namespace App\Services\Mail;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Obtains and in-memory caches a Microsoft Graph OAuth2 access token.
 *
 * Uses the OAuth 2.0 client-credentials flow (application identity, no user context).
 * The token is cached for the lifetime of the current PHP process / request so that
 * multiple Graph API calls within the same job or request share a single token fetch.
 *
 * Required config keys (via `config/mail.php` → `mail.graph.*`):
 *  - `tenant_id`     — Azure AD tenant GUID
 *  - `client_id`     — registered application (client) ID
 *  - `client_secret` — application secret
 *
 * Used by {@see GraphMailService} (inbound sync) and {@see MicrosoftGraphMailTransport} (outbound send).
 */
class MicrosoftGraphTokenService
{
    private ?string $accessToken = null;

    /**
     * Return a valid access token, requesting a new one if not yet cached.
     *
     * @throws Exception when credentials are missing or the token request fails
     */
    public function getAccessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $tenantId     = config('mail.graph.tenant_id');
        $clientId     = config('mail.graph.client_id');
        $clientSecret = config('mail.graph.client_secret');

        if (! $tenantId || ! $clientId || ! $clientSecret) {
            throw new Exception('Microsoft Graph credentials not configured');
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
                throw new Exception('Failed to get access token: '.$response->body());
            }

            $this->accessToken = $response->json('access_token');

            return $this->accessToken;
        } catch (Exception $e) {
            Log::error('Failed to get Microsoft Graph access token', ['error' => $e->getMessage()]);

            throw $e;
        }
    }
}
