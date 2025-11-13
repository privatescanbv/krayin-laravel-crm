<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case NEW = 'new';
    case PLANNED = 'planned';

    public function label(): string
    {
        return match ($this) {
            self::NEW     => 'Nieuw',
            self::PLANNED => 'Ingepland',
        };
    }
}
