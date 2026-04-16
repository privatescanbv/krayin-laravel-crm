<?php

namespace App\Enums;

enum EmailTemplateCode: string
{
    case ACTIVITY_CREATED = 'activity-created';
    case ACTIVITY_MODIFIED = 'activity-modified';
    case REPLY = 'reply';
    case REPLY_DE = 'reply-de';
    case REPLY_EN = 'reply-en';
    case APPOINTMENT_CONFIRMATION = 'appointment-confirmation';
    case INFORMATIEF_MET_GVL = 'informatief-met-gvl';
    case PATIENT_PORTAL_NOTIFICATION = 'patient-portal-notification';
    case CREATE_USER = 'create-user';
    case ACKNOWLEDGE_ORDER_MAIL = 'acknowledge-order-mail';
}
