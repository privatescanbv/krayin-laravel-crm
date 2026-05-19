<?php

namespace App\Enums;

enum StageCategory: string
{
    case OPTION = 'option';
    case NEARLY_WON = 'nearly_won';
    case WON = 'won';
    case LOST = 'lost';
}
