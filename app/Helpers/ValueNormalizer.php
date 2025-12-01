<?php

namespace App\Helpers;

class ValueNormalizer
{
    /**
     * Normalize any value to a string.
     * Handles null, strings, arrays, objects, and other types.
     * Useful for ensuring Vue components and other consumers receive string values.
     *
     * @param  mixed  $value
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
}
