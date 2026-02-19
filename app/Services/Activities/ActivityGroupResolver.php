<?php

namespace App\Services\Activities;

use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;
use Exception;
use Illuminate\Support\Facades\Log;
use Webkul\Activity\Models\Activity;
use Webkul\Lead\Models\Lead;
use Webkul\User\Models\Group;

class ActivityGroupResolver
{
    /**
     * Resolve the correct group_id for an activity based on its associated entity.
     *
     * Resolution chain (first match wins):
     *   1. lead_id        → Lead → Department → Group
     *   2. sales_lead_id  → SalesLead → Lead → Department → Group
     *   3. order_id       → Order → SalesLead → Lead → Department → Group
     *   4. fallback       → Privatescan group (with warning log)
     *
     * Returns null only when the fallback itself is unavailable (e.g. bare tests without seeders).
     */
    public static function resolve(Activity $activity): ?int
    {
        try {
            // 1. Direct lead
            if ($activity->lead_id) {
                $lead = Lead::find($activity->lead_id);
                if ($lead) {
                    return Department::getGroupIdForLead($lead);
                }
            }

            // 2. Via sales lead → lead
            if ($activity->sales_lead_id) {
                $salesLead = SalesLead::with('lead')->find($activity->sales_lead_id);
                if ($salesLead?->lead) {
                    return Department::getGroupIdForLead($salesLead->lead);
                }
            }

            // 3. Via order → sales lead → lead
            if ($activity->order_id) {
                $order = Order::with('salesLead.lead')->find($activity->order_id);
                if ($order?->salesLead?->lead) {
                    return Department::getGroupIdForLead($order->salesLead->lead);
                }
            }
        } catch (Exception $e) {
            Log::warning('ActivityGroupResolver: exception during resolution, falling back to Privatescan', [
                'lead_id'       => $activity->lead_id,
                'sales_lead_id' => $activity->sales_lead_id,
                'order_id'      => $activity->order_id,
                'error'         => $e->getMessage(),
            ]);
        }

        // Fallback: Privatescan group
        Log::warning('ActivityGroupResolver: could not resolve group from entity, defaulting to Privatescan', [
            'lead_id'       => $activity->lead_id,
            'sales_lead_id' => $activity->sales_lead_id,
            'order_id'      => $activity->order_id,
        ]);

        try {
            $privateScanDeptId = Department::findPrivateScanId();

            return Group::query()->where('department_id', $privateScanDeptId)->value('id');
        } catch (Exception $e) {
            Log::warning('ActivityGroupResolver: Privatescan fallback also failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
