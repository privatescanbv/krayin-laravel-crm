<?php

namespace App\Actions\Persons;

use App\Services\PersonKeycloakService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;

class DeletePortalAccountAction
{
    public function __construct(
        protected PersonKeycloakService $personKeycloakService
    ) {}

    /**
     * @return array{success: bool, message?: string}
     */
    public function execute(Person $person): array
    {
        if (! $this->isKeycloakConfigured()) {
            return [
                'success' => false,
                'message' => 'Keycloak is niet geconfigureerd.',
            ];
        }

        $result = $this->personKeycloakService->delete($person);

        if (! $result['success']) {
            return $result;
        }

        Person::withoutEvents(function () use ($person) {
            $person->forceFill([
                'is_active'        => false,
                'keycloak_user_id' => null,
                'password'         => null,
            ])->save();
        });

        Log::info('Person portal account removed', [
            'person_id' => $person->id,
        ]);

        return [
            'success' => true,
            'message' => 'Patiëntportaal account verwijderd.',
        ];
    }

    protected function isKeycloakConfigured(): bool
    {
        return ! empty(Config::get('services.keycloak.client_id'));
    }
}
