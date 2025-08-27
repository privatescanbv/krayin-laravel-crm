<?php

namespace App\Enums;

enum PersonGender: string
{
    case Man = 'Man';
    case Female = 'Vrouw';
    case Other = 'Anders';

    public function label(): string
    {
        return match ($this) {
            self::Man     => __('Man'),
            self::Female  => __('Vrouw'),
            self::Other   => __('Anders'),
        };
    }
}
