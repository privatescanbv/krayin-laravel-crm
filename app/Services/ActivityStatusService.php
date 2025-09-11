<?php

namespace App\Services;

use App\Enums\ActivityStatus;
use Carbon\CarbonInterface;

class ActivityStatusService
{
    public static function computeStatus(?CarbonInterface $from, ?CarbonInterface $to, ?ActivityStatus $current = null): ActivityStatus
    {
        $now = now();

        // Respect explicit DONE: once done, keep done
        if ($current === ActivityStatus::DONE) {
            return ActivityStatus::DONE;
        }

        if ($current === ActivityStatus::IN_PROGRESS) {
            return ActivityStatus::IN_PROGRESS;
        }

        // If both dates missing, default active
        if (! $from && ! $to) {
            return ActivityStatus::ACTIVE;
        }

        // Determine window relative to now
        $start = $from ?? $to;
        $end = $to ?? $from;

        if ($end && $end->lt($now)) {
            return ActivityStatus::EXPIRED;
        }

        if ($start && $start->gt($now)) {
            return ActivityStatus::ON_HOLD;
        }

        return ActivityStatus::ACTIVE;
    }
}
