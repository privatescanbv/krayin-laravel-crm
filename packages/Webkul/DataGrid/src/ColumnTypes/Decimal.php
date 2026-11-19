<?php

namespace Webkul\DataGrid\ColumnTypes;

use Illuminate\Database\Query\Expression;
use Webkul\DataGrid\Column;

class Decimal extends Column
{
    /**
     * Process filter.
     */
    public function processFilter($queryBuilder, $requestedValues)
    {
        // The bounds are inlined as numeric literals: SQLite compares a bound (text) parameter against
        // a computed numeric column as text. They are cast to float first, so nothing user-supplied is inlined.
        // `number_range`: [[min, max]] (either side may be empty), same shape as the date range filter.
        if ($this->filterableType === 'number_range' && is_array($requestedValues)) {
            [$min, $max] = array_pad(is_array($requestedValues[0] ?? null) ? $requestedValues[0] : $requestedValues, 2, null);

            return $queryBuilder
                ->when(is_numeric($min), fn ($query) => $query->where($this->columnName, '>=', new Expression((string) (float) $min)))
                ->when(is_numeric($max), fn ($query) => $query->where($this->columnName, '<=', new Expression((string) (float) $max)));
        }

        return $queryBuilder->where(function ($scopeQueryBuilder) use ($requestedValues) {
            if (is_string($requestedValues)) {
                $scopeQueryBuilder->orWhere($this->columnName, $requestedValues);

                return;
            }

            foreach ($requestedValues as $value) {
                $scopeQueryBuilder->orWhere($this->columnName, $value);
            }
        });
    }
}
