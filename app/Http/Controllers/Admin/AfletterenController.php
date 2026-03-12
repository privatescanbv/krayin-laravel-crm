<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Models\OrderPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AfletterenController extends Controller
{
    public function index(): View
    {
        // All orders for the "add payment" dropdown
        $orders = Order::with(['salesLead.persons'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Order $o) => [
                'id'    => $o->id,
                'label' => trim(
                    ($o->order_number ?? '').' — '.
                    ($o->salesLead?->persons?->first()?->name ?? $o->salesLead?->name ?? 'Onbekend')
                ),
            ]);

        return view('admin::afletteren.index', compact('orders'));
    }

    public function payments(): JsonResponse
    {
        $payments = OrderPayment::with(['order.salesLead.persons'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (OrderPayment $p) => [
                'id'           => $p->id,
                'order_id'     => $p->order_id,
                'order_number' => $p->order?->order_number,
                'patient_name' => $p->order?->salesLead?->persons?->first()?->name
                                  ?? $p->order?->salesLead?->name,
                'amount'       => $p->amount,
                'type'         => $p->type?->value ?? $p->getRawOriginal('type'),
                'method'       => $p->method,
                'paid_at'      => $p->paid_at?->format('Y-m-d'),
                'currency'     => $p->currency,
            ]);

        return response()->json($payments);
    }
}
