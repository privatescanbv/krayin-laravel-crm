<?php

namespace App\Enums;

enum AfbDispatchType: string
{
    case BATCH = 'batch';
    case INDIVIDUAL = 'individual';
}
