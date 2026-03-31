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

    public function badgeClass(): string
    {
        return match ($this) {
            self::NEW     => 'bg-neutral-bg text-gray-800',
            self::PLANNED => 'bg-green-100 text-green-800',
            self::WON     => 'bg-blue-100 text-blue-800',
            self::LOST    => 'bg-red-100 text-red-800',
        };
    }

    public function isPlannedStatus(): bool
    {
        return $this === self::PLANNED || $this === self::WON || $this === self::LOST;
    }
}
