<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case ACTIVE = 'active';
    case IN_PROGRESS = 'in_progress';
    case ON_HOLD = 'on_hold';
    case EXPIRED = 'expired';
    case DONE = 'done';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE      => 'Actief',
            self::IN_PROGRESS => 'In behandeling',
            self::ON_HOLD     => 'On hold',
            self::EXPIRED     => 'Verlopen',
            self::DONE        => 'Afgerond',
        };
    }
}

//

//
