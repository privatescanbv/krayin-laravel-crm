<?php

namespace App\Http\Controllers\Api;

use App\Enums\PersonPreferenceKey;
use App\Enums\PreferredLanguage;
use App\Http\Controllers\Controller;
use App\Models\PersonPreference;
use App\Services\Keycloak\KeycloakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PatientPreferenceController extends Controller
{
    public function __construct(private readonly KeycloakService $keycloakService) {}

    /**
     * Get preferences for a patient.
     *
     * @group Patient preferences
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @responseField preferences object Key-value map of preferences.
     * @responseField preferences.email_notifications_enabled object Email notification preference.
     * @responseField preferences.email_notifications_enabled.value boolean Whether email notifications are enabled.
     * @responseField preferences.email_notifications_enabled.is_system_managed boolean Whether this preference is managed by the system.
     * @responseField preferences.language ['nl', 'en', 'de'] Preferred language of the patient
     */
    public function index(string $keycloakUserId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            Log::warning("Patient with Keycloak ID {$keycloakUserId} not found when fetching preferences.");
            abort(404);
        }

        return response()->json([
            'preferences' => array_merge(
                PersonPreference::getAllForPerson($person->id),
                [
                    'language'                => $person->preferred_language?->value,
                    'onboarding_completed_at' => $person->onboarding_completed_at?->toIso8601String(),
                ]
            ),
        ]);
    }

    /**
     * Update preferences for a patient.
     *
     * @group Patient preferences
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @bodyParam preferences object required Key-value map of preferences to update. Example: {"email_notifications_enabled": true}
     * @bodyParam preferences.email_notifications_enabled boolean Enable or disable email notifications. Example: true
     * @bodyParam preferences.language ['nl', 'en', 'de'] Preferred language of the patient
     *
     * @response 200 scenario="Success" {"preferences": {"email_notifications_enabled": {"value": true, "is_system_managed": false}}}
     * @response 404 scenario="Patient not found" {"message":"Not Found"}
     * @response 422 scenario="Validation error" {"message":"The given data was invalid."}
     */
    public function update(Request $request, string $keycloakUserId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            abort(404);
        }

        $validated = $request->validate([
            'preferences'                                                          => 'required|array',
            'preferences.'.PersonPreferenceKey::EMAIL_NOTIFICATIONS_ENABLED->value => 'boolean',
            'preferences.language'                                                 => ['nullable', Rule::enum(PreferredLanguage::class)],
            'preferences.onboarding_completed_at'                                  => 'nullable|date',
        ]);

        $preferences = $validated['preferences'];

        foreach ($preferences as $key => $value) {
            if ($key === 'language') {
                $person->preferred_language = $value;
                $person->save();

                continue;
            }

            if ($key === 'onboarding_completed_at') {
                $person->onboarding_completed_at = $value;
                $person->save();

                continue;
            }

            $enumKey = PersonPreferenceKey::tryFrom($key);

            if ($enumKey === null) {
                continue;
            }

            if ($enumKey->isSystemManaged()) {
                continue;
            }

            PersonPreference::setValueForPerson($person->id, $enumKey, $value);
        }

        return response()->json([
            'preferences' => array_merge(
                PersonPreference::getAllForPerson($person->id),
                [
                    'language'                => $person->preferred_language?->value,
                    'onboarding_completed_at' => $person->onboarding_completed_at?->toIso8601String(),
                ]
            ),
        ]);
    }

    /**
     * Get default preferences for users without a person record.
     *
     * @return array<string, mixed>
     */
    private function getDefaultPreferences(): array
    {
        $result = [];

        foreach (PersonPreferenceKey::cases() as $key) {
            $result[$key->value] = [
                'value'             => $key->defaultValue(),
                'is_system_managed' => $key->isSystemManaged(),
            ];
        }

        return $result;
    }
}
