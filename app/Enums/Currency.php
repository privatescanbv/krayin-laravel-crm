<?php

namespace App\Enums;

/**
 * For now we only support euro, not needed.
 * Even the americans will pay in euro's to privatescan.
 *
 * When operating from another country as NL, this is needed. We also need to support change rates etc.
 */
enum Currency: string
{
    case EUR = 'EUR';
//    case USD = 'USD';
//    case GBP = 'GBP';
//    case CHF = 'CHF';
//    case DKK = 'DKK';
//    case NOK = 'NOK';
//    case SEK = 'SEK';

    public static function default(): self
    {
        return self::EUR;
    }

    public static function codes(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }

    public static function options(): array
    {
        return [
            ['code' => self::EUR->value, 'label' => 'Euro (EUR)'],
//            ['code' => self::USD->value, 'label' => 'US Dollar (USD)'],
//            ['code' => self::GBP->value, 'label' => 'British Pound (GBP)'],
//            ['code' => self::CHF->value, 'label' => 'Swiss Franc (CHF)'],
//            ['code' => self::DKK->value, 'label' => 'Danish Krone (DKK)'],
//            ['code' => self::NOK->value, 'label' => 'Norwegian Krone (NOK)'],
//            ['code' => self::SEK->value, 'label' => 'Swedish Krona (SEK)'],
        ];
    }

    /**
     * Format amount with currency using NL style: comma decimals, dot thousands.
     */
    public static function formatMoney(?string $currencyCode, float $amount): string
    {
        $symbolMap = [
            self::EUR->value => '€',
//            self::USD->value => '$',
//            self::GBP->value => '£',
//            self::CHF->value => 'CHF',
//            self::DKK->value => 'kr',
//            self::NOK->value => 'kr',
//            self::SEK->value => 'kr',
        ];

        $code = $currencyCode ?: self::default()->value;
        $symbol = $symbolMap[$code] ?? $code;
        $formatted = number_format($amount, 2, ',', '.');

//        if (in_array($code, [self::EUR->value, self::USD->value, self::GBP->value], true)) {
//            return $symbol.' '.$formatted;
//        }
        if (in_array($code, [self::EUR->value], true)) {
            return $symbol.' '.$formatted;
        }

        if (isset($symbolMap[$code])) {
            return $formatted.' '.$symbol;
        }

        return ($code ? $code.' ' : '').$formatted;
    }

    /**
     * Normalize localized price strings to a dot-decimal string.
     * Examples: "1.234,56" -> "1234.56", "45,00" -> "45.00".
     */
    public static function normalizePrice(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;
        $value = preg_replace('/\s+/', '', $value ?? '');

        if ($value === '') {
            return null;
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif ($hasComma && ! $hasDot) {
            $value = str_replace(',', '.', $value);
        }

        return $value;
    }
}
