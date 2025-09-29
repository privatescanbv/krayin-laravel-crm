<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPeriod implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $start = data_get($value, 'period_start');
        $end = data_get($value, 'period_end');

        if (empty($start)) {
            $fail(__('admin::app.settings.shifts.validation.period_start_required'));

            return;
        }

        // End may be null meaning infinite
        if (! empty($end) && strtotime($end) < strtotime($start)) {
            $fail(__('admin::app.settings.shifts.validation.end_after_start'));
        }
    }
}
