<?php

namespace App\Casts;

use App\Enums\CallStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class NullableCallStatusCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): CallStatus|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CallStatus::tryFrom($value) ?? $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value instanceof CallStatus) {
            $value = $value->value;
        }

        return [$key => $value];
    }
}
