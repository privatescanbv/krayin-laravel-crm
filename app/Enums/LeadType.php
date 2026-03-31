<?php

namespace App\Enums;

/**
 * Canonical lead type: fixed {@see self::value} matches `lead_types.id` (seed order).
 * Inbound strings (API, SugarCRM soort_aanvraag_c) map via {@see self::idFromInbound()}.
 */
enum LeadType: int
{
    case Preventie = 1;
    case Gericht = 2;
    case Operatie = 3;
    case Overig = 4;

    /**
     * Normalize inbound strings before lookup (API / imports).
     */
    public static function normalizeInboundInput(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    public static function tryFromInbound(?string $value): ?self
    {
        $key = self::normalizeInboundInput($value);
        if ($key === '') {
            return null;
        }

        return match ($key) {
            'preventie' => self::Preventie,
            'gericht'   => self::Gericht,
            'operatie'  => self::Operatie,
            'overig'    => self::Overig,
            default     => null,
        };
    }

    /**
     * Map inbound type string to `lead_types.id`. Unmatched or empty → Overig.
     */
    public static function idFromInbound(?string $value): int
    {
        return self::tryFromInbound($value)?->value ?? self::Overig->value;
    }

    public function databaseName(): string
    {
        return match ($this) {
            self::Preventie => 'Preventie',
            self::Gericht   => 'Gericht',
            self::Operatie  => 'Operatie',
            self::Overig    => 'Overig',
        };
    }
}
