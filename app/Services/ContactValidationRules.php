<?php

namespace App\Services;

use App\Enums\PersonGender;
use App\Enums\PersonSalutation;
use App\Validators\ContactArrayValidator;
use App\Validators\DateValidator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Webkul\Core\Contracts\Validations\EmailValidator;
use Webkul\Core\Contracts\Validations\PhoneValidator;

class ContactValidationRules
{
    /** Allowed contact labels for NAW / patient endpoints. */
    public const CONTACT_LABELS = ['eigen', 'relatie', 'anders'];

    public static function emailRules(): array
    {
        return [
            'emails'         => ['nullable', new ContactArrayValidator('email')],
            'emails.*.value' => ['nullable', new EmailValidator],
            'emails.*.label' => 'nullable|string|max:50',
        ];
    }

    /**
     * Stricter email rules for endpoints that require complete contact entries
     * (required value + validated label + is_default flag).
     */
    public static function strictEmailRules(): array
    {
        return [
            'emails'              => ['nullable', new ContactArrayValidator('email')],
            'emails.*.value'      => ['required', 'string', new EmailValidator],
            'emails.*.label'      => ['required', Rule::in(self::CONTACT_LABELS)],
            'emails.*.is_default' => 'boolean',
        ];
    }

    public static function phoneRules(): array
    {
        return [
            'phones'         => ['nullable', new ContactArrayValidator('telefoon')],
            'phones.*.value' => ['nullable', new PhoneValidator],
            'phones.*.label' => 'nullable|string|max:50',
        ];
    }

    /**
     * Stricter phone rules for endpoints that require complete contact entries
     * (required value + validated label + is_default flag).
     */
    public static function strictPhoneRules(): array
    {
        return [
            'phones'              => ['nullable', new ContactArrayValidator('telefoon')],
            'phones.*.value'      => ['required', 'string', new PhoneValidator],
            'phones.*.label'      => ['required', Rule::in(self::CONTACT_LABELS)],
            'phones.*.is_default' => 'boolean',
        ];
    }

    public static function addressRules(): array
    {
        return [
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
     * Address rules with conditional required fields for new vs. existing addresses.
     * When $isNewAddress is true, house_number and postal_code are required
     * whenever an address block is present in the request.
     */
    public static function addressRulesForPerson(bool $isNewAddress): array
    {
        return array_merge(self::addressRules(), [
            'address'              => 'nullable|array',
            'address.house_number' => $isNewAddress
                ? ['required_with:address', 'string', 'max:50']
                : ['nullable', 'string', 'max:50'],
            'address.postal_code'  => $isNewAddress
                ? ['required_with:address', 'string', 'max:20']
                : ['nullable', 'string', 'max:20'],
        ]);
    }

    public static function personalNameRules(): array
    {
        return [
            'salutation'          => ['nullable', new Enum(PersonSalutation::class)],
            'initials'            => 'nullable|string|max:50',
            'lastname_prefix'     => 'nullable|string|max:50',
            'married_name'        => 'nullable|string|max:255',
            'married_name_prefix' => 'nullable|string|max:50',
            'gender'              => ['nullable', new Enum(PersonGender::class)],
            'date_of_birth'       => ['nullable', new DateValidator],
        ];
    }
}
