<?php

namespace App\Enums;

enum PersonAttributeKeys: string
{
    case LAST_NAME = 'person_last_name';
    case FIRST_NAME = 'person_first_name';
    case NICKNAME = 'person_nickname';
    case LAST_NAME_PREFIX = 'person_last_name_prefix';
    case INITIALS = 'person_initials';
    case GENDER = 'person_gender';
    case MAIDEN_NAME = 'person_maiden_name';
    case MAIDEN_NAME_PREFIX = 'person_maiden_name_prefix';
    case BIRTH_DATE = 'person_birth_date';
    case STREET = 'person_street';
    case HOUSE_NUMBER = 'person_house_number';
    case POSTAL_CODE = 'person_postal_code';
    case CITY = 'person_city';
    case PROVINCE = 'person_province';
    case COUNTRY = 'person_country';
    case EMAILS = 'emails';
    case CONTACT_NUMBERS = 'person_contact_numbers';
    case JOB_TITLE = 'person_job_title';
    case USER_ID = 'user_id';
    case ORGANIZATION_ID = 'organization_id';

    public function label(): string
    {
        return match ($this) {
            self::LAST_NAME          => 'Last Name',
            self::FIRST_NAME         => 'First Name',
            self::NICKNAME           => 'Nickname',
            self::LAST_NAME_PREFIX   => 'Last Name Prefix',
            self::INITIALS           => 'Initials',
            self::GENDER             => 'Gender',
            self::MAIDEN_NAME        => 'Maiden Name',
            self::MAIDEN_NAME_PREFIX => 'Maiden Name Prefix',
            self::BIRTH_DATE         => 'Birth Date',
            self::STREET             => 'Street',
            self::HOUSE_NUMBER       => 'House Number',
            self::POSTAL_CODE        => 'Postal Code',
            self::CITY               => 'City',
            self::PROVINCE           => 'Province',
            self::COUNTRY            => 'Country',
            self::EMAILS             => 'Emails',
            self::CONTACT_NUMBERS    => 'Contact Numbers',
            self::JOB_TITLE          => 'Job Title',
            self::USER_ID            => 'User ID',
            self::ORGANIZATION_ID    => 'Organization ID',
        };
    }
}
