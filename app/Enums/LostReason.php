<?php

namespace App\Enums;

enum LostReason: string
{
    case NoMRI = 'noMRI';
    case Waste = 'waste';
    case Price = 'price';
    case NoInsurance = 'noInsurance';
    case Distance = 'distance';
    case Info = 'info';
    case Prescan = 'prescan';
    case Ill = 'ill';
    case Competitor = 'competitor';
    case NotSchedulable = 'notSchedulable';
    case NoTransport = 'noTransport';
    case PartnerDeclined = 'partnerDeclined';
    case NotFeasible = 'notFeasible';
    case NegativeAdvice = 'negativeAdvice';
    case NoResponse = 'noResponse';
    case NoPay = 'noPay';
    case Overslept = 'overslept';
    case TooLate = 'tooLate';
    case Fear = 'fear';
    case Dissatisfied = 'dissatisfied';
    case AltNL = 'altNL';
    case PostponeCircumstances = 'postponeCircumstances';
    case NewsletterOnly = 'newsletterOnly';
    case Incorrect = 'incorrect';
    case DataEntry = 'dataEntry';
    case NoReason = 'noReason';
    case NotMatching = 'notMatching';

    public function label(): string
    {
        return match ($this) {
            self::NoMRI                 => '(nog) geen MRI',
            self::Waste                 => 'Afval',
            self::Price                 => 'Prijs',
            self::NoInsurance           => 'Geen vergoeding verzekeraar',
            self::Distance              => 'Afstand',
            self::Info                  => 'Puur informatief',
            self::Prescan               => 'Naar Prescan',
            self::Ill                   => 'Ziek',
            self::Competitor            => 'Naar Concurrent',
            self::NotSchedulable        => 'Niet planbaar',
            self::NoTransport           => 'Geen vervoer',
            self::PartnerDeclined       => 'Partner niet akkoord',
            self::NotFeasible           => 'Niet uitvoerbaar',
            self::NegativeAdvice        => 'Negatief advies Privatescan',
            self::NoResponse            => 'Geen reactie meer',
            self::NoPay                 => 'Betaalt niet',
            self::Overslept             => 'Verslapen',
            self::TooLate               => 'Te laat',
            self::Fear                  => 'Angst',
            self::Dissatisfied          => 'Ontevreden',
            self::AltNL                 => 'Kan in NL zorg terecht',
            self::PostponeCircumstances => 'Uitstel door omstandigheden',
            self::NewsletterOnly        => 'Alleen nieuwsbriefinschrijving',
            self::Incorrect             => 'Foutief',
            self::DataEntry             => 'Data invoer achteraf',
            self::NoReason              => 'Geen reden',
            self::NotMatching           => 'Spoort niet',
        };
    }
}
