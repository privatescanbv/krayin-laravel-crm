<?php

namespace App\Enums;

enum CallStatus: string
{
    case NOT_REACHABLE = 'not_reachable';
    case VOICEMAIL_LEFT = 'voicemail_left';
    case SPOKEN = 'spoken';
    case WORDT_TERUGGEBELD = 'wordt_teruggebeld';
    case AFSPRAAK_GEMAAKT = 'afspraak_gemaakt';

    public function label(): string
    {
        return match ($this) {
            self::NOT_REACHABLE     => 'Niet kunnen bereiken',
            self::VOICEMAIL_LEFT    => 'Voicemail ingesproken',
            self::SPOKEN            => 'Bereikt',
            self::WORDT_TERUGGEBELD => 'Wordt teruggebeld',
            self::AFSPRAAK_GEMAAKT  => 'Afspraak gemaakt',
        };
    }

    /** Tailwind text-color class used in history items */
    public function colorClass(): string
    {
        return match ($this) {
            self::NOT_REACHABLE     => 'text-red-600',
            self::VOICEMAIL_LEFT    => 'text-blue-600',
            self::SPOKEN            => 'text-green-700',
            self::WORDT_TERUGGEBELD => 'text-amber-600',
            self::AFSPRAAK_GEMAAKT  => 'text-teal-700',
        };
    }

    /** Tailwind bg-color class used for the icon dot */
    public function bgClass(): string
    {
        return match ($this) {
            self::NOT_REACHABLE     => 'bg-red-100',
            self::VOICEMAIL_LEFT    => 'bg-blue-100',
            self::SPOKEN            => 'bg-green-100',
            self::WORDT_TERUGGEBELD => 'bg-amber-100',
            self::AFSPRAAK_GEMAAKT  => 'bg-teal-100',
        };
    }

    /** Icon name in the icon font used by this project */
    public function icon(): string
    {
        return match ($this) {
            self::NOT_REACHABLE     => 'icon-phone-off',
            self::VOICEMAIL_LEFT    => 'icon-voicemail',
            self::SPOKEN            => 'icon-phone',
            self::WORDT_TERUGGEBELD => 'icon-phone-incoming',
            self::AFSPRAAK_GEMAAKT  => 'icon-calendar',
        };
    }
}
