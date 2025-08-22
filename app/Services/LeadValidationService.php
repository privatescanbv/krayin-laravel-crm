<?php

namespace App\Services;

use App\Validators\ContactArrayValidator;
use App\Validators\DateValidator;
use Webkul\Core\Contracts\Validations\EmailValidator;
use Webkul\Core\Contracts\Validations\PhoneValidator;

class LeadValidationService
{
    /**
     * Get the validation rules for Lead creation and updates
     */
    public static function getValidationRules($request = null): array
    {
        return [
            'first_name'          => 'required|string|max:255',
            'last_name'           => 'required|string|max:255',
            'description'         => 'nullable|string',
            'salutation'          => 'nullable|string|max:50',
            'initials'            => 'nullable|string|max:50',
            'lastname_prefix'     => 'nullable|string|max:50',
            'married_name'        => 'nullable|string|max:255',
            'married_name_prefix' => 'nullable|string|max:50',
            'gender'              => 'nullable|string|in:Man,Vrouw,Anders',
            'date_of_birth'       => ['nullable', new DateValidator],

            // Contact information
            'emails'         => ['nullable', new ContactArrayValidator('email')],
            'emails.*.value' => ['nullable', new EmailValidator],
            'emails.*.label' => 'nullable|string|max:50',
            'phones'         => ['nullable', new ContactArrayValidator('telefoon')],
            'phones.*.value' => ['nullable', new PhoneValidator],
            'phones.*.label' => 'nullable|string|max:50',

            // Lead specific fields
            'lead_value'          => 'nullable|numeric|min:0',
            'expected_close_date' => 'nullable|date|after:yesterday',
            'lead_source_id'      => 'nullable|numeric|exists:lead_sources,id',
            'lead_channel_id'     => 'nullable|numeric|exists:lead_channels,id',
            'lead_type_id'        => 'nullable|numeric|exists:lead_types,id',
            'department_id'       => 'required|numeric|exists:departments,id',
            'user_id'             => 'nullable|numeric|exists:users,id',

            // Person relationships (multiple persons supported)
            'person_ids'                => 'nullable|array',
            'person_ids.*'              => 'numeric|exists:persons,id',
            'persons'                   => 'nullable|array',
            'persons.*.id'              => 'nullable|numeric|exists:persons,id',
            'persons.*.name'            => 'nullable|string|max:255',
            'persons.*.emails'          => 'nullable|array',
            'persons.*.contact_numbers' => 'nullable|array',

            // Lead organization (standalone for billing)
            'organization_id' => 'nullable|numeric|exists:organizations,id',

            // Order combination setting
            // 'combine_order' => 'nullable|boolean', // Temporarily disabled until migration runs

            // Address fields
            'address.postal_code'         => 'nullable|string|max:20',
            'address.house_number'        => 'nullable|string|max:20',
            'address.house_number_suffix' => 'nullable|string|max:20',
            'address.street'              => 'nullable|string|max:255',
            'address.city'                => 'nullable|string|max:255',
            'address.state'               => 'nullable|string|max:255',
            'address.country'             => 'nullable|string|max:255',
        ];
    }

    /**
     * Get validation rules specifically for API endpoints
     */
    public static function getApiValidationRules($request = null): array
    {
        $rules = self::getValidationRules($request);

        // For API, make some fields required that are optional in web
        $rules['lead_source_id'] = 'required|numeric|exists:lead_sources,id';
        $rules['lead_channel_id'] = 'required|numeric|exists:lead_channels,id';
        $rules['lead_type_id'] = 'required|numeric|exists:lead_types,id';

        // Add legacy email field for API compatibility (if needed)
        // $rules['email'] = 'required|email';

        return $rules;
    }

    /**
     * Get validation rules for web forms
     */
    public static function getWebValidationRules($request = null): array
    {
        return self::getValidationRules($request);
    }
}
