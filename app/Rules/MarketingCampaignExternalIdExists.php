<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;
use Webkul\Marketing\Models\Campaign;

/**
 * Validates marketing campaign by external_id without using the database presence verifier
 * (which turns connection errors into uncaught QueryExceptions during FormRequest resolution).
 */
class MarketingCampaignExternalIdExists implements ValidationRule
{
    public function __construct(
        private readonly bool $allowEmpty = false
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            if ($this->allowEmpty) {
                return;
            }

            $fail(__('validation.exists', ['attribute' => $attribute]));

            return;
        }

        if (! is_string($value)) {
            $fail(__('validation.exists', ['attribute' => $attribute]));

            return;
        }

        try {
            $exists = Campaign::query()->where('external_id', $value)->exists();
        } catch (QueryException $e) {
            Log::error('Marketing campaign lookup failed during validation', [
                'attribute' => $attribute,
                'message'   => $e->getMessage(),
            ]);

            throw new HttpResponseException(response()->json([
                'message' => 'Service temporarily unavailable.',
            ], 503));
        }

        if (! $exists) {
            $fail(__('validation.exists', ['attribute' => $attribute]));
        }
    }
}
