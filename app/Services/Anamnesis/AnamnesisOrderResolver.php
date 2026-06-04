<?php

namespace App\Services\Anamnesis;

use App\Models\Anamnesis;
use App\Models\Department;
use App\Models\Order;
use App\Models\SalesLead;

class AnamnesisOrderResolver
{
    public function findActiveOrderForAnamnesis(Anamnesis $anamnesis): ?Order
    {
        if ($anamnesis->order_id) {
            return Order::query()->find($anamnesis->order_id);
        }

        if ($anamnesis->sales_id) {
            $order = Order::query()
                ->inOpenStage()
                ->where('sales_lead_id', $anamnesis->sales_id)
                ->latest()
                ->first();

            if ($order) {
                return $order;
            }
        }

        if ($anamnesis->lead_id) {
            $salesLeadIds = SalesLead::where('lead_id', $anamnesis->lead_id)->pluck('id');

            if ($salesLeadIds->isNotEmpty()) {
                return Order::query()
                    ->inOpenStage()
                    ->whereIn('sales_lead_id', $salesLeadIds)
                    ->latest()
                    ->first();
            }
        }

        return null;
    }

    /**
     * Department for GVL form type: order pipeline, then sales lead, then lead.
     */
    public function resolveFormDepartment(Anamnesis $anamnesis): ?Department
    {
        $order = $this->findActiveOrderForAnamnesis($anamnesis);

        if ($order !== null) {
            return $order->getPipelineDepartment();
        }

        if ($anamnesis->sales_id !== null) {
            $department = $anamnesis->sales?->department;

            if ($department !== null) {
                return $department;
            }
        }

        return $anamnesis->lead?->department;
    }
}
