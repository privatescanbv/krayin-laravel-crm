<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BANK = 'bank';
    case PIN = 'pin';
    case CASH = 'cash';
    case CREDITCARD = 'creditcard';

    public static function options(): array
    {
        return array_map(
            static fn (self $method) => ['value' => $method->value, 'label' => $method->label()],
            self::cases()
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::BANK       => 'Bank',
            self::PIN        => 'Pin',
            self::CASH       => 'Contant',
            self::CREDITCARD => 'Creditcard',
        };
    }
}
