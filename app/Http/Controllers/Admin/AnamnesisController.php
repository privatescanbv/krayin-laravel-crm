<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationReferenceType;
use App\Events\PatientNotifyEvent;
use App\Helpers\Comparable;
use App\Http\Controllers\Concerns\HandlesReturnUrl;
use App\Http\Controllers\Controller;
use App\Models\Anamnesis;
use App\Services\FormService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Webkul\Lead\Repositories\LeadRepository;

class AnamnesisController extends Controller
{
    use Comparable, HandlesReturnUrl;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected FormService $formService
    ) {}

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): View
    {
        $anamnesis = Anamnesis::with('lead', 'sales')->findOrFail($id);

        return view('admin::anamnesis.edit', ['anamnesis' => $anamnesis]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $anamnesis = Anamnesis::findOrFail($id);

        $data = $request->validate([
            'name'                      => 'nullable|string|max:255',
            'description'               => 'nullable|string',
            'comment_clinic'            => 'nullable|string',
            'height'                    => 'nullable|integer',
            'weight'                    => 'nullable|integer',
            'metals'                    => 'required|in:0,1',
            'metals_notes'              => 'required_if:metals,1|nullable|string',
            'medications'               => 'required|in:0,1',
            'medications_notes'         => 'required_if:medications,1|nullable|string',
            'glaucoma'                  => 'required|in:0,1',
            'glaucoma_notes'            => 'required_if:glaucoma,1|nullable|string',
            'claustrophobia'            => 'required|in:0,1',
            'dormicum'                  => 'required|in:0,1',
            'heart_surgery'             => 'required|in:0,1',
            'heart_surgery_notes'       => 'required_if:heart_surgery,1|nullable|string',
            'implant'                   => 'required|in:0,1',
            'implant_notes'             => 'required_if:implant,1|nullable|string',
            'surgeries'                 => 'required|in:0,1',
            'surgeries_notes'           => 'required_if:surgeries,1|nullable|string',
            'remarks'                   => 'nullable|string|max:255',
            'hereditary_heart'          => 'required|in:0,1',
            'hereditary_heart_notes'    => 'required_if:hereditary_heart,1|nullable|string',
            'hereditary_vascular'       => 'required|in:0,1',
            'hereditary_vascular_notes' => 'required_if:hereditary_vascular,1|nullable|string',
            'hereditary_tumors'         => 'required|in:0,1',
            'hereditary_tumors_notes'   => 'required_if:hereditary_tumors,1|nullable|string',
            'allergies'                 => 'required|in:0,1',
            'allergies_notes'           => 'required_if:allergies,1|nullable|string',
            'back_problems'             => 'required|in:0,1',
            'back_problems_notes'       => 'required_if:back_problems,1|nullable|string',
            'heart_problems'            => 'required|in:0,1',
            'heart_problems_notes'      => 'required_if:heart_problems,1|nullable|string',
            'smoking'                   => 'required|in:0,1',
            'smoking_notes'             => 'required_if:smoking,1|nullable|string',
            'diabetes'                  => 'required|in:0,1',
            'diabetes_notes'            => 'required_if:diabetes,1|nullable|string',
            'digestive_problems'        => 'required|in:0,1',
            'digestive_problems_notes'  => 'required_if:digestive_problems,1|nullable|string',
            'heart_attack_risk'         => 'nullable|string',
            'active'                    => 'required|in:0,1',
            'advice_notes'              => 'nullable|string',
        ], [
            'metals.required'                       => 'Selecteer een antwoord voor Metalen.',
            'metals_notes.required_if'              => 'Vul een toelichting in bij Metalen.',
            'medications.required'                  => 'Selecteer een antwoord voor Medicijnen.',
            'medications_notes.required_if'         => 'Vul een toelichting in bij Medicijnen.',
            'glaucoma.required'                     => 'Selecteer een antwoord voor Glaucoom.',
            'glaucoma_notes.required_if'            => 'Vul een toelichting in bij Glaucoom.',
            'claustrophobia.required'               => 'Selecteer een antwoord voor Claustrofobie.',
            'dormicum.required'                     => 'Selecteer een antwoord voor Dormicum.',
            'heart_surgery.required'                => 'Selecteer een antwoord voor Hart operatie.',
            'heart_surgery_notes.required_if'       => 'Vul een toelichting in bij Hart operatie.',
            'implant.required'                      => 'Selecteer een antwoord voor Implantaat.',
            'implant_notes.required_if'             => 'Vul een toelichting in bij Implantaat.',
            'surgeries.required'                    => 'Selecteer een antwoord voor Operaties.',
            'surgeries_notes.required_if'           => 'Vul een toelichting in bij Operaties.',
            'hereditary_heart.required'             => 'Selecteer een antwoord voor Hart erfelijk.',
            'hereditary_heart_notes.required_if'    => 'Vul een toelichting in bij Hart erfelijk.',
            'hereditary_vascular.required'          => 'Selecteer een antwoord voor Vaat erfelijk.',
            'hereditary_vascular_notes.required_if' => 'Vul een toelichting in bij Vaat erfelijk.',
            'hereditary_tumors.required'            => 'Selecteer een antwoord voor Tumoren erfelijk.',
            'hereditary_tumors_notes.required_if'   => 'Vul een toelichting in bij Tumoren erfelijk.',
            'allergies.required'                    => 'Selecteer een antwoord voor Allergie.',
            'allergies_notes.required_if'           => 'Vul een toelichting in bij Allergie.',
            'back_problems.required'                => 'Selecteer een antwoord voor Rugklachten.',
            'back_problems_notes.required_if'       => 'Vul een toelichting in bij Rugklachten.',
            'heart_problems.required'               => 'Selecteer een antwoord voor Hartproblemen.',
            'heart_problems_notes.required_if'      => 'Vul een toelichting in bij Hartproblemen.',
            'smoking.required'                      => 'Selecteer een antwoord voor Roken.',
            'smoking_notes.required_if'             => 'Vul een toelichting in bij Roken.',
            'diabetes.required'                     => 'Selecteer een antwoord voor Diabetes.',
            'diabetes_notes.required_if'            => 'Vul een toelichting in bij Diabetes.',
            'digestive_problems.required'           => 'Selecteer een antwoord voor Spijsverteringsklachten.',
            'digestive_problems_notes.required_if'  => 'Vul een toelichting in bij Spijsverteringsklachten.',
            'active.required'                       => 'Selecteer een antwoord voor Actief.',
        ]);

        $anamnesis->update($data);

        return $this->redirectWithReturnUrl('admin.leads.view', [$anamnesis->lead_id], 'success', 'Anamnese is aangepast.');
    }

    public function attachGvlForm(Request $request, string $id): JsonResponse
    {
        $anamnesis = Anamnesis::with('person', 'lead')->findOrFail($id);

        if (! $anamnesis->person) {
            return response()->json([
                'message' => 'Anamnesis heeft geen gekoppelde persoon.',
            ], 422);
        }

        // Check if GVL form already exists
        if (! empty($anamnesis->gvl_form_link)) {
            return response()->json([
                'message'       => 'GVL formulier is al gekoppeld.',
                'gvl_form_link' => $anamnesis->gvl_form_link,
            ], 422);
        }

        try {
            // Create form request for this person
            $formLink = $this->createFormRequestForAnamnesis($anamnesis);

            // Reload the anamnesis to get updated gvl_form_link
            $anamnesis->refresh();

            // Notify patient that GVL form is ready
            PatientNotifyEvent::dispatch(
                $anamnesis->person_id,
                'GVL formulier beschikbaar',
                'Er staat een gezondheidsverklaring voor u klaar om in te vullen.',
                NotificationReferenceType::GVL_FORM,
                $anamnesis->id,
                false,
                auth()->id()
            );

            return response()->json([
                'message'       => 'GVL formulier is gekoppeld.',
                'gvl_form_link' => $formLink,
            ], 200);
        } catch (Exception $e) {
            Log::error('AnamnesisController@attachGvlForm failed', [
                'anamnesis_id' => $id,
                'error'        => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'GVL formulier koppelen is mislukt: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create anamnesis and attach GVL form for a person (used from order edit page)
     */
    public function createAndAttachGvlForm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lead_id'   => 'required|integer|exists:leads,id',
            'person_id' => 'required|integer|exists:persons,id',
        ]);

        $leadId = $data['lead_id'];
        $personId = $data['person_id'];

        try {
            // Get or create anamnesis
            $lead = $this->leadRepository->find($leadId);
            if (! $lead) {
                return response()->json([
                    'message' => 'Lead niet gevonden.',
                ], 404);
            }

            $anamnesis = Anamnesis::firstOrCreate(
                [
                    'lead_id'   => $leadId,
                    'person_id' => $personId,
                ],
                [
                    'id'         => Str::uuid(),
                    'name'       => 'Anamnesis voor '.$lead->name,
                    'created_by' => auth()->id() ?? $lead->user_id ?? 1,
                    'updated_by' => auth()->id() ?? $lead->user_id ?? 1,
                ]
            );

            // Load relations
            $anamnesis->load('person', 'lead');

            if (! $anamnesis->person) {
                return response()->json([
                    'message' => 'Persoon niet gevonden.',
                ], 422);
            }

            // Check if GVL form already exists
            if (! empty($anamnesis->gvl_form_link)) {
                return response()->json([
                    'message'       => 'GVL formulier is al gekoppeld.',
                    'gvl_form_link' => $anamnesis->gvl_form_link,
                ], 422);
            }

            // Create form request for this person
            $formLink = $this->createFormRequestForAnamnesis($anamnesis);

            // Reload the anamnesis to get updated gvl_form_link
            $anamnesis->refresh();

            // Notify patient that GVL form is ready
            PatientNotifyEvent::dispatch(
                $anamnesis->person_id,
                'GVL formulier beschikbaar',
                'Er staat een gezondheidsverklaring voor u klaar om in te vullen.',
                NotificationReferenceType::GVL_FORM,
                $anamnesis->id,
                false,
                auth()->id()
            );

            return response()->json([
                'message'       => 'Anamnesis aangemaakt en GVL formulier gekoppeld.',
                'gvl_form_link' => $formLink,
                'anamnesis_id'  => $anamnesis->id,
            ], 200);
        } catch (Exception $e) {
            Log::error('AnamnesisController@createAndAttachGvlForm failed', [
                'lead_id'   => $leadId,
                'person_id' => $personId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Anamnesis aanmaken en GVL formulier koppelen is mislukt: '.$e->getMessage(),
            ], 500);
        }
    }

    public function detachGvlForm(Request $request, string $id): JsonResponse
    {
        return $this->doDetachGvlForm($id);
    }

    public function cleanUpForLead(string $personId, string $leadId): void
    {
        $anamnesis = Anamnesis::select(['id', 'gvl_form_link'])
            ->where('person_id', $personId)
            ->where('lead_id', $leadId)
            ->firstOrFail();
        logger()->info('Running clean up action for lead, gvl form found: '.$anamnesis->gvl_form_link);
        if ($anamnesis->gvl_form_link) {
            $formId = $this->formService->extractFormIdFromUrl($anamnesis->gvl_form_link);
            if (! $this->formService->getFormStatusAsString($formId)->isCompleted()) {
                $this->doDetachGvlForm($anamnesis->id);
            }
        }
    }

    /**
     * Removed the form and removes the relation with anamnesis
     */
    public function doDetachGvlForm(string $anamnesisid): JsonResponse
    {
        $anamnesis = Anamnesis::findOrFail($anamnesisid);

        if (empty($anamnesis->gvl_form_link)) {
            return response()->json([
                'message' => 'Er is geen GVL formulier gekoppeld aan deze anamnesis.',
            ], 422);
        }

        try {
            $formId = $this->formService->extractFormIdFromUrl($anamnesis->gvl_form_link);

            if ($formId === null) {
                Log::error('AnamnesisController@detachGvlForm could not resolve form id from URL', [
                    'anamnesis_id'  => $anamnesisid,
                    'gvl_form_link' => $anamnesis->gvl_form_link,
                ]);
                throw new Exception('Could not resolve form id from url: '.$anamnesis->gvl_form_link);
            }

            $result = $this->formService->deleteForm($formId);
            $status = $result['status'];
            $json = $result['response'];

            if ($status !== 200) {
                Log::warning('AnamnesisController@detachGvlForm Forms API fout', [
                    'anamnesis_id'  => $anamnesisid,
                    'form_id'       => $formId,
                    'status'        => $status,
                    'response_json' => $json,
                ]);

                return response()->json([
                    'message' => $json['message'] ?? 'GVL formulier ontkoppelen is mislukt.',
                ], $status ?: 500);
            }

            $anamnesis->update([
                'gvl_form_link' => null,
            ]);

            Log::info('AnamnesisController@detachGvlForm geslaagd', [
                'anamnesis_id' => $anamnesisid,
            ]);

            return response()->json([
                'message' => 'GVL formulier is ontkoppeld.',
            ]);
        } catch (Exception $e) {
            Log::error('AnamnesisController@detachGvlForm failed', [
                'anamnesis_id' => $anamnesisid,
                'error'        => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'GVL formulier ontkoppelen is mislukt: '.$e->getMessage(),
            ], 500);
        }
    }

    public function getGvlFormStatus(Request $request, string $id): JsonResponse
    {
        return $this->getGvlFormStatus2($id);
    }

    public function getGvlFormStatus2(string $anamnesisId): JsonResponse
    {
        $anamnesis = Anamnesis::findOrFail($anamnesisId);

        if (empty($anamnesis->gvl_form_link)) {
            return response()->json([
                'message' => 'Er is geen GVL formulier gekoppeld aan deze anamnesis.',
            ], 422);
        }

        try {
            $formId = $this->formService->extractFormIdFromUrl($anamnesis->gvl_form_link);

            if (! $formId) {
                return response()->json([
                    'message' => 'Kon formulier ID niet extraheren uit URL.',
                ], 422);
            }

            $status = $this->formService->getFormStatus($formId);

            return response()->json([
                'data' => $status,
            ]);
        } catch (Exception $e) {
            Log::error('AnamnesisController@getGvlFormStatus failed', [
                'anamnesis_id' => $anamnesisId,
                'error'        => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Fout bij ophalen formulier status: '.$e->getMessage(),
            ], 500);
        }
    }

    public function syncLatestWithOlder(string $personId)
    {
        $lastAnamnesis = $this->lastAnamnesisByPersonId($personId);
        if (is_null($lastAnamnesis)) {
            abort(404, 'Geen anamnese gevonden voor deze persoon.');
        }
        $olderAnamnises = Anamnesis::where('id', '!=', $lastAnamnesis->id)
            ->where('person_id', $personId)
            ->where('deleted', 0)
            ->orderBy('created_at', 'desc')
            ->with('lead')
            ->get();

        $lastLeadId = $lastAnamnesis->lead_id;

        $matchBreakdown = $this->buildAnamnesisMatchBreakdown($lastAnamnesis, $olderAnamnises);

        // neem de beste match (hoogste percentage)
        $bestMatch = collect($matchBreakdown)->sortByDesc('percentage')->first();

        $anamnesis = $lastAnamnesis;
        $person = $lastAnamnesis->person;

        return view(
            'adminc::leads.sync-anamnesis',
            [
                'anamnesis'       => $anamnesis,
                'matchBreakdown'  => $matchBreakdown,
                'bestMatch'       => $bestMatch,
                'lastLeadId'      => $lastLeadId,
                'olderAnamnises'  => $olderAnamnises,
                'person'          => $person,
            ]
        );
    }

    /**
     * @throws Exception if no anamnesis found for person
     */
    public function storeSyncLatestWithOlder(Request $request, string $personId)
    {
        $anamnesis = $this->lastAnamnesisByPersonId($personId);
        if (is_null($anamnesis)) {
            throw new Exception('Geen anamnese gevonden voor deze persoon.'.$personId);
        }
        $data = $request->validate([
            'choice'   => 'required|array',
            'choice.*' => 'required|string', // 'current' or anamnesis id
        ]);

        $updates = [];
        // Get all referenced older anamneses in one query
        $referencedIds = array_unique(array_filter(array_values($data['choice']), fn ($v) => $v !== 'current'));

        if (! empty($referencedIds)) {
            $olderAnamnises = Anamnesis::whereIn('id', $referencedIds)->get()->keyBy('id');

            foreach ($data['choice'] as $field => $choice) {
                if ($choice === 'current') {
                    continue;
                }

                if (isset($olderAnamnises[$choice])) {
                    $olderAnamnesis = $olderAnamnises[$choice];
                    // Verify the field exists on the model to avoid errors
                    if (array_key_exists($field, $olderAnamnesis->getAttributes())) {
                        $updates[$field] = $olderAnamnesis->$field;
                    }
                }
            }
        }

        if (! empty($updates)) {
            $anamnesis->update($updates);
            session()->flash('success', 'Anamnesis is bijgewerkt met gegevens van oudere anamnese(s).');
        } else {
            session()->flash('info', 'Geen wijzigingen doorgevoerd.');
        }

        if ($request->wantsJson()) {
            $resolvedReturnUrl = $this->resolveReturnUrl();

            return response()->json([
                'message'      => 'Anamnesis succesvol bijgewerkt.',
                'redirect_url' => $resolvedReturnUrl ?: route('admin.leads.view', $anamnesis->lead_id),
            ]);
        }

        return $this->redirectWithReturnUrl(
            'admin.leads.view',
            [$anamnesis->lead_id],
            'success',
            'Anamnesis succesvol bijgewerkt.'
        );
    }

    /**
     * Create a form request for anamnesis and return the form link.
     */
    protected function createFormRequestForAnamnesis(Anamnesis $anamnesis): string
    {
        if (! $anamnesis->person) {
            throw new Exception('Anamnesis heeft geen gekoppelde persoon.');
        }

        if (! $anamnesis->lead) {
            throw new Exception('Anamnesis heeft geen gekoppelde lead.');
        }

        $person = $anamnesis->person;
        $lead = $anamnesis->lead;

        // Build form data
        $email = $person->findDefaultEmail();
        if (! $email) {
            throw new Exception('Persoon heeft geen e-mailadres.');
        }

        $birthday = $person->date_of_birth
            ? $person->date_of_birth->format('d-m-Y')
            : '01-01-1900';

        $firstName = $person->first_name ?? '';
        $lastName = $person->last_name ?? '';

        if (empty($firstName) && empty($lastName) && ! empty($person->name)) {
            $nameParts = explode(' ', $person->name, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? $firstName;
        }

        // Determine form type from lead department
        $department = $lead->department;
        $formType = $department && method_exists($department, 'isHernia') && $department->isHernia() ? 'herniapoli' : 'privatescan';

        $formData = [
            'user_crm_id'     => $person->id,
            'user_firstname'  => $firstName ?: '-',
            'user_lastname'   => $lastName ?: '-',
            'user_maidenname' => ! empty($person->married_name) ? $person->married_name : '--',
            'user_email'      => $email,
            'user_birthday'   => $birthday,
            'mri_research'    => 'Nee', // Default, can be updated later
            'ct_scan'         => 'Nee', // Default, can be updated later
            'form_type'       => $formType,
        ];

        $url = $this->formService->buildApiUrl('/api/forms');
        $response = $this->formService->makeRequest('post', $url, ['url' => $url], $formData);
        $result = $this->formService->parseResponse($response, ['url' => $url], true);

        if ($result['is_html'] || ! $response->successful()) {
            $errorMessage = 'Forms API fout';
            $errorDetails = [
                'anamnesis_id'     => $anamnesis->id,
                'person_id'        => $person->id,
                'lead_id'          => $lead->id,
                'http_status'      => $result['status'],
                'is_html'          => $result['is_html'],
                'response'         => $result['json'],
                'response_body'    => $response->body(),
                'response_message' => $result['json']['message'] ?? null,
                'response_errors'  => $result['json']['errors'] ?? null,
            ];

            Log::error('AnamnesisController: Forms API error', $errorDetails);

            // Build a more descriptive error message
            if ($result['is_html']) {
                $errorMessage = 'Forms API authentication failed: received HTML login page instead of JSON. Please check FORMS_API_KEY configuration.';
            } elseif ($result['json'] && isset($result['json']['message'])) {
                $errorMessage .= ': '.$result['json']['message'];
            } elseif ($result['json'] && isset($result['json']['errors'])) {
                $errorMessage .= ': '.json_encode($result['json']['errors']);
            } elseif (! $result['json']) {
                $errorMessage .= ': API returned no response';
                if ($result['status']) {
                    $errorMessage .= " (HTTP {$result['status']})";
                }
            } else {
                $errorMessage .= ': Server Error (HTTP '.$result['status'].')';
            }

            throw new Exception($errorMessage);
        }

        if (! isset($result['json']['data']['id'])) {
            $errorDetails = [
                'anamnesis_id'  => $anamnesis->id,
                'person_id'     => $person->id,
                'lead_id'       => $lead->id,
                'http_status'   => $result['status'],
                'response'      => $result['json'],
                'response_body' => $response->body(),
                'response_keys' => $result['json'] ? array_keys($result['json']) : null,
            ];

            Log::error('AnamnesisController: Forms API response missing form ID', $errorDetails);

            throw new Exception('Forms API response mist formulier ID');
        }

        $formLink = $result['json']['form_url'] ?? throw new Exception('Missing form_url in API response');

        // Save the link to the anamnesis
        $anamnesis->update([
            'gvl_form_link' => $formLink,
        ]);

        return $formLink;
    }

    // todo move to repro
    private function lastAnamnesisByPersonId(string $personId): ?Anamnesis
    {
        return Anamnesis::with(['lead', 'person'])
            ->where('person_id', $personId)
            ->orderBy('created_at', 'desc')
            ->first();

    }

    /**
     * Vergelijk huidige Anamnesis met meerdere oudere Anamneses.
     *
     * @return array<string, array{
     *     percentage: float,
     *     total_fields: int,
     *     matching_fields: int,
     *     field_differences: array<string, array{
     *         label: string,
     *         new_value: null|string,
     *         old_value: null|string,
     *         type: string
     *     }>
     * }>
     */
    private function buildAnamnesisMatchBreakdown(Anamnesis $anamnesis, Collection $olderAnamnese): array
    {
        $comparableFields = Anamnesis::getFieldsToCompare();
        $results = [];

        foreach ($olderAnamnese as $oldAnamnesis) {
            $totalFields = 0;
            $matchingFields = 0;
            $fieldDifferences = [];

            foreach ($comparableFields as $field => $labelKey) {
                $label = __($labelKey);
                $newValue = $anamnesis->$field ?? '';
                $oldValue = $oldAnamnesis->$field ?? null;

                $totalFields++;
                $isMatch = $this->valuesMatch($newValue, $oldValue, $field, 'anamnesis');

                if ($isMatch) {
                    $matchingFields++;
                } else {
                    $fieldDifferences[$field] = [
                        'label'     => $label,
                        'new_value' => $this->formatValueForDisplay($newValue, $field),
                        'old_value' => $this->formatValueForDisplay($oldValue, $field),
                        'type'      => $this->getFieldType($field, $oldValue, $newValue),
                    ];
                }
            }
            $percentage = $totalFields > 0 ? ($matchingFields / $totalFields) * 100 : 0;
            $resultKey = $oldAnamnesis->id;

            $results[$resultKey] = [
                'percentage'        => round($percentage, 1),
                'total_fields'      => $totalFields,
                'matching_fields'   => $matchingFields,
                'field_differences' => $fieldDifferences,
            ];
        }

        return $results;
    }

    /**
     * Check if two values match for comparison.
     */
    private function valuesMatch($leadValue, $personValue, $field, string $perspective = 'generic'): bool
    {
        // Handle regular string fields
        $leadNormalized = self::normalizeValue($leadValue);
        $personNormalized = self::normalizeValue($personValue);

        return $leadNormalized === $personNormalized;
    }
}
