<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FormStatus;
use App\Enums\FormType;
use App\Enums\NotificationReferenceType;
use App\Events\PatientNotifyEvent;
use App\Helpers\Comparable;
use App\Http\Controllers\Concerns\HandlesReturnUrl;
use App\Http\Controllers\Controller;
use App\Models\Anamnesis;
use App\Models\AnamnesisGvlForm;
use App\Models\Department;
use App\Models\Order;
use App\Models\PatientNotification;
use App\Models\SalesLead;
use App\Services\Anamnesis\AnamnesisFormsOverviewBuilder;
use App\Services\Anamnesis\AnamnesisOrderResolver;
use App\Services\FormService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Webkul\Contact\Models\Person;

class AnamnesisController extends Controller
{
    use Comparable, HandlesReturnUrl;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected FormService $formService,
        protected AnamnesisOrderResolver $anamnesisOrderResolver,
        protected AnamnesisFormsOverviewBuilder $formsOverviewBuilder,
    ) {}

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): View
    {
        $anamnesis = Anamnesis::with(['lead', 'sales', 'order', 'person', 'gvlForms'])->findOrFail($id);

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
            'dormicum_notes'            => 'required_if:dormicum,1|nullable|string',
            'heart_surgery'             => 'required|in:0,1',
            'heart_surgery_notes'       => 'required_if:heart_surgery,1|nullable|string',
            'implant'                   => 'required|in:0,1',
            'implant_notes'             => 'required_if:implant,1|nullable|string',
            'surgeries'                 => 'required|in:0,1',
            'surgeries_notes'           => 'required_if:surgeries,1|nullable|string',
            'hereditary_heart'          => 'required|in:0,1',
            'hereditary_heart_notes'    => 'required_if:hereditary_heart,1|nullable|string',
            'hereditary_vascular'       => 'required|in:0,1',
            'hereditary_vascular_notes' => 'required_if:hereditary_vascular,1|nullable|string',
            'hereditary_tumors'         => 'required|in:0,1',
            'hereditary_tumors_notes'   => 'required_if:hereditary_tumors,1|nullable|string',
            'allergies'                 => 'required|in:0,1',
            'allergies_notes'           => 'required_if:allergies,1|nullable|string',
            'back_problems'             => 'required|in:0,1',
            'back_problems_notes'       => 'required_if:back_problems,0|nullable|string',
            'heart_problems'            => 'required|in:0,1',
            'heart_problems_notes'      => 'required_if:heart_problems,1|nullable|string',
            'smoking'                   => 'required|in:0,1',
            'smoking_notes'             => 'required_if:smoking,1|nullable|string',
            'diabetes'                  => 'required|in:0,1',
            'diabetes_notes'            => 'required_if:diabetes,1|nullable|string',
            'infectious_disease'        => 'required|in:0,1',
            'infectious_disease_notes'  => 'required_if:infectious_disease,1|nullable|string',
            'digestive_problems'        => 'required|in:0,1',
            'digestive_problems_notes'  => 'required_if:digestive_problems,1|nullable|string',
            'heart_attack_risk'         => 'nullable|string',
            'active'                    => 'required|in:0,1',
        ], [
            'metals.required'                       => 'Selecteer een antwoord voor Metalen.',
            'metals_notes.required_if'              => 'Vul een toelichting in bij Metalen.',
            'medications.required'                  => 'Selecteer een antwoord voor Medicijnen.',
            'medications_notes.required_if'         => 'Vul een toelichting in bij Medicijnen.',
            'glaucoma.required'                     => 'Selecteer een antwoord voor Glaucoom.',
            'glaucoma_notes.required_if'            => 'Vul een toelichting in bij Glaucoom.',
            'claustrophobia.required'               => 'Selecteer een antwoord voor Claustrofobie.',
            'dormicum.required'                     => 'Selecteer een antwoord voor Dormicum.',
            'dormicum_notes.required_if'            => 'Vul in welk rustgevend middel gewenst is.',
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
            'infectious_disease.required'           => 'Selecteer een antwoord voor Infectieziekte.',
            'infectious_disease_notes.required_if'  => 'Vul een toelichting in bij Infectieziekte.',
            'digestive_problems.required'           => 'Selecteer een antwoord voor Spijsverteringsklachten.',
            'digestive_problems_notes.required_if'  => 'Vul een toelichting in bij Spijsverteringsklachten.',
            'active.required'                       => 'Selecteer een antwoord voor Actief.',
        ]);

        $anamnesis->update($data);

        $entityUrl = $this->entityViewUrlForAnamnesis($anamnesis);
        $resolvedReturnUrl = $this->resolveReturnUrl();

        return redirect($resolvedReturnUrl ?: $entityUrl)->with('success', 'Anamnese is aangepast.');
    }

    public function attachGvlForm(Request $request, string $id): JsonResponse
    {
        $anamnesis = Anamnesis::with('person', 'lead', 'order', 'sales')->findOrFail($id);
        $formTypeOverride = $request->input('form_type');
        $force = $request->boolean('force');

        try {
            if (! $force) {
                $duplicateResponse = $this->duplicateAttachConfirmationResponse($anamnesis, $formTypeOverride);
                if ($duplicateResponse !== null) {
                    return $duplicateResponse;
                }
            }

            return $this->attachGvlFormToAnamnesis($anamnesis, $formTypeOverride);
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
     * Set up a Herniapoli diagnose form (lage rugpijn / nekpijn) in the patient portal
     * from a Sale, for an existing patient — no new lead. Reuses the GVL machinery.
     */
    public function attachDiagnosisFormForSales(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sales_id'  => 'required|integer|exists:salesleads,id',
            'person_id' => 'required|integer|exists:persons,id',
            'form_type' => 'required|string|in:'.implode(',', array_map(
                fn (FormType $t) => $t->value,
                FormType::diagnosisCases()
            )),
            'force'     => 'sometimes|boolean',
        ]);

        $salesId = (int) $data['sales_id'];
        $personId = (int) $data['person_id'];

        if (! SalesLead::isHerniaPoli($salesId)) {
            abort(403, 'Diagnoseformulier klaarzetten kan alleen bij een Herniapoli-Sale.');
        }

        $salesLead = SalesLead::with('persons')->findOrFail($salesId);

        if (! $salesLead->persons->contains('id', $personId)) {
            return $this->redirectWithReturnUrl('admin.sales-leads.view', [$salesId], 'error', 'Persoon hoort niet bij deze Sale.');
        }

        $person = Person::find($personId);
        if (empty($person?->keycloak_user_id)) {
            return $this->redirectWithReturnUrl('admin.sales-leads.view', [$salesId], 'error', 'Patiënt heeft geen patiëntportaal account. Maak dat eerst aan.');
        }

        $anamnesis = Anamnesis::firstOrCreate(
            [
                'sales_id'  => $salesId,
                'person_id' => $personId,
            ],
            [
                'id'         => (string) Str::uuid(),
                'name'       => 'Anamnese voor '.$person->name,
                'created_by' => auth()->id() ?? $salesLead->user_id ?? 1,
                'updated_by' => auth()->id() ?? $salesLead->user_id ?? 1,
            ]
        );

        // Max one form per diagnose type — the other type is a separate action.
        if ($anamnesis->gvlForms()->where('gvl_form_type', $data['form_type'])->exists()) {
            return $this->redirectWithReturnUrl('admin.sales-leads.view', [$salesId], 'warning', 'Dit diagnoseformulier is al klaargezet. Ontkoppel het eerst om een nieuw exemplaar te maken.');
        }

        $anamnesis->load('person');

        if (! $request->boolean('force')) {
            $duplicateResponse = $this->duplicateAttachConfirmationResponse($anamnesis, $data['form_type']);
            if ($duplicateResponse !== null) {
                return $this->redirectWithReturnUrl(
                    'admin.sales-leads.view',
                    [$salesId],
                    'warning',
                    $duplicateResponse->getData(true)['message'] ?? 'Formulier van hetzelfde type bestaat al op een ander niveau.'
                );
            }
        }

        try {
            $this->attachGvlFormToAnamnesis($anamnesis, $data['form_type']);
        } catch (Exception $e) {
            Log::error('AnamnesisController@attachDiagnosisFormForSales failed', [
                'sales_id'  => $salesId,
                'person_id' => $personId,
                'form_type' => $data['form_type'],
                'error'     => $e->getMessage(),
            ]);

            return $this->redirectWithReturnUrl('admin.sales-leads.view', [$salesId], 'error', 'Diagnoseformulier klaarzetten is mislukt: '.$e->getMessage());
        }

        return $this->redirectWithReturnUrl('admin.sales-leads.view', [$salesId], 'success', 'Diagnoseformulier is klaargezet in het patiëntenportaal.');
    }

    /**
     * Detach a Herniapoli diagnose form (plain form POST from the Sale anamnese block).
     */
    public function detachDiagnosisForm(Request $request): RedirectResponse
    {
        $request->validate([
            'gvl_form_record_id' => 'required|integer|exists:anamnesis_gvl_forms,id',
        ]);

        $gvlForm = AnamnesisGvlForm::with('anamnesis')->findOrFail($request->integer('gvl_form_record_id'));
        $salesId = $gvlForm->anamnesis?->sales_id;

        if (! $gvlForm->gvl_form_type?->isDiagnosisForm() || ! $salesId) {
            abort(404);
        }

        $response = $this->doDetachGvlFormRecord($gvlForm);

        return $response->getStatusCode() === 200
            ? $this->redirectWithReturnUrl('admin.sales-leads.view', [$salesId], 'success', 'Diagnoseformulier is ontkoppeld.')
            : $this->redirectWithReturnUrl('admin.sales-leads.view', [$salesId], 'error', $response->getData(true)['message'] ?? 'Ontkoppelen is mislukt.');
    }

    public function detachGvlForm(Request $request, string $id, int $gvlFormRecordId): JsonResponse
    {
        $gvlForm = AnamnesisGvlForm::findOrFail($gvlFormRecordId);

        if ($gvlForm->anamnesis_id !== $id) {
            return response()->json(['message' => 'Formulier behoort niet tot deze anamnesis.'], 403);
        }

        return $this->doDetachGvlFormRecord($gvlForm);
    }

    public function cleanUpForLead(string $personId, string $leadId): void
    {
        $anamnesis = Anamnesis::where('person_id', $personId)
            ->where('lead_id', $leadId)
            ->first();

        if (! $anamnesis) {
            return;
        }

        $incomplete = $anamnesis->gvlForms()
            ->whereNotIn('gvl_form_status', [FormStatus::Completed->value])
            ->get();

        logger()->info('Running clean up for lead, incomplete gvl forms: '.$incomplete->count());

        foreach ($incomplete as $gvlForm) {
            $this->doDetachGvlFormRecord($gvlForm);
        }
    }

    public function getGvlFormStatus(string $anamnesisId, int $gvlFormRecordId): JsonResponse
    {
        $gvlForm = AnamnesisGvlForm::findOrFail($gvlFormRecordId);

        if ($gvlForm->anamnesis_id !== $anamnesisId) {
            return response()->json(['message' => 'Formulier behoort niet tot deze anamnesis.'], 403);
        }

        return response()->json([
            'data' => [
                'status' => $gvlForm->gvl_form_status?->value,
            ],
        ]);
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

        $matchBreakdown = $this->buildAnamnesisMatchBreakdown($lastAnamnesis, $olderAnamnises);

        $bestMatch = collect($matchBreakdown)->sortByDesc('percentage')->first();

        $anamnesis = $lastAnamnesis;
        $person = $lastAnamnesis->person;
        $entityUrl = $this->entityViewUrlForAnamnesis($lastAnamnesis);

        return view(
            'adminc::leads.sync-anamnesis',
            [
                'anamnesis'       => $anamnesis,
                'matchBreakdown'  => $matchBreakdown,
                'bestMatch'       => $bestMatch,
                'entityUrl'       => $entityUrl,
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

        $entityUrl = $this->entityViewUrlForAnamnesis($anamnesis);

        if ($request->wantsJson()) {
            $resolvedReturnUrl = $this->resolveReturnUrl();

            return response()->json([
                'message'      => 'Anamnesis succesvol bijgewerkt.',
                'redirect_url' => $resolvedReturnUrl ?: $entityUrl,
            ]);
        }

        $resolvedReturnUrl = $this->resolveReturnUrl();

        return redirect($resolvedReturnUrl ?: $entityUrl)
            ->with('success', 'Anamnesis succesvol bijgewerkt.');
    }

    /**
     * Create an override anamnesis at the order or sales level.
     * Copies data from the source (inherited) anamnesis if provided.
     */
    public function override(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_id'             => 'nullable|integer|exists:orders,id',
            'sales_id'             => 'nullable|integer|exists:salesleads,id',
            'person_id'            => 'required|integer|exists:persons,id',
            'source_anamnesis_id'  => 'nullable|string|exists:anamnesis,id',
        ]);

        if (empty($data['order_id']) && empty($data['sales_id'])) {
            return back()->with('error', 'Order ID of Sales ID is verplicht.');
        }

        $personId = (int) $data['person_id'];

        $lookupKey = ! empty($data['order_id'])
            ? ['order_id' => (int) $data['order_id'], 'person_id' => $personId]
            : ['sales_id' => (int) $data['sales_id'], 'person_id' => $personId];

        $existing = Anamnesis::where($lookupKey)->first();
        if ($existing) {
            return back()->with('warning', 'Er bestaat al een anamnese op dit niveau voor deze persoon.');
        }

        $copyFields = [];
        if (! empty($data['source_anamnesis_id'])) {
            $source = Anamnesis::find($data['source_anamnesis_id']);
            if ($source) {
                $excludeFields = ['id', 'lead_id', 'sales_id', 'order_id', 'person_id', 'created_at', 'updated_at', 'created_by', 'updated_by', 'gvl_form_id', 'gvl_form_status', 'gvl_form_type'];
                $copyFields = collect($source->attributesToArray())
                    ->except($excludeFields)
                    ->toArray();
            }
        }

        $createData = array_merge($copyFields, $lookupKey, [
            'id'         => (string) Str::uuid(),
            'name'       => 'Anamnese (overschrijving) voor '.Person::findOrFail($personId)->name,
            'created_by' => auth()->id() ?? 1,
            'updated_by' => auth()->id() ?? 1,
        ]);

        Anamnesis::create($createData);

        return $this->redirectWithReturnUrl('admin.leads.view', [], 'success', 'Anamnese overschrijving aangemaakt.');
    }

    /**
     * Revert (delete) an override anamnesis at the order or sales level.
     * Falls back to the inherited anamnesis from the parent level.
     */
    public function revertOverride(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_id'  => 'nullable|integer|exists:orders,id',
            'sales_id'  => 'nullable|integer|exists:salesleads,id',
            'person_id' => 'required|integer|exists:persons,id',
        ]);

        if (empty($data['order_id']) && empty($data['sales_id'])) {
            return back()->with('error', 'Order ID of Sales ID is verplicht.');
        }

        $personId = (int) $data['person_id'];

        $lookupKey = ! empty($data['order_id'])
            ? ['order_id' => (int) $data['order_id'], 'person_id' => $personId]
            : ['sales_id' => (int) $data['sales_id'], 'person_id' => $personId];

        $anamnesis = Anamnesis::where($lookupKey)->first();

        if (! $anamnesis) {
            return $this->redirectWithReturnUrl('admin.leads.view', [], 'warning', 'Geen overschrijving gevonden om terug te zetten.');
        }

        foreach ($anamnesis->gvlForms as $gvlForm) {
            $this->doDetachGvlFormRecord($gvlForm);
        }

        $anamnesis->delete();

        return $this->redirectWithReturnUrl('admin.leads.view', [], 'success', 'Anamnese overschrijving verwijderd. De anamnese van het bovenliggende niveau wordt nu weer gebruikt.');
    }

    public function mapFormTypeFromDepartment(?Department $department): string
    {
        return $department && $department->isHernia() ? FormType::HerniaNarcoseForm->value : FormType::PrivateScan->value;
    }

    /**
     * Create a form request for anamnesis and return the form link.
     */
    protected function createFormRequestForAnamnesis(Anamnesis $anamnesis, ?string $formTypeOverride = null): array
    {
        if (! $anamnesis->person) {
            throw new Exception('Anamnesis heeft geen gekoppelde persoon.');
        }

        $person = $anamnesis->person;

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

        $lastNameWithPrefix = trim(($person->lastname_prefix ? $person->lastname_prefix.' ' : '').$lastName);
        $maidenNameWithPrefix = trim(($person->married_name_prefix ? $person->married_name_prefix.' ' : '').($person->married_name ?? ''));

        // Use explicit override if provided, otherwise derive from department
        if ($formTypeOverride && FormType::tryFrom($formTypeOverride)) {
            $formType = $formTypeOverride;
        } else {
            $department = $this->anamnesisOrderResolver->resolveFormDepartment($anamnesis);
            $formType = $this->mapFormTypeFromDepartment($department);
        }

        // A Herniapoli diagnose form is only allowed from a Herniapoli Sale — never lead/order.
        if (FormType::tryFrom($formType)?->isDiagnosisForm()
            && (! $anamnesis->sales_id || ! SalesLead::isHerniaPoli((int) $anamnesis->sales_id))) {
            throw new Exception('Een diagnoseformulier kan alleen vanuit een Herniapoli-Sale worden klaargezet.');
        }

        $formData = [
            'user_crm_id'     => $person->id,
            'user_firstname'  => $firstName ?: '-',
            'user_lastname'   => $lastNameWithPrefix ?: '-',
            'user_maidenname' => $maidenNameWithPrefix ?: '--',
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
                'lead_id'          => $anamnesis->lead_id,
                'order_id'         => $anamnesis->order_id,
                'sales_id'         => $anamnesis->sales_id,
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
                'lead_id'       => $anamnesis->lead_id,
                'order_id'      => $anamnesis->order_id,
                'sales_id'      => $anamnesis->sales_id,
                'http_status'   => $result['status'],
                'response'      => $result['json'],
                'response_body' => $response->body(),
                'response_keys' => $result['json'] ? array_keys($result['json']) : null,
            ];

            Log::error('AnamnesisController: Forms API response missing form ID', $errorDetails);

            throw new Exception('Forms API response mist formulier ID');
        }

        $formLink = $result['json']['form_url'] ?? throw new Exception('Missing form_url in API response');

        $extractedFormId = $this->formService->extractFormIdFromUrl($formLink);

        $gvlFormRecord = AnamnesisGvlForm::create([
            'anamnesis_id'    => $anamnesis->id,
            'gvl_form_id'     => $extractedFormId,
            'gvl_form_type'   => $formType,
            'gvl_form_status' => null, // boot hook sets to New
        ]);

        return [$gvlFormRecord, $formLink];
    }

    private function doDetachGvlFormRecord(AnamnesisGvlForm $gvlForm): JsonResponse
    {
        $formId = $gvlForm->gvl_form_id;
        $anamnesisId = $gvlForm->anamnesis_id;
        $personId = $gvlForm->anamnesis?->person_id ?? Anamnesis::find($anamnesisId)?->person_id;

        try {
            if ($formId) {
                $result = $this->formService->deleteForm($formId);
                $status = $result['status'];
                $json = $result['response'];

                if ($status !== 200) {
                    Log::warning('AnamnesisController@detachGvlForm Forms API fout', [
                        'gvl_form_record_id' => $gvlForm->id,
                        'form_id'            => $formId,
                        'status'             => $status,
                        'response_json'      => $json,
                    ]);

                    return response()->json([
                        'message' => $json['message'] ?? 'GVL formulier ontkoppelen is mislukt.',
                    ], $status ?: 500);
                }
            }

            if ($personId && $formId) {
                PatientNotification::where('reference_id', $formId)
                    ->whereIn('reference_type', [
                        NotificationReferenceType::GVL_FORM,
                        NotificationReferenceType::DIAGNOSIS_FORM,
                    ])
                    ->where('patient_id', $personId)
                    ->delete();
            }

            $gvlForm->delete();

            Log::info('AnamnesisController@detachGvlForm geslaagd', [
                'gvl_form_record_id' => $gvlForm->id,
                'anamnesis_id'       => $anamnesisId,
            ]);

            return response()->json(['message' => 'GVL formulier is ontkoppeld.']);
        } catch (Exception $e) {
            Log::error('AnamnesisController@detachGvlForm failed', [
                'gvl_form_record_id' => $gvlForm->id,
                'error'              => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'GVL formulier ontkoppelen is mislukt: '.$e->getMessage(),
            ], 500);
        }
    }

    private function duplicateAttachConfirmationResponse(Anamnesis $anamnesis, ?string $formTypeOverride): ?JsonResponse
    {
        if (! $anamnesis->person) {
            return null;
        }

        $formType = $formTypeOverride
            ? FormType::fromValue($formTypeOverride)
            : FormType::defaultForAnamnesis($anamnesis);

        $context = $this->formsOverviewBuilder->contextForAnamnesis($anamnesis);

        if ($context === null) {
            return null;
        }

        $duplicate = $this->formsOverviewBuilder->activeDuplicateOnOtherLevel(
            $context['entity'],
            $anamnesis->person,
            $context['type'],
            $anamnesis,
            $formType,
        );

        if ($duplicate === null) {
            return null;
        }

        $levelLabels = ['lead' => 'Lead', 'sales' => 'Sales', 'order' => 'Order'];
        $levels = collect($duplicate['levels'])
            ->map(fn (string $level) => $levelLabels[$level] ?? $level)
            ->join(', ');

        return response()->json([
            'message'               => sprintf(
                '%s staat al open op %s-niveau. Weet je zeker dat je een tweede formulier van hetzelfde type wilt koppelen?',
                $duplicate['type_label'],
                $levels,
            ),
            'requires_confirmation' => true,
        ], 409);
    }

    private function attachGvlFormToAnamnesis(Anamnesis $anamnesis, ?string $formTypeOverride = null): JsonResponse
    {
        if (! $anamnesis->person) {
            return response()->json([
                'message' => 'Anamnesis heeft geen gekoppelde persoon.',
            ], 422);
        }

        [$gvlFormRecord, $formLink] = $this->createFormRequestForAnamnesis($anamnesis, $formTypeOverride);

        PatientNotifyEvent::dispatch(
            $anamnesis->person_id,
            $formLink,
            $this->notificationReferenceTypeFor($gvlFormRecord->gvl_form_type),
            $gvlFormRecord->gvl_form_id,
            false,
            auth()->id()
        );

        return response()->json([
            'message'            => 'GVL formulier is gekoppeld.',
            'gvl_form_link'      => $formLink,
            'gvl_form_record_id' => $gvlFormRecord->id,
        ], 200);
    }

    private function notificationReferenceTypeFor(?FormType $formType): NotificationReferenceType
    {
        return $formType?->isDiagnosisForm()
            ? NotificationReferenceType::DIAGNOSIS_FORM
            : NotificationReferenceType::GVL_FORM;
    }

    private function entityViewUrlForAnamnesis(Anamnesis $anamnesis): string
    {
        if ($anamnesis->order_id) {
            return route('admin.orders.view', $anamnesis->order_id).'#anamnese';
        }

        if ($anamnesis->sales_id) {
            return route('admin.sales-leads.view', $anamnesis->sales_id).'#anamnese';
        }

        return route('admin.leads.view', $anamnesis->lead_id).'#anamnese';
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
