<?php

namespace App\Enums;

enum PaymentType: string
{
    case ADVANCE = 'advance';
    case PAYED_IN_CLINIC = 'paid_at_clinic';
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
            self::ADVANCE         => 'Vooruitbetaling',
            self::PAYED_IN_CLINIC => 'Ontvangen in kliniek',
            self::REFUND          => 'Terugbetaling',
        };
    }
}
