<?php

namespace App\Repositories;

use App\Models\PartnerProduct;
use Webkul\Core\Eloquent\Repository;

class PartnerProductRepository extends Repository
{
    public function model(): string
    {
        return PartnerProduct::class;
    }

    /**
     * Format partner product name with clinic names for display.
     */
    public function formatDisplayName(PartnerProduct $partnerProduct): string
    {
        $clinicNames = $partnerProduct->clinics->pluck('name')->join(', ');

        return $clinicNames ? "{$clinicNames} - {$partnerProduct->name}" : $partnerProduct->name;
    }

    /**
     * Get partner products formatted for search/display with clinic names.
     *
     * @return \Illuminate\Support\Collection
     */
    public function searchFormatted(string $query = '', int $limit = 50)
    {
        $products = $this->scopeQuery(function ($q) use ($query) {
            return $q->where('active', true)
                ->where('name', 'like', '%'.$query.'%')
                ->orderBy('name')
                ->limit($limit);
        })->all();

        // Load clinics for each product separately to avoid N+1 queries
        $products->load('clinics:id,name');

        return $products->map(function ($product) {
            return [
                'id'   => $product->id,
                'name' => $this->formatDisplayName($product),
            ];
        });
    }
}
