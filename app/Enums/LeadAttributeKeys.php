<?php

namespace App\Enums;

enum LeadAttributeKeys: string
{
    case DEPARTMENT = 'department';

    public function label(): string
    {
        return match ($this) {
            self::DEPARTMENT => 'Department',
        };
    }
}
