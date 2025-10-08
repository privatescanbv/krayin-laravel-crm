<?php

namespace App\Http\Controllers\Admin\Planning;

use App\Http\Controllers\Controller;
use App\Models\OrderRegel;
use App\Models\Resource;
use App\Models\ResourceOrderItem;
use App\Models\Shift;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderItemPlanningController extends Controller
{
    public function show(Request $request, int $orderItemId): View
    {
        $orderItem = OrderRegel::with(['product.partnerProducts', 'order'])->findOrFail($orderItemId);

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
        $orderItem = OrderRegel::with(['product', 'order'])->findOrFail($orderItemId);

        $start = CarbonImmutable::parse($request->query('start', Carbon::now()->startOfWeek()));
        $end   = CarbonImmutable::parse($request->query('end', $start->addDays(6)->endOfDay()));

        $resourceTypeId = (int) $request->query('resource_type_id', $orderItem->product?->resource_type_id);
        $clinicId       = $request->query('clinic_id');

        $resourcesQuery = Resource::query()->with('clinic', 'resourceType')
            ->where('resource_type_id', $resourceTypeId);
        if ($clinicId !== null && $clinicId !== '') {
            $resourcesQuery->where('clinic_id', (int) $clinicId);
        }
        $resources = $resourcesQuery->get();

        // Occupancy (bookings) in the window
        $occupancy = ResourceOrderItem::query()
            ->whereIn('resource_id', $resources->pluck('id'))
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('from', [$start, $end])
                  ->orWhereBetween('to', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('from', '<=', $start)->where('to', '>=', $end);
                  });
            })
            ->get()
            ->groupBy('resource_id');
        
        // Shifts in the window
        $shifts = Shift::query()
            ->whereIn('resource_id', $resources->pluck('id'))
            ->where(function ($q) use ($start, $end) {
                $q->whereDate('period_start', '<=', $end->toDateString())
                  ->whereDate('period_end', '>=', $start->toDateString());
            })
            ->get()
            ->groupBy('resource_id');

        // Build availability blocks per resource by expanding weekday_time_blocks across the requested week
        $availabilityByResource = [];
        foreach ($resources as $resource) {
            $rid = $resource->id;
            $availabilityByResource[$rid] = [];
            $resourceShifts = $shifts->get($rid) ?? collect();
            
            for ($i = 0; $i < 7; $i++) {
                $day = $start->addDays($i);
                
                foreach ($resourceShifts as $shift) {
                    $blocks = $shift->weekday_time_blocks;
                    
                    if (empty($blocks) || $shift->available === false) {
                        continue;
                    }
                    if (is_array($blocks)) {
                        // Check if this is a weekday map (keys are numeric weekdays: '1', '2', etc.)
                        $isWeekdayMap = !empty($blocks) && array_keys($blocks) !== range(0, count($blocks) - 1);
                        
                        if ($isWeekdayMap) {
                            // Direct weekday map: { '1': [{from,to}], '2': [...] }
                            foreach ($blocks as $wk => $entries) {
                                $weekday = (int) $wk; // 1=Mon, 2=Tue, etc.
                                $weekdayNormalized = $weekday === 7 ? 0 : $weekday; // Convert Sun=7 to Sun=0
                                
                                if ($weekdayNormalized !== (int) $day->dayOfWeek) {
                                    continue;
                                }
                                if (! is_array($entries)) {
                                    continue;
                                }
                                foreach ($entries as $tb) {
                                    if (! is_array($tb)) { continue; }
                                    $fromStr = $tb['from'] ?? '09:00';
                                    $toStr = $tb['to'] ?? '17:00';
                                    $from = CarbonImmutable::parse($day->format('Y-m-d') . ' ' . $fromStr);
                                    $to   = CarbonImmutable::parse($day->format('Y-m-d') . ' ' . $toStr);
                                    if ($to->lessThanOrEqualTo($from)) {
                                        continue;
                                    }
                                    $availabilityByResource[$rid][] = [
                                        'from' => $from->toIso8601String(),
                                        'to'   => $to->toIso8601String(),
                                    ];
                                }
                            }
                        } else {
                            // Flat blocks array with 'weekday' field
                            foreach ($blocks as $tb) {
                                if (! is_array($tb)) { continue; }
                                $weekday = (int) ($tb['weekday'] ?? -1);
                                $weekdayNormalized = $weekday === 7 ? 0 : $weekday;
                                if ($weekdayNormalized !== (int) $day->dayOfWeek) {
                                    continue;
                                }
                                $fromStr = $tb['from'] ?? '09:00';
                                $toStr = $tb['to'] ?? '17:00';
                                $from = CarbonImmutable::parse($day->format('Y-m-d') . ' ' . $fromStr);
                                $to   = CarbonImmutable::parse($day->format('Y-m-d') . ' ' . $toStr);
                                if ($to->lessThanOrEqualTo($from)) {
                                    continue;
                                }
                                $availabilityByResource[$rid][] = [
                                    'from' => $from->toIso8601String(),
                                    'to'   => $to->toIso8601String(),
                                ];
                            }
                        }
                    }
                }
            }
        }

        // Helper to subtract occupancy intervals from availability intervals
        $subtractIntervals = function (array $avail, array $occ) {
            $result = [];
            foreach ($avail as $interval) {
                $segments = [ [ 'from' => CarbonImmutable::parse($interval['from']), 'to' => CarbonImmutable::parse($interval['to']) ] ];
                
                foreach ($occ as $o) {
                    $of = CarbonImmutable::parse($o['from']);
                    $ot = CarbonImmutable::parse($o['to']);
                    $next = [];
                    
                    foreach ($segments as $seg) {
                        $segStart = $seg['from'];
                        $segEnd = $seg['to'];
                        
                        // No overlap - keep segment as is
                        if ($ot->lessThanOrEqualTo($segStart) || $of->greaterThanOrEqualTo($segEnd)) {
                            $next[] = $seg;
                            continue;
                        }
                        
                        // Complete overlap - remove segment entirely
                        if ($of->lessThanOrEqualTo($segStart) && $ot->greaterThanOrEqualTo($segEnd)) {
                            continue;
                        }
                        
                        // Partial overlap - split segment
                        // Left part (before occupancy) - only if there's space before
                        if ($of->greaterThan($segStart)) {
                            $next[] = [ 'from' => $segStart, 'to' => $of ];
                        }
                        
                        // Right part (after occupancy) - only if there's space after
                        if ($ot->lessThan($segEnd)) {
                            $next[] = [ 'from' => $ot, 'to' => $segEnd ];
                        }
                    }
                    $segments = $next;
                }
                
                // Add remaining segments to result
                foreach ($segments as $s) {
                    if ($s['to']->greaterThan($s['from'])) {
                        $result[] = [ 'from' => $s['from']->toIso8601String(), 'to' => $s['to']->toIso8601String() ];
                    }
                }
            }
            return $result;
        };

        // Prepare flat occupied list per resource (ISO strings)
        $occupiedByResource = [];
        foreach ($resources as $resource) {
            $rid = $resource->id;
            $occupiedByResource[$rid] = [];
            foreach ($occupancy->get($rid, collect()) as $o) {
                $occupiedByResource[$rid][] = [
                    'from' => CarbonImmutable::parse($o->from)->toIso8601String(),
                    'to'   => CarbonImmutable::parse($o->to)->toIso8601String(),
                ];
            }
        }
        

        // Compute final availability (availability - occupied)
        $finalAvailability = [];
        foreach ($resources as $resource) {
            $rid = $resource->id;
            $originalAvailability = $availabilityByResource[$rid] ?? [];
            $occupancyForResource = $occupiedByResource[$rid] ?? [];
            
            // Debug logging
            if (!empty($originalAvailability) && !empty($occupancyForResource)) {
                \Log::info('Subtracting occupancy from availability', [
                    'resource_id' => $rid,
                    'original_availability' => $originalAvailability,
                    'occupancy' => $occupancyForResource
                ]);
            }
            
            $finalAvailability[$rid] = $subtractIntervals($originalAvailability, $occupancyForResource);
            
            // Debug logging for result
            if (!empty($originalAvailability) && !empty($occupancyForResource)) {
                \Log::info('Subtraction result', [
                    'resource_id' => $rid,
                    'final_availability' => $finalAvailability[$rid]
                ]);
            }
        }

        return response()->json([
            'resources'   => $resources->map(fn($r) => [
                'id'             => $r->id,
                'name'           => $r->name,
                'clinic'         => $r->clinic?->name,
                'resource_type'  => $r->resourceType?->name,
            ])->values(),
            'availability'=> $finalAvailability,
            'occupancy'   => $occupiedByResource,
            'window'      => [ 'start' => $start->toIso8601String(), 'end' => $end->toIso8601String() ],
        ]);
    }

    public function book(Request $request, int $orderItemId): JsonResponse
    {
        $request->validate([
            'resource_id' => ['required', 'integer', 'exists:resources,id'],
            'from'        => ['required', 'date'],
            'to'          => ['required', 'date', 'after:from'],
        ]);

        $orderItem = OrderRegel::findOrFail($orderItemId);

        $booking = ResourceOrderItem::create([
            'resource_id'  => (int) $request->input('resource_id'),
            'orderitem_id' => $orderItem->id,
            'from'         => Carbon::parse($request->input('from')),
            'to'           => Carbon::parse($request->input('to')),
        ]);

        return response()->json([
            'message' => 'Ingeboekt',
            'data'    => $booking,
        ], 201);
    }
}

