<?php

namespace App\Actions\Persons;

use App\Enums\EmailTemplateCode;
use App\Exceptions\PatientPortal\PatientPortalPasswordSetupLinkException;
use App\Services\Mail\CrmMailService;
use App\Services\PatientPortal\PatientPortalPasswordSetupLinkService;
use App\Services\PersonKeycloakService;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;

class CreatePortalAccountAction
{
    public function __construct(
        protected PersonKeycloakService $personKeycloakService,
        private readonly CrmMailService $crmMailService,
        private readonly PatientPortalPasswordSetupLinkService $patientPortalPasswordSetupLinkService,
    ) {}

    /**
     * Verstuurt bij succes (en sendAccountEmails true) de CRM onboardingmail
     * met een one-time wachtwoord-instel-link vanuit het patient portaal.
     *
     * @param  bool  $sendAccountEmails  Zet op false bij aanroep vanuit synchronisatie/import: dan worden geen patiëntportaal-accountmails verstuurd (wel Keycloak-account aanmaken).
     * @return array{success: bool, message?: string}
     */
    public function execute(Person $person, ?string $password = null, ?Lead $lead = null, bool $sendAccountEmails = true): array
    {
        if (! $this->isKeycloakConfigured()) {
            return [
                'success' => false,
                'message' => 'Keycloak is niet geconfigureerd. Controleer services.keycloak.* instellingen.',
            ];
        }

        if (empty($person->findDefaultEmail())) {
            return [
                'success' => false,
                'message' => 'Persoon heeft geen primair e-mailadres. Portal account kan niet worden aangemaakt.',
            ];
        }

        $person->is_active = true;

        $result = $this->personKeycloakService->create($person, $password, true);

        if (! $result['success']) {
            return $result;
        }

        if (isset($result['keycloak_user_id'])) {
            Person::withoutEvents(function () use ($person, $result) {
                $person->forceFill([
                    'is_active'        => true,
                    'keycloak_user_id' => $result['keycloak_user_id'],
                ])->save();
            });
        }

        $person->refresh();

        if ($sendAccountEmails) {
            try {
                $generatedPassword = $result['generated_password'] ?? throw new Exception('Missing generated password with create portal account');
                $this->sendWelcomeMail($person, $generatedPassword, $lead);
            } catch (PatientPortalPasswordSetupLinkException $e) {
                Log::error('Failed to obtain patient portal password-setup link for welcome mail', [
                    'person_id'        => $person->id,
                    'lead_id'          => $lead?->id,
                    'keycloak_user_id' => $person->keycloak_user_id,
                    'error'            => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Er is een technische fout opgetreden bij het aanmaken van het patiëntportaalaccount. Neem contact op met de beheerder.',
                ];
            } catch (Throwable $e) {
                Log::warning('Failed to send portal welcome mail', [
                    'person_id' => $person->id,
                    'lead_id'   => $lead?->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        Log::info('Person portal account created', [
            'person_id'        => $person->id,
            'keycloak_user_id' => $result['keycloak_user_id'] ?? null,
        ]);

        $message = 'Patiëntportaal account aangemaakt.';

        return [
            'success' => true,
            'message' => $message,
        ];
    }

    protected function isKeycloakConfigured(): bool
    {
        return ! empty(Config::get('services.keycloak.client_id'));
    }

    /**
     * Verstuur de welkomstmail en koppel deze aan lead/person in de email‑historie.
     */
    protected function sendWelcomeMail(Person $person, string $temporaryPassword, ?Lead $lead = null): void
    {
        $portalUrl = (string) config('services.portal.patient.web_url', '');
        $linkEmailToEntities = $lead !== null ? ['lead_id' => (string) $lead->id] : [];

        $emailPerson = $person->findDefaultEmailOrError();
        $passwordSetupUrl = $this->patientPortalPasswordSetupLinkService->fetchForPerson($person, $emailPerson);

        $this->crmMailService->sendToPersonTemplate(
            $person,
            EmailTemplateCode::PATIENT_PORTAL_NOTIFICATION,
            [
                'lastname'                 => (string) ($person->last_name ?? ''),
                'portal_url'               => $portalUrl,
                'portal_link'              => $portalUrl,
                'person'                   => $person,
                'temporaryPassword'        => $temporaryPassword,
                'loginUrl'                 => config('services.portal.patient.web_url'),
                'loginUrlWithUsernameHint' => $passwordSetupUrl,
            ],
            $linkEmailToEntities,
            isNotify: true
        );

        Log::info('Portal welcome mail prepared with onboarding variables', [
            'person_id'        => $person->id,
            'lead_id'          => $lead?->id,
            'keycloak_user_id' => $person->keycloak_user_id,
        ]);
    }
}
