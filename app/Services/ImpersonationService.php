<?php

namespace App\Services;

use App\Enums\ActivityType;
use App\Services\Keycloak\KeycloakService;
use App\Support\KeycloakConfig;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Contact\Models\Person;
use Webkul\User\Models\User;

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
        $url = KeycloakConfig::internalUrl("/admin/realms/$realm/users/$keycloakUserId/logout");

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
     * Stores AVG-relevant audit data: IPs, user agent, admin identity, session info.
     */
    public function logActivity(Person $person, string $action, Request $request): void
    {
        $isStart = $action === 'start';
        $adminUser = auth()->guard('user')->user();

        $additional = [
            'action'              => $action,
            'timestamp'           => now()->toISOString(),
            'patient_keycloak_id' => $person->keycloak_user_id,
            'person'              => [
                'id'   => $person->id,
                'name' => $person->name,
            ],
            'admin' => [
                'id'    => $adminUser?->id,
                'name'  => $adminUser?->name,
                'email' => $adminUser?->email,
            ],
            'network' => [
                'ip_address'    => $request->ip(),
                'all_ips'       => $request->ips(),
                'forwarded_for' => $request->header('X-Forwarded-For'),
            ],
            'client' => [
                'user_agent' => $request->userAgent(),
                'referer'    => $request->header('Referer'),
            ],
            'session_id' => session()->getId(),
        ];

        if (! $isStart) {
            $startedAt = session('impersonating.started_at');
            if ($startedAt) {
                $additional['duration_seconds'] = (int) round(now()->diffInSeconds(Carbon::parse($startedAt)));
            }
        }

        $this->activityRepository->create([
            'type'       => ActivityType::SYSTEM,
            'title'      => $isStart ? 'Patiëntsessie gestart' : 'Patiëntsessie gestopt',
            'comment'    => null,
            'is_done'    => 1,
            'user_id'    => $adminUser?->id,
            'person_id'  => $person->id,
            'additional' => $additional,
        ]);
    }

    /**
     * Log a clinic portal impersonation as a SYSTEM activity on the clinic of the impersonated user
     * (users have no activity log of their own). Same audit data as the patient variant.
     */
    public function logUserActivity(User $target, string $action, Request $request): void
    {
        $clinicId = $target->clinic_id ?? $target->resource?->clinicDepartment?->clinic_id;

        if ($clinicId === null) {
            Log::warning('Kliniekportaal-impersonation niet in logboek: gebruiker heeft geen kliniek', [
                'action'      => $action,
                'target_user' => $target->id,
            ]);

            return;
        }

        $isStart = $action === 'start';
        $adminUser = auth()->guard('user')->user();

        $additional = [
            'action'                  => $action,
            'timestamp'               => now()->toISOString(),
            'clinic_user_keycloak_id' => $target->keycloak_user_id,
            'clinic_user'             => [
                'id'    => $target->id,
                'name'  => $target->name,
                'email' => $target->email,
            ],
            'admin' => [
                'id'    => $adminUser?->id,
                'name'  => $adminUser?->name,
                'email' => $adminUser?->email,
            ],
            'network' => [
                'ip_address'    => $request->ip(),
                'all_ips'       => $request->ips(),
                'forwarded_for' => $request->header('X-Forwarded-For'),
            ],
            'client' => [
                'user_agent' => $request->userAgent(),
                'referer'    => $request->header('Referer'),
            ],
            'session_id' => session()->getId(),
        ];

        if (! $isStart && ($startedAt = session('impersonating.started_at'))) {
            $additional['duration_seconds'] = (int) round(Carbon::parse($startedAt)->diffInSeconds(now()));
        }

        $this->activityRepository->create([
            'type'       => ActivityType::SYSTEM,
            'title'      => $isStart ? 'Kliniekportaal-sessie gestart' : 'Kliniekportaal-sessie gestopt',
            'comment'    => null,
            'is_done'    => 1,
            'user_id'    => $adminUser?->id,
            'clinic_id'  => $clinicId,
            'additional' => $additional,
        ]);
    }
}
