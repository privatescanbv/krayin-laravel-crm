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

    public function getStatusClass(): string
    {
        return match ($this) {
            self::NIEUW      => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
            self::INGEPLAND  => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
            self::VERSTUURD  => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            self::AKKOORD    => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            self::AFGEWEZEN  => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        };
    }
}
