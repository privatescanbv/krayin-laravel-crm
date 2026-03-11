<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case NEW = 'new';
    case PLANNED = 'planned';
    case WON = 'won';
    case LOST = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::NEW     => 'Nieuw',
            self::PLANNED => 'Ingepland',
            self::WON     => 'Gewonnen',
            self::LOST    => 'Verloren',
        };
    }

    public function isPlannedStatus(): bool
    {
        return $this === self::PLANNED || $this === self::WON || $this === self::LOST;
    }
}
