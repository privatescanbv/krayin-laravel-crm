<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case NIEUW = 'nieuw';
    case MOET_WORDEN_INGEPLAND = 'moet_worden_ingepland';
    case INGEPLAND = 'ingepland';

    public function label(): string
    {
        return match ($this) {
            self::NIEUW                   => 'Nieuw',
            self::MOET_WORDEN_INGEPLAND   => 'Moet worden ingepland',
            self::INGEPLAND               => 'Ingepland',
        };
    }
}
