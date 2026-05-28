<?php

namespace App\Actions\Persons;

use App\Enums\EmailTemplateCode;
use App\Enums\KeyCloakClient;
use App\Services\Mail\CrmMailService;
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
    ) {}

    /**
     * Verstuurt bij succes (en sendAccountEmails true): eerst het DB-sjabloon {@see EmailTemplateCode::PATIENT_PORTAL_NOTIFICATION}, daarna de Blade-welkomstmail met tijdelijk wachtwoord.
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

        if (! empty($result['generated_password'])) {
            $message .= ' Tijdelijk wachtwoord: '.$result['generated_password'];
        }

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

        try {
            $emailPerson = $person->findDefaultEmailOrError();
            $this->crmMailService->sendToPersonTemplate(
                $person,
                EmailTemplateCode::PATIENT_PORTAL_NOTIFICATION,
                [
                    'lastname'          => (string) ($person->last_name ?? ''),
                    'portal_url'        => $portalUrl,
                    'portal_link'       => $portalUrl,
                    'person'            => $person,
                    'temporaryPassword' => $temporaryPassword,
                    'loginUrl'          => config('services.portal.patient.web_url'),
                    'loginUrlWithUsernameHint' => config('services.keycloak.base_url_external').'/realms/crm/protocol/openid-connect/auth?client_id='.KeyCloakClient::PATIENT->clientId().'&redirect_uri='.config('services.portal.patient.web_url').'%2Fcallback&response_type=code&scope=openid&login_hint='.$emailPerson,
                ],
                $linkEmailToEntities,
                isNotify: true
            );

        } catch (Exception $e) {
            Log::error('Failed to send portal welcome mail: person has no default email address', [
            'person_id' => $person->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
