<?php

namespace App\Http\Controllers\Admin\Settings;

use Exception;
use App\DataGrids\Settings\OrderDataGrid;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderCheck;
use App\Models\SalesLead;
use App\Repositories\OrderRepository;
use App\Services\OrderCheckService;
use App\Services\OrderMailService;
use App\Services\OrderStatusService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;
use Webkul\Product\Models\Product;

class OrderController extends SimpleEntityController
{
    public function __construct(
        protected OrderRepository $orderRepository,
        protected OrderCheckService $orderCheckService,
        protected OrderMailService $orderMailService,
        protected OrderStatusService $orderStatusService
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
                $productId = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];

                // Get total_price from request, or calculate from product price
                $totalPrice = (float) ($item['total_price'] ?? 0);
                if ($totalPrice == 0) {
                    $product = Product::find($productId);
                    if ($product && $product->price) {
                        $totalPrice = (float) $product->price * $quantity;
                    }
                }

                $order->orderItems()->create([
                    'product_id'  => $productId,
                    'person_id'   => ! empty($item['person_id']) ? (int) $item['person_id'] : null,
                    'quantity'    => $quantity,
                    'total_price' => $totalPrice,
                    'status'      => OrderItemStatus::NEW,
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

        logger()->info('OrderController@update request', [
            'order_id' => $id,
            'payload'  => $request->all(),
        ]);
        Event::dispatch("{$this->entityName}.update.before", $id);

        $payload = $this->transformPayload($request->all(), $id);
        $items = $payload['items'] ?? [];
        unset($payload['items']);

        // Get original keys mapping if available (from validation normalization)
        $originalKeys = $request->input('_items_original_keys', []);

        $order = $this->orderRepository->update($payload, $id);

        // Preserve existing order items: update by ID, create new ones, do not delete missing to avoid losing planning
        if (is_array($items) && ! empty($items)) {
            // Load current items keyed by id for quick lookup
            $currentItems = $order->orderItems()->get()->keyBy('id');

            foreach ($items as $normalizedKey => $item) {
                // Skip invalid rows
                if (empty($item['product_id']) || empty($item['quantity'])) {
                    continue;
                }

                // Use original key if available, otherwise use normalized key
                // Original keys mapping helps us distinguish between existing items (numeric keys)
                // and new items (non-numeric keys like "item_1")
                $key = $originalKeys[$normalizedKey] ?? $normalizedKey;

                $productId = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];

                // Get total_price from request, or calculate from product price
                $totalPrice = (float) ($item['total_price'] ?? 0);
                if ($totalPrice == 0) {
                    $product = Product::find($productId);
                    if ($product && $product->price) {
                        $totalPrice = (float) $product->price * $quantity;
                    }
                }

                $attributes = [
                    'product_id'  => $productId,
                    'person_id'   => ! empty($item['person_id']) ? (int) $item['person_id'] : null,
                    'quantity'    => $quantity,
                    'total_price' => $totalPrice,
                ];

                // If key is a numeric id and exists, update without touching status
                if (is_numeric($key) && $currentItems->has((int) $key)) {
                    $current = $currentItems->get((int) $key);
                    $current->update($attributes);
                } else {
                    // New item: create with default status NEW
                    // Status will be automatically calculated by OrderItemObserver
                    $order->orderItems()->create($attributes);
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

        // Recalculate and update order status based on order items
        $this->orderStatusService->recalculateAndPersist($order);

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

    public function attachGvlForm(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with('salesLead')->findOrFail($orderId);

        if (! $order->salesLead) {
            return response()->json([
                'message' => 'Order heeft geen gekoppelde sales.',
            ], 422);
        }

        // Check if GVL form already exists (early return for better API response)
        if (! empty($order->salesLead->gvl_form_link)) {
            return response()->json([
                'message' => 'GVL formulier is al gekoppeld.',
                'gvl_form_link' => $order->salesLead->gvl_form_link,
            ], 422);
        }

        try {
            // Use OrderMailService to create the form (this method also checks for existing link)
            $formLink = $this->orderMailService->createFormRequestAndGetLink($order);

            // Reload the order to get updated sales lead
            $order->refresh();
            $order->load('salesLead');

            return response()->json([
                'message' => 'GVL formulier is gekoppeld.',
                'gvl_form_link' => $formLink,
            ], 200);
        } catch (Exception $e) {
            Log::error('OrderController@attachGvlForm failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'GVL formulier koppelen is mislukt: '.$e->getMessage(),
            ], 500);
        }
    }

    public function detachGvlForm(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with('salesLead')->findOrFail($orderId);

        if (! $order->salesLead || empty($order->salesLead->gvl_form_link)) {
            return response()->json([
                'message' => 'Er is geen GVL formulier gekoppeld aan deze order.',
            ], 422);
        }

        $apiUrl = rtrim(config('services.forms.api_url', 'http://forms'), '/');

        $formId = null;
        // Try multiple regex patterns to extract form ID from URL
        // Pattern 1: 'forms/3/step/1' or 'forms/3'
        if (preg_match('#forms/(\d+)(?:/step|/|$)#', $order->salesLead->gvl_form_link, $m)) {
            $formId = (int) $m[1];
        }
        
        // Pattern 2: Try to extract from API response ID if stored differently
        // This handles cases where the form URL might have a different structure
        if ($formId === null && preg_match('#/(\d+)(?:/step|/|$)#', $order->salesLead->gvl_form_link, $m)) {
            $formId = (int) $m[1];
        }
        
        if ($formId === null) {
            Log::error('OrderController@detachGvlForm could not resolve form id from URL', [
                'order_id' => $order->id,
                'gvl_form_link' => $order->salesLead->gvl_form_link,
            ]);
            throw new Exception('Could not resolve form id from url: '.$order->salesLead->gvl_form_link);
        }
        
        $deleteUrl = "{$apiUrl}/api/forms/".$formId;
        $token = config('services.forms.api_token');

        $httpClient = Http::timeout(10)->acceptJson();
        if ($token) {
            $httpClient = $httpClient->withHeaders([
                'X-API-KEY' => $token,
            ]);
        }

        try {
            $response = $httpClient->delete($deleteUrl,);
        } catch (Throwable $exception) {
            Log::error('OrderController@detachGvlForm kon Forms API niet bereiken', [
                'order_id'  => $order->id,
                'deleteUrl' => $deleteUrl,
                'message'   => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'GVL formulier ontkoppelen is mislukt. Forms API kon niet worden bereikt.',
            ], 502);
        }

        $status = $response->status();
        $body = $response->body();
        $json = null;

        try {
            $json = $response->json();
        } catch (Throwable) {
            $json = null;
        }

        if ($status !== 200) {
            Log::warning('OrderController@detachGvlForm Forms API fout', [
                'order_id'     => $order->id,
                'deleteUrl'    => $deleteUrl,
                'status'       => $status,
                'responseBody' => strlen($body) > 500 ? substr($body, 0, 500).'...' : $body,
                'responseJson' => $json,
            ]);

            return response()->json([
                'message' => $json['message'] ?? 'GVL formulier ontkoppelen is mislukt.',
            ], $status ?: 500);
        }

        $order->salesLead->update([
            'gvl_form_link' => null,
        ]);

        Log::info('OrderController@detachGvlForm geslaagd', [
            'order_id' => $order->id,
        ]);

        return response()->json([
            'message' => 'GVL formulier is ontkoppeld.',
        ]);
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
            'status' => OrderStatus::SENT,
        ]);

        return response()->json([
            'message' => 'Orderstatus gezet op verstuurd.',
        ], 200);
    }

    protected function getEditViewData(Request $request, Model $entity): array
    {
        // Eager-load relations needed for planning button visibility and planning info
        $entity->load([
            'orderItems.product.productGroup', // Load productGroup for name_with_path
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
        // Normalize items array keys and values before validation
        // Frontend sends items with keys like "1" (for existing) or "item_1" (for new)
        // Laravel validation requires numeric keys for items.* pattern
        // Also normalize product_id, person_id, quantity to integers
        $items = $request->input('items', []);

        logger()->info('OrderController@validateStore - before normalization', [
            'items'      => $items,
            'items_keys' => is_array($items) ? array_keys($items) : 'not_array',
        ]);

        if (is_array($items) && ! empty($items)) {
            $normalizedItems = [];
            $originalKeys = []; // Store mapping of normalized key -> original key
            $nextNewKey = 1000000; // Start high to avoid conflicts with existing IDs

            foreach ($items as $key => $item) {
                // Normalize item values to integers
                if (isset($item['product_id']) && $item['product_id'] !== null && $item['product_id'] !== '') {
                    $item['product_id'] = (int) $item['product_id'];
                }
                if (isset($item['person_id']) && $item['person_id'] !== null && $item['person_id'] !== '') {
                    $item['person_id'] = (int) $item['person_id'];
                }
                if (isset($item['quantity']) && $item['quantity'] !== null && $item['quantity'] !== '') {
                    $item['quantity'] = (int) $item['quantity'];
                }

                // Normalize array keys
                if (is_numeric($key)) {
                    // If key is numeric, use it directly (existing item)
                    $normalizedKey = (int) $key;
                    $normalizedItems[$normalizedKey] = $item;
                    $originalKeys[$normalizedKey] = $key;
                } else {
                    // For non-numeric keys like "item_1", use a high numeric key to avoid conflicts
                    // This ensures validation works with items.* pattern while preserving uniqueness
                    $normalizedKey = $nextNewKey++;
                    $normalizedItems[$normalizedKey] = $item;
                    $originalKeys[$normalizedKey] = $key;
                }
            }

            logger()->info('OrderController@validateStore - after normalization', [
                'normalized_items' => $normalizedItems,
                'normalized_keys'  => array_keys($normalizedItems),
                'original_keys'    => $originalKeys,
            ]);

            // Replace the items in the request using replace method which properly handles arrays
            $request->replace(array_merge($request->except('items'), [
                'items'                => $normalizedItems,
                '_items_original_keys' => $originalKeys,
            ]));
        }

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
        // If trying to set status to PLANNED, check if all order items are planned
        if ($requestedStatus === OrderStatus::PLANNED->value) {
            $order = $this->orderRepository->findOrFail($orderId);

            $hasUnplannedItems = $order->orderItems()
                ->where('status', '!=', OrderItemStatus::PLANNED->value)
                ->exists();

            if ($hasUnplannedItems) {
                throw ValidationException::withMessages([
                    'status' => 'Order kan niet op Ingepland gezet worden: er zijn nog orderitems die niet ingepland zijn.',
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
