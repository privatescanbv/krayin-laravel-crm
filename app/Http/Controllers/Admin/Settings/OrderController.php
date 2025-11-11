<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\OrderDataGrid;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\OrderCheck;
use App\Models\SalesLead;
use App\Repositories\OrderRepository;
use App\Services\OrderCheckService;
use App\Services\OrderMailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class OrderController extends SimpleEntityController
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected OrderCheckService $orderCheckService,
        protected OrderMailService $orderMailService
    ) {
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

        $salesLeads = SalesLead::with('lead')->get()->mapWithKeys(function ($salesLead) {
            return [$salesLead->id => $salesLead->name.' ('.($salesLead->lead?->name ?? 'Geen lead').')'];
        })->toArray();

        // Get persons from the selected sales lead
        $persons = [];
        if ($salesLeadId) {
            $salesLead = SalesLead::with('lead.persons')->find($salesLeadId);
            if ($salesLead && $salesLead->lead) {
                $persons = $salesLead->lead->persons()->get()->mapWithKeys(function ($person) {
                    return [$person->id => $person->name];
                })->toArray();
            }
        }

        return view($this->createView, [
            'salesLeadId' => $salesLeadId,
            'salesLeads'  => $salesLeads,
            'persons'     => $persons,
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
                $order->orderItems()->create([
                    'product_id'  => (int) $item['product_id'],
                    'person_id'   => ! empty($item['person_id']) ? (int) $item['person_id'] : null,
                    'quantity'    => (int) $item['quantity'],
                    'total_price' => (float) ($item['total_price'] ?? 0),
                    'status'      => OrderItemStatus::NIEUW,
                ]);
            }
        }

        Event::dispatch("{$this->entityName}.create.after", $order);

        // Create partner product checks for new order
        $this->orderCheckService->updatePartnerProductChecks($order);

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

        // Preserve existing order items: update by ID, create new ones, do not delete missing to avoid losing planning
        if (is_array($items) && ! empty($items)) {
            // Load current items keyed by id for quick lookup
            $currentItems = $order->orderItems()->get()->keyBy('id');

            foreach ($items as $key => $item) {
                // Skip invalid rows
                if (empty($item['product_id']) || empty($item['quantity'])) {
                    continue;
                }

                $attributes = [
                    'product_id'  => (int) $item['product_id'],
                    'person_id'   => ! empty($item['person_id']) ? (int) $item['person_id'] : null,
                    'quantity'    => (int) $item['quantity'],
                    'total_price' => (float) ($item['total_price'] ?? 0),
                ];

                // If key is a numeric id and exists, update without touching status
                if (is_numeric($key) && $currentItems->has((int) $key)) {
                    $current = $currentItems->get((int) $key);
                    $current->update($attributes);
                } else {
                    // New item: create with default status NIEUW
                    $order->orderItems()->create($attributes + [
                        'status' => OrderItemStatus::NIEUW,
                    ]);
                }
            }
        }

        // Update GVL form link if provided (empty string becomes null)
        if ($request->has('gvl_form_link')) {
            $gvlFormLink = $request->input('gvl_form_link');
            if ($order->salesLead) {
                $order->salesLead->update([
                    'gvl_form_link' => empty(trim($gvlFormLink)) ? null : $gvlFormLink,
                ]);
            }
        }

        Event::dispatch("{$this->entityName}.update.after", $order);

        // Update partner product checks after order items are updated
        $this->orderCheckService->updatePartnerProductChecks($order);

        // Debug logging to trace redirect behavior
        try {
            Log::debug('OrderController@update request received', [
                'order_id'      => $id,
                'route'         => $request->route()?->getName(),
                'method'        => $request->method(),
                'ajax'          => $request->ajax(),
                'wants_json'    => $request->wantsJson(),
                'redirect_to'   => $request->input('redirect_to'),
                'submit_names'  => array_keys(array_filter($request->all(), fn ($v, $k) => is_string($k), ARRAY_FILTER_USE_BOTH)),
            ]);
        } catch (Throwable $e) {
            // no-op for logging failures
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data'    => $order,
                'message' => $this->getUpdateSuccessMessage(),
            ]);
        }

        // Redirect to provided URL if present and allowed, otherwise fallback to index
        // Read redirect target from body or query (supports HTML5 formaction)
        $redirectTo = $request->input('redirect_to') ?: $request->query('redirect_to') ?: $request->get('redirect_to');
        try {
            Log::debug('OrderController@update resolved redirect target', [
                'order_id'    => $order->id,
                'redirect_to' => $redirectTo,
            ]);
        } catch (Throwable $e) {
        }
        if ($redirectTo) {
            try {
                Log::debug('OrderController@update redirecting to provided URL', [
                    'order_id'    => $order->id,
                    'redirect_to' => $redirectTo,
                ]);
            } catch (Throwable $e) {
            }

            return redirect()->to($redirectTo)->with('success', $this->getUpdateSuccessMessage());
        }

        try {
            Log::debug('OrderController@update redirecting to index fallback', [
                'order_id' => $order->id,
                'route'    => $this->indexRoute,
            ]);
        } catch (Throwable $e) {
        }

        return redirect()->route($this->indexRoute)->with('success', $this->getUpdateSuccessMessage());
    }

    public function getPersonsForSalesLead(Request $request, int $salesLeadId): JsonResponse
    {
        $salesLead = SalesLead::with('lead.persons')->findOrFail($salesLeadId);

        $persons = [];
        if ($salesLead && $salesLead->lead) {
            $persons = $salesLead->lead->persons()->get()->mapWithKeys(function ($person) {
                return [$person->id => $person->name];
            })->toArray();
        }

        return response()->json([
            'persons' => $persons,
        ]);
    }

    public function storeCheck(Request $request, int $orderId): JsonResponse
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'done'      => 'boolean',
            'removable' => 'boolean',
        ]);

        $check = OrderCheck::create([
            'order_id'  => $orderId,
            'name'      => $request->input('name'),
            'done'      => $request->input('done', false),
            'removable' => $request->input('removable', true),
        ]);

        return response()->json([
            'id'      => $check->id,
            'message' => 'Check toegevoegd.',
        ]);
    }

    public function updateCheck(Request $request, int $orderId, int $checkId): JsonResponse
    {
        $check = OrderCheck::where('order_id', $orderId)->findOrFail($checkId);

        $request->validate([
            'name'      => 'required|string|max:255',
            'done'      => 'boolean',
            'removable' => 'boolean',
        ]);

        $check->update([
            'name'      => $request->input('name'),
            'done'      => $request->input('done', false),
            'removable' => $request->input('removable', true),
        ]);

        return response()->json([
            'message' => 'Check bijgewerkt.',
        ]);
    }

    public function destroyCheck(Request $request, int $orderId, int $checkId): JsonResponse
    {
        $check = OrderCheck::where('order_id', $orderId)->findOrFail($checkId);

        // Prevent deletion if removable is false
        if (! $check->removable) {
            return response()->json([
                'message' => 'Deze check kan niet worden verwijderd.',
            ], 422);
        }

        $check->delete();

        return response()->json([
            'message' => 'Check verwijderd.',
        ]);
    }

    public function mailPreview(int $orderId): JsonResponse
    {
        $order = $this->orderRepository->findOrFail($orderId);

        $order->load([
            'orderItems.product',
            'orderItems.person',
            'salesLead.lead',
            'salesLead.contactPerson',
        ]);

        if (! $order->salesLead) {
            return response()->json([
                'message' => 'Order heeft geen gekoppelde sales.',
            ], 422);
        }

        $mailData = $this->orderMailService->buildMailData($order);

        return response()->json($mailData);
    }

    public function markAsSent(Request $request, int $orderId): JsonResponse
    {
        $order = $this->orderRepository->findOrFail($orderId);

        $order->update([
            'status' => OrderStatus::VERSTUURD,
        ]);

        return response()->json([
            'message' => 'Orderstatus gezet op verstuurd.',
        ], 200);
    }

    protected function getEditViewData(Request $request, Model $entity): array
    {
        // Eager-load relations needed for planning button visibility and planning info
        $entity->load([
            'orderItems.product.partnerProducts' => function ($q) {
                $q->select('partner_products.id', 'partner_products.product_id');
            },
            'orderItems.resourceOrderItems.resource',
            'orderItems.person',
            'salesLead.lead',
            'salesLead.contactPerson',
            'orderChecks',
        ]);

        $orderEmailOptions = $this->orderMailService->getEmailOptions($entity);
        $orderDefaultEmail = $this->orderMailService->getDefaultEmail($entity);

        $salesLeads = SalesLead::with('lead')->get()->mapWithKeys(function ($salesLead) {
            return [$salesLead->id => $salesLead->name.' ('.($salesLead->lead?->name ?? 'Geen lead').')'];
        })->toArray();

        // Get persons from the sales lead
        $persons = [];
        if ($entity->salesLead && $entity->salesLead->lead) {
            $persons = $entity->salesLead->lead->persons()->get()->mapWithKeys(function ($person) {
                return [$person->id => $person->name];
            })->toArray();
        }

        return [
            $this->entityName   => $entity,
            'salesLeads'        => $salesLeads,
            'persons'           => $persons,
            'orderEmailOptions' => $orderEmailOptions,
            'orderDefaultEmail' => $orderDefaultEmail,
        ];
    }

    protected function validateStore(Request $request): void
    {
        $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'total_price'         => ['nullable', 'numeric', 'min:0'],
            'sales_lead_id'       => ['required', 'integer', 'exists:salesleads,id'],
            'combine_order'       => ['nullable', 'boolean'],
            'items'               => ['nullable', 'array'],
            'items.*.product_id'  => ['nullable', 'integer', 'exists:products,id'],
            'items.*.person_id'   => ['nullable', 'integer', 'exists:persons,id'],
            'items.*.quantity'    => ['nullable', 'integer', 'min:1'],
            'items.*.total_price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $this->validateStore($request);

        // Validate status transition
        if ($request->has('status')) {
            $this->validateOrderStatus($request->input('status'), $id);
        }
    }

    protected function validateOrderStatus(string $requestedStatus, int $orderId): void
    {
        // If trying to set status to INGEPLAND, check if all order items are ready
        if ($requestedStatus === OrderStatus::INGEPLAND->value) {
            $order = $this->orderRepository->findOrFail($orderId);

            $hasItemsNeedingPlanning = $order->orderItems()
                ->where('status', OrderItemStatus::MOET_WORDEN_INGEPLAND->value)
                ->exists();

            if ($hasItemsNeedingPlanning) {
                throw ValidationException::withMessages([
                    'status' => 'Order kan niet op INGEPLAND gezet worden: er zijn nog orderitems die moeten worden ingepland.',
                ]);
            }
        }
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
