<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Webkul\Lead\Models\Lead;

/**
 * @group Leads
 *
 * APIs for managing leads
 */
class LeadFormController extends Controller
{
    /**
     * Add form submission to a lead.
     *
     * Stores form submission data (questions and answers) for a lead.
     * The form data is logged for processing.
     *
     * The request body accepts dynamic keys where each key contains an array of [question, answer].
     *
     * @urlParam leadId integer required The ID of the lead. Example: 123
     *
     * @bodyParam insurance_type string[] Example question/answer pair. Example: ["What type of insurance do you have?", "Private"]
     * @bodyParam referral_source string[] Example question/answer pair. Example: ["How did you hear about us?", "Google"]
     *
     * @response 201 scenario="Success" {"message":"Form submission received.","lead_id":123,"form_keys":["insurance_type","referral_source"]}
     * @response 404 scenario="Lead not found" {"message":"Lead not found."}
     * @response 422 scenario="Validation error" {"message":"Invalid form data format. Each key must contain an array with [question, answer]."}
     */
    public function store(Request $request, int $leadId): JsonResponse
    {
        $lead = Lead::find($leadId);

        if (! $lead) {
            return response()->json([
                'message' => 'Lead not found.',
            ], 404);
        }

        $formData = $request->all();

        // Validate structure: each key should have [question, answer]
        foreach ($formData as $key => $value) {
            if (! is_array($value) || count($value) !== 2) {
                return response()->json([
                    'message' => 'Invalid form data format. Each key must contain an array with [question, answer].',
                ], 422);
            }
        }

        Log::info('Lead form submission received', [
            'lead_id'   => $leadId,
            'form_data' => $formData,
        ]);

        return response()->json([
            'message'   => 'Form submission received.',
            'lead_id'   => $leadId,
            'form_keys' => array_keys($formData),
        ], 201);
    }
}
