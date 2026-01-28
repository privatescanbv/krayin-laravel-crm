<?php

namespace App\Actions\Persons;

use App\Enums\PortalRevocationReason;
use App\Services\PersonKeycloakService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Models\Person;

class DeletePortalAccountAction
{
    public function __construct(
        protected PersonKeycloakService $personKeycloakService,
        protected ActivityRepository $activityRepository
    ) {}

    /**
     * @return array{success: bool, message?: string}
     */
    public function execute(
        Person $person,
        ?PortalRevocationReason $reason = null,
        ?string $comment = null
    ): array {
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

        // Create audit trail activity
        if ($reason) {
            $this->activityRepository->createSystemActivityForPortalRevocation(
                $person,
                $reason,
                $comment
            );
        }

        Log::info('Person portal account removed', [
            'person_id' => $person->id,
            'reason'    => $reason?->value,
            'comment'   => $comment,
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
