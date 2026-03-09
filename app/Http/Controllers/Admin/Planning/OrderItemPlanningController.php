<?php

namespace App\Http\Controllers\Admin\Planning;

use App\Http\Controllers\Admin\Planning\Concerns\ResourceAvailabilityTrait;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\Shift;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class OrderItemPlanningController extends Controller
{
    use ResourceAvailabilityTrait;

    public function show(Request $request, int $orderItemId): View
    {
        $orderItem = OrderItem::with(['product.partnerProducts', 'order'])->findOrFail($orderItemId);

        $defaultResourceTypeId = $orderItem->product?->resource_type_id;
        $defaultClinicId = $orderItem->order?->clinic_id ?? null;

        return view('admin::planning.order_item', [
            'orderItem'            => $orderItem,
            'defaultResourceTypeId'=> $defaultResourceTypeId,
            'defaultClinicId'      => $defaultClinicId,
        ]);
    }

    public function availability(Request $request, int $orderItemId): JsonResponse
    {
        $orderItem = OrderItem::with(['product', 'order'])->findOrFail($orderItemId);

        $viewType = $request->query('view', 'week'); // 'week' or 'month'

        if ($viewType === 'month') {
            return $this->getMonthAvailability($request, $orderItem);
        }

        return $this->getWeekAvailability($request, $orderItem);
    }

    public function book(Request $request, int $orderItemId): JsonResponse
    {
        try {
            $request->validate([
                'resource_id'      => ['required', 'integer', 'exists:resources,id'],
                'from'             => ['required', 'date'],
                'to'               => ['required', 'date', 'after:from'],
                'replace_existing' => ['sometimes', 'boolean'],
            ]);

            $orderItem = OrderItem::findOrFail($orderItemId);
            $resource = Resource::with('shifts')->findOrFail((int) $request->input('resource_id'));

            $from = CarbonImmutable::parse($request->input('from'));
            $to = CarbonImmutable::parse($request->input('to'));

            if ($error = $this->validateBookingAvailability($resource, $from, $to)) {
                return $error;
            }

            $replace = $request->boolean('replace_existing', true);

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
            Log::error('Error creating ResourceOrderItem', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Fout bij inboeken: '.$e->getMessage(),
            ], 500);
        }
    }

    private function getWeekAvailability(Request $request, OrderItem $orderItem): JsonResponse
    {
        $start = CarbonImmutable::parse($request->query('start', Carbon::now()->startOfWeek()));
        $end = CarbonImmutable::parse($request->query('end', $start->addDays(6)->endOfDay()));

        $resourceTypeId = (int) $request->query('resource_type_id', $orderItem->product?->resource_type_id);
        $clinicId = $request->query('clinic_id');

        $resourcesQuery = Resource::query()->with('clinic', 'resourceType')
            ->where('resource_type_id', $resourceTypeId);
        if ($clinicId !== null && $clinicId !== '') {
            $resourcesQuery->where('clinic_id', (int) $clinicId);
        }
        $resources = $resourcesQuery->get();

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
                    $occupancy->get($rid, collect())
                );

                $renderedBlocks[$rid][$dayKey] = $dayBlocks;
            }
        }

        // Existing bookings for this order item (summary)
        $existingForOrderItem = ResourceOrderItem::with('resource')
            ->where('orderitem_id', $orderItem->id)
            ->orderBy('from')
            ->get()
            ->map(fn ($b) => [
                'id'            => $b->id,
                'resource_id'   => $b->resource_id,
                'resource_name' => $b->resource?->name,
                'from'          => CarbonImmutable::parse($b->from)->toIso8601String(),
                'to'            => CarbonImmutable::parse($b->to)->toIso8601String(),
            ]);

        return response()->json([
            'view_type' => 'week',
            'resources' => $resources->map(fn ($r) => [
                'id'                         => $r->id,
                'name'                       => $r->name,
                'clinic_id'                  => $r->clinic_id,
                'clinic'                     => $r->clinic?->name,
                'resource_type'              => $r->resourceType?->name,
                'allow_outside_availability' => (bool) $r->allow_outside_availability,
            ])->values(),
            'blocks'                           => $renderedBlocks,
            'window'                           => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'existing_bookings_for_order_item' => $existingForOrderItem,
        ]);
    }

    private function getMonthAvailability(Request $request, OrderItem $orderItem): JsonResponse
    {
        $start = CarbonImmutable::parse($request->query('start', Carbon::now()->startOfMonth()));
        $end = CarbonImmutable::parse($request->query('end', $start->endOfMonth()));

        $resourceTypeId = (int) $request->query('resource_type_id', $orderItem->product?->resource_type_id);
        $clinicId = $request->query('clinic_id');

        $resourcesQuery = Resource::query()->with('clinic', 'resourceType')
            ->where('resource_type_id', $resourceTypeId);
        if ($clinicId !== null && $clinicId !== '') {
            $resourcesQuery->where('clinic_id', (int) $clinicId);
        }
        $resources = $resourcesQuery->get();

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
                    true // merge adjacent blocks for month view
                );

                $renderedBlocks[$rid][$dayKey] = $dayBlocks;
                $currentDay = $currentDay->addDay();
            }
        }

        // Existing bookings for this order item (summary)
        $existingForOrderItem = ResourceOrderItem::with('resource')
            ->where('orderitem_id', $orderItem->id)
            ->orderBy('from')
            ->get()
            ->map(fn ($b) => [
                'id'            => $b->id,
                'resource_id'   => $b->resource_id,
                'resource_name' => $b->resource?->name,
                'from'          => CarbonImmutable::parse($b->from)->toIso8601String(),
                'to'            => CarbonImmutable::parse($b->to)->toIso8601String(),
            ]);

        return response()->json([
            'view_type' => 'month',
            'resources' => $resources->map(fn ($r) => [
                'id'                         => $r->id,
                'name'                       => $r->name,
                'clinic_id'                  => $r->clinic_id,
                'clinic'                     => $r->clinic?->name,
                'resource_type'              => $r->resourceType?->name,
                'allow_outside_availability' => (bool) $r->allow_outside_availability,
            ])->values(),
            'blocks'                           => $renderedBlocks,
            'window'                           => ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()],
            'existing_bookings_for_order_item' => $existingForOrderItem,
        ]);
    }

    private function getShifts($resources, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return Shift::query()
            ->whereIn('resource_id', $resources->pluck('id'))
            ->where(function ($q) use ($start, $end) {
                $q->whereDate('period_start', '<=', $end->toDateString())
                    ->whereDate('period_end', '>=', $start->toDateString());
            })
            ->get()
            ->groupBy('resource_id');
    }

    private function renderDayBlocks($resource, CarbonImmutable $day, $resourceShifts, $resourceOccupancy, bool $mergeAdjacent = false): array
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

        // Generate occupancy blocks
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
                $productName = $occupancy->orderItem?->product?->name;
                $leadName .= ' - '.$productName;

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

        // Subtract occupancy from availability
        $blocks = $this->subtractOccupancyFromAvailability($blocks);

        // Merge adjacent blocks if requested (for month view)
        if ($mergeAdjacent) {
            $blocks = $this->mergeAdjacentBlocks($blocks);
        }

        // Sort blocks by start time
        usort($blocks, fn ($a, $b) => strcmp($a['from'], $b['from']));

        return $blocks;
    }

    private function subtractOccupancyFromAvailability(array $blocks): array
    {
        $availableBlocks = array_filter($blocks, fn ($b) => $b['type'] === 'available');
        $occupiedBlocks = array_filter($blocks, fn ($b) => $b['type'] === 'occupied');

        $result = $occupiedBlocks; // Keep all occupied blocks

        foreach ($availableBlocks as $availBlock) {
            $availStart = CarbonImmutable::parse($availBlock['from']);
            $availEnd = CarbonImmutable::parse($availBlock['to']);
            $segments = [[$availStart, $availEnd]];

            foreach ($occupiedBlocks as $occBlock) {
                $occStart = CarbonImmutable::parse($occBlock['from']);
                $occEnd = CarbonImmutable::parse($occBlock['to']);
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
}
