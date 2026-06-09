<?php

namespace App\Enums;

enum CallStatus: string
{
    case NOT_REACHABLE = 'not_reachable';
    case VOICEMAIL_LEFT = 'voicemail_left';
    case SPOKEN = 'spoken';
    public function label(): string
    {
        return match ($this) {
            self::NOT_REACHABLE  => 'Niet kunnen bereiken',
            self::VOICEMAIL_LEFT => 'Voicemail ingesproken',
            self::SPOKEN         => 'Bereikt',
        };
    }

    /** Tailwind text-color class used in history items */
    public function colorClass(): string
    {
        return match ($this) {
            self::NOT_REACHABLE  => 'text-red-600',
            self::VOICEMAIL_LEFT => 'text-blue-600',
            self::SPOKEN         => 'text-green-700',
        };
    }

    /** Tailwind bg-color class used for the icon dot */
    public function bgClass(): string
    {
        return match ($this) {
            self::NOT_REACHABLE  => 'bg-red-100',
            self::VOICEMAIL_LEFT => 'bg-blue-100',
            self::SPOKEN         => 'bg-green-100',
        };
    }

    /** Icon name in the icon font used by this project */
    public function icon(): string
    {
        return match ($this) {
            self::NOT_REACHABLE  => 'icon-phone-off',
            self::VOICEMAIL_LEFT => 'icon-voicemail',
            self::SPOKEN         => 'icon-phone',
        };
    }
}
