<?php

namespace App\Services;

use App\Enums\ProductReports;
use App\Models\Order;
use App\Models\OrderCheck;
use App\Models\OrderItem;
use App\Models\PartnerProduct;
use App\Models\ResourceOrderItem;
use Illuminate\Support\Collection;
use Webkul\Contact\Models\Person;

class OrderCheckService
{
    /**
     * Surface a non-removable order check so the user notices the purchase price could not be
     * resolved automatically (e.g. a non-plannable product with zero or multiple matching
     * partner products). Replaces any earlier check for the same order item.
     */
    public function flagPurchasePriceIssue(OrderItem $orderItem, string $reason): void
    {
        if (! $orderItem->order_id) {
            return;
        }

        $this->purchasePriceChecks($orderItem)->delete();

        OrderCheck::create([
            'order_id'  => $orderItem->order_id,
            'name'      => "{$this->purchasePriceCheckPrefix($orderItem)}: {$reason}",
            'done'      => false,
            'removable' => false,
        ]);
    }

    /**
     * Clear a previously flagged purchase price issue for this order item, e.g. once it has
     * been resolved automatically or filled in manually.
     */
    public function clearPurchasePriceIssue(OrderItem $orderItem): void
    {
        if (! $orderItem->order_id) {
            return;
        }

        $this->purchasePriceChecks($orderItem)->delete();
    }

    public function retrieveClinicFromOrder(Order $order, Person $patient): ?string
    {
        $firstBooking = ResourceOrderItem::query()
            ->with(['resource:id,clinic_department_id', 'resource.clinicDepartment:id,clinic_id'])
            ->whereHas('orderItem', function ($q) use ($order, $patient) {
                $q->where('order_id', $order->id);

                // Only restrict to the patient's order items when the order is explicitly NOT combined.
                // (Treat null as "combined", since legacy rows may have null here.)
                if ($order->combine_order === false) {
                    $q->where('person_id', $patient->id);
                }
            })
            ->orderBy('from', 'asc')
            ->first();

        $clinicId = $firstBooking?->resource?->clinicDepartment?->clinic_id;

        return $clinicId !== null ? (string) $clinicId : null;
    }

    /**
     * Update order checks based on partner product relations, scoped per person.
     *
     * Each (person, reporting type) combination gets its own check so that orders
     * with multiple persons never skip checks because a same-named check already exists.
     */
    public function updatePartnerProductChecks(Order $order): void
    {
        // Get existing partner product checks
        $existingChecks = $order->orderChecks()
            ->where('removable', false)
            ->where('name', 'like', 'Partner product rapportage:%')
            ->get()
            ->keyBy('name');

        // Build the full set of check names that should exist
        $requiredCheckNames = $this->buildRequiredCheckNames($order);

        // Create missing checks
        foreach ($requiredCheckNames as $checkName) {
            if (! $existingChecks->has($checkName)) {
                OrderCheck::create([
                    'order_id'  => $order->id,
                    'name'      => $checkName,
                    'done'      => false,
                    'removable' => false,
                ]);
            }
        }

        // Remove checks for reporting types that are no longer relevant
        $checksToRemove = $existingChecks->whereNotIn('name', $requiredCheckNames);

        foreach ($checksToRemove as $check) {
            $check->delete();
        }
    }

    /**
     * Match on "#{id}: " including the separator: a bare "#12%" would also match line #123.
     */
    private function purchasePriceChecks(OrderItem $orderItem)
    {
        return OrderCheck::where('order_id', $orderItem->order_id)
            ->where('name', 'like', $this->purchasePriceCheckPrefix($orderItem).': %');
    }

    private function purchasePriceCheckPrefix(OrderItem $orderItem): string
    {
        return "Inkoopprijs ontbreekt — orderregel #{$orderItem->id}";
    }

    /**
     * Build the set of check names required for this order.
     *
     * Groups order items by person so that two persons with the same product each
     * get their own uniquely named check (suffix "— {person name}").
     */
    private function buildRequiredCheckNames(Order $order): Collection
    {
        $orderItems = $order->orderItems()
            ->whereNotNull('product_id')
            ->with('person')
            ->get();

        $checkNames = collect();

        foreach ($orderItems->groupBy('person_id') as $personId => $items) {
            $person = $items->first()->person;
            $productIds = $items->pluck('product_id')->unique();

            $partnerProducts = PartnerProduct::whereIn('product_id', $productIds)
                ->whereNull('deleted_at')
                ->get();

            $reportingTypes = $this->getReportingTypesFromPartnerProducts($partnerProducts);

            foreach ($reportingTypes as $reportingType) {
                $checkName = $person
                    ? "Partner product rapportage: $reportingType — $person->name"
                    : "Partner product rapportage: $reportingType";

                $checkNames->push($checkName);
            }
        }

        return $checkNames->unique()->values();
    }

    /**
     * Get all reporting types from partner products
     */
    private function getReportingTypesFromPartnerProducts(Collection $partnerProducts): Collection
    {
        $reportingTypes = collect();

        foreach ($partnerProducts as $partnerProduct) {
            if ($partnerProduct->reporting) {
                $normalizedReporting = PartnerProduct::normalizeReporting($partnerProduct->reporting);
                foreach ($normalizedReporting as $reporting) {
                    $enum = ProductReports::tryFrom($reporting);
                    if ($enum) {
                        $reportingTypes->push($enum->getLabel());
                    }
                }
            }
        }

        return $reportingTypes->unique()->sort();
    }
}
