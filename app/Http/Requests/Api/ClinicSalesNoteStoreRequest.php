<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ClinicSalesNoteStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:5000'],
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'text' => ['description' => 'Plain text of the internal note (1-5000 characters). No HTML.', 'example' => 'Patiënt belt terug na MRI.'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('text'))) {
            $this->merge(['text' => trim($this->input('text'))]);
        }
    }
}
