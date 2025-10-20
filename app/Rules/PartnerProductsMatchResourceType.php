<?php

namespace App\Rules;

use App\Models\PartnerProduct;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PartnerProductsMatchResourceType implements ValidationRule
{
    public function __construct(protected ?int $resourceTypeId) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || empty($value)) {
            return;
        }

        if (is_null($this->resourceTypeId)) {
            $fail('admin::app.products.validation.resource-type-required-for-partner-products')->translate();

            return;
        }

        $partnerProducts = PartnerProduct::whereIn('id', $value)
            ->whereNull('deleted_at')
            ->get(['id', 'name', 'resource_type_id']);

        $mismatchedProducts = $partnerProducts->filter(function ($partnerProduct) {
            return $partnerProduct->resource_type_id !== $this->resourceTypeId;
        });

        if ($mismatchedProducts->isNotEmpty()) {
            $names = $mismatchedProducts->pluck('name')->join(', ');
            $fail('admin::app.products.validation.partner-products-resource-type-mismatch')
                ->translate(['products' => $names]);
        }
    }
}
