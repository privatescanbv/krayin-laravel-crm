<?php

namespace App\Http\Controllers\Admin\Inkoop;

use App\Enums\PurchasePriceType;
use App\Models\Inkoop\InkoopInvoice;
use App\Models\Inkoop\InkoopInvoiceItem;
use App\Models\Inkoop\InkoopInvoiceItemCrmProduct;
use App\Models\Inkoop\InkoopPerson;
use App\Models\OrderItem;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class InkoopStep2Controller extends Controller
{
    public function store(Request $request, InkoopInvoice $invoice)
    {
        $crmIds = $request->input('crm_ids', []);
        $linkedCount = 0;

        foreach ($crmIds as $itemId => $crmIdArray) {
            $invoiceItem = InkoopInvoiceItem::where('inkoop_invoice_id', $invoice->id)->findOrFail($itemId);
            $currentCrmIds = $invoiceItem->crmProducts->pluck('crm_id')->map(fn ($id) => (string) $id)->toArray();
            $newCrmIds = array_diff(array_filter((array) $crmIdArray), $currentCrmIds);

            foreach ($newCrmIds as $crmId) {
                $orderItem = OrderItem::find($crmId);

                if (! $orderItem) {
                    continue;
                }

                $invoiceItem->crmProducts()->save(new InkoopInvoiceItemCrmProduct([
                    'clinic_id'      => $invoice->clinic_id,
                    'product_id'     => $orderItem->product_id,
                    'crm_id'         => $orderItem->id,
                    'crm_status'     => $this->orderItemStatusValue($orderItem),
                    'purchase_price' => $invoiceItem->price,
                ]));
                $linkedCount++;
            }
        }

        return redirect()->back()->with('success', "Producten zijn succesvol gekoppeld. ({$linkedCount} product(en) gekoppeld)");
    }

    public function handleStep(Request $request, InkoopInvoice $invoice)
    {
        $personsWithCRMRelation = InkoopPerson::where('invoice_id', $invoice->id)
            ->whereNotNull('crm_id')
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->with(['invoiceItems' => function ($query) use ($invoice) {
                $query->where('inkoop_invoice_id', $invoice->id)
                    ->orderBy('date')
                    ->orderBy('id')
                    ->with('crmProducts');
            }])
            ->get();

        $allPersonsByInvoiceCount = InkoopPerson::where('invoice_id', $invoice->id)->count();
        $percentageResolvedInvoiceItems = $invoice->calculateResolvedInvoiceItemsPercentage();
        $orderItemsByPerson = $this->findOrderItemsByPerson($personsWithCRMRelation, $invoice);
        $orderProductsByPerson = $this->formatOrderItemsByPerson($orderItemsByPerson);
        $filteredProductsByInvoiceItemId = $this->suggestOrderItemsByInvoiceItem($personsWithCRMRelation, $orderItemsByPerson);

        $percentageResolvedPersons = $personsWithCRMRelation->count() > 0
            ? ceil(($personsWithCRMRelation->count() / $allPersonsByInvoiceCount) * 100)
            : 0;

        return view('adminc::inkoop.step2', [
            'invoice'                         => $invoice,
            'persons'                         => $personsWithCRMRelation,
            'percentageResolvedPersons'       => $percentageResolvedPersons,
            'percentageResolvedInvoiceItems'  => $percentageResolvedInvoiceItems,
            'orderProductsByPerson'           => $orderProductsByPerson,
            'orderItemsByPerson'              => $orderItemsByPerson,
            'filteredProductsByInvoiceItemId' => $filteredProductsByInvoiceItemId,
        ]);
    }

    public function saveAllCrmIds(Request $request, InkoopInvoice $invoice)
    {
        $crmIds = $request->input('crm_ids', []);
        $linkedProductsCount = 0;

        try {
            foreach ($crmIds as $personId => $items) {
                if (! is_array($items)) {
                    continue;
                }

                $person = InkoopPerson::where('invoice_id', $invoice->id)->find($personId);
                if (! $person) {
                    continue;
                }

                foreach ($items as $itemId => $selectedCrmIds) {
                    $selectedCrmIds = array_filter((array) $selectedCrmIds);
                    $item = InkoopInvoiceItem::where('inkoop_invoice_id', $invoice->id)
                        ->where('person_id', $person->id)
                        ->find($itemId);

                    if (! $item) {
                        continue;
                    }

                    $item->crmProducts()->delete();

                    foreach ($selectedCrmIds as $crmId) {
                        $orderItem = OrderItem::where('person_id', $person->crm_id)->find($crmId);

                        if (! $orderItem) {
                            continue;
                        }

                        $crmProduct = $item->crmProducts()->create([
                            'clinic_id'      => $invoice->clinic_id,
                            'product_id'     => $orderItem->product_id,
                            'crm_id'         => $orderItem->id,
                            'crm_status'     => $this->orderItemStatusValue($orderItem),
                            'purchase_price' => $item->price,
                        ]);

                        $this->updateInvoicePurchasePrice($invoice, $crmProduct);
                        $linkedProductsCount++;
                    }
                }
            }
        } catch (Exception $e) {
            Log::error('Could not handle storing crm mapping: '.$e->getMessage(), [
                'invoice_id' => $invoice->id,
            ]);

            return redirect()->back()->with('error', 'Er is een technische fout opgetreden. Neem contact op met de beheerder.');
        }

        $forcedCount = 0;
        foreach (array_filter((array) $request->input('force_item_ids', [])) as $itemId) {
            $orderItemIds = InkoopInvoiceItemCrmProduct::where('inkoop_invoice_item_id', $itemId)
                ->pluck('crm_id');
            foreach ($orderItemIds as $orderItemId) {
                $orderItem = OrderItem::find($orderItemId);
                if ($orderItem) {
                    $orderItem->invoicePurchasePrice()->updateOrCreate(
                        ['type' => PurchasePriceType::INVOICE],
                        ['force_received' => true]
                    );
                    $forcedCount++;
                }
            }
        }

        $message = "Producten zijn succesvol gekoppeld. ({$linkedProductsCount} product(en) gekoppeld)";
        if ($forcedCount > 0) {
            $message .= " {$forcedCount} order regel(s) geforceerd als geheel ontvangen.";
        }

        return redirect()->route('admin.inkoop.step2', ['invoice' => $invoice->id])
            ->with('success', $message);
    }

    public function resetCrmId(Request $request, InkoopInvoice $invoice, $item)
    {
        $invoiceItem = InkoopInvoiceItem::where('inkoop_invoice_id', $invoice->id)->findOrFail($item);
        $invoiceItem->crmProducts()->delete();

        return redirect()->back()->with('success', 'CRM koppelingen zijn gereset.');
    }

    public function bulkForceReceived(Request $request, InkoopInvoice $invoice): RedirectResponse
    {
        $itemIds = array_filter((array) $request->input('force_item_ids', []));

        $orderItemIds = InkoopInvoiceItemCrmProduct::whereIn('inkoop_invoice_item_id', $itemIds)
            ->pluck('crm_id');

        foreach ($orderItemIds as $orderItemId) {
            $orderItem = OrderItem::find($orderItemId);
            if ($orderItem) {
                $orderItem->invoicePurchasePrice()->updateOrCreate(
                    ['type' => PurchasePriceType::INVOICE],
                    ['force_received' => true]
                );
            }
        }

        return redirect()->back()->with('success', count($orderItemIds).' order regel(s) geforceerd als geheel ontvangen.');
    }

    private function findOrderItemsByPerson(Collection $persons, InkoopInvoice $invoice): Collection
    {
        $allOrderItems = OrderItem::query()
            ->with([
                'product',
                'person',
                'purchasePrice',
                'order.orderItems.invoicePurchasePrice',
                'order.orderItems.purchasePrice',
                'invoicePurchasePrice',
            ])
            ->whereIn('person_id', $persons->pluck('crm_id'))
            ->whereHas('resourceOrderItem.resource.clinicDepartment', function ($q) use ($invoice) {
                $q->where('clinic_id', $invoice->clinic_id);
            })
            ->orderByDesc('id')
            ->get()
            ->groupBy('person_id');

        return $persons->mapWithKeys(
            fn (InkoopPerson $person) => [$person->id => $allOrderItems->get($person->crm_id, collect())]
        );
    }

    private function formatOrderItemsByPerson(Collection $orderItemsByPerson): array
    {
        return $orderItemsByPerson->map(function (Collection $orderItems) {
            return $orderItems->mapWithKeys(function (OrderItem $orderItem) {
                $productName = $this->orderItemProductName($orderItem);
                $price = '€ '.number_format((float) $orderItem->total_price, 2, ',', '.');
                $person = $orderItem->person?->name ?: '-';
                $status = $this->orderItemStatusValue($orderItem);

                return [$orderItem->id => "{$price} - {$productName} - {$person} - {$status}"];
            })->toArray();
        })->toArray();
    }

    private function suggestOrderItemsByInvoiceItem(Collection $persons, Collection $orderItemsByPerson): array
    {
        return $orderItemsByPerson->mapWithKeys(function (Collection $orderItems, int $personId) use ($persons) {
            $person = $persons->firstWhere('id', $personId);

            if (! $person) {
                return [];
            }

            $result = [];

            foreach ($person->invoiceItems as $invoiceItem) {
                $bestMatch = null;
                $bestSimilarity = 0.0;

                foreach ($orderItems as $orderItem) {
                    if (abs((float) $orderItem->total_price - (float) $invoiceItem->price) > 0.01) {
                        continue;
                    }

                    $similarity = $this->calculateTextSimilarity(
                        $invoiceItem->name ?? $invoiceItem->description ?? '',
                        $this->orderItemProductName($orderItem)
                    );

                    if ($similarity > $bestSimilarity) {
                        $bestSimilarity = $similarity;
                        $bestMatch = $orderItem->id;
                    }
                }

                if ($bestMatch !== null) {
                    $result[$invoiceItem->id] = $bestMatch;
                }
            }

            return [$personId => $result];
        })->toArray();
    }

    private function updateInvoicePurchasePrice(InkoopInvoice $invoice, InkoopInvoiceItemCrmProduct $crmProduct): void
    {
        $purchaseField = match ($invoice->parser?->supplierType()) {
            'radiology'  => 'purchase_price_radiology',
            'cardiology' => 'purchase_price_cardiology',
            'clinic'     => 'purchase_price_clinic',
            default      => 'purchase_price_misc',
        };

        $orderItem = OrderItem::find($crmProduct->crm_id);

        if ($orderItem) {
            $orderItem->invoicePurchasePrice()->updateOrCreate(
                ['type' => PurchasePriceType::INVOICE],
                [
                    $purchaseField   => $crmProduct->purchase_price,
                    'purchase_price' => $crmProduct->purchase_price,
                ]
            );
        }
    }

    private function orderItemProductName(OrderItem $orderItem): string
    {
        return $orderItem->product?->name ?? $orderItem->name ?? 'Onbekend product';
    }

    private function orderItemStatusValue(OrderItem $orderItem): string
    {
        return $orderItem->status instanceof \BackedEnum
            ? (string) $orderItem->status->value
            : (string) ($orderItem->status ?? '-');
    }

    private function calculateTextSimilarity(string $str1, string $str2): float
    {
        $str1 = mb_strtolower(trim($str1));
        $str2 = mb_strtolower(trim($str2));

        if ($str1 === '' || $str2 === '') {
            return 0.0;
        }

        if ($str1 === $str2) {
            return 1.0;
        }

        $words1 = array_filter(preg_split('/\s+/', $str1));
        $words2 = array_filter(preg_split('/\s+/', $str2));
        $commonWordCount = count(array_intersect($words1, $words2));

        if ($commonWordCount > 0) {
            return 0.5 + (0.5 * ($commonWordCount / max(count($words1), count($words2))));
        }

        foreach ($words1 as $word1) {
            foreach ($words2 as $word2) {
                if (str_contains($word1, $word2) || str_contains($word2, $word1)) {
                    return 0.6;
                }
            }
        }

        return 0.0;
    }
}
