<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Anamnesis;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
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
    ) {
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): View
    {
        $anamnesis = Anamnesis::with('lead')->findOrFail($id);
        
        return view('admin::anamnesis.edit', compact('anamnesis'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $anamnesis = Anamnesis::findOrFail($id);
        
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'comment_clinic' => 'nullable|string',
            'lengte' => 'nullable|integer',
            'gewicht' => 'nullable|integer',
            'metalen' => 'required|in:0,1',
            'opm_metalen_c' => 'nullable|string',
            'medicijnen' => 'required|in:0,1',
            'opm_medicijnen_c' => 'nullable|string',
            'glaucoom' => 'required|in:0,1',
            'opm_glaucoom_c' => 'nullable|string',
            'claustrofobie' => 'required|in:0,1',
            'dormicum' => 'required|in:0,1',
            'hart_operatie_c' => 'required|in:0,1',
            'opm_hart_operatie_c' => 'nullable|string',
            'implantaat_c' => 'required|in:0,1',
            'opm_implantaat_c' => 'nullable|string',
            'operaties_c' => 'required|in:0,1',
            'opm_operaties_c' => 'nullable|string',
            'opmerking' => 'nullable|string|max:255',
            'hart_erfelijk' => 'required|in:0,1',
            'opm_erf_hart_c' => 'nullable|string',
            'vaat_erfelijk' => 'required|in:0,1',
            'opm_erf_vaat_c' => 'nullable|string',
            'tumoren_erfelijk' => 'required|in:0,1',
            'opm_erf_tumor_c' => 'nullable|string',
            'allergie_c' => 'required|in:0,1',
            'opm_allergie_c' => 'nullable|string',
            'rugklachten' => 'required|in:0,1',
            'opm_rugklachten_c' => 'nullable|string',
            'heart_problems' => 'required|in:0,1',
            'opm_hartklachten_c' => 'nullable|string',
            'smoking' => 'required|in:0,1',
            'opm_roken_c' => 'nullable|string',
            'diabetes' => 'required|in:0,1',
            'opm_diabetes_c' => 'nullable|string',
            'spijsverteringsklachten' => 'required|in:0,1',
            'opm_spijsvertering_c' => 'nullable|string',
            'risico_hartinfarct' => 'nullable|string',
            'actief' => 'required|in:0,1',
            'opm_advies_c' => 'nullable|string',
        ]);

        $data['updated_by'] = \Illuminate\Support\Str::uuid(); // Generate UUID for updated_by field
        $data['updated_at'] = now();

        $anamnesis->update($data);

        session()->flash('success', 'Anamnesis succesvol bijgewerkt.');

        return redirect()->route('admin.leads.view', $anamnesis->lead_id);
    }
}