<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use Carbon\CarbonInterface;

class ActivityStatusService
{
    public static function computeStatus(?CarbonInterface $from, ?CarbonInterface $to, ?ActivityStatus $current = null): ActivityStatus
    {
        // Respect explicit DONE: once done, keep done
        if ($current === ActivityStatus::DONE) {
            return ActivityStatus::DONE;
        }

        if ($current === ActivityStatus::IN_PROGRESS) {
            return ActivityStatus::IN_PROGRESS;
        }

        // schedule_from is intentionally ignored; schedule_to is the only managed deadline.
        if (! $to) {
            return ActivityStatus::ACTIVE;
        }

        if ($to->lt(now())) {
            return ActivityStatus::EXPIRED;
        }

        return ActivityStatus::ACTIVE;
    }
}
