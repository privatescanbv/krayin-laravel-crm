<?php

namespace App\Http\Requests\Admin\Settings;

use App\Validators\ContactArrayValidator;
use Illuminate\Foundation\Http\FormRequest;
use Webkul\Core\Contracts\Validations\EmailValidator;
use Webkul\Core\Contracts\Validations\PhoneValidator;

class StoreClinicRequest extends FormRequest
{
    public static function rulesForCreate(): array
    {
        return (new self)->rules();
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Required fields
            'name' => 'required|unique:clinics,name|max:100',

            // Optional description
            'description' => 'nullable|string|max:2000',

            // Active status
            'is_active' => 'nullable|boolean',

            // Website and order confirmation
            'website_url'             => 'nullable|url|max:255',
            'order_confirmation_note' => 'nullable|string|max:1000',

            // Contact information
            'emails'         => ['nullable', new ContactArrayValidator('email')],
            'emails.*.value' => ['nullable', new EmailValidator],
            'emails.*.label' => 'nullable|string|max:50',

            'phones'         => ['nullable', new ContactArrayValidator('telefoon')],
            'phones.*.value' => ['nullable', new PhoneValidator],
            'phones.*.label' => 'nullable|string|max:50',

            // Addresses
            'is_postal_address_same_as_visit_address' => 'nullable|boolean',

            'visit_address.postal_code'         => 'nullable|string|max:20',
            'visit_address.house_number'        => 'nullable|string|max:20',
            'visit_address.house_number_suffix' => 'nullable|string|max:20',
            'visit_address.street'              => 'nullable|string|max:255',
            'visit_address.city'                => 'nullable|string|max:255',
            'visit_address.state'               => 'nullable|string|max:255',
            'visit_address.country'             => 'nullable|string|max:255',

            'postal_address.postal_code'         => 'nullable|string|max:20',
            'postal_address.house_number'        => 'nullable|string|max:20',
            'postal_address.house_number_suffix' => 'nullable|string|max:20',
            'postal_address.street'              => 'nullable|string|max:255',
            'postal_address.city'                => 'nullable|string|max:255',
            'postal_address.state'               => 'nullable|string|max:255',
            'postal_address.country'             => 'nullable|string|max:255',

            // System fields
            'external_id' => 'nullable|string|max:255',
        ];
    }
}
