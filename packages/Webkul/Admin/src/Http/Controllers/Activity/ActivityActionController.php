<?php

namespace Webkul\Admin\Http\Controllers\Activity;

use App\Enums\ActivityActionType;
use App\Enums\CallStatus as CallStatusEnum;
use App\Models\ActivityAction;
use App\Services\ActivityStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Controller;

class ActivityActionController extends Controller
{
    public function __construct(private readonly ActivityRepository $activityRepository) {}

    public function store(Request $request, int $activityId): JsonResponse
    {
        $this->activityRepository->findOrFail($activityId);

        $validated = $request->validate([
            'type'            => 'required|in:notitie,belstatus',
            'body'            => 'nullable|string|max:10000',
            'call_status'     => 'required_if:type,belstatus|nullable|in:' . implode(',', array_map(fn ($c) => $c->value, CallStatusEnum::cases())),
            'reschedule_days' => 'nullable|integer|min:1|max:20',
            'send_email'      => 'nullable|boolean',
        ]);

        $type = ActivityActionType::from($validated['type']);

        // Reschedule defaults for belstatus
        $rescheduleDays = null;
        if ($type === ActivityActionType::Belstatus) {
            if ($validated['call_status'] === CallStatusEnum::SPOKEN->value) {
                $rescheduleDays = $request->filled('reschedule_days') ? (int) $validated['reschedule_days'] : null;
            } else {
                $rescheduleDays = $request->filled('reschedule_days') ? (int) $validated['reschedule_days'] : 7;
            }
        }

        $action = ActivityAction::create([
            'activity_id'     => $activityId,
            'type'            => $type->value,
            'body'            => $validated['body'] ?? null,
            'call_status'     => $validated['call_status'] ?? null,
            'reschedule_days' => $rescheduleDays,
        ]);

        $action->load('creator');

        // Apply reschedule when belstatus
        if ($rescheduleDays !== null) {
            $activity = $this->activityRepository->findOrFail($activityId);
            $originalFrom = $activity->schedule_from;
            $originalTo   = $activity->schedule_to;

            if ($originalFrom) {
                $diff = ($originalTo && $originalFrom)
                    ? (int) round($originalTo->diffInDays($originalFrom))
                    : 0;

                $activity->schedule_from = now()->addDays($rescheduleDays);

                if ($originalTo) {
                    $activity->schedule_to = $activity->schedule_from->copy()->addDays($diff);
                }

                $activity->save();

                $computed = ActivityStatusService::computeStatus($activity->schedule_from, $activity->schedule_to, $activity->status);
                if ($computed->value !== ($activity->status?->value ?? null)) {
                    $activity->status = $computed;
                    $activity->save();
                }
            }

            $this->activityRepository->unassign($activity);
        }

        $response = [
            'message' => $type === ActivityActionType::Belstatus
                ? 'Belstatus toegevoegd' . ($rescheduleDays ? ' en taak verplaatst' : '')
                : 'Notitie toegevoegd',
            'data' => $action,
        ];

        if ($type === ActivityActionType::Belstatus && ! empty($validated['send_email'])) {
            $activity = Activity::findOrFail($activityId);
            $response['send_email']   = true;
            $response['default_email'] = $this->getDefaultEmail($activity);
            $response['activity_id']  = $activityId;
        }

        return response()->json($response);
    }

    public function destroy(int $activityId, int $actionId): JsonResponse
    {
        $action = ActivityAction::where('activity_id', $activityId)->findOrFail($actionId);

        if ($action->created_by !== auth()->guard('user')->id()) {
            return response()->json(['message' => 'Niet toegestaan'], 403);
        }

        $action->delete();

        return response()->json(['message' => 'Verwijderd']);
    }

    private function getDefaultEmail(Activity $activity): ?string
    {
        if ($activity->lead && $activity->lead->persons?->isNotEmpty()) {
            foreach ($activity->lead->persons as $person) {
                if (! empty($person->emails)) {
                    foreach ($person->emails as $email) {
                        if (isset($email['is_default']) && in_array($email['is_default'], [true, 'on', '1'], true)) {
                            return $email['value'] ?? null;
                        }
                    }

                    return $person->emails[0]['value'] ?? null;
                }
            }
        }

        return $activity->lead?->findDefaultEmail();
    }
}
