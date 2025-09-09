<?php

namespace App\Enums;

enum ActivityStatus: string
{
    case NEW = 'new';
    case IN_PROGRESS = 'in_progress';
    case ON_HOLD = 'on_hold';

    public function label(): string
    {
        return match($this) {
            self::NEW => 'Nieuw',
            self::IN_PROGRESS => 'In behandeling',
            self::ON_HOLD => 'On hold',
        };
    }
}