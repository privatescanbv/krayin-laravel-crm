<?php

namespace App\Enums;

enum EntityType: string
{
    case LEAD = 'lead';
    case SALES = 'sales';
    case ORDER = 'order';

}
