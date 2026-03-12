<?php

namespace App\Enums;

enum PaymentType: string
{
    case ADVANCE = 'advance';
    case REFUND = 'refund';

    public static function options(): array
    {
        return array_map(
            static fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases()
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::ADVANCE => 'Aanbetaling',
            self::REFUND  => 'Terugbetaling',
        };
    }
}
