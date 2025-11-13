<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class RequestHelper
{
    /**
     * Filter and normalize array input values from request.
     * Removes empty values and converts to integers.
     */
    public static function filterIntegerArray(Request $request, string $key, array $default = []): array
    {
        $values = $request->input($key, $default);

        if (! is_array($values)) {
            return [];
        }

        return array_filter(
            array_map('intval', $values),
            fn ($id) => $id > 0
        );
    }

    /**
     * Sync a many-to-many relationship with filtered array values from request.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     */
    public static function syncRelationFromRequest($model, string $relationName, Request $request, string $requestKey, array $default = []): void
    {
        $filteredValues = self::filterIntegerArray($request, $requestKey, $default);
        $model->{$relationName}()->sync($filteredValues);
    }
}
