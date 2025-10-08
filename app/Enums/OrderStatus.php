<?php

namespace App\Enums;

enum OrderStatus: string
{
    case NIEUW = 'nieuw';
    case INGEPLAND = 'ingepland';
    case VERSTUURD = 'verstuurd';
    case AKKOORD = 'akkoord';
    case AFGEWEZEN = 'afgewezen';

    public function label(): string
    {
        return match ($this) {
            self::NIEUW      => 'Nieuw',
            self::INGEPLAND  => 'Ingepland',
            self::VERSTUURD  => 'Verstuurd',
            self::AKKOORD    => 'Akkoord',
            self::AFGEWEZEN  => 'Afgewezen',
        };
    }
}
