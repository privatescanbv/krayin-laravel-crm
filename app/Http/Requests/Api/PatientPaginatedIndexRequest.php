<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request for patient "index" endpoints that use pagination.
 *
 * Provides:
 * - common validation rules for `page` and `per_page`
 * - common Scribe `queryParameters()` for `page` and `per_page`
 *
 * Child classes can add extra rules and query params via the protected hooks.
 */
abstract class PatientPaginatedIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    final public function rules(): array
    {
        return array_merge(
            $this->paginationRules(),
            $this->additionalRules()
        );
    }

    /**
     * Extra metadata for Scribe docs (options + defaults).
     */
    final public function queryParameters(): array
    {
        return array_merge(
            $this->paginationQueryParameters(),
            $this->additionalQueryParameters()
        );
    }

    /**
     * Override to add endpoint-specific rules.
     */
    protected function additionalRules(): array
    {
        return [];
    }

    /**
     * Override to add endpoint-specific Scribe query parameters.
     */
    protected function additionalQueryParameters(): array
    {
        return [];
    }

    protected function perPageMax(): int
    {
        return 100;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function paginationRules(): array
    {
        return [
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.$this->perPageMax()],
        ];
    }

    /**
     * @return array<string, array{description: string, example: mixed}>
     */
    private function paginationQueryParameters(): array
    {
        return [
            'page' => [
                'description' => 'Page number.',
                'example'     => 1,
            ],
            'per_page' => [
                'description' => 'Items per page (max '.$this->perPageMax().'). Default: 15.',
                'example'     => 15,
            ],
        ];
    }
}
