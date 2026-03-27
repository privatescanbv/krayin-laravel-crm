<?php

namespace App\Http\Controllers\Admin\Planning;

use App\Http\Controllers\Admin\Planning\Concerns\ResourceAvailabilityTrait;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\ResourceType;
use App\Models\Shift;
use App\Repositories\ClinicRepository;
use App\Repositories\ResourceRepository;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ResourcePlanningMonitorController extends Controller
{
    use ResourceAvailabilityTrait;

    public function index(Request $request): View
    {
        $resourceTypes = ResourceType::all(['id', 'name']);
        $resources = app(ResourceRepository::class)
            ->allWithActiveClinics(['clinic', 'shifts'], ['id', 'name', 'clinic_id', 'resource_type_id']);
        $clinics = app(ClinicRepository::class)->allActive(['id', 'name']);

        return view('adminc.planning.monitor', [
            'resourceTypes' => $resourceTypes,
            'resources'     => $resources,
            'clinics'       => $clinics,
        ]);
    }

    public function availability(Request $request): JsonResponse
    {
        $viewType = $request->query('view', 'week'); // 'week' or 'month'

        if ($viewType === 'month') {
            return $this->getMonthAvailability($request);
        }

        return $this->getWeekAvailability($request);
    }

    public function orderPlanning(Request $request, int $orderId): View
    {
        $order = Order::with([
            'orderItems.product.partnerProducts',
            'orderItems.person',
            'orderItems.resourceType',
            'orderItems.product.productType',
            'salesLead.lead',
        ])->findOrFail($orderId);

        $resourceTypes = ResourceType::all(['id', 'name']);
        $resources = app(ResourceRepository::class)
            ->allWithActiveClinics(['clinic'], ['id', 'name', 'clinic_id', 'resource_type_id']);
        $clinics = app(ClinicRepository::class)->allActive(['id', 'name']);

        return view('adminc.planning.order_monitor', [
            'order'         => $order,
            'resourceTypes' => $resourceTypes,
            'resources'     => $resources,
            'clinics'       => $clinics,
        ]);
    }

    public function orderResourceTypes(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with([
            'orderItems.product.resourceType',
            'orderItems.resourceType',
            'orderItems.product.productType',
        ])->findOrFail($orderId);

        $resourceTypeNames = $order->orderItems
            ->map(fn (OrderItem $item) => $item->resolvedResourceTypeName())
            ->filter()
            ->unique()
            ->values();

        $resourceTypes = ResourceType::query()
            ->whereIn('name', $resourceTypeNames->all())
            ->get(['id', 'name'])
            ->sortBy(fn ($rt) => array_search($rt->name, $resourceTypeNames->all(), true))
            ->values()
            ->map(fn ($type) => [
                'id'   => $type->id,
                'name' => $type->name,
            ])
            ->values();

        return response()->json([
            'resource_types' => $resourceTypes,
        ]);
    }

    public function orderAvailability(Request $request, int $orderId): JsonResponse
    {
        $order = Order::with(['orderItems.product'])->findOrFail($orderId);
        $viewType = $request->query('view', 'week');

        if ($viewType === 'month') {
            return $this->getOrderMonthAvailability($request, $order);
        }

        return $this->getOrderWeekAvailability($request, $order);
    }

    public function bookOrderItem(Request $request, int $orderItemId): JsonResponse
    {
        try {
            $request->validate([
                'resource_id'      => ['required', 'integer', 'exists:resources,id'],
                'from'             => ['required', 'date'],
                'to'               => ['required', 'date', 'after:from'],
                'replace_existing' => ['sometimes', 'boolean'],
            ]);
            $replace = $request->boolean('replace_existing', true);

            // Load orderItem with product + resourceType
            $orderItem = OrderItem::with([
                'product.resourceType',
                'productType',
                'product.productType',
            ])->findOrFail($orderItemId);

            // Load resource with resourceType and shifts
            $resource = Resource::with('resourceType', 'shifts')->findOrFail((int) $request->input('resource_id'));

            // -------------------------------
            // VALIDATION: ResourceType match
            // -------------------------------
            $requiredType = $orderItem->resolvedResourceTypeName();
            $resourceTypeName = $resource->resourceType?->name;
            if ($requiredType !== $resourceTypeName) {
                $requiredLabel = $requiredType ?? 'Onbekend';
                $chosenLabel = $resourceTypeName ?? 'Onbekend';

                return response()->json([
                    'message' => sprintf(
                        'Dit orderregel vereist \'%s\', maar de gekozen resource is van het type \'%s\'. Kies een passende resource of een ander orderregel.',
                        $requiredLabel,
                        $chosenLabel
                    ),
                    'required_type' => $requiredType,
                    'resource_type' => $resourceTypeName,
                ], 422);
            }

            // -------------------------------
            // VALIDATION: Availability check
            // -------------------------------
            $from = CarbonImmutable::parse($request->input('from'));
            $to = CarbonImmutable::parse($request->input('to'));

            if ($error = $this->validateBookingAvailability($resource, $from, $to)) {
                return $error;
            }

            $booking = DB::transaction(function () use ($request, $orderItem, $replace) {
                if ($replace) {
                    ResourceOrderItem::where('orderitem_id', $orderItem->id)->delete();
                }

                return ResourceOrderItem::create([
                    'resource_id'  => (int) $request->input('resource_id'),
                    'orderitem_id' => $orderItem->id,
                    'from'         => Carbon::parse($request->input('from')),
                    'to'           => Carbon::parse($request->input('to')),
                    'created_by'   => auth()->id(),
                    'updated_by'   => auth()->id(),
                ]);
            });

            return response()->json([
                'message' => $replace ? 'Ingeboekt (vorige afspraak vervangen)' : 'Ingeboekt',
                'data'    => $booking,
            ], 201);
        } catch (Exception $e) {
            Log::error('Error creating ResourceOrderItem from monitor', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Fout bij inboeken: '.$e->getMessage(),
            ], 500);
        }
    }

    private function getWeekAvailability(Request $request): JsonResponse
    {
        $start = CarbonImmutable::parse($request->query('start', Carbon::now()->startOfWeek()));
        $end = CarbonImmutable::parse($request->query('end', $start->addDays(6)->endOfDay()));

        $resources = $this->getFilteredResources($request);
        $showAvailableOnly = $request->query('show_available_only') === '1';

        // Get occupancy and shifts
        $occupancy = $this->getOccupancy($resources, $start, $end);
        $shifts = $this->getShifts($resources, $start, $end);

        // Build rendered blocks per resource per day
        $renderedBlocks = [];
        foreach ($resources as $resource) {
            $rid = $resource->id;
            $renderedBlocks[$rid] = [];

            // Generate blocks for each day of the week
            for ($i = 0; $i < 7; $i++) {
                $day = $start->addDays($i);
                $dayKey = $day->format('Y-m-d');

                $dayBlocks = $this->renderDayBlocks(
                    $resource,
                    $day,
                    $shifts->get($rid, collect()),
                    $occupancy->get($rid, collect()),
                    false,
                    $showAvailableOnly
                );

                $renderedBlocks[$rid][$dayKey] = $dayBlocks;
            }
        }

        return response()->json([
            'view_type' => 'week',
            'resources' => $resources->map(fn ($r) => [
                'id'                         => $r->id,
                'name'                       => $r->name,
                'notes'                      => $r->notes,
                'clinic_id'                  => $r->clinic_id,
                'clinic'                     => $r->clinic?->name,
                'resource_type'              => $r->resourceType?->name,
                'resource_type_id'           => $r->resource_type_id,
                'has_infinite_duration'      => $r->hasInfiniteDuration(),
                'shifts_count'               => $r->shifts->count(),
                'allow_outside_availability' => (bool) $r->allow_outside_availability,
            ])->values(),
            'blocks'   => $renderedBlocks,
            'window'   => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
        ]);
    }

    private function getMonthAvailability(Request $request): JsonResponse
    {
        $start = CarbonImmutable::parse($request->query('start', Carbon::now()->startOfMonth()));
        $end = CarbonImmutable::parse($request->query('end', $start->endOfMonth()));

        $resources = $this->getFilteredResources($request);
        $showAvailableOnly = $request->query('show_available_only') === '1';

        // Get occupancy and shifts
        $occupancy = $this->getOccupancy($resources, $start, $end);
        $shifts = $this->getShifts($resources, $start, $end);

        // Build merged blocks per resource per day for month view
        $renderedBlocks = [];
        foreach ($resources as $resource) {
            $rid = $resource->id;
            $renderedBlocks[$rid] = [];

            // Generate blocks for each day of the month
            $currentDay = $start->copy();
            while ($currentDay->lte($end)) {
                $dayKey = $currentDay->format('Y-m-d');

                $dayBlocks = $this->renderDayBlocks(
                    $resource,
                    $currentDay,
                    $shifts->get($rid, collect()),
                    $occupancy->get($rid, collect()),
                    true, // merge adjacent blocks for month view
                    $showAvailableOnly
                );

                $renderedBlocks[$rid][$dayKey] = $dayBlocks;
                $currentDay = $currentDay->addDay();
            }
        }

        return response()->json([
            'view_type' => 'month',
            'resources' => $resources->map(fn ($r) => [
                'id'                         => $r->id,
                'name'                       => $r->name,
                'notes'                      => $r->notes,
                'clinic_id'                  => $r->clinic_id,
                'clinic'                     => $r->clinic?->name,
                'resource_type'              => $r->resourceType?->name,
                'resource_type_id'           => $r->resource_type_id,
                'has_infinite_duration'      => $r->hasInfiniteDuration(),
                'shifts_count'               => $r->shifts->count(),
                'allow_outside_availability' => (bool) $r->allow_outside_availability,
            ])->values(),
            'blocks'   => $renderedBlocks,
            'window'   => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
        ]);
    }

    private function getFilteredResources(Request $request)
    {
        $resourcesQuery = app(ResourceRepository::class)
            ->queryWithActiveClinics()
            ->with('clinic', 'resourceType', 'shifts');

        // Filter by resource types (multi-select)
        if ($request->filled('resource_type_ids')) {
            $resourceTypeIds = explode(',', $request->query('resource_type_ids'));
            $resourcesQuery->whereIn('resource_type_id', array_map('intval', $resourceTypeIds));
        }

        // Filter by clinics (multi-select)
        if ($request->filled('clinic_ids')) {
            $clinicIds = explode(',', $request->query('clinic_ids'));
            $resourcesQuery->whereIn('clinic_id', array_map('intval', $clinicIds));
        }

        // Filter by resources (multi-select)
        if ($request->filled('resource_ids')) {
            $resourceIds = explode(',', $request->query('resource_ids'));
            $resourcesQuery->whereIn('id', array_map('intval', $resourceIds));
        }

        /**
         * Include only resources that have at least one shift that overlaps the requested window.
         *
         * Important: the monitor should not surface resources whose availability is entirely in the past.
         * When the requested window includes past days (e.g. "current week" while today is later in the week),
         * we effectively treat the window start as "today" for the purpose of determining whether a shift is expired.
         */
        $start = CarbonImmutable::parse($request->query('start', Carbon::now()->startOfWeek()));
        $end = CarbonImmutable::parse($request->query('end', $start->addDays(6)->endOfDay()));

        $today = CarbonImmutable::today();
        $effectiveStart = $start->lt($today) ? $today : $start;

        $resourcesQuery->whereHas('shifts', function ($q) use ($effectiveStart, $end) {
            $q->where(function ($shiftQuery) use ($effectiveStart, $end) {
                // Finite shifts: overlap the effective window (inclusive)
                $shiftQuery->where(function ($finite) use ($effectiveStart, $end) {
                    $finite->whereNotNull('period_end')
                        ->whereDate('period_start', '<=', $end->toDateString())
                        ->whereDate('period_end', '>=', $effectiveStart->toDateString());
                })
                // Infinite shifts: started on/before window end
                    ->orWhere(function ($infinite) use ($end) {
                        $infinite->whereNull('period_end')
                            ->whereDate('period_start', '<=', $end->toDateString());
                    });
            });
        });

        return $resourcesQuery->get();
    }

    private function getShifts($resources, CarbonImmutable $start, CarbonImmutable $end)
    {
        return Shift::query()
            ->whereIn('resource_id', $resources->pluck('id'))
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($shiftQuery) use ($start, $end) {
                    // For finite duration shifts: check if they overlap with the requested period
                    $shiftQuery->whereNotNull('period_end')
                        ->whereDate('period_start', '<=', $end->toDateString())
                        ->whereDate('period_end', '>=', $start->toDateString());
                })->orWhere(function ($shiftQuery) use ($end) {
                    // For infinite duration shifts: check if they start before or during the requested period
                    $shiftQuery->whereNull('period_end')
                        ->whereDate('period_start', '<=', $end->toDateString());
                });
            })
            ->get()
            ->groupBy('resource_id');
    }

    private function renderDayBlocks($resource, CarbonImmutable $day, $resourceShifts, $resourceOccupancy, bool $mergeAdjacent = false, bool $showAvailableOnly = false): array
    {
        $blocks = [];

        // Collect availability windows for this day
        $availabilityWindows = [];
        foreach ($resourceShifts as $shift) {
            foreach ($this->getShiftBlocksForDay($shift, $day) as $block) {
                $availabilityWindows[] = $block;
            }
        }

        // Generate availability blocks from shifts
        foreach ($availabilityWindows as $block) {
            $blocks[] = [
                'type'          => 'available',
                'from'          => $block['from']->toIso8601String(),
                'to'            => $block['to']->toIso8601String(),
                'resource_id'   => $resource->id,
                'resource_name' => $resource->name,
                'clickable'     => true,
            ];
        }

        // Generate occupancy blocks (only if not showing available only)
        if (! $showAvailableOnly) {
            foreach ($resourceOccupancy as $occupancy) {
                $occStart = CarbonImmutable::parse($occupancy->from);
                $occEnd = CarbonImmutable::parse($occupancy->to);

                // Only include if it overlaps with this day
                if ($occStart->lt($day->endOfDay()) && $occEnd->gt($day->startOfDay())) {
                    $blockStart = $occStart->gt($day->startOfDay()) ? $occStart : $day->startOfDay();
                    $blockEnd = $occEnd->lt($day->endOfDay()) ? $occEnd : $day->endOfDay();

                    $leadName = $occupancy->orderItem?->order?->salesLead?->lead?->name ??
                               $occupancy->orderItem?->order?->salesLead?->name ??
                               'Onbekend';

                    $outsideAvailability = empty($availabilityWindows)
                        ? false
                        : ! $this->overlapsWithWindows($blockStart, $blockEnd, $availabilityWindows);

                    $blocks[] = [
                        'type'                 => 'occupied',
                        'from'                 => $blockStart->toIso8601String(),
                        'to'                   => $blockEnd->toIso8601String(),
                        'resource_id'          => $resource->id,
                        'resource_name'        => $resource->name,
                        'clickable'            => false,
                        'booking_id'           => $occupancy->id,
                        'lead_name'            => $leadName,
                        'outside_availability' => $outsideAvailability,
                    ];
                }
            }
        }

        // Subtract occupancy from availability (always subtract, even if not showing occupied)
        $blocks = $this->subtractOccupancyFromAvailability($blocks, $resourceOccupancy, $day);

        // Merge adjacent blocks if requested (for month view)
        if ($mergeAdjacent) {
            $blocks = $this->mergeAdjacentBlocks($blocks);
        }

        // Sort blocks by start time
        usort($blocks, fn ($a, $b) => strcmp($a['from'], $b['from']));

        return $blocks;
    }

    private function subtractOccupancyFromAvailability(array $blocks, $resourceOccupancy = null, $day = null): array
    {
        $availableBlocks = array_filter($blocks, fn ($b) => $b['type'] === 'available');
        $occupiedBlocks = array_filter($blocks, fn ($b) => $b['type'] === 'occupied');

        $result = $occupiedBlocks; // Keep all occupied blocks

        // Build occupancy list for subtraction
        $occupancyList = [];
        if ($resourceOccupancy !== null && $day !== null) {
            foreach ($resourceOccupancy as $occupancy) {
                $occStart = CarbonImmutable::parse($occupancy->from);
                $occEnd = CarbonImmutable::parse($occupancy->to);

                if ($occStart->lt($day->endOfDay()) && $occEnd->gt($day->startOfDay())) {
                    $blockStart = $occStart->gt($day->startOfDay()) ? $occStart : $day->startOfDay();
                    $blockEnd = $occEnd->lt($day->endOfDay()) ? $occEnd : $day->endOfDay();

                    $occupancyList[] = [
                        'from' => $blockStart,
                        'to'   => $blockEnd,
                    ];
                }
            }
        } else {
            // Fallback to occupied blocks
            foreach ($occupiedBlocks as $occBlock) {
                $occupancyList[] = [
                    'from' => CarbonImmutable::parse($occBlock['from']),
                    'to'   => CarbonImmutable::parse($occBlock['to']),
                ];
            }
        }

        foreach ($availableBlocks as $availBlock) {
            $availStart = CarbonImmutable::parse($availBlock['from']);
            $availEnd = CarbonImmutable::parse($availBlock['to']);
            $segments = [[$availStart, $availEnd]];

            foreach ($occupancyList as $occ) {
                $occStart = $occ['from'];
                $occEnd = $occ['to'];
                $newSegments = [];

                foreach ($segments as $segment) {
                    $segStart = $segment[0];
                    $segEnd = $segment[1];

                    // No overlap - keep segment
                    if ($occEnd->lte($segStart) || $occStart->gte($segEnd)) {
                        $newSegments[] = $segment;

                        continue;
                    }

                    // Complete overlap - remove segment
                    if ($occStart->lte($segStart) && $occEnd->gte($segEnd)) {
                        continue;
                    }

                    // Partial overlap - split segment
                    if ($occStart->gt($segStart)) {
                        $newSegments[] = [$segStart, $occStart];
                    }
                    if ($occEnd->lt($segEnd)) {
                        $newSegments[] = [$occEnd, $segEnd];
                    }
                }
                $segments = $newSegments;
            }

            // Add remaining segments as available blocks
            foreach ($segments as $segment) {
                if ($segment[1]->gt($segment[0])) {
                    $result[] = [
                        'type'          => 'available',
                        'from'          => $segment[0]->toIso8601String(),
                        'to'            => $segment[1]->toIso8601String(),
                        'resource_id'   => $availBlock['resource_id'],
                        'resource_name' => $availBlock['resource_name'],
                        'clickable'     => true,
                    ];
                }
            }
        }

        return $result;
    }

    private function getOrderWeekAvailability(Request $request, Order $order): JsonResponse
    {
        $start = CarbonImmutable::parse($request->query('start', Carbon::now()->startOfWeek()));
        $end = CarbonImmutable::parse($request->query('end', $start->addDays(6)->endOfDay()));

        $resources = $this->getFilteredResources($request);
        $showAvailableOnly = $request->query('show_available_only') === '1';

        // Get occupancy and shifts
        $occupancy = $this->getOccupancy($resources, $start, $end);
        $shifts = $this->getShifts($resources, $start, $end);

        // Build rendered blocks per resource per day
        $renderedBlocks = [];
        foreach ($resources as $resource) {
            $rid = $resource->id;
            $renderedBlocks[$rid] = [];

            // Generate blocks for each day of the week
            for ($i = 0; $i < 7; $i++) {
                $day = $start->addDays($i);
                $dayKey = $day->format('Y-m-d');

                $dayBlocks = $this->renderDayBlocks(
                    $resource,
                    $day,
                    $shifts->get($rid, collect()),
                    $occupancy->get($rid, collect()),
                    false,
                    $showAvailableOnly
                );

                $renderedBlocks[$rid][$dayKey] = $dayBlocks;
            }
        }

        // Get order items with their existing bookings
        $orderItems = $order->orderItems()->with([
            'product',
            'productType',
            'product.productType',
            'resourceOrderItems.resource',
        ])->get();

        return response()->json([
            'view_type' => 'week',
            'resources' => $resources->map(fn ($r) => [
                'id'                         => $r->id,
                'name'                       => $r->name,
                'notes'                      => $r->notes,
                'clinic_id'                  => $r->clinic_id,
                'clinic'                     => $r->clinic?->name,
                'resource_type'              => $r->resourceType?->name,
                'resource_type_id'           => $r->resource_type_id,
                'has_infinite_duration'      => $r->hasInfiniteDuration(),
                'shifts_count'               => $r->shifts->count(),
                'allow_outside_availability' => (bool) $r->allow_outside_availability,
            ])->values(),
            'blocks'     => $renderedBlocks,
            'window'     => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'order'      => [
                'id'    => $order->id,
                'title' => $order->title,
            ],
            'order_items' => $orderItems->map(fn ($item) => [
                'id'                     => $item->id,
                'product_name'           => $item->product?->name ?? 'Onbekend product',
                'quantity'               => $item->quantity,
                'status'                 => $item->status,
                'required_resource_type' => $item->resolvedResourceTypeName(),
                // Only plan if product has partner products linked to active clinics
                'can_plan'               => $item->isPlannable(),
                'bookings'               => $item->resourceOrderItems->map(fn ($booking) => [
                    'id'            => $booking->id,
                    'resource_id'   => $booking->resource_id,
                    'resource_name' => $booking->resource?->name ?? 'Onbekend',
                    'from'          => CarbonImmutable::parse($booking->from)->toIso8601String(),
                    'to'            => CarbonImmutable::parse($booking->to)->toIso8601String(),
                ]),
            ]),
        ]);
    }

    private function getOrderMonthAvailability(Request $request, Order $order): JsonResponse
    {
        $start = CarbonImmutable::parse($request->query('start', Carbon::now()->startOfMonth()));
        $end = CarbonImmutable::parse($request->query('end', $start->endOfMonth()));

        $resources = $this->getFilteredResources($request);
        $showAvailableOnly = $request->query('show_available_only') === '1';

        // Get occupancy and shifts
        $occupancy = $this->getOccupancy($resources, $start, $end);
        $shifts = $this->getShifts($resources, $start, $end);

        // Build merged blocks per resource per day for month view
        $renderedBlocks = [];
        foreach ($resources as $resource) {
            $rid = $resource->id;
            $renderedBlocks[$rid] = [];

            // Generate blocks for each day of the month
            $currentDay = $start->copy();
            while ($currentDay->lte($end)) {
                $dayKey = $currentDay->format('Y-m-d');

                $dayBlocks = $this->renderDayBlocks(
                    $resource,
                    $currentDay,
                    $shifts->get($rid, collect()),
                    $occupancy->get($rid, collect()),
                    true, // merge adjacent blocks for month view
                    $showAvailableOnly
                );

                $renderedBlocks[$rid][$dayKey] = $dayBlocks;
                $currentDay = $currentDay->addDay();
            }
        }

        // Get order items with their existing bookings
        $orderItems = $order->orderItems()->with([
            'product',
            'productType',
            'product.productType',
            'resourceOrderItems.resource',
        ])->get();

        return response()->json([
            'view_type' => 'month',
            'resources' => $resources->map(fn ($r) => [
                'id'                         => $r->id,
                'name'                       => $r->name,
                'notes'                      => $r->notes,
                'clinic_id'                  => $r->clinic_id,
                'clinic'                     => $r->clinic?->name,
                'resource_type'              => $r->resourceType?->name,
                'resource_type_id'           => $r->resource_type_id,
                'has_infinite_duration'      => $r->hasInfiniteDuration(),
                'shifts_count'               => $r->shifts->count(),
                'allow_outside_availability' => (bool) $r->allow_outside_availability,
            ])->values(),
            'blocks'     => $renderedBlocks,
            'window'     => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'order'      => [
                'id'    => $order->id,
                'title' => $order->title,
            ],
            'order_items' => $orderItems->map(fn ($item) => [
                'id'                     => $item->id,
                'product_name'           => $item->product?->name ?? 'Onbekend product',
                'quantity'               => $item->quantity,
                'status'                 => $item->status,
                'required_resource_type' => $item->resolvedResourceTypeName(),
                'can_plan'               => $item->isPlannable(),
                'bookings'               => $item->resourceOrderItems->map(fn ($booking) => [
                    'id'            => $booking->id,
                    'resource_id'   => $booking->resource_id,
                    'resource_name' => $booking->resource?->name ?? 'Onbekend',
                    'from'          => CarbonImmutable::parse($booking->from)->toIso8601String(),
                    'to'            => CarbonImmutable::parse($booking->to)->toIso8601String(),
                ]),
            ]),
        ]);
    }
}
