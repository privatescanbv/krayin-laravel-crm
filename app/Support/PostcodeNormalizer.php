<?php

namespace App\Support;

class PostcodeNormalizer
{
    /**
     * Normalize postal codes in a country-agnostic way for consistent storage.
     * - Trims surrounding whitespace
     * - Uppercases letters
     * - Removes all internal whitespace to avoid equality issues (e.g., "1234 AB" -> "1234AB")
     */
    public static function normalize(?string $postalCode): ?string
    {
        if ($postalCode === null) {
            return null;
        }

        $trimmed = trim($postalCode);
        if ($trimmed === '') {
            return '';
        }

        // Remove all Unicode whitespace characters
        $noWhitespace = preg_replace('/\s+/u', '', $trimmed);

        return mb_strtoupper($noWhitespace ?? '');
    }
}
