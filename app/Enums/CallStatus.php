<?php

namespace App\Enums;

enum CallStatus: string
{
    case NOT_REACHABLE = 'not_reachable';
    case VOICEMAIL_LEFT = 'voicemail_left';
    case SPOKEN = 'spoken';

    public function label(): string
    {
        return match($this) {
            self::NOT_REACHABLE => 'Niet kunnen bereiken',
            self::VOICEMAIL_LEFT => 'Voicemail ingesproken',
            self::SPOKEN => 'Gesproken',
        };
    }
}

