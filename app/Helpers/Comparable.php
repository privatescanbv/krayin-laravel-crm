<?php

namespace App\Helpers;

trait Comparable
{
    use ValueNormalizer;

    /**
     * Get the field type for proper display handling.
     */
    protected function getFieldType(string $field): string
    {
        if (in_array($field, ['emails', 'phones'])) {
            return 'array';
        }

        if ($field === 'address') {
            return 'address';
        }

        return 'text';
    }
}
