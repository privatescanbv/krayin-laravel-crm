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
                $values[] = \App\Helpers\ValueNormalizer::toString($item['value']);
            } elseif (is_string($item)) {
                $values[] = $item;
            } else {
                // Use ValueNormalizer for other types
                $normalized = \App\Helpers\ValueNormalizer::toString($item);
                if (! empty($normalized)) {
                    $values[] = $normalized;
                }
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

    private function getNameVariations(string $name): array
    {
        $variations = [$name];
        $nicknameMap = [
            'John'        => ['Johnny', 'Jon', 'Jack'],
            'Johnny'      => ['John', 'Jon'],
            'Jon'         => ['John', 'Johnny'],
            'Jack'        => ['John', 'Jackson'],
            'William'     => ['Will', 'Bill', 'Billy'],
            'Will'        => ['William', 'Bill'],
            'Bill'        => ['William', 'Will', 'Billy'],
            'Billy'       => ['William', 'Bill'],
            'Robert'      => ['Bob', 'Rob', 'Bobby'],
            'Bob'         => ['Robert', 'Bobby'],
            'Rob'         => ['Robert', 'Bobby'],
            'Bobby'       => ['Robert', 'Bob'],
            'Richard'     => ['Rick', 'Dick', 'Rich'],
            'Rick'        => ['Richard', 'Rich'],
            'Rich'        => ['Richard', 'Rick'],
            'Michael'     => ['Mike', 'Mickey'],
            'Mike'        => ['Michael', 'Mickey'],
            'Mickey'      => ['Michael', 'Mike'],
            'David'       => ['Dave', 'Davey'],
            'Dave'        => ['David', 'Davey'],
            'Davey'       => ['David', 'Dave'],
            'Christopher' => ['Chris', 'Christie'],
            'Chris'       => ['Christopher', 'Christie'],
            'Christie'    => ['Christopher', 'Chris'],
            'Elizabeth'   => ['Liz', 'Beth', 'Betty', 'Lizzy'],
            'Liz'         => ['Elizabeth', 'Beth', 'Betty'],
            'Beth'        => ['Elizabeth', 'Liz', 'Betty'],
            'Betty'       => ['Elizabeth', 'Liz', 'Beth'],
            'Lizzy'       => ['Elizabeth', 'Liz'],
        ];
        if (isset($nicknameMap[$name])) {
            $variations = array_merge($variations, $nicknameMap[$name]);
        }

        return array_values(array_unique(array_filter($variations)));
    }

    /**
     * Compute reasons between two leads provided as arrays (LeadResource output).
     */
    private function computeReasons(array $primary, array $dup, array $primaryEmails, array $primaryPhones): array
    {
        $dupEmails = $this->extractValues($dup['emails'] ?? []);
        $dupPhones = $this->extractValues($dup['phones'] ?? []);

        $emailMatches = array_values(array_intersect(
            array_map('strtolower', $primaryEmails),
            array_map('strtolower', $dupEmails)
        ));

        $pPhonesNorm = array_map(fn ($p) => $this->normalizePhone($p), $primaryPhones);
        $dPhonesNorm = array_map(fn ($p) => $this->normalizePhone($p), $dupPhones);
        $phoneMatches = array_values(array_filter($pPhonesNorm, fn ($p) => in_array($p, $dPhonesNorm, true)));

        // Name reason
        $nameReason = null;
        $leadFull = strtolower(trim(($primary['first_name'] ?? '').' '.($primary['last_name'] ?? '')));
        $dupFull = strtolower(trim(($dup['first_name'] ?? '').' '.($dup['last_name'] ?? '')));
        if ($leadFull && $dupFull && $leadFull === $dupFull) {
            $nameReason = 'first+last exact';
        } elseif (! empty($primary['married_name'])) {
            $swap1 = strtolower(trim(($primary['first_name'] ?? '').' '.($primary['married_name'] ?? '')));
            if ($dupFull === $swap1) {
                $nameReason = 'married/last swap';
            }
        } else {
            // nickname variants for first name
            $first = (string) ($primary['first_name'] ?? '');
            foreach ($this->getNameVariations($first) as $variant) {
                $variantFull = strtolower(trim($variant.' '.($primary['last_name'] ?? '')));
                if ($variantFull && $variantFull === $dupFull) {
                    $nameReason = 'nickname variant ('.$variant.')';
                    break;
                }
            }
        }

        return [
            'email'       => $emailMatches,
            'phone'       => $phoneMatches,
            'name_reason' => $nameReason,
        ];
    }
}
