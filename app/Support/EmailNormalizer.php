<?php

namespace App\Support;

final class EmailNormalizer
{
    /**
     * Normalize an email to canonical form: trimmed and lowercased.
     *
     * Returns null if the input is empty after trim.
     */
    public static function normalize(string $email): ?string
    {
        $normalized = strtolower(trim($email));

        return $normalized !== '' ? $normalized : null;
    }
}
