<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesLead;
use Illuminate\Http\Request;
use Webkul\Activity\Models\Activity;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Lead\Models\Lead;
use Webkul\Lead\Models\Pipeline;

class SalesLeadController extends Controller
{
    /**
     * Store a newly created workflow lead via API.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'              => 'nullable|string|max:255',
            'description'       => 'nullable|string',
            'pipeline_id'       => ['nullable', 'exists:lead_pipelines,id'],
            'pipeline_stage_id' => ['nullable', 'exists:lead_pipeline_stages,id'],
            'lead_id'           => ['required', 'exists:leads,id'],
            'user_id'           => ['nullable', 'exists:users,id'],
        ]);

        $lead = Lead::find($validated['lead_id']);
        if ($lead && empty($validated['name'])) {
            $validated['name'] = $lead->name;
            $validated['user_id'] = $lead->user_id;
        }
        // TODO choose hernia or privatescan
        $validated['pipeline_id'] = 3;
        $validated['pipeline_stage_id'] = Pipeline::find($validated['pipeline_id'])
            ->stages()
            ->orderByDesc('sort_order')
            ->firstOrFail()->id;

        $salesLead = SalesLead::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $salesLead,
        ], 201);
    }

    /**
     * List activities for a sales lead.
     */
    public function activities(int $id)
    {
        $salesLead = SalesLead::findOrFail($id);

        $query = Activity::where('sales_lead_id', $salesLead->id)->with('emails');

        if (request()->has('is_done')) {
            $isDone = (int) request('is_done') === 1 ? 1 : 0;
            $query->where('is_done', $isDone);
        }

        return ActivityResource::collection($query->get());
    }

    /**
     * Create an activity attached to a sales.
     */
    public function storeActivity(Request $request, int $id)
    {
        $validated = $request->validate([
            'type'          => 'required|in:task,meeting,call,note,file',
            'title'         => 'required_unless:type,note,file|string',
            'description'   => 'nullable|string',
            'comment'       => 'nullable|string',
            'user_id'       => 'nullable|exists:users,id',
            'schedule_from' => 'required_unless:type,note,file|date_format:Y-m-d H:i:s',
            'schedule_to'   => 'required_unless:type,note,file|date_format:Y-m-d H:i:s',
        ]);

        $salesLead = SalesLead::findOrFail($id);

        $activity = Activity::create([
            'type'             => $validated['type'],
            'title'            => $validated['title'] ?? null,
            'comment'          => $validated['comment'] ?? ($validated['description'] ?? null),
            'user_id'          => $validated['user_id'] ?? auth()->id(),
            'sales_lead_id'    => $salesLead->id,
            'schedule_from'    => $validated['schedule_from'] ?? null,
            'schedule_to'      => $validated['schedule_to'] ?? null,
            'is_done'          => 0,
        ]);

        return response()->json([
            'message' => 'Activity created successfully.',
            'data'    => new ActivityResource($activity),
        ], 201);
    }
}
