<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Webkul\User\Models\User;

class ActiveUser implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return; // Allow null/empty values
        }

        $user = User::find($value);

        if (! $user || $user->status != 1) {
            $fail('De geselecteerde gebruiker is niet actief.');
        }
    }
}
