<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class AbstractSugarCRMImport extends Command
{
    /**
     * Parse SugarCRM date format to our timezone
     */
    protected function parseSugarDate($value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            // Accept Carbon, DateTimeInterface, or string
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
            }

            return Carbon::parse((string) $value, 'UTC')
                ->setTimezone(config('app.timezone'))
                ->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Create an entity with proper timestamps from SugarCRM data
     *
     * @param  string  $modelClass  The model class to create
     * @param  array  $data  The entity data
     * @param  array  $timestamps  The timestamps to set (created_at, updated_at)
     * @return mixed The created entity
     */
    protected function createEntityWithTimestamps(string $modelClass, array $data, array $timestamps = [])
    {
        // Create entity without timestamps to avoid auto-override
        $entity = new $modelClass($data);
        $entity->timestamps = false;

        // Set custom timestamps if provided
        if (! empty($timestamps['created_at'])) {
            $entity->setAttribute('created_at', $timestamps['created_at']);
        }
        if (! empty($timestamps['updated_at'])) {
            $entity->setAttribute('updated_at', $timestamps['updated_at']);
        }

        // Save without triggering timestamps
        $entity->saveQuietly();

        // Re-enable timestamps for future operations
        $entity->timestamps = true;

        return $entity;
    }

    /**
     * Test database connection
     */
    protected function testConnection(string $connection): void
    {
        $this->info('Testing database connection...');
        DB::connection($connection)->getPdo();
        $this->info('✓ Database connection successful');
    }

    /**
     * Log error with context
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->error("\n{$message}");
        Log::error($message, $context);
    }
}
