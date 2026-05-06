<?php

namespace App\Support;

class PasswordGenerator
{
    private const UPPER = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const LOWER = 'abcdefghijklmnopqrstuvwxyz';

    private const DIGITS = '0123456789';

    private const SPECIAL = '!@#$%^&*()_+-=[]{}|';

    /**
     * Generate a random password that satisfies the application password policy:
     * min 8 chars, mixed case, at least one digit, at least one special character.
     */
    public static function generate(int $length = 12): string
    {
        // Guarantee one character from each required category
        $chars = [
            self::UPPER[random_int(0, strlen(self::UPPER) - 1)],
            self::LOWER[random_int(0, strlen(self::LOWER) - 1)],
            self::DIGITS[random_int(0, strlen(self::DIGITS) - 1)],
            self::SPECIAL[random_int(0, strlen(self::SPECIAL) - 1)],
        ];

        $all = self::UPPER.self::LOWER.self::DIGITS.self::SPECIAL;
        for ($i = count($chars); $i < max($length, 8); $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        shuffle($chars);

        return implode('', $chars);
    }
}
