<?php

namespace App\Http\Controllers\Admin\Planning\Concerns;

use App\Models\Resource;
use App\Models\ResourceOrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

trait ResourceAvailabilityTrait
{
    protected function validateBookingAvailability(
        Resource $resource,
        CarbonImmutable $from,
        CarbonImmutable $to
    ): ?JsonResponse {
        if ($resource->allow_outside_availability) {
            return null;
        }

        $activeShifts = $resource->shifts->filter(function ($shift) use ($from) {
            return $shift->period_start->lte($from)
                && ($shift->period_end === null || $shift->period_end->gte($from->startOfDay()));
        });

        if ($activeShifts->isEmpty()) {
            return null;
        }

        $availabilityWindows = [];
        foreach ($activeShifts as $shift) {
            foreach ($this->getShiftBlocksForDay($shift, $from) as $block) {
                $availabilityWindows[] = $block;
            }
        }

        if (! $this->overlapsWithWindows($from, $to, $availabilityWindows)) {
            return response()->json([
                'message' => 'Het geselecteerde tijdstip valt buiten de beschikbaarheid van deze resource.',
            ], 422);
        }

        return null;
    }

    protected function overlapsWithWindows(CarbonImmutable $start, CarbonImmutable $end, array $windows): bool
    {
        foreach ($windows as $window) {
            if ($start->lt($window['to']) && $end->gt($window['from'])) {
                return true;
            }
        }

        return false;
    }

    protected function mergeAdjacentBlocks(array $blocks): array
    {
        $merged = [];
        $availableBlocks = array_filter($blocks, fn ($b) => $b['type'] === 'available');
        $occupiedBlocks = array_filter($blocks, fn ($b) => $b['type'] === 'occupied');

        usort($availableBlocks, fn ($a, $b) => strcmp($a['from'], $b['from']));

        $current = null;
        foreach ($availableBlocks as $block) {
            if ($current === null) {
                $current = $block;
            } else {
                $currentEnd = CarbonImmutable::parse($current['to']);
                $blockStart = CarbonImmutable::parse($block['from']);

                if ($blockStart->diffInMinutes($currentEnd) <= 1) {
                    $current['to'] = $block['to'];
                } else {
                    $merged[] = $current;
                    $current = $block;
                }
            }
        }
        if ($current !== null) {
            $merged[] = $current;
        }

        $merged = array_merge($merged, $occupiedBlocks);

        return $merged;
    }

    protected function getOccupancy($resources, CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return ResourceOrderItem::query()
            ->with(['orderItem.order.salesLead.lead'])
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
    }

    protected function getShiftBlocksForDay($shift, CarbonImmutable $day): array
    {
        $blocks = [];
        $shiftBlocks = $shift->weekday_time_blocks;

        if (empty($shiftBlocks) || $shift->available === false) {
            return $blocks;
        }

        $shiftPeriod = $shift->period();
        if (! $shiftPeriod->contains($day->toDate())) {
            return $blocks;
        }

        if (is_array($shiftBlocks)) {
            $isWeekdayMap = ! empty($shiftBlocks) && array_keys($shiftBlocks) !== range(0, count($shiftBlocks) - 1);

            if ($isWeekdayMap) {
                foreach ($shiftBlocks as $wk => $entries) {
                    $weekday = (int) $wk;
                    $weekdayNormalized = $weekday === 7 ? 0 : $weekday;

                    if ($weekdayNormalized !== (int) $day->dayOfWeek) {
                        continue;
                    }

                    if (! is_array($entries)) {
                        continue;
                    }

                    foreach ($entries as $tb) {
                        if (! is_array($tb)) {
                            continue;
                        }

                        $fromStr = $tb['from'] ?? '09:00';
                        $toStr = $tb['to'] ?? '17:00';
                        $from = CarbonImmutable::parse($day->format('Y-m-d').' '.$fromStr);
                        $to = CarbonImmutable::parse($day->format('Y-m-d').' '.$toStr);

                        if ($to->gt($from)) {
                            $blocks[] = ['from' => $from, 'to' => $to];
                        }
                    }
                }
            } else {
                foreach ($shiftBlocks as $tb) {
                    if (! is_array($tb)) {
                        continue;
                    }

                    $weekday = (int) ($tb['weekday'] ?? -1);
                    $weekdayNormalized = $weekday === 7 ? 0 : $weekday;

                    if ($weekdayNormalized !== (int) $day->dayOfWeek) {
                        continue;
                    }

                    $fromStr = $tb['from'] ?? '09:00';
                    $toStr = $tb['to'] ?? '17:00';
                    $from = CarbonImmutable::parse($day->format('Y-m-d').' '.$fromStr);
                    $to = CarbonImmutable::parse($day->format('Y-m-d').' '.$toStr);

                    if ($to->gt($from)) {
                        $blocks[] = ['from' => $from, 'to' => $to];
                    }
                }
            }
        }

        return $blocks;
    }
}
