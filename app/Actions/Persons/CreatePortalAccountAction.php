<?php

namespace App\Actions\Persons;

use App\Services\PersonKeycloakService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;

class CreatePortalAccountAction
{
    public function __construct(
        protected PersonKeycloakService $personKeycloakService
    ) {}

    /**
     * @return array{success: bool, message?: string}
     */
    public function execute(Person $person, ?string $password = null): array
    {
        if (! $this->isKeycloakConfigured()) {
            return [
                'success' => false,
                'message' => 'Keycloak is niet geconfigureerd. Controleer services.keycloak.* instellingen.',
            ];
        }

        if ($person->is_active) {
            return [
                'success' => false,
                'message' => 'Patiëntportaal is al actief voor deze persoon.',
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
}
