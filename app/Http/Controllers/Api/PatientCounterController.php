<?php

namespace App\Http\Controllers\Api;

use App\Enums\AppointmentTimeFilter;
use App\Enums\PatientMessageSenderType;
use App\Http\Controllers\Controller;
use App\Models\PatientMessage;
use App\Repositories\ActivityRepository;
use App\Repositories\OrderRepository;
use App\Services\FormService;
use App\Services\Keycloak\KeycloakService;
use Illuminate\Http\JsonResponse;

class PatientCounterController extends Controller
{
    public function __construct(
        private readonly KeycloakService $keycloakService,
        private readonly OrderRepository $orderRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly FormService $formService,
    ) {}

    /**
     * Get notification counters for the patient portal menu badges.
     *
     * @group Patient counters
     *
     * @urlParam id string required The Keycloak user ID of the patient. Example: 3f0b2d3e-5e1d-4c0f-9c0c-1b2f3a4b5c6d
     *
     * @response 200 scenario="Success" {"new_messages_count":3,"new_appointments_count":2, "new_docs_count":0}
     * @response 200 scenario="No person" {"new_messages_count":0,"new_appointments_count":0, "new_docs_count":0}
     * @response 404 scenario="Not found" {"message":"Not Found"}
     */
    public function __invoke(string $keycloakUserId): JsonResponse
    {
        [$person, $user] = $this->keycloakService->resolvePersonOrUser($keycloakUserId);

        if (is_null($person)) {
            if (! is_null($user)) {
                return $this->countersResponse(0, 0, 0);
            }

            abort(404);
        }

        $now = now();

        $messagesCount = PatientMessage::where('person_id', $person->id)
            ->whereIn('sender_type', [PatientMessageSenderType::STAFF->value, PatientMessageSenderType::SYSTEM->value])
            ->where('is_read', false)
            ->count();

        $appointmentsCount = $this->orderRepository
            ->queryPatientAppointmentsForPerson($person, AppointmentTimeFilter::FUTURE, $now)
            ->count();

        foreach (PatientAppointmentController::PORTAL_ACTIVITY_TYPES as $type) {
            $appointmentsCount += $this->activityRepository
                ->queryPatientActivitiesForPerson($person, $type, AppointmentTimeFilter::FUTURE, $now)
                ->count();
        }

        $openForms = $this->formService->getOpenForms($person->id);

        return $this->countersResponse($messagesCount, $appointmentsCount, $openForms);
    }

    private function countersResponse(int $messages, int $appointments, int $forms): JsonResponse
    {
        return response()->json([
            'new_messages_count'     => $messages,
            'new_appointments_count' => $appointments,
            'new_docs_count'         => $forms,
        ]);
    }
}
