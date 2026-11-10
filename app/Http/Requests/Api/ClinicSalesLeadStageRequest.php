<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ClinicSalesLeadStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pipeline_stage_id' => ['required', 'integer'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'pipeline_stage_id' => ['description' => 'Target stage: an ordinary (not won/lost) stage of the Herniapoli sales pipeline that the doctor\'s role may see.', 'example' => 42],
        ];
    }
}
