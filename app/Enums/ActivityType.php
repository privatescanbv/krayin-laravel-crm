<?php

namespace App\Enums;

enum ActivityType: string
{
    case CALL = 'call';
    case MEETING = 'meeting';
    case TASK = 'task';
}

