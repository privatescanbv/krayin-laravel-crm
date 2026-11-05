<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ClinicSalesLeadIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage_id' => ['sometimes', 'integer'],
            'page'     => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function queryParameters(): array
    {
        return [
            'stage_id' => ['description' => 'Only sales in this stage (a stage the user may not see gives an empty page).', 'example' => 41],
            'page'     => ['description' => 'Page number.', 'example' => 1],
            'per_page' => ['description' => 'Items per page (1-100, default 25).', 'example' => 25],
        ];
    }
}
