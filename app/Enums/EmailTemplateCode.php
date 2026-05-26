<?php

namespace App\Enums;

/**
 * These templates are used in the code base.
 * So if you change them in the seeder..just change the value here to keep the same match
 */
enum EmailTemplateCode: string
{
    case PATIENT_PORTAL_NOTIFICATION = 'patient-portal-notification';
    case PATIENT_PORTAL_NOTIFICATION_NEW_CONTENT = 'patient-portal-notification-new-content';
    case CREATE_USER = 'create-user';
    case PATIENT_FORGOT_PASSWORD = 'patient-forgot-password';
    case CRM_FORGOT_PASSWORD = 'crm-forgot-password';
}
