<?php

namespace App\Http\Controllers\Admin\Inkoop;

use App\Enums\Inkoop\InkoopInvoiceStatus;
use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopInvoiceItem;
use App\Models\Inkoop\InkoopPerson;
use App\Models\OrderItem;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class InkoopStep3Controller extends Controller
{
    public function handleStep(Request $request, InkoopInvoice $invoice)
    {
        $persons = InkoopPerson::where('invoice_id', $invoice->id)
            ->with(['invoiceItems' => function ($query) use ($invoice) {
                $query->where('inkoop_invoice_id', $invoice->id);
            }])
            ->get();

        $unprocessedItems = InkoopInvoiceItem::where('inkoop_invoice_id', $invoice->id)
            ->whereDoesntHave('crmProducts')
            ->get();

        $crmOrderItemsByPerson = $persons
            ->filter(fn (InkoopPerson $person) => ! empty($person->crm_id))
            ->mapWithKeys(function (InkoopPerson $person) {
                return [
                    $person->id => OrderItem::query()
                        ->with(['product', 'person'])
                        ->where('person_id', $person->crm_id)
                        ->get(),
                ];
            });

        $allPersonsCount = $persons->count();
        $linkedPersonsCount = $persons->whereNotNull('crm_id')->count();
        $percentageResolvedPersons = $allPersonsCount > 0 ? (int) ceil(($linkedPersonsCount / $allPersonsCount) * 100) : 0;

        return view('adminc::inkoop.step3', [
            'invoice'                        => $invoice,
            'persons'                        => $persons,
            'unprocessedItems'               => $unprocessedItems,
            'percentageResolvedPersons'      => $percentageResolvedPersons,
            'percentageResolvedInvoiceItems' => $invoice->calculateResolvedInvoiceItemsPercentage(),
            'crmOrderItemsByPerson'          => $crmOrderItemsByPerson,
        ]);
    }

    public function markAsProcessed(Request $request, InkoopInvoice $invoice)
    {
        try {
            $invoice->status = InkoopInvoiceStatus::CLOSED;
            $invoice->save();

            return redirect()->to(route('admin.clinics.view', ['id' => $invoice->clinic_id]).'#inkoop-afletteren')
                ->with('success', 'Factuur is succesvol gemarkeerd als verwerkt.');
        } catch (Exception $e) {
            Log::error('Failed to mark inkoop invoice as processed', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);

            return redirect()->route('admin.inkoop.step3', ['invoice' => $invoice->id])
                ->with('error', 'Er is een fout opgetreden bij het markeren van de factuur als verwerkt.');
        }
    }
}
