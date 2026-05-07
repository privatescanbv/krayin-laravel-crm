<?php

namespace App\Http\Requests\Admin\Settings;

use Webkul\Core\Contracts\Validations\EmailValidator;

class UpdateClinicDepartmentRequest
{
    public static function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:255'],
            'email'                   => ['required', 'max:255', new EmailValidator],
            'description'             => ['nullable', 'string'],
            'order_confirmation_note' => ['nullable', 'string', 'max:1000'],
            'clinic_id'               => ['required', 'exists:clinics,id'],
        ];
    }
}
