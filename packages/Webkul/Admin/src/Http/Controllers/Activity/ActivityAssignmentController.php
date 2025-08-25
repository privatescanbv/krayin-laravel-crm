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
            $canTakeover = auth()->guard('user')->user()->can('activities.takeover');
            
            return response()->json([
                'message' => 'Deze activiteit is al toegekend aan ' . $activity->user->name . '.',
                'assigned_user' => $activity->user->name,
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
            'data' => new ActivityResource($activity->fresh()),
        ]);
    }

    /**
     * Takeover activity from another user.
     */
    public function takeover(int $id): JsonResponse
    {
        if (!auth()->guard('user')->user()->can('activities.takeover')) {
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
            'data' => new ActivityResource($activity->fresh()),
        ]);
    }
}