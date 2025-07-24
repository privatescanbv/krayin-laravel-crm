<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Anamnesis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Lead\Repositories\LeadRepository;

class AnamnesisController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository $leadRepository
    ) {}

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): View
    {
        $anamnesis = Anamnesis::with('lead')->findOrFail($id);

        return view('admin::anamnesis.edit', ['anamnesis' => $anamnesis]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $anamnesis = Anamnesis::findOrFail($id);

        // Map Dutch field names to English database column names
        $fieldMapping = [
            'metalen' => 'metals',
            'medicijnen' => 'medications',
            'glaucoom' => 'glaucoma',
            'claustrofobie' => 'claustrophobia',
            'hart_operatie_c' => 'heart_surgery',
            'implantaat_c' => 'implant',
            'operaties_c' => 'surgeries',
            'hart_erfelijk' => 'hereditary_heart',
            'vaat_erfelijk' => 'hereditary_vascular',
            'tumoren_erfelijk' => 'hereditary_tumors',
            'allergie_c' => 'allergies',
            'rugklachten' => 'back_problems',
            'actief' => 'active',
            'spijsverteringsklachten' => 'digestive_problems',
            'lengte' => 'height',
            'gewicht' => 'weight',
            'risico_hartinfarct' => 'heart_attack_risk',
            'opmerking' => 'remarks',
            'opm_metalen_c' => 'metals_notes',
            'opm_medicijnen_c' => 'medications_notes',
            'opm_glaucoom_c' => 'glaucoma_notes',
            'opm_hart_operatie_c' => 'heart_surgery_notes',
            'opm_implantaat_c' => 'implant_notes',
            'opm_operaties_c' => 'surgeries_notes',
            'opm_erf_hart_c' => 'hereditary_heart_notes',
            'opm_erf_vaat_c' => 'hereditary_vascular_notes',
            'opm_erf_tumor_c' => 'hereditary_tumors_notes',
            'opm_allergie_c' => 'allergies_notes',
            'opm_rugklachten_c' => 'back_problems_notes',
            'opm_roken_c' => 'smoking_notes',
            'opm_diabetes_c' => 'diabetes_notes',
            'opm_spijsvertering_c' => 'digestive_problems_notes',
            'opm_hartklachten_c' => 'heart_problems_notes',
            'opm_advies_c' => 'advice_notes',
        ];

        // Create a new array with mapped field names
        $mappedData = [];
        foreach ($request->all() as $key => $value) {
            $mappedKey = $fieldMapping[$key] ?? $key;
            $mappedData[$mappedKey] = $value;
        }

        // Create a new request instance with mapped data
        $mappedRequest = new Request($mappedData);

        $data = $mappedRequest->validate([
            'name'                      => 'nullable|string|max:255',
            'description'               => 'nullable|string',
            'comment_clinic'            => 'nullable|string',
            'height'                    => 'nullable|integer',
            'weight'                    => 'nullable|integer',
            'metals'                    => 'required|in:0,1',
            'metals_notes'              => 'nullable|string',
            'medications'               => 'required|in:0,1',
            'medications_notes'         => 'nullable|string',
            'glaucoma'                  => 'required|in:0,1',
            'glaucoma_notes'            => 'nullable|string',
            'claustrophobia'            => 'required|in:0,1',
            'dormicum'                  => 'required|in:0,1',
            'heart_surgery'             => 'required|in:0,1',
            'heart_surgery_notes'       => 'nullable|string',
            'implant'                   => 'required|in:0,1',
            'implant_notes'             => 'nullable|string',
            'surgeries'                 => 'required|in:0,1',
            'surgeries_notes'           => 'nullable|string',
            'remarks'                   => 'nullable|string|max:255',
            'hereditary_heart'          => 'required|in:0,1',
            'hereditary_heart_notes'    => 'nullable|string',
            'hereditary_vascular'       => 'required|in:0,1',
            'hereditary_vascular_notes' => 'nullable|string',
            'hereditary_tumors'         => 'required|in:0,1',
            'hereditary_tumors_notes'   => 'nullable|string',
            'allergies'                 => 'required|in:0,1',
            'allergies_notes'           => 'nullable|string',
            'back_problems'             => 'required|in:0,1',
            'back_problems_notes'       => 'nullable|string',
            'heart_problems'            => 'required|in:0,1',
            'heart_problems_notes'      => 'nullable|string',
            'smoking'                   => 'required|in:0,1',
            'smoking_notes'             => 'nullable|string',
            'diabetes'                  => 'required|in:0,1',
            'diabetes_notes'            => 'nullable|string',
            'digestive_problems'        => 'required|in:0,1',
            'digestive_problems_notes'  => 'nullable|string',
            'heart_attack_risk'         => 'nullable|string',
            'active'                    => 'required|in:0,1',
            'advice_notes'              => 'nullable|string',
        ], [
            'metals.required'              => 'Selecteer een antwoord voor Metalen.',
            'medications.required'         => 'Selecteer een antwoord voor Medicijnen.',
            'glaucoma.required'            => 'Selecteer een antwoord voor Glaucoom.',
            'claustrophobia.required'      => 'Selecteer een antwoord voor Claustrofobie.',
            'dormicum.required'            => 'Selecteer een antwoord voor Dormicum.',
            'heart_surgery.required'       => 'Selecteer een antwoord voor Hart operatie.',
            'implant.required'             => 'Selecteer een antwoord voor Implantaat.',
            'surgeries.required'           => 'Selecteer een antwoord voor Operaties.',
            'hereditary_heart.required'    => 'Selecteer een antwoord voor Hart erfelijk.',
            'hereditary_vascular.required' => 'Selecteer een antwoord voor Vaat erfelijk.',
            'hereditary_tumors.required'   => 'Selecteer een antwoord voor Tumoren erfelijk.',
            'allergies.required'           => 'Selecteer een antwoord voor Allergie.',
            'back_problems.required'       => 'Selecteer een antwoord voor Rugklachten.',
            'heart_problems.required'      => 'Selecteer een antwoord voor Hartproblemen.',
            'smoking.required'             => 'Selecteer een antwoord voor Roken.',
            'diabetes.required'            => 'Selecteer een antwoord voor Diabetes.',
            'digestive_problems.required'  => 'Selecteer een antwoord voor Spijsverteringsklachten.',
            'active.required'              => 'Selecteer een antwoord voor Actief.',
        ]);

        $data['updated_by'] = null; // Set to null since it's now nullable
        $data['updated_at'] = now();

        $anamnesis->update($data);

        session()->flash('success', 'Anamnesis updated successfully.');

        return redirect()->route('admin.leads.view', $anamnesis->lead_id);
    }
}
