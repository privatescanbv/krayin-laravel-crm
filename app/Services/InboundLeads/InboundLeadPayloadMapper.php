<?php

namespace App\Services\InboundLeads;

use App\Enums\PersonSalutation;

class InboundLeadPayloadMapper
{
    /**
     * Map payload from routes/privatescan_create_lead.json into the internal lead-create payload.
     */
    public function mapPrivatescan(array $payload): array
    {
        $description = $this->buildPrivatescanDescription($payload);

        return array_filter([
            'salutation'      => $this->mapSalutation($payload['salutation'] ?? null),
            'first_name'      => $this->nullIfEmpty($payload['first_name'] ?? null),
            'last_name'       => $this->nullIfEmpty($payload['last_name'] ?? null),
            'description'     => $description,
            'email'           => $this->nullIfEmpty($payload['email'] ?? null),
            'phone'           => $this->normalizePhone($payload['phone'] ?? null),
            'lead_source_id'  => $this->mapLeadSourceId($payload['lead_source'] ?? null),
            'lead_channel_id' => $this->mapLeadChannelId($payload['kanaal_c'] ?? null),
            'lead_type_id'    => $this->mapLeadTypeId($payload['soort_aanvraag_c'] ?? null),
        ], static fn ($v) => $v !== null);
    }

    /**
     * Map payload from routes/hernia_create_lead.json into the internal lead-create payload.
     */
    public function mapHernia(array $payload): array
    {
        $address = $this->mapHerniaAddress($payload);

        return array_filter([
            'salutation'         => $this->mapSalutation($payload['salutation'] ?? null),
            'first_name'         => $this->nullIfEmpty($payload['first_name'] ?? null),
            'last_name'          => $this->nullIfEmpty($payload['last_name'] ?? null),
            'date_of_birth'      => $this->nullIfEmpty($payload['birthdate'] ?? null),
            'description'        => $this->nullIfEmpty($payload['description'] ?? null),
            'email'              => $this->nullIfEmpty($payload['email1'] ?? null),
            'phone'              => $this->normalizePhone($payload['phone_mobile'] ?? null),
            'lead_source_id'     => $this->mapLeadSourceId($payload['lead_source'] ?? null),
            'lead_channel_id'    => $this->mapLeadChannelId($payload['kanaal_c'] ?? null),
            'lead_type_id'       => $this->mapLeadTypeId($payload['soort_aanvraag_c'] ?? null),
            'has_diagnosis_form' => array_key_exists('birthdate', $payload) && $this->nullIfEmpty($payload['birthdate']) !== null,
            'address'            => $address ?: null,
        ], static fn ($v) => $v !== null);
    }

    private function mapHerniaAddress(array $payload): array
    {
        $houseNumber = $this->nullIfEmpty($payload['primary_huisnr_c'] ?? null);
        $postalCode = $this->nullIfEmpty($payload['primary_address_postalcode'] ?? null);

        // AddressRepository will ignore partial addresses; only pass when meaningful.
        if ($houseNumber === null || $postalCode === null) {
            return [];
        }

        return [
            'house_number'        => $houseNumber,
            'house_number_suffix' => $this->nullIfEmpty($payload['primary_huisnr_toevoeging_c'] ?? null),
            'postal_code'         => $postalCode,
        ];
    }

    private function buildPrivatescanDescription(array $payload): ?string
    {
        $parts = [];

        $append = function (string $label, mixed $value) use (&$parts): void {
            if ($value === null || $value === false) {
                return;
            }
            $value = trim((string) $value);
            if ($value === '') {
                return;
            }
            $parts[] = "{$label}: {$value}";
        };

        // Prefer explicit description, but enrich it with structured fields if present.
        $base = $this->nullIfEmpty($payload['description'] ?? null);
        if ($base !== null) {
            $parts[] = $base;
        }

        $append('URL', $payload['url'] ?? null);
        $append('Section', $payload['section'] ?? null);
        $append('Verzoek', $payload['select_verzoek'] ?? null);
        $append('Interesse', $payload['select_interesse'] ?? null);
        $append('Personen', $payload['personen'] ?? null);
        $append('Campaign ID', $payload['campaign_id'] ?? null);

        if (empty($parts)) {
            return null;
        }

        return implode("\n", $parts);
    }

    private function mapSalutation(mixed $value): ?string
    {
        if ($value === null || $value === false) {
            return null;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            'mr.', 'dhr', 'dhr.' => PersonSalutation::Dhr->value,
            'mrs.', 'mevr', 'mevr.' => PersonSalutation::Mevr->value,
            ''      => null,
            default => null,
        };
    }

    /**
     * Map inbound lead_source strings to lead_sources.id.
     *
     * Accepts both "SugarCRM key" variants (eg privatescannl) and label variants (eg privatescan.nl).
     */
    private function mapLeadSourceId(?string $leadSource): int
    {
        $leadSourceLower = strtolower(trim((string) $leadSource));

        $sourceMap = [
            // Key variants (from older implementations/cookies)
            'bodyscannl'     => 1,
            'privatescannl'  => 2,
            'mriscannl'      => 3,
            'ccsvionlinenl'  => 4,
            'ccsvionlinecom' => 5,

            // Label variants (from DB seed + Sugar import mapping)
            'bodyscan.nl'                  => 1,
            'privatescan.nl'               => 2,
            'mri-scan.nl'                  => 3,
            'ccsvi-online.nl'              => 4,
            'ccsvi-online.com'             => 5,
            'google zoeken'                => 6,
            'adwords'                      => 7,
            'krant telegraaf'              => 8,
            'krant spits'                  => 9,
            'krant regionaal'              => 10,
            'krant overige dagbladen'      => 11,
            'krant redactioneel'           => 12,
            'magazine dito'                => 13,
            'magazine humo belgie'         => 14,
            'dokterdokter.nl'              => 15,
            'vrouw.nl'                     => 16,
            'dito-magazine.nl'             => 17,
            'groupdeal.nl'                 => 18,
            'marktplaats'                  => 19,
            'zorgplanet.nl'                => 20,
            'linkpartner'                  => 21,
            'youtube'                      => 22,
            'linkedin'                     => 23,
            'twitter'                      => 24,
            'facebook'                     => 25,
            'rtl business class'           => 26,
            'nieuwsbrief'                  => 27,
            'bestaande klant'              => 28,
            'zakenrelatie'                 => 29,
            'vrienden, familie, kennissen' => 30,
            'collega'                      => 31,
            'anders'                       => 32,
            'wegener webshop'              => 33,
            'herniapoli.nl'                => 34,
        ];

        if (isset($sourceMap[$leadSourceLower])) {
            return $sourceMap[$leadSourceLower];
        }

        // Default: Anders
        return 32;
    }

    private function mapLeadChannelId(?string $kanaal): int
    {
        $kanaalLower = strtolower(trim((string) $kanaal));
        $kanaalLower = str_replace('socialmedia', 'social media', $kanaalLower);
        $kanaalLower = str_replace('e-mail', 'email', $kanaalLower);

        $channelMap = [
            'telefoon'     => 1,     // Telefoon
            'website'      => 2,      // Website
            'email'        => 3,        // E-mail
            'tel-en-tel'   => 4,   // Tel-en-Tel
            'agenten'      => 5,      // Agenten
            'partners'     => 6,     // Partners
            'social media' => 7, // Social media
            'webshop'      => 8,      // Webshop
            'campagne'     => 9,     // Campagne
        ];

        return $channelMap[$kanaalLower] ?? 2;
    }

    private function mapLeadTypeId(?string $type): int
    {
        $typeLower = strtolower(trim((string) $type));

        $typeMap = [
            'preventie' => 1,
            'gericht'   => 2,
            'operatie'  => 3,
            'overig'    => 4,
        ];

        return $typeMap[$typeLower] ?? 4;
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        if ($value === null || $value === false) {
            return null;
        }

        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    /**
     * Normalize common Dutch phone inputs (eg "0612345678") to E.164 (eg "+31612345678").
     * Keeps existing "+..." as-is (minus whitespace/punctuation).
     */
    private function normalizePhone(mixed $value): ?string
    {
        $raw = $this->nullIfEmpty($value);
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);

        // Preserve leading +, strip the rest to digits
        if (str_starts_with($raw, '+')) {
            $digits = preg_replace('/[^0-9]/', '', $raw);

            return $digits ? ('+'.$digits) : null;
        }

        // Strip non-digits
        $digits = preg_replace('/[^0-9]/', '', $raw);
        if (! $digits) {
            return null;
        }

        // Convert "06..." → "+316..."
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+31'.substr($digits, 1);
        }

        // Convert "31..." → "+31..."
        if (str_starts_with($digits, '31')) {
            return '+'.$digits;
        }

        // Fallback: return as "+<digits>" to satisfy PhoneValidator formatting expectations.
        return '+'.$digits;
    }
}
