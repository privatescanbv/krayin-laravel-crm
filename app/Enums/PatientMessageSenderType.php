<?php

namespace App\Enums;

enum PatientMessageSenderType: string
{
    case PATIENT = 'patient';
    case STAFF = 'staff';
    case SYSTEM = 'system';
}
