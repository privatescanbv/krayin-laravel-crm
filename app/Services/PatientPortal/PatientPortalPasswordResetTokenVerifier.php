<?php

namespace App\Services\PatientPortal;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies patient portal reset tokens with the portal API.
 *
 * Replaces ephemeral CRM cache flags so password resets keep working after
 * deploys or cache flushes while the portal token remains valid in the DB.
 */
class PatientPortalPasswordResetTokenVerifier
{
    public function verify(string $email, string $resetToken, string $keycloakUserId): bool
    {
        $apiUrl = rtrim((string) config('services.portal.patient.api_url'), '/');
        $apiToken = (string) config('services.portal.patient.api_token');

        if ($apiUrl === '' || $apiToken === '') {
            Log::error('Patient portal password-reset token verification skipped: portal API not configured');

            return false;
        }

        $response = Http::withHeader('X-API-KEY', $apiToken)
            ->post($apiUrl.'/api/patient/password-reset/verify', [
                'email'            => $email,
                'reset_token'      => $resetToken,
                'keycloak_user_id' => $keycloakUserId,
            ]);

        if ($response->status() === 204) {
            return true;
        }

        Log::warning('Patient portal password-reset token verification failed', [
            'email'            => $email,
            'keycloak_user_id' => $keycloakUserId,
            'status'           => $response->status(),
            'body'             => $response->body(),
        ]);

        return false;
    }
}
