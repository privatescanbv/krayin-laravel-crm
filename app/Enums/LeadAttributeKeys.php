<?php

namespace App\Enums;

enum LeadAttributeKeys: string
{
    case DEPARTMENT = 'department';
    case EMAIL = 'email';
    case PHONE = 'phone';
    case FIRSTNAME = 'first_name';
    case LASTNAME = 'last_name';

    //    public function label(): string
    //    {
    //        return match ($this) {
    //            self::DEPARTMENT => 'Department',
    //            self::EMAIL => 'Email',
    //            self::PHONE => 'Phone',
    //            self::FIRSTNAME => 'Firstname',
    //            self::LASTNAME => 'Lastname',
    //        };
    //    }
}
