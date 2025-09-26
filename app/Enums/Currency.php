<?php

namespace App\Enums;

enum Currency: string
{
    case EUR = 'EUR';
    case USD = 'USD';
    case GBP = 'GBP';
    case CHF = 'CHF';
    case DKK = 'DKK';
    case NOK = 'NOK';
    case SEK = 'SEK';

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
            ['code' => self::USD->value, 'label' => 'US Dollar (USD)'],
            ['code' => self::GBP->value, 'label' => 'British Pound (GBP)'],
            ['code' => self::CHF->value, 'label' => 'Swiss Franc (CHF)'],
            ['code' => self::DKK->value, 'label' => 'Danish Krone (DKK)'],
            ['code' => self::NOK->value, 'label' => 'Norwegian Krone (NOK)'],
            ['code' => self::SEK->value, 'label' => 'Swedish Krona (SEK)'],
        ];
    }
}
