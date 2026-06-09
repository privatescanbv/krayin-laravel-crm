<?php

namespace App\Enums;

enum ActivityActionType: string
{
    case Notitie = 'notitie';
    case Belstatus = 'belstatus';

    public function label(): string
    {
        return match ($this) {
            self::Notitie   => 'Notitie',
            self::Belstatus => 'Belstatus',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Notitie   => 'icon-comment',
            self::Belstatus => 'icon-phone',
        };
    }
}
