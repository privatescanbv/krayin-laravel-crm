<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesLead;
use Illuminate\Http\Request;
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
}
