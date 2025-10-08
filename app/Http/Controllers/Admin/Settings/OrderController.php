<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\OrderDataGrid;
use App\Repositories\OrderRepository;
use Illuminate\Http\Request;

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

    protected function validateStore(Request $request): void
    {
        $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'total_price'    => ['nullable', 'numeric', 'min:0'],
            'items'          => ['nullable', 'array'],
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

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->validateStore($request);

        \Illuminate\Support\Facades\Event::dispatch("settings.{$this->entityName}.create.before");

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

        \Illuminate\Support\Facades\Event::dispatch("settings.{$this->entityName}.create.after", $order);

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

        \Illuminate\Support\Facades\Event::dispatch("settings.{$this->entityName}.update.before", $id);

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

        \Illuminate\Support\Facades\Event::dispatch("settings.{$this->entityName}.update.after", $order);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data'    => $order,
                'message' => $this->getUpdateSuccessMessage(),
            ]);
        }

        return redirect()->route($this->indexRoute)->with('success', $this->getUpdateSuccessMessage());
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
