<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SaveAiFeedbackRequest extends FormRequest
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
            'feedback' => ['required', 'string', 'max:1000'],
        ];
    }
}
