<?php

namespace App\Http\Controllers\Admin\Inkoop;

use App\Enums\Inkoop\InkoopInvoiceStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderPurchaseStatus;
use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopInvoiceItem;
use App\Models\Inkoop\InkoopPerson;
use App\Models\Order;
use App\Models\OrderItem;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class InkoopStep3Controller extends Controller
{
    public function handleStep(Request $request, InkoopInvoice $invoice)
    {
        $invoice->loadMissing('clinic');

        $persons = InkoopPerson::where('invoice_id', $invoice->id)
            ->with(['invoiceItems' => function ($query) use ($invoice) {
                $query->where('inkoop_invoice_id', $invoice->id)->with('crmProducts');
            }])
            ->get();

        $unprocessedItems = InkoopInvoiceItem::where('inkoop_invoice_id', $invoice->id)
            ->whereDoesntHave('crmProducts')
            ->get();

        $crmOrderItemsByPerson = $persons
            ->filter(fn (InkoopPerson $person) => ! empty($person->crm_id))
            ->mapWithKeys(function (InkoopPerson $person) use ($invoice) {
                return [
                    $person->id => OrderItem::query()
                        ->with(['product', 'product.partnerProducts.purchasePrice', 'person', 'order', 'purchasePrice', 'invoicePurchasePrice'])
                        ->where('person_id', $person->crm_id)
                        ->where('status', '!=', OrderItemStatus::LOST->value)
                        ->whereHas('resourceOrderItem.resource.clinicDepartment', function ($q) use ($invoice) {
                            $q->where('clinic_id', $invoice->clinic_id);
                        })
                        ->get(),
                ];
            });

        $invoiceDataByOrderItemId = $persons->flatMap(fn ($person) => $person->invoiceItems)
            ->reduce(function (array $carry, $invoiceItem) {
                foreach ($invoiceItem->crmProducts as $crmProduct) {
                    $carry[(int) $crmProduct->crm_id] = [
                        'date'  => $invoiceItem->date,
                        'price' => $crmProduct->purchase_price,
                    ];
                }

                return $carry;
            }, []);

        $allOrderItems = $crmOrderItemsByPerson->flatMap(fn ($items) => $items);

        $orderItemPurchaseStatuses = $allOrderItems
            ->mapWithKeys(function (OrderItem $orderItem) {
                $purchaseTotal = (float) $orderItem->resolvedPurchasePrice()->purchase_price;
                $invoiceTotal = (float) ($orderItem->invoicePurchasePrice?->purchase_price ?? 0);
                $forced = (bool) ($orderItem->invoicePurchasePrice?->force_received ?? false);

                return [$orderItem->id => OrderPurchaseStatus::forItem($purchaseTotal, $invoiceTotal, $forced)];
            })
            ->all();

        $orderIds = $allOrderItems
            ->pluck('order_id')
            ->filter()
            ->unique()
            ->values();

        $ordersById = Order::query()
            ->with([
                'orderItems.purchasePrice',
                'orderItems.invoicePurchasePrice',
                'orderItems.product.partnerProducts.purchasePrice',
                'orderItems.resourceOrderItems',
            ])
            ->whereIn('id', $orderIds)
            ->get()
            ->keyBy('id');

        $orderPurchaseStatuses = $ordersById
            ->map(fn (Order $order) => $order->purchaseStatus())
            ->all();

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
            'orderItemPurchaseStatuses'      => $orderItemPurchaseStatuses,
            'invoiceDataByOrderItemId'       => $invoiceDataByOrderItemId,
            'orderPurchaseStatuses'          => $orderPurchaseStatuses,
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
