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

    /**
     * Human-readable attribute names for a strict address block.
     * Pass as the third argument to $request->validate() to replace
     * technical dot-notation keys in error messages.
     *
     * @param  string  $prefix  e.g. 'address', 'visit_address', 'postal_address'
     * @param  string  $label  Optional context label shown in parentheses, e.g. 'bezoekadres'
     */
    public static function strictAddressAttributes(string $prefix = 'address', string $label = ''): array
    {
        $suffix = $label ? " ({$label})" : '';

        return [
            "{$prefix}.postal_code"  => "Postcode{$suffix}",
            "{$prefix}.house_number" => "Huisnummer{$suffix}",
            "{$prefix}.street"       => "Straat{$suffix}",
            "{$prefix}.city"         => "Stad{$suffix}",
            "{$prefix}.country"      => "Land{$suffix}",
        ];
    }

    /**
     * Lenient address rules: all fields are nullable.
     * Use for API endpoints that allow partial address updates.
     */
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
     * Strict all-or-nothing address rules.
     * If any required field is filled, ALL required fields become mandatory.
     * Use for admin UI forms where a partial address should be rejected.
     * Optional fields (house_number_suffix, state) are always nullable.
     *
     * @param  string  $prefix  The dot-notation prefix for the address block, e.g. 'address',
     *                          'visit_address', or 'postal_address'.
     */
    public static function strictAddressRules(string $prefix = 'address'): array
    {
        $required = [
            "{$prefix}.postal_code",
            "{$prefix}.house_number",
            "{$prefix}.street",
            "{$prefix}.city",
            "{$prefix}.country",
        ];

        $others = fn (string $field) => implode(',', array_filter($required, fn ($f) => $f !== $field));

        return [
            "{$prefix}.postal_code"         => ['required_with:'.$others("{$prefix}.postal_code"), 'nullable', 'string', 'max:20'],
            "{$prefix}.house_number"        => ['required_with:'.$others("{$prefix}.house_number"), 'nullable', 'string', 'max:20'],
            "{$prefix}.house_number_suffix" => 'nullable|string|max:20',
            "{$prefix}.street"              => ['required_with:'.$others("{$prefix}.street"), 'nullable', 'string', 'max:255'],
            "{$prefix}.city"                => ['required_with:'.$others("{$prefix}.city"), 'nullable', 'string', 'max:255'],
            "{$prefix}.state"               => 'nullable|string|max:255',
            "{$prefix}.country"             => ['required_with:'.$others("{$prefix}.country"), 'nullable', 'string', 'max:255'],
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
