<?php

namespace App\Http\Controllers\Admin\Inkoop;

use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopPerson;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Webkul\Contact\Models\Person;

class InkoopStep1Controller extends Controller
{
    private array $prefixSurname = [
        'van de ', 'van den ', 'van der ', 'van ', 'de ', 'den ', 'der ',
        'te ', 'ter ', 'ten ', "'t ", 'op den ', 'aan de ', "in 't ",
        'de la ', 'du ', 'des ', 'le', 'le de ', 'le van ', 'la ',
        'von ', 'zu ', 'vom ', 'zur ', 'el ',
    ];

    public function handleStep(Request $request, InkoopInvoice $invoice)
    {
        $patients = InkoopPerson::where('invoice_id', $invoice->id)
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();

        $errorMessage = null;

        try {
            foreach ($patients as $patient) {
                if (! empty($patient->crm_id)) {
                    $linked = Person::find($patient->crm_id);
                    $patient->crm_matches = $linked ? collect([$linked]) : collect();

                    continue;
                }

                $searchLastname = $this->getSearchLastName($patient->lastname ?? '');

                $query = Person::query()
                    ->when($patient->firstname, fn ($q) => $q->whereRaw('LOWER(first_name) LIKE ?', ['%'.mb_strtolower($patient->firstname).'%']))
                    ->when($searchLastname, fn ($q) => $q->whereRaw('LOWER(last_name) LIKE ?', ['%'.mb_strtolower($searchLastname).'%']))
                    ->when($patient->birthday, fn ($q) => $q->whereDate('date_of_birth', $patient->birthday));

                $patient->crm_matches = $query->limit(10)->get();
            }
        } catch (Exception $e) {
            Log::error('Error searching CRM persons in step1', ['error' => $e->getMessage()]);
            $errorMessage = 'Er is een fout opgetreden bij het zoeken in het CRM. Probeer het later opnieuw.';
        }

        return view('adminc::inkoop.step1', [
            'invoice'                   => $invoice,
            'patients'                  => $patients,
            'percentageResolvedPersons' => InkoopPerson::calculatePercentageHasCRMRelation($invoice->id),
            'errorMessage'              => $errorMessage,
        ]);
    }

    public function saveAllCrmIds(Request $request, InkoopInvoice $invoice)
    {
        $request->validate([
            'crm_ids' => 'required|array',
        ]);

        $updatedCount = 0;
        foreach ($request->crm_ids as $patientId => $crmId) {
            if (! empty($crmId)) {
                $patient = InkoopPerson::find($patientId);
                if ($patient) {
                    $patient->crm_id = $crmId;
                    $patient->save();
                    $updatedCount++;
                }
            }
        }

        return redirect()
            ->route('admin.inkoop.step1', ['invoice' => $invoice->id])
            ->with('success', "{$updatedCount} patiënt(en) succesvol gekoppeld aan CRM");
    }

    public function resetCrmId(Request $request, InkoopInvoice $invoice, InkoopPerson $person)
    {
        try {
            $person->update(['crm_id' => null]);

            return response()->json(['success' => true, 'message' => 'CRM ID is gereset']);
        } catch (Exception $e) {
            Log::error('Error resetting person CRM ID', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Er is een fout opgetreden'], 500);
        }
    }

    public function getSearchLastName(string $lastname): string
    {
        $parts = explode('-', $lastname);
        $lastPart = end($parts);

        return $this->stripSurnamePrefix(trim($lastPart), $this->prefixSurname);
    }

    private function stripSurnamePrefix(string $surname, array $prefixes): string
    {
        $trimmed = trim($surname);
        $lower = mb_strtolower($trimmed);

        foreach ($prefixes as $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return trim(mb_substr($trimmed, mb_strlen($prefix)));
            }
        }

        return $trimmed;
    }
}
