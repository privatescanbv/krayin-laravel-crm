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

        // Accept either NL (d-m-Y) or HTML date (Y-m-d) input
        $isDutchFormat = preg_match('/^(0?[1-9]|[12]\d|3[01])-(0?[1-9]|1[0-2])-\d{4}$/', $value) === 1;
        $isHtmlFormat  = preg_match('/^\d{4}-(0?[1-9]|1[0-2])-(0?[1-9]|[12]\d|3[01])$/', $value) === 1;

        if ($isDutchFormat) {
            $date = DateTime::createFromFormat('d-m-Y', $value);
            return $date && $date->format('d-m-Y') === $value;
        }

        if ($isHtmlFormat) {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            return $date && $date->format('Y-m-d') === $value;
        }

        return false;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return 'Het :attribute veld moet een geldige datum zijn in het formaat dd-mm-yyyy (bijv. 11-07-2025).';
    }
}
