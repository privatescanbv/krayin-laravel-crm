<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ClinicSalesMessageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:2000'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'text' => ['description' => 'Plain text of the team chat message (1-2000 characters). No HTML.', 'example' => 'Kunt u de MRI-uitslag inzien?'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('text'))) {
            $this->merge(['text' => trim($this->input('text'))]);
        }
    }
}
