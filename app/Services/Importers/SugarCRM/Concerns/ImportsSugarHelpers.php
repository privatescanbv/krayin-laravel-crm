<?php

namespace App\Services\Importers\SugarCRM\Concerns;

use App\Enums\ActivityStatus;
use App\Services\ActivityStatusService;
use Illuminate\Support\Carbon;
use Webkul\Activity\Models\Activity;

trait ImportsSugarHelpers
{
    /**
     * Parse SugarCRM date format to application timezone.
     */
    protected function parseSugarDate($value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            if ($value instanceof \DateTimeInterface) {
                return Carbon::instance($value)
                    ->setTimezone(config('app.timezone'))
                    ->format('Y-m-d H:i:s');
            }

            return Carbon::parse((string) $value, config('app.timezone'))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Create an entity with provided timestamps without auto-overrides.
     *
     * @param  class-string  $modelClass
     * @return mixed
     */
    protected function createEntityWithTimestamps(string $modelClass, array $data, array $timestamps = [])
    {
        $entity = new $modelClass($data);
        $entity->timestamps = false;

        if (! empty($timestamps['created_at'])) {
            $entity->setAttribute('created_at', $timestamps['created_at']);
        }
        if (! empty($timestamps['updated_at'])) {
            $entity->setAttribute('updated_at', $timestamps['updated_at']);
        }

        $entity->saveQuietly();
        $entity->timestamps = true;

        return $entity;
    }

    /**
     * Synchronize Activity.status with is_done and schedule dates without changing timestamps.
     */
    protected function syncActivityStatus(Activity $activity): void
    {
        $activity->timestamps = false;
        if ($activity->is_done) {
            $activity->status = ActivityStatus::DONE;
        } else {
            $activity->status = ActivityStatusService::computeStatus(
                $activity->schedule_from,
                $activity->schedule_to,
                ActivityStatus::ACTIVE
            );
        }
        $activity->saveQuietly();
        $activity->timestamps = true;
    }
}
