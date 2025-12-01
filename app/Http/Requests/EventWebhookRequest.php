<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'in:forms'],
            'id'          => ['required'],
            'action'      => ['required', 'string', 'in:STATUS_UPDATE'],
            'status'      => ['required', 'string'],
            'url'         => ['required', 'string'],
        ];
    }
}
