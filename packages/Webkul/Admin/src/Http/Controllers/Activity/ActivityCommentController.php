<?php

namespace Webkul\Admin\Http\Controllers\Activity;

use App\Models\ActivityComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Controller;

class ActivityCommentController extends Controller
{
    public function __construct(private readonly ActivityRepository $activityRepository) {}

    public function index(int $activityId): JsonResponse
    {
        $comments = ActivityComment::where('activity_id', $activityId)
            ->with('creator')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $comments]);
    }

    public function store(Request $request, int $activityId): JsonResponse
    {
        $this->activityRepository->findOrFail($activityId);

        $validated = $request->validate([
            'comment' => 'required|string|max:5000',
        ]);

        $comment = ActivityComment::create([
            'activity_id' => $activityId,
            'comment'     => $validated['comment'],
            'created_by'  => auth()->guard('user')->id(),
        ]);

        $comment->load('creator');

        return response()->json([
            'message' => 'Opmerking toegevoegd.',
            'data'    => $comment,
        ], 201);
    }

    public function destroy(int $activityId, int $commentId): JsonResponse
    {
        $comment = ActivityComment::where('activity_id', $activityId)->findOrFail($commentId);

        if ($comment->created_by !== auth()->guard('user')->id()) {
            return response()->json(['message' => 'Niet toegestaan'], 403);
        }

        $comment->delete();

        return response()->json(['message' => 'Opmerking verwijderd']);
    }
}
