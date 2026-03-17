<?php

namespace App\Http\Requests;

use App\Enums\FormType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entity_type'      => ['required', 'string', 'in:forms'],
            'id'               => ['required'],
            'action'           => ['required', 'string', 'in:STATUS_UPDATE'],
            'status'           => ['required', 'string'],
            'url'              => ['required', 'string'],
            'person_id'        => ['required', 'integer', 'exists:persons,id'],
            'form_type'        => ['required', Rule::enum(FormType::class)],
        ];
    }
}
