<?php

namespace App\Casts;

use App\Enums\ActivityStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ActivityStatusCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?ActivityStatus
    {
        if ($value === null) {
            return null;
        }

        $mapped = $value === 'new' ? 'active' : $value;

        try {
            return ActivityStatus::from($mapped);
        } catch (\ValueError) {
            // Fallback to active if invalid legacy value encountered
            return ActivityStatus::ACTIVE;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof ActivityStatus) {
            return $value->value;
        }

        if (is_string($value)) {
            return $value === 'new' ? 'active' : $value;
        }

        return null;
    }
}

