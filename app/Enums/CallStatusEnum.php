<?php

namespace App\Enums;

enum CallStatusEnum: string
{
    case NOT_REACHABLE = 'not_reachable';
    case VOICEMAIL_LEFT = 'voicemail_left';
    case SPOKEN = 'spoken';
}

