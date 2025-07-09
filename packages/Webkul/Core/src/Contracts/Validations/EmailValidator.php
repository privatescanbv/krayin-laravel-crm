<?php

namespace Webkul\Core\Contracts\Validations;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class EmailValidator implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Allow empty values, let required rule handle this
        }

        // Check if email contains exactly one @ symbol
        if (substr_count($value, '@') !== 1) {
            $fail(trans('admin::app.validations.message.email-single-at', ['attribute' => $attribute]));
            return;
        }

        // Split email into local and domain parts
        [$local, $domain] = explode('@', $value);

        // Validate local part (before @)
        if (empty($local) || strlen($local) > 64) {
            $fail(trans('admin::app.validations.message.email-local-part', ['attribute' => $attribute]));
            return;
        }

        // Validate domain part (after @)
        if (empty($domain) || strlen($domain) > 255) {
            $fail(trans('admin::app.validations.message.email-domain-part', ['attribute' => $attribute]));
            return;
        }

        // Check for invalid characters in local part
        if (!preg_match('/^[a-zA-Z0-9._%+-]+$/', $local)) {
            $fail(trans('admin::app.validations.message.email-local-invalid-chars', ['attribute' => $attribute]));
            return;
        }

        // Check for invalid characters in domain part
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $domain)) {
            $fail(trans('admin::app.validations.message.email-domain-invalid-chars', ['attribute' => $attribute]));
            return;
        }

        // Check if local part starts or ends with a dot
        if (str_starts_with($local, '.') || str_ends_with($local, '.')) {
            $fail(trans('admin::app.validations.message.email-local-dot-position', ['attribute' => $attribute]));
            return;
        }

        // Check for consecutive dots in local part
        if (str_contains($local, '..')) {
            $fail(trans('admin::app.validations.message.email-local-consecutive-dots', ['attribute' => $attribute]));
            return;
        }

        // Check if domain has at least one dot (for TLD)
        if (!str_contains($domain, '.')) {
            $fail(trans('admin::app.validations.message.email-domain-no-tld', ['attribute' => $attribute]));
            return;
        }

        // Check if domain starts or ends with a dot or hyphen
        if (str_starts_with($domain, '.') || str_ends_with($domain, '.') ||
            str_starts_with($domain, '-') || str_ends_with($domain, '-')) {
            $fail(trans('admin::app.validations.message.email-domain-invalid-start-end', ['attribute' => $attribute]));
            return;
        }

        // Check for consecutive dots in domain part
        if (str_contains($domain, '..')) {
            $fail(trans('admin::app.validations.message.email-domain-consecutive-dots', ['attribute' => $attribute]));
            return;
        }

        // Validate TLD (top-level domain) - should be at least 2 characters
        $domainParts = explode('.', $domain);
        $tld = end($domainParts);
        if (strlen($tld) < 2) {
            $fail(trans('admin::app.validations.message.email-tld-too-short', ['attribute' => $attribute]));
            return;
        }

        // Use PHP's filter_var as final validation
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $fail(trans('admin::app.validations.message.email-invalid-format', ['attribute' => $attribute]));
        }
    }
}
