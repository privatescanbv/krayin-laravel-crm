<?php

namespace App\Services;

use Carbon\Carbon;
use Throwable;

class StageTransitionAttributes
{
    /**
     * Resolve closed_at for a stage transition.
     * Returns Carbon when stage is won/lost (defaults to now if request value empty).
     * Returns null when stage is neither won nor lost (reset).
     *
     * @param  object  $stage  Stage model with is_won, is_lost
     * @param  string|null  $requestClosedAt  Request value (typically d-m-Y from frontend)
     */
    public static function resolveClosedAt(object $stage, ?string $requestClosedAt): ?Carbon
    {
        if (! static::isWonOrLost($stage)) {
            return null;
        }

        if ($requestClosedAt) {
            try {
                return Carbon::createFromFormat('d-m-Y', $requestClosedAt)->startOfDay();
            } catch (Throwable $e) {
                return Carbon::parse($requestClosedAt);
            }
        }

        return now();
    }

    /**
     * Resolve lost_reason for a stage transition.
     * Returns request value when stage is lost, null otherwise.
     *
     * @param  object  $stage  Stage model with is_lost
     */
    public static function resolveLostReason(object $stage, ?string $requestLostReason): ?string
    {
        return $stage->is_lost ? $requestLostReason : null;
    }

    /**
     * Whether the stage is won or lost.
     */
    public static function isWonOrLost(object $stage): bool
    {
        return (bool) ($stage->is_won ?? false) || (bool) ($stage->is_lost ?? false);
    }
}
