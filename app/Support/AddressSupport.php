<?php

namespace App\Support;

use App\Models\Address;

class AddressSupport
{
    public const NO_ADDRESS_LABEL = 'Geen adres bekend';

    public const MISSING_POSTCODE_WARNING = 'Postcode ontbreekt. Controleer of het adres volledig is.';

    public const WARNING_MISSING_POSTCODE = 'missing_postcode';

    /**
     * Address fields required when any address field is filled (postcode excluded).
     *
     * @return list<string>
     */
    public static function strictRequiredFields(): array
    {
        return ['house_number', 'street', 'city', 'country'];
    }

    /**
     * @return list<string>
     */
    public static function strictRequiredFieldKeys(string $prefix = 'address'): array
    {
        return array_map(
            static fn (string $field): string => "{$prefix}.{$field}",
            self::strictRequiredFields(),
        );
    }

    public static function hasPostalCode(?Address $address): bool
    {
        if ($address === null) {
            return false;
        }

        return trim((string) ($address->postal_code ?? '')) !== '';
    }

    public static function hasAddressData(?Address $address): bool
    {
        if ($address === null) {
            return false;
        }

        foreach (['street', 'house_number', 'house_number_suffix', 'postal_code', 'city', 'state', 'country'] as $field) {
            if (trim((string) ($address->{$field} ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    public static function isMissingPostcode(?Address $address): bool
    {
        return self::hasAddressData($address) && ! self::hasPostalCode($address);
    }

    /**
     * @return list<string>
     */
    public static function warnings(?Address $address): array
    {
        if (! self::isMissingPostcode($address)) {
            return [];
        }

        return [self::WARNING_MISSING_POSTCODE];
    }

    public static function formatPostalCodeForDisplay(?string $postalCode): string
    {
        $postalCode = trim((string) $postalCode);
        if ($postalCode === '') {
            return '';
        }

        if (preg_match('/^([0-9]{4})([A-Z]{2})$/u', $postalCode, $matches)) {
            return $matches[1].' '.$matches[2];
        }

        return $postalCode;
    }

    public static function formatLine1(?Address $address): string
    {
        if ($address === null) {
            return '';
        }

        $suffix = $address->house_number_suffix ? ' '.$address->house_number_suffix : '';

        return trim(($address->street ?? '').' '.($address->house_number ?? '').$suffix);
    }

    public static function formatLine2(?Address $address): string
    {
        if ($address === null) {
            return '';
        }

        $postalFormatted = self::formatPostalCodeForDisplay($address->postal_code ?? '');

        return trim($postalFormatted.' '.($address->city ?? ''));
    }

    public static function formatFull(?Address $address, bool $includeCountry = true): string
    {
        if ($address === null) {
            return '';
        }

        $lines = array_values(array_filter([
            self::formatLine1($address),
            self::formatLine2($address),
            $includeCountry ? trim((string) ($address->country ?? '')) : '',
        ], static fn (string $line): bool => $line !== ''));

        return implode(', ', $lines);
    }

    public static function formatMultiline(?Address $address): string
    {
        if ($address === null) {
            return '';
        }

        $lines = array_values(array_filter([
            self::formatLine1($address),
            self::formatLine2($address),
        ], static fn (string $line): bool => $line !== ''));

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string>
     */
    public static function buildEmailVariables(?Address $address): array
    {
        $keys = [
            'address_street', 'address_house_number', 'address_house_number_suffix',
            'address_postal_code', 'address_city', 'address_state', 'address_country',
            'address_line1', 'address_line2', 'address_full',
        ];

        if ($address === null) {
            $empty = array_fill_keys($keys, '');
            $empty['address_full'] = '<span style="display:block;">'.e(self::NO_ADDRESS_LABEL).'</span>';

            return $empty;
        }

        $line1 = self::formatLine1($address);
        $line2 = self::formatLine2($address);
        $line3 = trim((string) ($address->country ?? ''));
        $postalFormatted = self::formatPostalCodeForDisplay($address->postal_code ?? '');
        $fullLines = array_values(array_filter([$line1, $line2, $line3], static fn (string $s): bool => $s !== ''));
        $addressFull = implode('', array_map(
            static fn (string $s): string => '<span style="display:block;">'.e($s).'</span>',
            $fullLines,
        ));

        return [
            'address_street'              => $address->street ?? '',
            'address_house_number'        => $address->house_number ?? '',
            'address_house_number_suffix' => $address->house_number_suffix ?? '',
            'address_postal_code'         => $postalFormatted,
            'address_city'                => $address->city ?? '',
            'address_state'               => $address->state ?? '',
            'address_country'             => $address->country ?? '',
            'address_line1'               => $line1,
            'address_line2'               => $line2,
            'address_full'                => $addressFull,
        ];
    }
}
