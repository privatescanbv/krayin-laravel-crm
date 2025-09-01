<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Lead\Repositories\LeadRepository;

class LeadNoteController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected ActivityRepository $activityRepository,
        protected LeadRepository $leadRepository
    ) {}

    /**
     * Add a note to a lead.
     */
    public function store(int $leadId): JsonResponse
    {
        Log::info('Store note lead: '.$leadId);
        request()->validate(request(), [
            'comment' => 'required|string',
        ]);

        $lead = $this->leadRepository->findOrFail($leadId);

        // Get default group from lead's department or fallback
        $groupId = null;
        if ($lead->department) {
            $group = \Webkul\User\Models\Group::where('name', $lead->department->name)->first();
            if ($group) {
                $groupId = $group->id;
            }
        }
        
        // Fallback to first available group if no department group found
        if (!$groupId) {
            $groupId = \Webkul\User\Models\Group::first()->id;
        }

        $activity = $this->activityRepository->create([
            'type'    => 'note',
            'comment' => request('comment'),
            'is_done' => 1,
            'user_id' => 1, // TODO: Replace with actual user ID when auth is implemented
            'lead_id' => $leadId,
            'group_id' => $groupId,
        ]);

        return response()->json([
            'message' => 'Note added successfully',
            'data'    => $activity,
        ]);
    }
}
