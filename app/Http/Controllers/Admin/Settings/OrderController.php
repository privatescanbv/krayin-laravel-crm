<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\OrderDataGrid;
use App\Repositories\OrderRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;

class OrderController extends SimpleEntityController
{
    public function __construct(protected OrderRepository $orderRepository)
    {
        parent::__construct($orderRepository);

        $this->entityName = 'orders';
        $this->datagridClass = OrderDataGrid::class;
        $this->indexView = 'admin::orders.index';
        $this->createView = 'admin::orders.create';
        $this->editView = 'admin::orders.edit';
        $this->indexRoute = 'admin.orders.index';
        $this->permissionPrefix = 'orders';
    }

    public function create(Request $request): View
    {
        $salesLeadId = $request->get('sales_lead_id');
        
        $salesLeads = \App\Models\SalesLead::with('lead')->get()->mapWithKeys(function($salesLead) {
            return [$salesLead->id => $salesLead->name . ' (' . ($salesLead->lead?->name ?? 'Geen lead') . ')'];
        })->toArray();

        return view($this->createView, [
            'salesLeadId' => $salesLeadId,
            'salesLeads' => $salesLeads
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->validateStore($request);

        Event::dispatch("{$this->entityName}.create.before");

        $payload = $this->transformPayload($request->all());
        $items = $payload['items'] ?? [];
        unset($payload['items']);

        $order = $this->orderRepository->create($payload);

        // Persist items
        foreach ($items as $item) {
            if (! empty($item['product_id']) && ! empty($item['quantity'])) {
                $order->orderRegels()->create([
                    'product_id'  => (int) $item['product_id'],
                    'quantity'    => (int) $item['quantity'],
                    'total_price' => (float) ($item['total_price'] ?? 0),
                ]);
            }
        }

        Event::dispatch("{$this->entityName}.create.after", $order);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data'    => $order,
                'message' => $this->getCreateSuccessMessage(),
            ], 200);
        }

        return redirect()->route($this->indexRoute)->with('success', $this->getCreateSuccessMessage());
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $this->validateUpdate($request, $id);

        Event::dispatch("{$this->entityName}.update.before", $id);

        $payload = $this->transformPayload($request->all(), $id);
        $items = $payload['items'] ?? [];
        unset($payload['items']);

        $order = $this->orderRepository->update($payload, $id);

        // Re-sync items (simple approach: delete and recreate)
        $order->orderRegels()->delete();
        foreach ($items as $item) {
            if (! empty($item['product_id']) && ! empty($item['quantity'])) {
                $order->orderRegels()->create([
                    'product_id'  => (int) $item['product_id'],
                    'quantity'    => (int) $item['quantity'],
                    'total_price' => (float) ($item['total_price'] ?? 0),
                ]);
            }
        }

        Event::dispatch("{$this->entityName}.update.after", $order);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data'    => $order,
                'message' => $this->getUpdateSuccessMessage(),
            ]);
        }

        return redirect()->route($this->indexRoute)->with('success', $this->getUpdateSuccessMessage());
    }

    protected function getEditViewData(Request $request, Model $entity): array
    {
        // Eager-load relations needed for planning button visibility per orderregel
        $entity->load([
            'orderRegels.product.partnerProducts' => function ($q) {
                $q->select('partner_products.id');
            },
        ]);

        $salesLeads = \App\Models\SalesLead::with('lead')->get()->mapWithKeys(function($salesLead) {
            return [$salesLead->id => $salesLead->name . ' (' . ($salesLead->lead?->name ?? 'Geen lead') . ')'];
        })->toArray();

        return [
            $this->entityName => $entity,
            'salesLeads' => $salesLeads,
        ];
    }

    protected function validateStore(Request $request): void
    {
        $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'total_price'         => ['nullable', 'numeric', 'min:0'],
            'sales_lead_id'       => ['required', 'integer', 'exists:salesleads,id'],
            'items'               => ['nullable', 'array'],
            'items.*.product_id'  => ['nullable', 'integer', 'exists:products,id'],
            'items.*.quantity'    => ['nullable', 'integer', 'min:1'],
            'items.*.total_price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $this->validateStore($request);
    }

    protected function transformPayload(array $payload, ?int $id = null): array
    {
        // Compute total from items if not provided
        if (! empty($payload['items']) && is_array($payload['items'])) {
            $sum = 0;
            foreach ($payload['items'] as $item) {
                $sum += (float) ($item['total_price'] ?? 0);
            }
            $payload['total_price'] = $sum;
        }

        return parent::transformPayload($payload, $id);
    }

    protected function getCreateSuccessMessage(): string
    {
        return 'Order aangemaakt.';
    }

    protected function getUpdateSuccessMessage(): string
    {
        return 'Order bijgewerkt.';
    }

    protected function getDestroySuccessMessage(): string
    {
        return 'Order verwijderd.';
    }

    protected function getDeleteFailedMessage(): string
    {
        return 'Verwijderen mislukt.';
    }
}
