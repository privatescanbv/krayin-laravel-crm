<?php

namespace App\Validators;

use DateTime;
use Illuminate\Contracts\Validation\Rule;

class DateValidator implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (empty($value)) {
            return true; // Allow empty values
        }

        // Check if the value matches the d-m-Y format
        if (! preg_match('/^(0?[1-9]|[12]\d|3[01])-(0?[1-9]|1[0-2])-\d{4}$/', $value)) {
            return false;
        }

        // Parse the date to check if it's a valid date
        $date = DateTime::createFromFormat('d-m-Y', $value);

        return $date && $date->format('d-m-Y') === $value;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'Het :attribute veld moet een geldige datum zijn in het formaat dd-mm-yyyy (bijv. 11-07-2025).';
    }
}
