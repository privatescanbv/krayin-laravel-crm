<?php

namespace App\Enums;

enum LostReason: string
{
    case NoMRI = 'geenMRI';
    case Waste = 'afval';
    case Price = 'prijs';
    case NoInsuranceCoverage = 'geen_vergoeding_zorgv';
    case Distance = 'afstand';
    case Informative = 'informatief';
    case Prescan = 'prescan';
    case Sick = 'ziek';
    case Competitor = 'concurrent';
    case NotSchedulable = 'niet_planbaar';
    case NoTransport = 'geen_vervoer';
    case PartnerDisagrees = 'partner_niet';
    case NotFeasible = 'niet_uitvoerbaar';
    case NegativeAdvice = 'negatief_advies';
    case NoResponse = 'geen_reactie';
    case DoesNotPay = 'betaalt_niet';
    case Overslept = 'verslapen';
    case TooLate = 'te_laat';
    case Fear = 'angst';
    case Dissatisfied = 'ontevreden';
    case StandardCareNL = 'elders_nl_standaard';
    case PostponedCircumstances = 'uitstel_omstandigheden';
    case NewsletterOnly = 'nieuwsbrief';
    case Incorrect = 'foutief';
    case DataEntry = 'datainvoer';
    case NoReason = 'geen_reden';
    case NotMatching = 'Spoort_niet';

    public function label(): string
    {
        return match ($this) {
            self::NoMRI                  => '(nog) geen MRI',
            self::Waste                  => 'Afval',
            self::Price                  => 'Prijs',
            self::NoInsuranceCoverage    => 'Geen vergoeding verzekeraar',
            self::Distance               => 'Afstand',
            self::Informative            => 'Puur informatief',
            self::Prescan                => 'Naar Prescan',
            self::Sick                   => 'Ziek',
            self::Competitor             => 'Naar Concurrent',
            self::NotSchedulable         => 'Niet planbaar',
            self::NoTransport            => 'Geen vervoer',
            self::PartnerDisagrees       => 'Partner niet akkoord',
            self::NotFeasible            => 'Niet uitvoerbaar',
            self::NegativeAdvice         => 'Negatief advies Privatescan',
            self::NoResponse             => 'Geen reactie meer',
            self::DoesNotPay             => 'Betaalt niet',
            self::Overslept              => 'Verslapen',
            self::TooLate                => 'Te laat',
            self::Fear                   => 'Angst',
            self::Dissatisfied           => 'Ontevreden',
            self::StandardCareNL         => 'Kan in NL zorg terecht',
            self::PostponedCircumstances => 'Uitstel door omstandigheden',
            self::NewsletterOnly         => 'Alleen nieuwsbriefinschrijving',
            self::Incorrect              => 'Foutief',
            self::DataEntry              => 'Data invoer achteraf',
            self::NoReason               => 'Geen reden',
            self::NotMatching            => 'Spoort niet',
        };
    }
}
