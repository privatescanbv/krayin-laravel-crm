<?php

namespace App\Rules;

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

        // Resource type mismatch between partner products and product is intentionally allowed:
        // OrderItem can override the resource type, which is needed for one-off products.
    }
}
