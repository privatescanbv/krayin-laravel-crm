<?php

namespace App\Enums\Inkoop;

enum InkoopInvoiceStatus: string
{
    case OPEN = 'open';
    case PROCESSING = 'processing';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN       => 'Open',
            self::PROCESSING => 'Processing',
            self::CLOSED     => 'Gesloten',
        };
    }
}
