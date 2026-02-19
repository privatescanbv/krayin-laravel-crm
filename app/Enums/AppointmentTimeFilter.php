<?php

namespace App\Enums;

enum AppointmentTimeFilter: string
{
    case FUTURE = 'future';
    case PAST = 'past';
}
