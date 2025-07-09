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
    case CONTACT_NUMBERS = 'person_contact_numbers';
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
            self::CONTACT_NUMBERS    => 'Contact Numbers',
            self::USER_ID            => 'User ID',
            self::ORGANIZATION_ID    => 'Organization ID',
        };
    }
}
