<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PatientDocumentsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],

            // Optional filters
            'order_id' => ['sometimes', 'integer', 'min:1', 'exists:orders,id'],
            'type'     => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * Extra metadata for Scribe docs (options + defaults).
     */
    public function queryParameters(): array
    {
        return [
            'page' => [
                'description' => 'Page number.',
                'example'     => 1,
            ],
            'per_page' => [
                'description' => 'Items per page (max 100). Default: 15.',
                'example'     => 15,
            ],
            'order_id' => [
                'description' => 'Optional: limit documents to a single Order id.',
                'example'     => 987,
            ],
            'type' => [
                'description' => 'Optional: document kind (stored in activity.additional.document_type).',
                'example'     => 'report',
            ],
        ];
    }
}
