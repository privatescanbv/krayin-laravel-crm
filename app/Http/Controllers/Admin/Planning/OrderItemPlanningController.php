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

        // Shifts (availability) in the week window
        $shifts = Shift::query()
            ->whereIn('resource_id', $resources->pluck('id'))
            ->where(function ($q) use ($start, $end) {
                $q->whereDate('period_start', '<=', $end->toDateString())
                  ->whereDate('period_end', '>=', $start->toDateString());
            })
            ->get()
            ->groupBy('resource_id');

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

        return response()->json([
            'resources'  => $resources->map(fn($r) => [
                'id'             => $r->id,
                'name'           => $r->name,
                'clinic'         => $r->clinic?->name,
                'resource_type'  => $r->resourceType?->name,
            ])->values(),
            'shifts'     => $shifts,
            'occupancy'  => $occupancy,
            'window'     => [ 'start' => $start->toIso8601String(), 'end' => $end->toIso8601String() ],
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

