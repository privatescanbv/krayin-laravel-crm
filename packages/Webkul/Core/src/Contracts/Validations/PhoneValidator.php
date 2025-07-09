<?php

namespace Webkul\Core\Contracts\Validations;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class PhoneValidator implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Allow empty values, let required rule handle this
        }

        // Check if phone number starts with +
        if (!str_starts_with($value, '+')) {
            $fail(trans('admin::app.validations.message.phone-must-start-with-plus', ['attribute' => $attribute]));
            return;
        }

        // Remove the + for further validation
        $phoneWithoutPlus = substr($value, 1);

        // Check if the rest contains only digits
        if (!ctype_digit($phoneWithoutPlus)) {
            $fail(trans('admin::app.validations.message.phone-only-digits-after-plus', ['attribute' => $attribute]));
            return;
        }

        // Check minimum length (country code + number should be at least 8 digits)
        if (strlen($phoneWithoutPlus) < 8) {
            $fail(trans('admin::app.validations.message.phone-too-short', ['attribute' => $attribute]));
            return;
        }

        // Check maximum length (reasonable maximum for international numbers)
        if (strlen($phoneWithoutPlus) > 15) {
            $fail(trans('admin::app.validations.message.phone-too-long', ['attribute' => $attribute]));
            return;
        }

        // Check if it's a valid format (country code should be 1-4 digits)
        if (!preg_match('/^[1-9]\d{7,14}$/', $phoneWithoutPlus)) {
            $fail(trans('admin::app.validations.message.phone-invalid-format', ['attribute' => $attribute]));
            return;
        }

        // Additional validation: country code should start with 1-9 (not 0)
        if (str_starts_with($phoneWithoutPlus, '0')) {
            $fail(trans('admin::app.validations.message.phone-invalid-country-code', ['attribute' => $attribute]));
        }
    }
}
