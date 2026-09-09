<?php

namespace App\Support;

final class NameSimilarity
{
    /**
     * Whether two given names should count as the same person for suggestions.
     *
     * Exact (accent-insensitive), shared tokens ("Anna Maria" / "Anna"),
     * or Levenshtein ≤ 2 on the first token (min. 3 characters).
     * Nicknames (Jan / Johannes) are intentionally not matched.
     */
    public static function firstNamesAreSimilar(?string $left, ?string $right): bool
    {
        $a = self::normalize($left);
        $b = self::normalize($right);

        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        $tokensA = self::tokens($a);
        $tokensB = self::tokens($b);

        if (array_intersect($tokensA, $tokensB) !== []) {
            return true;
        }

        $firstA = $tokensA[0] ?? '';
        $firstB = $tokensB[0] ?? '';

        return mb_strlen($firstA) >= 3
            && mb_strlen($firstB) >= 3
            && levenshtein($firstA, $firstB) <= 2;
    }

    public static function normalize(?string $name): string
    {
        $name = mb_strtolower(trim((string) $name));
        $name = str_replace(['-', "'"], ' ', $name);
        $name = self::stripAccents($name);
        $name = preg_replace('/[^a-z\s]/', '', $name) ?? '';

        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }

    public static function isBlank(?string $name): bool
    {
        return self::normalize($name) === '';
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $normalized): array
    {
        $tokens = preg_split('/\s+/', $normalized) ?: [];

        return array_values(array_filter($tokens, fn (string $token) => $token !== ''));
    }

    private static function stripAccents(string $value): string
    {
        return strtr($value, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y', 'ñ' => 'n', 'ç' => 'c',
        ]);
    }
}
