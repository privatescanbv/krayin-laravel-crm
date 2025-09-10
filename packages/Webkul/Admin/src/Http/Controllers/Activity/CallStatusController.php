<?php

namespace Webkul\Admin\Http\Controllers\Activity;

use App\Enums\CallStatus as CallStatusEnum;
use App\Models\CallStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Activity\Models\Activity;
use Webkul\Admin\Http\Controllers\Controller;
use App\Services\ActivityStatusService;

class CallStatusController extends Controller
{
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

        // Reschedule activity if requested
        if (!empty($validated['reschedule_days'])) {
            $activity = Activity::find($activityId);
            if ($activity) {
                $days = (int) $validated['reschedule_days'];

               // Update schedule_from to today plus the specified days
               $originalFrom = $activity->schedule_from;
               $originalTo = $activity->schedule_to;

               if ($originalFrom) {
                   $diff = $originalTo && $originalFrom ? $originalTo->diffInDays($originalFrom) : 0;
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
        }

        return response()->json([
            'message' => 'Call status toegevoegd' . (!empty($validated['reschedule_days']) ? ' en taak verplaatst' : ''),
            'data' => $callStatus,
        ]);
    }
}

