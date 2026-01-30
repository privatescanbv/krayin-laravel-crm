<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\OrderDataGrid;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderCheck;
use App\Models\OrderItem;
use App\Models\SalesLead;
use App\Repositories\OrderRepository;
use App\Repositories\SalesLeadRepository;
use App\Services\FormService;
use App\Services\OrderCheckService;
use App\Services\OrderMailService;
use App\Services\OrderStatusService;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;
use Webkul\Admin\Http\Controllers\Mail\EmailController;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Core\Traits\PDFHandler;
use Webkul\EmailTemplate\Models\EmailTemplate;
use Webkul\Product\Models\Product;

class OrderController extends SimpleEntityController
{
    use PDFHandler;

    public function __construct(
        protected OrderRepository $orderRepository,
        protected OrderCheckService $orderCheckService,
        protected OrderMailService $orderMailService,
        protected OrderStatusService $orderStatusService,
        protected SalesLeadRepository $salesLeadRepository,
        protected FormService $formService,
        private EmailController $emailController
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

        $payload['status'] = OrderStatus::NEW->value;
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

    public function view(int $id): View
    {
        $order = $this->orderRepository->with([
            'salesLead.lead',
            'salesLead.persons',
            'salesLead.contactPerson',
            'orderItems.product',
            'orderItems.person',
        ])->findOrFail($id);

        $activitiesCount = $order->activities()->where('is_done', false)->count();

        return view('admin::orders.view', ['order' => $order, 'activitiesCount' => $activitiesCount]);
    }

    public function activities(int $id): AnonymousResourceCollection
    {
        $order = $this->orderRepository->findOrFail($id);

        $activities = $order->activities()->get();

        return ActivityResource::collection($activities);
    }

    public function emailsDetach(int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented for orders'], 501);
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

        // Preserve existing order items: update by ID, create new ones, delete missing
        if ($request->has('items')) {
            // Load current items keyed by id for quick lookup
            $currentItems = $order->orderItems()->get()->keyBy('id');
            $updatedItemIds = [];

            if (is_array($items) && ! empty($items)) {
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
                        $updatedItemIds[] = (int) $key;
                    } else {
                        // New item: create with default status NEW
                        // Status will be automatically calculated by OrderItemObserver
                        $order->orderItems()->create($attributes);
                    }
                }
            }

            // Delete items that are in DB but not in the update list
            $itemsToDelete = $currentItems->keys()->diff($updatedItemIds);
            if ($itemsToDelete->isNotEmpty()) {
                OrderItem::destroy($itemsToDelete->toArray());
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

    /**
     * @deprecated Use AnamnesisController methods instead. GVL forms are now managed per person/anamnesis.
     */
    public function attachGvlForm(Request $request, int $orderId): JsonResponse
    {
        return response()->json([
            'message' => 'GVL formulier koppelen gebeurt nu per persoon via anamnesis.',
        ], 410); // 410 Gone
    }

    /**
     * @deprecated Use AnamnesisController methods instead. GVL forms are now managed per person/anamnesis.
     */
    public function detachGvlForm(Request $request, int $orderId): JsonResponse
    {
        return response()->json([
            'message' => 'GVL formulier ontkoppelen gebeurt nu per persoon via anamnesis.',
        ], 410); // 410 Gone
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

        // Validate that confirmation letter content exists
        if (empty($order->confirmation_letter_content)) {
            return response()->json([
                'message' => 'De orderbevestiging brief moet eerst worden gegenereerd en opgeslagen voordat de order mail kan worden gemaakt.',
            ], 422);
        }

        $mailData = $this->orderMailService->buildMailData($order);

        // Add attachments array to the response (supports multiple attachments)
        $attachments = [
            [
                'url'      => route('admin.orders.confirmation.export-pdf', ['orderId' => $orderId]),
                'filename' => 'order-bevestiging-'.$order->id.'-'.date('Y-m-d').'.pdf',
            ],
        ];

        $mailData['attachments'] = $attachments;

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

    /**
     * Get list of available order confirmation templates.
     */
    public function getConfirmationTemplates(): JsonResponse
    {
        $templatesPath = resource_path('views/adminc/email_templates/order');
        $templates = [];

        if (File::exists($templatesPath)) {
            $files = File::files($templatesPath);

            foreach ($files as $file) {
                $filename = $file->getFilename();
                if (str_ends_with($filename, '.blade.php')) {
                    $name = str_replace('.blade.php', '', $filename);
                    $templates[] = [
                        'name'  => $name,
                        'label' => ucfirst(str_replace('_', ' ', $name)),
                    ];
                }
            }
        }

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * Get rendered order confirmation template content.
     */
    public function getConfirmationTemplateContent(Request $request, int $orderId): JsonResponse
    {
        $templateIdentifier = $request->query('template');

        if (! $templateIdentifier) {
            return response()->json([
                'error' => 'Template identifier is required',
            ], 400);
        }

        try {
            // Use EmailController to render template with order entity

            // Build entities array with order
            $entities = [
                'order' => $orderId,
            ];

            // Resolve variables from entities
            $variables = $this->emailController->resolveTemplateVariablesFromEntities($entities);

            // Get template from database
            $template = EmailTemplate::where('code', $templateIdentifier)
                ->orWhere('name', $templateIdentifier)
                ->first();

            if (! $template) {
                return response()->json([
                    'error'   => 'Template not found',
                    'message' => "Template with code or name '{$templateIdentifier}' does not exist in database",
                ], 404);
            }

            // Render template with layout
            $content = $this->emailController->renderTemplateToHTML($template, $variables);

            return response()->json([
                'data' => [
                    'content' => $content,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Order confirmation template rendering error: '.$e->getMessage(), [
                'template'  => $templateIdentifier ?? 'unknown',
                'order_id'  => $orderId,
                'exception' => $e,
            ]);

            return response()->json([
                'error'   => 'Template not found or error rendering template',
                'message' => $e->getMessage(),
                'trace'   => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Save confirmation letter content.
     */
    public function saveConfirmationLetter(Request $request, int $orderId): JsonResponse
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $order = $this->orderRepository->findOrFail($orderId);

        $order->update([
            'confirmation_letter_content' => $request->input('content'),
        ]);

        return response()->json([
            'message' => 'Orderbevestiging opgeslagen.',
            'data'    => [
                'content' => $order->confirmation_letter_content,
            ],
        ]);
    }

    /**
     * Export confirmation letter to PDF.
     */
    public function exportConfirmationLetterPDF(Request $request, int $orderId)
    {
        $order = $this->orderRepository->findOrFail($orderId);

        if (empty($order->confirmation_letter_content)) {
            return response()->json([
                'error' => 'Geen orderbevestiging beschikbaar om te exporteren.',
            ], 422);
        }

        $fileName = 'order-bevestiging-'.$order->id.'-'.date('Y-m-d');

        return $this->downloadPDF($order->confirmation_letter_content, $fileName);
    }

    /**
     * Get GVL form status.
     */
    /**
     * @deprecated Use AnamnesisController methods instead. GVL forms are now managed per person/anamnesis.
     */
    public function getGvlFormStatus(Request $request, int $orderId): JsonResponse
    {
        return response()->json([
            'message' => 'GVL formulier status ophalen gebeurt nu per persoon via anamnesis.',
        ], 410); // 410 Gone
    }

    protected function getEditViewData(Request $request, Model $entity): array
    {
        /** @var Order $order */
        $order = $entity instanceof Order
            ? $entity
            : throw new Exception('Entity is not an Order instance');
        // Model = Order
        // Eager-load relations needed for planning button visibility and planning info
        $order->load([
            'orderItems.product.productGroup', // Load productGroup for name_with_path
            'orderItems.product.partnerProducts' => function ($q) {
                $q->select('partner_products.id', 'partner_products.product_id', 'partner_products.resource_type_id');
            },
            'orderItems.resourceOrderItems.resource',
            'orderItems.person',
            'salesLead',
            'orderChecks',
        ]);

        // Load salesLead nested relations separately if salesLead exists
        if ($entity->salesLead) {
            try {
                $entity->salesLead->load(['persons', 'contactPerson']);
            } catch (Exception $e) {
                // If salesLead relations can't be loaded, just log and continue
                Log::warning('Failed to load salesLead relations for order', [
                    'order_id'      => $entity->id,
                    'sales_lead_id' => $entity->sales_lead_id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        $orderEmailOptions = $this->orderMailService->getEmailOptions($entity);
        $orderDefaultEmail = $this->orderMailService->getDefaultEmail($entity);

        $salesLeads = SalesLead::with('lead')->get()->mapWithKeys(function ($salesLead) {
            return [$salesLead->id => $salesLead->name.' ('.($salesLead->lead?->name ?? 'Geen lead').')'];
        })->toArray();

        // Get persons from the sales lead (only from salesLead.persons, not from lead.persons)
        $persons = [];
        $personsWithAnamnesis = [];
        $missingPersonsWarning = null;

        if ($entity->salesLead) {
            // Get persons directly from sales lead only
            $salesLeadPersons = $entity->salesLead->persons()->get();

            $persons = $salesLeadPersons->mapWithKeys(function ($person) {
                return [$person->id => $person->name];
            })->toArray();

            // Load anamnesis for each person (via lead_id and person_id)
            // Show all persons, even if they don't have an anamnesis yet

            $leadId = $entity->salesLead->lead_id;
            $personsWithAnamnesis = $this->salesLeadRepository
                ->findAnamnesisBySalesLeadId($leadId, $salesLeadPersons->pluck('id')->toArray())
                ->mapWithKeys(fn ($anamnesis) => [
                    $anamnesis->person_id => [
                        'person'    => $salesLeadPersons->firstWhere('id', $anamnesis->person_id),
                        'anamnesis' => $anamnesis,
                        'lead_id'   => $leadId,
                    ],
                ]);

            // Check if all persons have an order item
            if ($salesLeadPersons->isNotEmpty()) {
                $personIds = $salesLeadPersons->pluck('id')->toArray();
                $orderItemPersonIds = $entity->orderItems()
                    ->whereNotNull('person_id')
                    ->pluck('person_id')
                    ->unique()
                    ->toArray();

                $missingPersonIds = array_diff($personIds, $orderItemPersonIds);

                if (! empty($missingPersonIds)) {
                    $missingPersons = $salesLeadPersons->whereIn('id', $missingPersonIds);
                    $missingPersonNames = $missingPersons->pluck('name')->toArray();
                    $missingPersonsWarning = 'Niet alle personen uit de sales lead hebben een order item regel. Ontbrekende personen: '.implode(', ', $missingPersonNames);
                }
            }
        }

        return [
            $this->entityName         => $entity,

            'orders'                  => $entity,
            'salesLeads'              => $salesLeads,
            'persons'                 => $persons,
            'personsWithAnamnesis'    => $personsWithAnamnesis,
            'orderEmailOptions'       => $orderEmailOptions,
            'orderDefaultEmail'       => $orderDefaultEmail,
            'missingPersonsWarning'   => $missingPersonsWarning,
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
            // Replace the items in the request using replace method which properly handles arrays
            $request->replace(array_merge($request->except('items'), [
                'items'                => $normalizedItems,
                '_items_original_keys' => $originalKeys,
            ]));
        }

        $request->validate([
            'title'                => ['required', 'string', 'max:255'],
            'total_price'          => ['nullable', 'numeric', 'min:0'],
            'sales_lead_id'        => ['required', 'integer', 'exists:salesleads,id'],
            'combine_order'        => ['nullable', 'boolean'],
            'first_examination_at' => ['nullable', 'date'],
            'items'                => ['nullable', 'array'],
            'items.*.product_id'   => ['nullable', 'integer', 'exists:products,id'],
            'items.*.person_id'    => ['nullable', 'integer', 'exists:persons,id'],
            'items.*.quantity'     => ['nullable', 'integer', 'min:1'],
            'items.*.total_price'  => ['nullable', 'numeric', 'min:0'],
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

            $hasUnplannedItems = $order->orderItems
                ->filter(function (OrderItem $item) {
                    return $item->isPlannable() && $item->status !== OrderItemStatus::PLANNED;
                })
                ->isNotEmpty();

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

    /**
     * Build template variables for order confirmation templates.
     */
    protected function buildOrderTemplateVariables(Order $order): array
    {
        // Resolve customer name from sales lead
        $customerName = 'heer/mevrouw';
        if ($order->salesLead) {
            if ($order->salesLead->contactPerson) {
                $person = $order->salesLead->contactPerson;
                $customerName = trim(($person->first_name ?? '').' '.($person->last_name ?? ''));
                if (empty($customerName)) {
                    $customerName = $person->name ?? 'heer/mevrouw';
                }
            } elseif ($order->salesLead->lead) {
                $customerName = $order->salesLead->lead->name ?? 'heer/mevrouw';
            }
        }

        return [
            'order'         => $order,
            'customer_name' => $customerName,
        ];
    }
}
