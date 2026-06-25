<?php

namespace App\Services\Activities;

use App\Services\ActivityStatusService;
use Webkul\Activity\Models\Activity;

class ActivityRescheduleService
{
    /**
     * Move the managed deadline forward. schedule_from is legacy-only and clamped when invalid.
     */
    public function reschedule(Activity $activity, int $days): void
    {
        $activity->schedule_to = now()->addDays($days);

        if ($activity->schedule_from?->gt($activity->schedule_to)) {
            $activity->schedule_from = $activity->schedule_to->copy();
        }

        $activity->save();

        $computed = ActivityStatusService::computeStatus(null, $activity->schedule_to, $activity->status);
        if ($computed->value !== ($activity->status?->value ?? null)) {
            $activity->status = $computed;
            $activity->save();
        }
    }
}
