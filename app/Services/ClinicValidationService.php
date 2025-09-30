<?php

namespace App\Services;

use App\Validators\ContactArrayValidator;
use Webkul\Core\Contracts\Validations\EmailValidator;
use Webkul\Core\Contracts\Validations\PhoneValidator;

class ClinicValidationService
{
    /**
     * Get the validation rules for Clinic creation and updates
     */
    public static function getValidationRules(?int $id = null): array
    {
        return [
            // Required fields
            'name' => $id 
                ? 'required|max:100|unique:clinics,name,' . $id
                : 'required|unique:clinics,name|max:100',

            // Contact information
            'emails'         => ['nullable', new ContactArrayValidator('email')],
            'emails.*.value' => ['nullable', new EmailValidator],
            'emails.*.label' => 'nullable|string|max:50',
            
            'phones'         => ['nullable', new ContactArrayValidator('telefoon')],
            'phones.*.value' => ['nullable', new PhoneValidator],
            'phones.*.label' => 'nullable|string|max:50',

            // Address (handled by address component)
            'address.postal_code'         => 'nullable|string|max:20',
            'address.house_number'        => 'nullable|string|max:20',
            'address.house_number_suffix' => 'nullable|string|max:20',
            'address.street'              => 'nullable|string|max:255',
            'address.city'                => 'nullable|string|max:255',
            'address.state'               => 'nullable|string|max:255',
            'address.country'             => 'nullable|string|max:255',
            
            // System fields
            'external_id' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get validation rules for creating clinics
     */
    public static function getCreateValidationRules(): array
    {
        return self::getValidationRules(null);
    }

    /**
     * Get validation rules for updating clinics
     */
    public static function getUpdateValidationRules(int $id): array
    {
        return self::getValidationRules($id);
    }
}