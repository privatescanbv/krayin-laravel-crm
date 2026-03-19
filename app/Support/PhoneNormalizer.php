<?php

namespace App\Support;

final class PhoneNormalizer
{
    /**
     * Normalize to E.164 format for storage (eg "+31612345678").
     *
     * - "+..." preserved (non-digits stripped)
     * - "06XXXXXXXX" (10 digits) → "+316XXXXXXXX"
     * - "316XXXXXXXX" → "+316XXXXXXXX"
     * - Other digit strings → "+{digits}" as fallback
     *
     * Returns null if the input is empty or produces no digits.
     */
    public static function toE164(string $phone): ?string
    {
        $raw = trim($phone);
        if ($raw === '') {
            return null;
        }

        // Preserve leading +, strip the rest to digits
        if (str_starts_with($raw, '+')) {
            $digits = preg_replace('/[^0-9]/', '', $raw);

            return $digits ? ('+'.$digits) : null;
        }

        // Strip non-digits
        $digits = preg_replace('/[^0-9]/', '', $raw);
        if (! $digits) {
            return null;
        }

        // Convert "06XXXXXXXX" (10 digits) → "+316XXXXXXXX"
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+31'.substr($digits, 1);
        }

        // Convert "31XXXXXXXX" → "+31XXXXXXXX"
        if (str_starts_with($digits, '31')) {
            return '+'.$digits;
        }

        // Fallback: return as "+<digits>"
        return '+'.$digits;
    }

    /**
     * Normalize to Dutch local "0-prefix" format for comparison (eg "0612345678").
     *
     * - Strips all non-digits
     * - "31XXXXXXXX" (≥10 digits) → "0XXXXXXXX"
     * - Others returned as-is (digits only)
     */
    public static function toDutchLocal(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($digits, '31') && strlen($digits) >= 10) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }
}
