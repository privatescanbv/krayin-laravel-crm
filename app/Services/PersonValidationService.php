<?php

namespace App\Services;

use App\Validators\ContactArrayValidator;
use Webkul\Core\Contracts\Validations\PhoneValidator;

class PersonValidationService
{
    /**
     * Get the validation rules for Person creation and updates
     */
    public static function getValidationRules($request = null): array
    {
        return array_merge(
            [
                // Required personal fields
                'first_name' => 'required|string|max:255',
                'last_name'  => 'required|string|max:255',
                'job_title'  => 'nullable|string|max:255',
            ],
            ContactValidationRules::personalNameRules(),
            ContactValidationRules::emailRules(),
            ContactValidationRules::phoneRules(),
            ContactValidationRules::addressRules(),
            [
                // contact_numbers backwards compat (Person legacy field)
                'contact_numbers'         => ['nullable', new ContactArrayValidator('telefoon')],
                'contact_numbers.*.value' => ['nullable', new PhoneValidator],
                'contact_numbers.*.label' => 'nullable|string|max:50',

                // Relationships
                'user_id'         => 'nullable|numeric|exists:users,id',
                'organization_id' => 'nullable|numeric|exists:organizations,id',

                // Portal/account management
                'is_active' => 'sometimes|boolean',
                'password'  => 'nullable|string|min:8|max:255',

                // System fields
                'entity_type' => 'nullable|string',
            ]
        );
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
