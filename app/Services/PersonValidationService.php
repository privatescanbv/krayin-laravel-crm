<?php

namespace App\Services;

use App\Enums\PersonGender;
use App\Enums\PersonSalutation;
use App\Validators\ContactArrayValidator;
use App\Validators\DateValidator;
use Illuminate\Validation\Rules\Enum;
use Webkul\Core\Contracts\Validations\EmailValidator;
use Webkul\Core\Contracts\Validations\PhoneValidator;

class PersonValidationService
{
    /**
     * Get the validation rules for Person creation and updates
     */
    public static function getValidationRules($request = null): array
    {
        return [
            // Required personal fields
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',

            // Optional personal fields
            'salutation'          => ['nullable', new Enum(PersonSalutation::class)],
            'initials'            => 'nullable|string|max:50',
            'lastname_prefix'     => 'nullable|string|max:50',
            'married_name'        => 'nullable|string|max:255',
            'married_name_prefix' => 'nullable|string|max:50',
            'gender'              => ['nullable', new Enum(PersonGender::class)],
            'date_of_birth'       => ['nullable', new DateValidator],
            'job_title'           => 'nullable|string|max:255',

            // Contact information
            'emails'                  => ['nullable', new ContactArrayValidator('email')],
            'emails.*.value'          => ['nullable', new EmailValidator],
            'emails.*.label'          => 'nullable|string|max:50',
            'phones'                  => ['nullable', new ContactArrayValidator('telefoon')],
            'phones.*.value'          => ['nullable', new PhoneValidator],
            'phones.*.label'          => 'nullable|string|max:50',
            'contact_numbers'         => ['nullable', new ContactArrayValidator('telefoon')],
            'contact_numbers.*.value' => ['nullable', new PhoneValidator],
            'contact_numbers.*.label' => 'nullable|string|max:50',

            // Relationships
            'user_id'         => 'nullable|numeric|exists:users,id',
            'organization_id' => 'nullable|numeric|exists:organizations,id',

            // Address fields
            'address.postal_code'         => 'nullable|string|max:20',
            'address.house_number'        => 'nullable|string|max:20',
            'address.house_number_suffix' => 'nullable|string|max:20',
            'address.street'              => 'nullable|string|max:255',
            'address.city'                => 'nullable|string|max:255',
            'address.state'               => 'nullable|string|max:255',
            'address.country'             => 'nullable|string|max:255',

            // Portal/account management
            'is_active' => 'sometimes|boolean',
            'password'  => 'nullable|string|min:8|max:255',

            // System fields
            'entity_type' => 'nullable|string',
        ];
    }

    /**
     * Get validation rules specifically for API endpoints
     */
    public static function getApiValidationRules($request = null): array
    {
        $rules = self::getValidationRules($request);

        // For API, make some fields required that are optional in web
        // Add any API-specific requirements here if needed

        return $rules;
    }

    /**
     * Get validation rules for web forms
     */
    public static function getWebValidationRules($request = null): array
    {
        return self::getValidationRules($request);
    }

    /**
     * Get validation rules for creating persons from lead contact matcher
     */
    public static function getContactMatcherValidationRules($request = null): array
    {
        $rules = self::getValidationRules($request);

        // For contact matcher, we might have less strict requirements
        // since data comes from lead forms

        return $rules;
    }
}
