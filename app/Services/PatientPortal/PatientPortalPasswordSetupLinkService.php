<?php

namespace App\Services\PatientPortal;

use App\Exceptions\PatientPortal\PatientPortalPasswordSetupLinkException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;

/**
 * Fetches a one-time password-setup URL from the patient portal for CRM emails.
 *
 * The portal reset-password page lets the patient set a new password without
 * typing the temporary Keycloak password. CRM remains the only mail sender.
 */
class PatientPortalPasswordSetupLinkService
{
    /**
     * @throws PatientPortalPasswordSetupLinkException
     */
    public function fetchForPerson(Person $person, string $email): string
    {
        if (empty($person->keycloak_user_id)) {
            Log::error('Patient portal password-setup link request failed: person has no keycloak user', [
                'person_id' => $person->id,
                'email'     => $email,
            ]);

            throw new PatientPortalPasswordSetupLinkException(
                'Person has no Keycloak user ID for password-setup link request.',
            );
        }

        $apiUrl = rtrim((string) config('services.portal.patient.api_url'), '/');
        $apiToken = (string) config('services.portal.patient.api_token');
        $endpoint = (string) config(
            'services.portal.patient.password_setup_endpoint',
            '/api/patient/password-reset-link',
        );

        $response = Http::withHeader('X-API-KEY', $apiToken)
            ->post($apiUrl.$endpoint, [
                'email'            => $email,
                'keycloak_user_id' => $person->keycloak_user_id,
            ]);

        if (! $response->successful()) {
            Log::error('Patient portal password-setup link request failed', [
                'person_id'        => $person->id,
                'keycloak_user_id' => $person->keycloak_user_id,
                'email'            => $email,
                'status'           => $response->status(),
                'body'             => $response->body(),
            ]);

            throw new PatientPortalPasswordSetupLinkException(
                'Patient portal password-setup link request failed with HTTP '.$response->status().'.',
            );
        }

        $setupUrl = $response->json('reset_url');

        if (! is_string($setupUrl) || $setupUrl === '') {
            Log::error('Patient portal password-setup response missing reset_url', [
                'person_id'        => $person->id,
                'keycloak_user_id' => $person->keycloak_user_id,
                'email'            => $email,
                'body'             => $response->body(),
            ]);

            throw new PatientPortalPasswordSetupLinkException(
                'Patient portal password-setup response missing reset_url.',
            );
        }

        Log::info('Patient portal password-setup link requested for onboarding mail', [
            'person_id'        => $person->id,
            'keycloak_user_id' => $person->keycloak_user_id,
            'email'            => $email,
        ]);

        return $setupUrl;
    }
}
