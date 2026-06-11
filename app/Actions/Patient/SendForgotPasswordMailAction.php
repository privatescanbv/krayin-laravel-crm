<?php

namespace App\Actions\Patient;

use App\Enums\EmailTemplateCode;
use App\Services\Mail\CrmMailService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;

class SendForgotPasswordMailAction
{
    public function __construct(
        private readonly CrmMailService $crmMailService,
    ) {}

    /**
     * Send a password-reset mail to the patient with the given e-mail address.
     *
     * Silently no-ops when the address is unknown or not linked to a Keycloak
     * account — the caller should always return the same "check your inbox"
     * message to avoid email enumeration.
     */
    public function execute(string $email): void
    {
        $person = Person::where('emails', 'like', '%'.$email.'%')->first();

        if (! $person || empty($person->keycloak_user_id)) {
            Log::info('Patient forgot-password requested for unknown / unlinked email', [
                'email'             => $email,
                'person_found'      => (bool) $person,
                'has_keycloak_link' => $person ? ! empty($person->keycloak_user_id) : false,
            ]);

            return;
        }

        $resetUrl = $this->fetchResetUrlFromPortal($email, $person->keycloak_user_id);

        if ($resetUrl === null) {
            return;
        }

        // Authorise exactly one password/reset call for this keycloak ID.
        // PatientPasswordController::reset() consumes this token via Cache::pull,
        // so it cannot be replayed even if the portal calls back multiple times.
        Cache::put('patient_reset_pending:'.$person->keycloak_user_id, true, now()->addHours(2));

        $sent = $this->crmMailService->sendToPersonTemplate(
            person: $person,
            templateIdentifier: EmailTemplateCode::PATIENT_FORGOT_PASSWORD,
            variables: [
                'person'    => $person,
                'reset_url' => $resetUrl,
            ],
            isNotify: false,
        );

        Log::info('Patient forgot-password mail dispatched', [
            'person_id' => $person->id,
            'sent'      => $sent,
        ]);
    }

    /**
     * Ask the patient portal to generate a signed reset link hosted on the portal.
     *
     * Returns null on failure so the caller can silently skip sending the mail
     * (avoids leaking that the account exists when the portal is unreachable).
     */
    private function fetchResetUrlFromPortal(string $email, string $keycloakUserId): ?string
    {
        $apiUrl = rtrim((string) config('services.portal.patient.api_url'), '/');
        $apiToken = (string) config('services.portal.patient.api_token');

        $response = Http::withHeader('X-API-KEY', $apiToken)
            ->post("{$apiUrl}/api/patient/password-reset-link", [
                'email'             => $email,
                'keycloak_user_id'  => $keycloakUserId,
            ]);

        if (! $response->successful()) {
            Log::error('Patient forgot-password: portal failed to generate reset link', [
                'email'  => $email,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return null;
        }

        $resetUrl = $response->json('reset_url');

        if (empty($resetUrl)) {
            Log::error('Patient forgot-password: portal response missing reset_url', [
                'email' => $email,
                'body'  => $response->body(),
            ]);

            return null;
        }

        return $resetUrl;
    }
}
