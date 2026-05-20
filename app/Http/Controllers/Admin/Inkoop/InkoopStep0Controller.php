<?php

namespace App\Http\Controllers\Admin\Inkoop;

use App\Models\Inkoop\InkoopInvoice;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InkoopStep0Controller extends Controller
{
    public function show(InkoopInvoice $invoice)
    {
        return view('adminc::inkoop.step0', [
            'invoice' => $invoice,
        ]);
    }

    public function update(Request $request, InkoopInvoice $invoice)
    {
        $request->validate([
            'reference_date' => 'nullable|date',
            'name'           => 'nullable|string|max:255',
        ]);

        $invoice->reference_date = $request->reference_date;
        $invoice->name = $request->name;
        $invoice->save();

        return redirect()->route('admin.inkoop.step1', ['invoice' => $invoice->id])
            ->with('success', 'Referentie datum is bijgewerkt.');
    }
}
