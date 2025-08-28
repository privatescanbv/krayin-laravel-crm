<?php

namespace App\Services;

trait DuplicateReasonHelpers
{
    /**
     * Extract values from a JSON field or array.
     */
    private function extractValues($field): array
    {
        if (is_string($field)) {
            $decoded = json_decode($field, true) ?: [];
        } elseif (is_array($field)) {
            $decoded = $field;
        } else {
            $decoded = [];
        }

        $values = [];
        foreach ($decoded as $item) {
            if (is_array($item) && ! empty($item['value'])) {
                $values[] = (string) $item['value'];
            } elseif (is_string($item)) {
                $values[] = $item;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * Normalize phone number for comparison.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($digits, '31') && strlen($digits) >= 10) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    /**
     * Compute duplicate reasons for leads.
     */
    private function computeReasons($primary, $duplicate, array $primaryEmails, array $primaryPhones): array
    {
        $duplicateEmails = $this->extractValues($duplicate->emails ?? []);
        $duplicatePhones = $this->extractValues($duplicate->phones ?? []);

        $emailMatches = array_values(array_intersect(
            array_map('strtolower', $primaryEmails),
            array_map('strtolower', $duplicateEmails)
        ));

        $primaryPhonesNorm = array_map(fn ($p) => $this->normalizePhone($p), $primaryPhones);
        $duplicatePhonesNorm = array_map(fn ($p) => $this->normalizePhone($p), $duplicatePhones);
        $phoneMatches = array_values(array_filter($primaryPhonesNorm, fn ($p) => in_array($p, $duplicatePhonesNorm, true)));

        // Name reasons (exact, nickname-variant, married/last swap)
        $nameReason = null;
        $primaryFull = strtolower(trim(($primary->first_name ?? '').' '.($primary->last_name ?? '')));
        $duplicateFull = strtolower(trim(($duplicate->first_name ?? '').' '.($duplicate->last_name ?? '')));
        
        if ($primaryFull && $duplicateFull && $primaryFull === $duplicateFull) {
            $nameReason = 'first+last exact';
        } elseif (! empty($primary->married_name ?? '')) {
            $marriedSwap1 = strtolower(trim(($primary->first_name ?? '').' '.($primary->married_name ?? '')));
            $marriedSwap2 = strtolower(trim(($primary->first_name ?? '').' '.($primary->last_name ?? '')));
            if ($duplicateFull === $marriedSwap1 || $duplicateFull === $marriedSwap2) {
                $nameReason = 'married/last swap';
            }
        } else {
            // nickname variations for first name
            $first = (string) ($primary->first_name ?? '');
            $variants = $this->getNameVariations($first);
            if (! empty($variants)) {
                foreach ($variants as $variant) {
                    $variantFull = strtolower(trim($variant.' '.($primary->last_name ?? '')));
                    if ($variantFull && $variantFull === $duplicateFull) {
                        $nameReason = 'nickname variant ('.$variant.')';
                        break;
                    }
                }
            }
        }

        return [
            'email'       => $emailMatches,
            'phone'       => $phoneMatches,
            'name_reason' => $nameReason,
        ];
    }

    /**
     * Get name variations for nickname matching.
     */
    private function getNameVariations(string $name): array
    {
        $variations = [$name];
        $nicknameMap = [
            'alexander' => ['alex', 'sander', 'lex'],
            'alexandra' => ['alex', 'sandra', 'alexa'],
            'anthony' => ['tony', 'anton'],
            'antonio' => ['tony', 'anton'],
            'barbara' => ['barb', 'babs'],
            'benjamin' => ['ben', 'benny'],
            'catherine' => ['kate', 'katie', 'cathy', 'cat'],
            'christopher' => ['chris', 'christie'],
            'daniel' => ['dan', 'danny'],
            'david' => ['dave', 'davy'],
            'elizabeth' => ['liz', 'beth', 'betty', 'eliza'],
            'emily' => ['em', 'emmy'],
            'gregory' => ['greg', 'greggy'],
            'jennifer' => ['jen', 'jenny', 'jenna'],
            'jessica' => ['jess', 'jessie'],
            'jonathan' => ['jon', 'johnny', 'nathan'],
            'joseph' => ['joe', 'joey'],
            'joshua' => ['josh'],
            'katherine' => ['kate', 'katie', 'kathy', 'kat'],
            'margaret' => ['maggie', 'meg', 'peggy'],
            'matthew' => ['matt', 'matty'],
            'michael' => ['mike', 'mickey', 'mick'],
            'michelle' => ['mike', 'mickey', 'mick'], // sometimes used
            'nicholas' => ['nick', 'nicky'],
            'patricia' => ['pat', 'patty', 'tricia'],
            'richard' => ['rick', 'ricky', 'dick'],
            'robert' => ['rob', 'bob', 'bobby', 'robbie'],
            'samuel' => ['sam', 'sammy'],
            'stephanie' => ['steph', 'steffi'],
            'thomas' => ['tom', 'tommy', 'thom'],
            'timothy' => ['tim', 'timmy'],
            'william' => ['will', 'bill', 'billy', 'willie'],
            // Dutch names
            'johannes' => ['jan', 'johan', 'hans'],
            'wilhelmus' => ['wim', 'willem', 'will'],
            'franciscus' => ['frans', 'frank'],
            'antonius' => ['ton', 'anton', 'antoon'],
            'jacobus' => ['jaap', 'koos', 'jack'],
            'theodorus' => ['theo', 'dorus'],
            'henricus' => ['henk', 'harry', 'hendrik'],
            'petrus' => ['piet', 'peter'],
            'cornelis' => ['kees', 'cor', 'cees'],
            'adrianus' => ['arie', 'adriaan'],
        ];

        $lowerName = strtolower($name);
        if (isset($nicknameMap[$lowerName])) {
            $variations = array_merge($variations, $nicknameMap[$lowerName]);
        }

        // Check if the name is a nickname for something else
        foreach ($nicknameMap as $fullName => $nicknames) {
            if (in_array($lowerName, $nicknames, true)) {
                $variations[] = $fullName;
                $variations = array_merge($variations, $nicknames);
                break;
            }
        }

        return array_unique($variations);
    }
}