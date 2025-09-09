<?php

namespace Webkul\Admin\Http\Controllers\Activity;

use App\Enums\CallStatusEnum;
use App\Models\CallStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Admin\Http\Controllers\Controller;

class CallStatusController extends Controller
{
    public function index(int $activityId): JsonResponse
    {
        $items = CallStatus::where('activity_id', $activityId)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, int $activityId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', array_map(fn($c) => $c->value, CallStatusEnum::cases())),
            'omschrijving' => 'nullable|string',
        ]);

        $callStatus = CallStatus::create([
            'activity_id' => $activityId,
            'status' => $validated['status'],
            'omschrijving' => $validated['omschrijving'] ?? null,
        ]);

        return response()->json([
            'message' => 'Call status toegevoegd',
            'data' => $callStatus,
        ]);
    }
}

