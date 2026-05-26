<?php

namespace App\Enums\Inkoop;

enum InkoopSupplierType: string
{
    case RADIO = 'radiology';
    case CARDIO = 'cardiology';
    case OTHER = 'other';
    case CLINIC = 'clinic';
}
