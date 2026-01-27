<?php

namespace App\Helpers;

use BackedEnum;

trait ValueNormalizerTrait
{
    /**
     * Normalize datetime-like input to NULL when "empty".
     *
     * Intended for form inputs where an empty datetime can arrive as:
     * - null / '' / 'null' / 'undefined'
     * - "minimum" sentinel dates (e.g. year 0000/0001) from UI widgets
     *
     * Returns the original value for non-string inputs (e.g. DateTimeInterface),
     * and a trimmed string for string inputs that are not considered empty.
     */
    public static function nullableDateTime(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $v = trim($value);

        if ($v === '') {
            return null;
        }

        $lower = strtolower($v);
        if ($lower === 'null' || $lower === 'undefined') {
            return null;
        }

        // MySQL "zero date" / other sentinels.
        if (
            $v === '0000-00-00'
            || $v === '0000-00-00 00:00'
            || $v === '0000-00-00 00:00:00'
        ) {
            return null;
        }

        // Year-first formats: YYYY-MM-DD[ HH:MM[:SS]] or YYYY/MM/DD...
        if (preg_match('/^(?<year>\d{4})[-\/]\d{2}[-\/]\d{2}(?:\s+\d{2}:\d{2}(?::\d{2})?)?$/', $v, $m)) {
            if ((int) $m['year'] <= 1) {
                return null;
            }

            return $v;
        }

        // Day-first formats: DD-MM-YYYY[ HH:MM[:SS]] or DD/MM/YYYY...
        if (preg_match('/^\d{2}[-\/]\d{2}[-\/](?<year>\d{4})(?:\s+\d{2}:\d{2}(?::\d{2})?)?$/', $v, $m)) {
            if ((int) $m['year'] <= 1) {
                return null;
            }

            return $v;
        }

        return $v;
    }

    /**
     * Normalize any value to a string.
     * Handles null, strings, arrays, objects, and other types.
     * Useful for ensuring Vue components and other consumers receive string values.
     */
    public static function toString($value): string
    {
        // If null or empty, return empty string
        if (empty($value) && $value !== '0' && $value !== 0) {
            return '';
        }

        // If already a string, return as is
        if (is_string($value)) {
            return $value;
        }

        // If it's an array, try to extract a meaningful value
        if (is_array($value)) {
            // Try common keys for name/value
            if (isset($value['name'])) {
                return (string) $value['name'];
            }
            if (isset($value['value'])) {
                return (string) $value['value'];
            }
            // If it's an associative array with one value, use that
            if (count($value) === 1) {
                return (string) reset($value);
            }

            // Otherwise, try to convert to string (fallback)
            return (string) json_encode($value);
        }

        // If it's an object, try to get name/value property or convert to string
        if (is_object($value)) {
            if (isset($value->name)) {
                return (string) $value->name;
            }
            if (isset($value->value)) {
                return (string) $value->value;
            }

            // Try to convert to string
            return (string) $value;
        }

        // Fallback: convert to string
        return (string) $value;
    }

    /**
     * Normalize value for comparison.
     */
    public static function normalizeValue($value): string
    {
        if (is_null($value)) {
            return '';
        }

        // Unwrap backed enums to their scalar backing values for comparison
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (is_string($value)) {
            return strtolower(trim($value));
        }

        return strtolower(trim((string) $value));
    }

    /**
     * Format value for display in the UI.
     */
    public function formatValueForDisplay($value, $field): string
    {
        if (is_null($value)) {
            return 'Geen waarde';
        }

        if (in_array($field, ['emails', 'phones'])) {
            if (is_array($value)) {
                $values = $this->extractArrayValues($value);

                return implode(', ', $values) ?: 'Geen waarde';
            }

            return self::toString($value);
        }

        if ($field === 'date_of_birth') {
            $formatted = $this->formatDateForComparison($value);

            return $formatted ?: 'Geen waarde';
        }

        // For enums, return backing value for display
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return self::toString($value);
    }
}
