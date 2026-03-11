<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Services\Keycloak\KeycloakService;
use App\Support\KeycloakConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Models\Person;

class ImpersonationService
{
    public function __construct(
        protected KeycloakService $keycloakService,
        protected ActivityRepository $activityRepository,
    ) {}

    /**
     * Stop impersonation: logs out the patient user's Keycloak sessions.
     */
    public function stopImpersonation(string $keycloakUserId): void
    {
        $token = $this->keycloakService->getAdminToken();

        if (! $token) {
            Log::error('Kon geen Keycloak admin token verkrijgen voor stop impersonation.');

            return;
        }

        $realm = KeycloakConfig::realm();
        $url = KeycloakConfig::internalUrl("/admin/realms/{$realm}/users/{$keycloakUserId}/logout");

        $response = Http::withToken($token)->post($url);

        if (! $response->successful()) {
            Log::error('Keycloak logout (stop impersonation) failed', [
                'keycloak_user_id' => $keycloakUserId,
                'status'           => $response->status(),
                'body'             => $response->body(),
            ]);
        }
    }

    /**
     * Log an impersonation action as a SYSTEM activity on the person.
     */
    public function logActivity(Person $person, string $action, string $ip): void
    {
        $isStart = $action === 'start';

        $this->activityRepository->create([
            'type'       => ActivityType::SYSTEM,
            'title'      => $isStart ? 'Patiëntsessie gestart' : 'Patiëntsessie gestopt',
            'comment'    => null,
            'is_done'    => 1,
            'user_id'    => auth()->guard('user')->id(),
            'person_id'  => $person->id,
            'additional' => [
                'action'              => $action,
                'ip_address'          => $ip,
                'patient_keycloak_id' => $person->keycloak_user_id,
                'person'              => [
                    'id'   => $person->id,
                    'name' => $person->name,
                ],
            ],
        ]);
    }
}
