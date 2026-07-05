<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Actions\Activities\CreatePatientMessageFromActivityAction;
use App\DataGrids\Settings\OrderDataGrid;
use App\Enums\ActivityType;
use App\Enums\Currency;
use App\Enums\LostReason;
use App\Enums\OrderItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Enums\PipelineDefaultKeys;
use App\Enums\PipelineType;
use App\Http\Requests\Admin\OrderPaymentRequest;
use App\Http\Requests\Admin\OrderRequest;
use App\Models\Anamnesis;
use App\Models\Order;
use App\Models\OrderCheck;
use App\Models\OrderPayment;
use App\Models\SalesLead;
use App\Repositories\OrderRepository;
use App\Repositories\SalesLeadRepository;
use App\Services\Afb\AfbDispatchService;
use App\Services\OrderMailService;
use App\Services\OrderRefundService;
use App\Services\OrderStatusService;
use App\Services\OrderStatusTransitionValidator;
use App\Services\PipelineCookieService;
use App\Services\StageTransitionAttributes;
use App\Traits\CreatesInlineOrganization;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;
use Prettus\Repository\Criteria\RequestCriteria;
use Throwable;
use Webkul\Activity\Models\Activity;
use Webkul\Activity\Repositories\ActivityRepository;
use Webkul\Admin\Http\Controllers\Concerns\ConcatsEmailActivities;
use Webkul\Admin\Http\Controllers\Concerns\HasAdvancedSearch;
use Webkul\Admin\Http\Resources\ActivityResource;
use Webkul\Admin\Http\Resources\OrderLookupResource;
use Webkul\Contact\Models\Person;
use Webkul\Core\Traits\PDFHandler;
use Webkul\Email\Repositories\AttachmentRepository;
use Webkul\Lead\Models\Stage;
use Webkul\Lead\Models\StageProxy;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Product\Models\Product;

class OrderController extends SimpleEntityController
{
    use ConcatsEmailActivities, HasAdvancedSearch, PDFHandler;
    use CreatesInlineOrganization;

    public function __construct(
        protected OrderRepository $orderRepository,
        protected OrderMailService $orderMailService,
        protected OrderStatusService $orderStatusService,
        protected SalesLeadRepository $salesLeadRepository,
        protected PipelineCookieService $pipelineCookieService,
        private AfbDispatchService $afbDispatchService,
        private readonly AttachmentRepository $attachmentRepository,
        private readonly OrderRefundService $orderRefundService,
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
                    'payments',
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

                $paymentStatus = $order->paymentStatus();

                return [
                    'id'                     => $order->id,
                    'order_number'           => $order->order_number,
                    'title'                  => $order->title,
                    'total_price'            => $order->total_price,
                    'first_examination_at'   => $order->firstExaminationCarbon(),
                    'created_at'             => $order->created_at,
                    'pipeline_stage_id'      => $order->pipeline_stage_id,
                    'pipeline_stage'         => $stagePayload,
                    'stage'                  => $stagePayload,
                    'open_activities_count'  => (int) ($order->open_activities_count ?? 0),
                    'patient_name'           => $patientName,
                    'user_id'                => $order->user_id,
                    'lost_reason_label'      => $order->lost_reason_label ?? null,
                    'sales_lead'             => $order->salesLead ? [
                        'id'   => $order->salesLead->id,
                        'name' => $order->salesLead->name,
                    ] : null,
                    'payment_status_label'       => $paymentStatus->label(),
                    'payment_status_badge_class' => $paymentStatus->badgeClass(),
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
        $salesLeadId = $request->get('sales_lead_id') ?? old('sales_lead_id');

        $salesLeadName = null;
        $defaultOrderTitle = null;
        $resolvedOrganization = null;

        if ($salesLeadId) {
            $salesLead = SalesLead::with('lead')->find($salesLeadId);
            if ($salesLead) {
                $salesLeadName = $salesLead->labelWithLeadSuffix();
                $defaultOrderTitle = $salesLead->name;
                $resolvedOrganization = $salesLead->resolveDefaultOrganizationForOrder();
            }
        }

        $oldInput = $request->session()->get('_old_input', []);

        $defaultOrganization = null;
        if (is_array($oldInput) && ! empty($oldInput['organization_id'])) {
            $defaultOrganization = [
                'id'   => (int) $oldInput['organization_id'],
                'name' => (string) ($oldInput['organization_name'] ?? ''),
            ];
        } elseif ($resolvedOrganization) {
            $defaultOrganization = [
                'id'   => $resolvedOrganization->id,
                'name' => $resolvedOrganization->name,
            ];
        }

        if (is_array($oldInput) && array_key_exists('is_business', $oldInput)) {
            $v = $oldInput['is_business'];
            $initialIsBusinessForOrderOrg = filter_var($v, FILTER_VALIDATE_BOOLEAN) || (string) $v === '1';
        } elseif (is_array($oldInput) && ! empty($oldInput['organization_id'])) {
            $initialIsBusinessForOrderOrg = true;
        } elseif ($resolvedOrganization) {
            $initialIsBusinessForOrderOrg = true;
        } else {
            $initialIsBusinessForOrderOrg = false;
        }

        return view($this->createView, [
            'salesLeadId'                  => $salesLeadId,
            'salesLeadName'                => $salesLeadName,
            'defaultOrderTitle'            => $defaultOrderTitle,
            'defaultOrganization'          => $defaultOrganization,
            'initialIsBusinessForOrderOrg' => $initialIsBusinessForOrderOrg,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->validateStore($request);

        $inlineOrgError = $this->mergeInlineOrganizationFromRequest($request);
        if ($inlineOrgError !== null) {
            return $inlineOrgError;
        }

        Event::dispatch("{$this->entityName}.create.before");

        $payload = $this->transformPayload($request->all());
        $items = $payload['items'] ?? [];
        unset($payload['items']);

        // Determine department for initial order stage and default user_id from sales lead
        $department = null;
        if (! empty($payload['sales_lead_id'])) {
            $sl = SalesLead::with('lead.department')->find($payload['sales_lead_id']);
            $department = $sl?->department;

            if (empty($payload['user_id'])) {
                $slUserId = $sl?->user_id;
                $payload['user_id'] = ($slUserId && $slUserId > 0) ? (int) $slUserId : null;
            }
        }
        $payload['pipeline_stage_id'] = Order::firstOrderStageId($department);
        $order = $this->orderRepository->create($payload);

        // Persist items
        foreach ($items as $item) {
            if (! empty($item['product_id']) && ! empty($item['quantity'])) {
                $productId = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];

                // Get total_price from request, or calculate from product price.
                // Only fall back to the product price when the field was not explicitly provided —
                // an explicit 0 means "free" and must be preserved.
                $hasTotalPrice = array_key_exists('total_price', $item)
                    && $item['total_price'] !== ''
                    && $item['total_price'] !== null;
                $totalPrice = $hasTotalPrice ? (float) $item['total_price'] : 0;
                if (! $hasTotalPrice) {
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

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'data'    => $order,
                'message' => $this->getCreateSuccessMessage(),
            ], 200);
        }

        return redirect()->route('admin.orders.edit', $order->id)->with('success', $this->getCreateSuccessMessage());
    }

    public function view(int $id): View
    {
        $order = $this->orderRepository->with([
            'organization',
            'salesLead.lead',
            'salesLead.persons',
            'salesLead.contactPerson',
            'orderItems.product',
            'orderItems.product.partnerProducts.purchasePrice',
            'orderItems.person',
            'orderItems.purchasePrice',
            'orderItems.invoicePurchasePrice',
            'orderItems.resourceOrderItems.resource.clinicDepartment.clinic',
            'afbPersonDocuments.dispatch.clinicDepartment',
            'payments',
            'orderChecks',
        ])->findOrFail($id);

        $activitiesCount = $order->activities()->where('is_done', false)->count();

        $avbDispatchReadiness = $this->afbDispatchService->getAvbDispatchReadiness($order);
        $afbNeedsManualBanner = $avbDispatchReadiness['needs_manual_send'];
        $afbHasBatchSuccess = $this->afbDispatchService->hasSuccessfulBatchDispatchForOrder($order);

        $afbStatusRows = $order->afbStatusRows();

        $totalChecks = $order->orderChecks->count();
        $completedChecks = $order->orderChecks->where('done', true)->count();

        $composeMailEmails = [];
        if ($order->salesLead?->persons && $order->salesLead->persons->isNotEmpty()) {
            $composeMailEmails[] = [
                'value'      => $order->salesLead->defaultEmailContactPerson() ?: '',
                'is_default' => true,
            ];
        }

        return view('admin::orders.view', [
            'order'                => $order,
            'activitiesCount'      => $activitiesCount,
            'composeMailEmails'    => $composeMailEmails,
            'afbNeedsManualBanner' => $afbNeedsManualBanner,
            'afbHasBatchSuccess'   => $afbHasBatchSuccess,
            'afbSendUrl'           => route('admin.orders.send_afb', $order->id),
            'afbStatusRows'        => $afbStatusRows,
            'avbDispatchReadiness' => $avbDispatchReadiness,
            'totalChecks'          => $totalChecks,
            'completedChecks'      => $completedChecks,
        ]);
    }

    public function activities(int $id): AnonymousResourceCollection
    {
        $order = $this->orderRepository->with(['salesLead'])->findOrFail($id);

        if (request('tab') === ActivityType::SYSTEM->value) {
            return $this->paginateSystemActivities(Activity::where('order_id', $order->id));
        }

        $isDoneFilter = request()->has('is_done') ? (int) request('is_done') : null;

        $query = Activity::query()->where('order_id', $order->id);

        if (! is_null($isDoneFilter)) {
            $query->where('is_done', $isDoneFilter);
        }

        $activities = $query->with('portalPersons')->get();

        return ActivityResource::collection(
            $this->concatEmailActivitiesFor('order', $id, $activities, $this->attachmentRepository, $isDoneFilter)
        );
    }

    public function emailsDetach(int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented for orders'], 501);
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $this->validateUpdate($request, $id);

        $inlineOrgError = $this->mergeInlineOrganizationFromRequest($request);
        if ($inlineOrgError !== null) {
            return $inlineOrgError;
        }

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

        // Snapshot uitstaande terugbetalingen vóór verwerking, zodat we na afloop kunnen detecteren
        // of er automatisch een nieuwe terugbetaling is aangemaakt (door observer of service).
        $pendingRefundBefore = (float) $order->payments()->where('type', PaymentType::REFUND->value)->whereNull('paid_at')->sum('amount');

        // Preserve existing order items: update by ID, create new ones, mark removed as LOST
        if ($request->has('items') || $request->has('removed_order_item_ids')) {
            $currentItems = $order->orderItems()->get()->keyBy('id');
            $currentActiveItems = $currentItems->filter(
                fn ($item) => $item->status !== OrderItemStatus::LOST
            );
            $updatedItemIds = [];

            if (is_array($items) && ! empty($items)) {
                $sortCounter = 0;
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

                    // Get total_price from request, or calculate from product price.
                    // Only fall back to the product price when the field was not explicitly provided —
                    // an explicit 0 means "free" and must be preserved.
                    $hasTotalPrice = array_key_exists('total_price', $item)
                        && $item['total_price'] !== ''
                        && $item['total_price'] !== null;
                    $totalPrice = $hasTotalPrice ? (float) $item['total_price'] : 0;
                    if (! $hasTotalPrice) {
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
                        'sort_order'  => $sortCounter++,
                    ];

                    if (! empty($item['status'])) {
                        $attributes['status'] = OrderItemStatus::from((string) $item['status']);
                    }

                    if (is_numeric($key) && $currentItems->has((int) $key)) {
                        $current = $currentItems->get((int) $key);
                        $current->update($attributes);
                        $updatedItemIds[] = (int) $key;
                    } else {
                        // New item: create with default status NEW
                        // Status will be automatically calculated by OrderItemObserver
                        $newItem = $order->orderItems()->create($attributes);
                        $updatedItemIds[] = $newItem->id;
                    }
                }
            }

            $explicitlyRemovedIds = collect($request->input('removed_order_item_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            foreach ($explicitlyRemovedIds as $itemId) {
                $item = $currentItems->get($itemId);
                if ($item && $item->status !== OrderItemStatus::LOST) {
                    $item->update(['status' => OrderItemStatus::LOST]);
                }
                $updatedItemIds[] = $itemId;
            }

            // When the UI sends explicit removals, trust those IDs only. Otherwise fall back to
            // diffing the payload (API clients / legacy behaviour).
            if (! $request->has('removed_order_item_ids')) {
                $itemsToRemove = $currentActiveItems->keys()->diff(collect($updatedItemIds)->unique());
                foreach ($itemsToRemove as $itemId) {
                    $item = $currentItems->get($itemId);
                    if ($item && $item->status !== OrderItemStatus::LOST) {
                        $item->update(['status' => OrderItemStatus::LOST]);
                    }
                }
            }

            $order->recalculateTotalPrice();
        }

        // Ook aanroepen buiten het items-blok: dekt situaties waarbij de observer niet firedde
        // (bijv. bulk-updates via OrderObserver). Idempotent: als surplus al 0 is, gebeurt niets.
        $this->orderRefundService->createRefundIfSurplus($order, auth()->id());

        // Bepaal of er tijdens dit request een nieuwe terugbetaling is aangemaakt.
        $pendingRefundAfter = (float) $order->payments()->where('type', PaymentType::REFUND->value)->whereNull('paid_at')->sum('amount');
        $autoRefundAmount = round($pendingRefundAfter - $pendingRefundBefore, 2) > 0.01
            ? round($pendingRefundAfter - $pendingRefundBefore, 2)
            : null;

        Event::dispatch("{$this->entityName}.update.after", $order);

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
                'data'           => $order,
                'message'        => $this->getUpdateSuccessMessage(),
                'refund_created' => $autoRefundAmount !== null,
                'refund_amount'  => $autoRefundAmount,
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

            $redirect = redirect()->to($redirectTo)->with('success', $this->getUpdateSuccessMessage());
            if ($autoRefundAmount !== null) {
                $redirect = $redirect->with('info', 'Let op: een terugbetaling van € '.number_format($autoRefundAmount, 2, ',', '.').' is automatisch aangemaakt.');
            }

            return $redirect;
        }

        try {
            Log::debug('OrderController@update redirecting to index fallback', [
                'order_id' => $order->id,
                'route'    => $this->indexRoute,
            ]);
        } catch (Throwable $e) {
        }

        $redirect = redirect()->route($this->indexRoute)->with('success', $this->getUpdateSuccessMessage());
        if ($autoRefundAmount !== null) {
            $redirect = $redirect->with('info', 'Let op: een terugbetaling van € '.number_format($autoRefundAmount, 2, ',', '.').' is automatisch aangemaakt.');
        }

        return $redirect;
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
        $salesLead = SalesLead::with(['persons', 'lead.persons'])->findOrFail($salesLeadId);

        $salesLeadPersons = $salesLead->persons()->get();
        $persons = $salesLeadPersons->isNotEmpty()
            ? $salesLeadPersons->mapWithKeys(fn ($p) => [$p->id => $p->name])->toArray()
            : ($salesLead->lead
                ? $salesLead->lead->persons()->get()->mapWithKeys(fn ($p) => [$p->id => $p->name])->toArray()
                : []);

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

    /**
     * Return clinics (from order item resource bookings) and unchecked order checks for the report upload modal.
     */
    public function reportUploadData(int $orderId): JsonResponse
    {
        $order = $this->orderRepository->with([
            'orderItems.resourceOrderItem.resource.clinicDepartment.clinic',
            'orderChecks',
        ])->findOrFail($orderId);

        $clinics = $order->orderItems
            ->map(function ($item) {
                $resource = $item->resourceOrderItem?->resource;
                if (! $resource) {
                    return null;
                }

                return $resource->clinicDepartment?->clinic;
            })
            ->filter()
            ->unique('id')
            ->values()
            ->map(fn ($clinic) => [
                'id'   => $clinic->id,
                'name' => $clinic->name,
            ]);

        $uncheckedChecks = $order->orderChecks
            ->where('done', false)
            ->values()
            ->map(fn ($check) => [
                'id'   => $check->id,
                'name' => $check->name,
            ]);

        return response()->json([
            'clinics' => $clinics,
            'checks'  => $uncheckedChecks,
        ]);
    }

    /**
     * Upload report file(s): create a file Activity per file linked to order + clinic, and mark selected checks as done.
     */
    public function storeReport(Request $request, int $orderId): JsonResponse
    {
        $request->validate([
            'files'             => 'required|array|min:1',
            'files.*'           => 'file|max:20480',
            'clinic_id'         => 'required|integer|exists:clinics,id',
            'check_ids'         => 'nullable|array',
            'check_ids.*'       => 'integer|exists:order_checks,id',
            'title'             => 'nullable|string|max:255',
            'comment'           => 'nullable|string',
            'publish_to_portal' => 'nullable|boolean',
            'person_ids'        => 'nullable|array',
            'person_ids.*'      => 'integer|exists:persons,id',
        ]);

        $order = $this->orderRepository->findOrFail($orderId);

        $activityRepository = app(ActivityRepository::class);

        $publishToPortal = filter_var($request->input('publish_to_portal', false), FILTER_VALIDATE_BOOLEAN);
        $personIds = array_filter(array_map('intval', (array) $request->input('person_ids', [])));

        $files = $request->file('files');
        $baseTitle = $request->input('title', '');
        $lastActivity = null;

        foreach ($files as $file) {
            $title = count($files) > 1
                ? ($baseTitle ? "{$baseTitle} – {$file->getClientOriginalName()}" : $file->getClientOriginalName())
                : ($baseTitle ?: $file->getClientOriginalName());

            $activity = $activityRepository->create([
                'type'      => ActivityType::FILE,
                'title'     => $title,
                'comment'   => $request->input('comment'),
                'is_done'   => true,
                'user_id'   => auth()->id(),
                'order_id'  => $order->id,
                'clinic_id' => $request->input('clinic_id'),
                'file'      => $file,
            ]);

            if ($publishToPortal) {
                $resolvedPersonIds = ! empty($personIds)
                    ? $personIds
                    : $activity->getPatientsFromActivity()->pluck('id')->all();

                if (! empty($resolvedPersonIds)) {
                    $activity->syncPortalPersons($resolvedPersonIds);
                    CreatePatientMessageFromActivityAction::notifyPortalPersons($activity);
                }
            }

            $lastActivity = $activity;
        }

        $checkIds = $request->input('check_ids', []);
        OrderCheck::where('order_id', $orderId)
            ->whereIn('id', $checkIds)
            ->update(['done' => true]);

        $count = count($files);
        $hasChecks = ! empty($checkIds);
        $message = $count > 1
            ? ($hasChecks ? "{$count} rapportages succesvol geüpload en checks afgevinkt." : "{$count} rapportages succesvol geüpload.")
            : ($hasChecks ? 'Rapportage succesvol geüpload en checks afgevinkt.' : 'Rapportage succesvol geüpload.');

        return response()->json([
            'data'    => new ActivityResource($lastActivity),
            'message' => $message,
        ]);
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
            'lost_reason'            => ['nullable', new Enum(LostReason::class)],
            'closed_at'              => 'nullable',
            'user_id'                => 'nullable|integer|exists:users,id',
        ]);

        $order = Order::findOrFail($id);
        $targetStage = StageProxy::findOrFail((int) request('lead_pipeline_stage_id'));

        // Valideer status transitie (bijv. plannable items mogen niet meer nieuw zijn)
        try {
            OrderStatusTransitionValidator::validateTransition($order, $targetStage->id);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Order status transitie validatie gefaald: '.$e->getMessage(),
                'errors'  => $e->errors(),
            ], 422);
        }

        // Optionally close open activities for this order when requested
        if (request()->boolean('close_open_activities')) {
            Activity::where('order_id', $order->id)
                ->where('is_done', 0)
                ->update(['is_done' => 1]);
        }

        $attributes = [
            'pipeline_stage_id' => $targetStage->id,
            'closed_at'         => StageTransitionAttributes::resolveClosedAt($targetStage, request('closed_at')),
        ];

        // If stage is being set to won, require + persist user_id
        if ($targetStage->is_won) {
            request()->validate([
                'user_id' => 'required|integer|exists:users,id',
            ]);

            $attributes['user_id'] = (int) request('user_id');
            $attributes['lost_reason'] = null;
        } elseif ($targetStage->is_lost) {
            request()->validate([
                'lost_reason' => ['required', new Enum(LostReason::class)],
            ]);
        }

        $attributes['lost_reason'] = StageTransitionAttributes::resolveLostReason($targetStage, request('lost_reason'));

        $order->update($attributes);

        return response()->json([
            'message' => 'Order stage bijgewerkt.',
        ]);
    }

    /**
     * Betalingsoverzicht: alle orders die nog niet volledig betaald zijn.
     * Optioneel gefilterd op pipeline via ?pipeline_id=
     */
    public function paymentOverview(Request $request): View
    {
        /** @var PipelineRepository $pipelineRepo */
        $pipelineRepo = app(PipelineRepository::class);

        $pipelines = $pipelineRepo->getPipelinesByType(PipelineType::ORDER);
        $currentPipeline = $this->pipelineCookieService->getPipeline(
            PipelineType::ORDER,
            $request->filled('pipeline_id') ? (int) $request->pipeline_id : null
        );

        $stageIds = $currentPipeline->stages->pluck('id');

        $orders = Order::query()
            ->with(['payments', 'orderItems.resourceOrderItems'])
            ->whereIn('pipeline_stage_id', $stageIds)
            ->whereHas('stage', fn ($q) => $q->where('is_lost', false))
            ->where(function ($q) {
                $q->whereNotNull('first_examination_at')
                    ->orWhereHas('orderItems', function ($orderItems) {
                        $orderItems
                            ->where('status', '!=', OrderItemStatus::LOST->value)
                            ->whereHas('resourceOrderItems', fn ($roi) => $roi->whereNotNull('from'));
                    });
            })
            ->get()
            ->filter(fn (Order $o) => $o->firstExaminationCarbon() !== null)
            ->sortBy(fn (Order $o) => $o->firstExaminationCarbon()?->getTimestamp() ?? PHP_INT_MAX)
            ->filter(fn (Order $o) => ! in_array($o->paymentStatus(), [OrderPaymentStatus::FULLY_PAID, OrderPaymentStatus::NOT_APPLICABLE], true))
            ->values();

        return view('adminc.orders.payment-overview', [
            'orders'               => $orders,
            'pipelines'            => $pipelines,
            'currentPipelineId'    => $currentPipeline->id,
            'paymentTypeOptions'   => PaymentType::options(),
            'paymentMethodOptions' => PaymentMethod::options(),
            'currencyOptions'      => Currency::options(),
            'defaultCurrencyCode'  => Currency::default()->value,
            'today'                => now()->format('Y-m-d'),
        ]);
    }

    /**
     * Sla meerdere betalingen in één keer op (mass action vanuit betalingsoverzicht).
     */
    public function savePaymentOverview(OrderPaymentRequest $request): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            foreach ($data['rows'] as $row) {
                $data = [
                    'amount'   => $row['amount'],
                    'type'     => $row['type'],
                    'method'   => $row['method'],
                    'paid_at'  => $row['paid_at'] ?? null,
                    'currency' => $row['currency'] ?? 'EUR',
                ];

                if (! empty($row['payment_id'])) {
                    OrderPayment::where('id', $row['payment_id'])->where('order_id', $row['order_id'])->update($data);
                } else {
                    OrderPayment::create(array_merge($data, ['order_id' => $row['order_id']]));
                }
            }
        });

        $count = count($data['rows']);

        return response()->json([
            'message' => "Betalingen opgeslagen ({$count} ".($count === 1 ? 'betaling' : 'betalingen').').',
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $result = $this->performAdvancedSearch(
            repository: $this->orderRepository,
            getFieldsSearchable: fn () => $this->orderRepository->getFieldsSearchable(),
            eagerLoadRelations: ['stage', 'user'],
            getResults: function ($repository) {
                $repository->pushCriteria(app(RequestCriteria::class));
                $this->applyPermissionFilter($repository);

                return $repository->all();
            },
            resourceClass: OrderLookupResource::class,
            queryParams: request()->query->all()
        );

        return $result instanceof AnonymousResourceCollection ? $result->response() : $result;
    }

    /**
     * Persons for the order GVL tab: unique persons from order lines (preserves order), otherwise sales lead persons.
     * Includes an anamnesis row when present; otherwise the tab can create one via create-and-attach GVL.
     *
     * @return array<int, array{person: Person, anamnesis: ?Anamnesis, lead_id: int}>
     */
    protected function buildOrderGvlPersonRows(Order $order): array
    {
        if (! $order->salesLead) {
            return [];
        }

        $leadId = $order->salesLead->lead_id;

        $personsOrdered = $order->orderItems
            ->map(fn ($item) => $item->person)
            ->filter()
            ->unique('id')
            ->values();

        if ($personsOrdered->isEmpty()) {
            $personsOrdered = $order->salesLead->persons;
        }

        if ($personsOrdered->isEmpty()) {
            return [];
        }

        $personIds = $personsOrdered->pluck('id')->toArray();
        $anamnesesByPersonId = $this->salesLeadRepository
            ->findAnamnesisBySalesLeadId($leadId, $personIds)
            ->keyBy('person_id');

        return $personsOrdered
            ->mapWithKeys(fn ($person) => [
                $person->id => [
                    'person'    => $person,
                    'anamnesis' => $anamnesesByPersonId->get($person->id),
                    'lead_id'   => $leadId,
                ],
            ])
            ->all();
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
            'organization',
            'orderItems.product.productGroup',
            'orderItems.product.partnerProducts' => function ($q) {
                $q->select('partner_products.id', 'partner_products.product_id', 'partner_products.resource_type_id');
            },
            'orderItems.resourceOrderItems.resource',
            'orderItems.person',
            'salesLead',
            'orderChecks',
            'personConfirmations',
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
            return [$salesLead->id => $salesLead->labelWithLeadSuffix()];
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

            $personsWithAnamnesis = $this->buildOrderGvlPersonRows($entity);

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

        $suggestedOrderOrganization = null;
        if (! $order->organization_id && $order->salesLead) {
            $suggested = $order->salesLead->resolveDefaultOrganizationForOrder();
            if ($suggested) {
                $suggestedOrderOrganization = [
                    'id'   => $suggested->id,
                    'name' => $suggested->name,
                ];
            }
        }

        $oldInput = $request->session()->get('_old_input', []);

        $orderOrgSectionInitialOrg = null;
        if (is_array($oldInput) && ! empty($oldInput['organization_id'])) {
            $orderOrgSectionInitialOrg = [
                'id'   => (int) $oldInput['organization_id'],
                'name' => (string) ($oldInput['organization_name'] ?? ''),
            ];
        } elseif ($order->organization) {
            $orderOrgSectionInitialOrg = [
                'id'   => $order->organization->id,
                'name' => $order->organization->name,
            ];
        }

        if (is_array($oldInput) && array_key_exists('is_business', $oldInput)) {
            $v = $oldInput['is_business'];
            $orderOrgSectionInitialIsBusiness = filter_var($v, FILTER_VALIDATE_BOOLEAN) || (string) $v === '1';
        } else {
            $orderOrgSectionInitialIsBusiness = (bool) $order->is_business;
        }

        $psStages = Stage::where('lead_pipeline_id', PipelineDefaultKeys::PIPELINE_PRIVATESCAN_ORDERS_ID->value)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'sort_order', 'is_won', 'is_lost'])
            ->toArray();

        $herniaStages = Stage::where('lead_pipeline_id', PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'sort_order', 'is_won', 'is_lost'])
            ->toArray();

        return [
            $this->entityName         => $entity,

            'orders'                           => $entity,
            'salesLeads'                       => $salesLeads,
            'persons'                          => $persons,
            'personsWithAnamnesis'             => $personsWithAnamnesis,
            'orderEmailOptions'                => $orderEmailOptions,
            'orderDefaultEmail'                => $orderDefaultEmail,
            'missingPersonsWarning'            => $missingPersonsWarning,
            'orderOrgSectionInitialOrg'        => $orderOrgSectionInitialOrg,
            'orderOrgSectionInitialIsBusiness' => $orderOrgSectionInitialIsBusiness,
            'orderOrgSectionHintOrg'           => $suggestedOrderOrganization,
            'computedExaminationAt'            => $order->earliestScheduledResourceSlotStart(),
            'orderPipelineStages'              => [
                'privatescan' => $psStages,
                'hernia'      => $herniaStages,
            ],
            'orderCurrentDepartmentIsHernia'   => $order->stage?->lead_pipeline_id === PipelineDefaultKeys::PIPELINE_HERNIA_ORDERS_ID->value,
        ];
    }

    protected function validateStore(Request $request): void
    {
        $formRequest = OrderRequest::createFrom($request);
        $formRequest->setContainer(app())->setRedirector(app(Redirector::class));
        $formRequest->validateResolved();

        // Propagate the normalized input (items keys/values, `_items_original_keys`)
        // back to the live request, since store()/update() keep working with it.
        $request->replace($formRequest->all());
    }

    protected function validateUpdate(Request $request, int $id): void
    {
        $this->validateStore($request);

        // Validate stage transition
        if ($request->has('pipeline_stage_id')) {
            /** @var Order $order */
            $order = $this->orderRepository->findOrFail($id);

            // Fill the in-memory model with incoming request values so the transition
            // validator sees the to-be-saved state, not the current DB state.
            // Exclude pipeline_stage_id: the validator uses $order->pipeline_stage_id as
            // the *current* (from) stage; the new (to) stage is passed as a separate argument.
            $fillable = array_diff($order->getFillable(), ['pipeline_stage_id']);
            $payload = $this->transformPayload($request->all(), $id);
            $order->fill(array_intersect_key($payload, array_flip($fillable)));

            OrderStatusTransitionValidator::validateTransition($order, (int) $request->input('pipeline_stage_id'));
        }
    }

    protected function transformPayload(array $payload, ?int $id = null): array
    {
        // Combine split date/time override fields into first_examination_at
        if (array_key_exists('first_examination_date', $payload) || array_key_exists('first_examination_date_override', $payload)) {
            $dateOverride = ! empty($payload['first_examination_date_override']);
            $timeOverride = ! empty($payload['first_examination_time_override']);
            $date = $dateOverride ? ($payload['first_examination_date'] ?? null) : null;
            $time = $timeOverride ? ($payload['first_examination_time'] ?? null) : null;
            unset(
                $payload['first_examination_date'],
                $payload['first_examination_date_override'],
                $payload['first_examination_time_override'],
            );

            // Store time override independently (null = not overridden)
            $payload['first_examination_time'] = $time;

            if ($date) {
                $payload['first_examination_at'] = $date;
            } else {
                $payload['first_examination_at'] = null;
            }
        }

        // Optional user select submits "" or "0"; MySQL FK rejects both — must be NULL when cleared.
        if (array_key_exists('user_id', $payload)) {
            $userId = (int) $payload['user_id'];
            $payload['user_id'] = $userId > 0 ? $userId : null;
        }

        if (array_key_exists('clinic_coordinator_user_id', $payload)) {
            $val = $payload['clinic_coordinator_user_id'];
            $payload['clinic_coordinator_user_id'] = ($val === '' || $val === null) ? null : (int) $val;
        }

        // Clear organization when not a business order
        if (isset($payload['is_business']) && ! filter_var($payload['is_business'], FILTER_VALIDATE_BOOLEAN)) {
            $payload['organization_id'] = null;
        }

        // Compute total from items if provided; otherwise default to 0 for create without items
        if (! empty($payload['items']) && is_array($payload['items'])) {
            $sum = 0;
            foreach ($payload['items'] as $item) {
                $sum += (float) ($item['total_price'] ?? 0);
            }
            $payload['total_price'] = $sum;
        } elseif (empty($payload['total_price']) || $payload['total_price'] === '') {
            $payload['total_price'] = 0;
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

    protected function getSearchConfig(): array
    {
        return [
            'name_fields'                 => ['order_number', 'title'],
            'supports_email_phone_search' => false,
            'supports_user_name_search'   => false,
            'enable_debug_logging'        => false,
            'table_name'                  => 'orders',
        ];
    }

    private function mergeInlineOrganizationFromRequest(Request $request): RedirectResponse|JsonResponse|null
    {
        if (! $request->boolean('is_business') || ! $request->filled('new_org.name')) {
            return null;
        }

        try {
            $newOrg = $request->input('new_org', []);
            $org = $this->createInlineOrganization($newOrg['name'], $newOrg);
            $request->merge(['organization_id' => $org->id]);
        } catch (InvalidArgumentException $e) {
            return $this->respondInlineOrganizationValidationError($request, $e);
        }

        return null;
    }

    private function respondInlineOrganizationValidationError(Request $request, InvalidArgumentException $exception): RedirectResponse|JsonResponse
    {
        if ($request->ajax() || $request->wantsJson()) {
            throw ValidationException::withMessages([
                'new_org' => $exception->getMessage(),
            ]);
        }

        return redirect()->back()->withInput()->withErrors([
            'new_org' => $exception->getMessage(),
        ]);
    }
}
