<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\OrderItemDataGrid;
use App\Enums\Currency;
use App\Enums\OrderItemStatus;
use App\Models\OrderItem;
use App\Models\ProductType;
use App\Repositories\OrderItemRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Product\Models\Product;

class OrderItemController extends SimpleEntityController
{
    public function __construct(
        protected OrderItemRepository $orderItemRepository,
        protected PersonRepository $personRepository
    ) {
        parent::__construct($orderItemRepository);

        $this->entityName = 'order_items';
        $this->datagridClass = OrderItemDataGrid::class;
        $this->indexView = 'admin::order_items.index';
        $this->createView = 'admin::order_items.create';
        $this->editView = 'admin::order_items.edit';
        $this->indexRoute = 'admin.order_items.index';
        $this->permissionPrefix = 'settings.order_items';
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $this->validateUpdate($request, $id);

        Event::dispatch("settings.{$this->entityName}.update.before", $id);

        $entity = $this->repository->update($this->transformPayload($request->all(), $id), $id);

        $this->saveOrderItemPurchasePrice($entity, $request);
        $this->saveInvoicePurchasePrice($entity, $request);

        Event::dispatch("settings.{$this->entityName}.update.after", $entity);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data'    => $entity,
                'message' => $this->getUpdateSuccessMessage(),
            ]);
        }

        return redirect()
            ->route('admin.orders.edit', ['id' => $entity->order_id])
            ->with('success', $this->getUpdateSuccessMessage());
    }

    public function getPartnerPurchasePrices(int $productId): JsonResponse
    {
        $product = Product::with(['partnerProducts.purchasePrice'])
            ->findOrFail($productId);

        $suffixes = ['misc', 'doctor', 'cardiology', 'clinic', 'radiology'];
        $totals = array_fill_keys($suffixes, 0.0);

        foreach ($product->partnerProducts as $pp) {
            if (! $pp->purchasePrice) {
                continue;
            }
            foreach ($suffixes as $suffix) {
                $totals[$suffix] += (float) ($pp->purchasePrice->{'purchase_price_'.$suffix} ?? 0);
            }
        }

        $totals['product_type_id'] = $product->product_type_id;

        return response()->json($totals);
    }

    protected function validateStore(Request $request): void
    {
        $purchasePriceFields = [
            'purchase_price_misc', 'purchase_price_doctor', 'purchase_price_cardiology',
            'purchase_price_clinic', 'purchase_price_radiology',
            'invoice_purchase_price_misc', 'invoice_purchase_price_doctor', 'invoice_purchase_price_cardiology',
            'invoice_purchase_price_clinic', 'invoice_purchase_price_radiology',
        ];
        foreach ($purchasePriceFields as $field) {
            $value = $request->input($field);
            $normalized = Currency::normalizePrice($value);
            $request->merge([
                $field => ($normalized === '' || $normalized === null) ? 0 : $normalized,
            ]);
        }

        $request->validate([
            'order_id'                          => ['required', 'integer', 'exists:orders,id'],
            'product_id'                        => ['required', 'integer', 'exists:products,id'],
            'product_type_id'                   => ['nullable', 'integer', 'exists:product_types,id'],
            'name'                              => ['nullable', 'string', 'max:255'],
            'description'                       => ['nullable', 'string'],
            'person_id'                         => ['required', 'integer', 'exists:persons,id'],
            'quantity'                          => ['required', 'integer', 'min:1'],
            'total_price'                       => ['nullable', 'numeric', 'min:0'],
            'currency'                          => ['nullable', 'string', 'in:'.implode(',', Currency::codes())],
            'status'                            => ['nullable', 'string', 'in:'.implode(',', array_column(OrderItemStatus::cases(), 'value'))],
            'purchase_price_misc'               => ['nullable', 'numeric', 'min:0'],
            'purchase_price_doctor'             => ['nullable', 'numeric', 'min:0'],
            'purchase_price_cardiology'         => ['nullable', 'numeric', 'min:0'],
            'purchase_price_clinic'             => ['nullable', 'numeric', 'min:0'],
            'purchase_price_radiology'          => ['nullable', 'numeric', 'min:0'],
            'invoice_purchase_price_misc'       => ['nullable', 'numeric', 'min:0'],
            'invoice_purchase_price_doctor'     => ['nullable', 'numeric', 'min:0'],
            'invoice_purchase_price_cardiology' => ['nullable', 'numeric', 'min:0'],
            'invoice_purchase_price_clinic'     => ['nullable', 'numeric', 'min:0'],
            'invoice_purchase_price_radiology'  => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $this->validateStore($request);
    }

    protected function saveOrderItemPurchasePrice(OrderItem $entity, Request $request): void
    {
        $fields = [
            'purchase_price_misc', 'purchase_price_doctor', 'purchase_price_cardiology',
            'purchase_price_clinic', 'purchase_price_radiology',
        ];
        $data = [];
        $total = 0;
        foreach ($fields as $field) {
            $value = floatval($request->input($field, 0));
            $data[$field] = $value;
            $total += $value;
        }
        $data['purchase_price'] = $total;
        $entity->purchasePrice()->updateOrCreate([], $data);
    }

    protected function saveInvoicePurchasePrice(OrderItem $entity, Request $request): void
    {
        $suffixes = ['misc', 'doctor', 'cardiology', 'clinic', 'radiology'];
        $data = ['type' => 'invoice'];
        $total = 0;
        foreach ($suffixes as $suffix) {
            $value = floatval($request->input('invoice_purchase_price_'.$suffix, 0));
            $data['purchase_price_'.$suffix] = $value;
            $total += $value;
        }
        $data['purchase_price'] = $total;
        $entity->invoicePurchasePrice()->updateOrCreate(['type' => 'invoice'], $data);
    }

    protected function getCreateSuccessMessage(): string
    {
        return 'Orderitem aangemaakt.';
    }

    protected function getUpdateSuccessMessage(): string
    {
        return 'Orderitem bijgewerkt.';
    }

    protected function getDestroySuccessMessage(): string
    {
        return 'Orderitem verwijderd.';
    }

    protected function getDeleteFailedMessage(): string
    {
        return 'Verwijderen mislukt.';
    }

    protected function getCreateViewData(Request $request): array
    {
        $persons = $this->personRepository->all(['id', 'name'])->mapWithKeys(function ($person) {
            return [$person->id => $person->name];
        })->toArray();

        return [
            'persons' => $persons,
        ];
    }

    protected function getEditViewData(Request $request, $entity): array
    {
        $entity->load('purchasePrice', 'invoicePurchasePrice', 'product');

        $persons = $this->personRepository->all(['id', 'name'])->mapWithKeys(function ($person) {
            return [$person->id => $person->name];
        })->toArray();

        return [
            'order_items'     => $entity,
            'persons'         => $persons,
            'productTypes'    => ProductType::orderBy('name')->get(['id', 'name']),
            'statuses'        => collect(OrderItemStatus::cases())
                ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                ->toArray(),
            'currencies'      => Currency::options(),
            'defaultCurrency' => Currency::default()->value,
        ];
    }

    protected function transformPayload(array $payload, ?int $id = null): array
    {
        $payload = parent::transformPayload($payload, $id);

        $productId = $payload['product_id'] ?? null;

        $selectedProductTypeId = $payload['product_type_id'] ?? null;
        if ($selectedProductTypeId === '') {
            $selectedProductTypeId = null;
        }

        if ($selectedProductTypeId !== null) {
            $selectedProductTypeId = (int) $selectedProductTypeId;
        }

        if ($productId) {
            $product = Product::query()
                ->select(['id', 'product_type_id'])
                ->find($productId);

            if ($product && (int) ($product->product_type_id ?? 0) === (int) ($selectedProductTypeId ?? 0)) {
                $payload['product_type_id'] = null;
            } else {
                $payload['product_type_id'] = $selectedProductTypeId;
            }
        } else {
            $payload['product_type_id'] = $selectedProductTypeId;
        }

        return $payload;
    }
}
