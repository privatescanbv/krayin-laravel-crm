<?php

namespace App\Enums;

/**
 * Where a person stood with us at a given moment, see CustomerHistoryService.
 */
enum CustomerType: string
{
    case New = 'new';
    case ExistingBuyer = 'existing_buyer';
    case ExistingNonBuyer = 'existing_non_buyer';

    public function label(): string
    {
        return match ($this) {
            self::New              => 'Nieuwe lead',
            self::ExistingBuyer    => 'Bestaande klant, eerder gekocht',
            self::ExistingNonBuyer => 'Bestaande klant, nooit gekocht',
        };
    }

    /**
     * How much history this type implies, for picking the strongest of several persons.
     */
    public function rank(): int
    {
        return match ($this) {
            self::New              => 0,
            self::ExistingNonBuyer => 1,
            self::ExistingBuyer    => 2,
        };
    }
}
