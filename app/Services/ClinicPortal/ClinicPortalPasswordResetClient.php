<?php

namespace App\Services\ClinicPortal;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reset tokens live in the clinic portal (same set-up as the patient portal):
 * it creates the link we mail and verifies the token before we change the password.
 */
class ClinicPortalPasswordResetClient
{
    public function createResetLink(string $email, string $keycloakUserId): ?string
    {
        $response = $this->post('/api/password-reset-link', [
            'email'            => $email,
            'keycloak_user_id' => $keycloakUserId,
        ]);

        $resetUrl = $response?->successful() ? $response->json('reset_url') : null;

        if (empty($resetUrl)) {
            Log::error('Clinic forgot-password: portal failed to generate reset link', [
                'email'  => $email,
                'status' => $response?->status(),
            ]);

            return null;
        }

        return $resetUrl;
    }

    public function verify(string $email, string $resetToken, string $keycloakUserId): bool
    {
        $response = $this->post('/api/password-reset/verify', [
            'email'            => $email,
            'reset_token'      => $resetToken,
            'keycloak_user_id' => $keycloakUserId,
        ]);

        if ($response?->status() === 204) {
            return true;
        }

        Log::warning('Clinic portal password-reset token verification failed', [
            'email'            => $email,
            'keycloak_user_id' => $keycloakUserId,
            'status'           => $response?->status(),
        ]);

        return false;
    }

    /**
     * @param  array<string, string>  $data
     */
    private function post(string $path, array $data): ?Response
    {
        $apiUrl = rtrim((string) config('services.portal.clinic.api_url'), '/');
        $apiToken = (string) config('services.portal.clinic.api_token');

        if ($apiUrl === '' || $apiToken === '') {
            Log::error('Clinic portal API not configured (CLINIC_PORTAL_API_URL / CLINIC_API_KEY)');

            return null;
        }

        try {
            return $this->request($apiToken)->post($apiUrl.$path, $data);
        } catch (Throwable $e) {
            Log::error('Clinic portal API call failed', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function request(string $apiToken): PendingRequest
    {
        return Http::withHeader('X-API-KEY', $apiToken)->acceptJson();
    }
}
