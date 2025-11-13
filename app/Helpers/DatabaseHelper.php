<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class DatabaseHelper
{
    /**
     * Get database-specific string concatenation expression.
     *
     * @param  array  $columns  Array of column names or expressions to concatenate
     * @param  string  $separator  Separator between columns (default: ' ')
     * @param  bool  $trim  Whether to wrap with TRIM (default: false)
     * @param  bool  $coalesce  Whether to wrap columns with COALESCE (default: false)
     * @param  string|null  $alias  Optional alias for the expression
     */
    public static function concat(array $columns, string $separator = ' ', bool $trim = false, bool $coalesce = false, ?string $alias = null): string
    {
        $driver = DB::connection()->getDriverName();

        // Handle COALESCE if needed
        $processedColumns = $coalesce
            ? array_map(fn ($col) => "COALESCE({$col}, '')", $columns)
            : $columns;

        // Build concatenation expression based on database driver
        if ($driver === 'sqlite' || $driver === 'pgsql') {
            // SQLite and PostgreSQL use || operator
            $expression = implode(" || '{$separator}' || ", $processedColumns);
        } else {
            // MySQL/MariaDB use CONCAT function
            $quotedSeparator = "'{$separator}'";
            $expression = 'CONCAT('.implode(', ', array_merge(
                array_map(fn ($col) => "{$col}, {$quotedSeparator}", array_slice($processedColumns, 0, -1)),
                [end($processedColumns)]
            )).')';
        }

        // Apply TRIM if needed
        if ($trim) {
            $expression = "TRIM({$expression})";
        }

        // Add alias if provided
        if ($alias) {
            $expression .= " as {$alias}";
        }

        return $expression;
    }

    /**
     * Concatenate user first_name and last_name columns.
     * Convenience method for the most common use case.
     *
     * @param  string  $tablePrefix  Table prefix (e.g., 'users.' or '')
     * @param  string|null  $alias  Optional alias (default: null)
     */
    public static function concatUserName(string $tablePrefix = 'users.', ?string $alias = null): string
    {
        return self::concat(
            ["{$tablePrefix}first_name", "{$tablePrefix}last_name"],
            ' ',
            false,
            false,
            $alias
        );
    }
}
