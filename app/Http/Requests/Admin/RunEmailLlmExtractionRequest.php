<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RunEmailLlmExtractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('user');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'system_prompt'  => ['nullable', 'string', 'max:20000'],
            'apply_links'    => ['sometimes', 'boolean'],
            'force_refresh'  => ['sometimes', 'boolean'],
        ];
    }
}
