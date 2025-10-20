<?php

namespace App\Services;

use App\Enums\ProductReports;
use App\Models\Order;
use App\Models\OrderCheck;
use App\Models\PartnerProduct;
use Illuminate\Support\Collection;

class OrderCheckService
{
    /**
     * Update order checks based on partner product relations
     */
    public function updatePartnerProductChecks(Order $order): void
    {
        // Get all partner products for this order's products
        $partnerProducts = $this->getPartnerProductsForOrder($order);

        // Get existing partner product checks
        $existingChecks = $order->orderChecks()
            ->where('removable', false)
            ->where('name', 'like', 'Partner product rapportage:%')
            ->get()
            ->keyBy('name');

        // Get all reporting types from partner products
        $reportingTypes = $this->getReportingTypesFromPartnerProducts($partnerProducts);

        // Create checks for each reporting type
        foreach ($reportingTypes as $reportingType) {
            $checkName = "Partner product rapportage: {$reportingType}";

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
        $currentCheckNames = $reportingTypes->map(fn ($type) => "Partner product rapportage: {$type}");
        $checksToRemove = $existingChecks->whereNotIn('name', $currentCheckNames);

        foreach ($checksToRemove as $check) {
            $check->delete();
        }
    }

    /**
     * Get all partner products for order items
     */
    private function getPartnerProductsForOrder(Order $order): Collection
    {
        $productIds = $order->orderItems()
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->unique();

        return PartnerProduct::whereIn('product_id', $productIds)
            ->whereNull('deleted_at')
            ->get();
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
