<?php

namespace Webkul\Admin\Http\Controllers\Activity;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Resources\ActivityResource;

class ActivityAssignmentController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected ActivityRepository $activityRepository
    ) {}

    /**
     * Assign activity to current user.
     */
    public function assign(int $id): JsonResponse
    {
        $activity = $this->activityRepository->findOrFail($id);
        
        // Check if activity is already assigned
        if ($activity->user_id) {
            $canTakeover = auth()->guard('user')->user()->hasPermission('activities.takeover');
            $userName = $activity->user ? $activity->user->name : 'Onbekende gebruiker';
            
            return response()->json([
                'message' => 'Deze activiteit is al toegekend aan ' . $userName . '.',
                'assigned_user' => $userName,
                'can_takeover' => $canTakeover,
                'activity_id' => $id,
            ], 409); // 409 Conflict
        }
        
        // Assign to current user
        $activity->update([
            'user_id' => auth()->guard('user')->id(),
            'assigned_at' => Carbon::now(),
        ]);
        
        return response()->json([
            'message' => 'Activiteit succesvol toegekend.',
            'data' => new ActivityResource($activity->fresh(['user'])),
        ]);
    }

    /**
     * Takeover activity from another user.
     */
    public function takeover(int $id): JsonResponse
    {
        if (!auth()->guard('user')->user()->hasPermission('activities.takeover')) {
            return response()->json([
                'message' => 'Je hebt geen rechten om activiteiten over te nemen.',
            ], 403);
        }
        
        $activity = $this->activityRepository->findOrFail($id);
        
        $previousUser = $activity->user ? $activity->user->name : null;
        
        $activity->update([
            'user_id' => auth()->guard('user')->id(),
            'assigned_at' => Carbon::now(),
        ]);
        
        $message = $previousUser 
            ? "Activiteit overgenomen van {$previousUser}."
            : 'Activiteit succesvol toegekend.';
        
        return response()->json([
            'message' => $message,
            'data' => new ActivityResource($activity->fresh(['user'])),
        ]);
    }

    /**
     * Unassign activity (make it available for others).
     */
    public function unassign(int $id): JsonResponse
    {
        $activity = $this->activityRepository->findOrFail($id);
        $currentUser = auth()->guard('user')->user();
        
        // Check if activity is assigned to current user
        if (!$activity->user_id || $activity->user_id !== $currentUser->id) {
            return response()->json([
                'message' => 'Je kunt alleen activiteiten teruggeven die aan jou zijn toegewezen.',
            ], 403);
        }
        
        $previousUser = $activity->user ? $activity->user->name : 'Onbekende gebruiker';
        
        $activity->update([
            'user_id' => null,
            'assigned_at' => null,
        ]);
        
        return response()->json([
            'message' => "Activiteit ontkoppeld. {$previousUser} kan de activiteit niet meer zien.",
            'data' => new ActivityResource($activity->fresh(['user'])),
        ]);
    }
}