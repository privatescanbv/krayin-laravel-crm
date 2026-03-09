<?php

namespace App\Enums;

enum EntityType: string
{
    case LEAD = 'lead';
    case SALES = 'sales';
    case ORDER = 'order';
    case CLINIC = 'clinic';
    case PERSON = 'person';

    public static function haveActivities(): array
    {
        return [
            self::LEAD,
            self::SALES,
            self::ORDER,
            self::CLINIC,
        ];
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::LEAD   => 'Lead',
            self::SALES  => 'Sales',
            self::ORDER  => 'Order',
            self::CLINIC => 'kliniek',
            self::PERSON => 'Persoon',
        };
    }
}
