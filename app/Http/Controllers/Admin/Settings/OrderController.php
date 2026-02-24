<?php

namespace App\Http\Controllers\Admin\Settings;

use App\DataGrids\Settings\OrderDataGrid;
use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Enums\PipelineType;
use App\Events\OrderMarkedAsSent;
use App\Models\Order;
use App\Models\OrderCheck;
use App\Models\OrderItem;
use App\Models\SalesLead;
use App\Repositories\OrderRepository;
use App\Repositories\SalesLeadRepository;
use App\Services\FormService;
use App\Services\Mail\EmailTemplateRenderingService;
use App\Services\OrderCheckService;
use App\Services\OrderMailService;
use App\Services\OrderStatusService;
use App\Services\PipelineCookieService;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
use Webkul\Activity\Models\Activity;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Core\Traits\PDFHandler;
use Webkul\EmailTemplate\Models\EmailTemplate;
use Webkul\Lead\Models\StageProxy;
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
        protected PipelineCookieService $pipelineCookieService,
        private EmailTemplateRenderingService $emailTemplateRenderingService
    ) {
        parent::__construct($orderRepository);

        $this->entityName = 'orders';
        $this->datagridClass = OrderDataGrid::class;
        $this->indexView = 'adminc.orders.index';
        $this->createView = 'admin::orders.create';
        $this->editView = 'admin::orders.edit';
        $this->indexRoute = 'admin.orders.index';
        $this->permissionPrefix = 'orders';
    }

    public function index(Request $request): View|JsonResponse
    {
        // Keep datagrid JSON responses working (used by table views / embedded datagrids).
        if ($request->ajax() || $request->wantsJson()) {
            return parent::index($request);
        }

        $pipeline = $this->pipelineCookieService->getPipeline(PipelineType::ORDER, $request->pipeline_id);

        $stages = $pipeline->stages->map(function ($stage) {
            return [
                'id'               => $stage->id,
                'code'             => $stage->code,
                'name'             => $stage->name,
                'description'      => $stage->description,
                'sort_order'       => $stage->sort_order,
                'lead_pipeline_id' => $stage->lead_pipeline_id,
                'is_won'           => (bool) $stage->is_won,
                'is_lost'          => (bool) $stage->is_lost,
                'leads'            => [
                    'data' => [],
                    'meta' => [
                        'total'        => 0,
                        'current_page' => 1,
                        'per_page'     => 10,
                        'last_page'    => 1,
                    ],
                ],
            ];
        })->toArray();

        return view($this->indexView, [
            'pipeline' => $pipeline,
            'stages'   => $stages,
        ]);
    }

    public function get(Request $request): JsonResponse
    {
        $request->validate([
            // pipeline_id is optional; if invalid/missing we fall back to default ORDER pipeline.
            'pipeline_id'        => ['nullable', 'integer'],
            'pipeline_stage_id'  => ['nullable', 'integer', 'exists:lead_pipeline_stages,id'],
            'page'               => ['nullable', 'integer', 'min:1'],
            'limit'              => ['nullable', 'integer', 'min:1', 'max:100'],
            // Accept typical querystring boolean values like "true"/"false" and 0/1.
            // (Laravel's `boolean` validator does not accept "true"/"false".)
            'exclude_won_lost'   => ['nullable', 'in:0,1,true,false'],
            'sort'               => ['nullable', 'string'],
            'order'              => ['nullable', 'string', 'in:asc,desc'],
        ]);

        try {
            $pipeline = $this->pipelineCookieService->getPipeline(
                PipelineType::ORDER,
                $request->filled('pipeline_id') ? (int) $request->pipeline_id : null
            );
        } catch (ModelNotFoundException|Exception) {
            // If the requested pipeline id doesn't exist (or any resolution error occurs),
            // gracefully fall back to the default ORDER pipeline.
            $pipeline = $this->pipelineCookieService->getPipeline(PipelineType::ORDER, null);
        }

        $limit = (int) ($request->input('limit', 10));
        $page = (int) ($request->input('page', 1));
        $excludeWonLost = $request->boolean('exclude_won_lost', false);

        $sort = (string) ($request->input('sort', 'created_at'));
        $order = (string) ($request->input('order', 'desc'));

        // Whitelist sortable columns.
        if (! in_array($sort, ['created_at', 'id', 'total_price'], true)) {
            $sort = 'created_at';
        }

        $stages = $pipeline->stages;

        if ($request->filled('pipeline_stage_id')) {
            $stageId = (int) $request->input('pipeline_stage_id');
            $stages = $stages->where('id', $stageId);
        }

        $data = [];

        foreach ($stages as $stage) {
            if ($excludeWonLost && ($stage->is_won || $stage->is_lost)) {
                continue;
            }

            $query = Order::query()
                ->with([
                    'stage',
                    'salesLead.persons',
                    'salesLead.lead',
                ])
                ->withCount([
                    'activities as open_activities_count' => function ($q) {
                        $q->where('is_done', 0);
                    },
                ])
                ->where('pipeline_stage_id', $stage->id)
                ->orderBy($sort, $order);

            $paginator = $query->paginate($limit, ['*'], 'page', $page);

            $orders = $paginator->getCollection()->map(function (Order $order) {
                $stagePayload = $order->stage ? [
                    'id'      => $order->stage->id,
                    'name'    => $order->stage->name,
                    'code'    => $order->stage->code,
                    'is_won'  => (bool) $order->stage->is_won,
                    'is_lost' => (bool) $order->stage->is_lost,
                ] : null;

                $patientName = null;
                if ($order->salesLead) {
                    $patientName = $order->salesLead->persons?->first()?->name
                        ?: $order->salesLead->lead?->name
                        ?: $order->salesLead->name;
                }

                return [
                    'id'                   => $order->id,
                    'title'                => $order->title,
                    'total_price'          => $order->total_price,
                    'first_examination_at' => $order->first_examination_at,
                    'created_at'           => $order->created_at,
                    'pipeline_stage_id'    => $order->pipeline_stage_id,
                    'pipeline_stage'       => $stagePayload,
                    'stage'                => $stagePayload,
                    'open_activities_count'=> (int) ($order->open_activities_count ?? 0),
                    'patient_name'         => $patientName,
                    'sales_lead'           => $order->salesLead ? [
                        'id'   => $order->salesLead->id,
                        'name' => $order->salesLead->name,
                    ] : null,
                ];
            })->values();

            $data[$stage->sort_order] = [
                'id'               => $stage->id,
                'code'             => $stage->code,
                'name'             => $stage->name,
                'description'      => $stage->description,
                'sort_order'       => $stage->sort_order,
                'lead_pipeline_id' => $stage->lead_pipeline_id,
                'is_won'           => (bool) $stage->is_won,
                'is_lost'          => (bool) $stage->is_lost,
                'leads'            => [
                    'data' => $orders,
                    'meta' => [
                        'total'        => (int) $paginator->total(),
                        'current_page' => (int) $paginator->currentPage(),
                        'per_page'     => (int) $paginator->perPage(),
                        'last_page'    => (int) $paginator->lastPage(),
                    ],
                ],
            ];
        }

        return response()->json($data);
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

        // Determine department for initial order stage and default user_id from sales lead
        $departmentName = null;
        if (! empty($payload['sales_lead_id'])) {
            $sl = SalesLead::with('lead.department')->find($payload['sales_lead_id']);
            $departmentName = $sl?->lead?->department?->name;

            if (empty($payload['user_id'])) {
                $payload['user_id'] = $sl?->user_id;
            }
        }
        $payload['pipeline_stage_id'] = Order::firstOrderStageId($departmentName);
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

        return response()->json($mailData);
    }

    public function markAsSent(Request $request, int $orderId): JsonResponse
    {
        $order = $this->orderRepository->with('salesLead.lead.department')->findOrFail($orderId);

        $departmentName = $order->salesLead?->lead?->department?->name;
        $order->update([
            'pipeline_stage_id' => Order::orderVerzondenStageId($departmentName),
        ]);

        // Dispatch event - listeners will handle PDF activity creation
        OrderMarkedAsSent::dispatch($order, auth()->id());

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
            // Build entities array with order
            $entities = [
                'order' => $orderId,
            ];

            // Resolve variables from entities
            $variables = $this->emailTemplateRenderingService->resolveVariablesFromEntities($entities);

            // Get template from database
            $template = EmailTemplate::byCode($templateIdentifier)
                ->orWhere('name', $templateIdentifier)
                ->first();

            if (! $template) {
                return response()->json([
                    'error'   => 'Template not found',
                    'message' => "Template with code or name '{$templateIdentifier}' does not exist in database",
                ], 404);
            }

            // Render template with layout
            $content = $this->emailTemplateRenderingService->renderTemplateToHTML($template, $variables);

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

    public function updateStage($id)
    {
        request()->validate([
            'lead_pipeline_stage_id' => 'required|exists:lead_pipeline_stages,id',
        ]);

        $order = Order::findOrFail($id);
        $targetStage = StageProxy::findOrFail((int) request('lead_pipeline_stage_id'));

        // Validate stage transition (e.g. unplanned items check)
        $this->validateOrderStage($targetStage->id, $id);

        // Optionally close open activities for this order when requested
        if (request()->boolean('close_open_activities')) {
            Activity::where('order_id', $order->id)
                ->where('is_done', 0)
                ->update(['is_done' => 1]);
        }

        // Order only updates pipeline_stage_id (no closed_at / lost_reason)
        $order->update([
            'pipeline_stage_id' => $targetStage->id,
        ]);

        return response()->json([
            'message' => 'Order stage bijgewerkt.',
        ]);
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
            'user_id'              => ['nullable', 'integer', 'exists:users,id'],
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

        // Validate stage transition
        if ($request->has('pipeline_stage_id')) {
            $this->validateOrderStage((int) $request->input('pipeline_stage_id'), $id);
        }
    }

    protected function validateOrderStage(int $requestedStageId, int $orderId): void
    {
        // If trying to set stage to a "wachten-uitvoering" stage, check if all order items are planned
        $wachtenStageIds = [
            PipelineStage::ORDER_WACHTEN_UITVOERING->id(),
            PipelineStage::ORDER_WACHTEN_UITVOERING_HERNIA->id(),
        ];

        if (in_array($requestedStageId, $wachtenStageIds, true)) {
            $order = $this->orderRepository->findOrFail($orderId);

            $hasUnplannedItems = $order->orderItems
                ->filter(function (OrderItem $item) {
                    return $item->isPlannable() && $item->status !== OrderItemStatus::PLANNED;
                })
                ->isNotEmpty();

            if ($hasUnplannedItems) {
                throw ValidationException::withMessages([
                    'pipeline_stage_id' => 'Order kan niet op Ingepland gezet worden: er zijn nog orderitems die niet ingepland zijn.',
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
