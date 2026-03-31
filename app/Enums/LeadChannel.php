<?php

namespace App\Enums;

/**
 * Canonical lead channel: fixed {@see self::value} matches `lead_channels.id` (seed order).
 * Inbound strings (API, SugarCRM) map via {@see self::idFromInbound()}.
 */
enum LeadChannel: int
{
    case Telefoon = 1;
    case Website = 2;
    case Email = 3;
    case TelEnTel = 4;
    case Agenten = 5;
    case Partners = 6;
    case SocialMedia = 7;
    case Webshop = 8;
    case Campagne = 9;

    /**
     * Normalize inbound kanaal strings before lookup (API / imports).
     */
    public static function normalizeInboundInput(?string $kanaal): string
    {
        $kanaalLower = strtolower(trim((string) $kanaal));
        $kanaalLower = str_replace('socialmedia', 'social media', $kanaalLower);
        $kanaalLower = str_replace('e-mail', 'email', $kanaalLower);

        return $kanaalLower;
    }

    public static function tryFromInbound(?string $kanaal): ?self
    {
        $key = self::normalizeInboundInput($kanaal);
        if ($key === '') {
            return null;
        }

        return match ($key) {
            'telefoon'     => self::Telefoon,
            'website'      => self::Website,
            'email'        => self::Email,
            'tel-en-tel'   => self::TelEnTel,
            'agenten'      => self::Agenten,
            'partners'     => self::Partners,
            'social media' => self::SocialMedia,
            'webshop'      => self::Webshop,
            'campagne'     => self::Campagne,
            default        => null,
        };
    }

    /**
     * Map inbound kanaal string to `lead_channels.id`. Unmatched or empty → Website.
     */
    public static function idFromInbound(?string $kanaal): int
    {
        return self::tryFromInbound($kanaal)?->value ?? self::Website->value;
    }

    public function databaseName(): string
    {
        return match ($this) {
            self::Telefoon    => 'Telefoon',
            self::Website     => 'Website',
            self::Email       => 'E-mail',
            self::TelEnTel    => 'Tel-en-Tel',
            self::Agenten     => 'Agenten',
            self::Partners    => 'Partners',
            self::SocialMedia => 'Social media',
            self::Webshop     => 'Webshop',
            self::Campagne    => 'Campagne',
        };
    }
}
