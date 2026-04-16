<?php

namespace App\Enums;

/**
 * These templates are used in the code base.
 * So if you change them in the seeder..just change the value here to keep the same match
 */
enum EmailTemplateCode: string
{
    case ACTIVITY_CREATED = 'activity-created';
    case ACTIVITY_MODIFIED = 'activity-modified';
    case REPLY = 'reply';
    case REPLY_DE = 'reply-de';
    case REPLY_EN = 'reply-en';
    case APPOINTMENT_CONFIRMATION = 'appointment-confirmation';
    case INFORMATIEF_MET_GVL = 'informatief-met-gvl';
    // use when user gets a new patient portal account
    case PATIENT_PORTAL_NOTIFICATION = 'patient-portal-notification';
    case PATIENT_PORTAL_NOTIFICATION_NEW_CONTENT = 'patient-portal-notification-new-content';
    case CREATE_USER = 'create-user';
    case ACKNOWLEDGE_ORDER_MAIL = 'acknowledge-order-mail';
}
