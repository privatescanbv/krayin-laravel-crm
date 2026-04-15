<?php

namespace Webkul\Admin\Http\Controllers\Activity;

use App\Enums\CallStatus as CallStatusEnum;
use App\Models\CallStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Controller;
use App\Services\ActivityStatusService;

class CallStatusController extends Controller
{

    public function __construct(private readonly ActivityRepository $activityRepository)
    {
    }

    public function index(int $activityId): JsonResponse
    {
        $items = CallStatus::where('activity_id', $activityId)
            ->with('creator')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, int $activityId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', array_map(fn($c) => $c->value, CallStatusEnum::cases())),
            'omschrijving' => 'nullable|string',
            'reschedule_days' => 'nullable|integer|min:1|max:20',
            'send_email' => 'nullable|boolean',
        ]);

        // Backend defaults: spoken => no move; others => 7 days if empty
        if (($validated['status'] ?? null) === CallStatusEnum::SPOKEN->value) {
            $validated['reschedule_days'] = $request->filled('reschedule_days') ? (string) $validated['reschedule_days'] : '';
        } else {
            if (!$request->filled('reschedule_days')) {
                $validated['reschedule_days'] = '7';
            }
        }

        $callStatus = CallStatus::create([
            'activity_id' => $activityId,
            'status' => $validated['status'],
            'omschrijving' => $validated['omschrijving'] ?? null,
        ]);
        $activity = $this->activityRepository->findOrFail($activityId);
        // Reschedule activity if requested
        if (!empty($validated['reschedule_days'])) {

               $days = (int) $validated['reschedule_days'];
               // Update schedule_from to today plus the specified days
               $originalFrom = $activity->schedule_from;
               $originalTo = $activity->schedule_to;

               if ($originalFrom) {
                   $diff = $originalTo && $originalFrom ? (int) round($originalTo->diffInDays($originalFrom)) : 0;
                   $activity->schedule_from = now()->addDays($days);
                   if ($originalTo) {
                       $activity->schedule_to = $activity->schedule_from->copy()->addDays($diff);
                   }
               }
                $activity->save();

                // Recompute status after reschedule
                $computed = ActivityStatusService::computeStatus($activity->schedule_from, $activity->schedule_to, $activity->status);
                if ($computed->value !== ($activity->status?->value ?? null)) {
                    $activity->status = $computed;
                    $activity->save();
                }
        }
        $this->activityRepository->unassign($activity);

        $response = [
            'message' => 'Call status toegevoegd' . (!empty($validated['reschedule_days']) ? ' en taak verplaatst' : ''),
            'data' => $callStatus,
        ];

        // If email should be sent, return additional data for frontend to handle
        if (!empty($validated['send_email'])) {
            // Fetch activity; rely on lazy-loading with null checks in helper to avoid BelongsToMany on missing lead
            $activity = Activity::findOrFail($activityId);
            $defaultEmail = $this->getDefaultEmailForActivity($activity);

            $response['send_email'] = true;
            $response['default_email'] = $defaultEmail;
            $response['activity_id'] = $activityId;
        }

        return response()->json($response);
    }

    /**
     * Get the default email address for an activity.
     * First tries to get email from associated persons, then from the lead itself.
     */
    private function getDefaultEmailForActivity(Activity $activity): ?string
    {
        // First try to get email from associated persons
        if ($activity->lead && $activity->lead->persons && $activity->lead->persons->isNotEmpty()) {
            foreach ($activity->lead->persons as $person) {
                if (!empty($person->emails)) {
                    // Find default email or first email
                    foreach ($person->emails as $email) {
                        if (isset($email['is_default']) && ($email['is_default'] === true || $email['is_default'] === 'on' || $email['is_default'] === '1')) {
                            return $email['value'] ?? null;
                        }
                    }
                    // If no default found, return first email
                    return $person->emails[0]['value'] ?? null;
                }
            }
        }

        // If no person email found, try lead's email
        if ($activity->lead) {
            return $activity->lead->findDefaultEmail();
        }

        return null;
    }
}

