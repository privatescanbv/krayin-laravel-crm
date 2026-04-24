<?php

namespace App\Services\Mail;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches and caches a Microsoft Graph OAuth2 access token for the duration of one request/job.
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
