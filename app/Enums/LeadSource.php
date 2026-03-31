<?php

namespace App\Enums;

/**
 * Canonical lead source: {@see self::value} matches `lead_sources.id`.
 * Inbound strings (API keys, domain labels, SugarCRM) map via {@see self::idFromInbound()}.
 */
enum LeadSource: int
{
    case BodyscanNl = 1;
    case PrivatescanNl = 2;
    case MriScanNl = 3;
    case CcsviOnlineNl = 4;
    case CcsviOnlineCom = 5;
    case GoogleZoeken = 6;
    case Adwords = 7;
    case KrantTelegraaf = 8;
    case KrantSpits = 9;
    case KrantRegionaal = 10;
    case KrantOverigeDagbladen = 11;
    case KrantRedactioneel = 12;
    case MagazineDito = 13;
    case MagazineHumoBelgie = 14;
    case DokterdokterNl = 15;
    case VrouwNl = 16;
    case DitoMagazineNl = 17;
    case GroupdealNl = 18;
    case Marktplaats = 19;
    case ZorgplanetNl = 20;
    case Linkpartner = 21;
    case Youtube = 22;
    case Linkedin = 23;
    case Twitter = 24;
    case Facebook = 25;
    case RtlBusinessClass = 26;
    case Nieuwsbrief = 27;
    case BestaandeKlant = 28;
    case Zakenrelatie = 29;
    case VriendenFamilieKennissen = 30;
    case Collega = 31;
    case Anders = 32;
    case WegenerWebshop = 33;
    case HerniaPoliNl = 34;

    public static function tryFromInbound(?string $leadSource): ?self
    {
        $key = strtolower(trim((string) $leadSource));
        if ($key === '') {
            return null;
        }

        return self::inboundLookupMap()[$key] ?? null;
    }

    /**
     * Map inbound lead_source string to `lead_sources.id`. Unmatched or empty → Anders.
     */
    public static function idFromInbound(?string $leadSource): int
    {
        return self::tryFromInbound($leadSource)?->value ?? self::Anders->value;
    }

    /**
     * @return array<string, self>
     */
    private static function inboundLookupMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (self::cases() as $case) {
                foreach ($case->inboundKeys() as $key) {
                    $map[$key] = $case;
                }
            }
        }

        return $map;
    }

    /**
     * Lowercase inbound keys (Sugar labels + legacy cookie/API keys) for this source id.
     *
     * @return list<string>
     */
    public function inboundKeys(): array
    {
        return match ($this) {
            self::BodyscanNl               => ['bodyscannl', 'bodyscan.nl'],
            self::PrivatescanNl            => ['privatescannl', 'privatescan.nl'],
            self::MriScanNl                => ['mriscannl', 'mri-scan.nl'],
            self::CcsviOnlineNl            => ['ccsvionlinenl', 'ccsvi-online.nl'],
            self::CcsviOnlineCom           => ['ccsvionlinecom', 'ccsvi-online.com'],
            self::GoogleZoeken             => ['google zoeken'],
            self::Adwords                  => ['adwords'],
            self::KrantTelegraaf           => ['krant telegraaf'],
            self::KrantSpits               => ['krant spits'],
            self::KrantRegionaal           => ['krant regionaal'],
            self::KrantOverigeDagbladen    => ['krant overige dagbladen'],
            self::KrantRedactioneel        => ['krant redactioneel'],
            self::MagazineDito             => ['magazine dito'],
            self::MagazineHumoBelgie       => ['magazine humo belgie'],
            self::DokterdokterNl           => ['dokterdokter.nl'],
            self::VrouwNl                  => ['vrouw.nl'],
            self::DitoMagazineNl           => ['dito-magazine.nl'],
            self::GroupdealNl              => ['groupdeal.nl'],
            self::Marktplaats              => ['marktplaats'],
            self::ZorgplanetNl             => ['zorgplanet.nl'],
            self::Linkpartner              => ['linkpartner'],
            self::Youtube                  => ['youtube'],
            self::Linkedin                 => ['linkedin'],
            self::Twitter                  => ['twitter'],
            self::Facebook                 => ['facebook'],
            self::RtlBusinessClass         => ['rtl business class'],
            self::Nieuwsbrief              => ['nieuwsbrief'],
            self::BestaandeKlant           => ['bestaande klant'],
            self::Zakenrelatie             => ['zakenrelatie'],
            self::VriendenFamilieKennissen => ['vrienden, familie, kennissen'],
            self::Collega                  => ['collega'],
            self::Anders                   => ['anders'],
            self::WegenerWebshop           => ['wegener webshop'],
            self::HerniaPoliNl             => ['herniapoli.nl'],
        };
    }

    public function databaseName(): string
    {
        return match ($this) {
            self::BodyscanNl               => 'bodyscan.nl',
            self::PrivatescanNl            => 'privatescan.nl',
            self::MriScanNl                => 'mri-scan.nl',
            self::CcsviOnlineNl            => 'ccsvi-online.nl',
            self::CcsviOnlineCom           => 'ccsvi-online.com',
            self::GoogleZoeken             => 'Google zoeken',
            self::Adwords                  => 'Adwords',
            self::KrantTelegraaf           => 'Krant Telegraaf',
            self::KrantSpits               => 'Krant Spits',
            self::KrantRegionaal           => 'Krant regionaal',
            self::KrantOverigeDagbladen    => 'Krant overige dagbladen',
            self::KrantRedactioneel        => 'Krant redactioneel',
            self::MagazineDito             => 'Magazine Dito',
            self::MagazineHumoBelgie       => 'Magazine Humo België',
            self::DokterdokterNl           => 'dokterdokter.nl',
            self::VrouwNl                  => 'vrouw.nl',
            self::DitoMagazineNl           => 'dito-magazine.nl',
            self::GroupdealNl              => 'groupdeal.nl',
            self::Marktplaats              => 'Marktplaats',
            self::ZorgplanetNl             => 'zorgplanet.nl',
            self::Linkpartner              => 'Linkpartner',
            self::Youtube                  => 'YouTube',
            self::Linkedin                 => 'LinkedIn',
            self::Twitter                  => 'Twitter',
            self::Facebook                 => 'Facebook',
            self::RtlBusinessClass         => 'RTL Business Class',
            self::Nieuwsbrief              => 'Nieuwsbrief',
            self::BestaandeKlant           => 'Bestaande klant',
            self::Zakenrelatie             => 'Zakenrelatie',
            self::VriendenFamilieKennissen => 'Vrienden, familie, kennissen',
            self::Collega                  => 'Collega',
            self::Anders                   => 'Anders',
            self::WegenerWebshop           => 'Wegener webshop',
            self::HerniaPoliNl             => 'Herniapoli.nl',
        };
    }
}
