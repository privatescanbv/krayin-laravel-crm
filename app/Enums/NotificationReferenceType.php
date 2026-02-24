<?php

namespace App\Enums;

enum NotificationReferenceType: string
{
    case FILE = 'activity';
    case GVL_FORM = 'gvl_form';
}
