<?php

namespace App\Actions\Persons;

use App\Mail\PortalWelcomeMail;
use App\Services\Keycloak\KeycloakService;
use App\Services\PersonKeycloakService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;
use Webkul\Contact\Models\Person;
use Webkul\Email\Models\Email as EmailModel;
use Webkul\Lead\Models\Lead;

class CreatePortalAccountAction
{
    public function __construct(
        protected PersonKeycloakService $personKeycloakService,
        private KeycloakService $keycloakService
    ) {}

    /**
     * @return array{success: bool, message?: string}
     */
    public function execute(Person $person, ?string $password = null, ?Lead $lead = null): array
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

        // Stuur welkomstmail met tijdelijk wachtwoord (indien beschikbaar).
        try {
            $generatedPassword = $result['generated_password'] ?? null;

            if ($generatedPassword && $person->findDefaultEmail()) {
                $this->sendWelcomeMail($person, $generatedPassword, $lead);
            }
        } catch (Throwable $e) {
            Log::warning('Failed to send portal welcome mail', [
                'person_id' => $person->id,
                'lead_id'   => $lead?->id,
                'error'     => $e->getMessage(),
            ]);
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
        $emailAddress = $person->findDefaultEmail();

        if (! $emailAddress) {
            return;
        }

        // Stuur mail naar patiënt.
        Mail::to($emailAddress)->queue(new PortalWelcomeMail($person, $temporaryPassword));
        $redirect = urlencode(config('services.portal.patient'));

        // Sla een eenvoudige email‑record op voor de tijdlijn/koppelingen.
        $emailModel = new EmailModel;
        $emailModel->subject = 'Welkom bij het Privatescan patiëntportaal';
        $emailModel->reply = view('adminc.emails.portal-welcome', [
            'person'            => $person,
            'temporaryPassword' => $temporaryPassword,
            'loginUrl'          => $this->keycloakService->getRealmLoginUrl($redirect),
        ])->render();
        $emailModel->reply_to = [$emailAddress];

        // Zorg dat de frontend (bijv. VAvatar) altijd een bruikbare naam heeft
        $emailModel->name = $person->name ?: $emailAddress ?: 'Patiënt';

        $emailModel->person_id = $person->id;

        if ($lead) {
            $emailModel->lead_id = $lead->id;
        }

        $emailModel->save();
    }
}
