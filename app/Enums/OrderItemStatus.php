<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case NEW = 'new';
    case PLANNED = 'planned';
    case WON = 'won';
    case LOST = 'lost';
    // Wrongly offered examination/operation: lost like LOST (isLost()), but not lost revenue.
    case INCORRECT = 'incorrect';

    /**
     * @return list<string>
     */
    public static function lostValues(): array
    {
        return [self::LOST->value, self::INCORRECT->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::NEW       => 'Nieuw',
            self::PLANNED   => 'Ingepland',
            self::WON       => 'Gewonnen',
            self::LOST      => 'Verloren',
            self::INCORRECT => 'Foutief',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NEW       => 'bg-neutral-bg text-gray-800',
            self::PLANNED   => 'bg-green-100 text-green-800',
            self::WON       => 'bg-blue-100 text-blue-800',
            self::LOST      => 'bg-red-100 text-red-800',
            self::INCORRECT => 'bg-orange-100 text-orange-800',
        };
    }

    public function isPlannedStatus(): bool
    {
        return $this === self::PLANNED || $this === self::WON || $this->isLost();
    }

    /**
     * LOST or INCORRECT: the line no longer counts for the order (total, planning, payment).
     */
    public function isLost(): bool
    {
        return $this === self::LOST || $this === self::INCORRECT;
    }
}
