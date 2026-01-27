<?php

namespace App\Helpers;

trait Comparable
{
    use ValueNormalizerTrait;

    /**
     * Get the field type for proper display handling.
     */
    protected function getFieldType(string $field, $oldValue, $newValue): string
    {
        if ($oldValue === '1' || $newValue === '1' || is_bool($oldValue) || is_bool($newValue)) {
            return 'boolean';
        }
        if (in_array($field, ['emails', 'phones'])) {
            return 'array';
        }

        if ($field === 'address') {
            return 'address';
        }

        return 'text';
    }
}
